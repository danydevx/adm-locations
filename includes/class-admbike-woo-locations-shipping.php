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
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );
		add_action( 'woocommerce_load_shipping_methods', array( $this, 'load_shipping_methods' ) );
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
}
