<?php
/**
 * Plugin installation and migrations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ADMBIKE_WOO_LOCATIONS_DB_VERSION' ) ) {
	define( 'ADMBIKE_WOO_LOCATIONS_DB_VERSION', '1.5.0' );
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
	 * Run uninstall cleanup.
	 *
	 * @return void
	 */
	public static function uninstall() {
		self::delete_managed_shipping_method_settings();
		self::delete_managed_shipping_zones();
		self::drop_plugin_tables();
		delete_option( self::DB_VERSION_OPTION );
		delete_option( 'admbike_no_coverage_message' );
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
			postcode_coverage_mode varchar(10) NOT NULL DEFAULT 'range',
			postcode_coverage longtext DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY name (name),
			KEY postcode_coverage_mode (postcode_coverage_mode),
			KEY is_active (is_active)
		) {$charset_collate};";

		$municipalities_sql = "CREATE TABLE {$municipalities_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			state_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			normalized_name varchar(191) NOT NULL,
			postcode_coverage_mode varchar(10) NOT NULL DEFAULT 'range',
			postcode_coverage longtext DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY state_name (state_id, normalized_name),
			KEY state_id (state_id),
			KEY postcode_coverage_mode (postcode_coverage_mode),
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
			rule_title varchar(191) NOT NULL DEFAULT '',
			display_title varchar(191) NOT NULL DEFAULT '',
			customer_message text DEFAULT NULL,
			state_id bigint(20) unsigned DEFAULT NULL,
			municipality_id bigint(20) unsigned DEFAULT NULL,
			postcode_id bigint(20) unsigned DEFAULT NULL,
			postcode_code varchar(10) NOT NULL DEFAULT '',
			postcode_from varchar(10) NOT NULL DEFAULT '',
			postcode_to varchar(10) NOT NULL DEFAULT '',
			shipping_cost decimal(10,2) NOT NULL DEFAULT 0.00,
			currency_code char(3) NOT NULL DEFAULT 'MXN',
			priority smallint(5) unsigned NOT NULL DEFAULT 100,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			wc_zone_id bigint(20) unsigned NOT NULL DEFAULT 0,
			wc_zone_name varchar(191) NOT NULL DEFAULT '',
			notes text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY match_type (match_type),
			KEY rule_type (rule_type),
			KEY rule_title (rule_title),
			KEY state_id (state_id),
			KEY municipality_id (municipality_id),
			KEY postcode_id (postcode_id),
			KEY postcode_code (postcode_code),
			KEY postcode_range (postcode_from, postcode_to),
			KEY priority (priority),
			KEY is_active (is_active),
			KEY wc_zone_id (wc_zone_id)
		) {$charset_collate};";

		dbDelta( $states_sql );
		dbDelta( $municipalities_sql );
		dbDelta( $postcodes_sql );
		dbDelta( $shipping_rules_sql );
		self::backfill_municipality_coverage_mode();
		self::backfill_shipping_rule_title();
		self::backfill_shipping_rule_postcode_code();
		self::seed_default_coverage_data();
		self::cleanup_orphaned_shipping_zones();
		add_option( ADMBIKE_WOO_LOCATIONS::OPTION_NO_COVERAGE_MESSAGE, __( 'No disponible en tu zona', 'admbike-woo-locations' ) );

		update_option( self::DB_VERSION_OPTION, ADMBIKE_WOO_LOCATIONS_DB_VERSION );
	}

	/**
	 * Backfill exact postcode text for legacy shipping rules.
	 *
	 * @return void
	 */
	protected static function backfill_shipping_rule_postcode_code() {
		global $wpdb;

		$shipping_rules_table = $wpdb->prefix . 'admbike_locations_shipping_rules';
		$postcodes_table      = $wpdb->prefix . 'admbike_locations_postcodes';

		$wpdb->query(
			"UPDATE {$shipping_rules_table} sr
			INNER JOIN {$postcodes_table} pc ON pc.id = sr.postcode_id
			SET sr.postcode_code = pc.postcode
			WHERE sr.match_type = 'postcode' AND (sr.postcode_code = '' OR sr.postcode_code IS NULL) AND sr.postcode_id IS NOT NULL"
		);
	}

	/**
	 * Backfill the municipality coverage mode for legacy rows.
	 *
	 * @return void
	 */
	protected static function backfill_municipality_coverage_mode() {
		global $wpdb;

		$municipalities_table = $wpdb->prefix . 'admbike_locations_municipalities';

		$wpdb->query(
			"UPDATE {$municipalities_table}
			SET postcode_coverage_mode = CASE
				WHEN postcode_coverage IS NULL OR TRIM(postcode_coverage) = '' THEN 'range'
				WHEN postcode_coverage REGEXP ',' THEN 'list'
				ELSE 'range'
			END"
		);
	}

	/**
	 * Backfill the admin rule title for legacy shipping rules.
	 *
	 * @return void
	 */
	protected static function backfill_shipping_rule_title() {
		global $wpdb;

		$shipping_rules_table = $wpdb->prefix . 'admbike_locations_shipping_rules';

		$wpdb->query(
			"UPDATE {$shipping_rules_table}
			SET rule_title = CASE
				WHEN rule_title IS NULL OR rule_title = '' THEN
					CASE
						WHEN display_title IS NOT NULL AND display_title <> '' THEN display_title
						ELSE CONCAT('Rule #', id)
					END
				ELSE rule_title
			END"
		);
	}

	/**
	 * Delete WooCommerce shipping method settings created by the plugin.
	 *
	 * @return void
	 */
	protected static function delete_managed_shipping_method_settings() {
		global $wpdb;

		$zones_table       = $wpdb->prefix . 'woocommerce_shipping_zones';
		$methods_table     = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
		$rules_table       = $wpdb->prefix . 'admbike_locations_shipping_rules';
		$zone_ids          = array();
		$method_option_ids = array();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $methods_table ) ) ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rules_table ) ) ) {
				$zone_ids = $wpdb->get_col( "SELECT DISTINCT wc_zone_id FROM {$rules_table} WHERE wc_zone_id > 0" );
			}

			if ( empty( $zone_ids ) && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $zones_table ) ) ) {
				$zone_ids = $wpdb->get_col( "SELECT zone_id FROM {$zones_table} WHERE zone_name LIKE 'ADM -%' OR zone_name LIKE 'ADM %'" );
			}

			if ( empty( $zone_ids ) ) {
				return;
			}

			$placeholders = implode( ',', array_fill( 0, count( $zone_ids ), '%d' ) );
			$query        = $wpdb->prepare(
				"SELECT instance_id, method_id FROM {$methods_table} WHERE zone_id IN ({$placeholders})",
				array_map( 'absint', $zone_ids )
			);
			$method_option_ids = $wpdb->get_results( $query, ARRAY_A );
		}

		if ( empty( $method_option_ids ) ) {
			return;
		}

		foreach ( $method_option_ids as $method ) {
			$instance_id = isset( $method['instance_id'] ) ? absint( $method['instance_id'] ) : 0;
			$method_id   = isset( $method['method_id'] ) ? sanitize_key( (string) $method['method_id'] ) : '';

			if ( $instance_id <= 0 || '' === $method_id ) {
				continue;
			}

			delete_option( 'woocommerce_' . $method_id . '_' . $instance_id . '_settings' );
		}
	}

	/**
	 * Drop plugin database tables.
	 *
	 * @return void
	 */
	protected static function drop_plugin_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'admbike_locations_states',
			$wpdb->prefix . 'admbike_locations_municipalities',
			$wpdb->prefix . 'admbike_locations_postcodes',
			$wpdb->prefix . 'admbike_locations_shipping_rules',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	/**
	 * Delete WooCommerce zones managed by the plugin.
	 *
	 * @return void
	 */
	protected static function delete_managed_shipping_zones() {
		global $wpdb;

		$zone_ids = array();
		$shipping_rules_table = $wpdb->prefix . 'admbike_locations_shipping_rules';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $shipping_rules_table ) ) ) {
			$zone_ids = $wpdb->get_col( "SELECT DISTINCT wc_zone_id FROM {$shipping_rules_table} WHERE wc_zone_id > 0" );
		}

		if ( class_exists( 'WC_Shipping_Zones' ) && ! empty( $zone_ids ) ) {
			foreach ( $zone_ids as $zone_id ) {
				WC_Shipping_Zones::delete_zone( absint( $zone_id ) );
			}
		}

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}woocommerce_shipping_zones'" ) ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE zone_id IN ( SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zones WHERE zone_name LIKE 'ADM -%' OR zone_name LIKE 'ADM %' )" );
			$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id IN ( SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zones WHERE zone_name LIKE 'ADM -%' OR zone_name LIKE 'ADM %' )" );
			$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_shipping_zones WHERE zone_name LIKE 'ADM -%' OR zone_name LIKE 'ADM %'" );
		}
	}

	/**
	 * Delete orphaned WooCommerce zones created by this plugin when no rules exist yet.
	 *
	 * @return void
	 */
	protected static function cleanup_orphaned_shipping_zones() {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return;
		}

		$rules_repo = new ADMBike_Woo_Locations_Shipping_Rule_Repository();
		if ( $rules_repo->count_all() > 0 ) {
			return;
		}

		$zones = WC_Shipping_Zones::get_shipping_zones();
		foreach ( $zones as $zone ) {
			if ( ! $zone instanceof WC_Shipping_Zone ) {
				continue;
			}

			$zone_name = method_exists( $zone, 'get_zone_name' ) ? (string) $zone->get_zone_name() : '';
			if ( 0 !== strpos( $zone_name, 'ADM -' ) && 0 !== strpos( $zone_name, 'ADM ' ) ) {
				continue;
			}

			$zone->delete();
		}
	}

	/**
	 * Build a compact postcode coverage string from ranges.
	 *
	 * @param array<int, array{from:int,to:int}> $ranges Coverage ranges.
	 * @return string
	 */
	protected static function build_postcode_coverage_string( array $ranges ) {
		$parts = array();

		foreach ( $ranges as $range ) {
			$from = isset( $range['from'] ) ? sprintf( '%05d', absint( $range['from'] ) ) : '';
			$to   = isset( $range['to'] ) ? sprintf( '%05d', absint( $range['to'] ) ) : '';

			if ( '' === $from || '' === $to ) {
				continue;
			}

			$parts[] = $from === $to ? $from : $from . '-' . $to;
		}

		return implode( ', ', array_values( array_unique( $parts ) ) );
	}

	/**
	 * Persist compact postcode coverage for a municipality.
	 *
	 * @param ADMBike_Woo_Locations_Municipality_Repository $municipalities_repo Municipalities repository.
	 * @param int                                          $municipality_id Municipality ID.
	 * @param string                                       $coverage Coverage string.
	 * @return void
	 */
	protected static function update_municipality_postcode_coverage( $municipalities_repo, $municipality_id, $coverage ) {
		$municipalities_repo->update(
			absint( $municipality_id ),
			array(
				'postcode_coverage' => $coverage,
			)
		);
	}

	/**
	 * Seed the default coverage data used by the plugin.
	 *
	 * @return void
	 */
	protected static function seed_default_coverage_data() {
		global $wpdb;

		require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-abstract-repository.php';
		require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-state-repository.php';
		require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-municipality-repository.php';
		require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-postcode-repository.php';
		require_once ADMBIKE_WOO_LOCATIONS_PATH . 'includes/class-admbike-woo-locations-shipping-rule-repository.php';

		$states_repo         = new ADMBike_Woo_Locations_State_Repository();
		$municipalities_repo = new ADMBike_Woo_Locations_Municipality_Repository();
		$postcodes_repo      = new ADMBike_Woo_Locations_Postcode_Repository();
		$rules_repo          = new ADMBike_Woo_Locations_Shipping_Rule_Repository();

		$state_ids = array();

		$states = array(
			array( 'code' => 'JA',  'name' => 'Jalisco' ),
			array( 'code' => 'AG',  'name' => 'Aguascalientes' ),
			array( 'code' => 'CL',  'name' => 'Colima' ),
			array( 'code' => 'ZA',  'name' => 'Zacatecas' ),
			array( 'code' => 'MI',  'name' => 'Michoacán' ),
			array( 'code' => 'GT',  'name' => 'Guanajuato' ),
			array( 'code' => 'QT',  'name' => 'Querétaro' ),
			array( 'code' => 'SL',  'name' => 'San Luis Potosí' ),
			array( 'code' => 'NA',  'name' => 'Nayarit' ),
			array( 'code' => 'NL',  'name' => 'Nuevo León' ),
			array( 'code' => 'SI',  'name' => 'Sinaloa' ),
		);

		foreach ( $states as $state_data ) {
			$state = $states_repo->get_by_code_or_name( $state_data['name'] );
			$payload = array(
				'code'      => $state_data['code'],
				'name'      => $state_data['name'],
				'is_active' => 1,
			);

			if ( $state ) {
				$states_repo->update( (int) $state['id'], $payload );
				$state_ids[ $state_data['code'] ] = (int) $state['id'];
				continue;
			}

			$state_ids[ $state_data['code'] ] = (int) $states_repo->create( $payload );
		}

		$municipalities = array(
			array( 'state_code' => 'JA',  'name' => 'Acatic' ),
			array( 'state_code' => 'JA',  'name' => 'Acatlán de Juárez' ),
			array( 'state_code' => 'JA',  'name' => 'Ahualulco de Mercado' ),
			array( 'state_code' => 'JA',  'name' => 'Amacueca' ),
			array( 'state_code' => 'JA',  'name' => 'Amatitán' ),
			array( 'state_code' => 'JA',  'name' => 'Ameca' ),
			array( 'state_code' => 'JA',  'name' => 'Arandas' ),
			array( 'state_code' => 'JA',  'name' => 'Atemajac de Brizuela' ),
			array( 'state_code' => 'JA',  'name' => 'Atengo' ),
			array( 'state_code' => 'JA',  'name' => 'Atenguillo' ),
			array( 'state_code' => 'JA',  'name' => 'Atotonilco el Alto' ),
			array( 'state_code' => 'JA',  'name' => 'Atoyac' ),
			array( 'state_code' => 'JA',  'name' => 'Autlán de Navarro' ),
			array( 'state_code' => 'JA',  'name' => 'Ayotlán' ),
			array( 'state_code' => 'JA',  'name' => 'Ayutla' ),
			array( 'state_code' => 'JA',  'name' => 'Bolaños' ),
			array( 'state_code' => 'JA',  'name' => 'Cabo Corrientes' ),
			array( 'state_code' => 'JA',  'name' => 'Cañadas de Obregón' ),
			array( 'state_code' => 'JA',  'name' => 'Casimiro Castillo' ),
			array( 'state_code' => 'JA',  'name' => 'Chapala' ),
			array( 'state_code' => 'JA',  'name' => 'Chimaltitán' ),
			array( 'state_code' => 'JA',  'name' => 'Chiquilistlán' ),
			array( 'state_code' => 'JA',  'name' => 'Cihuatlán' ),
			array( 'state_code' => 'JA',  'name' => 'Cocula' ),
			array( 'state_code' => 'JA',  'name' => 'Colotlán' ),
			array( 'state_code' => 'JA',  'name' => 'Concepción de Buenos Aires' ),
			array( 'state_code' => 'JA',  'name' => 'Cuautitlán de García Barragán' ),
			array( 'state_code' => 'JA',  'name' => 'Cuautla' ),
			array( 'state_code' => 'JA',  'name' => 'Cuquío' ),
			array( 'state_code' => 'JA',  'name' => 'Degollado' ),
			array( 'state_code' => 'JA',  'name' => 'Ejutla' ),
			array( 'state_code' => 'JA',  'name' => 'El Arenal' ),
			array( 'state_code' => 'JA',  'name' => 'El Grullo' ),
			array( 'state_code' => 'JA',  'name' => 'El Limón' ),
			array( 'state_code' => 'JA',  'name' => 'El Salto' ),
			array( 'state_code' => 'JA',  'name' => 'Encarnación de Díaz' ),
			array( 'state_code' => 'JA',  'name' => 'Etzatlán' ),
			array( 'state_code' => 'JA',  'name' => 'Gómez Farías' ),
			array( 'state_code' => 'JA',  'name' => 'Guachinango' ),
			array( 'state_code' => 'JA',  'name' => 'Guadalajara' ),
			array( 'state_code' => 'JA',  'name' => 'Hostotipaquillo' ),
			array( 'state_code' => 'JA',  'name' => 'Huejúcar' ),
			array( 'state_code' => 'JA',  'name' => 'Huejuquilla el Alto' ),
			array( 'state_code' => 'JA',  'name' => 'Ixtlahuacán de los Membrillos' ),
			array( 'state_code' => 'JA',  'name' => 'Ixtlahuacán del Río' ),
			array( 'state_code' => 'JA',  'name' => 'Jalostotitlán' ),
			array( 'state_code' => 'JA',  'name' => 'Jamay' ),
			array( 'state_code' => 'JA',  'name' => 'Jesús María' ),
			array( 'state_code' => 'JA',  'name' => 'Jilotlán de los Dolores' ),
			array( 'state_code' => 'JA',  'name' => 'Jocotepec' ),
			array( 'state_code' => 'JA',  'name' => 'Juanacatlán' ),
			array( 'state_code' => 'JA',  'name' => 'Juchitlán' ),
			array( 'state_code' => 'JA',  'name' => 'La Barca' ),
			array( 'state_code' => 'JA',  'name' => 'La Huerta' ),
			array( 'state_code' => 'JA',  'name' => 'La Manzanilla de la Paz' ),
			array( 'state_code' => 'JA',  'name' => 'Lagos de Moreno' ),
			array( 'state_code' => 'JA',  'name' => 'Magdalena' ),
			array( 'state_code' => 'JA',  'name' => 'Mascota' ),
			array( 'state_code' => 'JA',  'name' => 'Mazamitla' ),
			array( 'state_code' => 'JA',  'name' => 'Mexticacán' ),
			array( 'state_code' => 'JA',  'name' => 'Mezquitic' ),
			array( 'state_code' => 'JA',  'name' => 'Mixtlán' ),
			array( 'state_code' => 'JA',  'name' => 'Ocotlán' ),
			array( 'state_code' => 'JA',  'name' => 'Ojuelos de Jalisco' ),
			array( 'state_code' => 'JA',  'name' => 'Pihuamo' ),
			array( 'state_code' => 'JA',  'name' => 'Poncitlán' ),
			array( 'state_code' => 'JA',  'name' => 'Puerto Vallarta' ),
			array( 'state_code' => 'JA',  'name' => 'Quitupan' ),
			array( 'state_code' => 'JA',  'name' => 'San Cristóbal de la Barranca' ),
			array( 'state_code' => 'JA',  'name' => 'San Diego de Alejandría' ),
			array( 'state_code' => 'JA',  'name' => 'San Gabriel' ),
			array( 'state_code' => 'JA',  'name' => 'San Ignacio Cerro Gordo' ),
			array( 'state_code' => 'JA',  'name' => 'San Juan de los Lagos' ),
			array( 'state_code' => 'JA',  'name' => 'San Juanito de Escobedo' ),
			array( 'state_code' => 'JA',  'name' => 'San Julián' ),
			array( 'state_code' => 'JA',  'name' => 'San Marcos' ),
			array( 'state_code' => 'JA',  'name' => 'San Martín de Bolaños' ),
			array( 'state_code' => 'JA',  'name' => 'San Martín Hidalgo' ),
			array( 'state_code' => 'JA',  'name' => 'San Miguel el Alto' ),
			array( 'state_code' => 'JA',  'name' => 'San Pedro Tlaquepaque' ),
			array( 'state_code' => 'JA',  'name' => 'San Sebastián del Oeste' ),
			array( 'state_code' => 'JA',  'name' => 'Santa María de los Ángeles' ),
			array( 'state_code' => 'JA',  'name' => 'Santa María del Oro' ),
			array( 'state_code' => 'JA',  'name' => 'Sayula' ),
			array( 'state_code' => 'JA',  'name' => 'Tala' ),
			array( 'state_code' => 'JA',  'name' => 'Talpa de Allende' ),
			array( 'state_code' => 'JA',  'name' => 'Tamazula de Gordiano' ),
			array( 'state_code' => 'JA',  'name' => 'Tapalpa' ),
			array( 'state_code' => 'JA',  'name' => 'Tecalitlán' ),
			array( 'state_code' => 'JA',  'name' => 'Techaluta de Montenegro' ),
			array( 'state_code' => 'JA',  'name' => 'Tecolotlán' ),
			array( 'state_code' => 'JA',  'name' => 'Tenamaxtlán' ),
			array( 'state_code' => 'JA',  'name' => 'Teocaltiche' ),
			array( 'state_code' => 'JA',  'name' => 'Teocuitatlán de Corona' ),
			array( 'state_code' => 'JA',  'name' => 'Tepatitlán de Morelos' ),
			array( 'state_code' => 'JA',  'name' => 'Tequila' ),
			array( 'state_code' => 'JA',  'name' => 'Teuchitlán' ),
			array( 'state_code' => 'JA',  'name' => 'Tesistán, Zapopan' ),
			array( 'state_code' => 'JA',  'name' => 'Tizapán el Alto' ),
			array( 'state_code' => 'JA',  'name' => 'Tlajomulco de Zúñiga' ),
			array( 'state_code' => 'JA',  'name' => 'Tolimán' ),
			array( 'state_code' => 'JA',  'name' => 'Tomatlán' ),
			array( 'state_code' => 'JA',  'name' => 'Tonalá' ),
			array( 'state_code' => 'JA',  'name' => 'Tonaya' ),
			array( 'state_code' => 'JA',  'name' => 'Tonila' ),
			array( 'state_code' => 'JA',  'name' => 'Totatiche' ),
			array( 'state_code' => 'JA',  'name' => 'Tototlán' ),
			array( 'state_code' => 'JA',  'name' => 'Tuxcacuesco' ),
			array( 'state_code' => 'JA',  'name' => 'Tuxcueca' ),
			array( 'state_code' => 'JA',  'name' => 'Tuxpan' ),
			array( 'state_code' => 'JA',  'name' => 'Unión de San Antonio' ),
			array( 'state_code' => 'JA',  'name' => 'Unión de Tula' ),
			array( 'state_code' => 'JA',  'name' => 'Valle de Guadalupe' ),
			array( 'state_code' => 'JA',  'name' => 'Valle de Juárez' ),
			array( 'state_code' => 'JA',  'name' => 'Villa Corona' ),
			array( 'state_code' => 'JA',  'name' => 'Villa Guerrero' ),
			array( 'state_code' => 'JA',  'name' => 'Villa Hidalgo' ),
			array( 'state_code' => 'JA',  'name' => 'Villa Purificación' ),
			array( 'state_code' => 'JA',  'name' => 'Yahualica de González Gallo' ),
			array( 'state_code' => 'JA',  'name' => 'Zacoalco de Torres' ),
			array( 'state_code' => 'JA',  'name' => 'Zapopan' ),
			array( 'state_code' => 'JA',  'name' => 'Zapotiltic' ),
			array( 'state_code' => 'JA',  'name' => 'Zapotitlán de Vadillo' ),
			array( 'state_code' => 'JA',  'name' => 'Zapotlán del Rey' ),
			array( 'state_code' => 'JA',  'name' => 'Zapotlán el Grande' ),
			array( 'state_code' => 'JA',  'name' => 'Zapotlanejo' ),
			array( 'state_code' => 'SI',  'name' => 'Mazatlán' ),
			array( 'state_code' => 'AG',  'name' => 'Aguascalientes' ),
			array( 'state_code' => 'AG',  'name' => 'Asientos' ),
			array( 'state_code' => 'AG',  'name' => 'Calvillo' ),
			array( 'state_code' => 'AG',  'name' => 'Cosío' ),
			array( 'state_code' => 'AG',  'name' => 'Jesús María' ),
			array( 'state_code' => 'AG',  'name' => 'Pabellón de Arteaga' ),
			array( 'state_code' => 'AG',  'name' => 'Rincón de Romos' ),
			array( 'state_code' => 'AG',  'name' => 'San José de Gracia' ),
			array( 'state_code' => 'AG',  'name' => 'Tepezalá' ),
			array( 'state_code' => 'AG',  'name' => 'El Llano' ),
			array( 'state_code' => 'AG',  'name' => 'San Francisco de los Romo' ),
			array( 'state_code' => 'ZA',  'name' => 'Zacatecas' ),
			array( 'state_code' => 'ZA',  'name' => 'Guadalupe' ),
			array( 'state_code' => 'ZA',  'name' => 'Fresnillo' ),
			array( 'state_code' => 'ZA',  'name' => 'Sombrerete' ),
			array( 'state_code' => 'ZA',  'name' => 'Río Grande' ),
			array( 'state_code' => 'ZA',  'name' => 'Calera' ),
			array( 'state_code' => 'ZA',  'name' => 'Victor Rosales' ),
			array( 'state_code' => 'ZA',  'name' => 'Loreto' ),
			array( 'state_code' => 'ZA',  'name' => 'Pinos' ),
			array( 'state_code' => 'ZA',  'name' => 'Francisco I. Madero' ),
			array( 'state_code' => 'ZA',  'name' => 'General Pánfilo Natera' ),
			array( 'state_code' => 'ZA',  'name' => 'Cuauhtémoc' ),
			array( 'state_code' => 'ZA',  'name' => 'Trancoso' ),
			array( 'state_code' => 'ZA',  'name' => 'Sain Alto' ),
			array( 'state_code' => 'ZA',  'name' => 'Villa García' ),
			array( 'state_code' => 'ZA',  'name' => 'Miguel Auza' ),
			array( 'state_code' => 'MI',  'name' => 'Morelia' ),
			array( 'state_code' => 'MI',  'name' => 'Uruapan' ),
			array( 'state_code' => 'MI',  'name' => 'Zamora' ),
			array( 'state_code' => 'MI',  'name' => 'La Piedad' ),
			array( 'state_code' => 'MI',  'name' => 'Salamanca' ),
			array( 'state_code' => 'MI',  'name' => 'Zacapu' ),
			array( 'state_code' => 'MI',  'name' => 'Sahuayo' ),
			array( 'state_code' => 'MI',  'name' => 'Los Reyes' ),
			array( 'state_code' => 'MI',  'name' => 'Peribán' ),
			array( 'state_code' => 'MI',  'name' => 'Apatzingán' ),
			array( 'state_code' => 'MI',  'name' => 'Lázaro Cárdenas' ),
			array( 'state_code' => 'MI',  'name' => 'Ciudad Hidalgo' ),
			array( 'state_code' => 'MI',  'name' => 'Zitácuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Huetamo' ),
			array( 'state_code' => 'MI',  'name' => 'Nueva Italia' ),
			array( 'state_code' => 'MI',  'name' => 'Patzcuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Coalcomán' ),
			array( 'state_code' => 'MI',  'name' => 'Jungapeo' ),
			array( 'state_code' => 'MI',  'name' => 'Ario' ),
			array( 'state_code' => 'MI',  'name' => 'Tacámbaro' ),
			array( 'state_code' => 'MI',  'name' => 'Purépero' ),
			array( 'state_code' => 'MI',  'name' => 'Angangueo' ),
			array( 'state_code' => 'MI',  'name' => 'Tlalpujahua' ),
			array( 'state_code' => 'GT',  'name' => 'Abasolo' ),
			array( 'state_code' => 'GT',  'name' => 'Acámbaro' ),
			array( 'state_code' => 'GT',  'name' => 'Apaseo el Alto' ),
			array( 'state_code' => 'GT',  'name' => 'Apaseo el Grande' ),
			array( 'state_code' => 'GT',  'name' => 'Atarjea' ),
			array( 'state_code' => 'GT',  'name' => 'Celaya' ),
			array( 'state_code' => 'GT',  'name' => 'Comonfort' ),
			array( 'state_code' => 'GT',  'name' => 'Coroneo' ),
			array( 'state_code' => 'GT',  'name' => 'Cortazar' ),
			array( 'state_code' => 'GT',  'name' => 'Cuerámaro' ),
			array( 'state_code' => 'GT',  'name' => 'Doctor Mora' ),
			array( 'state_code' => 'GT',  'name' => 'Dolores Hidalgo Cuna de la Independencia Nacional' ),
			array( 'state_code' => 'GT',  'name' => 'Guanajuato' ),
			array( 'state_code' => 'GT',  'name' => 'Huanímaro' ),
			array( 'state_code' => 'GT',  'name' => 'Irapuato' ),
			array( 'state_code' => 'GT',  'name' => 'Jaral del Progreso' ),
			array( 'state_code' => 'GT',  'name' => 'Jerécuaro' ),
			array( 'state_code' => 'GT',  'name' => 'León' ),
			array( 'state_code' => 'GT',  'name' => 'Manuel Doblado' ),
			array( 'state_code' => 'GT',  'name' => 'Moroleón' ),
			array( 'state_code' => 'GT',  'name' => 'Ocampo' ),
			array( 'state_code' => 'GT',  'name' => 'Pénjamo' ),
			array( 'state_code' => 'GT',  'name' => 'Pueblo Nuevo' ),
			array( 'state_code' => 'GT',  'name' => 'Purísima del Rincón' ),
			array( 'state_code' => 'GT',  'name' => 'Romita' ),
			array( 'state_code' => 'GT',  'name' => 'Salamanca' ),
			array( 'state_code' => 'GT',  'name' => 'Salvatierra' ),
			array( 'state_code' => 'GT',  'name' => 'San Diego de la Unión' ),
			array( 'state_code' => 'GT',  'name' => 'San Felipe' ),
			array( 'state_code' => 'GT',  'name' => 'San Francisco del Rincón' ),
			array( 'state_code' => 'GT',  'name' => 'San José Iturbide' ),
			array( 'state_code' => 'GT',  'name' => 'San Luis de la Paz' ),
			array( 'state_code' => 'GT',  'name' => 'San Miguel de Allende' ),
			array( 'state_code' => 'GT',  'name' => 'Santa Catarina' ),
			array( 'state_code' => 'GT',  'name' => 'Santa Cruz de Juvenino Rosas' ),
			array( 'state_code' => 'GT',  'name' => 'Santiago Maravatío' ),
			array( 'state_code' => 'GT',  'name' => 'Silao de la Victoria' ),
			array( 'state_code' => 'GT',  'name' => 'Tarandacuao' ),
			array( 'state_code' => 'GT',  'name' => 'Tarimoro' ),
			array( 'state_code' => 'GT',  'name' => 'Tierra Blanca' ),
			array( 'state_code' => 'GT',  'name' => 'Uriangato' ),
			array( 'state_code' => 'GT',  'name' => 'Valle de Santiago' ),
			array( 'state_code' => 'GT',  'name' => 'Victoria' ),
			array( 'state_code' => 'GT',  'name' => 'Villagrán' ),
			array( 'state_code' => 'GT',  'name' => 'Xichú' ),
			array( 'state_code' => 'GT',  'name' => 'Yuriria' ),
			array( 'state_code' => 'QT',  'name' => 'Amealco de Bonfil' ),
			array( 'state_code' => 'QT',  'name' => 'Arroyo Seco' ),
			array( 'state_code' => 'QT',  'name' => 'Cadereyta de Montes' ),
			array( 'state_code' => 'QT',  'name' => 'Colón' ),
			array( 'state_code' => 'QT',  'name' => 'Corregidora' ),
			array( 'state_code' => 'QT',  'name' => 'El Marqués' ),
			array( 'state_code' => 'QT',  'name' => 'Ezequiel Montes' ),
			array( 'state_code' => 'QT',  'name' => 'Huimilpan' ),
			array( 'state_code' => 'QT',  'name' => 'Jalpan de Serra' ),
			array( 'state_code' => 'QT',  'name' => 'Landa de Matamoros' ),
			array( 'state_code' => 'QT',  'name' => 'Pedro Escobedo' ),
			array( 'state_code' => 'QT',  'name' => 'Peñamiller' ),
			array( 'state_code' => 'QT',  'name' => 'Pinal de Amoles' ),
			array( 'state_code' => 'QT',  'name' => 'Querétaro' ),
			array( 'state_code' => 'QT',  'name' => 'San Joaquín' ),
			array( 'state_code' => 'QT',  'name' => 'San Juan del Río' ),
			array( 'state_code' => 'QT',  'name' => 'Tequisquiapan' ),
			array( 'state_code' => 'QT',  'name' => 'Tolimán' ),
			array( 'state_code' => 'SL',  'name' => 'Ahualulco' ),
			array( 'state_code' => 'SL',  'name' => 'Alaquines' ),
			array( 'state_code' => 'SL',  'name' => 'Aquismón' ),
			array( 'state_code' => 'SL',  'name' => 'Armadillo de los Infante' ),
			array( 'state_code' => 'SL',  'name' => 'Axtla de Terrazas' ),
			array( 'state_code' => 'SL',  'name' => 'Cárdenas' ),
			array( 'state_code' => 'SL',  'name' => 'Catorce' ),
			array( 'state_code' => 'SL',  'name' => 'Cedral' ),
			array( 'state_code' => 'SL',  'name' => 'Cerritos' ),
			array( 'state_code' => 'SL',  'name' => 'Cerro de San Pedro' ),
			array( 'state_code' => 'SL',  'name' => 'Charcas' ),
			array( 'state_code' => 'SL',  'name' => 'Ciudad del Maíz' ),
			array( 'state_code' => 'SL',  'name' => 'Ciudad Fernández' ),
			array( 'state_code' => 'SL',  'name' => 'Ciudad Valles' ),
			array( 'state_code' => 'SL',  'name' => 'Coxcatlán' ),
			array( 'state_code' => 'SL',  'name' => 'Ebano' ),
			array( 'state_code' => 'SL',  'name' => 'El Naranjo' ),
			array( 'state_code' => 'SL',  'name' => 'Guadalcázar' ),
			array( 'state_code' => 'SL',  'name' => 'Huehuetlán' ),
			array( 'state_code' => 'SL',  'name' => 'Lagunillas' ),
			array( 'state_code' => 'SL',  'name' => 'Matehuala' ),
			array( 'state_code' => 'SL',  'name' => 'Matlapa' ),
			array( 'state_code' => 'SL',  'name' => 'Mexquitic de Carmona' ),
			array( 'state_code' => 'SL',  'name' => 'Moctezuma' ),
			array( 'state_code' => 'SL',  'name' => 'Rayón' ),
			array( 'state_code' => 'SL',  'name' => 'Rioverde' ),
			array( 'state_code' => 'SL',  'name' => 'Salinas' ),
			array( 'state_code' => 'SL',  'name' => 'San Antonio' ),
			array( 'state_code' => 'SL',  'name' => 'San Ciro de Acosta' ),
			array( 'state_code' => 'SL',  'name' => 'San Luis Potosí' ),
			array( 'state_code' => 'SL',  'name' => 'San Martín Chalchicuautla' ),
			array( 'state_code' => 'SL',  'name' => 'San Nicolás Tolentino' ),
			array( 'state_code' => 'SL',  'name' => 'San Vicente Tancuayalab' ),
			array( 'state_code' => 'SL',  'name' => 'Santa Catarina' ),
			array( 'state_code' => 'SL',  'name' => 'Santa María del Río' ),
			array( 'state_code' => 'SL',  'name' => 'Santo Domingo' ),
			array( 'state_code' => 'SL',  'name' => 'Soledad de Graciano Sánchez' ),
			array( 'state_code' => 'SL',  'name' => 'Tamasopo' ),
			array( 'state_code' => 'SL',  'name' => 'Tamazunchale' ),
			array( 'state_code' => 'SL',  'name' => 'Tampacán' ),
			array( 'state_code' => 'SL',  'name' => 'Tampamolón Corona' ),
			array( 'state_code' => 'SL',  'name' => 'Tamuín' ),
			array( 'state_code' => 'SL',  'name' => 'Tancanhuitz' ),
			array( 'state_code' => 'SL',  'name' => 'Tanlajás' ),
			array( 'state_code' => 'SL',  'name' => 'Tanquián de Escobedo' ),
			array( 'state_code' => 'SL',  'name' => 'Tierra Nueva' ),
			array( 'state_code' => 'SL',  'name' => 'Vanegas' ),
			array( 'state_code' => 'SL',  'name' => 'Venado' ),
			array( 'state_code' => 'SL',  'name' => 'Villa de Arista' ),
			array( 'state_code' => 'SL',  'name' => 'Villa de Arriaga' ),
			array( 'state_code' => 'SL',  'name' => 'Villa de Guadalupe' ),
			array( 'state_code' => 'SL',  'name' => 'Villa de la Paz' ),
			array( 'state_code' => 'SL',  'name' => 'Villa de Ramos' ),
			array( 'state_code' => 'SL',  'name' => 'Villa de Reyes' ),
			array( 'state_code' => 'SL',  'name' => 'Villa Hidalgo' ),
			array( 'state_code' => 'SL',  'name' => 'Villa Juárez' ),
			array( 'state_code' => 'SL',  'name' => 'Xilitla' ),
			array( 'state_code' => 'SL',  'name' => 'Zaragoza' ),
			array( 'state_code' => 'NA',  'name' => 'Acaponeta' ),
			array( 'state_code' => 'NA',  'name' => 'Ahuacatlán' ),
			array( 'state_code' => 'NA',  'name' => 'Amatlán de Cañas' ),
			array( 'state_code' => 'NA',  'name' => 'Bahía de Banderas' ),
			array( 'state_code' => 'NA',  'name' => 'Compostela' ),
			array( 'state_code' => 'NA',  'name' => 'Del Nayar' ),
			array( 'state_code' => 'NA',  'name' => 'Huajicori' ),
			array( 'state_code' => 'NA',  'name' => 'Ixtlán del Río' ),
			array( 'state_code' => 'NA',  'name' => 'Jala' ),
			array( 'state_code' => 'NA',  'name' => 'La Yesca' ),
			array( 'state_code' => 'NA',  'name' => 'Rosamorada' ),
			array( 'state_code' => 'NA',  'name' => 'Ruíz' ),
			array( 'state_code' => 'NA',  'name' => 'San Blas' ),
			array( 'state_code' => 'NA',  'name' => 'San Pedro Lagunillas' ),
			array( 'state_code' => 'NA',  'name' => 'Santa María del Oro' ),
			array( 'state_code' => 'NA',  'name' => 'Santiago Ixcuintla' ),
			array( 'state_code' => 'NA',  'name' => 'Tecuala' ),
			array( 'state_code' => 'NA',  'name' => 'Tepic' ),
			array( 'state_code' => 'NA',  'name' => 'Tuxpan' ),
			array( 'state_code' => 'NA',  'name' => 'Xalisco' ),
			array( 'state_code' => 'NL',  'name' => 'Abasolo' ),
			array( 'state_code' => 'NL',  'name' => 'Agualeguas' ),
			array( 'state_code' => 'NL',  'name' => 'Allende' ),
			array( 'state_code' => 'NL',  'name' => 'Anáhuac' ),
			array( 'state_code' => 'NL',  'name' => 'Apodaca' ),
			array( 'state_code' => 'NL',  'name' => 'Aramberri' ),
			array( 'state_code' => 'NL',  'name' => 'Bustamante' ),
			array( 'state_code' => 'NL',  'name' => 'Cadereyta Jiménez' ),
			array( 'state_code' => 'NL',  'name' => 'Cerralvo' ),
			array( 'state_code' => 'NL',  'name' => 'China' ),
			array( 'state_code' => 'NL',  'name' => 'Ciénega de Flores' ),
			array( 'state_code' => 'NL',  'name' => 'Doctor Arroyo' ),
			array( 'state_code' => 'NL',  'name' => 'Doctor Coss' ),
			array( 'state_code' => 'NL',  'name' => 'Doctor González' ),
			array( 'state_code' => 'NL',  'name' => 'El Carmen' ),
			array( 'state_code' => 'NL',  'name' => 'Galeana' ),
			array( 'state_code' => 'NL',  'name' => 'García' ),
			array( 'state_code' => 'NL',  'name' => 'General Bravo' ),
			array( 'state_code' => 'NL',  'name' => 'General Escobedo' ),
			array( 'state_code' => 'NL',  'name' => 'General Terán' ),
			array( 'state_code' => 'NL',  'name' => 'General Treviño' ),
			array( 'state_code' => 'NL',  'name' => 'General Zaragoza' ),
			array( 'state_code' => 'NL',  'name' => 'General Zuazua' ),
			array( 'state_code' => 'NL',  'name' => 'Guadalupe' ),
			array( 'state_code' => 'NL',  'name' => 'Hidalgo' ),
			array( 'state_code' => 'NL',  'name' => 'Higueras' ),
			array( 'state_code' => 'NL',  'name' => 'Hualahuises' ),
			array( 'state_code' => 'NL',  'name' => 'Iturbide' ),
			array( 'state_code' => 'NL',  'name' => 'Juárez' ),
			array( 'state_code' => 'NL',  'name' => 'Lampazos de Naranjo' ),
			array( 'state_code' => 'NL',  'name' => 'Linares' ),
			array( 'state_code' => 'NL',  'name' => 'Los Aldamas' ),
			array( 'state_code' => 'NL',  'name' => 'Los Herreras' ),
			array( 'state_code' => 'NL',  'name' => 'Los Ramones' ),
			array( 'state_code' => 'NL',  'name' => 'Marín' ),
			array( 'state_code' => 'NL',  'name' => 'Melchor Ocampo' ),
			array( 'state_code' => 'NL',  'name' => 'Mier y Noriega' ),
			array( 'state_code' => 'NL',  'name' => 'Mina' ),
			array( 'state_code' => 'NL',  'name' => 'Montemorelos' ),
			array( 'state_code' => 'NL',  'name' => 'Monterrey' ),
			array( 'state_code' => 'NL',  'name' => 'Parás' ),
			array( 'state_code' => 'NL',  'name' => 'Pesquería' ),
			array( 'state_code' => 'NL',  'name' => 'Rayones' ),
			array( 'state_code' => 'NL',  'name' => 'Sabinas Hidalgo' ),
			array( 'state_code' => 'NL',  'name' => 'Salinas Victoria' ),
			array( 'state_code' => 'NL',  'name' => 'San Nicolás de los Garza' ),
			array( 'state_code' => 'NL',  'name' => 'San Pedro Garza García' ),
			array( 'state_code' => 'NL',  'name' => 'Santa Catarina' ),
			array( 'state_code' => 'NL',  'name' => 'Santiago' ),
			array( 'state_code' => 'NL',  'name' => 'Vallecillo' ),
			array( 'state_code' => 'NL',  'name' => 'Villaldama' ),
			array( 'state_code' => 'MI',  'name' => 'Acuitzio' ),
			array( 'state_code' => 'MI',  'name' => 'Aguililla' ),
			array( 'state_code' => 'MI',  'name' => 'Álvaro Obregón' ),
			array( 'state_code' => 'MI',  'name' => 'Angamacutiro' ),
			array( 'state_code' => 'MI',  'name' => 'Angangueo' ),
			array( 'state_code' => 'MI',  'name' => 'Apatzingán' ),
			array( 'state_code' => 'MI',  'name' => 'Aporo' ),
			array( 'state_code' => 'MI',  'name' => 'Aquila' ),
			array( 'state_code' => 'MI',  'name' => 'Ario' ),
			array( 'state_code' => 'MI',  'name' => 'Arteaga' ),
			array( 'state_code' => 'MI',  'name' => 'Briseñas' ),
			array( 'state_code' => 'MI',  'name' => 'Buenavista' ),
			array( 'state_code' => 'MI',  'name' => 'Carácuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Charapan' ),
			array( 'state_code' => 'MI',  'name' => 'Charo' ),
			array( 'state_code' => 'MI',  'name' => 'Chavinda' ),
			array( 'state_code' => 'MI',  'name' => 'Cherán' ),
			array( 'state_code' => 'MI',  'name' => 'Chilchota' ),
			array( 'state_code' => 'MI',  'name' => 'Chinicuila' ),
			array( 'state_code' => 'MI',  'name' => 'Chucándiro' ),
			array( 'state_code' => 'MI',  'name' => 'Churintzio' ),
			array( 'state_code' => 'MI',  'name' => 'Churumuco' ),
			array( 'state_code' => 'MI',  'name' => 'Coahuayana' ),
			array( 'state_code' => 'MI',  'name' => 'Coalcomán de Vázquez Pallares' ),
			array( 'state_code' => 'MI',  'name' => 'Coeneo' ),
			array( 'state_code' => 'MI',  'name' => 'Cojumatlán de Régules' ),
			array( 'state_code' => 'MI',  'name' => 'Contepec' ),
			array( 'state_code' => 'MI',  'name' => 'Copándaro' ),
			array( 'state_code' => 'MI',  'name' => 'Cotija' ),
			array( 'state_code' => 'MI',  'name' => 'Cuitzeo' ),
			array( 'state_code' => 'MI',  'name' => 'Ecuandureo' ),
			array( 'state_code' => 'MI',  'name' => 'Epitacio Huerta' ),
			array( 'state_code' => 'MI',  'name' => 'Erongarícuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Gabriel Zamora' ),
			array( 'state_code' => 'MI',  'name' => 'Hidalgo' ),
			array( 'state_code' => 'MI',  'name' => 'Huandacareo' ),
			array( 'state_code' => 'MI',  'name' => 'Huaniqueo' ),
			array( 'state_code' => 'MI',  'name' => 'Huetamo' ),
			array( 'state_code' => 'MI',  'name' => 'Huiramba' ),
			array( 'state_code' => 'MI',  'name' => 'Indaparapeo' ),
			array( 'state_code' => 'MI',  'name' => 'Irimbo' ),
			array( 'state_code' => 'MI',  'name' => 'Ixtlán' ),
			array( 'state_code' => 'MI',  'name' => 'Jacona' ),
			array( 'state_code' => 'MI',  'name' => 'Jiménez' ),
			array( 'state_code' => 'MI',  'name' => 'Jiquilpan' ),
			array( 'state_code' => 'MI',  'name' => 'José Sixto Verduzco' ),
			array( 'state_code' => 'MI',  'name' => 'Jungapeo' ),
			array( 'state_code' => 'MI',  'name' => 'Juárez' ),
			array( 'state_code' => 'MI',  'name' => 'La Huacana' ),
			array( 'state_code' => 'MI',  'name' => 'La Piedad' ),
			array( 'state_code' => 'MI',  'name' => 'Lagunillas' ),
			array( 'state_code' => 'MI',  'name' => 'Los Reyes' ),
			array( 'state_code' => 'MI',  'name' => 'Lázaro Cárdenas' ),
			array( 'state_code' => 'MI',  'name' => 'Madero' ),
			array( 'state_code' => 'MI',  'name' => 'Maravatío' ),
			array( 'state_code' => 'MI',  'name' => 'Marcos Castellanos' ),
			array( 'state_code' => 'MI',  'name' => 'Morelia' ),
			array( 'state_code' => 'MI',  'name' => 'Morelos' ),
			array( 'state_code' => 'MI',  'name' => 'Múgica' ),
			array( 'state_code' => 'MI',  'name' => 'Nahuatzen' ),
			array( 'state_code' => 'MI',  'name' => 'Nocupétaro' ),
			array( 'state_code' => 'MI',  'name' => 'Nuevo Parangaricutiro' ),
			array( 'state_code' => 'MI',  'name' => 'Nuevo Urecho' ),
			array( 'state_code' => 'MI',  'name' => 'Numarán' ),
			array( 'state_code' => 'MI',  'name' => 'Ocampo' ),
			array( 'state_code' => 'MI',  'name' => 'Pajacuarán' ),
			array( 'state_code' => 'MI',  'name' => 'Panindícuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Paracho' ),
			array( 'state_code' => 'MI',  'name' => 'Parácuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Penjamillo' ),
			array( 'state_code' => 'MI',  'name' => 'Peribán' ),
			array( 'state_code' => 'MI',  'name' => 'Puruándiro' ),
			array( 'state_code' => 'MI',  'name' => 'Purépero' ),
			array( 'state_code' => 'MI',  'name' => 'Pátzcuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Queréndaro' ),
			array( 'state_code' => 'MI',  'name' => 'Quiroga' ),
			array( 'state_code' => 'MI',  'name' => 'Sahuayo' ),
			array( 'state_code' => 'MI',  'name' => 'Salvador Escalante' ),
			array( 'state_code' => 'MI',  'name' => 'San Lucas' ),
			array( 'state_code' => 'MI',  'name' => 'Santa Ana Maya' ),
			array( 'state_code' => 'MI',  'name' => 'Senguio' ),
			array( 'state_code' => 'MI',  'name' => 'Susupuato' ),
			array( 'state_code' => 'MI',  'name' => 'Tacámbaro' ),
			array( 'state_code' => 'MI',  'name' => 'Tancítaro' ),
			array( 'state_code' => 'MI',  'name' => 'Tangamandapio' ),
			array( 'state_code' => 'MI',  'name' => 'Tangancícuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Tanhuato' ),
			array( 'state_code' => 'MI',  'name' => 'Taretan' ),
			array( 'state_code' => 'MI',  'name' => 'Tarímbaro' ),
			array( 'state_code' => 'MI',  'name' => 'Tepalcatepec' ),
			array( 'state_code' => 'MI',  'name' => 'Tingambato' ),
			array( 'state_code' => 'MI',  'name' => 'Tingüindín' ),
			array( 'state_code' => 'MI',  'name' => 'Tiquicheo de Nicolás Romero' ),
			array( 'state_code' => 'MI',  'name' => 'Tlalpujahua' ),
			array( 'state_code' => 'MI',  'name' => 'Tlazazalca' ),
			array( 'state_code' => 'MI',  'name' => 'Tocumbo' ),
			array( 'state_code' => 'MI',  'name' => 'Tumbiscatío' ),
			array( 'state_code' => 'MI',  'name' => 'Turicato' ),
			array( 'state_code' => 'MI',  'name' => 'Tuxpan' ),
			array( 'state_code' => 'MI',  'name' => 'Tuzantla' ),
			array( 'state_code' => 'MI',  'name' => 'Tzintzuntzan' ),
			array( 'state_code' => 'MI',  'name' => 'Tzitzio' ),
			array( 'state_code' => 'MI',  'name' => 'Uruapan' ),
			array( 'state_code' => 'MI',  'name' => 'Venustiano Carranza' ),
			array( 'state_code' => 'MI',  'name' => 'Villamar' ),
			array( 'state_code' => 'MI',  'name' => 'Vista Hermosa' ),
			array( 'state_code' => 'MI',  'name' => 'Yurécuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Zacapu' ),
			array( 'state_code' => 'MI',  'name' => 'Zamora' ),
			array( 'state_code' => 'MI',  'name' => 'Zináparo' ),
			array( 'state_code' => 'MI',  'name' => 'Zinapécuaro' ),
			array( 'state_code' => 'MI',  'name' => 'Ziracuaretiro' ),
			array( 'state_code' => 'MI',  'name' => 'Zitácuaro' ),
		);

		$municipality_ids = array();
		foreach ( $municipalities as $municipality_data ) {
			$state_id = $state_ids[ $municipality_data['state_code'] ] ?? 0;
			if ( $state_id <= 0 ) {
				continue;
			}

			$municipality = $municipalities_repo->get_by_state_and_name( $state_id, $municipality_data['name'], false );
			$payload      = array(
				'state_id' => $state_id,
				'name'     => $municipality_data['name'],
				'is_active' => 1,
			);

			if ( $municipality ) {
				$municipalities_repo->update( (int) $municipality['id'], $payload );
				$municipality_ids[ $municipality_data['name'] ] = (int) $municipality['id'];
				continue;
			}

			$municipality_ids[ $municipality_data['name'] ] = (int) $municipalities_repo->create( $payload );
		}

		$mazatlan_state_id = $state_ids['SI'] ?? 0;
		$mazatlan_municipality_id = $municipality_ids['Mazatlán'] ?? 0;
		if ( $mazatlan_state_id > 0 && $mazatlan_municipality_id > 0 ) {
			self::update_municipality_postcode_coverage(
				$municipalities_repo,
				$mazatlan_municipality_id,
				self::build_postcode_coverage_string( array( array( 'from' => 82000, 'to' => 82384 ) ) )
			);
		}

		$ag_postcodes = array(
			'Aguascalientes'             => array( 'from' => 20000, 'to' => 20299 ),
			'Jesús María'                => array( 'from' => 20300, 'to' => 20399 ),
			'Pabellón de Arteaga'        => array( 'from' => 20400, 'to' => 20499 ),
			'Rincón de Romos'            => array( 'from' => 20500, 'to' => 20599 ),
			'Calvillo'                   => array( 'from' => 20600, 'to' => 20699 ),
			'San Francisco de los Romo'  => array( 'from' => 20700, 'to' => 20749 ),
			'Asientos'                   => array( 'from' => 20750, 'to' => 20799 ),
			'Cosío'                      => array( 'from' => 20800, 'to' => 20849 ),
			'El Llano'                   => array( 'from' => 20850, 'to' => 20899 ),
			'San José de Gracia'         => array( 'from' => 20900, 'to' => 20949 ),
			'Tepezalá'                   => array( 'from' => 20950, 'to' => 20999 ),
		);

		$ag_state_id = $state_ids['AG'] ?? 0;
		foreach ( $ag_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $ag_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage(
				$municipalities_repo,
				$municipality_id,
				self::build_postcode_coverage_string( array( $range ) )
			);
		}

		$za_postcodes = array(
			'Zacatecas'              => array( 'from' => 98000, 'to' => 98299 ),
			'Guadalupe'              => array( 'from' => 98300, 'to' => 98449 ),
			'Fresnillo'              => array( 'from' => 98450, 'to' => 98599 ),
			'Sombrerete'             => array( 'from' => 98600, 'to' => 98699 ),
			'Río Grande'             => array( 'from' => 98700, 'to' => 98799 ),
			'Calera'                 => array( 'from' => 98800, 'to' => 98849 ),
			'Victor Rosales'         => array( 'from' => 98850, 'to' => 98949 ),
			'Pinos'                  => array( 'from' => 98950, 'to' => 99049 ),
			'Loreto'                 => array( 'from' => 99050, 'to' => 99149 ),
			'Francisco I. Madero'    => array( 'from' => 99150, 'to' => 99249 ),
			'General Pánfilo Natera' => array( 'from' => 99250, 'to' => 99349 ),
			'Cuauhtémoc'             => array( 'from' => 99350, 'to' => 99399 ),
			'Trancoso'               => array( 'from' => 99400, 'to' => 99449 ),
			'Sain Alto'              => array( 'from' => 99450, 'to' => 99499 ),
			'Villa García'           => array( 'from' => 99500, 'to' => 99549 ),
			'Miguel Auza'            => array( 'from' => 99550, 'to' => 99599 ),
		);

		$za_state_id = $state_ids['ZA'] ?? 0;
		foreach ( $za_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $za_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage(
				$municipalities_repo,
				$municipality_id,
				self::build_postcode_coverage_string( array( $range ) )
			);
		}

		$mi_postcodes = array(
			'Acuitzio'                       => array( 'from' => 58000, 'to' => 58035 ),
			'Aguililla'                      => array( 'from' => 58036, 'to' => 58071 ),
			'Angamacutiro'                   => array( 'from' => 58072, 'to' => 58107 ),
			'Angangueo'                      => array( 'from' => 58108, 'to' => 58143 ),
			'Apatzingán'                     => array( 'from' => 58144, 'to' => 58179 ),
			'Aporo'                          => array( 'from' => 58180, 'to' => 58215 ),
			'Aquila'                         => array( 'from' => 58216, 'to' => 58251 ),
			'Ario'                           => array( 'from' => 58252, 'to' => 58287 ),
			'Arteaga'                        => array( 'from' => 58288, 'to' => 58323 ),
			'Briseñas'                       => array( 'from' => 58324, 'to' => 58359 ),
			'Buenavista'                     => array( 'from' => 58360, 'to' => 58395 ),
			'Carácuaro'                      => array( 'from' => 58396, 'to' => 58431 ),
			'Charapan'                       => array( 'from' => 58432, 'to' => 58467 ),
			'Charo'                          => array( 'from' => 58468, 'to' => 58503 ),
			'Chavinda'                       => array( 'from' => 58504, 'to' => 58539 ),
			'Cherán'                         => array( 'from' => 58540, 'to' => 58575 ),
			'Chilchota'                      => array( 'from' => 58576, 'to' => 58611 ),
			'Chinicuila'                     => array( 'from' => 58612, 'to' => 58647 ),
			'Chucándiro'                     => array( 'from' => 58648, 'to' => 58683 ),
			'Churintzio'                     => array( 'from' => 58684, 'to' => 58719 ),
			'Churumuco'                      => array( 'from' => 58720, 'to' => 58755 ),
			'Coahuayana'                     => array( 'from' => 58756, 'to' => 58791 ),
			'Coalcomán de Vázquez Pallares'  => array( 'from' => 58792, 'to' => 58827 ),
			'Coeneo'                         => array( 'from' => 58828, 'to' => 58863 ),
			'Cojumatlán de Régules'          => array( 'from' => 58864, 'to' => 58899 ),
			'Contepec'                       => array( 'from' => 58900, 'to' => 58935 ),
			'Copándaro'                      => array( 'from' => 58936, 'to' => 58971 ),
			'Cotija'                         => array( 'from' => 58972, 'to' => 59007 ),
			'Cuitzeo'                        => array( 'from' => 59008, 'to' => 59043 ),
			'Ecuandureo'                     => array( 'from' => 59044, 'to' => 59079 ),
			'Epitacio Huerta'                => array( 'from' => 59080, 'to' => 59115 ),
			'Erongarícuaro'                  => array( 'from' => 59116, 'to' => 59151 ),
			'Gabriel Zamora'                  => array( 'from' => 59152, 'to' => 59187 ),
			'Hidalgo'                        => array( 'from' => 59188, 'to' => 59223 ),
			'Huandacareo'                    => array( 'from' => 59224, 'to' => 59259 ),
			'Huaniqueo'                      => array( 'from' => 59260, 'to' => 59295 ),
			'Huetamo'                        => array( 'from' => 59296, 'to' => 59331 ),
			'Huiramba'                       => array( 'from' => 59332, 'to' => 59367 ),
			'Indaparapeo'                    => array( 'from' => 59368, 'to' => 59403 ),
			'Irimbo'                         => array( 'from' => 59404, 'to' => 59439 ),
			'Ixtlán'                         => array( 'from' => 59440, 'to' => 59475 ),
			'Jacona'                         => array( 'from' => 59476, 'to' => 59511 ),
			'Jiménez'                       => array( 'from' => 59512, 'to' => 59547 ),
			'Jiquilpan'                      => array( 'from' => 59548, 'to' => 59583 ),
			'José Sixto Verduzco'            => array( 'from' => 59584, 'to' => 59619 ),
			'Jungapeo'                       => array( 'from' => 59620, 'to' => 59654 ),
			'Juárez'                         => array( 'from' => 59655, 'to' => 59689 ),
			'La Huacana'                     => array( 'from' => 59690, 'to' => 59724 ),
			'La Piedad'                      => array( 'from' => 59725, 'to' => 59759 ),
			'Lagunillas'                     => array( 'from' => 59760, 'to' => 59794 ),
			'Los Reyes'                      => array( 'from' => 59795, 'to' => 59829 ),
			'Lázaro Cárdenas'                => array( 'from' => 59830, 'to' => 59864 ),
			'Madero'                         => array( 'from' => 59865, 'to' => 59899 ),
			'Maravatío'                      => array( 'from' => 59900, 'to' => 59934 ),
			'Marcos Castellanos'             => array( 'from' => 59935, 'to' => 59969 ),
			'Morelia'                        => array( 'from' => 59970, 'to' => 60004 ),
			'Morelos'                        => array( 'from' => 60005, 'to' => 60039 ),
			'Múgica'                         => array( 'from' => 60040, 'to' => 60074 ),
			'Nahuatzen'                      => array( 'from' => 60075, 'to' => 60109 ),
			'Nocupétaro'                     => array( 'from' => 60110, 'to' => 60144 ),
			'Nuevo Parangaricutiro'          => array( 'from' => 60145, 'to' => 60179 ),
			'Nuevo Urecho'                   => array( 'from' => 60180, 'to' => 60214 ),
			'Numarán'                        => array( 'from' => 60215, 'to' => 60249 ),
			'Ocampo'                         => array( 'from' => 60250, 'to' => 60284 ),
			'Pajacuarán'                     => array( 'from' => 60285, 'to' => 60319 ),
			'Panindícuaro'                   => array( 'from' => 60320, 'to' => 60354 ),
			'Paracho'                        => array( 'from' => 60355, 'to' => 60389 ),
			'Parácuaro'                      => array( 'from' => 60390, 'to' => 60424 ),
			'Penjamillo'                     => array( 'from' => 60425, 'to' => 60459 ),
			'Peribán'                        => array( 'from' => 60460, 'to' => 60494 ),
			'Puruándiro'                     => array( 'from' => 60495, 'to' => 60529 ),
			'Purépero'                       => array( 'from' => 60530, 'to' => 60564 ),
			'Pátzcuaro'                      => array( 'from' => 60565, 'to' => 60599 ),
			'Queréndaro'                     => array( 'from' => 60600, 'to' => 60634 ),
			'Quiroga'                        => array( 'from' => 60635, 'to' => 60669 ),
			'Sahuayo'                        => array( 'from' => 60670, 'to' => 60704 ),
			'Salvador Escalante'             => array( 'from' => 60705, 'to' => 60739 ),
			'San Lucas'                      => array( 'from' => 60740, 'to' => 60774 ),
			'Santa Ana Maya'                  => array( 'from' => 60775, 'to' => 60809 ),
			'Senguio'                        => array( 'from' => 60810, 'to' => 60844 ),
			'Susupuato'                      => array( 'from' => 60845, 'to' => 60879 ),
			'Tacámbaro'                      => array( 'from' => 60880, 'to' => 60914 ),
			'Tancítaro'                      => array( 'from' => 60915, 'to' => 60949 ),
			'Tangamandapio'                  => array( 'from' => 60950, 'to' => 60984 ),
			'Tangancícuaro'                  => array( 'from' => 60985, 'to' => 61019 ),
			'Tanhuato'                       => array( 'from' => 61020, 'to' => 61054 ),
			'Taretan'                        => array( 'from' => 61055, 'to' => 61089 ),
			'Tarímbaro'                      => array( 'from' => 61090, 'to' => 61124 ),
			'Tepalcatepec'                   => array( 'from' => 61125, 'to' => 61159 ),
			'Tingambato'                     => array( 'from' => 61160, 'to' => 61194 ),
			'Tingüindín'                     => array( 'from' => 61195, 'to' => 61229 ),
			'Tiquicheo de Nicolás Romero'    => array( 'from' => 61230, 'to' => 61264 ),
			'Tlalpujahua'                    => array( 'from' => 61265, 'to' => 61299 ),
			'Tlazazalca'                     => array( 'from' => 61300, 'to' => 61334 ),
			'Tocumbo'                        => array( 'from' => 61335, 'to' => 61369 ),
			'Tumbiscatío'                    => array( 'from' => 61370, 'to' => 61404 ),
			'Turicato'                       => array( 'from' => 61405, 'to' => 61439 ),
			'Tuxpan'                         => array( 'from' => 61440, 'to' => 61474 ),
			'Tuzantla'                       => array( 'from' => 61475, 'to' => 61509 ),
			'Tzintzuntzan'                   => array( 'from' => 61510, 'to' => 61544 ),
			'Tzitzio'                        => array( 'from' => 61545, 'to' => 61579 ),
			'Uruapan'                        => array( 'from' => 61580, 'to' => 61614 ),
			'Venustiano Carranza'            => array( 'from' => 61615, 'to' => 61649 ),
			'Villamar'                       => array( 'from' => 61650, 'to' => 61684 ),
			'Vista Hermosa'                  => array( 'from' => 61685, 'to' => 61719 ),
			'Yurécuaro'                      => array( 'from' => 61720, 'to' => 61754 ),
			'Zacapu'                         => array( 'from' => 61755, 'to' => 61789 ),
			'Zamora'                         => array( 'from' => 61790, 'to' => 61824 ),
			'Zináparo'                       => array( 'from' => 61860, 'to' => 61894 ),
			'Zinapécuaro'                    => array( 'from' => 61825, 'to' => 61859 ),
			'Ziracuaretiro'                  => array( 'from' => 61895, 'to' => 61929 ),
			'Zitácuaro'                      => array( 'from' => 61930, 'to' => 61964 ),
			'Álvaro Obregón'                 => array( 'from' => 61965, 'to' => 61999 ),
		);

		$mi_state_id = $state_ids['MI'] ?? 0;
		foreach ( $mi_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $mi_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage( $municipalities_repo, $municipality_id, self::build_postcode_coverage_string( array( $range ) ) );
		}

		$gt_postcodes = array(
			'Abasolo'                                      => array( 'from' => '36970', 'to' => '36987' ),
			'Acámbaro'                                     => array( 'from' => '38600', 'to' => '38787' ),
			'Apaseo el Alto'                               => array( 'from' => '38500', 'to' => '38537' ),
			'Apaseo el Grande'                            => array( 'from' => '38160', 'to' => '38198' ),
			'Atarjea'                                      => array( 'from' => '37940', 'to' => '37948' ),
			'Celaya'                                       => array( 'from' => '38000', 'to' => '38159' ),
			'Comonfort'                                    => array( 'from' => '38200', 'to' => '38227' ),
			'Coroneo'                                      => array( 'from' => '38590', 'to' => '38597' ),
			'Cortazar'                                     => array( 'from' => '38300', 'to' => '38499' ),
			'Cuerámaro'                                    => array( 'from' => '36960', 'to' => '36969' ),
			'Doctor Mora'                                  => array( 'from' => '37960', 'to' => '37967' ),
			'Dolores Hidalgo Cuna de la Independencia Nacional' => array( 'from' => '37800', 'to' => '37849' ),
			'Guanajuato'                                   => array( 'from' => '36003', 'to' => '36268' ),
			'Huanímaro'                                    => array( 'from' => '36990', 'to' => '36998' ),
			'Irapuato'                                     => array( 'from' => '36500', 'to' => '36849' ),
			'Jaral del Progreso'                           => array( 'from' => '38470', 'to' => '38488' ),
			'Jerécuaro'                                     => array( 'from' => '38540', 'to' => '38587' ),
			'León'                                         => array( 'from' => '37000', 'to' => '37698' ),
			'Manuel Doblado'                               => array( 'from' => '36470', 'to' => '36497' ),
			'Moroleón'                                      => array( 'from' => '38800', 'to' => '38997' ),
			'Ocampo'                                       => array( 'from' => '37630', 'to' => '37647' ),
			'Pénjamo'                                      => array( 'from' => '36900', 'to' => '36948' ),
			'Pueblo Nuevo'                                 => array( 'from' => '36890', 'to' => '36897' ),
			'Purísima del Rincón'                          => array( 'from' => '36400', 'to' => '36437' ),
			'Romita'                                       => array( 'from' => '36200', 'to' => '36218' ),
			'Salamanca'                                    => array( 'from' => '36700', 'to' => '36888' ),
			'Salvatierra'                                  => array( 'from' => '38900', 'to' => '38938' ),
			'San Diego de la Unión'                        => array( 'from' => '37850', 'to' => '37877' ),
			'San Felipe'                                   => array( 'from' => '37600', 'to' => '37625' ),
			'San Francisco del Rincón'                       => array( 'from' => '36300', 'to' => '36469' ),
			'San José Iturbide'                            => array( 'from' => '37980', 'to' => '37998' ),
			'San Luis de la Paz'                           => array( 'from' => '37900', 'to' => '37919' ),
			'San Miguel de Allende'                        => array( 'from' => '37700', 'to' => '37898' ),
			'Santa Catarina'                                => array( 'from' => '37950', 'to' => '37958' ),
			'Santa Cruz de Juvenino Rosas'                  => array( 'from' => '38240', 'to' => '38257' ),
			'Santiago Maravatío'                           => array( 'from' => '38970', 'to' => '38978' ),
			'Silao de la Victoria'                         => array( 'from' => '36100', 'to' => '36298' ),
			'Tarandacuao'                                  => array( 'from' => '38790', 'to' => '38798' ),
			'Tarimoro'                                     => array( 'from' => '38700', 'to' => '38727' ),
			'Tierra Blanca'                                 => array( 'from' => '37970', 'to' => '37978' ),
			'Uriangato'                                    => array( 'from' => '38980', 'to' => '38989' ),
			'Valle de Santiago'                             => array( 'from' => '38400', 'to' => '38467' ),
			'Victoria'                                     => array( 'from' => '37920', 'to' => '37928' ),
			'Villagrán'                                    => array( 'from' => '38260', 'to' => '38295' ),
			'Xichú'                                         => array( 'from' => '37930', 'to' => '37939' ),
			'Yuriria'                                      => array( 'from' => '38940', 'to' => '38967' ),
		);

		$gt_state_id = $state_ids['GT'] ?? 0;
		foreach ( $gt_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $gt_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage( $municipalities_repo, $municipality_id, self::build_postcode_coverage_string( array( $range ) ) );
		}

		$qt_postcodes = array(
			'Amealco de Bonfil'         => array( 'from' => 76000, 'to' => 76055 ),
			'Arroyo Seco'               => array( 'from' => 76056, 'to' => 76111 ),
			'Cadereyta de Montes'       => array( 'from' => 76112, 'to' => 76167 ),
			'Colón'                    => array( 'from' => 76168, 'to' => 76223 ),
			'Corregidora'               => array( 'from' => 76224, 'to' => 76279 ),
			'El Marqués'               => array( 'from' => 76280, 'to' => 76335 ),
			'Ezequiel Montes'          => array( 'from' => 76336, 'to' => 76391 ),
			'Huimilpan'                => array( 'from' => 76392, 'to' => 76447 ),
			'Jalpan de Serra'          => array( 'from' => 76448, 'to' => 76503 ),
			'Landa de Matamoros'       => array( 'from' => 76504, 'to' => 76559 ),
			'Pedro Escobedo'            => array( 'from' => 76560, 'to' => 76614 ),
			'Peñamiller'               => array( 'from' => 76615, 'to' => 76669 ),
			'Pinal de Amoles'          => array( 'from' => 76670, 'to' => 76724 ),
			'Querétaro'                => array( 'from' => 76725, 'to' => 76779 ),
			'San Joaquín'               => array( 'from' => 76780, 'to' => 76834 ),
			'San Juan del Río'          => array( 'from' => 76835, 'to' => 76889 ),
			'Tequisquiapan'             => array( 'from' => 76890, 'to' => 76944 ),
			'Tolimán'                  => array( 'from' => 76945, 'to' => 76999 ),
		);

		$qt_state_id = $state_ids['QT'] ?? 0;
		foreach ( $qt_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $qt_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage( $municipalities_repo, $municipality_id, self::build_postcode_coverage_string( array( $range ) ) );
		}

		$sl_postcodes = array(
			'Ahualulco'                      => array( 'from' => 78450, 'to' => 78457 ),
			'Alaquines'                      => array( 'from' => 79360, 'to' => 79377 ),
			'Aquismón'                       => array( 'from' => 79760, 'to' => 79777 ),
			'Armadillo de los Infante'       => array( 'from' => 78980, 'to' => 78997 ),
			'Axtla de Terrazas'              => array( 'from' => 79930, 'to' => 79938 ),
			'Cárdenas'                       => array( 'from' => 79380, 'to' => 79397 ),
			'Catorce'                        => array( 'from' => 78540, 'to' => 78567 ),
			'Cedral'                         => array( 'from' => 78520, 'to' => 78537 ),
			'Cerritos'                       => array( 'from' => 79400, 'to' => 79447 ),
			'Cerro de San Pedro'             => array( 'from' => 78440, 'to' => 78448 ),
			'Charcas'                        => array( 'from' => 78570, 'to' => 78597 ),
			'Ciudad del Maíz'                => array( 'from' => 79320, 'to' => 79357 ),
			'Ciudad Fernández'               => array( 'from' => 79650, 'to' => 79677 ),
			'Ciudad Valles'                  => array( 'from' => 79000, 'to' => 79287 ),
			'Coxcatlán'                      => array( 'from' => 79860, 'to' => 79879 ),
			'Ebano'                          => array( 'from' => 79100, 'to' => 79297 ),
			'El Naranjo'                     => array( 'from' => 79300, 'to' => 79318 ),
			'Guadalcázar'                    => array( 'from' => 78870, 'to' => 78897 ),
			'Huehuetlán'                     => array( 'from' => 79880, 'to' => 79895 ),
			'Lagunillas'                     => array( 'from' => 79780, 'to' => 79788 ),
			'Matehuala'                      => array( 'from' => 78700, 'to' => 78827 ),
			'Matlapa'                        => array( 'from' => 79970, 'to' => 79978 ),
			'Mexquitic de Carmona'           => array( 'from' => 78460, 'to' => 78487 ),
			'Moctezuma'                      => array( 'from' => 78900, 'to' => 78919 ),
			'Rayón'                          => array( 'from' => 79740, 'to' => 79755 ),
			'Rioverde'                       => array( 'from' => 79600, 'to' => 79647 ),
			'Salinas'                        => array( 'from' => 78600, 'to' => 78626 ),
			'San Antonio'                    => array( 'from' => 79830, 'to' => 79837 ),
			'San Ciro de Acosta'             => array( 'from' => 79680, 'to' => 79697 ),
			'San Luis Potosí'                => array( 'from' => 78000, 'to' => 78427 ),
			'San Martín Chalchicuautla'     => array( 'from' => 79950, 'to' => 79958 ),
			'San Nicolás Tolentino'         => array( 'from' => 79480, 'to' => 79493 ),
			'San Vicente Tancuayalab'       => array( 'from' => 79820, 'to' => 79826 ),
			'Santa Catarina'                 => array( 'from' => 79790, 'to' => 79798 ),
			'Santa María del Río'            => array( 'from' => 79560, 'to' => 79589 ),
			'Santo Domingo'                  => array( 'from' => 78630, 'to' => 78649 ),
			'Soledad de Graciano Sánchez'   => array( 'from' => 78430, 'to' => 78439 ),
			'Tamasopo'                       => array( 'from' => 79702, 'to' => 79733 ),
			'Tamazunchale'                   => array( 'from' => 79960, 'to' => 79997 ),
			'Tampacán'                       => array( 'from' => 79940, 'to' => 79948 ),
			'Tampamolón Corona'              => array( 'from' => 79850, 'to' => 79859 ),
			'Tamuín'                         => array( 'from' => 79200, 'to' => 79228 ),
			'Tancanhuitz'                    => array( 'from' => 79800, 'to' => 79807 ),
			'Tanlajás'                       => array( 'from' => 79810, 'to' => 79819 ),
			'Tanquián de Escobedo'          => array( 'from' => 79840, 'to' => 79849 ),
			'Tierra Nueva'                   => array( 'from' => 79590, 'to' => 79599 ),
			'Vanegas'                        => array( 'from' => 78500, 'to' => 78514 ),
			'Venado'                         => array( 'from' => 78920, 'to' => 78937 ),
			'Villa de Arista'                => array( 'from' => 78940, 'to' => 78958 ),
			'Villa de Arriaga'               => array( 'from' => 78490, 'to' => 78497 ),
			'Villa de Guadalupe'             => array( 'from' => 78840, 'to' => 78868 ),
			'Villa de la Paz'                => array( 'from' => 78830, 'to' => 78837 ),
			'Villa de Ramos'                 => array( 'from' => 78660, 'to' => 78697 ),
			'Villa de Reyes'                 => array( 'from' => 79500, 'to' => 79533 ),
			'Villa Hidalgo'                  => array( 'from' => 78960, 'to' => 78979 ),
			'Villa Juárez'                   => array( 'from' => 79450, 'to' => 79471 ),
			'Xilitla'                        => array( 'from' => 79900, 'to' => 79928 ),
			'Zaragoza'                       => array( 'from' => 79540, 'to' => 79557 ),
		);

		$sl_state_id = $state_ids['SL'] ?? 0;
		foreach ( $sl_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $sl_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage( $municipalities_repo, $municipality_id, self::build_postcode_coverage_string( array( $range ) ) );
		}

		$na_postcodes = array(
			'Acaponeta'              => array( 'from' => 63400, 'to' => 63438 ),
			'Ahuacatlán'            => array( 'from' => 63900, 'to' => 63938 ),
			'Amatlán de Cañas'      => array( 'from' => 63960, 'to' => 63996 ),
			'Bahía de Banderas'     => array( 'from' => 63726, 'to' => 63739 ),
			'Compostela'            => array( 'from' => 63700, 'to' => 63724 ),
			'Del Nayar'             => array( 'from' => 63530, 'to' => 63549 ),
			'Huajicori'             => array( 'from' => 63480, 'to' => 63499 ),
			'Ixtlán del Río'        => array( 'from' => 63940, 'to' => 63959 ),
			'Jala'                  => array( 'from' => 63880, 'to' => 63899 ),
			'La Yesca'              => array( 'from' => 63580, 'to' => 63596 ),
			'Rosamorada'            => array( 'from' => 63630, 'to' => 63658 ),
			'Ruíz'                  => array( 'from' => 63600, 'to' => 63628 ),
			'San Blas'              => array( 'from' => 63740, 'to' => 63779 ),
			'San Pedro Lagunillas'  => array( 'from' => 63800, 'to' => 63826 ),
			'Santa María del Oro'   => array( 'from' => 63830, 'to' => 63874 ),
			'Santiago Ixcuintla'   => array( 'from' => 63300, 'to' => 63579 ),
			'Tecuala'              => array( 'from' => 63440, 'to' => 63470 ),
			'Tepic'                 => array( 'from' => 63000, 'to' => 63529 ),
			'Tuxpan'                => array( 'from' => 63200, 'to' => 63670 ),
			'Xalisco'               => array( 'from' => 63780, 'to' => 63799 ),
		);

		$na_state_id = $state_ids['NA'] ?? 0;
		foreach ( $na_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $na_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage( $municipalities_repo, $municipality_id, self::build_postcode_coverage_string( array( $range ) ) );
		}

		$nl_postcodes = array(
			'Abasolo'                  => array( 'from' => 65650, 'to' => 65663 ),
			'Agualeguas'               => array( 'from' => 65800, 'to' => 65837 ),
			'Allende'                  => array( 'from' => 67350, 'to' => 67383 ),
			'Anáhuac'                  => array( 'from' => 65000, 'to' => 65059 ),
			'Apodaca'                  => array( 'from' => 66600, 'to' => 66649 ),
			'Aramberri'                => array( 'from' => 67940, 'to' => 67959 ),
			'Bustamante'               => array( 'from' => 65150, 'to' => 65150 ),
			'Cadereyta Jiménez'        => array( 'from' => 67451, 'to' => 67498 ),
			'Cerralvo'                 => array( 'from' => 65900, 'to' => 65940 ),
			'China'                    => array( 'from' => 67050, 'to' => 67099 ),
			'Ciénega de Flores'       => array( 'from' => 65550, 'to' => 65583 ),
			'Doctor Arroyo'            => array( 'from' => 67900, 'to' => 67938 ),
			'Doctor Coss'              => array( 'from' => 66950, 'to' => 66980 ),
			'Doctor González'          => array( 'from' => 66750, 'to' => 66793 ),
			'El Carmen'                => array( 'from' => 66550, 'to' => 66583 ),
			'Galeana'                  => array( 'from' => 67850, 'to' => 67878 ),
			'García'                   => array( 'from' => 66000, 'to' => 66042 ),
			'General Bravo'            => array( 'from' => 67000, 'to' => 67030 ),
			'General Escobedo'          => array( 'from' => 66050, 'to' => 66085 ),
			'General Terán'            => array( 'from' => 67400, 'to' => 67448 ),
			'General Treviño'          => array( 'from' => 65850, 'to' => 65880 ),
			'General Zaragoza'         => array( 'from' => 67960, 'to' => 67978 ),
			'General Zuazua'           => array( 'from' => 65750, 'to' => 65780 ),
			'Guadalupe'                => array( 'from' => 67100, 'to' => 67205 ),
			'Hidalgo'                  => array( 'from' => 65600, 'to' => 65600 ),
			'Higueras'                 => array( 'from' => 65700, 'to' => 65700 ),
			'Hualahuises'              => array( 'from' => 67880, 'to' => 67899 ),
			'Iturbide'                 => array( 'from' => 67830, 'to' => 67849 ),
			'Juárez'                   => array( 'from' => 67250, 'to' => 67298 ),
			'Lampazos de Naranjo'      => array( 'from' => 65070, 'to' => 65092 ),
			'Linares'                  => array( 'from' => 67700, 'to' => 67828 ),
			'Los Aldamas'              => array( 'from' => 66900, 'to' => 66945 ),
			'Los Herreras'             => array( 'from' => 66850, 'to' => 66880 ),
			'Los Ramones'              => array( 'from' => 66800, 'to' => 66849 ),
			'Marín'                    => array( 'from' => 66700, 'to' => 66708 ),
			'Melchor Ocampo'           => array( 'from' => 65950, 'to' => 65950 ),
			'Mier y Noriega'           => array( 'from' => 67980, 'to' => 67996 ),
			'Mina'                     => array( 'from' => 65100, 'to' => 65140 ),
			'Montemorelos'             => array( 'from' => 67500, 'to' => 67640 ),
			'Monterrey'                => array( 'from' => 64000, 'to' => 64997 ),
			'Parás'                    => array( 'from' => 65450, 'to' => 65480 ),
			'Pesquería'                => array( 'from' => 66650, 'to' => 66693 ),
			'Rayones'                  => array( 'from' => 67650, 'to' => 67690 ),
			'Sabinas Hidalgo'           => array( 'from' => 65200, 'to' => 65348 ),
			'Salinas Victoria'          => array( 'from' => 65500, 'to' => 65548 ),
			'San Nicolás de los Garza' => array( 'from' => 66400, 'to' => 66499 ),
			'San Pedro Garza García'   => array( 'from' => 66200, 'to' => 66297 ),
			'Santa Catarina'            => array( 'from' => 66100, 'to' => 66390 ),
			'Santiago'                  => array( 'from' => 67300, 'to' => 67344 ),
			'Vallecillo'               => array( 'from' => 65400, 'to' => 65449 ),
			'Villaldama'               => array( 'from' => 65350, 'to' => 65395 ),
		);

		$nl_state_id = $state_ids['NL'] ?? 0;
		foreach ( $nl_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $nl_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage( $municipalities_repo, $municipality_id, self::build_postcode_coverage_string( array( $range ) ) );
		}

		$ja_postcodes = array(
			'Acatic'                      => array( 'from' => 45470, 'to' => 45479 ),
			'Acatlán de Juárez'           => array( 'from' => 45700, 'to' => 45723 ),
			'Ahualulco de Mercado'        => array( 'from' => 46730, 'to' => 46758 ),
			'Amacueca'                    => array( 'from' => 49370, 'to' => 49388 ),
			'Amatitán'                    => array( 'from' => 45380, 'to' => 45399 ),
			'Ameca'                       => array( 'from' => 46600, 'to' => 46729 ),
			'Arandas'                     => array( 'from' => 47180, 'to' => 47197 ),
			'Atemajac de Brizuela'       => array( 'from' => 45790, 'to' => 45799 ),
			'Atengo'                      => array( 'from' => 48160, 'to' => 48199 ),
			'Atenguillo'                  => array( 'from' => 48100, 'to' => 48146 ),
			'Atotonilco el Alto'          => array( 'from' => 47750, 'to' => 47779 ),
			'Atoyac'                      => array( 'from' => 49200, 'to' => 49229 ),
			'Autlán de Navarro'           => array( 'from' => 48900, 'to' => 48929 ),
			'Ayotlán'                     => array( 'from' => 47930, 'to' => 47949 ),
			'Ayutla'                      => array( 'from' => 48050, 'to' => 48098 ),
			'Bolaños'                     => array( 'from' => 46130, 'to' => 46157 ),
			'Cabo Corrientes'             => array( 'from' => 48400, 'to' => 48449 ),
			'Cañadas de Obregón'          => array( 'from' => 47360, 'to' => 47378 ),
			'Casimiro Castillo'           => array( 'from' => 48930, 'to' => 48948 ),
			'Chapala'                     => array( 'from' => 45900, 'to' => 45946 ),
			'Chimaltitán'                 => array( 'from' => 46300, 'to' => 46330 ),
			'Chiquilistlán'              => array( 'from' => 48640, 'to' => 48655 ),
			'Cihuatlán'                   => array( 'from' => 48970, 'to' => 48995 ),
			'Cocula'                      => array( 'from' => 48500, 'to' => 48532 ),
			'Colotlán'                    => array( 'from' => 46200, 'to' => 46239 ),
			'Concepción de Buenos Aires'  => array( 'from' => 49170, 'to' => 49178 ),
			'Cuautitlán de García Barragán' => array( 'from' => 48950, 'to' => 48967 ),
			'Cuautla'                     => array( 'from' => 48150, 'to' => 48159 ),
			'Cuquío'                      => array( 'from' => 45480, 'to' => 45496 ),
			'Degollado'                   => array( 'from' => 47980, 'to' => 47998 ),
			'Ejutla'                      => array( 'from' => 48670, 'to' => 48686 ),
			'El Arenal'                   => array( 'from' => 45350, 'to' => 45368 ),
			'El Grullo'                   => array( 'from' => 48740, 'to' => 48753 ),
			'El Limón'                    => array( 'from' => 48700, 'to' => 48739 ),
			'El Salto'                    => array( 'from' => 45680, 'to' => 45696 ),
			'Encarnación de Díaz'        => array( 'from' => 47270, 'to' => 47298 ),
			'Etzatlán'                    => array( 'from' => 46500, 'to' => 46535 ),
			'Gómez Farías'                => array( 'from' => 49120, 'to' => 49150 ),
			'Guachinango'                 => array( 'from' => 46800, 'to' => 46846 ),
			'Guadalajara'                 => array( 'from' => 44100, 'to' => 44990 ),
			'Hostotipaquillo'             => array( 'from' => 46440, 'to' => 46460 ),
			'Huejúcar'                    => array( 'from' => 46260, 'to' => 46299 ),
			'Huejuquilla el Alto'         => array( 'from' => 46000, 'to' => 46037 ),
			'Ixtlahuacán de los Membrillos' => array( 'from' => 45850, 'to' => 45877 ),
			'Ixtlahuacán del Río'         => array( 'from' => 45260, 'to' => 45299 ),
			'Jalostotitlán'              => array( 'from' => 47120, 'to' => 47139 ),
			'Jamay'                       => array( 'from' => 47900, 'to' => 47909 ),
			'Jesús María'                 => array( 'from' => 47950, 'to' => 47977 ),
			'Jilotlán de los Dolores'     => array( 'from' => 49950, 'to' => 49968 ),
			'Jocotepec'                   => array( 'from' => 45800, 'to' => 45840 ),
			'Juanacatlán'                 => array( 'from' => 45880, 'to' => 45899 ),
			'Juchitlán'                   => array( 'from' => 48600, 'to' => 48635 ),
			'La Barca'                    => array( 'from' => 47910, 'to' => 47927 ),
			'La Huerta'                   => array( 'from' => 48850, 'to' => 48898 ),
			'La Manzanilla de la Paz'     => array( 'from' => 49460, 'to' => 49490 ),
			'Lagos de Moreno'             => array( 'from' => 47400, 'to' => 47539 ),
			'Magdalena'                   => array( 'from' => 46470, 'to' => 46495 ),
			'Mascota'                     => array( 'from' => 46900, 'to' => 46955 ),
			'Mazamitla'                   => array( 'from' => 49500, 'to' => 49537 ),
			'Mexticacán'                  => array( 'from' => 47340, 'to' => 47359 ),
			'Mezquitic'                   => array( 'from' => 46040, 'to' => 46087 ),
			'Mixtlán'                     => array( 'from' => 46850, 'to' => 46860 ),
			'Ocotlán'                     => array( 'from' => 47780, 'to' => 47899 ),
			'Ojuelos de Jalisco'          => array( 'from' => 47540, 'to' => 47566 ),
			'Pihuamo'                     => array( 'from' => 49870, 'to' => 49897 ),
			'Poncitlán'                   => array( 'from' => 45950, 'to' => 45979 ),
			'Puerto Vallarta'             => array( 'from' => 48260, 'to' => 48399 ),
			'Quitupan'                    => array( 'from' => 49570, 'to' => 49598 ),
			'San Cristóbal de la Barranca' => array( 'from' => 45250, 'to' => 45259 ),
			'San Diego de Alejandría'     => array( 'from' => 47590, 'to' => 47599 ),
			'San Gabriel'                  => array( 'from' => 49700, 'to' => 49744 ),
			'San Ignacio Cerro Gordo'     => array( 'from' => 47190, 'to' => 47195 ),
			'San Juan de los Lagos'       => array( 'from' => 47000, 'to' => 47119 ),
			'San Juanito de Escobedo'     => array( 'from' => 46560, 'to' => 46580 ),
			'San Julián'                  => array( 'from' => 47170, 'to' => 47179 ),
			'San Marcos'                   => array( 'from' => 46540, 'to' => 46545 ),
			'San Martín de Bolaños'       => array( 'from' => 46350, 'to' => 46386 ),
			'San Martín Hidalgo'           => array( 'from' => 46770, 'to' => 46799 ),
			'San Miguel el Alto'           => array( 'from' => 47140, 'to' => 47160 ),
			'San Pedro Tlaquepaque'        => array( 'from' => 45500, 'to' => 45638 ),
			'San Sebastián del Oeste'     => array( 'from' => 46960, 'to' => 46997 ),
			'Santa María de los Ángeles'   => array( 'from' => 46240, 'to' => 46259 ),
			'Santa María del Oro'          => array( 'from' => 49970, 'to' => 49994 ),
			'Sayula'                       => array( 'from' => 49300, 'to' => 49339 ),
			'Tala'                         => array( 'from' => 45300, 'to' => 45349 ),
			'Talpa de Allende'             => array( 'from' => 48200, 'to' => 48259 ),
			'Tamazula de Gordiano'         => array( 'from' => 49650, 'to' => 49690 ),
			'Tapalpa'                      => array( 'from' => 49340, 'to' => 49369 ),
			'Tecalitlán'                   => array( 'from' => 49900, 'to' => 49943 ),
			'Techaluta de Montenegro'     => array( 'from' => 49230, 'to' => 49240 ),
			'Tecolotlán'                   => array( 'from' => 48540, 'to' => 48569 ),
			'Tenamaxtlán'                  => array( 'from' => 48570, 'to' => 48595 ),
			'Teocaltiche'                  => array( 'from' => 47200, 'to' => 47249 ),
			'Teocuitatlán de Corona'       => array( 'from' => 49250, 'to' => 49296 ),
			'Tepatitlán de Morelos'        => array( 'from' => 47600, 'to' => 47729 ),
			'Tequila'                      => array( 'from' => 46400, 'to' => 46433 ),
			'Teuchitlán'                   => array( 'from' => 46760, 'to' => 46765 ),
			'Tizapán el Alto'              => array( 'from' => 49400, 'to' => 49427 ),
			'Tlajomulco de Zúñiga'        => array( 'from' => 45640, 'to' => 45679 ),
			'Tolimán'                      => array( 'from' => 49750, 'to' => 49769 ),
			'Tomatlán'                     => array( 'from' => 48450, 'to' => 48499 ),
			'Tonalá'                       => array( 'from' => 45400, 'to' => 45429 ),
			'Tonaya'                       => array( 'from' => 48760, 'to' => 48769 ),
			'Tonila'                       => array( 'from' => 49840, 'to' => 49869 ),
			'Totatiche'                    => array( 'from' => 46170, 'to' => 46197 ),
			'Tototlán'                     => array( 'from' => 47730, 'to' => 47749 ),
			'Tuxcacuesco'                  => array( 'from' => 48770, 'to' => 48799 ),
			'Tuxcueca'                     => array( 'from' => 49430, 'to' => 49446 ),
			'Tuxpan'                       => array( 'from' => 49800, 'to' => 49837 ),
			'Unión de San Antonio'         => array( 'from' => 47570, 'to' => 47588 ),
			'Unión de Tula'                => array( 'from' => 48000, 'to' => 48048 ),
			'Valle de Guadalupe'            => array( 'from' => 47380, 'to' => 47398 ),
			'Valle de Juárez'              => array( 'from' => 49540, 'to' => 49548 ),
			'Villa Corona'                 => array( 'from' => 45730, 'to' => 45746 ),
			'Villa Guerrero'                => array( 'from' => 46100, 'to' => 46127 ),
			'Villa Hidalgo'                => array( 'from' => 47250, 'to' => 47269 ),
			'Villa Purificación'           => array( 'from' => 48800, 'to' => 48847 ),
			'Yahualica de González Gallo'  => array( 'from' => 47300, 'to' => 47339 ),
			'Zacoalco de Torres'           => array( 'from' => 45750, 'to' => 45789 ),
			'Zapopan'                      => array( 'from' => 45010, 'to' => 45245 ),
			'Zapotiltic'                   => array( 'from' => 49600, 'to' => 49647 ),
			'Zapotitlán de Vadillo'        => array( 'from' => 49770, 'to' => 49789 ),
			'Zapotlán del Rey'             => array( 'from' => 45980, 'to' => 45998 ),
			'Zapotlán el Grande'           => array( 'from' => 49000, 'to' => 49109 ),
			'Zapotlanejo'                  => array( 'from' => 45430, 'to' => 45466 ),
		);

		$ja_state_id = $state_ids['JA'] ?? 0;
		foreach ( $ja_postcodes as $municipality_name => $range ) {
			$municipality_id = $municipality_ids[ $municipality_name ] ?? 0;
			if ( $ja_state_id <= 0 || $municipality_id <= 0 ) {
				continue;
			}
			self::update_municipality_postcode_coverage( $municipalities_repo, $municipality_id, self::build_postcode_coverage_string( array( $range ) ) );
		}

		$existing_jalisco_rule = $rules_repo->get_items(
			array(
				'state_id'  => $state_ids['JA'] ?? 0,
				'rule_type' => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
			),
			'id ASC',
			1
		);
		if ( ! empty( $existing_jalisco_rule[0] ) ) {
			$rules_repo->update(
				(int) $existing_jalisco_rule[0]['id'],
				array(
					'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
					'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
					'state_id'         => $state_ids['JA'] ?? 0,
					'municipality_id'  => null,
					'postcode_id'      => null,
					'postcode_from'    => '44100',
					'postcode_to'      => '49999',
					'shipping_cost'    => 3000,
					'currency_code'    => 'MXN',
					'priority'         => 100,
					'is_active'        => 1,
					'display_title'    => 'Jalisco',
					'customer_message' => 'Costo de envío: $3,000 MXN',
					'notes'            => 'seed:jalisco-fallback',
				)
			);
		}

		$existing_colima_rule = $rules_repo->get_items(
			array(
				'state_id'  => $state_ids['CL'] ?? 0,
				'rule_type' => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
			),
			'id ASC',
			1
		);
		if ( ! empty( $existing_colima_rule[0] ) ) {
			$rules_repo->update(
				(int) $existing_colima_rule[0]['id'],
				array(
					'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
					'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
					'state_id'         => $state_ids['CL'] ?? 0,
					'municipality_id'  => null,
					'postcode_id'      => null,
					'postcode_from'    => '',
					'postcode_to'      => '',
					'shipping_cost'    => 3000,
					'currency_code'    => 'MXN',
					'priority'         => 200,
					'is_active'        => 1,
					'display_title'    => 'Colima',
					'customer_message' => 'Envíos con costo a todo el estado',
					'notes'            => 'seed:paid-colima',
				)
			);
		}

		$tesistan_state_id = $state_ids['JA'] ?? 0;
		$tesistan_municipality_id = $municipality_ids['Tesistán, Zapopan'] ?? 0;
		if ( $tesistan_state_id > 0 && $tesistan_municipality_id > 0 ) {
			$existing_postcode_rows = $postcodes_repo->get_by_postcode( '45200' );
			$existing_postcode      = null;

			foreach ( $existing_postcode_rows as $postcode_row ) {
				if ( (int) ( $postcode_row['municipality_id'] ?? 0 ) === $tesistan_municipality_id ) {
					$existing_postcode = $postcode_row;
					break;
				}
			}

			$postcode_payload = array(
				'state_id'        => $tesistan_state_id,
				'municipality_id' => $tesistan_municipality_id,
				'postcode'        => '45200',
				'is_active'       => 1,
			);

			if ( $existing_postcode ) {
				$postcodes_repo->update( (int) $existing_postcode['id'], $postcode_payload );
			} else {
				$postcodes_repo->create( $postcode_payload );
			}
		}

		$tesistan_postcode_rows = $postcodes_repo->get_by_postcode( '45200' );
		$tesistan_postcode_id   = 0;
		foreach ( $tesistan_postcode_rows as $postcode_row ) {
			if ( (int) ( $postcode_row['municipality_id'] ?? 0 ) === $tesistan_municipality_id ) {
				$tesistan_postcode_id = (int) $postcode_row['id'];
				break;
			}
		}

		if ( $tesistan_postcode_id > 0 ) {
			$existing_tesistan_rule = $rules_repo->get_items(
				array(
					'match_type' => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE,
					'rule_type'  => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				),
				'id ASC',
				1
			);

			if ( ! empty( $existing_tesistan_rule[0] ) ) {
				$rules_repo->update(
					(int) $existing_tesistan_rule[0]['id'],
					array(
						'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE,
						'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
						'state_id'         => $tesistan_state_id,
						'municipality_id'   => $tesistan_municipality_id,
						'postcode_id'       => $tesistan_postcode_id,
						'postcode_from'     => '',
						'postcode_to'       => '',
						'shipping_cost'     => 0,
						'currency_code'     => 'MXN',
						'priority'          => 5,
						'is_active'         => 1,
						'display_title'     => 'Tesistán, Zapopan',
						'customer_message'  => 'Envío GRATIS en CP 45200',
						'notes'             => 'seed:free-tesistan',
					)
				);
			}
		}

		// Shipping rules are no longer seeded on install.
		return;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$rules_repo->get_table_name()} WHERE notes LIKE %s",
				'seed:%'
			)
		);

		$rules = array(
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_UNAVAILABLE,
				'state_id'         => $state_ids['SI'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '',
				'postcode_to'      => '',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 50,
				'is_active'        => 1,
				'display_title'    => 'Sinaloa',
				'customer_message' => 'Sin cobertura fuera de Mazatlán',
				'notes'            => 'seed:no-sinaloa',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['SI'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '82000',
				'postcode_to'      => '82139',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 10,
				'is_active'        => 1,
				'display_title'    => 'Mazatlán',
				'customer_message' => 'Cobertura dentro de Mazatlán',
				'notes'            => 'seed:mazatlan-paid',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				'state_id'         => $state_ids['JA'] ?? 0,
				'municipality_id'  => $municipality_ids['Tesistán, Zapopan'] ?? null,
				'postcode_id'      => $tesistan_postcode_id ?? null,
				'postcode_from'    => '',
				'postcode_to'      => '',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 5,
				'is_active'        => 1,
				'display_title'    => 'Tesistán, Zapopan',
				'customer_message' => 'Envío GRATIS en CP 45200',
				'notes'            => 'seed:free-tesistan',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				'state_id'         => $state_ids['JA'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '44100',
				'postcode_to'      => '45440',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 10,
				'is_active'        => 1,
				'display_title'    => 'Guadalajara',
				'customer_message' => 'Envío GRATIS en CP 44100–45440',
				'notes'            => 'seed:free-guadalajara',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				'state_id'         => $state_ids['JA'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '45010',
				'postcode_to'      => '45240',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 20,
				'is_active'        => 1,
				'display_title'    => 'Zapopan',
				'customer_message' => 'Envío GRATIS en CP 45010–45240',
				'notes'            => 'seed:free-zapopan',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				'state_id'         => $state_ids['JA'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '45640',
				'postcode_to'      => '45679',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 30,
				'is_active'        => 1,
				'display_title'    => 'Tlajomulco de Zúñiga',
				'customer_message' => 'Envío GRATIS en CP 45640–45679',
				'notes'            => 'seed:free-tlajomulco',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				'state_id'         => $state_ids['JA'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '45500',
				'postcode_to'      => '45619',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 40,
				'is_active'        => 1,
				'display_title'    => 'San Pedro Tlaquepaque',
				'customer_message' => 'Envío GRATIS en CP 45500–45619',
				'notes'            => 'seed:free-tlaquepaque',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['JA'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '44100',
				'postcode_to'      => '49999',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 100,
				'is_active'        => 1,
				'display_title'    => 'Jalisco',
				'customer_message' => 'Costo de envío: $3,000 MXN',
				'notes'            => 'seed:jalisco-fallback',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				'state_id'         => $state_ids['AG'] ?? 0,
				'municipality_id'  => $municipality_ids['Aguascalientes'] ?? null,
				'postcode_id'      => null,
				'postcode_from'    => '20000',
				'postcode_to'      => '20299',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 10,
				'is_active'        => 1,
				'display_title'    => 'Aguascalientes ciudad',
				'customer_message' => 'Envío GRATIS en CP 20000–20299',
				'notes'            => 'seed:free-aguascalientes-ciudad',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['AG'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '20300',
				'postcode_to'      => '20999',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'Aguascalientes',
				'customer_message' => 'Costo de envío: $3,000 MXN',
				'notes'            => 'seed:paid-aguascalientes',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['CL'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '',
				'postcode_to'      => '',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'Colima',
				'customer_message' => 'Envíos con costo a todo el estado',
				'notes'            => 'seed:paid-colima',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				'state_id'         => $state_ids['ZA'] ?? 0,
				'municipality_id'  => $municipality_ids['Zacatecas'] ?? null,
				'postcode_id'      => null,
				'postcode_from'    => '98000',
				'postcode_to'      => '98299',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 10,
				'is_active'        => 1,
				'display_title'    => 'Zacatecas ciudad',
				'customer_message' => 'Envío GRATIS en CP 98000–98299',
				'notes'            => 'seed:free-zacatecas-ciudad',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['ZA'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '98300',
				'postcode_to'      => '99999',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'Zacatecas',
				'customer_message' => 'Costo de envío: $3,000 MXN',
				'notes'            => 'seed:paid-zacatecas',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
				'state_id'         => $state_ids['MI'] ?? 0,
				'municipality_id'  => $municipality_ids['Morelia'] ?? null,
				'postcode_id'      => null,
				'postcode_from'    => '58000',
				'postcode_to'      => '58499',
				'shipping_cost'    => 0,
				'currency_code'    => 'MXN',
				'priority'         => 10,
				'is_active'        => 1,
				'display_title'    => 'Morelia',
				'customer_message' => 'Envío GRATIS en CP 58000–58499',
				'notes'            => 'seed:free-morelia',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['MI'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '58500',
				'postcode_to'      => '61999',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'Michoacán',
				'customer_message' => 'Costo de envío: $3,000 MXN',
				'notes'            => 'seed:paid-michoacan',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['GT'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '',
				'postcode_to'      => '',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'Guanajuato',
				'customer_message' => 'Envíos con costo a todo el estado',
				'notes'            => 'seed:paid-guanajuato',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['QT'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '',
				'postcode_to'      => '',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'Querétaro',
				'customer_message' => 'Envíos con costo a todo el estado',
				'notes'            => 'seed:paid-queretaro',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['SL'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '',
				'postcode_to'      => '',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'San Luis Potosí',
				'customer_message' => 'Envíos con costo a todo el estado',
				'notes'            => 'seed:paid-slp',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['NA'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '',
				'postcode_to'      => '',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'Nayarit',
				'customer_message' => 'Envíos con costo a todo el estado',
				'notes'            => 'seed:paid-nayarit',
			),
			array(
				'match_type'       => ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
				'rule_type'        => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
				'state_id'         => $state_ids['NL'] ?? 0,
				'municipality_id'  => null,
				'postcode_id'      => null,
				'postcode_from'    => '',
				'postcode_to'      => '',
				'shipping_cost'    => 3000,
				'currency_code'    => 'MXN',
				'priority'         => 200,
				'is_active'        => 1,
				'display_title'    => 'Nuevo León',
				'customer_message' => 'Envíos con costo a todo el estado',
				'notes'            => 'seed:paid-nuevo-leon',
			),
		);

		foreach ( $rules as $rule_data ) {
			$existing_rule = self::find_shipping_rule( $rules_repo, $rule_data );
			$payload       = $rule_data;

			if ( isset( $payload['state_id'] ) ) {
				$payload['state_id'] = ! empty( $payload['state_id'] ) ? absint( $payload['state_id'] ) : null;
			}
			if ( isset( $payload['municipality_id'] ) ) {
				$payload['municipality_id'] = ! empty( $payload['municipality_id'] ) ? absint( $payload['municipality_id'] ) : null;
			}
			if ( isset( $payload['postcode_id'] ) ) {
				$payload['postcode_id'] = ! empty( $payload['postcode_id'] ) ? absint( $payload['postcode_id'] ) : null;
			}

			if ( $existing_rule ) {
				$rules_repo->update( (int) $existing_rule['id'], $payload );
				continue;
			}

			$rules_repo->create( $payload );
		}
	}

	/**
	 * Find a shipping rule matching the provided seed data.
	 *
	 * @param ADMBike_Woo_Locations_Shipping_Rule_Repository $repo Rules repository.
	 * @param array<string, mixed>                            $rule_data Seed data.
	 * @return array<string, mixed>|null
	 */
	protected static function find_shipping_rule( $repo, array $rule_data ) {
		global $wpdb;

		$table = $repo->get_table_name();
		$sql   = "SELECT * FROM {$table} WHERE match_type = %s AND rule_type = %s";
		$args  = array(
			(string) $rule_data['match_type'],
			(string) $rule_data['rule_type'],
		);

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE === $rule_data['match_type'] ) {
			if ( ! empty( $rule_data['postcode_id'] ) ) {
				$sql   .= ' AND postcode_id = %d';
				$args[] = absint( $rule_data['postcode_id'] );
			} else {
				$sql .= ' AND postcode_id IS NULL';
			}

			if ( array_key_exists( 'notes', $rule_data ) ) {
				$sql   .= ' AND notes = %s';
				$args[] = (string) $rule_data['notes'];
			}
		} elseif ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE === $rule_data['match_type'] ) {
			if ( ! empty( $rule_data['state_id'] ) ) {
				$sql   .= ' AND state_id = %d';
				$args[] = absint( $rule_data['state_id'] );
			}

			$sql   .= ' AND postcode_from = %s AND postcode_to = %s';
			$args[] = (string) $rule_data['postcode_from'];
			$args[] = (string) $rule_data['postcode_to'];

			if ( array_key_exists( 'notes', $rule_data ) ) {
				$sql   .= ' AND notes = %s';
				$args[] = (string) $rule_data['notes'];
			}
		} elseif ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE === $rule_data['match_type'] ) {
			if ( ! empty( $rule_data['state_id'] ) ) {
				$sql   .= ' AND state_id = %d';
				$args[] = absint( $rule_data['state_id'] );
			}

			if ( array_key_exists( 'notes', $rule_data ) ) {
				$sql   .= ' AND notes = %s';
				$args[] = (string) $rule_data['notes'];
			}
		}

		$sql  .= ' LIMIT 1';
		$query = $wpdb->prepare( $sql, $args );
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return $row ?: null;
	}
}
