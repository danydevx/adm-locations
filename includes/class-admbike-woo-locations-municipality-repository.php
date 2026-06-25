<?php
/**
 * Municipalities repository.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Municipality_Repository extends ADMBike_Woo_Locations_Abstract_Repository {

	/**
	 * Get table suffix.
	 *
	 * @return string
	 */
	protected function get_table_suffix() {
		return 'admbike_locations_municipalities';
	}

	/**
	 * Get column formats.
	 *
	 * @return array<string, string>
	 */
	protected function get_column_formats() {
		return array(
			'state_id'        => '%d',
			'name'            => '%s',
			'normalized_name' => '%s',
			'is_active'       => '%d',
			'created_at'      => '%s',
			'updated_at'      => '%s',
		);
	}

	/**
	 * Normalize row data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_item_for_database( array $data ) {
		$name = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';

		$prepared = array(
			'state_id'        => isset( $data['state_id'] ) ? absint( $data['state_id'] ) : 0,
			'name'            => $name,
			'normalized_name' => sanitize_title( $name ),
			'is_active'       => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'updated_at'      => $this->now(),
		);

		if ( empty( $data['created_at'] ) ) {
			$prepared['created_at'] = $this->now();
		}

		return $prepared;
	}

	/**
	 * Get municipalities by state.
	 *
	 * @param int  $state_id State ID.
	 * @param bool $active_only Filter active rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_state( $state_id, $active_only = true ) {
		$where = array( 'state_id' => absint( $state_id ) );

		if ( $active_only ) {
			$where['is_active'] = 1;
		}

		return $this->get_items( $where, 'name ASC' );
	}

	/**
	 * Get a municipality by state and normalized name.
	 *
	 * @param int    $state_id State ID.
	 * @param string $name Municipality name.
	 * @param bool   $active_only Filter active rows.
	 * @return array<string, mixed>|null
	 */
	public function get_by_state_and_name( $state_id, $name, $active_only = true ) {
		$state_id = absint( $state_id );
		$term     = sanitize_title( sanitize_text_field( (string) $name ) );

		if ( $state_id <= 0 || '' === $term ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE state_id = %d AND normalized_name = %s",
			$state_id,
			$term
		);

		if ( $active_only ) {
			$sql .= ' AND is_active = 1';
		}

		$sql .= ' LIMIT 1';

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Get a municipality by normalized name.
	 *
	 * @param string $name Municipality name.
	 * @param bool   $active_only Filter active rows.
	 * @return array<string, mixed>|null
	 */
	public function get_by_name( $name, $active_only = true ) {
		$term = sanitize_title( sanitize_text_field( (string) $name ) );

		if ( '' === $term ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE normalized_name = %s",
			$term
		);

		if ( $active_only ) {
			$sql .= ' AND is_active = 1';
		}

		$sql .= ' ORDER BY state_id ASC LIMIT 1';

		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Count municipalities by state.
	 *
	 * @param int $state_id State ID.
	 * @return int
	 */
	public function count_by_state( $state_id ) {
		return $this->count( array( 'state_id' => absint( $state_id ) ) );
	}

	/**
	 * Get paginated municipalities.
	 *
	 * @param string $order_by Order by clause.
	 * @param int $per_page Items per page.
	 * @param int $page Page number.
	 * @param string $search Search term.
	 * @param int|null $state_id Filter by state.
	 * @param int|null $is_active Filter by active status.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( $order_by = 'name ASC', $per_page = 20, $page = 1, $search = '', $state_id = null, $is_active = null ) {
		$page   = max( 1, absint( $page ) );
		$offset = ( $page - 1 ) * $per_page;
		$term   = sanitize_text_field( (string) $search );

		if ( '' !== $term ) {
			$extra = array();
			if ( null !== $state_id ) {
				$extra['state_id'] = absint( $state_id );
			}

			return $this->search_by_column( 'name', $term, $extra, $order_by, $per_page, $offset );
		}

		$where = array();
		if ( null !== $state_id ) {
			$where['state_id'] = absint( $state_id );
		}
		if ( null !== $is_active ) {
			$where['is_active'] = (int) (bool) $is_active;
		}

		$where['_offset'] = $offset;

		return $this->get_items( $where, $order_by, $per_page );
	}

	/**
	 * Count municipalities.
	 *
	 * @param string $search Search term.
	 * @param int|null $state_id Filter by state.
	 * @param int|null $is_active Filter by active status.
	 * @return int
	 */
	public function count_all( $search = '', $state_id = null, $is_active = null ) {
		$term = sanitize_text_field( (string) $search );

		if ( '' !== $term ) {
			$sql = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE name LIKE %s",
				'%' . $this->wpdb->esc_like( $term ) . '%'
			);

			if ( null !== $state_id ) {
				$sql .= $this->wpdb->prepare( ' AND state_id = %d', absint( $state_id ) );
			}
			if ( null !== $is_active ) {
				$sql .= $this->wpdb->prepare( ' AND is_active = %d', (int) (bool) $is_active );
			}

			return (int) $this->wpdb->get_var( $sql );
		}

		$where = array();
		if ( null !== $state_id ) {
			$where['state_id'] = absint( $state_id );
		}
		if ( null !== $is_active ) {
			$where['is_active'] = (int) (bool) $is_active;
		}

		return $this->count( $where );
	}
}
