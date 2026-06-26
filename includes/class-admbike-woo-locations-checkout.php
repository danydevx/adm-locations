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
		add_action( 'woocommerce_store_api_cart_errors', array( $this, 'validate_store_api_shipping_coverage' ), 10, 2 );
		add_action( 'woocommerce_checkout_posted_data', array( $this, 'save_checkout_posted_data' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'clear_postcode_when_municipality_empty' ), 20 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_order_location_meta' ), 10, 3 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'save_store_api_shipping_location_from_request' ), 5, 2 );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'save_store_api_shipping_location_from_request' ), 5, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_store_api_order_location_meta' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'output_inline_config' ), 5 );
	}

	/**
	 * Save the shipping address from Store API requests into session.
	 *
	 * @param WC_Customer      $customer Customer object.
	 * @param WP_REST_Request  $request Request object.
	 * @return void
	 */
	public function save_store_api_shipping_location_from_request( $customer, $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return;
		}

		$shipping_address = isset( $request['shipping_address'] ) && is_array( $request['shipping_address'] ) ? $request['shipping_address'] : array();
		$location         = $this->resolve_location_from_shipping_address( $shipping_address );

		if ( empty( $location['state_id'] ) && empty( $location['municipality_id'] ) && empty( $location['postcode'] ) ) {
			return;
		}

		if ( isset( WC()->session ) && WC()->session ) {
			$previous_location = (array) WC()->session->get( self::SESSION_KEY, array() );

			WC()->session->set( self::SESSION_KEY, $location );

			if ( $previous_location !== $location ) {
				$this->invalidate_shipping_session_cache();
			}
		}

		if ( class_exists( 'ADMBike_Woo_Locations_Logger' ) ) {
			ADMBike_Woo_Locations_Logger::warning(
				'Store API shipping location saved',
				array(
					'location' => $location,
				)
			);
		}
	}

	/**
	 * Save order meta for Store API orders.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function save_store_api_order_location_meta( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$this->persist_order_location_meta( $order );
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
			$location = array(
				'state_id'        => absint( $_POST['admbike_state_id'] ),
				'municipality_id' => ! empty( $_POST['admbike_municipality_id'] ) ? absint( $_POST['admbike_municipality_id'] ) : 0,
				'postcode'        => isset( $_POST['admbike_postcode_select'] ) ? sanitize_text_field( (string) $_POST['admbike_postcode_select'] ) : '',
				'postcode_raw'    => isset( $_POST['admbike_postcode_select'] ) ? sanitize_text_field( (string) $_POST['admbike_postcode_select'] ) : '',
				'city'            => isset( $_POST['billing_city'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['billing_city'] ) ) : ( isset( $_POST['shipping_city'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['shipping_city'] ) ) : '' ),
			);

			$previous_location = (array) WC()->session->get( self::SESSION_KEY, array() );
			WC()->session->set( self::SESSION_KEY, $location );

			if ( $previous_location !== $location ) {
				$this->invalidate_shipping_session_cache();
			}
		}

		return $data;
	}

	protected function invalidate_shipping_session_cache() {
		if ( ! isset( WC()->session ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( 'chosen_shipping_methods', array() );

		for ( $i = 0; $i < 10; $i++ ) {
			$key = 'shipping_for_package_' . $i;
			if ( method_exists( WC()->session, '__unset' ) ) {
				WC()->session->__unset( $key );
			} else {
				WC()->session->set( $key, null );
			}
		}

		if ( is_object( $customer ) ) {
			if ( method_exists( $customer, 'set_shipping_country' ) ) {
				$customer->set_shipping_country( ! empty( $location['country'] ) ? (string) $location['country'] : 'MX' );
			}
			if ( method_exists( $customer, 'set_shipping_state' ) && ! empty( $location['state_code'] ) ) {
				$customer->set_shipping_state( (string) $location['state_code'] );
			}
			if ( method_exists( $customer, 'set_shipping_city' ) ) {
				$customer->set_shipping_city( ! empty( $location['city'] ) ? (string) $location['city'] : '' );
			}
			if ( method_exists( $customer, 'set_shipping_postcode' ) ) {
				$customer->set_shipping_postcode( ! empty( $location['postcode'] ) ? (string) $location['postcode'] : '' );
			}
		}

		$shipping_address['country'] = ! empty( $location['country'] ) ? (string) $location['country'] : 'MX';
		if ( ! empty( $location['state_code'] ) ) {
			$shipping_address['state'] = (string) $location['state_code'];
		}
		if ( ! empty( $location['city'] ) ) {
			$shipping_address['city'] = (string) $location['city'];
		}
		if ( ! empty( $location['postcode'] ) ) {
			$shipping_address['postcode'] = (string) $location['postcode'];
		}

		if ( $request instanceof ArrayAccess ) {
			$request['shipping_address'] = $shipping_address;
		}
	}

	/**
	 * Clear billing/shipping postcode when municipality is empty.
	 *
	 * When the municipality select is at its placeholder (empty value),
	 * the postcode from a previous selection must not be used by
	 * WooCommerce for shipping calculation. This prevents free/paid
	 * shipping rules from being incorrectly matched based on a stale
	 * postcode that belongs to a different municipality.
	 *
	 * @param array<string, mixed> $post_data Posted checkout data.
	 * @return array<string, mixed>
	 */
	public function clear_postcode_when_municipality_empty( $post_data ) {
		if ( ! is_array( $post_data ) ) {
			return $post_data;
		}

		$municipality_id = ! empty( $post_data['municipality_id'] ) ? absint( $post_data['municipality_id'] ) : 0;
		$city            = isset( $post_data['city'] ) ? sanitize_text_field( (string) $post_data['city'] ) : '';

		if ( $municipality_id <= 0 && empty( $city ) ) {
			$post_data['billing_postcode'] = '';
			$post_data['shipping_postcode'] = '';
		}

		return $post_data;
	}

	/**
	 * Validate that the selected location has coverage before order creation.
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 * @param WP_Error              $errors Validation errors.
	 * @return void
	 */
	public function validate_checkout_coverage( $data, $errors ) {
		if ( $this->is_pickup_selected( $data ) ) {
			return;
		}

		$location = $this->get_checkout_location( $data );

		if ( empty( $location['postcode'] ) && empty( $location['municipality_id'] ) && empty( $location['city'] ) ) {
			return;
		}

		$coverage = $this->get_coverage_for_location( $location );

		if ( empty( $coverage ) || ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_UNAVAILABLE === ( $coverage[0]['rule_type'] ?? '' ) ) {
			$message = $this->get_no_coverage_message();

			if ( is_object( $errors ) && method_exists( $errors, 'add' ) ) {
				$errors->add( 'admbike_no_coverage', $message );
			}

			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * Validate shipping coverage for Store API checkout/cart requests.
	 *
	 * @param WP_Error $errors Validation errors.
	 * @param WC_Cart   $cart Cart object.
	 * @return void
	 */
	public function validate_store_api_shipping_coverage( $errors, $cart ) {
		if ( $this->is_pickup_selected() ) {
			return;
		}

		$location = $this->get_checkout_location( array() );
		if ( empty( $location['postcode'] ) && empty( $location['state_id'] ) && empty( $location['municipality_id'] ) && empty( $location['city'] ) ) {
			return;
		}

		$coverage = $this->get_coverage_for_location( $location );
		if ( ! empty( $coverage ) && ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_UNAVAILABLE !== ( $coverage[0]['rule_type'] ?? '' ) ) {
			return;
		}

		if ( is_object( $errors ) && method_exists( $errors, 'add' ) ) {
			$errors->add( 'admbike_no_coverage', $this->get_no_coverage_message() );
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

		$this->persist_order_location_meta( $order, $location );
	}

	/**
	 * Persist order meta from a resolved checkout location.
	 *
	 * @param WC_Order               $order Order object.
	 * @param array<string, mixed>    $location Resolved location.
	 * @return void
	 */
	protected function persist_order_location_meta( $order, array $location = array() ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		if ( empty( $location ) ) {
			$location = array();
			if ( isset( WC()->session ) && WC()->session ) {
				$location = (array) WC()->session->get( self::SESSION_KEY, array() );
			}
		}

		if ( empty( $location ) ) {
			return;
		}

		$states_repo = new ADMBike_Woo_Locations_State_Repository();
		$muni_repo   = new ADMBike_Woo_Locations_Municipality_Repository();
		$pc_repo     = new ADMBike_Woo_Locations_Postcode_Repository();

		$state    = ! empty( $location['state_id'] ) ? $states_repo->get_by_id( $location['state_id'] ) : null;
		$muni     = ! empty( $location['municipality_id'] ) ? $muni_repo->get_by_id( $location['municipality_id'] ) : null;
		$postcode = isset( $location['postcode'] ) ? sanitize_text_field( (string) $location['postcode'] ) : '';

		$order->update_meta_data( '_admbike_state_id', isset( $location['state_id'] ) ? absint( $location['state_id'] ) : 0 );
		$order->update_meta_data( '_admbike_state_name', $state ? $state['name'] : '' );
		$order->update_meta_data( '_admbike_state_code', $state ? $state['code'] : '' );
		$order->update_meta_data( '_admbike_municipality_id', isset( $location['municipality_id'] ) ? absint( $location['municipality_id'] ) : 0 );
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
	 * Resolve checkout location from a Store API shipping address.
	 *
	 * @param array<string, mixed> $shipping_address Shipping address.
	 * @return array<string, int|string>
	 */
	protected function resolve_location_from_shipping_address( array $shipping_address ) {
		$states_repo = new ADMBike_Woo_Locations_State_Repository();
		$muni_repo   = new ADMBike_Woo_Locations_Municipality_Repository();
		$pc_repo     = new ADMBike_Woo_Locations_Postcode_Repository();

		$state_value = isset( $shipping_address['state'] ) ? sanitize_text_field( (string) $shipping_address['state'] ) : '';
		$city_value   = isset( $shipping_address['city'] ) ? sanitize_text_field( (string) $shipping_address['city'] ) : '';
		$postcode     = isset( $shipping_address['postcode'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $shipping_address['postcode'] ) : '';

		$state_id = 0;
		if ( '' !== $state_value ) {
			$state = $states_repo->get_by_code_or_name( $state_value );
			if ( $state && ! empty( $state['id'] ) ) {
				$state_id = absint( $state['id'] );
			}
		}

		$municipality_id = 0;
		if ( '' !== $postcode ) {
			$pc_rows = $pc_repo->get_by_postcode( $postcode );
			if ( ! empty( $pc_rows ) ) {
				$municipality_id = ! empty( $pc_rows[0]['municipality_id'] ) ? absint( $pc_rows[0]['municipality_id'] ) : 0;
				$state_id        = ! empty( $pc_rows[0]['state_id'] ) ? absint( $pc_rows[0]['state_id'] ) : $state_id;
			}
		}

		$municipality_name = '';

		if ( $municipality_id <= 0 && '' !== $city_value ) {
			if ( $state_id > 0 ) {
				$municipality = $muni_repo->get_by_state_and_name( $state_id, $city_value );
				if ( $municipality && ! empty( $municipality['id'] ) ) {
					$municipality_id = absint( $municipality['id'] );
					$municipality_name = ! empty( $municipality['name'] ) ? sanitize_text_field( (string) $municipality['name'] ) : $city_value;
				}
			}

			if ( $municipality_id <= 0 ) {
				$municipality = $muni_repo->get_by_name( $city_value );
				if ( $municipality && ! empty( $municipality['id'] ) ) {
					$municipality_id = absint( $municipality['id'] );
					$municipality_name = ! empty( $municipality['name'] ) ? sanitize_text_field( (string) $municipality['name'] ) : $city_value;
					if ( $state_id <= 0 && ! empty( $municipality['state_id'] ) ) {
						$state_id = absint( $municipality['state_id'] );
					}
				}
			}
		}

		if ( '' === $municipality_name && $municipality_id > 0 ) {
			$municipality = $muni_repo->get_by_id( $municipality_id );
			if ( $municipality && ! empty( $municipality['name'] ) ) {
				$municipality_name = sanitize_text_field( (string) $municipality['name'] );
			}
		}

		return array(
			'country'         => isset( $shipping_address['country'] ) ? sanitize_text_field( (string) $shipping_address['country'] ) : 'MX',
			'state_id'        => $state_id,
			'municipality_id' => $municipality_id,
			'postcode'        => $postcode,
			'state_code'      => '' !== $state_value ? strtoupper( $state_value ) : '',
			'city'            => '' !== $municipality_name ? $municipality_name : $city_value,
		);
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

		$city = '';
		if ( ! empty( $data['billing_city'] ) ) {
			$city = sanitize_text_field( (string) $data['billing_city'] );
		} elseif ( ! empty( $data['shipping_city'] ) ) {
			$city = sanitize_text_field( (string) $data['shipping_city'] );
		} elseif ( ! empty( $_POST['billing_city'] ) ) {
			$city = sanitize_text_field( wp_unslash( (string) $_POST['billing_city'] ) );
		} elseif ( ! empty( $_POST['shipping_city'] ) ) {
			$city = sanitize_text_field( wp_unslash( (string) $_POST['shipping_city'] ) );
		} elseif ( ! empty( $session_location['city'] ) ) {
			$city = sanitize_text_field( (string) $session_location['city'] );
		}

		if ( '' === $city && $municipality_id > 0 ) {
			$municipality_repo = new ADMBike_Woo_Locations_Municipality_Repository();
			$municipality      = $municipality_repo->get_by_id( $municipality_id );
			if ( $municipality && ! empty( $municipality['name'] ) ) {
				$city = sanitize_text_field( (string) $municipality['name'] );
			}
		}

		return array(
			'state_id'        => $state_id,
			'municipality_id' => $municipality_id,
			'postcode'        => $postcode,
			'city'            => $city,
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
		$city            = isset( $location['city'] ) ? sanitize_text_field( (string) $location['city'] ) : '';

		if ( $municipality_id <= 0 && '' !== $city ) {
			$muni_repo = new ADMBike_Woo_Locations_Municipality_Repository();
			if ( $state_id > 0 ) {
				$municipality = $muni_repo->get_by_state_and_name( $state_id, $city );
				if ( $municipality && ! empty( $municipality['id'] ) ) {
					$municipality_id = absint( $municipality['id'] );
				}
			}

			if ( $municipality_id <= 0 ) {
				$municipality = $muni_repo->get_by_name( $city );
				if ( $municipality && ! empty( $municipality['id'] ) ) {
					$municipality_id = absint( $municipality['id'] );
					if ( $state_id <= 0 && ! empty( $municipality['state_id'] ) ) {
						$state_id = absint( $municipality['state_id'] );
					}
				}
			}
		}

		if ( 0 === $municipality_id && '' === $postcode ) {
			return array();
		}

		if ( '' !== $postcode && $state_id > 0 ) {
			$postcode_repo  = new ADMBike_Woo_Locations_Postcode_Repository();
			$postcode_rows = $postcode_repo->get_by_postcode( $postcode );
			if ( ! empty( $postcode_rows ) ) {
				$first_match     = $postcode_rows[0];
				$postcode_state  = ! empty( $first_match['state_id'] ) ? absint( $first_match['state_id'] ) : 0;
				$postcode_muni  = ! empty( $first_match['municipality_id'] ) ? absint( $first_match['municipality_id'] ) : 0;
				$state_match    = $postcode_state > 0 && $postcode_state === $state_id;
				$muni_match     = $municipality_id > 0 && $postcode_muni > 0 && $postcode_muni === $municipality_id;
				if ( ! ( $state_match && $muni_match ) && ! ( $state_match && 0 === $municipality_id && $postcode_muni > 0 ) ) {
					$postcode = '';
				}
			} else {
				$postcode = '';
			}
		}

		$state_repo = new ADMBike_Woo_Locations_Shipping_Rule_Repository();
		return $state_repo->get_applicable_rules( $state_id, $municipality_id, $postcode );
	}

	/**
	 * Detect whether pickup is selected.
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 * @return bool
	 */
	protected function is_pickup_selected( array $data = array() ) {
		if ( ! empty( $data['admbike_pickup_toggle'] ) ) {
			return true;
		}

		if ( ! empty( $_POST['admbike_pickup_toggle'] ) ) {
			return true;
		}

		$chosen_methods = array();
		if ( isset( WC()->session ) && WC()->session ) {
			$chosen_methods = (array) WC()->session->get( 'chosen_shipping_methods', array() );
		}

		foreach ( $chosen_methods as $method_id ) {
			if ( is_string( $method_id ) && false !== strpos( $method_id, 'local_pickup' ) ) {
				return true;
			}
		}

		if ( ! empty( $_POST['shipping_method'] ) && is_array( $_POST['shipping_method'] ) ) {
			foreach ( $_POST['shipping_method'] as $method_id ) {
				if ( is_string( $method_id ) && false !== strpos( $method_id, 'local_pickup' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the global no coverage message.
	 *
	 * @return string
	 */
	protected function get_no_coverage_message() {
		return admbike_woo_locations()->get_no_coverage_message();
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
					'noCoverage'        => admbike_woo_locations()->get_no_coverage_message(),
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

		$states = admbike_woo_locations()->get_frontend_states();
		$munis  = admbike_woo_locations()->get_frontend_municipalities();
		$pcs    = admbike_woo_locations()->get_frontend_postcodes();

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
