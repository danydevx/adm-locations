<?php
/**
 * Municipalities add/edit form partial.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item      = isset( $item ) ? $item : null;
$states    = isset( $states ) ? $states : array();
$error_msg = isset( $error_msg ) ? $error_msg : '';
$is_edit   = ( null !== $item && ! empty( $item ) );
?>
<?php if ( $error_msg ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
<?php endif; ?>

<form method="post" action="" style="max-width:600px;">
	<input type="hidden" name="_action" value="<?php echo esc_attr( $is_edit ? 'edit' : 'add' ); ?>">
	<?php wp_nonce_field( 'admbike_save_municipality', 'admbike_muni_nonce' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="state_id"><?php esc_html_e( 'State', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<select id="state_id" name="state_id" required>
					<option value=""><?php esc_html_e( 'Select a state…', 'admbike-woo-locations' ); ?></option>
					<?php foreach ( $states as $state ) : ?>
						<option value="<?php echo esc_attr( $state['id'] ); ?>" <?php selected( $is_edit ? (int) $item['state_id'] : 0, $state['id'] ); ?>>
							<?php echo esc_html( $state['name'] . ' (' . $state['code'] . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="name"><?php esc_html_e( 'Municipality Name', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="name" name="name" class="regular-text" required
					value="<?php echo $is_edit ? esc_attr( $item['name'] ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'e.g. Guadalajara', 'admbike-woo-locations' ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Active', 'admbike-woo-locations' ); ?></th>
			<td>
				<label for="is_active">
					<input type="checkbox" id="is_active" name="is_active" value="1"
						<?php checked( $is_edit ? (int) $item['is_active'] : 1, 1 ); ?>>
					<?php esc_html_e( 'Enable this municipality for shipping rules', 'admbike-woo-locations' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php echo esc_html( $is_edit ? __( 'Update Municipality', 'admbike-woo-locations' ) : __( 'Add Municipality', 'admbike-woo-locations' ) ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::MUNICIPALITIES_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'admbike-woo-locations' ); ?></a>
	</p>
</form>
