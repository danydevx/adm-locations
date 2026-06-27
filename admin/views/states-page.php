<?php
/**
 * States admin view.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$admin = orpot_woo_locations_admin();

$states_repo = new ADMBike_Woo_Locations_State_Repository();

$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

if ( 'add' === $action || 'edit' === $action ) {
	$item = null;
	if ( 'edit' === $action && $id > 0 ) {
		$item = $states_repo->get_by_id( $id );
		if ( ! $item ) {
			wp_die( esc_html__( 'No se encontró el estado.', 'admbike-woo-locations' ) );
		}
	}
	?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( 'edit' === $action ? __( 'Edit State', 'admbike-woo-locations' ) : __( 'Add State', 'admbike-woo-locations' ) ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG ) ); ?>" class="page-title-action"><?php esc_html_e( 'Volver al listado', 'admbike-woo-locations' ); ?></a>
	<hr class="wp-header-end">

	<?php
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['orpot_woo_locations_state_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['orpot_woo_locations_state_nonce'] ) ), 'orpot_woo_locations_save_state' ) || ! current_user_can( ADMBike_Woo_Locations_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Falló la verificación de seguridad.', 'admbike-woo-locations' ) );
		}

		$post = wp_unslash( $_POST );
		$action = isset( $post['_action'] ) ? sanitize_key( (string) $post['_action'] ) : '';
		$code = isset( $post['code'] ) ? strtoupper( sanitize_text_field( (string) $post['code'] ) ) : '';
		$name = isset( $post['name'] ) ? sanitize_text_field( (string) $post['name'] ) : '';
		$coverage_mode = isset( $post['postcode_coverage_mode'] ) ? sanitize_key( (string) $post['postcode_coverage_mode'] ) : '';
		$coverage_mode = in_array( $coverage_mode, array( 'range', 'list' ), true ) ? $coverage_mode : '';
		$postcode_from = isset( $post['postcode_from'] ) ? preg_replace( '/[^0-9]/', '', (string) $post['postcode_from'] ) : '';
		$postcode_to   = isset( $post['postcode_to'] ) ? preg_replace( '/[^0-9]/', '', (string) $post['postcode_to'] ) : '';
		$postcode_list = isset( $post['postcode_coverage_list'] ) ? sanitize_textarea_field( (string) $post['postcode_coverage_list'] ) : '';
		$active = isset( $post['is_active'] ) ? (int) (bool) $post['is_active'] : 0;

		if ( empty( $code ) || empty( $name ) ) {
			$error_msg = __( 'Se requieren el código y el nombre.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/states-form.php';
			return;
		}

		if ( '' !== $coverage_mode && 'range' === $coverage_mode && ( '' === $postcode_from || '' === $postcode_to ) ) {
			$error_msg = __( 'La cobertura por rango requiere un código postal inicial y uno final.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/states-form.php';
			return;
		}

		if ( '' !== $coverage_mode && 'list' === $coverage_mode && '' === trim( $postcode_list ) ) {
			$error_msg = __( 'La cobertura por lista requiere al menos un código postal.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/states-form.php';
			return;
		}

		$postcode_coverage = 'range' === $coverage_mode
			? ( function () use ( $postcode_from, $postcode_to ) {
				$from = absint( $postcode_from );
				$to   = absint( $postcode_to );

				if ( $from > $to ) {
					$tmp  = $from;
					$from = $to;
					$to   = $tmp;
				}

				return sprintf( '%05d-%05d', $from, $to );
			} )()
			: ( 'list' === $coverage_mode ? $states_repo->normalize_postcode_list( $postcode_list ) : '' );

		if ( '' !== $coverage_mode && '' === $postcode_coverage ) {
			$error_msg = __( 'No se pudo normalizar la cobertura postal. Ingresa códigos postales válidos de 5 dígitos.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/states-form.php';
			return;
		}

		$existing_code = $states_repo->get_by_code( $code );
		if ( $existing_code && ( 'add' === $action || ( 'edit' === $action && (int) $existing_code['id'] !== $id ) ) ) {
			$error_msg = __( 'Ya existe un estado con este código.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/states-form.php';
			return;
		}

		$data = array(
			'code'                   => $code,
			'name'                   => $name,
			'postcode_coverage_mode'  => $coverage_mode,
			'postcode_coverage'       => $postcode_coverage,
			'is_active'              => $active,
		);

		if ( 'add' === $action ) {
			$result = $states_repo->create( $data );
			if ( $result ) {
				$admin->redirect_with_message( 'success', urlencode( __( 'Estado creado correctamente.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::STATES_SLUG ) );
			} else {
				$error_msg = __( 'No se pudo crear el estado.', 'admbike-woo-locations' );
			}
		} else {
			$result = $states_repo->update( $id, $data );
			if ( $result ) {
				$admin->redirect_with_message( 'success', urlencode( __( 'Estado actualizado correctamente.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::STATES_SLUG ) );
			} else {
				$error_msg = __( 'No se pudo actualizar el estado.', 'admbike-woo-locations' );
			}
		}

		include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/states-form.php';
		return;
	}

	include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/states-form.php';
	return;
}

if ( 'delete' === $action ) {
	if ( ! $admin->verify_nonce( 'orpot_woo_locations_delete_state' ) ) {
		wp_die( esc_html__( 'Falló la verificación de seguridad.', 'admbike-woo-locations' ) );
	}

	$result = $states_repo->delete( $id );
	$admin->redirect_with_message( $result ? 'success' : 'error', urlencode( $result ? __( 'Estado eliminado.', 'admbike-woo-locations' ) : __( 'No se pudo eliminar el estado.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::STATES_SLUG ) );
	return;
}

if ( 'toggle' === $action ) {
	if ( ! $admin->verify_nonce( 'orpot_woo_locations_toggle_state' ) ) {
		wp_die( esc_html__( 'Falló la verificación de seguridad.', 'admbike-woo-locations' ) );
	}

	$item = $states_repo->get_by_id( $id );
	if ( $item ) {
		$states_repo->update( $id, array( 'is_active' => $item['is_active'] ? 0 : 1 ) );
	}

	$admin->redirect_with_message( 'success', urlencode( __( 'Estado actualizado.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::STATES_SLUG ) );
	return;
}

$admin->render_admin_messages();

$paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$per_page = 20;

$total  = $states_repo->count_all( $search );
$items  = $states_repo->get_paginated( 'name ASC', $per_page, $paged, $search );
$total_pages = max( 1, ceil( $total / $per_page ) );
$woocommerce_mx_states = ADMBike_Woo_Locations_Admin::get_woocommerce_mx_states();

?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( 'Estados' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG . '&action=add' ) ); ?>" class="page-title-action"><?php echo esc_html( 'Agregar estado' ); ?></a>
	<hr class="wp-header-end">

	<div class="notice notice-info inline admbike-help-card">
		<h2><?php echo esc_html( 'Códigos de estados MX de WooCommerce' ); ?></h2>
		<p>
			<?php echo esc_html( 'Usa el código exacto de WooCommerce en el campo Código del Estado. Esto es necesario para que Checkout Blocks relacione correctamente los estados con tus municipios.' ); ?>
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

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( ADMBike_Woo_Locations_Admin::STATES_SLUG ); ?>">
		<p class="search-box">
			<label for="post-search-input"><?php echo esc_html( 'Buscar estados:' ); ?></label>
			<input type="search" id="post-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
			<input type="submit" class="button" value="<?php echo esc_attr( 'Buscar' ); ?>">
			<?php if ( $search ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG ) ); ?>" class="button"><?php echo esc_html( 'Limpiar' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<?php if ( empty( $items ) ) : ?>
		<p><?php echo esc_html( 'No se encontraron estados.' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="column-id" style="width:60px;"><?php echo esc_html( 'ID' ); ?></th>
					<th scope="col" class="column-code"><?php echo esc_html( 'Código' ); ?></th>
					<th scope="col" class="column-name"><?php echo esc_html( 'Nombre' ); ?></th>
					<th scope="col" class="column-coverage"><?php echo esc_html( 'Cobertura' ); ?></th>
					<th scope="col" class="column-status" style="width:100px;"><?php echo esc_html( 'Estado' ); ?></th>
					<th scope="col" class="column-actions" style="width:160px;"><?php echo esc_html( 'Acciones' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $items as $item ) :
					$edit_url   = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG . '&action=edit&id=' . absint( $item['id'] ) ),
						'orpot_woo_locations_edit_state_' . $item['id'],
						'_wpnonce'
					);
					$delete_url = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG . '&action=delete&id=' . absint( $item['id'] ) ),
						'orpot_woo_locations_delete_state',
						'_wpnonce'
					);
					$toggle_url = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG . '&action=toggle&id=' . absint( $item['id'] ) ),
						'orpot_woo_locations_toggle_state',
						'_wpnonce'
					);
					?>
					<tr>
						<td class="column-id"><?php echo esc_html( $item['id'] ); ?></td>
						<td class="column-code"><strong><?php echo esc_html( $item['code'] ); ?></strong></td>
						<td class="column-name"><?php echo esc_html( $item['name'] ); ?></td>
						<td class="column-coverage">
								<?php
								$coverage_mode = ! empty( $item['postcode_coverage_mode'] ) ? $item['postcode_coverage_mode'] : '';
								$coverage      = (string) ( $item['postcode_coverage'] ?? '' );
								if ( '' === $coverage ) {
									echo esc_html( 'Sin límite / solo estado' );
								} else {
									echo esc_html( ucfirst( (string) $coverage_mode ) . ': ' . $coverage );
								}
								?>
						</td>
						<td class="column-status">
							<?php if ( $item['is_active'] ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color:#2271b1;" title="<?php echo esc_attr( 'Activo' ); ?>"></span>
								<?php else : ?>
									<span class="dashicons dashicons-dismiss" style="color:#d63638;" title="<?php echo esc_attr( 'Inactivo' ); ?>"></span>
								<?php endif; ?>
						</td>
						<td class="column-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php echo esc_html( 'Editar' ); ?></a>
							<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small"><?php echo esc_html( $item['is_active'] ? 'Desactivar' : 'Activar' ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" style="color:#d63638;" onclick="return confirm('<?php echo esc_attr( '¿Eliminar este estado?' ); ?>');"><?php echo esc_html( 'Eliminar' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="alignleft actions">
					<?php
					$base_url = admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::STATES_SLUG );
					if ( $search ) {
						$base_url = add_query_arg( 's', urlencode( $search ), $base_url );
					}
					?>
					<span class="displaying-num"><?php printf( esc_html( _n( '%s item', '%s items', $total, 'admbike-woo-locations' ) ), number_format_i18n( $total ) ); ?></span>
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
<?php
