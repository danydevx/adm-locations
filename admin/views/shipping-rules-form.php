<?php
/**
 * Formulario para agregar o editar reglas de envío.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$item      = isset( $item ) ? $item : null;
$states   = isset( $states ) ? $states : array();
$error_msg = isset( $error_msg ) ? $error_msg : '';
$is_edit   = ( null !== $item && ! empty( $item ) );
$pc_repo_local = new ADMBike_Woo_Locations_Postcode_Repository();

$post = wp_unslash( $_POST );
$match_type = $is_edit ? ( $item['match_type'] ?? '' ) : ( isset( $post['match_type'] ) ? sanitize_key( (string) $post['match_type'] ) : '' );
$rule_type  = $is_edit ? ( $item['rule_type'] ?? '' ) : ( isset( $post['rule_type'] ) ? sanitize_key( (string) $post['rule_type'] ) : '' );

$selected_state_id        = $is_edit ? (int) ( $item['state_id'] ?? 0 ) : ( isset( $post['state_id'] ) ? absint( $post['state_id'] ) : 0 );
$selected_municipality_id = $is_edit ? (int) ( $item['municipality_id'] ?? 0 ) : ( isset( $post['municipality_id'] ) ? absint( $post['municipality_id'] ) : 0 );
$selected_rule_title      = $is_edit ? (string) ( $item['rule_title'] ?? '' ) : ( isset( $post['rule_title'] ) ? sanitize_text_field( (string) $post['rule_title'] ) : '' );
$selected_rule_title      = '' !== $selected_rule_title ? $selected_rule_title : ( $is_edit ? (string) ( $item['display_title'] ?? '' ) : '' );
$selected_postcode_code   = $is_edit ? (string) ( $item['postcode_code'] ?? '' ) : ( isset( $post['postcode_code'] ) ? preg_replace( '/[^0-9]/', '', (string) $post['postcode_code'] ) : '' );
$selected_postcode_code   = '' !== $selected_postcode_code ? substr( (string) $selected_postcode_code, 0, 5 ) : '';
$selected_postcode_row     = $is_edit && ! empty( $item['postcode_id'] ) ? $pc_repo_local->get_by_id( (int) $item['postcode_id'] ) : null;
$selected_postcode_code   = '' !== $selected_postcode_code ? $selected_postcode_code : ( is_array( $selected_postcode_row ) && ! empty( $selected_postcode_row['postcode'] ) ? (string) $selected_postcode_row['postcode'] : '' );
$selected_postcode_from   = $is_edit ? ( $item['postcode_from'] ?? '' ) : ( isset( $post['postcode_from'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $post['postcode_from'] ) : '' );
$selected_postcode_to     = $is_edit ? ( $item['postcode_to'] ?? '' ) : ( isset( $post['postcode_to'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $post['postcode_to'] ) : '' );
$selected_cost            = $is_edit ? ( $item['shipping_cost'] ?? 0 ) : ( isset( $post['shipping_cost'] ) ? (float) $post['shipping_cost'] : 0 );
$selected_currency       = $is_edit ? ( $item['currency_code'] ?? 'MXN' ) : ( isset( $post['currency_code'] ) ? sanitize_text_field( (string) $post['currency_code'] ) : 'MXN' );
$selected_priority       = $is_edit ? ( $item['priority'] ?? 100 ) : ( isset( $post['priority'] ) ? absint( $post['priority'] ) : 100 );
$selected_display_title   = $is_edit ? ( $item['display_title'] ?? '' ) : ( isset( $post['display_title'] ) ? sanitize_text_field( (string) $post['display_title'] ) : '' );
$selected_customer_message = $is_edit ? ( $item['customer_message'] ?? '' ) : ( isset( $post['customer_message'] ) ? sanitize_textarea_field( (string) $post['customer_message'] ) : '' );
$selected_notes          = $is_edit ? ( $item['notes'] ?? '' ) : ( isset( $post['notes'] ) ? sanitize_textarea_field( (string) $post['notes'] ) : '' );
$selected_active         = $is_edit ? (int) ( $item['is_active'] ?? 1 ) : ( isset( $post['is_active'] ) ? (int) (bool) $post['is_active'] : 1 );
?>
<?php if ( $error_msg ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
<?php endif; ?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $is_edit ? __( 'Editar regla de envío', 'admbike-woo-locations' ) : __( 'Agregar regla de envío', 'admbike-woo-locations' ) ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) ); ?>" class="page-title-action"><?php esc_html_e( 'Volver al listado', 'admbike-woo-locations' ); ?></a>
	<hr class="wp-header-end">
</div>

<form method="post" action="" id="admbike-rule-form" style="max-width:700px;">
	<input type="hidden" name="_action" value="<?php echo esc_attr( $is_edit ? 'edit' : 'add' ); ?>">
	<?php wp_nonce_field( 'orpot_woo_locations_save_rule', 'orpot_woo_locations_rule_nonce' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="match_type"><?php esc_html_e( 'Tipo de coincidencia', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<select id="match_type" name="match_type" required>
					<option value=""><?php esc_html_e( 'Selecciona un tipo de coincidencia…', 'admbike-woo-locations' ); ?></option>
					<option value="state" <?php selected( 'state', $match_type ); ?>><?php esc_html_e( 'Estado', 'admbike-woo-locations' ); ?></option>
					<option value="municipality" <?php selected( 'municipality', $match_type ); ?>><?php esc_html_e( 'Municipio', 'admbike-woo-locations' ); ?></option>
					<option value="postcode" <?php selected( 'postcode', $match_type ); ?>><?php esc_html_e( 'Código postal (exacto)', 'admbike-woo-locations' ); ?></option>
					<option value="postcode_range" <?php selected( 'postcode_range', $match_type ); ?>><?php esc_html_e( 'Rango de códigos postales', 'admbike-woo-locations' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Define a qué áreas geográficas aplica esta regla.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
	</table>

	<fieldset id="admbike-match-fields" style="border:1px solid #ccc;padding:15px;margin-bottom:20px; border-radius:4px;">
		<legend style="font-weight:600;padding:0 5px;"><?php esc_html_e( 'Ámbito de ubicación', 'admbike-woo-locations' ); ?></legend>

		<table class="form-table" style="margin-bottom:0;">
			<tbody id="admbike-field-state" class="admbike-match-group" style="<?php echo 'state' !== $match_type && 'municipality' !== $match_type && 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
				<tr>
					<th scope="row">
						<label for="state_id"><?php esc_html_e( 'Estado', 'admbike-woo-locations' ); ?> <span style="color:#d63638;" id="admbike-state-required">*</span>
						</label>
					</th>
					<td>
						<select id="state_id" name="state_id" style="<?php echo 'state' !== $match_type && 'municipality' !== $match_type && 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
							<option value=""><?php esc_html_e( 'Selecciona un estado…', 'admbike-woo-locations' ); ?></option>
							<?php foreach ( $states as $s ) : ?>
								<option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $selected_state_id, $s['id'] ); ?>>
									<?php echo esc_html( $s['name'] . ' (' . $s['code'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>

			<tbody id="admbike-field-municipality" class="admbike-match-group" style="<?php echo 'municipality' !== $match_type ? 'display:none;' : ''; ?>">
				<tr>
					<th scope="row">
						<label for="municipality_id"><?php esc_html_e( 'Municipio', 'admbike-woo-locations' ); ?> <span style="color:#d63638;" id="admbike-muni-required">*</span>
						</label>
					</th>
					<td>
						<select id="municipality_id" name="municipality_id" style="<?php echo 'municipality' !== $match_type ? 'display:none;' : ''; ?>">
							<option value=""><?php esc_html_e( 'Selecciona un municipio…', 'admbike-woo-locations' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Selecciona primero un estado para cargar sus municipios.', 'admbike-woo-locations' ); ?></p>
						<p class="description"><?php esc_html_e( 'Esta regla usará la cobertura postal configurada en el municipio seleccionado.', 'admbike-woo-locations' ); ?></p>
					</td>
				</tr>
			</tbody>

			<tbody id="admbike-field-postcode" class="admbike-match-group" style="<?php echo 'postcode' !== $match_type ? 'display:none;' : ''; ?>">
				<tr>
					<th scope="row">
						<label for="postcode_code"><?php esc_html_e( 'Código postal', 'admbike-woo-locations' ); ?> <span style="color:#d63638;" id="admbike-pc-required">*</span>
						</label>
					</th>
					<td>
						<input type="text" id="postcode_code" name="postcode_code" inputmode="numeric" pattern="[0-9]*" maxlength="5"
							value="<?php echo esc_attr( $selected_postcode_code ); ?>"
								placeholder="<?php esc_attr_e( 'p. ej. 44100', 'admbike-woo-locations' ); ?>"
							style="<?php echo 'postcode' !== $match_type ? 'display:none;' : ''; ?>">
						<p class="description"><?php esc_html_e( 'Ingresa un código postal de 5 dígitos.', 'admbike-woo-locations' ); ?></p>
					</td>
				</tr>
			</tbody>

			<tbody id="admbike-field-postcode-range" class="admbike-match-group" style="<?php echo 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
				<tr>
					<th scope="row">
						<label for="postcode_from"><?php esc_html_e( 'Código postal inicial', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
					</th>
					<td>
						<input type="text" id="postcode_from" name="postcode_from" maxlength="10"
							value="<?php echo esc_attr( $selected_postcode_from ); ?>"
								placeholder="<?php esc_attr_e( 'p. ej. 82000', 'admbike-woo-locations' ); ?>"
							style="<?php echo 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="postcode_to"><?php esc_html_e( 'Código postal final', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
					</th>
					<td>
						<input type="text" id="postcode_to" name="postcode_to" maxlength="10"
							value="<?php echo esc_attr( $selected_postcode_to ); ?>"
								placeholder="<?php esc_attr_e( 'p. ej. 82139', 'admbike-woo-locations' ); ?>"
							style="<?php echo 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
					</td>
				</tr>
			</tbody>
		</table>
	</fieldset>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="rule_type"><?php esc_html_e( 'Tipo de regla', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<select id="rule_type" name="rule_type" required>
					<option value=""><?php esc_html_e( 'Selecciona un tipo de regla…', 'admbike-woo-locations' ); ?></option>
					<option value="free" <?php selected( 'free', $rule_type ); ?>><?php esc_html_e( 'Envío gratis', 'admbike-woo-locations' ); ?></option>
					<option value="paid" <?php selected( 'paid', $rule_type ); ?>><?php esc_html_e( 'Envío pagado', 'admbike-woo-locations' ); ?></option>
					<option value="unavailable" <?php selected( 'unavailable', $rule_type ); ?>><?php esc_html_e( 'No disponible / bloqueado', 'admbike-woo-locations' ); ?></option>
				</select>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="rule_title"><?php esc_html_e( 'Título de la regla', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<input type="text" id="rule_title" name="rule_title" class="regular-text" required
					value="<?php echo esc_attr( $selected_rule_title ); ?>"
					placeholder="<?php esc_attr_e( 'p. ej. Envíos a GDL', 'admbike-woo-locations' ); ?>">
				<p class="description"><?php esc_html_e( 'Nombre interno usado solo en el listado de admin.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="display_title"><?php esc_html_e( 'Título visible', 'admbike-woo-locations' ); ?></label>
			</th>
			<td>
				<input type="text" id="display_title" name="display_title" class="regular-text"
					value="<?php echo esc_attr( $selected_display_title ); ?>"
					placeholder="<?php esc_attr_e( 'p. ej. Entrega en Guadalajara', 'admbike-woo-locations' ); ?>">
				<p class="description"><?php esc_html_e( 'Texto que se muestra como título de la opción de envío en el pago.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="customer_message"><?php esc_html_e( 'Explicación del envío', 'admbike-woo-locations' ); ?></label>
			</th>
			<td>
				<textarea id="customer_message" name="customer_message" rows="3" class="large-text"
					placeholder="<?php esc_attr_e( 'Explica por qué aplica este costo de envío...', 'admbike-woo-locations' ); ?>"><?php echo esc_textarea( $selected_customer_message ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Se muestra debajo de la opción de envío para explicar el precio.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>

		<tbody id="admbike-cost-fields" style="<?php echo 'paid' !== $rule_type ? 'display:none;' : ''; ?>">
			<tr>
				<th scope="row">
					<label for="shipping_cost"><?php esc_html_e( 'Costo de envío', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
				</th>
				<td>
					<input type="number" id="shipping_cost" name="shipping_cost" step="0.01" min="0" max="9999999"
						value="<?php echo esc_attr( number_format( $selected_cost, 2, '.', '' ) ); ?>"
						placeholder="0.00">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="currency_code"><?php esc_html_e( 'Moneda', 'admbike-woo-locations' ); ?></label>
				</th>
				<td>
					<input type="text" id="currency_code" name="currency_code" maxlength="3" value="<?php echo esc_attr( $selected_currency ); ?>" placeholder="MXN" style="width:80px;">
				</td>
			</tr>
		</tbody>

		<tr>
			<th scope="row">
				<label for="priority"><?php esc_html_e( 'Prioridad', 'admbike-woo-locations' ); ?></label>
			</th>
			<td>
				<input type="number" id="priority" name="priority" min="1" max="9999" value="<?php echo esc_attr( $selected_priority ); ?>" style="width:100px;">
				<p class="description"><?php esc_html_e( 'Un número menor = mayor prioridad. Ej.: 10 gana a 50.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Activo', 'admbike-woo-locations' ); ?></th>
			<td>
				<label for="is_active">
					<input type="checkbox" id="is_active" name="is_active" value="1" <?php checked( $selected_active, 1 ); ?>>
					<?php esc_html_e( 'Habilitar esta regla', 'admbike-woo-locations' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="notes"><?php esc_html_e( 'Notas', 'admbike-woo-locations' ); ?></label>
			</th>
			<td>
				<textarea id="notes" name="notes" rows="3" class="regular-text" placeholder="<?php esc_attr_e( 'Notas internas…', 'admbike-woo-locations' ); ?>"><?php echo esc_textarea( $selected_notes ); ?></textarea>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php echo esc_html( $is_edit ? __( 'Actualizar regla', 'admbike-woo-locations' ) : __( 'Agregar regla', 'admbike-woo-locations' ) ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Cancelar', 'admbike-woo-locations' ); ?></a>
	</p>
</form>

<script>
(function() {
	var allMunis = [
		<?php
		$muni_repo_local = new ADMBike_Woo_Locations_Municipality_Repository();
		foreach ( $muni_repo_local->get_items( array( 'is_active' => 1 ), 'name ASC' ) as $m ) {
			echo '{id:' . absint( $m['id'] ) . ',state_id:' . absint( $m['state_id'] ) . ',name:' . wp_json_encode( $m['name'] ) . '},';
		}
		?>
	];
	var matchType = document.getElementById('match_type');
	var ruleType = document.getElementById('rule_type');
	var stateSelect = document.getElementById('state_id');
	var muniSelect = document.getElementById('municipality_id');
	var pcInput = document.getElementById('postcode_code');
	var pcFromInput = document.getElementById('postcode_from');
	var pcToInput = document.getElementById('postcode_to');

	var fieldState = document.getElementById('admbike-field-state');
	var fieldMuni = document.getElementById('admbike-field-municipality');
	var fieldPC = document.getElementById('admbike-field-postcode');
	var fieldRange = document.getElementById('admbike-field-postcode-range');
	var costFields = document.getElementById('admbike-cost-fields');
	var stateRequired = document.getElementById('admbike-state-required');
	var muniRequired = document.getElementById('admbike-muni-required');
	var $form = jQuery('#admbike-rule-form');

	var selStateId = <?php echo absint( $selected_state_id ); ?>;
	var selMuniId = <?php echo absint( $selected_municipality_id ); ?>;
	function showFields(type) {
		[fieldState, fieldMuni, fieldPC, fieldRange].forEach(function(el){ el.style.display = 'none'; });
		document.getElementById('state_id').style.display = 'none';
		document.getElementById('municipality_id').style.display = 'none';
		document.getElementById('postcode_code').style.display = 'none';
		document.getElementById('postcode_from').style.display = 'none';
		document.getElementById('postcode_to').style.display = 'none';
		[stateRequired, muniRequired].forEach(function(el){ if(el) el.style.display = 'none'; });

		if (type === 'state' || type === 'municipality' || type === 'postcode_range') {
			fieldState.style.display = '';
			document.getElementById('state_id').style.display = '';
			if (stateRequired) stateRequired.style.display = '';
		}
		if (type === 'municipality') {
			fieldMuni.style.display = '';
			document.getElementById('municipality_id').style.display = '';
			if (muniRequired) muniRequired.style.display = '';
		}
		if (type === 'postcode') {
			fieldPC.style.display = '';
			document.getElementById('postcode_code').style.display = '';
		}
		if (type === 'postcode_range') {
			fieldRange.style.display = '';
			document.getElementById('postcode_from').style.display = '';
			document.getElementById('postcode_to').style.display = '';
		}
	}

	function showCost(type) {
		if (costFields) {
			costFields.style.display = (type === 'paid') ? '' : 'none';
		}
	}

	function clearValidationErrors() {
		$form.find('.admbike-field-error').removeClass('admbike-field-error');
		$form.find('.admbike-validation-error').remove();
	}

	function showValidationError(selector, message) {
		var $field = jQuery(selector);

		clearValidationErrors();
		$field.addClass('admbike-field-error');
		$field.after('<p class="description admbike-validation-error" style="color:#d63638;">' + message + '</p>');
		$field.trigger('focus');
	}

	function validateForm() {
		var type = matchType ? matchType.value : '';

		clearValidationErrors();

		if ('' === type) {
			showValidationError('#match_type', '<?php echo esc_js( __( 'Selecciona un tipo de coincidencia.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		if ( ! ruleType || '' === ruleType.value ) {
			showValidationError('#rule_type', '<?php echo esc_js( __( 'Selecciona un tipo de regla.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		if ( '' === jQuery.trim( jQuery('#rule_title').val() || '' ) ) {
			showValidationError('#rule_title', '<?php echo esc_js( __( 'Ingresa un título para la regla.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		if ( 'state' === type || 'municipality' === type || 'postcode_range' === type ) {
			if ( '' === jQuery.trim( jQuery('#state_id').val() || '' ) ) {
			showValidationError('#state_id', '<?php echo esc_js( __( 'Selecciona un estado.', 'admbike-woo-locations' ) ); ?>');
				return false;
			}
		}

		if ( 'municipality' === type && '' === jQuery.trim( jQuery('#municipality_id').val() || '' ) ) {
			showValidationError('#municipality_id', '<?php echo esc_js( __( 'Selecciona un municipio.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		if ( 'postcode' === type && '' === jQuery.trim( jQuery('#postcode_code').val() || '' ) ) {
			showValidationError('#postcode_code', '<?php echo esc_js( __( 'Ingresa un código postal.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		if ( 'postcode_range' === type ) {
			if ( '' === jQuery.trim( jQuery('#postcode_from').val() || '' ) ) {
				showValidationError('#postcode_from', '<?php echo esc_js( __( 'Ingresa el código postal inicial.', 'admbike-woo-locations' ) ); ?>');
				return false;
			}
			if ( '' === jQuery.trim( jQuery('#postcode_to').val() || '' ) ) {
				showValidationError('#postcode_to', '<?php echo esc_js( __( 'Ingresa el código postal final.', 'admbike-woo-locations' ) ); ?>');
				return false;
			}
		}

		if ( 'paid' === ruleType.value && '' === jQuery.trim( jQuery('#shipping_cost').val() || '' ) ) {
			showValidationError('#shipping_cost', '<?php echo esc_js( __( 'Ingresa un costo de envío.', 'admbike-woo-locations' ) ); ?>');
			return false;
		}

		return true;
	}

	function filterMunis(stateId) {
		while (muniSelect.options.length > 1) { muniSelect.remove(1); }
		if (!stateId) return;
		allMunis.forEach(function(m) {
			if (m.state_id == stateId) {
				var opt = document.createElement('option');
				opt.value = m.id;
				opt.textContent = m.name;
				if (m.id == selMuniId) opt.selected = true;
				muniSelect.appendChild(opt);
			}
		});
	}

	if (matchType) {
		matchType.addEventListener('change', function() {
			showFields(this.value);
		});
		showFields(matchType.value);
	}

	if (ruleType) {
		ruleType.addEventListener('change', function() {
			showCost(this.value);
		});
		showCost(ruleType.value);
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

})();
</script>
