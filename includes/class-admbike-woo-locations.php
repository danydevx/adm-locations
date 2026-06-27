<?php
/**
 * Main plugin bootstrap.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations {

	public const OPTION_NO_COVERAGE_MESSAGE = 'admbike_no_coverage_message';

	/**
	 * Repository instances.
	 *
	 * @var array<string, object>
	 */
	protected $repositories = array();

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'plugins_loaded', array( 'ADMBike_Woo_Locations_Installer', 'maybe_upgrade' ), 5 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_filter( 'woocommerce_states', array( $this, 'limit_woocommerce_states' ), 20 );
		add_filter( 'gettext', array( $this, 'filter_woocommerce_gettext' ), 20, 3 );
		add_filter( 'woocommerce_no_shipping_available_html', array( $this, 'filter_no_shipping_available_html' ), 20 );
		add_filter( 'woocommerce_cart_no_shipping_available_html', array( $this, 'filter_no_shipping_available_html' ), 20 );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'admbike-woo-locations', false, dirname( plugin_basename( ADMBIKE_WOO_LOCATIONS_FILE ) ) . '/languages' );
	}

	/**
	 * Get the global no-coverage message.
	 *
	 * @return string
	 */
	public function get_no_coverage_message() {
		$default = __( 'No disponible en tu zona', 'admbike-woo-locations' );
		$saved   = $this->get_saved_no_coverage_message();
		$message = '' !== $saved ? $saved : $default;

		return apply_filters( 'admbike_woo_locations_no_coverage_message', $message );
	}

	/**
	 * Get the saved WooCommerce no-shipping replacement message.
	 *
	 * @return string
	 */
	public function get_saved_no_coverage_message() {
		$message = get_option( self::OPTION_NO_COVERAGE_MESSAGE, '' );

		return is_string( $message ) ? trim( $message ) : '';
	}

	/**
	 * Filter WooCommerce's no-shipping HTML output.
	 *
	 * @param string $html Existing HTML.
	 * @return string
	 */
	public function filter_no_shipping_available_html( $html ) {
		$message = $this->get_saved_no_coverage_message();

		if ( '' === trim( (string) $message ) ) {
			return $html;
		}

		return '<p class="woocommerce-info">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Replace WooCommerce's default no-shipping text with the saved message.
	 *
	 * @param string $translation Translated text.
	 * @param string $text Original text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public function filter_woocommerce_gettext( $translation, $text, $domain ) {
		if ( 'woocommerce' !== $domain ) {
			return $translation;
		}

		$targets = array(
			'No shipping options are available for this address. Please verify the address is correct or try a different address.',
			'No shipping options are available for this address.',
			'No shipping options were found for this address. Please verify the address is correct or try a different address.',
			'No shipping options were found for this address.',
		);

		if ( ! in_array( $text, $targets, true ) ) {
			return $translation;
		}

		$message = $this->get_saved_no_coverage_message();
		if ( '' === $message ) {
			return $translation;
		}

		return wp_strip_all_tags( $message );
	}

	/**
	 * Limit WooCommerce checkout states to those used by active shipping rules.
	 *
	 * @param array<string, array<string, string>> $states States by country.
	 * @return array<string, array<string, string>>
	 */
	public function limit_woocommerce_states( $states ) {
		if ( ! is_array( $states ) || ! isset( $states['MX'] ) || ! function_exists( 'admbike_woo_locations' ) ) {
			return $states;
		}

		$plugin = admbike_woo_locations();
		if ( ! $plugin instanceof ADMBike_Woo_Locations ) {
			return $states;
		}

		$active_states = $plugin->get_active_rule_states();
		if ( empty( $active_states ) ) {
			return $states;
		}

		$filtered = array();
		foreach ( $states['MX'] as $code => $name ) {
			if ( isset( $active_states[ $code ] ) ) {
				$filtered[ $code ] = $name;
			}
		}

		if ( ! empty( $filtered ) ) {
			$states['MX'] = $filtered;
		}

		return $states;
	}

	/**
	 * Get the states repository.
	 *
	 * @return ADMBike_Woo_Locations_State_Repository
	 */
	public function states() {
		return $this->get_repository( 'states', 'ADMBike_Woo_Locations_State_Repository' );
	}

	/**
	 * Get the municipalities repository.
	 *
	 * @return ADMBike_Woo_Locations_Municipality_Repository
	 */
	public function municipalities() {
		return $this->get_repository( 'municipalities', 'ADMBike_Woo_Locations_Municipality_Repository' );
	}

	/**
	 * Get the postcodes repository.
	 *
	 * @return ADMBike_Woo_Locations_Postcode_Repository
	 */
	public function postcodes() {
		return $this->get_repository( 'postcodes', 'ADMBike_Woo_Locations_Postcode_Repository' );
	}

	/**
	 * Get the shipping rules repository.
	 *
	 * @return ADMBike_Woo_Locations_Shipping_Rule_Repository
	 */
	public function shipping_rules() {
		return $this->get_repository( 'shipping_rules', 'ADMBike_Woo_Locations_Shipping_Rule_Repository' );
	}

	/**
	 * Get active state codes referenced by shipping rules.
	 *
	 * @return array<string, string>
	 */
	public function get_active_rule_states() {
		$states = array();
		$rules  = $this->shipping_rules()->get_active_rules();

		foreach ( $rules as $rule ) {
			$state_id = absint( $rule['state_id'] ?? 0 );
			if ( $state_id <= 0 ) {
				continue;
			}

			$state = $this->states()->get_by_id( $state_id );
			if ( $state && ! empty( $state['code'] ) ) {
				$states[ strtoupper( (string) $state['code'] ) ] = (string) $state['name'];
			}
		}

		return $states;
	}

	/**
	 * Get frontend-visible active states limited to states used by rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_frontend_states() {
		$active_states = $this->get_active_rule_states();
		$states        = $this->states()->get_active_states();

		if ( empty( $active_states ) ) {
			return $states;
		}

		$filtered = array();
		foreach ( $states as $state ) {
			if ( ! empty( $state['code'] ) && isset( $active_states[ strtoupper( (string) $state['code'] ) ] ) ) {
				$filtered[] = $state;
			}
		}

		return ! empty( $filtered ) ? $filtered : $states;
	}

	/**
	 * Get frontend-visible municipalities limited to active-rule states.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_frontend_municipalities() {
		$active_states = array_keys( $this->get_active_rule_states() );
		$munis         = $this->municipalities()->get_items( array( 'is_active' => 1 ), 'name ASC' );

		if ( empty( $active_states ) ) {
			return $munis;
		}

		$filtered = array();
		foreach ( $munis as $muni ) {
			$state = $this->states()->get_by_id( absint( $muni['state_id'] ?? 0 ) );
			if ( $state && ! empty( $state['code'] ) && in_array( strtoupper( (string) $state['code'] ), $active_states, true ) ) {
				$filtered[] = $muni;
			}
		}

		return ! empty( $filtered ) ? $filtered : $munis;
	}

	/**
	 * Get frontend-visible postcodes limited to active-rule states.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_frontend_postcodes() {
		$active_states = array_keys( $this->get_active_rule_states() );
		$pcs           = $this->postcodes()->get_items( array( 'is_active' => 1 ), 'postcode ASC' );

		if ( empty( $active_states ) ) {
			return $pcs;
		}

		$filtered = array();
		foreach ( $pcs as $pc ) {
			$state = $this->states()->get_by_id( absint( $pc['state_id'] ?? 0 ) );
			if ( $state && ! empty( $state['code'] ) && in_array( strtoupper( (string) $state['code'] ), $active_states, true ) ) {
				$filtered[] = $pc;
			}
		}

		return ! empty( $filtered ) ? $filtered : $pcs;
	}

	/**
	 * Get a repository instance.
	 *
	 * @param string $key Repository key.
	 * @param string $class_name Repository class name.
	 * @return object
	 */
	protected function get_repository( $key, $class_name ) {
		if ( ! isset( $this->repositories[ $key ] ) ) {
			$this->repositories[ $key ] = new $class_name();
		}

		return $this->repositories[ $key ];
	}
}
