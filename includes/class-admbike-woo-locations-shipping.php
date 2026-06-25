<?php
/**
 * Shipping integration loader for ADM Bike Woo Locations.
 *
 * Registers the custom shipping method with WooCommerce.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Shipping {

	/**
	 * Repository instances.
	 *
	 * @var array<string, object>
	 */
	protected $repositories = array();

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );
		add_action( 'woocommerce_load_shipping_methods', array( $this, 'load_shipping_methods' ) );
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'apply_checkout_location_to_shipping_packages' ), 5 );
		add_filter( 'woocommerce_package_rates', array( $this, 'inject_direct_rate' ), 5, 2 );
		add_filter( 'woocommerce_package_rates', array( $this, 'prefer_plugin_rates' ), 100, 2 );
		add_filter( 'woocommerce_shipping_method_add_rate', array( $this, 'maybe_set_rate_description' ), 10, 3 );
	}

	/**
	 * Register the custom shipping method.
	 *
	 * @param array<string, string> $methods Shipping methods.
	 * @return array<string, string>
	 */
	public function register_shipping_method( $methods ) {
		$methods['admbike_locations'] = 'ADMBike_Woo_Locations_Shipping_Method';
		return $methods;
	}

	/**
	 * Ensure our shipping method is loaded.
	 *
	 * @param array<int, string>|null $package Shipping package (only in WC 8.3+).
	 * @return void
	 */
	public function load_shipping_methods( $package = null ) {
		if ( ! function_exists( 'wc_get_shipping_methods' ) ) {
			return;
		}

		$zones = WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone ) {
			$zone_methods = $zone->get_shipping_methods( true );

			foreach ( $zone_methods as $method ) {
				if ( is_a( $method, 'ADMBike_Woo_Locations_Shipping_Method' ) ) {
					continue;
				}

				if ( isset( $method->id ) && 'admbike_locations' === $method->id ) {
					continue;
				}
			}
		}
	}

	/**
	 * Get the states repository.
	 *
	 * @return ADMBike_Woo_Locations_State_Repository
	 */
	protected function states_repo() {
		if ( ! isset( $this->repositories['states'] ) ) {
			$this->repositories['states'] = new ADMBike_Woo_Locations_State_Repository();
		}

		return $this->repositories['states'];
	}

	/**
	 * Get the shipping rules repository.
	 *
	 * @return ADMBike_Woo_Locations_Shipping_Rule_Repository
	 */
	protected function shipping_rules_repo() {
		if ( ! isset( $this->repositories['shipping_rules'] ) ) {
			$this->repositories['shipping_rules'] = new ADMBike_Woo_Locations_Shipping_Rule_Repository();
		}

		return $this->repositories['shipping_rules'];
	}

	/**
	 * Get the municipalities repository.
	 *
	 * @return ADMBike_Woo_Locations_Municipality_Repository
	 */
	protected function municipalities_repo() {
		if ( ! isset( $this->repositories['municipalities'] ) ) {
			$this->repositories['municipalities'] = new ADMBike_Woo_Locations_Municipality_Repository();
		}

		return $this->repositories['municipalities'];
	}

	/**
	 * Apply the selected ADM Bike location to shipping packages.
	 *
	 * This gives WooCommerce's native shipping zones a real country/state/postcode
	 * destination so shipping methods and rates can resolve normally.
	 *
	 * @param array<int, array<string, mixed>> $packages Shipping packages.
	 * @return array<int, array<string, mixed>>
	 */
	public function apply_checkout_location_to_shipping_packages( $packages ) {
		if ( ! is_array( $packages ) || empty( $packages ) ) {
			return $packages;
		}

		$location = $this->get_session_location();
		if ( empty( $location['state_id'] ) && empty( $location['postcode'] ) && empty( $location['municipality_id'] ) ) {
			return $packages;
		}

		$state = ! empty( $location['state_id'] ) ? $this->states_repo()->get_by_id( (int) $location['state_id'] ) : null;
		$municipality = ! empty( $location['municipality_id'] ) ? $this->municipalities_repo()->get_by_id( (int) $location['municipality_id'] ) : null;

		foreach ( $packages as $index => $package ) {
			if ( ! isset( $packages[ $index ]['destination'] ) || ! is_array( $packages[ $index ]['destination'] ) ) {
				$packages[ $index ]['destination'] = array();
			}

			$packages[ $index ]['destination']['country']  = 'MX';
			$packages[ $index ]['destination']['state']    = isset( $state['code'] ) ? (string) $state['code'] : ( $packages[ $index ]['destination']['state'] ?? '' );
			$packages[ $index ]['destination']['city']     = isset( $municipality['name'] ) ? (string) $municipality['name'] : ( $packages[ $index ]['destination']['city'] ?? '' );
			$packages[ $index ]['destination']['postcode'] = ! empty( $location['postcode'] ) ? (string) $location['postcode'] : ( $packages[ $index ]['destination']['postcode'] ?? '' );
		}

		return $packages;
	}

	/**
	 * Get the current session location.
	 *
	 * @return array<string, int|string>
	 */
	protected function get_session_location() {
		$location = array(
			'state_id' => 0,
			'municipality_id' => 0,
			'postcode' => '',
		);

		if ( isset( WC()->session ) && WC()->session->has_session() ) {
			$session_location = (array) WC()->session->get( 'admbike_checkout_location', array() );
			if ( ! empty( $session_location ) ) {
				$location['state_id']        = ! empty( $session_location['state_id'] ) ? absint( $session_location['state_id'] ) : 0;
				$location['municipality_id'] = ! empty( $session_location['municipality_id'] ) ? absint( $session_location['municipality_id'] ) : 0;
				$location['postcode']        = ! empty( $session_location['postcode'] ) ? sanitize_text_field( (string) $session_location['postcode'] ) : '';
			}
		}

		return $location;
	}

	/**
	 * Inject a direct ADM Bike rate when WooCommerce has no zone-based rate.
	 *
	 * This keeps the plugin working even if the shipping method has not been
	 * added to a WooCommerce shipping zone.
	 *
	 * @param array<string, WC_Shipping_Rate> $rates Package rates.
	 * @param array<string, mixed>            $package Shipping package.
	 * @return array<string, WC_Shipping_Rate>
	 */
	public function inject_direct_rate( $rates, $package ) {
		if ( ! is_array( $rates ) ) {
			return $rates;
		}

		foreach ( $rates as $rate_id => $rate ) {
			if ( 0 === strpos( (string) $rate_id, 'admbike_locations' ) ) {
				return $rates;
			}
		}

		$rate = $this->build_direct_rate( $package );
		if ( null === $rate ) {
			return $rates;
		}

		$package_id = isset( $package['package_id'] ) ? (string) $package['package_id'] : '0';
		if ( isset( WC()->session ) && WC()->session ) {
			$chosen_methods                 = (array) WC()->session->get( 'chosen_shipping_methods', array() );
			$chosen_methods[ $package_id ] = $rate->get_id();
			WC()->session->set( 'chosen_shipping_methods', $chosen_methods );
		}

		$rates[ $rate->get_id() ] = $rate;

		return $rates;
	}

	/**
	 * Build a direct shipping rate from the current checkout context.
	 *
	 * @param array<string, mixed> $package Shipping package.
	 * @return WC_Shipping_Rate|null
	 */
	protected function build_direct_rate( $package ) {
		$location = $this->get_checkout_location( $package );
		$postcode  = isset( $location['postcode'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $location['postcode'] ) : '';
		$state_id  = isset( $location['state_id'] ) ? absint( $location['state_id'] ) : 0;
		$municipality_id = isset( $location['municipality_id'] ) ? absint( $location['municipality_id'] ) : 0;

		if ( '' === $postcode && $state_id <= 0 && $municipality_id <= 0 ) {
			return null;
		}

		$rules = $this->shipping_rules_repo()->get_applicable_rules( $state_id, $municipality_id, $postcode );
		if ( empty( $rules ) ) {
			return null;
		}

		$applied_rule = $rules[0];
		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_UNAVAILABLE === $applied_rule['rule_type'] ) {
			return null;
		}

		$cost = 0.0;
		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID === $applied_rule['rule_type'] ) {
			$cost = (float) ( $applied_rule['shipping_cost'] ?? 0 );
		}

		$label = ! empty( $applied_rule['display_title'] ) ? sanitize_text_field( (string) $applied_rule['display_title'] ) : $this->title;
		$customer_message = ! empty( $applied_rule['customer_message'] ) ? sanitize_textarea_field( (string) $applied_rule['customer_message'] ) : '';

		$rate_id = sprintf( '%s:%d:direct', $this->id, absint( $this->instance_id ) );
		$rate    = new WC_Shipping_Rate( $rate_id, $label, $cost, array(), $this->id, $this->instance_id, 'taxable', $customer_message );
		$rate->add_meta_data( '_admbike_rule_id', (int) ( $applied_rule['id'] ?? 0 ) );
		$rate->add_meta_data( '_admbike_rule_type', (string) ( $applied_rule['rule_type'] ?? '' ) );
		$rate->add_meta_data( '_admbike_match_type', (string) ( $applied_rule['match_type'] ?? '' ) );
		$rate->add_meta_data( '_admbike_rule_priority', isset( $applied_rule['priority'] ) ? (int) $applied_rule['priority'] : 100 );
		$rate->add_meta_data( '_admbike_rule_specificity', $this->get_match_specificity( (string) ( $applied_rule['match_type'] ?? '' ) ) );
		$rate->add_meta_data( '_admbike_postcode', $postcode );
		$rate->add_meta_data( '_admbike_state_id', $state_id );
		$rate->add_meta_data( '_admbike_municipality_id', $municipality_id );
		$rate->add_meta_data( '_admbike_display_title', $label );
		$rate->add_meta_data( '_admbike_customer_message', $customer_message );

		return $rate;
	}

	/**
	 * Apply a customer message as rate description for the shipping method flow.
	 *
	 * @param WC_Shipping_Rate        $rate Rate object.
	 * @param array<string, mixed>     $args Rate args.
	 * @param WC_Shipping_Method|null  $method Shipping method.
	 * @return WC_Shipping_Rate
	 */
	public function maybe_set_rate_description( $rate, $args, $method ) {
		if ( ! $rate instanceof WC_Shipping_Rate || ! is_object( $method ) || ! isset( $method->id ) || 'admbike_locations' !== $method->id ) {
			return $rate;
		}

		if ( ! empty( $args['description'] ) && is_callable( array( $rate, 'set_description' ) ) ) {
			$rate->set_description( sanitize_textarea_field( (string) $args['description'] ) );
		}

		return $rate;
	}

	/**
	 * Get the current checkout location from the session or shipping package.
	 *
	 * @param array<string, mixed> $package Shipping package.
	 * @return array<string, int|string>
	 */
	protected function get_checkout_location( $package ) {
		$location = $this->get_session_location();

		if ( '' !== $location['postcode'] || $location['state_id'] > 0 || $location['municipality_id'] > 0 ) {
			return $location;
		}

		$destination = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
		$postcode    = isset( $destination['postcode'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $destination['postcode'] ) : '';
		$state_code   = isset( $destination['state'] ) ? sanitize_text_field( (string) $destination['state'] ) : '';
		$city         = isset( $destination['city'] ) ? sanitize_text_field( (string) $destination['city'] ) : '';

		if ( '' !== $postcode ) {
			$location['postcode'] = $postcode;
		}

		if ( '' !== $state_code ) {
			$states_repo = $this->states_repo();
			$state = method_exists( $states_repo, 'get_by_code_or_name' )
				? $states_repo->get_by_code_or_name( $state_code )
				: $states_repo->get_by_code( $state_code );
			if ( $state && ! empty( $state['id'] ) ) {
				$location['state_id'] = absint( $state['id'] );
			}
		}

		if ( empty( $location['municipality_id'] ) && ! empty( $location['state_id'] ) && '' !== $city ) {
			$municipality = $this->municipalities_repo()->get_by_state_and_name( (int) $location['state_id'], $city );
			if ( $municipality && ! empty( $municipality['id'] ) ) {
				$location['municipality_id'] = absint( $municipality['id'] );
			}
		}

		if ( empty( $location['municipality_id'] ) && '' !== $city ) {
			$municipality = $this->municipalities_repo()->get_by_name( $city );
			if ( $municipality && ! empty( $municipality['id'] ) ) {
				$location['municipality_id'] = absint( $municipality['id'] );
				if ( empty( $location['state_id'] ) && ! empty( $municipality['state_id'] ) ) {
					$location['state_id'] = absint( $municipality['state_id'] );
				}
			}
		}

		return $location;
	}

	/**
	 * Prefer the plugin's shipping rates when they are available.
	 *
	 * This keeps WooCommerce's default rates out of the checkout once our
	 * coverage-based method has produced a rate for the current package.
	 *
	 * @param array<string, WC_Shipping_Rate> $rates Package rates.
	 * @param array<string, mixed>            $package Shipping package.
	 * @return array<string, WC_Shipping_Rate>
	 */
	public function prefer_plugin_rates( $rates, $package ) {
		if ( empty( $rates ) || ! is_array( $rates ) ) {
			return $rates;
		}

		$plugin_rates = array();

		foreach ( $rates as $rate_id => $rate ) {
			if ( 0 === strpos( (string) $rate_id, 'admbike_locations' ) ) {
				$plugin_rates[ $rate_id ] = $rate;
			}
		}

		if ( empty( $plugin_rates ) ) {
			return $rates;
		}

		if ( 1 === count( $plugin_rates ) ) {
			return $plugin_rates;
		}

		uasort(
			$plugin_rates,
			function ( $left, $right ) {
				$left_specificity  = (int) ( is_object( $left ) && method_exists( $left, 'get_meta' ) ? $left->get_meta( '_admbike_rule_specificity', 99 ) : 99 );
				$right_specificity = (int) ( is_object( $right ) && method_exists( $right, 'get_meta' ) ? $right->get_meta( '_admbike_rule_specificity', 99 ) : 99 );

				if ( $left_specificity !== $right_specificity ) {
					return $left_specificity <=> $right_specificity;
				}

				$left_priority  = (int) ( is_object( $left ) && method_exists( $left, 'get_meta' ) ? $left->get_meta( '_admbike_rule_priority', 100 ) : 100 );
				$right_priority = (int) ( is_object( $right ) && method_exists( $right, 'get_meta' ) ? $right->get_meta( '_admbike_rule_priority', 100 ) : 100 );

				if ( $left_priority !== $right_priority ) {
					return $left_priority <=> $right_priority;
				}

				$left_cost  = (float) ( is_object( $left ) && method_exists( $left, 'get_cost' ) ? $left->get_cost() : 0 );
				$right_cost = (float) ( is_object( $right ) && method_exists( $right, 'get_cost' ) ? $right->get_cost() : 0 );

				if ( $left_cost !== $right_cost ) {
					return $left_cost <=> $right_cost;
				}

				return strcmp( (string) ( is_object( $left ) && method_exists( $left, 'get_id' ) ? $left->get_id() : '' ), (string) ( is_object( $right ) && method_exists( $right, 'get_id' ) ? $right->get_id() : '' ) );
			}
		);

		$best_rate    = reset( $plugin_rates );
		$best_rate_id = key( $plugin_rates );
		$package_id   = isset( $package['package_id'] ) ? (string) $package['package_id'] : '0';
		if ( '' !== (string) $best_rate_id && isset( WC()->session ) && WC()->session ) {
			$chosen_methods                 = (array) WC()->session->get( 'chosen_shipping_methods', array() );
			$chosen_methods[ $package_id ] = (string) $best_rate_id;
			WC()->session->set( 'chosen_shipping_methods', $chosen_methods );
		}

		return is_array( $best_rate ) || is_object( $best_rate ) ? array( $best_rate_id => $best_rate ) : $plugin_rates;
	}

	/**
	 * Convert a match type into a specificity score.
	 *
	 * Lower numbers are more specific.
	 *
	 * @param string $match_type Match type.
	 * @return int
	 */
	protected function get_match_specificity( $match_type ) {
		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE === $match_type ) {
			return 1;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE === $match_type ) {
			return 2;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_MUNICIPALITY === $match_type ) {
			return 3;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE === $match_type ) {
			return 4;
		}

		return 99;
	}
}
