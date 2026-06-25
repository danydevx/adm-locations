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
		$message = get_option( self::OPTION_NO_COVERAGE_MESSAGE, $default );

		return is_string( $message ) && '' !== trim( $message ) ? $message : $default;
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
