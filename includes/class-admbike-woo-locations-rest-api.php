<?php
/**
 * REST API controller for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_REST_API {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'admbike-woo-locations/v1';

	/**
	 * Repositories.
	 *
	 * @var array<string, object>
	 */
	protected $repositories = array();

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/states',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_states' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'is_active' => array(
							'description' => 'Filter by active status.',
							'type'        => 'boolean',
							'default'     => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/municipalities',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_municipalities' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'state_id'   => array(
							'description' => 'Filter by state ID.',
							'type'        => 'integer',
							'required'    => false,
						),
						'is_active'  => array(
							'description' => 'Filter by active status.',
							'type'        => 'boolean',
							'default'     => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/postcodes',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_postcodes' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'municipality_id' => array(
							'description' => 'Filter by municipality ID.',
							'type'        => 'integer',
							'required'    => false,
						),
						'state_id'        => array(
							'description' => 'Filter by state ID.',
							'type'        => 'integer',
							'required'    => false,
						),
						'is_active'       => array(
							'description' => 'Filter by active status.',
							'type'        => 'boolean',
							'default'     => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/coverage',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_coverage' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'postcode' => array(
							'description' => 'Postcode to check coverage for.',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout-location',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'set_checkout_location' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'state_id' => array(
							'description' => 'State ID selected in checkout.',
							'type'        => 'integer',
							'required'    => false,
						),
						'municipality_id' => array(
							'description' => 'Municipality ID selected in checkout.',
							'type'        => 'integer',
							'required'    => false,
						),
						'postcode' => array(
							'description' => 'Postcode selected in checkout.',
							'type'        => 'string',
							'required'    => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Save the current checkout location in the WooCommerce session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function set_checkout_location( $request ) {
		$state_id        = isset( $request['state_id'] ) ? absint( $request['state_id'] ) : 0;
		$municipality_id = isset( $request['municipality_id'] ) ? absint( $request['municipality_id'] ) : 0;
		$postcode        = isset( $request['postcode'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $request['postcode'] ) : '';

		$location = array(
			'state_id'        => $state_id,
			'municipality_id' => $municipality_id,
			'postcode'        => $postcode,
		);

		if ( isset( WC()->session ) && WC()->session ) {
			WC()->session->set( ADMBike_Woo_Locations_Checkout::SESSION_KEY, $location );
		}

		$response = array(
			'success'  => true,
			'location'  => $location,
			'refreshed' => false,
		);

		if ( '' !== $postcode ) {
			$coverage = $this->get_coverage( $request );
			if ( $coverage instanceof WP_REST_Response ) {
				$coverage_data = $coverage->get_data();
				if ( is_array( $coverage_data ) ) {
					$response = array_merge( $response, $coverage_data );
				}
			}
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * GET /states
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_states( $request ) {
		$is_active = isset( $request['is_active'] ) ? (bool) $request['is_active'] : true;

		$states = $is_active
			? $this->states_repo()->get_active_states()
			: $this->states_repo()->get_items( array(), 'name ASC' );

		$data = array_map(
			function ( $state ) {
				return array(
					'id'   => (int) $state['id'],
					'code' => $state['code'],
					'name' => $state['name'],
				);
			},
			$states
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /municipalities
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_municipalities( $request ) {
		$state_id  = isset( $request['state_id'] ) ? absint( $request['state_id'] ) : 0;
		$is_active = isset( $request['is_active'] ) ? (bool) $request['is_active'] : true;

		if ( $state_id > 0 ) {
			$municipalities = $this->municipalities_repo()->get_by_state( $state_id, $is_active );
		} else {
			$where = array();
			if ( $is_active ) {
				$where['is_active'] = 1;
			}
			$municipalities = $this->municipalities_repo()->get_items( $where, 'name ASC' );
		}

		$data = array_map(
			function ( $muni ) {
				return array(
					'id'       => (int) $muni['id'],
					'state_id' => (int) $muni['state_id'],
					'name'     => $muni['name'],
				);
			},
			$municipalities
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /postcodes
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_postcodes( $request ) {
		$municipality_id = isset( $request['municipality_id'] ) ? absint( $request['municipality_id'] ) : 0;
		$state_id        = isset( $request['state_id'] ) ? absint( $request['state_id'] ) : 0;
		$is_active       = isset( $request['is_active'] ) ? (bool) $request['is_active'] : true;

		if ( $municipality_id > 0 ) {
			$postcodes = $this->postcodes_repo()->get_by_municipality( $municipality_id, $is_active );
		} elseif ( $state_id > 0 ) {
			$where = array( 'state_id' => $state_id );
			if ( $is_active ) {
				$where['is_active'] = 1;
			}
			$postcodes = $this->postcodes_repo()->get_items( $where, 'postcode ASC' );
		} else {
			$where = array();
			if ( $is_active ) {
				$where['is_active'] = 1;
			}
			$postcodes = $this->postcodes_repo()->get_items( $where, 'postcode ASC' );
		}

		$data = array_map(
			function ( $pc ) {
				return array(
					'id'             => (int) $pc['id'],
					'municipality_id' => (int) $pc['municipality_id'],
					'postcode'       => $pc['postcode'],
				);
			},
			$postcodes
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /coverage
	 * Returns the applicable shipping rule for a given postcode.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_coverage( $request ) {
		$postcode = isset( $request['postcode'] )
			? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $request['postcode'] )
			: '';

		if ( empty( $postcode ) ) {
			return new WP_REST_Response(
				array(
					'error'   => 'postcode_required',
					'message' => __( 'Postcode is required.', 'admbike-woo-locations' ),
				),
				400
			);
		}

		$postcode_rows = $this->postcodes_repo()->get_by_postcode( $postcode );

		if ( empty( $postcode_rows ) ) {
			return new WP_REST_Response(
				array(
					'available' => false,
					'rule_type' => 'unavailable',
					'message'   => __( 'No coverage for this postcode.', 'admbike-woo-locations' ),
				),
				200
			);
		}

		$first_match = $postcode_rows[0];
		$state_id        = (int) $first_match['state_id'];
		$municipality_id = (int) $first_match['municipality_id'];

		$rules = $this->shipping_rules_repo()->get_applicable_rules( $state_id, $municipality_id, $postcode );

		if ( empty( $rules ) ) {
			return new WP_REST_Response(
				array(
					'available'         => false,
					'rule_type'         => 'unavailable',
					'message'           => __( 'No shipping rule found for this location.', 'admbike-woo-locations' ),
					'postcode'          => $postcode,
					'state_id'          => $state_id,
					'municipality_id'   => $municipality_id,
				),
				200
			);
		}

		$applied_rule = $rules[0];

		if ( 'unavailable' === $applied_rule['rule_type'] ) {
			return new WP_REST_Response(
				array(
					'available'    => false,
					'rule_type'    => 'unavailable',
					'message'      => __( 'Sorry, we do not deliver to this location.', 'admbike-woo-locations' ),
					'postcode'     => $postcode,
					'state_id'     => $state_id,
					'municipality_id' => $municipality_id,
					'priority'     => (int) $applied_rule['priority'],
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'available'       => true,
				'rule_type'       => $applied_rule['rule_type'],
				'shipping_cost'   => (float) $applied_rule['shipping_cost'],
				'currency_code'   => $applied_rule['currency_code'],
				'message'         => 'free' === $applied_rule['rule_type']
					? __( 'Free shipping available!', 'admbike-woo-locations' )
					: sprintf(
						/* translators: %s: formatted shipping cost */
						__( 'Shipping cost: %s', 'admbike-woo-locations' ),
						number_format( (float) $applied_rule['shipping_cost'], 2 ) . ' ' . $applied_rule['currency_code']
					),
				'postcode'        => $postcode,
				'state_id'        => $state_id,
				'municipality_id' => $municipality_id,
				'priority'        => (int) $applied_rule['priority'],
			),
			200
		);
	}
}
