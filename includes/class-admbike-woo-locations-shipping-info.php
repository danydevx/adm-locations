<?php
/**
 * Shipping information banner for ADM Bike Woo Locations.
 *
 * Displays coverage information on the frontend (cart and checkout pages).
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Shipping_Info {

	/**
	 * Free shipping locations.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static $free_locations = array(
		array(
			'name'      => 'Guadalajara',
			'postcodes' => '44100–45440',
		),
		array(
			'name'      => 'Zapopan',
			'postcodes' => '45010–45240',
		),
		array(
			'name'      => 'Tesistán, Zapopan',
			'postcodes' => '45200',
		),
		array(
			'name'      => 'Tlajomulco de Zúñiga',
			'postcodes' => '45640–45679',
		),
		array(
			'name'      => 'San Pedro Tlaquepaque',
			'postcodes' => '45500–45619',
		),
	);

	/**
	 * Paid shipping states (full state coverage).
	 *
	 * @var array<int, array<string, string>>
	 */
	private static $paid_states = array(
		array( 'name' => 'Aguascalientes',    'cp_range' => '20000–20999' ),
		array( 'name' => 'Colima',            'cp_range' => '28000–28999' ),
		array( 'name' => 'Zacatecas',        'cp_range' => '98000–99999' ),
		array( 'name' => 'Michoacán',         'cp_range' => '58000–61999' ),
		array( 'name' => 'Guanajuato',        'cp_range' => '36000–38999' ),
		array( 'name' => 'Querétaro',         'cp_range' => '76000–76999' ),
		array( 'name' => 'San Luis Potosí',    'cp_range' => '78000–79999' ),
		array( 'name' => 'Nayarit',           'cp_range' => '63000–63999' ),
		array( 'name' => 'Nuevo León',         'cp_range' => '64000–67999' ),
	);

	/**
	 * Jalisco fallback (paid outside free municipalities).
	 *
	 * @var array<string, string>
	 */
	private static $jalisco_fallback = array(
		'description' => 'Jalisco (fuera de Guadalajara, Zapopan, Tesistán, Tlajomulco y Tlaquepaque)',
		'cost'        => '$3,000 MXN',
		'cp_range'    => '44100–49999',
	);

	/**
	 * Mazatlán coverage.
	 *
	 * @var array<string, string>
	 */
	private static $mazatlan = array(
		'description' => 'Mazatlán, Sinaloa (cobertura dentro de la ciudad)',
		'cp_range'    => '82000–82139',
		'note'        => 'Aplican costos de envío',
	);

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_before_cart', array( $this, 'render_banner' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_banner' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue banner stylesheet.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'admbike-woo-locations-shipping-info',
			ADMBIKE_WOO_LOCATIONS_URL . 'assets/css/shipping-info.css',
			array(),
			ADMBIKE_WOO_LOCATIONS_VERSION
		);
	}

	/**
	 * Render the shipping info banner.
	 *
	 * @return void
	 */
	public function render_banner() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		echo $this->get_banner_html();
	}

	/**
	 * Get the complete banner HTML.
	 *
	 * @return string
	 */
	public function get_banner_html() {
		$free_items  = $this->render_free_list();
		$paid_items  = $this->render_paid_list();
		$jalisco     = $this->render_jalisco_fallback();
		$mazatlan    = $this->render_mazatlan();

		return sprintf(
			'<div class="admbike-shipping-info" id="admbike-shipping-info">
				<div class="admbike-shipping-info__header">
					<span class="dashicons dashicons-truck" aria-hidden="true"></span>
					<strong>%1$s</strong>
				</div>
				<div class="admbike-shipping-info__grid">
					<div class="admbike-shipping-info__col">
						<div class="admbike-shipping-info__section admbike-shipping-info__section--free">
							<div class="admbike-shipping-info__section-title">%2$s</div>
							<ul class="admbike-shipping-info__list">%3$s</ul>
						</div>
					</div>
					<div class="admbike-shipping-info__col">
						<div class="admbike-shipping-info__section admbike-shipping-info__section--paid">
							<div class="admbike-shipping-info__section-title">%4$s</div>
							<ul class="admbike-shipping-info__list">%5$s</ul>
						</div>
					</div>
				</div>
				<div class="admbike-shipping-info__footer">
					<div class="admbike-shipping-info__special admbike-shipping-info__special--jalisco">%6$s</div>
					<div class="admbike-shipping-info__special admbike-shipping-info__special--mazatlan">%7$s</div>
				</div>
			</div>',
			esc_html( 'INFORMACIÓN DE ENVÍOS ADM BIKE', 'admbike-woo-locations' ),
			esc_html( 'Envío GRATIS en:', 'admbike-woo-locations' ),
			$free_items,
			esc_html( 'Envíos con costo a todo el estado de:', 'admbike-woo-locations' ),
			$paid_items,
			$this->render_jalisco_fallback_html(),
			$this->render_mazatlan_html()
		);
	}

	/**
	 * Render the free shipping list HTML.
	 *
	 * @return string
	 */
	private function render_free_list() {
		$items = '';
		foreach ( self::$free_locations as $loc ) {
			$items .= sprintf(
				'<li class="admbike-shipping-info__item admbike-shipping-info__item--free">
					<span class="dashicons dashicons-yes-alt admbike-shipping-info__icon admbike-shipping-info__icon--free" aria-hidden="true"></span>
					<strong>%1$s</strong> <span class="admbike-shipping-info__cp">(CP %2$s)</span>
				</li>',
				esc_html( $loc['name'] ),
				esc_html( $loc['postcodes'] )
			);
		}
		return $items;
	}

	/**
	 * Render the paid states list HTML.
	 *
	 * @return string
	 */
	private function render_paid_list() {
		$items = '';
		foreach ( self::$paid_states as $state ) {
			$items .= sprintf(
				'<li class="admbike-shipping-info__item admbike-shipping-info__item--paid">
					<span class="dashicons dashicons-money-alt admbike-shipping-info__icon admbike-shipping-info__icon--paid" aria-hidden="true"></span>
					<strong>%1$s</strong> <span class="admbike-shipping-info__cp">(CP %2$s)</span>
				</li>',
				esc_html( $state['name'] ),
				esc_html( $state['cp_range'] )
			);
		}
		return $items;
	}

	/**
	 * Render the Jalisco fallback HTML.
	 *
	 * @return string
	 */
	private function render_jalisco_fallback_html() {
		$f = self::$jalisco_fallback;
		return sprintf(
			'<div class="admbike-shipping-info__special-item">
				<span class="dashicons dashicons-location admbike-shipping-info__icon admbike-shipping-info__icon--warning" aria-hidden="true"></span>
				<strong>%1$s</strong><br>
				<span>%2$s</span> — <strong>%3$s</strong> <span class="admbike-shipping-info__cp">(CP %4$s)</span>
			</div>',
			esc_html( '📍 Jalisco (fuera de zonas gratuitas):', 'admbike-woo-locations' ),
			esc_html( 'Costo de envío:', 'admbike-woo-locations' ),
			esc_html( $f['cost'] ),
			esc_html( $f['cp_range'] )
		);
	}

	/**
	 * Render the Mazatlán section HTML.
	 *
	 * @return string
	 */
	private function render_mazatlan_html() {
		$m = self::$mazatlan;
		return sprintf(
			'<div class="admbike-shipping-info__special-item">
				<span class="dashicons dashicons-location admbike-shipping-info__icon admbike-shipping-info__icon--info" aria-hidden="true"></span>
				<strong>%1$s</strong><br>
				<span>%2$s</span> <span class="admbike-shipping-info__cp">(CP %3$s)</span> — %4$s
			</div>',
			esc_html( '📍 Mazatlán, Sinaloa:', 'admbike-woo-locations' ),
			esc_html( 'Cobertura dentro de la ciudad', 'admbike-woo-locations' ),
			esc_html( $m['cp_range'] ),
			esc_html( $m['note'] )
		);
	}

	/**
	 * Alias for get_banner_html for compatibility.
	 *
	 * @return string
	 */
	public function render() {
		return $this->get_banner_html();
	}
}
