<?php
/**
 * Postal codes admin view.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$admin = admbike_woo_locations_admin();

$states_repo = new ADMBike_Woo_Locations_State_Repository();
$muni_repo   = new ADMBike_Woo_Locations_Municipality_Repository();
$pc_repo     = new ADMBike_Woo_Locations_Postcode_Repository();

$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

if ( 'add' === $action || 'edit' === $action ) {
	$item = null;
	if ( 'edit' === $action && $id > 0 ) {
		$item = $pc_repo->get_by_id( $id );
		if ( ! $item ) {
			wp_die( esc_html__( 'Postcode not found.', 'admbike-woo-locations' ) );
		}
	}

	$municipalities = array();
	if ( 'edit' === $action && $item ) {
		$municipalities = $muni_repo->get_by_state( $item['state_id'], false );
	}

	$states = $states_repo->get_items( array( 'is_active' => 1 ), 'name ASC' );
	if ( empty( $states ) ) {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Add Postal Code', 'admbike-woo-locations' ); ?></h1>
			<hr class="wp-header-end">
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'You must add at least one active state and municipality before adding postal codes.', 'admbike-woo-locations' ); ?></p>
			</div>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::POSTCODES_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Back to list', 'admbike-woo-locations' ); ?></a></p>
		</div>
		<?php
		return;
	}

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['admbike_pc_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['admbike_pc_nonce'] ) ), 'admbike_save_postcode' ) || ! current_user_can( ADMBike_Woo_Locations_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'Security check failed.', 'admbike-woo-locations' ) );
		}

		$municipality_id = isset( $_POST['municipality_id'] ) ? absint( $_POST['municipality_id'] ) : 0;
		$postcode        = isset( $_POST['postcode'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $_POST['postcode'] ) : '';
		$active          = isset( $_POST['is_active'] ) ? (int) (bool) $_POST['is_active'] : 0;

		if ( empty( $municipality_id ) || empty( $postcode ) ) {
			$error_msg = __( 'Municipality and Postal Code are required.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/postcodes-form.php';
			return;
		}

		$municipality = $muni_repo->get_by_id( $municipality_id );
		if ( ! $municipality ) {
			$error_msg = __( 'Selected municipality does not exist.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/postcodes-form.php';
			return;
		}

		$existing = $pc_repo->get_items( array( 'municipality_id' => $municipality_id, 'postcode' => $postcode ), 'id ASC', 1 );
		if ( ! empty( $existing ) && ( 'add' === $_POST['_action'] || (int) $existing[0]['id'] !== $id ) ) {
			$error_msg = __( 'This postal code already exists in the selected municipality.', 'admbike-woo-locations' );
			include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/postcodes-form.php';
			return;
		}

		$data = array(
			'state_id'        => $municipality['state_id'],
			'municipality_id' => $municipality_id,
			'postcode'        => $postcode,
			'is_active'       => $active,
		);

		if ( 'add' === $_POST['_action'] ) {
			$result = $pc_repo->create( $data );
			if ( $result ) {
				$admin->redirect_with_message( 'success', urlencode( __( 'Postal code created successfully.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::POSTCODES_SLUG ) );
			} else {
				$error_msg = __( 'Failed to create postal code.', 'admbike-woo-locations' );
			}
		} else {
			$result = $pc_repo->update( $id, $data );
			if ( $result ) {
				$admin->redirect_with_message( 'success', urlencode( __( 'Postal code updated successfully.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::POSTCODES_SLUG ) );
			} else {
				$error_msg = __( 'Failed to update postal code.', 'admbike-woo-locations' );
			}
		}

		include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/postcodes-form.php';
		return;
	}

	include ADMBIKE_WOO_LOCATIONS_PATH . 'admin/views/postcodes-form.php';
	return;
}

if ( 'delete' === $action ) {
	if ( ! $admin->verify_nonce( 'admbike_delete_postcode' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'admbike-woo-locations' ) );
	}

	$result = $pc_repo->delete( $id );
	$admin->redirect_with_message( $result ? 'success' : 'error', urlencode( $result ? __( 'Postal code deleted.', 'admbike-woo-locations' ) : __( 'Failed to delete postal code.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::POSTCODES_SLUG ) );
	return;
}

if ( 'toggle' === $action ) {
	if ( ! $admin->verify_nonce( 'admbike_toggle_postcode' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'admbike-woo-locations' ) );
	}

	$item = $pc_repo->get_by_id( $id );
	if ( $item ) {
		$pc_repo->update( $id, array( 'is_active' => $item['is_active'] ? 0 : 1 ) );
	}

	$admin->redirect_with_message( 'success', urlencode( __( 'Status updated.', 'admbike-woo-locations' ) ), array( 'page' => ADMBike_Woo_Locations_Admin::POSTCODES_SLUG ) );
	return;
}

$admin->render_admin_messages();

$paged          = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$state_id       = isset( $_GET['state_id'] ) ? absint( $_GET['state_id'] ) : 0;
$municipality_id = isset( $_GET['municipality_id'] ) ? absint( $_GET['municipality_id'] ) : 0;
$is_active      = isset( $_GET['is_active'] ) ? ( '' !== $_GET['is_active'] ? (int) (bool) $_GET['is_active'] : null ) : null;
$per_page       = 20;

$total  = $pc_repo->count_all( $search, $municipality_id ?: null, $state_id ?: null, $is_active );
$items  = $pc_repo->get_paginated( 'postcode ASC', $per_page, $paged, $search, $municipality_id ?: null, $state_id ?: null, $is_active );
$total_pages = max( 1, ceil( $total / $per_page ) );

$all_states       = $states_repo->get_items( array(), 'name ASC' );
$all_municipalities = $muni_repo->get_items( array( 'is_active' => 1 ), 'name ASC' );

$state_map = array();
foreach ( $all_states as $s ) {
	$state_map[ $s['id'] ] = $s['name'];
}

$muni_map = array();
foreach ( $all_municipalities as $m ) {
	$muni_map[ $m['id'] ] = array(
		'name'      => $m['name'],
		'state_id'  => $m['state_id'],
	);
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Postal Codes', 'admbike-woo-locations' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::POSTCODES_SLUG . '&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'admbike-woo-locations' ); ?></a>
	<hr class="wp-header-end">

	<form method="get" action="">
		<input type="hidden" name="page" value="<?php echo esc_attr( ADMBike_Woo_Locations_Admin::POSTCODES_SLUG ); ?>">
		<p class="search-box">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by postal code…', 'admbike-woo-locations' ); ?>">
			<select name="state_id" id="filter_state_id" style="vertical-align:middle;">
				<option value=""><?php esc_html_e( 'All states', 'admbike-woo-locations' ); ?></option>
				<?php foreach ( $all_states as $s ) : ?>
					<option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $state_id, $s['id'] ); ?>><?php echo esc_html( $s['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="municipality_id" id="filter_municipality_id" style="vertical-align:middle;">
				<option value=""><?php esc_html_e( 'All municipalities', 'admbike-woo-locations' ); ?></option>
				<?php foreach ( $all_municipalities as $m ) : ?>
					<option value="<?php echo esc_attr( $m['id'] ); ?>" data-state="<?php echo esc_attr( $m['state_id'] ); ?>" <?php selected( $municipality_id, $m['id'] ); ?>><?php echo esc_html( $m['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="is_active" style="vertical-align:middle;">
				<option value=""><?php esc_html_e( 'All statuses', 'admbike-woo-locations' ); ?></option>
				<option value="1" <?php selected( 1, $is_active ); ?>><?php esc_html_e( 'Active', 'admbike-woo-locations' ); ?></option>
				<option value="0" <?php selected( 0, $is_active ); ?>><?php esc_html_e( 'Inactive', 'admbike-woo-locations' ); ?></option>
			</select>
			<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'admbike-woo-locations' ); ?>">
			<?php if ( $search || $state_id || $municipality_id || null !== $is_active ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::POSTCODES_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'admbike-woo-locations' ); ?></a>
			<?php endif; ?>
		</p>
	</form>

	<?php if ( empty( $items ) ) : ?>
		<p><?php esc_html_e( 'No postal codes found.', 'admbike-woo-locations' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" style="width:60px;"><?php esc_html_e( 'ID', 'admbike-woo-locations' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Postal Code', 'admbike-woo-locations' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Municipality', 'admbike-woo-locations' ); ?></th>
					<th scope="col"><?php esc_html_e( 'State', 'admbike-woo-locations' ); ?></th>
					<th scope="col" style="width:100px;"><?php esc_html_e( 'Status', 'admbike-woo-locations' ); ?></th>
					<th scope="col" style="width:160px;"><?php esc_html_e( 'Actions', 'admbike-woo-locations' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $items as $item ) :
					$edit_url   = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::POSTCODES_SLUG . '&action=edit&id=' . absint( $item['id'] ) ),
						'admbike_edit_postcode_' . $item['id'],
						'_wpnonce'
					);
					$delete_url = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::POSTCODES_SLUG . '&action=delete&id=' . absint( $item['id'] ) ),
						'admbike_delete_postcode',
						'_wpnonce'
					);
					$toggle_url = wp_nonce_url(
						admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::POSTCODES_SLUG . '&action=toggle&id=' . absint( $item['id'] ) ),
						'admbike_toggle_postcode',
						'_wpnonce'
					);

					$muni_info  = isset( $muni_map[ $item['municipality_id'] ] ) ? $muni_map[ $item['municipality_id'] ] : null;
					$state_name = $muni_info && isset( $state_map[ $muni_info['state_id'] ] ) ? $state_map[ $muni_info['state_id'] ] : '—';
					$muni_name  = $muni_info ? $muni_info['name'] : '—';
					?>
					<tr>
						<td><?php echo esc_html( $item['id'] ); ?></td>
						<td><strong><?php echo esc_html( $item['postcode'] ); ?></strong></td>
						<td><?php echo esc_html( $muni_name ); ?></td>
						<td><?php echo esc_html( $state_name ); ?></td>
						<td>
							<?php if ( $item['is_active'] ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color:#2271b1;" title="<?php esc_attr_e( 'Active', 'admbike-woo-locations' ); ?>"></span>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color:#d63638;" title="<?php esc_attr_e( 'Inactive', 'admbike-woo-locations' ); ?>"></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'admbike-woo-locations' ); ?></a>
							<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small"><?php echo esc_html( $item['is_active'] ? __( 'Deactivate', 'admbike-woo-locations' ) : __( 'Activate', 'admbike-woo-locations' ) ); ?></a>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" style="color:#d63638;" onclick="return confirm('<?php esc_attr_e( 'Delete this postal code?', 'admbike-woo-locations' ); ?>');"><?php esc_html_e( 'Delete', 'admbike-woo-locations' ); ?></a>
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
					$base_url = admin_url( 'admin.php?page=' . ADMBike_Woo_Locations_Admin::POSTCODES_SLUG );
					$args = array();
					if ( $search ) {
						$args['s'] = urlencode( $search );
					}
					if ( $state_id ) {
						$args['state_id'] = $state_id;
					}
					if ( $municipality_id ) {
						$args['municipality_id'] = $municipality_id;
					}
					if ( null !== $is_active ) {
						$args['is_active'] = $is_active;
					}
					if ( ! empty( $args ) ) {
						$base_url = add_query_arg( $args, $base_url );
					}
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
