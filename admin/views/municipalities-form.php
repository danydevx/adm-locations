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
$muni_repo_local = new ADMBike_Woo_Locations_Municipality_Repository();

$selected_coverage_mode = $is_edit ? (string) ( $item['postcode_coverage_mode'] ?? '' ) : ( isset( $_POST['postcode_coverage_mode'] ) ? sanitize_key( (string) $_POST['postcode_coverage_mode'] ) : 'range' );
$selected_coverage_mode = in_array( $selected_coverage_mode, array( 'range', 'list' ), true ) ? $selected_coverage_mode : 'range';
$selected_postcode_from = '';
$selected_postcode_to   = '';
$selected_postcode_list = '';

if ( $is_edit ) {
	$selected_postcode_coverage = (string) ( $item['postcode_coverage'] ?? '' );
	if ( 'list' === $selected_coverage_mode || ( empty( $item['postcode_coverage_mode'] ) && str_contains( $selected_postcode_coverage, ',' ) ) ) {
		$selected_coverage_mode = 'list';
		$selected_postcode_list = $muni_repo_local->normalize_postcode_list( $selected_postcode_coverage );
	} else {
		$selected_postcode_coverage = $muni_repo_local->normalize_postcode_coverage( $selected_postcode_coverage );
		$segments = preg_split( '/\s*,\s*/', $selected_postcode_coverage );
		$first    = is_array( $segments ) && ! empty( $segments[0] ) ? trim( (string) $segments[0] ) : '';
		if ( preg_match( '/^(\d{5})-(\d{5})$/', $first, $matches ) ) {
			$selected_postcode_from = $matches[1];
			$selected_postcode_to   = $matches[2];
		} elseif ( preg_match( '/^(\d{5})$/', $first, $matches ) ) {
			$selected_postcode_from = $matches[1];
			$selected_postcode_to   = $matches[1];
		}
	}
} else {
	$selected_postcode_from = isset( $_POST['postcode_from'] ) ? preg_replace( '/[^0-9]/', '', (string) $_POST['postcode_from'] ) : '';
	$selected_postcode_to   = isset( $_POST['postcode_to'] ) ? preg_replace( '/[^0-9]/', '', (string) $_POST['postcode_to'] ) : '';
	$selected_postcode_list = isset( $_POST['postcode_coverage_list'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['postcode_coverage_list'] ) ) : '';
}
?>
<?php if ( $error_msg ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
<?php endif; ?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $is_edit ? __( 'Edit Municipality', 'admbike-woo-locations' ) : __( 'Add Municipality', 'admbike-woo-locations' ) ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::MUNICIPALITIES_SLUG ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'admbike-woo-locations' ); ?></a>
	<hr class="wp-header-end">
</div>

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
			<th scope="row">
				<label for="postcode_coverage_mode"><?php esc_html_e( 'Coverage Mode', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<select id="postcode_coverage_mode" name="postcode_coverage_mode" required>
					<option value="range" <?php selected( 'range', $selected_coverage_mode ); ?>><?php esc_html_e( 'Range', 'admbike-woo-locations' ); ?></option>
					<option value="list" <?php selected( 'list', $selected_coverage_mode ); ?>><?php esc_html_e( 'List', 'admbike-woo-locations' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Range is default. List lets you enter specific 5-digit CPs separated by commas.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>

		<tr id="postcode_coverage_range_row" style="<?php echo 'range' !== $selected_coverage_mode ? 'display:none;' : ''; ?>">
			<th scope="row">
				<label><?php esc_html_e( 'Range Coverage', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="postcode_from" name="postcode_from" inputmode="numeric" pattern="[0-9]*" maxlength="5" value="<?php echo esc_attr( $selected_postcode_from ); ?>" placeholder="44100" style="width:120px;">
				<span style="display:inline-block;margin:0 8px;">-</span>
				<input type="text" id="postcode_to" name="postcode_to" inputmode="numeric" pattern="[0-9]*" maxlength="5" value="<?php echo esc_attr( $selected_postcode_to ); ?>" placeholder="44109" style="width:120px;">
				<p class="description"><?php esc_html_e( 'Enter the first and last CP in the municipality range.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>

		<tr id="postcode_coverage_list_row" style="<?php echo 'list' !== $selected_coverage_mode ? 'display:none;' : ''; ?>">
			<th scope="row">
				<label for="postcode_coverage_list"><?php esc_html_e( 'List Coverage', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<textarea id="postcode_coverage_list" name="postcode_coverage_list" class="large-text code" rows="4" placeholder="44100, 44120, 44125"><?php echo esc_textarea( $selected_postcode_list ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Enter 5-digit CPs separated by commas. They will be normalized and de-duplicated.', 'admbike-woo-locations' ); ?></p>
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

</div>

<script>
(function() {
	var coverageMode = document.getElementById('postcode_coverage_mode');
	var rangeRow = document.getElementById('postcode_coverage_range_row');
	var listRow = document.getElementById('postcode_coverage_list_row');

	function toggleCoverageMode(mode) {
		if (rangeRow) {
			rangeRow.style.display = ('range' === mode) ? '' : 'none';
		}
		if (listRow) {
			listRow.style.display = ('list' === mode) ? '' : 'none';
		}
	}

	if (coverageMode) {
		coverageMode.addEventListener('change', function() {
			toggleCoverageMode(this.value);
		});
		toggleCoverageMode(coverageMode.value);
	}
})();
</script>
