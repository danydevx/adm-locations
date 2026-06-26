<?php
/**
 * WooCommerce shipping method for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

class ADMBike_Woo_Locations_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Shipping method ID.
	 *
	 * @var string
	 */
	public $id = 'admbike_locations';

	/**
	 * Shipping method code.
	 *
	 * @var string
	 */
	public $method_code = 'admbike_locations';

	/**
	 * Shipping method title.
	 *
	 * @var string
	 */
	public $title = 'ADM Bike Cobertura';

	/**
	 * Repositories.
	 *
	 * @var array<string, object>
	 */
	protected $repositories = array();

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance ID.
	 * @return void
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'admbike_locations';
		$this->method_title        = __( 'ADM Bike Locations', 'admbike-woo-locations' );
		$this->method_description  = __( 'Calculates shipping based on state, municipality and postal code coverage rules.', 'admbike-woo-locations' );
		$this->instance_id         = absint( $instance_id );
		$this->supports            = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();
	}

	/**
	 * Initialize the shipping method.
	 *
	 * @return void
	 */
	public function init() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( 'Method Title', 'admbike-woo-locations' ),
				'type'        => 'text',
				'description' => __( 'This controls the title displayed at checkout.', 'admbike-woo-locations' ),
				'default'     => __( 'Envío Coverage', 'admbike-woo-locations' ),
				'desc_tip'   => true,
			),
			'tax_status' => array(
				'title'   => __( 'Tax Status', 'admbike-woo-locations' ),
				'type'    => 'select',
				'description' => __( 'Tax status for this shipping method.', 'admbike-woo-locations' ),
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'admbike-woo-locations' ),
					'none'    => __( 'None', 'admbike-woo-locations' ),
				),
			),
		);

		$this->title = $this->get_option( 'title', __( 'Envío Coverage', 'admbike-woo-locations' ) );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Get the states repository.
	 *
	 * @return ADMBike_Woo_Locations_State_Repository
	 */
	protected function states_repo() {
		if ( ! isset( $this->repositories['states'] ) ) {
			$this->repositories['states'] = new ADMBike_Woo_Locations_State_Repository();
		}
		return $this->repositories['states'];
	}

	/**
	 * Get the municipalities repository.
	 *
	 * @return ADMBike_Woo_Locations_Municipality_Repository
	 */
	protected function municipalities_repo() {
		if ( ! isset( $this->repositories['municipalities'] ) ) {
			$this->repositories['municipalities'] = new ADMBike_Woo_Locations_Municipality_Repository();
		}
		return $this->repositories['municipalities'];
	}

	/**
	 * Get the postcodes repository.
	 *
	 * @return ADMBike_Woo_Locations_Postcode_Repository
	 */
	protected function postcodes_repo() {
		if ( ! isset( $this->repositories['postcodes'] ) ) {
			$this->repositories['postcodes'] = new ADMBike_Woo_Locations_Postcode_Repository();
		}
		return $this->repositories['postcodes'];
	}

	/**
	 * Get the shipping rules repository.
	 *
	 * @return ADMBike_Woo_Locations_Shipping_Rule_Repository
	 */
	protected function shipping_rules_repo() {
		if ( ! isset( $this->repositories['shipping_rules'] ) ) {
			$this->repositories['shipping_rules'] = new ADMBike_Woo_Locations_Shipping_Rule_Repository();
		}
		return $this->repositories['shipping_rules'];
	}

	/**
	 * Get the postcode from the package.
	 *
	 * Priority:
	 * 1. ADM Bike checkout session (if customer completed our custom selectors)
	 * 2. Package destination postcode
	 *
	 * @param array<string, mixed> $package Shipping package.
	 * @return string
	 */
	protected function get_postcode_from_package( $package ) {
		if ( isset( WC()->session ) && WC()->session->has_session() ) {
			$location = WC()->session->get( 'admbike_checkout_location' );
			if ( ! empty( $location['postcode'] ) ) {
				return preg_replace( '/[^0-9A-Za-z-]/', '', (string) $location['postcode'] );
			}
		}

		$postcode = isset( $package['destination']['postcode'] )
			? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $package['destination']['postcode'] )
			: '';

		return $postcode;
	}

	/**
	 * Resolve shipping context from session and package destination.
	 *
	 * @param array<string, mixed> $package Shipping package.
	 * @return array<string, int|string>
	 */
	protected function resolve_shipping_context( $package ) {
		$destination = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
		$state_id = 0;
		$municipality_id = 0;

		if ( isset( WC()->session ) && WC()->session->has_session() ) {
			$location = (array) WC()->session->get( 'admbike_checkout_location', array() );
			$state_id = ! empty( $location['state_id'] ) ? absint( $location['state_id'] ) : 0;
			$municipality_id = ! empty( $location['municipality_id'] ) ? absint( $location['municipality_id'] ) : 0;
		}

		$destination_state = isset( $destination['state'] ) ? sanitize_text_field( (string) $destination['state'] ) : '';
		$destination_city  = isset( $destination['city'] ) ? sanitize_text_field( (string) $destination['city'] ) : '';

		if ( $state_id <= 0 && '' !== $destination_state ) {
			$state = $this->states_repo()->get_by_code_or_name( $destination_state );
			if ( $state && ! empty( $state['id'] ) ) {
				$state_id = absint( $state['id'] );
			}
		}

		if ( $municipality_id <= 0 && '' !== $destination_city ) {
			if ( $state_id > 0 ) {
				$municipality = $this->municipalities_repo()->get_by_state_and_name( $state_id, $destination_city );
				if ( $municipality && ! empty( $municipality['id'] ) ) {
					$municipality_id = absint( $municipality['id'] );
				}
			}

			if ( $municipality_id <= 0 ) {
				$municipality = $this->municipalities_repo()->get_by_name( $destination_city );
				if ( $municipality && ! empty( $municipality['id'] ) ) {
					$municipality_id = absint( $municipality['id'] );
					if ( $state_id <= 0 && ! empty( $municipality['state_id'] ) ) {
						$state_id = absint( $municipality['state_id'] );
					}
				}
			}
		}

		return array(
			'destination'     => $destination,
			'state_id'        => $state_id,
			'municipality_id' => $municipality_id,
			'postcode'        => $this->get_postcode_from_package( $package ),
		);
	}

	/**
	 * Calculate shipping.
	 *
	 * Priority evaluation:
	 * 1. Exact postcode match (highest specificity) — only if postcode belongs to selected state+municipality
	 * 2. Postcode range match — only if postcode belongs to selected state+municipality
	 * 3. Municipality match
	 * 4. State match
	 *
	 * @param array<string, mixed> $package Shipping package.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		ADMBike_Woo_Locations_Logger::debug(
			'Shipping calculation started for package',
			array(
				'destination' => $package['destination'] ?? array(),
			)
		);

		$resolved = $this->resolve_shipping_context( $package );
		$state_id = isset( $resolved['state_id'] ) ? absint( $resolved['state_id'] ) : 0;
		$municipality_id = isset( $resolved['municipality_id'] ) ? absint( $resolved['municipality_id'] ) : 0;
		$postcode = isset( $resolved['postcode'] ) ? (string) $resolved['postcode'] : '';

		if ( '' !== $postcode && $state_id > 0 ) {
			$postcode_rows = $this->postcodes_repo()->get_by_postcode( $postcode );
			if ( ! empty( $postcode_rows ) ) {
				$first_match    = $postcode_rows[0];
				$postcode_state = ! empty( $first_match['state_id'] ) ? absint( $first_match['state_id'] ) : 0;
				$postcode_muni  = ! empty( $first_match['municipality_id'] ) ? absint( $first_match['municipality_id'] ) : 0;
				$state_match    = $postcode_state > 0 && $postcode_state === $state_id;
				$muni_match     = $municipality_id > 0 && $postcode_muni > 0 && $postcode_muni === $municipality_id;
				if ( ! ( $state_match && $muni_match ) && ! ( $state_match && 0 === $municipality_id && $postcode_muni > 0 ) ) {
					$postcode = '';
				} else {
					if ( $state_id <= 0 ) {
						$state_id = $postcode_state;
					}
					if ( $municipality_id <= 0 && $postcode_muni > 0 ) {
						$municipality_id = $postcode_muni;
					}
				}
			} else {
				$postcode = '';
			}
		}

		if ( '' === $postcode && $municipality_id <= 0 ) {
			ADMBike_Woo_Locations_Logger::info( 'Shipping calculation: incomplete location, waiting for municipality or postcode' );
			return;
		}

		if ( $state_id <= 0 ) {
			ADMBike_Woo_Locations_Logger::info( 'Shipping calculation: no state_id available' );
			return;
		}

		ADMBike_Woo_Locations_Logger::debug(
			'Shipping calculation: resolved',
			array(
				'postcode'        => $postcode,
				'state_id'        => $state_id,
				'municipality_id' => $municipality_id,
			)
		);

		$rules = $this->shipping_rules_repo()->get_applicable_rules( $state_id, $municipality_id, $postcode );

		if ( empty( $rules ) ) {
			ADMBike_Woo_Locations_Logger::info(
				'Shipping calculation: no rules matched',
				array( 'postcode' => $postcode, 'state_id' => $state_id, 'municipality_id' => $municipality_id )
			);
			return;
		}

		$applied_rule = $rules[0];

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_UNAVAILABLE === $applied_rule['rule_type'] ) {
			ADMBike_Woo_Locations_Logger::info(
				'Shipping calculation: location is marked unavailable',
				array( 'rule_id' => $applied_rule['id'] ?? 0 )
			);
			return;
		}

		$cost = 0.0;

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID === $applied_rule['rule_type'] ) {
			$cost = (float) ( $applied_rule['shipping_cost'] ?? 0 );
		}

		$label = ! empty( $applied_rule['display_title'] ) ? sanitize_text_field( (string) $applied_rule['display_title'] ) : $this->title;
		$customer_message = ! empty( $applied_rule['customer_message'] ) ? sanitize_textarea_field( (string) $applied_rule['customer_message'] ) : '';

		ADMBike_Woo_Locations_Logger::log_shipping_calculation( $postcode, $rules, $applied_rule, $cost );

		$rate = array(
			'id'    => $this->get_rate_id(),
			'label' => $label,
			'description' => $customer_message,
			'cost'  => $cost,
			'meta_data' => array(
				'_admbike_rule_id'     => (int) $applied_rule['id'],
				'_admbike_rule_type'  => $applied_rule['rule_type'],
				'_admbike_match_type' => $applied_rule['match_type'],
				'_admbike_rule_priority' => isset( $applied_rule['priority'] ) ? (int) $applied_rule['priority'] : 100,
				'_admbike_rule_specificity' => $this->get_match_specificity( (string) $applied_rule['match_type'] ),
				'_admbike_postcode'   => $postcode,
				'_admbike_state_id'   => $state_id,
				'_admbike_municipality_id' => $municipality_id,
				'_admbike_display_title' => $label,
				'_admbike_customer_message' => $customer_message,
			),
		);

		$tax_status = $this->get_option( 'tax_status', 'taxable' );
		if ( 'none' === $tax_status ) {
			$rate['taxes'] = false;
		}

		$this->add_rate( $rate );
	}

	/**
	 * Check if this shipping method is available for a given package.
	 *
	 * @param array<string, mixed> $package Shipping package.
	 * @return bool
	 */
	public function is_available( $package ) {
		if ( ! is_checkout() && ! is_cart() ) {
			return true;
		}

		return true;
	}

	/**
	 * Convert a match type into a specificity score.
	 *
	 * Lower numbers are more specific.
	 *
	 * @param string $match_type Match type.
	 * @return int
	 */
	protected function get_match_specificity( $match_type ) {
		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE === $match_type ) {
			return 1;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE === $match_type ) {
			return 2;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_MUNICIPALITY === $match_type ) {
			return 3;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE === $match_type ) {
			return 4;
		}

		return 99;
	}
}
