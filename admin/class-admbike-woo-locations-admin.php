<?php
/**
 * Admin controller for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Admin {

	/**
	 * Admin page slugs.
	 */
	public const SLUG = 'admbike-woo-locations';
	public const STATES_SLUG      = 'admbike-woo-locations-states';
	public const MUNICIPALITIES_SLUG = 'admbike-woo-locations-municipalities';
	public const POSTCODES_SLUG   = 'admbike-woo-locations-postcodes';
	public const SHIPPING_SLUG    = 'admbike-woo-locations-shipping';

	/**
	 * Required capability.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		add_menu_page(
			__( 'ADM Bike Locations', 'admbike-woo-locations' ),
			__( 'ADM Locations', 'admbike-woo-locations' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-location',
			56
		);

		add_submenu_page(
			self::SLUG,
			__( 'States', 'admbike-woo-locations' ),
			__( 'States', 'admbike-woo-locations' ),
			self::CAPABILITY,
			self::STATES_SLUG,
			array( $this, 'render_states_page' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Municipalities', 'admbike-woo-locations' ),
			__( 'Municipalities', 'admbike-woo-locations' ),
			self::CAPABILITY,
			self::MUNICIPALITIES_SLUG,
			array( $this, 'render_municipalities_page' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Postal Codes', 'admbike-woo-locations' ),
			__( 'Postal Codes', 'admbike-woo-locations' ),
			self::CAPABILITY,
			self::POSTCODES_SLUG,
			array( $this, 'render_postcodes_page' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Shipping Rules', 'admbike-woo-locations' ),
			__( 'Shipping Rules', 'admbike-woo-locations' ),
			self::CAPABILITY,
			self::SHIPPING_SLUG,
			array( $this, 'render_shipping_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$hook = (string) $hook;

		if ( false === strpos( $hook, self::SLUG ) && false === strpos( $hook, self::STATES_SLUG ) && false === strpos( $hook, self::MUNICIPALITIES_SLUG ) && false === strpos( $hook, self::POSTCODES_SLUG ) && false === strpos( $hook, self::SHIPPING_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'admbike-woo-locations-admin',
			ADMBIKE_WOO_LOCATIONS_URL . 'assets/css/admin.css',
			array(),
			ADMBIKE_WOO_LOCATIONS_VERSION
		);
	}

	/**
	 * Render the main admin page (menu container).
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['orpot_woo_locations_no_coverage_message_nonce'] ) ) {
			if ( ! $this->verify_post_nonce( 'orpot_woo_locations_no_coverage_message' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'admbike-woo-locations' ) );
			}

			$message = isset( $_POST['orpot_woo_locations_no_coverage_message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['orpot_woo_locations_no_coverage_message'] ) ) : '';
			update_option( ADMBike_Woo_Locations::OPTION_NO_COVERAGE_MESSAGE, $message );

			$this->redirect_with_message( 'success', urlencode( __( 'Coverage message updated successfully.', 'admbike-woo-locations' ) ), array( 'page' => self::SLUG ) );
		}

		$plugin = orpot_woo_locations();
		$states_total = $plugin ? $plugin->states()->count_all() : 0;
		$states_active = $plugin ? $plugin->states()->count_all( '', 1 ) : 0;
		$municipalities_total = $plugin ? $plugin->municipalities()->count_all() : 0;
		$municipalities_active = $plugin ? $plugin->municipalities()->count_all( '', null, 1 ) : 0;
		$postcodes_total = $plugin ? $plugin->postcodes()->count_all() : 0;
		$postcodes_active = $plugin ? $plugin->postcodes()->count_all( '', null, null, 1 ) : 0;
		$rules_total = $plugin ? $plugin->shipping_rules()->count_all() : 0;
		$rules_active = $plugin ? $plugin->shipping_rules()->count_all( '', null, null, 1 ) : 0;
		$message = $plugin ? $plugin->get_saved_no_coverage_message() : '';
		$message_preview = '' !== trim( $message ) ? wp_trim_words( wp_strip_all_tags( $message ), 18, '…' ) : __( 'No coverage message configured yet.', 'admbike-woo-locations' );
		$metric_suffix = '%1$s activos';
		?>
		<div class="wrap admbike-admin-wrap admbike-dashboard-wrap">
			<div class="admbike-dashboard-hero">
				<div class="admbike-dashboard-hero__copy">
			<p class="admbike-dashboard-kicker"><?php echo esc_html( 'Panel' ); ?></p>
					<h1><?php echo esc_html( __( 'Orpot Mexico Woo Reglas', 'admbike-woo-locations' ) ); ?></h1>
					<p><?php echo esc_html( 'Administración de cobertura de envíos por Estado, Municipio y Código Postal.' ); ?></p>
				</div>
				<div class="admbike-dashboard-hero__actions">
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SHIPPING_SLUG . '&action=add' ) ); ?>"><?php echo esc_html( 'Nueva regla' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::STATES_SLUG . '&action=add' ) ); ?>"><?php echo esc_html( 'Estados' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MUNICIPALITIES_SLUG . '&action=add' ) ); ?>"><?php echo esc_html( 'Municipios' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::POSTCODES_SLUG . '&action=add' ) ); ?>"><?php echo esc_html( 'Códigos postales' ); ?></a>
				</div>
			</div>

			<?php $this->render_admin_messages(); ?>

			<div class="admbike-dashboard-stats">
				<?php
				$cards = array(
					array(
						'label' => 'Estados',
						'value' => $states_total,
						'meta'  => sprintf( $metric_suffix, number_format_i18n( $states_active ) ),
						'url'   => admin_url( 'admin.php?page=' . self::STATES_SLUG ),
						'icon'  => 'dashicons-location',
					),
					array(
						'label' => 'Municipios',
						'value' => $municipalities_total,
						'meta'  => sprintf( $metric_suffix, number_format_i18n( $municipalities_active ) ),
						'url'   => admin_url( 'admin.php?page=' . self::MUNICIPALITIES_SLUG ),
						'icon'  => 'dashicons-admin-site',
					),
					array(
						'label' => 'Códigos postales',
						'value' => $postcodes_total,
						'meta'  => sprintf( $metric_suffix, number_format_i18n( $postcodes_active ) ),
						'url'   => admin_url( 'admin.php?page=' . self::POSTCODES_SLUG ),
						'icon'  => 'dashicons-tag',
					),
					array(
						'label' => 'Reglas de envío',
						'value' => $rules_total,
						'meta'  => sprintf( $metric_suffix, number_format_i18n( $rules_active ) ),
						'url'   => admin_url( 'admin.php?page=' . self::SHIPPING_SLUG ),
						'icon'  => 'dashicons-randomize',
					),
				);

				foreach ( $cards as $card ) :
					?>
					<div class="admbike-stat-card">
						<div class="admbike-stat-card__icon"><span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>"></span></div>
						<div class="admbike-stat-card__body">
							<div class="admbike-stat-card__value"><?php echo esc_html( number_format_i18n( (int) $card['value'] ) ); ?></div>
							<div class="admbike-stat-card__label"><?php echo esc_html( $card['label'] ); ?></div>
							<div class="admbike-stat-card__meta"><?php echo esc_html( $card['meta'] ); ?></div>
						</div>
						<a class="admbike-stat-card__link" href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( 'Abrir' ); ?></a>
					</div>
					<?php
				endforeach;
				?>
			</div>

			<div class="admbike-dashboard-grid">
				<section class="admbike-panel admbike-panel--wide">
					<div class="admbike-panel__head">
						<div>
							<p class="admbike-panel__eyebrow"><?php echo esc_html( 'Estado del sistema' ); ?></p>
							<h2><?php echo esc_html( 'Mensaje de cobertura' ); ?></h2>
						</div>
						<span class="admbike-panel__badge"><?php echo esc_html( number_format_i18n( $rules_active ) ); ?> <?php echo esc_html( 'activos' ); ?></span>
					</div>

					<p class="admbike-panel__summary"><?php echo esc_html( $message_preview ); ?></p>

					<form method="post" action="" class="admbike-message-form">
						<?php wp_nonce_field( 'orpot_woo_locations_no_coverage_message', 'orpot_woo_locations_no_coverage_message_nonce' ); ?>
						<label for="orpot_woo_locations_no_coverage_message" class="screen-reader-text"><?php echo esc_html( 'Mensaje de cobertura sin envío' ); ?></label>
						<textarea id="orpot_woo_locations_no_coverage_message" name="orpot_woo_locations_no_coverage_message" rows="6" class="large-text code"><?php echo esc_textarea( $message ); ?></textarea>
						<p class="description"><?php echo esc_html( 'Guarda este texto para reemplazar el mensaje de WooCommerce cuando no haya envío. Déjalo vacío para conservar el mensaje predeterminado.' ); ?></p>
						<p><button type="submit" class="button button-primary"><?php echo esc_html( 'Guardar mensaje' ); ?></button></p>
					</form>
				</section>

				<section class="admbike-panel">
					<p class="admbike-panel__eyebrow"><?php echo esc_html( 'Acciones rápidas' ); ?></p>
					<ul class="admbike-quick-actions">
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::STATES_SLUG ) ); ?>"><?php esc_html_e( 'States', 'admbike-woo-locations' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MUNICIPALITIES_SLUG ) ); ?>"><?php esc_html_e( 'Municipalities', 'admbike-woo-locations' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::POSTCODES_SLUG ) ); ?>"><?php esc_html_e( 'Postal Codes', 'admbike-woo-locations' ); ?></a></li>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SHIPPING_SLUG ) ); ?>"><?php esc_html_e( 'Shipping Rules', 'admbike-woo-locations' ); ?></a></li>
					</ul>

					<div class="admbike-panel__note">
						<p><code>woocommerce_no_shipping_available_html</code></p>
						<p><code>woocommerce_cart_no_shipping_available_html</code></p>
					</div>
				</section>
			</div>

			<div class="admbike-dashboard-grid admbike-dashboard-grid--compact">
				<section class="admbike-panel">
					<p class="admbike-panel__eyebrow"><?php echo esc_html( 'Resumen' ); ?></p>
					<ul class="admbike-status-list">
						<li><?php echo esc_html( sprintf( '%1$s activos', number_format_i18n( $states_active ) ) ); ?> Estados</li>
						<li><?php echo esc_html( sprintf( '%1$s activos', number_format_i18n( $municipalities_active ) ) ); ?> Municipios</li>
						<li><?php echo esc_html( sprintf( '%1$s activos', number_format_i18n( $postcodes_active ) ) ); ?> Códigos postales</li>
						<li><?php echo esc_html( sprintf( '%1$s activos', number_format_i18n( $rules_active ) ) ); ?> Reglas de envío</li>
					</ul>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the states page.
	 *
	 * @return void
	 */
	public function render_states_page() {
		$this->render_view( 'states-page' );
	}

	/**
	 * Render the municipalities page.
	 *
	 * @return void
	 */
	public function render_municipalities_page() {
		$this->render_view( 'municipalities-page' );
	}

	/**
	 * Render the postcodes page.
	 *
	 * @return void
	 */
	public function render_postcodes_page() {
		$this->render_view( 'postcodes-page' );
	}

	/**
	 * Render the shipping rules page.
	 *
	 * @return void
	 */
	public function render_shipping_page() {
		$this->render_view( 'shipping-page' );
	}

	/**
	 * Get the WooCommerce MX state codes reference list.
	 *
	 * @return array<string, string>
	 */
	public static function get_woocommerce_mx_states() {
		return array(
			'DF' => __( 'Ciudad de Mexico', 'admbike-woo-locations' ),
			'JA' => __( 'Jalisco', 'admbike-woo-locations' ),
			'NL' => __( 'Nuevo Leon', 'admbike-woo-locations' ),
			'AG' => __( 'Aguascalientes', 'admbike-woo-locations' ),
			'BC' => __( 'Baja California', 'admbike-woo-locations' ),
			'BS' => __( 'Baja California Sur', 'admbike-woo-locations' ),
			'CM' => __( 'Campeche', 'admbike-woo-locations' ),
			'CS' => __( 'Chiapas', 'admbike-woo-locations' ),
			'CH' => __( 'Chihuahua', 'admbike-woo-locations' ),
			'CO' => __( 'Coahuila', 'admbike-woo-locations' ),
			'CL' => __( 'Colima', 'admbike-woo-locations' ),
			'DG' => __( 'Durango', 'admbike-woo-locations' ),
			'GT' => __( 'Guanajuato', 'admbike-woo-locations' ),
			'GR' => __( 'Guerrero', 'admbike-woo-locations' ),
			'HG' => __( 'Hidalgo', 'admbike-woo-locations' ),
			'MX' => __( 'Estado de Mexico', 'admbike-woo-locations' ),
			'MI' => __( 'Michoacan', 'admbike-woo-locations' ),
			'MO' => __( 'Morelos', 'admbike-woo-locations' ),
			'NA' => __( 'Nayarit', 'admbike-woo-locations' ),
			'OA' => __( 'Oaxaca', 'admbike-woo-locations' ),
			'PU' => __( 'Puebla', 'admbike-woo-locations' ),
			'QT' => __( 'Queretaro', 'admbike-woo-locations' ),
			'QR' => __( 'Quintana Roo', 'admbike-woo-locations' ),
			'SL' => __( 'San Luis Potosi', 'admbike-woo-locations' ),
			'SI' => __( 'Sinaloa', 'admbike-woo-locations' ),
			'SO' => __( 'Sonora', 'admbike-woo-locations' ),
			'TB' => __( 'Tabasco', 'admbike-woo-locations' ),
			'TM' => __( 'Tamaulipas', 'admbike-woo-locations' ),
			'TL' => __( 'Tlaxcala', 'admbike-woo-locations' ),
			'VE' => __( 'Veracruz', 'admbike-woo-locations' ),
			'YU' => __( 'Yucatan', 'admbike-woo-locations' ),
			'ZA' => __( 'Zacatecas', 'admbike-woo-locations' ),
		);
	}

	/**
	 * Include a view file.
	 *
	 * @param string $view View name without extension.
	 * @return void
	 */
	protected function render_view( $view ) {
		$file = ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/' . $view . '.php';

		if ( ! file_exists( $file ) ) {
			wp_die( esc_html__( 'View not found.', 'admbike-woo-locations' ) );
		}

		include $file;
	}

	/**
	 * Verify a nonce and current user capability.
	 *
	 * @param string $action Nonce action.
	 * @param string $query_arg Nonce query argument name.
	 * @return bool
	 */
	public function verify_nonce( $action, $query_arg = '_wpnonce' ) {
		if ( ! isset( $_GET[ $query_arg ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ $query_arg ] ) ), $action ) ) {
			return false;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Verify a POST nonce and current user capability.
	 *
	 * @param string $action Nonce action.
	 * @return bool
	 */
	public function verify_post_nonce( $action ) {
		if ( ! isset( $_POST[ $action . '_nonce' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $action . '_nonce' ] ) ), $action ) ) {
			return false;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Redirect back with a message.
	 *
	 * @param string $type Message type: success, error.
	 * @param string $message Message text.
	 * @param array<string, mixed> $extra Extra query args.
	 * @return void
	 */
	public function redirect_with_message( $type, $message, array $extra = array() ) {
		$url = add_query_arg(
			array_merge(
				array(
					'message' => $type . ':' . $message,
				),
				$extra
			),
			wp_get_referer()
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Display a persistent admin message.
	 *
	 * @return void
	 */
	public function render_admin_messages() {
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$raw = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		$parts = explode( ':', $raw, 2 );

		if ( count( $parts ) < 2 ) {
			return;
		}

		$type    = $parts[0];
		$message = urldecode( $parts[1] );

		if ( ! in_array( $type, array( 'success', 'error', 'warning' ), true ) ) {
			return;
		}

		$class = 'notice notice-' . $type;

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
}
