<?php
/**
 * Uninstall cleanup for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-admbike-woo-locations-installer.php';

ADMBike_Woo_Locations_Installer::uninstall();
