<?php
/**
 * Store API integration for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Store_API {

	public const SESSION_KEY = 'admbike_checkout_location';

	public function __construct() {
		add_action( 'woocommerce_store_api_cart_errors', array( $this, 'validate_store_api_shipping_coverage' ), 10, 2 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'save_store_api_shipping_location_from_request' ), 5, 2 );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'save_store_api_shipping_location_from_request' ), 5, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_store_api_order_location_meta' ), 10, 1 );
	}

	public function save_store_api_shipping_location_from_request( $customer, $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return;
		}

		$shipping_address = isset( $request['shipping_address'] ) && is_array( $request['shipping_address'] ) ? $request['shipping_address'] : array();
		$location         = $this->resolve_location_from_shipping_address( $shipping_address );

		ADMBike_Woo_Locations_Logger::warning(
			'Store API shipping address resolved',
			array(
				'shipping_address' => $shipping_address,
				'location'         => $location,
			)
		);

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
	}

	public function save_store_api_order_location_meta( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$this->persist_order_location_meta( $order );
	}

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

	protected function persist_order_location_meta( $order, array $location = array() ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		if ( empty( $location ) && isset( WC()->session ) && WC()->session ) {
			$location = (array) WC()->session->get( self::SESSION_KEY, array() );
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

		if ( '' !== $postcode ) {
			$pc_rows = $pc_repo->get_by_postcode( $postcode );
			if ( ! empty( $pc_rows ) ) {
				$order->update_meta_data( '_admbike_municipality_id', $pc_rows[0]['municipality_id'] );
			}
		}

		$order->save();
	}

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

		$state_code = '';
		if ( $state_id > 0 ) {
			$state = $states_repo->get_by_id( $state_id );
			if ( $state && ! empty( $state['code'] ) ) {
				$state_code = strtoupper( sanitize_text_field( (string) $state['code'] ) );
			}
		}

		$municipality_id = 0;
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

		if ( '' !== $postcode ) {
			$pc_rows = $pc_repo->get_by_postcode( $postcode );
			if ( ! empty( $pc_rows ) ) {
				$postcode_state = ! empty( $pc_rows[0]['state_id'] ) ? absint( $pc_rows[0]['state_id'] ) : 0;
				$postcode_muni  = ! empty( $pc_rows[0]['municipality_id'] ) ? absint( $pc_rows[0]['municipality_id'] ) : 0;
				$state_match    = $state_id > 0 && $postcode_state > 0 && $state_id === $postcode_state;
				$muni_match     = $municipality_id > 0 && $postcode_muni > 0 && $municipality_id === $postcode_muni;

				if ( $state_match && $muni_match ) {
					$state_id        = $postcode_state;
					$municipality_id = $postcode_muni;
				} elseif ( $state_match && 0 === $municipality_id && $postcode_muni > 0 ) {
					$state_id        = $postcode_state;
					$municipality_id = $postcode_muni;
				} else {
					$postcode = '';
				}
			} else {
				$postcode = '';
			}
		}

		$country = isset( $shipping_address['country'] ) ? strtoupper( sanitize_text_field( (string) $shipping_address['country'] ) ) : 'MX';

		return array(
			'country'         => $country,
			'state_id'        => $state_id,
			'municipality_id' => $municipality_id,
			'postcode'        => $postcode,
			'state_code'      => $state_code,
			'city'            => '' !== $municipality_name ? $municipality_name : $city_value,
		);
	}

	protected function get_checkout_location( array $data = array() ) {
		$session_location = array();
		if ( isset( WC()->session ) ) {
			$session_location = (array) WC()->session->get( self::SESSION_KEY, array() );
		}

		$state_id = 0;
		if ( ! empty( $data['state_id'] ) ) {
			$state_id = absint( $data['state_id'] );
		} elseif ( ! empty( $session_location['state_id'] ) ) {
			$state_id = absint( $session_location['state_id'] );
		}

		$municipality_id = 0;
		if ( ! empty( $data['municipality_id'] ) ) {
			$municipality_id = absint( $data['municipality_id'] );
		} elseif ( ! empty( $session_location['municipality_id'] ) ) {
			$municipality_id = absint( $session_location['municipality_id'] );
		}

		$postcode = '';
		if ( ! empty( $data['postcode'] ) ) {
			$postcode = sanitize_text_field( (string) $data['postcode'] );
		} elseif ( ! empty( $session_location['postcode'] ) ) {
			$postcode = sanitize_text_field( (string) $session_location['postcode'] );
		}

		if ( '' !== $postcode && $state_id > 0 ) {
			$postcode_repo  = new ADMBike_Woo_Locations_Postcode_Repository();
			$postcode_rows = $postcode_repo->get_by_postcode( $postcode );
			if ( ! empty( $postcode_rows ) ) {
				$first_match     = $postcode_rows[0];
				$postcode_state = ! empty( $first_match['state_id'] ) ? absint( $first_match['state_id'] ) : 0;
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

		$city = '';
		if ( ! empty( $data['city'] ) ) {
			$city = sanitize_text_field( (string) $data['city'] );
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

		$country = '';
		if ( ! empty( $data['country'] ) ) {
			$country = strtoupper( sanitize_text_field( (string) $data['country'] ) );
		} elseif ( ! empty( $session_location['country'] ) ) {
			$country = strtoupper( sanitize_text_field( (string) $session_location['country'] ) );
		}

		return array(
			'country'         => '' !== $country ? $country : 'MX',
			'state_id'        => $state_id,
			'municipality_id' => $municipality_id,
			'postcode'        => $postcode,
			'city'            => $city,
		);
	}

	protected function get_coverage_for_location( array $location ) {
		$state_id        = isset( $location['state_id'] ) ? absint( $location['state_id'] ) : 0;
		$municipality_id = isset( $location['municipality_id'] ) ? absint( $location['municipality_id'] ) : 0;
		$postcode        = isset( $location['postcode'] ) ? (string) $location['postcode'] : '';
		$city            = isset( $location['city'] ) ? sanitize_text_field( (string) $location['city'] ) : '';
		$country         = isset( $location['country'] ) ? strtoupper( sanitize_text_field( (string) $location['country'] ) ) : 'MX';

		if ( '' !== $country && 'MX' !== $country ) {
			return array();
		}

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

		$rules_repo = new ADMBike_Woo_Locations_Shipping_Rule_Repository();
		return $rules_repo->get_applicable_rules( $state_id, $municipality_id, $postcode );
	}

	protected function is_pickup_selected() {
		$chosen_methods = array();
		if ( isset( WC()->session ) && WC()->session ) {
			$chosen_methods = (array) WC()->session->get( 'chosen_shipping_methods', array() );
		}

		foreach ( $chosen_methods as $method_id ) {
			if ( is_string( $method_id ) && false !== strpos( $method_id, 'local_pickup' ) ) {
				return true;
			}
		}

		return false;
	}

	protected function get_no_coverage_message() {
		return admbike_woo_locations()->get_no_coverage_message();
	}
}
