<?php
/**
 * States repository.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_State_Repository extends ADMBike_Woo_Locations_Abstract_Repository {

	/**
	 * Get table suffix.
	 *
	 * @return string
	 */
	protected function get_table_suffix() {
		return 'admbike_locations_states';
	}

	/**
	 * Get column formats.
	 *
	 * @return array<string, string>
	 */
	protected function get_column_formats() {
		return array(
			'code'       => '%s',
			'name'       => '%s',
			'is_active'  => '%d',
			'created_at' => '%s',
			'updated_at' => '%s',
		);
	}

	/**
	 * Normalize row data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_item_for_database( array $data ) {
		$prepared = array(
			'code'      => isset( $data['code'] ) ? strtoupper( sanitize_text_field( (string) $data['code'] ) ) : '',
			'name'      => isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '',
			'is_active' => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'updated_at' => $this->now(),
		);

		if ( empty( $data['created_at'] ) ) {
			$prepared['created_at'] = $this->now();
		}

		return $prepared;
	}

	/**
	 * Get all active states.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_active_states() {
		return $this->get_items( array( 'is_active' => 1 ), 'name ASC' );
	}

	/**
	 * Get a state by code.
	 *
	 * @param string $code State code.
	 * @return array<string, mixed>|null
	 */
	public function get_by_code( $code ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE code = %s LIMIT 1",
			strtoupper( sanitize_text_field( $code ) )
		);

		$row = $this->wpdb->get_row( $query, ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Get paginated states.
	 *
	 * @param string $order_by Order by clause.
	 * @param int $per_page Items per page.
	 * @param int $page Page number.
	 * @param string $search Search term.
	 * @param int|null $is_active Filter by active status.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( $order_by = 'name ASC', $per_page = 20, $page = 1, $search = '', $is_active = null ) {
		$page    = max( 1, absint( $page ) );
		$offset  = ( $page - 1 ) * $per_page;
		$term    = sanitize_text_field( (string) $search );

		if ( '' !== $term ) {
			return $this->search_by_column( 'name', $term, array(), $order_by, $per_page, $offset );
		}

		$where = array();
		if ( null !== $is_active ) {
			$where['is_active'] = (int) (bool) $is_active;
		}

		$where['_offset'] = $offset;

		return $this->get_items( $where, $order_by, $per_page );
	}

	/**
	 * Count states.
	 *
	 * @param string $search Search term.
	 * @param int|null $is_active Filter by active status.
	 * @return int
	 */
	public function count_all( $search = '', $is_active = null ) {
		$term = sanitize_text_field( (string) $search );

		if ( '' !== $term ) {
			$sql = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE name LIKE %s",
				'%' . $this->wpdb->esc_like( $term ) . '%'
			);

			if ( null !== $is_active ) {
				$sql .= $this->wpdb->prepare( ' AND is_active = %d', (int) (bool) $is_active );
			}

			return (int) $this->wpdb->get_var( $sql );
		}

		$where = array();
		if ( null !== $is_active ) {
			$where['is_active'] = (int) (bool) $is_active;
		}

		return $this->count( $where );
	}
}
