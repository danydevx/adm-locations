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
		$allowed_pages = array(
			self::STATES_SLUG,
			self::MUNICIPALITIES_SLUG,
			self::POSTCODES_SLUG,
			self::SHIPPING_SLUG,
		);

		$screen = get_current_screen();

		if ( ! in_array( $screen->id, $allowed_pages, true ) ) {
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ADM Bike Locations', 'admbike-woo-locations' ); ?></h1>
			<p><?php esc_html_e( 'Shipping coverage management by State, Municipality and Postal Code.', 'admbike-woo-locations' ); ?></p>
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
