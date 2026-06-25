<?php
/**
 * States add/edit form partial.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item       = isset( $item ) ? $item : null;
$error_msg  = isset( $error_msg ) ? $error_msg : '';
$is_edit    = ( null !== $item && ! empty( $item ) );
$woocommerce_mx_states = ADMBike_Woo_Locations_Admin::get_woocommerce_mx_states();
?>
<?php if ( $error_msg ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
<?php endif; ?>

<div class="notice notice-info inline admbike-help-card" style="max-width: 900px;">
	<h2><?php esc_html_e( 'Reference: WooCommerce MX State Codes', 'admbike-woo-locations' ); ?></h2>
	<p>
		<?php esc_html_e( 'Save the exact WooCommerce code in State Code. Example: JA for Jalisco, DF for Ciudad de Mexico, NL for Nuevo Leon.', 'admbike-woo-locations' ); ?>
	</p>
	<div class="admbike-help-grid">
		<?php foreach ( $woocommerce_mx_states as $code => $label ) : ?>
			<div class="admbike-help-grid__item">
				<code><?php echo esc_html( $code ); ?></code>
				<span><?php echo esc_html( $label ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<form method="post" action="" style="max-width:600px;">
	<input type="hidden" name="_action" value="<?php echo esc_attr( $is_edit ? 'edit' : 'add' ); ?>">
	<?php wp_nonce_field( 'admbike_save_state', 'admbike_state_nonce' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="code"><?php esc_html_e( 'State Code', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="code" name="code" class="regular-text" required maxlength="10"
					value="<?php echo $is_edit ? esc_attr( $item['code'] ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'e.g. JA', 'admbike-woo-locations' ); ?>">
				<p class="description"><?php esc_html_e( 'Use the exact WooCommerce MX code (for example: JA, DF, NL).', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="name"><?php esc_html_e( 'State Name', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="name" name="name" class="regular-text" required
					value="<?php echo $is_edit ? esc_attr( $item['name'] ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'e.g. Jalisco', 'admbike-woo-locations' ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Active', 'admbike-woo-locations' ); ?></th>
			<td>
				<label for="is_active">
					<input type="checkbox" id="is_active" name="is_active" value="1"
						<?php checked( $is_edit ? (int) $item['is_active'] : 1, 1 ); ?>>
					<?php esc_html_e( 'Enable this state for shipping rules', 'admbike-woo-locations' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php echo esc_html( $is_edit ? __( 'Update State', 'admbike-woo-locations' ) : __( 'Add State', 'admbike-woo-locations' ) ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'admbike-woo-locations' ); ?></a>
	</p>
</form>
