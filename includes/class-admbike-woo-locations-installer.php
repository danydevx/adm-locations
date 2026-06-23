<?php
/**
 * Plugin installation and migrations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Installer {

	/**
	 * Database version option key.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'admbike_woo_locations_db_version';

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		self::migrate();
	}

	/**
	 * Run migrations when the schema version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$current_version = get_option( self::DB_VERSION_OPTION );

		if ( ADMBIKE_WOO_LOCATIONS_DB_VERSION !== $current_version ) {
			self::migrate();
		}
	}

	/**
	 * Create or update plugin tables.
	 *
	 * @return void
	 */
	public static function migrate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$states_table         = $wpdb->prefix . 'admbike_locations_states';
		$municipalities_table = $wpdb->prefix . 'admbike_locations_municipalities';
		$postcodes_table      = $wpdb->prefix . 'admbike_locations_postcodes';
		$shipping_rules_table = $wpdb->prefix . 'admbike_locations_shipping_rules';

		$states_sql = "CREATE TABLE {$states_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(10) NOT NULL,
			name varchar(191) NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY name (name),
			KEY is_active (is_active)
		) {$charset_collate};";

		$municipalities_sql = "CREATE TABLE {$municipalities_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			state_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			normalized_name varchar(191) NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY state_name (state_id, normalized_name),
			KEY state_id (state_id),
			KEY is_active (is_active)
		) {$charset_collate};";

		$postcodes_sql = "CREATE TABLE {$postcodes_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			state_id bigint(20) unsigned NOT NULL,
			municipality_id bigint(20) unsigned NOT NULL,
			postcode varchar(10) NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY municipality_postcode (municipality_id, postcode),
			KEY state_id (state_id),
			KEY postcode (postcode),
			KEY is_active (is_active)
		) {$charset_collate};";

		$shipping_rules_sql = "CREATE TABLE {$shipping_rules_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			match_type varchar(20) NOT NULL,
			rule_type varchar(20) NOT NULL,
			state_id bigint(20) unsigned DEFAULT NULL,
			municipality_id bigint(20) unsigned DEFAULT NULL,
			postcode_id bigint(20) unsigned DEFAULT NULL,
			postcode_from varchar(10) NOT NULL DEFAULT '',
			postcode_to varchar(10) NOT NULL DEFAULT '',
			shipping_cost decimal(10,2) NOT NULL DEFAULT 0.00,
			currency_code char(3) NOT NULL DEFAULT 'MXN',
			priority smallint(5) unsigned NOT NULL DEFAULT 100,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			notes text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY match_type (match_type),
			KEY rule_type (rule_type),
			KEY state_id (state_id),
			KEY municipality_id (municipality_id),
			KEY postcode_id (postcode_id),
			KEY postcode_range (postcode_from, postcode_to),
			KEY priority (priority),
			KEY is_active (is_active)
		) {$charset_collate};";

		dbDelta( $states_sql );
		dbDelta( $municipalities_sql );
		dbDelta( $postcodes_sql );
		dbDelta( $shipping_rules_sql );

		update_option( self::DB_VERSION_OPTION, ADMBIKE_WOO_LOCATIONS_DB_VERSION );
	}
}
