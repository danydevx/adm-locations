<?php
/**
 * Shipping rules admin view.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$admin = orpot_woo_locations_admin();
$rules_repo = new ADMBike_Woo_Locations_Shipping_Rule_Repository();
$states_repo = new ADMBike_Woo_Locations_State_Repository();
$muni_repo = new ADMBike_Woo_Locations_Municipality_Repository();
$sync = function_exists( 'orpot_woo_locations_shipping_zone_sync' ) ? orpot_woo_locations_shipping_zone_sync() : null;

$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

if ( 'add' === $action || 'edit' === $action ) {
	$item = null;
	if ( 'edit' === $action && $id > 0 ) {
		$item = $rules_repo->get_by_id( $id );
		if ( ! $item ) {
			wp_die( esc_html__( 'No se encontró la regla.', 'admbike-woo-locations' ) );
		}
	}

	$states = $states_repo->get_items( array( 'is_active' => 1 ), 'name ASC' );
	$municipalities = $muni_repo->get_items( array( 'is_active' => 1 ), 'name ASC' );
	$postcodes_repo = new ADMBike_Woo_Locations_Postcode_Repository();
	$postcodes = $postcodes_repo->get_items( array( 'is_active' => 1 ), 'postcode ASC' );

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['orpot_woo_locations_rule_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['orpot_woo_locations_rule_nonce'] ) ), 'orpot_woo_locations_save_rule' ) || ! current_user_can( ADMBike_Woo_Locations_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Falló la verificación de seguridad.', 'admbike-woo-locations' ) );
		}

		$post = wp_unslash( $_POST );
		$action = isset( $post['_action'] ) ? sanitize_key( (string) $post['_action'] ) : '';
		$match_type = isset( $post['match_type'] ) ? sanitize_key( (string) $post['match_type'] ) : '';
		$rule_type  = isset( $post['rule_type'] ) ? sanitize_key( (string) $post['rule_type'] ) : '';
		$rule_title = isset( $post['rule_title'] ) ? sanitize_text_field( (string) $post['rule_title'] ) : '';
		$display_title = isset( $post['display_title'] ) ? sanitize_text_field( (string) $post['display_title'] ) : '';
		$customer_message = isset( $post['customer_message'] ) ? sanitize_textarea_field( (string) $post['customer_message'] ) : '';
		$state_id   = isset( $post['state_id'] ) ? absint( $post['state_id'] ) : 0;
		$muni_id    = isset( $post['municipality_id'] ) ? absint( $post['municipality_id'] ) : 0;
		$pc_id      = 0;
		$pc_code    = isset( $post['postcode_code'] ) ? preg_replace( '/[^0-9]/', '', (string) $post['postcode_code'] ) : '';
		$pc_code    = '' !== $pc_code ? substr( (string) $pc_code, 0, 5 ) : '';
		$pc_lookup   = null;
		$pc_from     = isset( $post['postcode_from'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $post['postcode_from'] ) : '';
		$pc_to       = isset( $post['postcode_to'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $post['postcode_to'] ) : '';
		$cost       = isset( $post['shipping_cost'] ) ? (float) $post['shipping_cost'] : 0;
		$currency   = isset( $post['currency_code'] ) ? strtoupper( sanitize_text_field( (string) $post['currency_code'] ) ) : 'MXN';
		$priority   = isset( $post['priority'] ) ? absint( $post['priority'] ) : 100;
		$active     = isset( $post['is_active'] ) ? (int) (bool) $post['is_active'] : 0;
		$notes      = isset( $post['notes'] ) ? sanitize_textarea_field( (string) $post['notes'] ) : '';

		$valid_match_types = array(
			ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE,
			ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_MUNICIPALITY,
			ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE,
			ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE,
		);
		$valid_rule_types = array(
			ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE,
			ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID,
			ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_UNAVAILABLE,
		);

		if ( ! in_array( $match_type, $valid_match_types, true ) || ! in_array( $rule_type, $valid_rule_types, true ) ) {
				$error_msg = __( 'El tipo de coincidencia o de regla no es válido.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
			return;
		}

		if ( '' === trim( $rule_title ) ) {
				$error_msg = __( 'El título de la regla es obligatorio.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
			return;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE === $match_type && $state_id <= 0 ) {
				$error_msg = __( 'Se requiere un estado para las reglas por estado.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
			return;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_MUNICIPALITY === $match_type && ( $state_id <= 0 || $muni_id <= 0 ) ) {
				$error_msg = __( 'Se requieren estado y municipio para las reglas por municipio.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
			return;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE === $match_type && '' === $pc_code ) {
				$error_msg = __( 'Se requiere un código postal para las reglas por código postal.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
			return;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE === $match_type && '' !== $pc_code ) {
			$pc_lookup = $postcodes_repo->get_by_postcode( $pc_code );
			if ( ! empty( $pc_lookup ) && ! empty( $pc_lookup[0]['id'] ) ) {
				$pc_id = absint( $pc_lookup[0]['id'] );
			}
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE === $match_type && ( empty( $pc_from ) || empty( $pc_to ) ) ) {
				$error_msg = __( 'Se requiere un rango de códigos postales (de / a) para las reglas por rango.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
			return;
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE === $match_type && $state_id <= 0 ) {
				$error_msg = __( 'Se requiere un estado para las reglas por rango de códigos postales.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
			return;
		}

		$data = array(
			'match_type'    => $match_type,
			'rule_type'     => $rule_type,
			'rule_title'    => $rule_title,
			'display_title' => $display_title,
			'customer_message' => $customer_message,
			'state_id'      => $state_id ?: null,
			'municipality_id' => $muni_id ?: null,
			'postcode_code' => $pc_code,
			'postcode_id'   => $pc_id ?: null,
			'postcode_from' => $pc_from,
			'postcode_to'   => $pc_to,
			'shipping_cost' => $cost,
			'currency_code' => $currency,
			'priority'      => $priority,
			'is_active'     => $active,
			'notes'         => $notes,
		);

		if ( 'add' === $action ) {
			$result = $rules_repo->create( $data );
			if ( $result ) {
				if ( $sync instanceof ADMBike_Woo_Locations_Shipping_Zone_Sync ) {
					$sync_result = $sync->sync_rule_by_id( (int) $result );
					if ( is_wp_error( $sync_result ) ) {
						$rules_repo->delete( (int) $result );
						$error_msg = $sync_result->get_error_message();
						include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
						return;
					}
				}

					$admin->redirect_with_message( 'success', urlencode( __( 'Regla creada correctamente y zona de WooCommerce sincronizada.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) );
			} else {
				$error_msg = __( 'No se pudo crear la regla.', 'admbike-woo-locations' );
			}
		} else {
			$result = $rules_repo->update( $id, $data );
			if ( $result ) {
				if ( $sync instanceof ADMBike_Woo_Locations_Shipping_Zone_Sync ) {
					$sync_result = $sync->sync_rule_by_id( $id );
					if ( is_wp_error( $sync_result ) ) {
						if ( $item ) {
							$rules_repo->update( $id, $item );
						}
						$error_msg = $sync_result->get_error_message();
						include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
						return;
					}
				}

					$admin->redirect_with_message( 'success', urlencode( __( 'Regla actualizada correctamente y zona de WooCommerce sincronizada.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) );
			} else {
				$error_msg = __( 'No se pudo actualizar la regla.', 'admbike-woo-locations' );
			}
		}

		include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
		return;
	}

	include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
	return;
}

if ( 'delete' === $action ) {
	if ( ! $admin->verify_nonce( 'orpot_woo_locations_delete_rule' ) ) {
		wp_die( esc_html__( 'Falló la verificación de seguridad.', 'admbike-woo-locations' ) );
	}

	$item = $rules_repo->get_by_id( $id );
	if ( $sync instanceof ADMBike_Woo_Locations_Shipping_Zone_Sync ) {
		$sync_result = $sync->delete_zone_for_rule( $item ? $item : $id );
		if ( is_wp_error( $sync_result ) ) {
			$error_msg = $sync_result->get_error_message();
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/shipping-rules-form.php';
			return;
		}
	}

	$result = $rules_repo->delete( $id );
	$admin->redirect_with_message( $result ? 'success' : 'error', urlencode( $result ? __( 'Regla eliminada.', 'admbike-woo-locations' ) : __( 'No se pudo eliminar la regla.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) );
	return;
}

if ( 'toggle' === $action ) {
	if ( ! $admin->verify_nonce( 'orpot_woo_locations_toggle_rule' ) ) {
		wp_die( esc_html__( 'Falló la verificación de seguridad.', 'admbike-woo-locations' ) );
	}

	$item = $rules_repo->get_by_id( $id );
	if ( $item ) {
		$rules_repo->update( $id, array( 'is_active' => $item['is_active'] ? 0 : 1 ) );
		if ( $sync instanceof ADMBike_Woo_Locations_Shipping_Zone_Sync ) {
			$sync_result = $sync->sync_rule_by_id( $id );
			if ( is_wp_error( $sync_result ) ) {
				$rules_repo->update( $id, $item );
				$admin->redirect_with_message( 'error', urlencode( $sync_result->get_error_message() ), array( 'page' => ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) );
				return;
			}
		}
	}

	$admin->redirect_with_message( 'success', urlencode( __( 'Estado actualizado.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) );
	return;
}

if ( 'preview' === $action ) {
	$state_id       = isset( $_GET['state_id'] ) ? absint( $_GET['state_id'] ) : 0;
	$municipality_id = isset( $_GET['municipality_id'] ) ? absint( $_GET['municipality_id'] ) : 0;
	$postcode        = isset( $_GET['postcode'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $_GET['postcode'] ) : '';

	$rules = $rules_repo->get_applicable_rules( $state_id ?: null, $municipality_id ?: null, $postcode ?: null );

	$state_map = array();
	foreach ( $states_repo->get_items( array(), 'name ASC' ) as $s ) {
		$state_map[ $s['id'] ] = $s['name'];
	}

	$muni_map = array();
	foreach ( $muni_repo->get_items( array(), 'name ASC' ) as $m ) {
		$muni_map[ $m['id'] ] = $m['name'];
	}

	$pc_map = array();
	$pc_repo_local = new ADMBike_Woo_Locations_Postcode_Repository();
	foreach ( $pc_repo_local->get_items( array(), 'postcode ASC' ) as $p ) {
		$pc_map[ $p['id'] ] = $p['postcode'];
	}
	?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Vista previa de reglas de envío', 'admbike-woo-locations' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) ); ?>" class="page-title-action"><?php esc_html_e( 'Volver al listado', 'admbike-woo-locations' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( empty( $rules ) ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'No se encontraron reglas que coincidan con la ubicación seleccionada.', 'admbike-woo-locations' ); ?></p></div>
	<?php else : ?>
		<p><?php esc_html_e( 'Reglas encontradas (ordenadas por prioridad):', 'admbike-woo-locations' ); ?></p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:60px;">#</th>
					<th><?php esc_html_e( 'Título de la regla', 'admbike-woo-locations' ); ?></th>
					<th><?php esc_html_e( 'Tipo de coincidencia', 'admbike-woo-locations' ); ?></th>
					<th><?php esc_html_e( 'Ámbito', 'admbike-woo-locations' ); ?></th>
					<th><?php esc_html_e( 'Tipo de regla', 'admbike-woo-locations' ); ?></th>
					<th><?php esc_html_e( 'Costo', 'admbike-woo-locations' ); ?></th>
					<th style="width:80px;"><?php esc_html_e( 'Prioridad', 'admbike-woo-locations' ); ?></th>
					<th><?php esc_html_e( 'Notas', 'admbike-woo-locations' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rules as $i => $rule ) : ?>
					<tr style="<?php echo ( 0 === $i ) ? 'background:#e7f5e7;' : ''; ?>">
						<td><?php echo esc_html( $i + 1 ); ?></td>
						<td><?php echo esc_html( ! empty( $rule['rule_title'] ) ? $rule['rule_title'] : ( $rule['display_title'] ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( $rule['match_type'] ); ?></code></td>
						<td>
							<?php
							if ( 'state' === $rule['match_type'] ) {
								echo esc_html( $state_map[ $rule['state_id'] ] ?? '—' );
							} elseif ( 'municipality' === $rule['match_type'] ) {
								echo esc_html( $muni_map[ $rule['municipality_id'] ] ?? '—' );
							} elseif ( 'postcode' === $rule['match_type'] ) {
								echo esc_html( ! empty( $rule['postcode_code'] ) ? $rule['postcode_code'] : ( $pc_map[ $rule['postcode_id'] ] ?? '—' ) );
							} elseif ( 'postcode_range' === $rule['match_type'] ) {
								echo esc_html( $rule['postcode_from'] . ' – ' . $rule['postcode_to'] );
							}
							?>
						</td>
						<td>
							<?php
							$badge_class = 'free' === $rule['rule_type'] ? 'color:green;' : ( 'paid' === $rule['rule_type'] ? 'color:#996900;' : 'color:red;' );
							printf( '<strong style="%1$s">%2$s</strong>', esc_attr( $badge_class ), esc_html( $rule['rule_type'] ) );
							?>
						</td>
						<td>
							<?php
							if ( 'free' === $rule['rule_type'] ) {
							esc_html_e( 'Gratis', 'admbike-woo-locations' );
							} elseif ( 'unavailable' === $rule['rule_type'] ) {
							esc_html_e( 'Bloqueada', 'admbike-woo-locations' );
							} else {
								echo esc_html( number_format( (float) $rule['shipping_cost'], 2 ) . ' ' . esc_html( $rule['currency_code'] ) );
							}
							?>
						</td>
						<td><?php echo esc_html( $rule['priority'] ); ?></td>
						<td><?php echo esc_html( $rule['notes'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
			<p><?php esc_html_e( 'La primera regla coincidente (fila 1) se aplicará en el pago. Una regla bloqueada impide el pago para esa ubicación.', 'admbike-woo-locations' ); ?></p>
	<?php endif; ?>
</div>
	<?php
	return;
}

$admin->render_admin_messages();

$paged      = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$rule_type  = isset( $_GET['rule_type'] ) ? sanitize_key( $_GET['rule_type'] ) : null;
$match_type = isset( $_GET['match_type'] ) ? sanitize_key( $_GET['match_type'] ) : null;
$is_active  = isset( $_GET['is_active'] ) ? ( '' !== $_GET['is_active'] ? (int) (bool) $_GET['is_active'] : null ) : null;
$per_page   = 20;

$total  = $rules_repo->count_all( $search, $rule_type, $match_type, $is_active );
$items  = $rules_repo->get_paginated( 'priority ASC, id ASC', $per_page, $paged, $search, $rule_type, $match_type, $is_active );
$total_pages = max( 1, ceil( $total / $per_page ) );

$states_repo_local = new ADMBike_Woo_Locations_State_Repository();
$muni_repo_local  = new ADMBike_Woo_Locations_Municipality_Repository();
$pc_repo_local   = new ADMBike_Woo_Locations_Postcode_Repository();

$state_map = array();
foreach ( $states_repo_local->get_items( array(), 'name ASC' ) as $s ) {
	$state_map[ $s['id'] ] = $s['name'];
}

$muni_map = array();
foreach ( $muni_repo_local->get_items( array(), 'name ASC' ) as $m ) {
	$muni_map[ $m['id'] ] = $m['name'];
}

$pc_map = array();
foreach ( $pc_repo_local->get_items( array(), 'postcode ASC' ) as $p ) {
	$pc_map[ $p['id'] ] = $p['postcode'];
}

$states_all = $states_repo_local->get_items( array(), 'name ASC' );
$munis_all  = $muni_repo_local->get_items( array(), 'name ASC' );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Reglas de envío', 'admbike-woo-locations' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG . '&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Agregar nuevo', 'admbike-woo-locations' ); ?></a>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG . '&action=preview' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Vista previa', 'admbike-woo-locations' ); ?></a>
	<hr class="wp-header-end">

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ); ?>">
		<p class="search-box">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar por título, notas, código postal…', 'admbike-woo-locations' ); ?>">
			<select name="rule_type" style="vertical-align:middle;">
				<option value=""><?php esc_html_e( 'Todos los tipos', 'admbike-woo-locations' ); ?></option>
				<option value="free" <?php selected( 'free', $rule_type ); ?>><?php esc_html_e( 'Gratis', 'admbike-woo-locations' ); ?></option>
				<option value="paid" <?php selected( 'paid', $rule_type ); ?>><?php esc_html_e( 'Pagada', 'admbike-woo-locations' ); ?></option>
				<option value="unavailable" <?php selected( 'unavailable', $rule_type ); ?>><?php esc_html_e( 'No disponible', 'admbike-woo-locations' ); ?></option>
			</select>
			<select name="match_type" style="vertical-align:middle;">
				<option value=""><?php esc_html_e( 'Todos los tipos de coincidencia', 'admbike-woo-locations' ); ?></option>
				<option value="state" <?php selected( 'state', $match_type ); ?>><?php esc_html_e( 'Estado', 'admbike-woo-locations' ); ?></option>
				<option value="municipality" <?php selected( 'municipality', $match_type ); ?>><?php esc_html_e( 'Municipio', 'admbike-woo-locations' ); ?></option>
				<option value="postcode" <?php selected( 'postcode', $match_type ); ?>><?php esc_html_e( 'Código postal', 'admbike-woo-locations' ); ?></option>
				<option value="postcode_range" <?php selected( 'postcode_range', $match_type ); ?>><?php esc_html_e( 'Rango de códigos postales', 'admbike-woo-locations' ); ?></option>
			</select>
			<select name="is_active" style="vertical-align:middle;">
				<option value=""><?php esc_html_e( 'Todos los estatus', 'admbike-woo-locations' ); ?></option>
				<option value="1" <?php selected( 1, $is_active ); ?>><?php esc_html_e( 'Activo', 'admbike-woo-locations' ); ?></option>
				<option value="0" <?php selected( 0, $is_active ); ?>><?php esc_html_e( 'Inactivo', 'admbike-woo-locations' ); ?></option>
			</select>
			<input type="submit" class="button" value="<?php esc_attr_e( 'Filtrar', 'admbike-woo-locations' ); ?>">
			<?php if ( $search || $rule_type || $match_type || null !== $is_active ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Limpiar', 'admbike-woo-locations' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<?php if ( empty( $items ) ) : ?>
		<p><?php esc_html_e( 'No se encontraron reglas.', 'admbike-woo-locations' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:50px;"><?php esc_html_e( 'ID', 'admbike-woo-locations' ); ?></th>
					<th><?php esc_html_e( 'Título de la regla', 'admbike-woo-locations' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Tipo de coincidencia', 'admbike-woo-locations' ); ?></th>
					<th><?php esc_html_e( 'Ámbito', 'admbike-woo-locations' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Tipo de regla', 'admbike-woo-locations' ); ?></th>
					<th style="width:100px;"><?php esc_html_e( 'Costo', 'admbike-woo-locations' ); ?></th>
					<th style="width:70px;"><?php esc_html_e( 'Prioridad', 'admbike-woo-locations' ); ?></th>
					<th style="width:60px;"><?php esc_html_e( 'Estatus', 'admbike-woo-locations' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Acciones', 'admbike-woo-locations' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $items as $item ) :
					$edit_url   = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG . '&action=edit&id=' . absint( $item['id'] ) ),
						'orpot_woo_locations_edit_rule_' . $item['id'],
						'_wpnonce'
					);
					$delete_url = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG . '&action=delete&id=' . absint( $item['id'] ) ),
						'orpot_woo_locations_delete_rule',
						'_wpnonce'
					);
					$toggle_url = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG . '&action=toggle&id=' . absint( $item['id'] ) ),
						'orpot_woo_locations_toggle_rule',
						'_wpnonce'
					);

					$scope = '';
					if ( 'state' === $item['match_type'] ) {
						$scope = $state_map[ $item['state_id'] ] ?? '—';
					} elseif ( 'municipality' === $item['match_type'] ) {
						$scope = $muni_map[ $item['municipality_id'] ] ?? '—';
					} elseif ( 'postcode' === $item['match_type'] ) {
						$scope = ! empty( $item['postcode_code'] ) ? $item['postcode_code'] : ( $pc_map[ $item['postcode_id'] ] ?? '—' );
					} elseif ( 'postcode_range' === $item['match_type'] ) {
						$scope = esc_html( $item['postcode_from'] ) . ' – ' . esc_html( $item['postcode_to'] );
					}

					$cost_display = '';
					if ( 'free' === $item['rule_type'] ) {
						$cost_display = esc_html__( 'Gratis', 'admbike-woo-locations' );
					} elseif ( 'unavailable' === $item['rule_type'] ) {
						$cost_display = '<span style="color:red;">' . esc_html__( 'Bloqueada', 'admbike-woo-locations' ) . '</span>';
					} else {
						$cost_display = esc_html( number_format( (float) $item['shipping_cost'], 2 ) . ' ' . esc_html( $item['currency_code'] ) );
					}

					$rule_type_badge = '';
					if ( 'free' === $item['rule_type'] ) {
						$rule_type_badge = '<span style="background:#d4edda;color:#155724;padding:2px 8px;border-radius:3px;font-size:11px;">' . esc_html__( 'GRATIS', 'admbike-woo-locations' ) . '</span>';
					} elseif ( 'paid' === $item['rule_type'] ) {
						$rule_type_badge = '<span style="background:#fff3cd;color:#856404;padding:2px 8px;border-radius:3px;font-size:11px;">' . esc_html__( 'PAGADA', 'admbike-woo-locations' ) . '</span>';
					} else {
						$rule_type_badge = '<span style="background:#f8d7da;color:#721c24;padding:2px 8px;border-radius:3px;font-size:11px;">' . esc_html__( 'BLOQUEADA', 'admbike-woo-locations' ) . '</span>';
					}
					?>
					<tr>
						<td><?php echo esc_html( $item['id'] ); ?></td>
						<td><?php echo esc_html( ! empty( $item['rule_title'] ) ? $item['rule_title'] : ( $item['display_title'] ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( $item['match_type'] ); ?></code></td>
						<td><?php echo esc_html( $scope ); ?></td>
						<td><?php echo $rule_type_badge; ?></td>
						<td><?php echo $cost_display; ?></td>
						<td><?php echo esc_html( $item['priority'] ); ?></td>
						<td style="text-align:center;">
							<?php if ( $item['is_active'] ) : ?>
				<span class="dashicons dashicons-yes-alt" style="color:#2271b1;" title="<?php esc_attr_e( 'Activo', 'admbike-woo-locations' ); ?>"></span>
							<?php else : ?>
				<span class="dashicons dashicons-dismiss" style="color:#d63638;" title="<?php esc_attr_e( 'Inactivo', 'admbike-woo-locations' ); ?>"></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'admbike-woo-locations' ); ?></a>
							<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small"><?php echo esc_html( $item['is_active'] ? __( 'Desactivar', 'admbike-woo-locations' ) : __( 'Activar', 'admbike-woo-locations' ) ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" style="color:#d63638;" onclick="return confirm('<?php esc_attr_e( '¿Eliminar esta regla?', 'admbike-woo-locations' ); ?>');"><?php esc_html_e( 'Eliminar', 'admbike-woo-locations' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="alignleft actions">
					<span class="displaying-num"><?php printf( esc_html( _n( '%s item', '%s items', $total, 'admbike-woo-locations' ) ), number_format_i18n( $total ) ); ?></span>
					<?php
					$base_url = admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::SHIPPING_SLUG );
					$args = array();
					if ( $search ) { $args['s'] = urlencode( $search ); }
					if ( $rule_type ) { $args['rule_type'] = $rule_type; }
					if ( $match_type ) { $args['match_type'] = $match_type; }
					if ( null !== $is_active ) { $args['is_active'] = $is_active; }
					if ( ! empty( $args ) ) { $base_url = add_query_arg( $args, $base_url ); }
					?>
					<?php if ( $paged > 1 ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>">«</a>
					<?php endif; ?>
					<span class="button disabled"><?php echo esc_html( $paged ); ?> / <?php echo esc_html( $total_pages ); ?></span>
					<?php if ( $paged < $total_pages ) : ?>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>">»</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
