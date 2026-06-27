<?php
/**
 * WooCommerce Blocks integration for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Blocks_Checkout {

	public const SCRIPT_HANDLE = 'admbike-woo-locations-blocks-checkout';

	public function __construct() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'add_block_data' ) );
		add_action( 'woocommerce_blocks_cart_enqueue_data', array( $this, 'add_block_data' ) );
	}

	public function register_assets() {
		wp_register_script(
			self::SCRIPT_HANDLE,
			ADMBIKE_WOO_LOCATIONS_URL . 'assets/js/blocks-checkout-v2.js',
			array( 'wp-components', 'wp-data', 'wp-element', 'wp-i18n', 'wc-blocks-data-store' ),
			ADMBIKE_WOO_LOCATIONS_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( self::SCRIPT_HANDLE, 'admbike-woo-locations', ADMBIKE_WOO_LOCATIONS_PATH . 'languages' );
		}
	}

	public function enqueue_assets() {
		if ( ! $this->is_blocks_page() ) {
			return;
		}

		$data = $this->build_block_data();
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.admbikeWooLocationsBlocks = ' . wp_json_encode( $data ) . ';',
			'before'
		);

		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	public function add_block_data() {
		$registry = $this->get_asset_registry();
		if ( ! $registry ) {
			return;
		}

		$value = $this->build_block_data();
		$key   = 'admbikeBlocks';

		if ( ! $registry->exists( $key ) ) {
			$registry->add( $key, $value );
		}
	}

	protected function build_block_data() {
		$states = array_map(
			static function ( $state ) {
				return array(
					'id'   => (int) $state['id'],
					'code' => (string) $state['code'],
					'name' => (string) $state['name'],
				);
			},
			admbike_woo_locations()->get_frontend_states()
		);

		$municipalities = array_map(
			static function ( $municipality ) {
				return array(
					'id'              => (int) $municipality['id'],
					'state_id'        => (int) $municipality['state_id'],
					'name'            => (string) $municipality['name'],
					'normalized_name'  => isset( $municipality['normalized_name'] ) ? (string) $municipality['normalized_name'] : '',
				);
			},
			admbike_woo_locations()->get_frontend_municipalities()
		);

		return array(
			'restUrl'                   => rest_url( 'admbike-woo-locations/v1/' ),
			'frontendNoCoverageMessage' => admbike_woo_locations()->get_no_coverage_message(),
			'states'                    => $states,
			'municipalities'            => $municipalities,
			'i18n'                      => array(
				'loading'            => __( 'Cargando…', 'admbike-woo-locations' ),
				'selectMunicipality'  => __( 'Selecciona un municipio…', 'admbike-woo-locations' ),
			),
		);
	}

	protected function get_asset_registry() {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Package' ) || ! class_exists( '\Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry' ) ) {
			return null;
		}

		try {
			$registry = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class );
			return is_object( $registry ) ? $registry : null;
		} catch ( Throwable $e ) {
			return null;
		}
	}

	protected function is_blocks_page() {
		if ( ! function_exists( 'is_checkout' ) || ! function_exists( 'is_cart' ) ) {
			return false;
		}

		if ( ! is_checkout() && ! is_cart() ) {
			return false;
		}

		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return has_block( 'woocommerce/checkout', $post ) || has_block( 'woocommerce/cart', $post );
	}
}
