<?php
/**
 * Formulario para agregar o editar códigos postales.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item         = isset( $item ) ? $item : null;
$states       = isset( $states ) ? $states : array();
$municipalities = isset( $municipalities ) ? $municipalities : array();
$error_msg    = isset( $error_msg ) ? $error_msg : '';
$is_edit      = ( null !== $item && ! empty( $item ) );

$selected_state_id       = $is_edit ? (int) $item['state_id'] : 0;
$selected_municipality_id = $is_edit ? (int) $item['municipality_id'] : 0;
?>
<?php if ( $error_msg ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
<?php endif; ?>

<form method="post" action="" id="admbike-postcode-form" style="max-width:600px;">
	<input type="hidden" name="_action" value="<?php echo esc_attr( $is_edit ? 'edit' : 'add' ); ?>">
	<?php wp_nonce_field( 'orpot_woo_locations_save_postcode', 'orpot_woo_locations_pc_nonce' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="state_id"><?php esc_html_e( 'Estado', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<select id="state_id" name="state_id" required>
					<option value=""><?php esc_html_e( 'Selecciona un estado…', 'admbike-woo-locations' ); ?></option>
					<?php foreach ( $states as $state ) : ?>
						<option value="<?php echo esc_attr( $state['id'] ); ?>" <?php selected( $selected_state_id, $state['id'] ); ?>>
							<?php echo esc_html( $state['name'] . ' (' . $state['code'] . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="municipality_id"><?php esc_html_e( 'Municipio', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<select id="municipality_id" name="municipality_id" required>
					<option value=""><?php esc_html_e( 'Selecciona un municipio…', 'admbike-woo-locations' ); ?></option>
					<?php if ( $is_edit ) : ?>
						<?php foreach ( $municipalities as $muni ) : ?>
							<option value="<?php echo esc_attr( $muni['id'] ); ?>" data-state="<?php echo esc_attr( $muni['state_id'] ); ?>" <?php selected( $selected_municipality_id, $muni['id'] ); ?>>
								<?php echo esc_html( $muni['name'] ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Selecciona primero un estado para cargar sus municipios.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="postcode"><?php esc_html_e( 'Código postal', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="postcode" name="postcode" class="regular-text" required maxlength="10"
					value="<?php echo $is_edit ? esc_attr( $item['postcode'] ) : ''; ?>"
					placeholder="<?php esc_attr_e( 'p. ej. 44100', 'admbike-woo-locations' ); ?>">
				<p class="description"><?php esc_html_e( 'Solo dígitos y guiones.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Activo', 'admbike-woo-locations' ); ?></th>
			<td>
				<label for="is_active">
					<input type="checkbox" id="is_active" name="is_active" value="1"
						<?php checked( $is_edit ? (int) $item['is_active'] : 1, 1 ); ?>>
					<?php esc_html_e( 'Habilitar este código postal para las reglas de envío', 'admbike-woo-locations' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php echo esc_html( $is_edit ? __( 'Actualizar código postal', 'admbike-woo-locations' ) : __( 'Agregar código postal', 'admbike-woo-locations' ) ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::POSTCODES_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Cancelar', 'admbike-woo-locations' ); ?></a>
	</p>
</form>

<script>
jQuery(function($) {
	var allMunis = [
		<?php
		$muni_repo_local = new ADMBike_Woo_Locations_Municipality_Repository();
		$all_munis = $muni_repo_local->get_items( array( 'is_active' => 1 ), 'name ASC' );
		foreach ( $all_munis as $m ) {
			echo '{id:' . absint( $m['id'] ) . ',state_id:' . absint( $m['state_id'] ) . ',name:' . wp_json_encode( $m['name'] ) . '},';
		}
		?>
	];

	var $form = $('form').first();
	var stateSelect = document.getElementById('state_id');
	var muniSelect = document.getElementById('municipality_id');
	var postcodeInput = $('#postcode');

	function filterMunis(stateId) {
		while (muniSelect.options.length > 1) {
			muniSelect.remove(1);
		}
		if (!stateId) return;

		var selectedMuniId = <?php echo $selected_municipality_id; ?>;
		allMunis.forEach(function(m) {
			if (m.state_id == stateId) {
				var opt = document.createElement('option');
				opt.value = m.id;
				opt.textContent = m.name;
				opt.dataset.state = m.state_id;
				if (m.id == selectedMuniId) opt.selected = true;
				muniSelect.appendChild(opt);
			}
		});
	}

	function clearErrors() {
		$form.find('.admbike-field-error').removeClass('admbike-field-error');
		$form.find('.admbike-validation-error').remove();
	}

	function showError($field, message) {
		clearErrors();
		$field.addClass('admbike-field-error');
		$field.after('<p class="description admbike-validation-error" style="color:#d63638;">' + message + '</p>');
		$field.trigger('focus');
	}

	function validateForm() {
		clearErrors();

		if (!$('#state_id').val()) {
			showError($('#state_id'), '<?php echo esc_js( __( 'Selecciona un estado.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		if (!$('#municipality_id').val()) {
			showError($('#municipality_id'), '<?php echo esc_js( __( 'Selecciona un municipio.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		if (!postcodeInput.val().trim()) {
			showError(postcodeInput, '<?php echo esc_js( __( 'Ingresa un código postal.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		if (!/^[0-9-]+$/.test(String(postcodeInput.val() || ''))) {
			showError(postcodeInput, '<?php echo esc_js( __( 'Usa solo dígitos y guiones.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		return true;
	}

	if (stateSelect && muniSelect) {
		stateSelect.addEventListener('change', function() {
			filterMunis(this.value);
		});
		filterMunis(stateSelect.value);
	}

	$form.on('submit', function(e) {
		if (!validateForm()) {
			e.preventDefault();
		}
	});
});
</script>
