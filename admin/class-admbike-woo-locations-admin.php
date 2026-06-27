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
	public const CAPABILITY = 'manage_woocommerce';

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

		wp_enqueue_script(
			'admbike-woo-locations-admin',
			ADMBIKE_WOO_LOCATIONS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ADMBIKE_WOO_LOCATIONS_VERSION,
			true
		);
	}

	/**
	 * Render the main admin page (menu container).
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['admbike_no_coverage_message_nonce'] ) ) {
			if ( ! $this->verify_post_nonce( 'admbike_no_coverage_message' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'admbike-woo-locations' ) );
			}

			$message = isset( $_POST['admbike_no_coverage_message'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['admbike_no_coverage_message'] ) ) : '';
			update_option( ADMBike_Woo_Locations::OPTION_NO_COVERAGE_MESSAGE, $message );

			$this->redirect_with_message( 'success', urlencode( __( 'Coverage message updated successfully.', 'admbike-woo-locations' ) ), array( 'page' => self::SLUG ) );
		}

		$message = admbike_woo_locations() ? admbike_woo_locations()->get_saved_no_coverage_message() : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ADM Bike Locations', 'admbike-woo-locations' ); ?></h1>
			<p><?php esc_html_e( 'Shipping coverage management by State, Municipality and Postal Code.', 'admbike-woo-locations' ); ?></p>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'WooCommerce no-shipping messages are handled by the shipping hooks now. The plugin filters the default WooCommerce message when no coverage applies.', 'admbike-woo-locations' ); ?></p>
				<p><code>woocommerce_no_shipping_available_html</code> / <code>woocommerce_cart_no_shipping_available_html</code></p>
			</div>
			<form method="post" action="">
				<?php wp_nonce_field( 'admbike_no_coverage_message', 'admbike_no_coverage_message_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="admbike_no_coverage_message"><?php esc_html_e( 'No Coverage Message', 'admbike-woo-locations' ); ?></label></th>
						<td>
							<textarea id="admbike_no_coverage_message" name="admbike_no_coverage_message" rows="10" class="large-text code"><?php echo esc_textarea( $message ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Save this text to replace the WooCommerce no-shipping message. Leave blank to keep WooCommerce default.', 'admbike-woo-locations' ); ?></p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Message', 'admbike-woo-locations' ); ?></button></p>
			</form>
			<hr>
			<p><?php esc_html_e( 'Use the submenu above to manage States, Municipalities, Postal Codes and Shipping Rules.', 'admbike-woo-locations' ); ?></p>
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
