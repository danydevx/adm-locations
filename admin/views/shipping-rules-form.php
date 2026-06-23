<?php
/**
 * Shipping rules add/edit form.
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

$match_type = $is_edit ? ( $item['match_type'] ?? '' ) : ( isset( $_POST['match_type'] ) ? sanitize_key( $_POST['match_type'] ) : '' );
$rule_type  = $is_edit ? ( $item['rule_type'] ?? '' ) : ( isset( $_POST['rule_type'] ) ? sanitize_key( $_POST['rule_type'] ) : '' );

$selected_state_id        = $is_edit ? (int) ( $item['state_id'] ?? 0 ) : ( isset( $_POST['state_id'] ) ? absint( $_POST['state_id'] ) : 0 );
$selected_municipality_id = $is_edit ? (int) ( $item['municipality_id'] ?? 0 ) : ( isset( $_POST['municipality_id'] ) ? absint( $_POST['municipality_id'] ) : 0 );
$selected_postcode_id     = $is_edit ? (int) ( $item['postcode_id'] ?? 0 ) : ( isset( $_POST['postcode_id'] ) ? absint( $_POST['postcode_id'] ) : 0 );
$selected_postcode_from   = $is_edit ? ( $item['postcode_from'] ?? '' ) : ( isset( $_POST['postcode_from'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $_POST['postcode_from'] ) : '' );
$selected_postcode_to     = $is_edit ? ( $item['postcode_to'] ?? '' ) : ( isset( $_POST['postcode_to'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $_POST['postcode_to'] ) : '' );
$selected_cost            = $is_edit ? ( $item['shipping_cost'] ?? 0 ) : ( isset( $_POST['shipping_cost'] ) ? (float) $_POST['shipping_cost'] : 0 );
$selected_currency       = $is_edit ? ( $item['currency_code'] ?? 'MXN' ) : ( isset( $_POST['currency_code'] ) ? sanitize_text_field( (string) $_POST['currency_code'] ) : 'MXN' );
$selected_priority       = $is_edit ? ( $item['priority'] ?? 100 ) : ( isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 100 );
$selected_notes          = $is_edit ? ( $item['notes'] ?? '' ) : ( isset( $_POST['notes'] ) ? sanitize_textarea_field( (string) $_POST['notes'] ) : '' );
$selected_active         = $is_edit ? (int) ( $item['is_active'] ?? 1 ) : ( isset( $_POST['is_active'] ) ? (int) (bool) $_POST['is_active'] : 1 );
?>
<?php if ( $error_msg ) : ?>
	<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
<?php endif; ?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $is_edit ? __( 'Edit Shipping Rule', 'admbike-woo-locations' ) : __( 'Add Shipping Rule', 'admbike-woo-locations' ) ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'admbike-woo-locations' ); ?></a>
	<hr class="wp-header-end">
</div>

<form method="post" action="" id="admbike-rule-form" style="max-width:700px;">
	<input type="hidden" name="_action" value="<?php echo esc_attr( $is_edit ? 'edit' : 'add' ); ?>">
	<?php wp_nonce_field( 'admbike_save_rule', 'admbike_rule_nonce' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="match_type"><?php esc_html_e( 'Match Type', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<select id="match_type" name="match_type" required>
					<option value=""><?php esc_html_e( 'Select match type…', 'admbike-woo-locations' ); ?></option>
					<option value="state" <?php selected( 'state', $match_type ); ?>><?php esc_html_e( 'State', 'admbike-woo-locations' ); ?></option>
					<option value="municipality" <?php selected( 'municipality', $match_type ); ?>><?php esc_html_e( 'Municipality', 'admbike-woo-locations' ); ?></option>
					<option value="postcode" <?php selected( 'postcode', $match_type ); ?>><?php esc_html_e( 'Postcode (exact)', 'admbike-woo-locations' ); ?></option>
					<option value="postcode_range" <?php selected( 'postcode_range', $match_type ); ?>><?php esc_html_e( 'Postcode Range', 'admbike-woo-locations' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Defines which geographic areas this rule applies to.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>
	</table>

	<fieldset id="admbike-match-fields" style="border:1px solid #ccc;padding:15px;margin-bottom:20px; border-radius:4px;">
		<legend style="font-weight:600;padding:0 5px;"><?php esc_html_e( 'Location Scope', 'admbike-woo-locations' ); ?></legend>

		<table class="form-table" style="margin-bottom:0;">
			<tbody id="admbike-field-state" class="admbike-match-group" style="<?php echo 'state' !== $match_type && 'municipality' !== $match_type && 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
				<tr>
					<th scope="row">
						<label for="state_id"><?php esc_html_e( 'State', 'admbike-woo-locations' ); ?> <span style="color:#d63638;" id="admbike-state-required">*</span>
						</label>
					</th>
					<td>
						<select id="state_id" name="state_id" style="<?php echo 'state' !== $match_type && 'municipality' !== $match_type && 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
							<option value=""><?php esc_html_e( 'Select a state…', 'admbike-woo-locations' ); ?></option>
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
						<label for="municipality_id"><?php esc_html_e( 'Municipality', 'admbike-woo-locations' ); ?> <span style="color:#d63638;" id="admbike-muni-required">*</span>
						</label>
					</th>
					<td>
						<select id="municipality_id" name="municipality_id" style="<?php echo 'municipality' !== $match_type ? 'display:none;' : ''; ?>">
							<option value=""><?php esc_html_e( 'Select a municipality…', 'admbike-woo-locations' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Select a state first to load its municipalities.', 'admbike-woo-locations' ); ?></p>
					</td>
				</tr>
			</tbody>

			<tbody id="admbike-field-postcode" class="admbike-match-group" style="<?php echo 'postcode' !== $match_type ? 'display:none;' : ''; ?>">
				<tr>
					<th scope="row">
						<label for="postcode_id"><?php esc_html_e( 'Postcode', 'admbike-woo-locations' ); ?> <span style="color:#d63638;" id="admbike-pc-required">*</span>
						</label>
					</th>
					<td>
						<select id="postcode_id" name="postcode_id" style="<?php echo 'postcode' !== $match_type ? 'display:none;' : ''; ?>">
							<option value=""><?php esc_html_e( 'Select a postcode…', 'admbike-woo-locations' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Select a municipality first to load its postcodes.', 'admbike-woo-locations' ); ?></p>
					</td>
				</tr>
			</tbody>

			<tbody id="admbike-field-postcode-range" class="admbike-match-group" style="<?php echo 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
				<tr>
					<th scope="row">
						<label for="postcode_from"><?php esc_html_e( 'Postcode From', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
					</th>
					<td>
						<input type="text" id="postcode_from" name="postcode_from" maxlength="10"
							value="<?php echo esc_attr( $selected_postcode_from ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. 82000', 'admbike-woo-locations' ); ?>"
							style="<?php echo 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="postcode_to"><?php esc_html_e( 'Postcode To', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
					</th>
					<td>
						<input type="text" id="postcode_to" name="postcode_to" maxlength="10"
							value="<?php echo esc_attr( $selected_postcode_to ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. 82139', 'admbike-woo-locations' ); ?>"
							style="<?php echo 'postcode_range' !== $match_type ? 'display:none;' : ''; ?>">
					</td>
				</tr>
			</tbody>
		</table>
	</fieldset>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="rule_type"><?php esc_html_e( 'Rule Type', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
			</th>
			<td>
				<select id="rule_type" name="rule_type" required>
					<option value=""><?php esc_html_e( 'Select rule type…', 'admbike-woo-locations' ); ?></option>
					<option value="free" <?php selected( 'free', $rule_type ); ?>><?php esc_html_e( 'Free Shipping', 'admbike-woo-locations' ); ?></option>
					<option value="paid" <?php selected( 'paid', $rule_type ); ?>><?php esc_html_e( 'Paid Shipping', 'admbike-woo-locations' ); ?></option>
					<option value="unavailable" <?php selected( 'unavailable', $rule_type ); ?>><?php esc_html_e( 'Unavailable / Blocked', 'admbike-woo-locations' ); ?></option>
				</select>
			</td>
		</tr>

		<tbody id="admbike-cost-fields" style="<?php echo 'paid' !== $rule_type ? 'display:none;' : ''; ?>">
			<tr>
				<th scope="row">
					<label for="shipping_cost"><?php esc_html_e( 'Shipping Cost', 'admbike-woo-locations' ); ?> <span style="color:#d63638;">*</span></label>
				</th>
				<td>
					<input type="number" id="shipping_cost" name="shipping_cost" step="0.01" min="0" max="9999999"
						value="<?php echo esc_attr( number_format( $selected_cost, 2, '.', '' ) ); ?>"
						placeholder="0.00">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="currency_code"><?php esc_html_e( 'Currency', 'admbike-woo-locations' ); ?></label>
				</th>
				<td>
					<input type="text" id="currency_code" name="currency_code" maxlength="3" value="<?php echo esc_attr( $selected_currency ); ?>" placeholder="MXN" style="width:80px;">
				</td>
			</tr>
		</tbody>

		<tr>
			<th scope="row">
				<label for="priority"><?php esc_html_e( 'Priority', 'admbike-woo-locations' ); ?></label>
			</th>
			<td>
				<input type="number" id="priority" name="priority" min="1" max="9999" value="<?php echo esc_attr( $selected_priority ); ?>" style="width:100px;">
				<p class="description"><?php esc_html_e( 'Lower number = higher precedence. E.g. 10 beats 50.', 'admbike-woo-locations' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Active', 'admbike-woo-locations' ); ?></th>
			<td>
				<label for="is_active">
					<input type="checkbox" id="is_active" name="is_active" value="1" <?php checked( $selected_active, 1 ); ?>>
					<?php esc_html_e( 'Enable this rule', 'admbike-woo-locations' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="notes"><?php esc_html_e( 'Notes', 'admbike-woo-locations' ); ?></label>
			</th>
			<td>
				<textarea id="notes" name="notes" rows="3" class="regular-text" placeholder="<?php esc_attr_e( 'Internal notes…', 'admbike-woo-locations' ); ?>"><?php echo esc_textarea( $selected_notes ); ?></textarea>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php echo esc_html( $is_edit ? __( 'Update Rule', 'admbike-woo-locations' ) : __( 'Add Rule', 'admbike-woo-locations' ) ); ?></button>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'admbike-woo-locations' ); ?></a>
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
	var allPCs = [
		<?php
		$pc_repo_local = new ADMBike_Woo_Locations_Postcode_Repository();
		foreach ( $pc_repo_local->get_items( array( 'is_active' => 1 ), 'postcode ASC' ) as $p ) {
			echo '{id:' . absint( $p['id'] ) . ',municipality_id:' . absint( $p['municipality_id'] ) . ',postcode:' . wp_json_encode( $p['postcode'] ) . '},';
		}
		?>
	];

	var matchType = document.getElementById('match_type');
	var ruleType = document.getElementById('rule_type');
	var stateSelect = document.getElementById('state_id');
	var muniSelect = document.getElementById('municipality_id');
	var pcSelect = document.getElementById('postcode_id');
	var pcFromInput = document.getElementById('postcode_from');
	var pcToInput = document.getElementById('postcode_to');

	var fieldState = document.getElementById('admbike-field-state');
	var fieldMuni = document.getElementById('admbike-field-municipality');
	var fieldPC = document.getElementById('admbike-field-postcode');
	var fieldRange = document.getElementById('admbike-field-postcode-range');
	var costFields = document.getElementById('admbike-cost-fields');
	var stateRequired = document.getElementById('admbike-state-required');
	var muniRequired = document.getElementById('admbike-muni-required');

	var selStateId = <?php echo absint( $selected_state_id ); ?>;
	var selMuniId = <?php echo absint( $selected_municipality_id ); ?>;
	var selPcId = <?php echo absint( $selected_postcode_id ); ?>;

	function showFields(type) {
		[fieldState, fieldMuni, fieldPC, fieldRange].forEach(function(el){ el.style.display = 'none'; });
		document.getElementById('state_id').style.display = 'none';
		document.getElementById('municipality_id').style.display = 'none';
		document.getElementById('postcode_id').style.display = 'none';
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
			document.getElementById('postcode_id').style.display = '';
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

	function filterPCs(muniId) {
		while (pcSelect.options.length > 1) { pcSelect.remove(1); }
		if (!muniId) return;
		allPCs.forEach(function(p) {
			if (p.municipality_id == muniId) {
				var opt = document.createElement('option');
				opt.value = p.id;
				opt.textContent = p.postcode;
				if (p.id == selPcId) opt.selected = true;
				pcSelect.appendChild(opt);
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

	if (muniSelect && pcSelect) {
		muniSelect.addEventListener('change', function() {
			filterPCs(this.value);
		});
		filterPCs(muniSelect.value);
	}
})();
</script>
