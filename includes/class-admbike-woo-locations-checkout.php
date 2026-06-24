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
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout_coverage' ), 10, 2 );
		add_action( 'woocommerce_checkout_posted_data', array( $this, 'save_checkout_posted_data' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_order_location_meta' ), 10, 3 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'log_store_api_customer_request' ), 5, 2 );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'log_store_api_customer_request' ), 5, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'log_store_api_order_request' ), 5, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'output_inline_config' ), 5 );
	}

	/**
	 * Log Store API customer update requests for debugging.
	 *
	 * @param WC_Customer      $customer Customer object.
	 * @param WP_REST_Request  $request Request object.
	 * @return void
	 */
	public function log_store_api_customer_request( $customer, $request ) {
		if ( ! class_exists( 'ADMBike_Woo_Locations_Logger' ) || ! $request instanceof WP_REST_Request ) {
			return;
		}

		ADMBike_Woo_Locations_Logger::warning(
			'Store API customer request received',
			array(
				'billing_address'  => $this->sanitize_store_api_address_for_log( $request['billing_address'] ?? array() ),
				'shipping_address' => $this->sanitize_store_api_address_for_log( $request['shipping_address'] ?? array() ),
			)
		);
	}

	/**
	 * Log Store API order update requests for debugging.
	 *
	 * @param WC_Order        $order Order object.
	 * @param WP_REST_Request  $request Request object.
	 * @return void
	 */
	public function log_store_api_order_request( $order, $request ) {
		if ( ! class_exists( 'ADMBike_Woo_Locations_Logger' ) || ! $request instanceof WP_REST_Request ) {
			return;
		}

		ADMBike_Woo_Locations_Logger::warning(
			'Store API order request received',
			array(
				'payment_method'   => sanitize_text_field( (string) ( $request['payment_method'] ?? '' ) ),
				'billing_address'  => $this->sanitize_store_api_address_for_log( $request['billing_address'] ?? array() ),
				'shipping_address' => $this->sanitize_store_api_address_for_log( $request['shipping_address'] ?? array() ),
			)
		);
	}

	/**
	 * Sanitize a Store API address for logging.
	 *
	 * @param array<string, mixed> $address Address data.
	 * @return array<string, string>
	 */
	protected function sanitize_store_api_address_for_log( $address ) {
		$address = is_array( $address ) ? $address : array();

		return array(
			'first_name' => isset( $address['first_name'] ) ? sanitize_text_field( (string) $address['first_name'] ) : '',
			'last_name'  => isset( $address['last_name'] ) ? sanitize_text_field( (string) $address['last_name'] ) : '',
			'city'       => isset( $address['city'] ) ? sanitize_text_field( (string) $address['city'] ) : '',
			'state'      => isset( $address['state'] ) ? sanitize_text_field( (string) $address['state'] ) : '',
			'postcode'   => isset( $address['postcode'] ) ? sanitize_text_field( (string) $address['postcode'] ) : '',
			'country'    => isset( $address['country'] ) ? sanitize_text_field( (string) $address['country'] ) : '',
		);
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

		$fields['billing']['billing_country'] = array(
			'label'    => __( 'País', 'admbike-woo-locations' ),
			'required' => true,
			'class'    => array( 'form-row-wide', 'admbike-hidden' ),
			'priority' => 5,
			'type'     => 'country',
			'default'  => 'MX',
		);

		$fields['shipping']['shipping_country'] = array(
			'label'    => __( 'País', 'admbike-woo-locations' ),
			'required' => true,
			'class'    => array( 'form-row-wide', 'admbike-hidden' ),
			'priority' => 5,
			'type'     => 'country',
			'default'  => 'MX',
		);

		$fields['billing']['admbike_state_id'] = array(
			'label'       => __( 'Estado', 'admbike-woo-locations' ),
			'required'    => true,
			'class'        => array( 'form-row-wide', 'admbike-field' ),
			'priority'    => 70,
			'type'        => 'select',
			'options'     => array( '' => __( 'Selecciona un estado…', 'admbike-woo-locations' ) ),
			'input_class' => array( 'admbike-state-select' ),
		);

		$fields['billing']['admbike_pickup_toggle'] = array(
			'label'       => __( 'Ubicaciones de recolección', 'admbike-woo-locations' ),
			'required'    => false,
			'class'       => array( 'form-row-wide', 'admbike-field', 'admbike-pickup-toggle' ),
			'priority'    => 69,
			'type'        => 'checkbox',
			'description' => __( 'Activa esta opción para elegir una ubicación de recolección y rellenar los campos de WooCommerce.', 'admbike-woo-locations' ),
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
		if ( ! empty( $_POST['admbike_state_id'] ) && isset( WC()->session ) && WC()->session ) {
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
	 * Validate that the selected location has coverage before order creation.
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 * @param WP_Error              $errors Validation errors.
	 * @return void
	 */
	public function validate_checkout_coverage( $data, $errors ) {
		$location = $this->get_checkout_location( $data );

		if ( empty( $location['postcode'] ) ) {
			return;
		}

		$coverage = $this->get_coverage_for_location( $location );

		if ( empty( $coverage ) || ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_UNAVAILABLE === ( $coverage[0]['rule_type'] ?? '' ) ) {
			$message = __( 'No contamos con cobertura para esta ubicación.', 'admbike-woo-locations' );

			if ( is_object( $errors ) && method_exists( $errors, 'add' ) ) {
				$errors->add( 'admbike_no_coverage', $message );
			}

			wc_add_notice( $message, 'error' );
		}
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

		$location = array();
		if ( isset( WC()->session ) && WC()->session ) {
			$location = WC()->session->get( self::SESSION_KEY );
		}

		if ( empty( $location ) || ! is_array( $location ) ) {
			$location = $this->get_checkout_location( is_array( $posted_data ) ? $posted_data : array() );
		}

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
	 * Build checkout location from posted data and session fallback.
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 * @return array<string, int|string>
	 */
	protected function get_checkout_location( array $data = array() ) {
		$session_location = array();
		if ( isset( WC()->session ) ) {
			$session_location = (array) WC()->session->get( self::SESSION_KEY, array() );
		}

		$state_id = 0;
		if ( ! empty( $data['admbike_state_id'] ) ) {
			$state_id = absint( $data['admbike_state_id'] );
		} elseif ( ! empty( $_POST['admbike_state_id'] ) ) {
			$state_id = absint( wp_unslash( $_POST['admbike_state_id'] ) );
		} elseif ( ! empty( $session_location['state_id'] ) ) {
			$state_id = absint( $session_location['state_id'] );
		}

		$municipality_id = 0;
		if ( ! empty( $data['admbike_municipality_id'] ) ) {
			$municipality_id = absint( $data['admbike_municipality_id'] );
		} elseif ( ! empty( $_POST['admbike_municipality_id'] ) ) {
			$municipality_id = absint( wp_unslash( $_POST['admbike_municipality_id'] ) );
		} elseif ( ! empty( $session_location['municipality_id'] ) ) {
			$municipality_id = absint( $session_location['municipality_id'] );
		}

		$postcode = '';
		if ( ! empty( $data['admbike_postcode_select'] ) ) {
			$postcode = sanitize_text_field( (string) $data['admbike_postcode_select'] );
		} elseif ( ! empty( $_POST['admbike_postcode_select'] ) ) {
			$postcode = sanitize_text_field( wp_unslash( (string) $_POST['admbike_postcode_select'] ) );
		} elseif ( ! empty( $session_location['postcode'] ) ) {
			$postcode = sanitize_text_field( (string) $session_location['postcode'] );
		}

		return array(
			'state_id'        => $state_id,
			'municipality_id' => $municipality_id,
			'postcode'        => $postcode,
		);
	}

	/**
	 * Resolve matching coverage rules for a checkout location.
	 *
	 * @param array<string, int|string> $location Checkout location.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_coverage_for_location( array $location ) {
		$state_id        = isset( $location['state_id'] ) ? absint( $location['state_id'] ) : 0;
		$municipality_id = isset( $location['municipality_id'] ) ? absint( $location['municipality_id'] ) : 0;
		$postcode        = isset( $location['postcode'] ) ? (string) $location['postcode'] : '';

		$state_repo = new ADMBike_Woo_Locations_Shipping_Rule_Repository();
		return $state_repo->get_applicable_rules( $state_id, $municipality_id, $postcode );
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
