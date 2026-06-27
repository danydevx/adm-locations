<?php
/**
 * Formulario para agregar o editar estados.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item       = isset( $item ) ? $item : null;
$error_msg  = isset( $error_msg ) ? $error_msg : '';
$is_edit    = ( null !== $item && ! empty( $item ) );
$state_repo_local = new ADMBike_Woo_Locations_State_Repository();
$woocommerce_mx_states = ADMBike_Woo_Locations_Admin::get_woocommerce_mx_states();

$post = wp_unslash( $_POST );
$selected_coverage_mode = $is_edit ? (string) ( $item['postcode_coverage_mode'] ?? '' ) : ( isset( $post['postcode_coverage_mode'] ) ? sanitize_key( (string) $post['postcode_coverage_mode'] ) : '' );
$selected_coverage_mode = in_array( $selected_coverage_mode, array( 'range', 'list' ), true ) ? $selected_coverage_mode : '';
$selected_postcode_from = '';
$selected_postcode_to   = '';
$selected_postcode_list = '';

	if ( $is_edit ) {
		$selected_postcode_coverage = (string) ( $item['postcode_coverage'] ?? '' );
		if ( 'list' === $selected_coverage_mode || ( empty( $item['postcode_coverage_mode'] ) && str_contains( $selected_postcode_coverage, ',' ) ) ) {
			$selected_coverage_mode = 'list';
			$selected_postcode_list = $state_repo_local->normalize_postcode_list( $selected_postcode_coverage );
		} else {
		$selected_postcode_coverage = $state_repo_local->normalize_postcode_coverage( $selected_postcode_coverage );
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
	$selected_postcode_from = isset( $post['postcode_from'] ) ? preg_replace( '/[^0-9]/', '', (string) $post['postcode_from'] ) : '';
	$selected_postcode_to   = isset( $post['postcode_to'] ) ? preg_replace( '/[^0-9]/', '', (string) $post['postcode_to'] ) : '';
	$selected_postcode_list = isset( $post['postcode_coverage_list'] ) ? sanitize_textarea_field( (string) $post['postcode_coverage_list'] ) : '';
}
?>
<?php if ( $error_msg ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
<?php endif; ?>

<div class="notice notice-info inline admbike-help-card" style="max-width: 900px;">
	<h2><?php esc_html_e( 'Referencia: códigos de estado MX de WooCommerce', 'admbike-woo-locations' ); ?></h2>
	<p>
		<?php esc_html_e( 'Guarda el código exacto de WooCommerce en Código de estado. Ejemplo: JA para Jalisco, DF para Ciudad de México, NL para Nuevo León.', 'admbike-woo-locations' ); ?>
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
	<?php wp_nonce_field( 'orpot_woo_locations_save_state', 'orpot_woo_locations_state_nonce' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="code"><?php esc_html_e( 'Código de estado', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="code" name="code" class="regular-text" required maxlength="10"
					value="<?php echo $is_edit ? esc_attr( $item['code'] ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'p. ej. JA', 'admbike-woo-locations' ); ?>">
				<p class="description"><?php esc_html_e( 'Usa el código exacto de WooCommerce MX (por ejemplo: JA, DF, NL).', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="name"><?php esc_html_e( 'Nombre del estado', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="name" name="name" class="regular-text" required
					value="<?php echo $is_edit ? esc_attr( $item['name'] ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'p. ej. Jalisco', 'admbike-woo-locations' ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="postcode_coverage_mode"><?php esc_html_e( 'Modo de cobertura', 'admbike-woo-locations' ); ?></label>
			</th>
			<td>
				<select id="postcode_coverage_mode" name="postcode_coverage_mode">
					<option value="" <?php selected( '', $selected_coverage_mode ); ?>><?php esc_html_e( 'Sin límite / solo estado WooCommerce', 'admbike-woo-locations' ); ?></option>
					<option value="range" <?php selected( 'range', $selected_coverage_mode ); ?>><?php esc_html_e( 'Rango', 'admbike-woo-locations' ); ?></option>
					<option value="list" <?php selected( 'list', $selected_coverage_mode ); ?>><?php esc_html_e( 'Lista', 'admbike-woo-locations' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Déjalo vacío para sincronizar solo el estado de WooCommerce. Rango y Lista te permiten restringir los CP disponibles.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
		<tr id="postcode_coverage_range_row" style="<?php echo 'range' !== $selected_coverage_mode ? 'display:none;' : ''; ?>">
			<th scope="row">
				<label><?php esc_html_e( 'Cobertura por rango', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="postcode_from" name="postcode_from" inputmode="numeric" pattern="[0-9]*" maxlength="5" value="<?php echo esc_attr( $selected_postcode_from ); ?>" placeholder="44100" style="width:120px;">
				<span style="display:inline-block;margin:0 8px;">-</span>
				<input type="text" id="postcode_to" name="postcode_to" inputmode="numeric" pattern="[0-9]*" maxlength="5" value="<?php echo esc_attr( $selected_postcode_to ); ?>" placeholder="44109" style="width:120px;">
				<p class="description"><?php esc_html_e( 'Ingresa el primer y último CP del estado.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
		<tr id="postcode_coverage_list_row" style="<?php echo 'list' !== $selected_coverage_mode ? 'display:none;' : ''; ?>">
			<th scope="row">
				<label for="postcode_coverage_list"><?php esc_html_e( 'Cobertura por lista', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<textarea id="postcode_coverage_list" name="postcode_coverage_list" class="large-text code" rows="4" placeholder="44100, 44120, 44125"><?php echo esc_textarea( $selected_postcode_list ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Ingresa CP de 5 dígitos separados por comas. Se normalizarán y se eliminarán duplicados.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Activo', 'admbike-woo-locations' ); ?></th>
			<td>
				<label for="is_active">
					<input type="checkbox" id="is_active" name="is_active" value="1"
						<?php checked( $is_edit ? (int) $item['is_active'] : 1, 1 ); ?>>
					<?php esc_html_e( 'Habilitar este estado para las reglas de envío', 'admbike-woo-locations' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php echo esc_html( $is_edit ? __( 'Actualizar estado', 'admbike-woo-locations' ) : __( 'Agregar estado', 'admbike-woo-locations' ) ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Cancelar', 'admbike-woo-locations' ); ?></a>
	</p>
</form>

<script>
jQuery(function($) {
	var $form = $('form').first();
	var $coverageMode = $('#postcode_coverage_mode');
	var $rangeRow = $('#postcode_coverage_range_row');
	var $listRow = $('#postcode_coverage_list_row');
	var $postcodeFrom = $('#postcode_from');
	var $postcodeTo = $('#postcode_to');
	var $postcodeList = $('#postcode_coverage_list');

	function toggleCoverageMode(mode) {
		if ($rangeRow.length) {
			$rangeRow.toggle(mode === 'range');
		}
		if ($listRow.length) {
			$listRow.toggle(mode === 'list');
		}
	}

	function clearCoverageErrors() {
		$form.find('.admbike-field-error').removeClass('admbike-field-error');
		$form.find('.admbike-coverage-error').remove();
	}

	function showCoverageError($field, message) {
		clearCoverageErrors();
		$field.addClass('admbike-field-error');
		$field.after('<p class="description admbike-coverage-error" style="color:#d63638;">' + message + '</p>');
		$field.trigger('focus');
	}

	function validateCoverage() {
		var mode = $coverageMode.val() || '';

		clearCoverageErrors();

		if (mode === 'range') {
			if (!($postcodeFrom.val() || '').trim()) {
				showCoverageError($postcodeFrom, '<?php echo esc_js( __( 'Ingresa el primer código postal.', 'admbike-woo-locations' ) ); ?>');
				return false;
			}
			if (!($postcodeTo.val() || '').trim()) {
				showCoverageError($postcodeTo, '<?php echo esc_js( __( 'Ingresa el último código postal.', 'admbike-woo-locations' ) ); ?>');
				return false;
			}
		}

		if (mode === 'list' && !($postcodeList.val() || '').trim()) {
			showCoverageError($postcodeList, '<?php echo esc_js( __( 'Ingresa al menos un código postal.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		return true;
	}

	if ($coverageMode.length) {
		$coverageMode.on('change', function() {
			toggleCoverageMode($(this).val() || '');
			clearCoverageErrors();
		});
		toggleCoverageMode($coverageMode.val() || '');
	}

	$form.on('submit', function(e) {
		if (!validateCoverage()) {
			e.preventDefault();
		}
	});
});
</script>
