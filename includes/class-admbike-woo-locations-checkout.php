<?php
/**
 * Checkout integration for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Checkout {

	/**
	 * Session key for location data.
	 */
	public const SESSION_KEY = 'admbike_checkout_location';

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_fields', array( $this, 'override_checkout_fields' ), 5 );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'output_checkout_selectors' ) );
		add_action( 'woocommerce_checkout_posted_data', array( $this, 'save_checkout_posted_data' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_order_location_meta' ), 10, 3 );
		add_action( 'woocommerce_order_itemshipping_item', array( $this, 'attach_location_to_order_item' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'output_inline_config' ), 5 );
	}

	/**
	 * Override WooCommerce checkout fields with our dependent selectors.
	 *
	 * @param array<string, mixed> $fields Checkout fields.
	 * @return array<string, mixed>
	 */
	public function override_checkout_fields( $fields ) {
		if ( ! is_checkout() ) {
			return $fields;
		}

		$fields['billing']['admbike_state_id'] = array(
			'label'       => __( 'Estado', 'admbike-woo-locations' ),
			'required'    => true,
			'class'        => array( 'form-row-wide', 'admbike-field' ),
			'priority'    => 70,
			'type'        => 'select',
			'options'     => array( '' => __( 'Selecciona un estado…', 'admbike-woo-locations' ) ),
			'input_class' => array( 'admbike-state-select' ),
		);

		$fields['billing']['admbike_municipality_id'] = array(
			'label'       => __( 'Municipio / Ciudad', 'admbike-woo-locations' ),
			'required'    => true,
			'class'       => array( 'form-row-wide', 'admbike-field', 'admbike-hidden' ),
			'priority'    => 71,
			'type'        => 'select',
			'options'     => array( '' => __( 'Selecciona un municipio…', 'admbike-woo-locations' ) ),
			'input_class' => array( 'admbike-municipality-select' ),
		);

		$fields['billing']['admbike_postcode_select'] = array(
			'label'       => __( 'Código Postal', 'admbike-woo-locations' ),
			'required'    => true,
			'class'       => array( 'form-row-wide', 'admbike-field', 'admbike-hidden' ),
			'priority'    => 72,
			'type'        => 'select',
			'options'     => array( '' => __( 'Selecciona un código postal…', 'admbike-woo-locations' ) ),
			'input_class' => array( 'admbike-postcode-select' ),
		);

		$fields['billing']['admbike_coverage_info'] = array(
			'label'       => '',
			'required'   => false,
			'class'      => array( 'form-row-wide', 'admbike-coverage-info', 'admbike-hidden' ),
			'priority'   => 73,
			'type'       => 'hidden',
			'input_class' => array( 'admbike-coverage-info-input' ),
		);

		unset( $fields['billing']['billing_postcode'] );
		unset( $fields['billing']['billing_city'] );

		return $fields;
	}

	/**
	 * Output the checkout selector HTML and JS initialization.
	 *
	 * @return void
	 */
	public function output_checkout_selectors() {
		if ( ! is_checkout() ) {
			return;
		}
		?>
		<div id="admbike-checkout-overlay" class="admbike-checkout-overlay" style="display:none;">
			<div class="admbike-checkout-loading">
				<span class="admbike-spinner"></span>
				<span><?php esc_html_e( 'Cargando opciones de envío…', 'admbike-woo-locations' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Capture and store the custom checkout posted data in session.
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 * @return array<string, mixed>
	 */
	public function save_checkout_posted_data( $data ) {
		if ( ! empty( $_POST['admbike_state_id'] ) ) {
			WC()->session->set( self::SESSION_KEY, array(
				'state_id'        => absint( $_POST['admbike_state_id'] ),
				'municipality_id' => ! empty( $_POST['admbike_municipality_id'] ) ? absint( $_POST['admbike_municipality_id'] ) : 0,
				'postcode'        => isset( $_POST['admbike_postcode_select'] ) ? sanitize_text_field( (string) $_POST['admbike_postcode_select'] ) : '',
				'postcode_raw'    => isset( $_POST['admbike_postcode_select'] ) ? sanitize_text_field( (string) $_POST['admbike_postcode_select'] ) : '',
			) );
		}

		return $data;
	}

	/**
	 * Save location data as order meta after order is processed.
	 *
	 * @param int $order_id Order ID.
	 * @param array $posted_data Posted checkout data (unused).
	 * @param WC_Order|int|null $order Order object or ID (optional, for WC 9+ compatibility).
	 * @return void
	 */
	public function save_order_location_meta( $order_id, $posted_data, $order = null ) {
		$order = is_a( $order, 'WC_Order' ) ? $order : wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$location = WC()->session->get( self::SESSION_KEY );

		if ( ! $location || ! is_array( $location ) ) {
			return;
		}

		$states_repo = new ADMBike_Woo_Locations_State_Repository();
		$muni_repo   = new ADMBike_Woo_Locations_Municipality_Repository();
		$pc_repo     = new ADMBike_Woo_Locations_Postcode_Repository();

		$state   = $states_repo->get_by_id( $location['state_id'] );
		$muni    = $muni_repo->get_by_id( $location['municipality_id'] );
		$postcode = $location['postcode'];

		$order->update_meta_data( '_admbike_state_id', $location['state_id'] );
		$order->update_meta_data( '_admbike_state_name', $state ? $state['name'] : '' );
		$order->update_meta_data( '_admbike_state_code', $state ? $state['code'] : '' );
		$order->update_meta_data( '_admbike_municipality_id', $location['municipality_id'] );
		$order->update_meta_data( '_admbike_municipality_name', $muni ? $muni['name'] : '' );
		$order->update_meta_data( '_admbike_postcode', $postcode );

		if ( ! empty( $postcode ) ) {
			$pc_rows = $pc_repo->get_by_postcode( $postcode );
			if ( ! empty( $pc_rows ) ) {
				$order->update_meta_data( '_admbike_municipality_id', $pc_rows[0]['municipality_id'] );
			}
		}

		$order->save();
	}

	/**
	 * Enqueue frontend CSS and JS.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_checkout() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'admbike-woo-locations-checkout',
			ADMBIKE_WOO_LOCATIONS_URL . 'assets/css/checkout.css',
			array(),
			ADMBIKE_WOO_LOCATIONS_VERSION
		);

		wp_enqueue_script(
			'admbike-woo-locations-checkout',
			ADMBIKE_WOO_LOCATIONS_URL . 'assets/js/checkout.js',
			array( 'jquery', 'wc-checkout' ),
			ADMBIKE_WOO_LOCATIONS_VERSION,
			true
		);

		wp_localize_script(
			'admbike-woo-locations-checkout',
			'admbikeCheckout',
			array(
				'restUrl'    => rest_url( 'admbike-woo-locations/v1/' ),
				'i18n'       => array(
					'selectState'       => __( 'Selecciona un estado…', 'admbike-woo-locations' ),
					'selectMunicipality'=> __( 'Selecciona un municipio…', 'admbike-woo-locations' ),
					'selectPostcode'    => __( 'Selecciona un código postal…', 'admbike-woo-locations' ),
					'noCoverage'        => __( 'No contamos con cobertura para esta ubicación.', 'admbike-woo-locations' ),
					'loading'           => __( 'Cargando…', 'admbike-woo-locations' ),
				),
			)
		);
	}

	/**
	 * Output inline configuration for AJAX fallback when REST API is unavailable.
	 *
	 * @return void
	 */
	public function output_inline_config() {
		if ( ! is_checkout() ) {
			return;
		}

		$states_repo = new ADMBike_Woo_Locations_State_Repository();
		$muni_repo   = new ADMBike_Woo_Locations_Municipality_Repository();
		$pc_repo     = new ADMBike_Woo_Locations_Postcode_Repository();

		$states = $states_repo->get_active_states();
		$munis  = $muni_repo->get_items( array( 'is_active' => 1 ), 'name ASC' );
		$pcs    = $pc_repo->get_items( array( 'is_active' => 1 ), 'postcode ASC' );

		$states_data = array_map(
			function ( $s ) {
				return array( 'id' => (int) $s['id'], 'name' => $s['name'], 'code' => $s['code'] );
			},
			$states
		);

		$munis_data = array_map(
			function ( $m ) {
				return array( 'id' => (int) $m['id'], 'state_id' => (int) $m['state_id'], 'name' => $m['name'] );
			},
			$munis
		);

		$pcs_data = array_map(
			function ( $p ) {
				return array( 'id' => (int) $p['id'], 'municipality_id' => (int) $p['municipality_id'], 'postcode' => $p['postcode'] );
			},
			$pcs
		);

		printf(
			'<script id="admbike-location-data" type="application/json">%s</script>',
			wp_json_encode(
				array(
					'states' => $states_data,
					'municipalities' => $munis_data,
					'postcodes' => $pcs_data,
				)
			)
		);
	}
}
