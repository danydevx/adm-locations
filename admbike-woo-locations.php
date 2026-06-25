<?php
/**
 * Plugin Name:       ADM Bike Woo Locations
 * Plugin URI:        https://admbike.com/
 * Description:       WooCommerce shipping coverage manager by state, municipality and postal code.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Daniel Lopez (orpot.com)
 * Text Domain:       admbike-woo-locations
 * Domain Path:       /languages
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADMBIKE_WOO_LOCATIONS_VERSION', '0.1.0' );
define( 'ADMBIKE_WOO_LOCATIONS_DB_VERSION', '1.1.0' );
define( 'ADMBIKE_WOO_LOCATIONS_FILE', __FILE__ );
define( 'ADMBIKE_WOO_LOCATIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADMBIKE_WOO_LOCATIONS_URL', plugin_dir_url( __FILE__ ) );

require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-logger.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-installer.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-abstract-repository.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-state-repository.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-municipality-repository.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-postcode-repository.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-shipping-rule-repository.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-rest-api.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-store-api.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-blocks-checkout.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-shipping-method.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-shipping.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-shipping-info.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations.php';
require_once ADMBIKE_WOO_LOCATIONS_PATH . 'admin/class-admbike-woo-locations-admin.php';

register_activation_hook( __FILE__, array( 'ADMBike_Woo_Locations_Installer', 'activate' ) );

function admbike_woo_locations() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new ADMBike_Woo_Locations();
	}

	return $plugin;
}

admbike_woo_locations()->run();

ADMBike_Woo_Locations_Logger::init();

if ( is_admin() ) {
	$GLOBALS['admbike_woo_locations_admin'] = new ADMBike_Woo_Locations_Admin();
}

new ADMBike_Woo_Locations_REST_API();
new ADMBike_Woo_Locations_Shipping();
new ADMBike_Woo_Locations_Store_API();

if ( ! is_admin() ) {
	new ADMBike_Woo_Locations_Blocks_Checkout();
	new ADMBike_Woo_Locations_Shipping_Info();
}

/**
 * Get the admin controller instance.
 *
 * @return ADMBike_Woo_Locations_Admin|null
 */
function admbike_woo_locations_admin() {
	return $GLOBALS['admbike_woo_locations_admin'] ?? null;
}
