<?php
/**
 * WooCommerce Blocks integration for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Blocks {

	/**
	 * Initialize blocks integration.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_checkout_fields_hidden' ), 1 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_blocks_checkout_data' ), 10, 3 );
		add_filter( 'woocommerce_get_script_data', array( $this, 'add_checkout_data' ), 10, 2 );
	}

	/**
	 * Check if WooCommerce Blocks is active.
	 *
	 * @return bool
	 */
	public static function is_blocks_checkout() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
			return true;
		}
		if ( class_exists( 'WooCommerce\Blocks\Package' ) ) {
			return true;
		}
		if ( function_exists( 'woocommerce_blocks_init' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Enqueue checkout assets.
	 *
	 * @return void
	 */
	public function enqueue_checkout_assets() {
		if ( ! is_checkout() ) {
			return;
		}

		$is_blocks_checkout = false;
		if ( function_exists( 'has_block' ) ) {
			$post = get_post();
			$is_blocks_checkout = $post instanceof WP_Post && has_block( 'woocommerce/checkout', $post );
		}

		wp_enqueue_style(
			'admbike-woo-locations-blocks',
			ADMBIKE_WOO_LOCATIONS_URL . 'assets/css/checkout.css',
			array(),
			ADMBIKE_WOO_LOCATIONS_VERSION
		);

		wp_enqueue_script(
			'admbike-woo-locations-blocks',
			ADMBIKE_WOO_LOCATIONS_URL . 'assets/js/blocks-checkout.js',
			array( 'jquery', 'wc-settings', 'wc-blocks-data-store' ),
			ADMBIKE_WOO_LOCATIONS_VERSION,
			true
		);

		if ( $is_blocks_checkout ) {
			wp_add_inline_script(
				'admbike-woo-locations-blocks',
				'window.admbikeBlocksData = window.admbikeBlocksData || {}; window.admbikeBlocksData.isBlocksCheckout = true;',
				'before'
			);
		}
	}

	/**
	 * Add location data to WooCommerce checkout script data.
	 *
	 * @param array $data Script data.
	 * @param string $handle Script handle.
	 * @return array
	 */
	public function add_checkout_data( $data, $handle ) {
		if ( 'wc-checkout-block' !== $handle && 'wc-blocks-checkout-package' !== $handle ) {
			return $data;
		}

		$is_blocks_checkout = false;
		if ( function_exists( 'has_block' ) ) {
			$post = get_post();
			$is_blocks_checkout = $post instanceof WP_Post && has_block( 'woocommerce/checkout', $post );
		}

		$states = admbike_woo_locations()->get_frontend_states();
		$munis  = admbike_woo_locations()->get_frontend_municipalities();
		$pcs    = admbike_woo_locations()->get_frontend_postcodes();

		$data['admbikeLocations'] = array(
			'isBlocksCheckout' => $is_blocks_checkout,
			'states'         => array_map(
				function ( $s ) {
					return array( 'id' => (int) $s['id'], 'name' => $s['name'], 'code' => $s['code'] );
				},
				$states
			),
			'municipalities' => array_map(
				function ( $m ) {
					return array( 'id' => (int) $m['id'], 'state_id' => (int) $m['state_id'], 'name' => $m['name'] );
				},
				$munis
			),
			'postcodes'      => array_map(
				function ( $p ) {
					return array( 'id' => (int) $p['id'], 'municipality_id' => (int) $p['municipality_id'], 'postcode' => $p['postcode'] );
				},
				$pcs
			),
			'i18n'           => array(
				'selectState'        => __( 'Selecciona un estado…', 'admbike-woo-locations' ),
				'selectMunicipality' => __( 'Selecciona una ciudad…', 'admbike-woo-locations' ),
				'selectPostcode'     => __( 'Selecciona un código postal…', 'admbike-woo-locations' ),
				'noCoverage'         => admbike_woo_locations()->get_no_coverage_message(),
				'loading'            => __( 'Cargando…', 'admbike-woo-locations' ),
			),
			'restUrl'        => rest_url( 'admbike-woo-locations/v1/' ),
		);

		return $data;
	}

	/**
	 * Render checkout fields hidden (for JS to pick up and inject).
	 *
	 * @return void
	 */
	public function render_checkout_fields_hidden() {
		if ( ! is_checkout() ) {
			return;
		}

		$is_blocks_checkout = false;
		if ( function_exists( 'has_block' ) ) {
			$post = get_post();
			$is_blocks_checkout = $post instanceof WP_Post && has_block( 'woocommerce/checkout', $post );
		}

		$states = admbike_woo_locations()->get_frontend_states();
		$munis  = admbike_woo_locations()->get_frontend_municipalities();
		$pcs    = admbike_woo_locations()->get_frontend_postcodes();

		$data = array(
			'isBlocksCheckout' => $is_blocks_checkout,
			'states'        => array_map(
				function ( $s ) {
					return array( 'id' => (int) $s['id'], 'name' => $s['name'], 'code' => $s['code'] );
				},
				$states
			),
			'municipalities' => array_map(
				function ( $m ) {
					return array( 'id' => (int) $m['id'], 'state_id' => (int) $m['state_id'], 'name' => $m['name'] );
				},
				$munis
			),
			'postcodes'     => array_map(
				function ( $p ) {
					return array( 'id' => (int) $p['id'], 'municipality_id' => (int) $p['municipality_id'], 'postcode' => $p['postcode'] );
				},
				$pcs
			),
			'restUrl'       => rest_url( 'admbike-woo-locations/v1/' ),
			'i18n'          => array(
				'selectState'        => __( 'Selecciona un estado…', 'admbike-woo-locations' ),
				'selectMunicipality' => __( 'Selecciona una ciudad…', 'admbike-woo-locations' ),
				'selectPostcode'     => __( 'Selecciona un código postal…', 'admbike-woo-locations' ),
				'noCoverage'         => admbike_woo_locations()->get_no_coverage_message(),
				'loading'            => __( 'Cargando…', 'admbike-woo-locations' ),
			),
		);
		?>
		<script id="admbike-location-data" type="application/json"><?php echo wp_json_encode( $data ); ?></script>
		<div id="admbike-blocks-fields-container" style="display:none;" data-placement="woocommerce-checkout">
			<div class="admbike-blocks-checkout-fields">
				<div class="admbike-blocks-field-group">
					<label for="admbike_blocks_state" class="admbike-blocks-label">
						<?php esc_html_e( 'Estado', 'admbike-woo-locations' ); ?>
					</label>
					<select id="admbike_blocks_state" class="admbike-blocks-select" name="admbike_blocks_state">
						<option value=""><?php esc_html_e( 'Selecciona un estado…', 'admbike-woo-locations' ); ?></option>
					</select>
				</div>

				<div class="admbike-blocks-field-group admbike-hidden" id="admbike_blocks_municipality_group">
					<label for="admbike_blocks_municipality" class="admbike-blocks-label">
						<?php esc_html_e( 'Municipio / Ciudad', 'admbike-woo-locations' ); ?>
					</label>
					<select id="admbike_blocks_municipality" class="admbike-blocks-select" name="admbike_blocks_municipality">
						<option value=""><?php esc_html_e( 'Selecciona un municipio…', 'admbike-woo-locations' ); ?></option>
					</select>
				</div>

				<div class="admbike-blocks-field-group admbike-hidden" id="admbike_blocks_postcode_group">
					<label for="admbike_blocks_postcode" class="admbike-blocks-label">
						<?php esc_html_e( 'Código Postal', 'admbike-woo-locations' ); ?>
					</label>
					<select id="admbike_blocks_postcode" class="admbike-blocks-select" name="admbike_blocks_postcode">
						<option value=""><?php esc_html_e( 'Selecciona un código postal…', 'admbike-woo-locations' ); ?></option>
					</select>
				</div>

				<div id="admbike_blocks_coverage_result" class="admbike-hidden"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save checkout data from blocks.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $posted_data Posted checkout data.
	 * @return void
	 */
	public function save_blocks_checkout_data( $order_id, $posted_data ) {
		$post = wp_unslash( $_POST );
		$state_code = '';
		if ( ! empty( $post['billing_state'] ) ) {
			$state_code = sanitize_text_field( (string) $post['billing_state'] );
		} elseif ( ! empty( $post['shipping_state'] ) ) {
			$state_code = sanitize_text_field( (string) $post['shipping_state'] );
		}

		if ( '' === $state_code ) {
			return;
		}

		$states_repo     = new ADMBike_Woo_Locations_State_Repository();
		$state           = $states_repo->get_by_code_or_name( $state_code );
		$state_id        = $state && ! empty( $state['id'] ) ? absint( $state['id'] ) : 0;
		$municipality_id = 0;
		if ( ! empty( $post['billing_city'] ) ) {
			$municipality_id = absint( $post['billing_city'] );
		} elseif ( ! empty( $post['shipping_city'] ) ) {
			$municipality_id = absint( $post['shipping_city'] );
		}
		$postcode        = isset( $post['admbike_blocks_postcode'] ) ? sanitize_text_field( (string) $post['admbike_blocks_postcode'] ) : '';

		$muni_repo   = new ADMBike_Woo_Locations_Municipality_Repository();
		$pc_repo     = new ADMBike_Woo_Locations_Postcode_Repository();

		$state = $states_repo->get_by_id( $state_id );
		$muni  = $municipality_id ? $muni_repo->get_by_id( $municipality_id ) : null;

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( '_admbike_state_id', $state_id );
		$order->update_meta_data( '_admbike_state_name', $state ? $state['name'] : '' );
		$order->update_meta_data( '_admbike_state_code', $state ? $state['code'] : '' );
		$order->update_meta_data( '_admbike_municipality_id', $municipality_id );
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
}
