<?php
/**
 * Postcodes repository.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Postcode_Repository extends ADMBike_Woo_Locations_Abstract_Repository {

	/**
	 * Get table suffix.
	 *
	 * @return string
	 */
	protected function get_table_suffix() {
		return 'orpot_woo_locations_postcodes';
	}

	/**
	 * Get column formats.
	 *
	 * @return array<string, string>
	 */
	protected function get_column_formats() {
		return array(
			'state_id'         => '%d',
			'municipality_id'  => '%d',
			'postcode'         => '%s',
			'is_active'        => '%d',
			'created_at'       => '%s',
			'updated_at'       => '%s',
		);
	}

	/**
	 * Normalize row data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_item_for_database( array $data ) {
		$postcode = isset( $data['postcode'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $data['postcode'] ) : '';

		$prepared = array(
			'state_id'        => isset( $data['state_id'] ) ? absint( $data['state_id'] ) : 0,
			'municipality_id' => isset( $data['municipality_id'] ) ? absint( $data['municipality_id'] ) : 0,
			'postcode'        => (string) $postcode,
			'is_active'       => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'updated_at'      => $this->now(),
		);

		if ( empty( $data['created_at'] ) ) {
			$prepared['created_at'] = $this->now();
		}

		return $prepared;
	}

	/**
	 * Get postcodes by municipality.
	 *
	 * @param int  $municipality_id Municipality ID.
	 * @param bool $active_only Filter active rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_municipality( $municipality_id, $active_only = true ) {
		$where = array( 'municipality_id' => absint( $municipality_id ) );

		if ( $active_only ) {
			$where['is_active'] = 1;
		}

		return $this->get_items( $where, 'postcode ASC' );
	}

	/**
	 * Count postcodes by municipality.
	 *
	 * @param int $municipality_id Municipality ID.
	 * @return int
	 */
	public function count_by_municipality( $municipality_id ) {
		return $this->count( array( 'municipality_id' => absint( $municipality_id ) ) );
	}

	/**
	 * Get an exact postcode row.
	 *
	 * @param string $postcode Postcode.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_postcode( $postcode ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE postcode = %s ORDER BY municipality_id ASC",
			preg_replace( '/[^0-9A-Za-z-]/', '', (string) $postcode )
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get paginated postcodes.
	 *
	 * @param string $order_by Order by clause.
	 * @param int $per_page Items per page.
	 * @param int $page Page number.
	 * @param string $search Search term.
	 * @param int|null $municipality_id Filter by municipality.
	 * @param int|null $state_id Filter by state.
	 * @param int|null $is_active Filter by active status.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( $order_by = 'postcode ASC', $per_page = 20, $page = 1, $search = '', $municipality_id = null, $state_id = null, $is_active = null ) {
		$page   = max( 1, absint( $page ) );
		$offset = ( $page - 1 ) * $per_page;
		$term   = sanitize_text_field( (string) $search );

		if ( '' !== $term ) {
			$extra = array();
			if ( null !== $municipality_id ) {
				$extra['municipality_id'] = absint( $municipality_id );
			}
			if ( null !== $state_id ) {
				$extra['state_id'] = absint( $state_id );
			}

			return $this->search_by_column( 'postcode', $term, $extra, $order_by, $per_page, $offset );
		}

		$where = array();
		if ( null !== $municipality_id ) {
			$where['municipality_id'] = absint( $municipality_id );
		}
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
	 * Count postcodes.
	 *
	 * @param string $search Search term.
	 * @param int|null $municipality_id Filter by municipality.
	 * @param int|null $state_id Filter by state.
	 * @param int|null $is_active Filter by active status.
	 * @return int
	 */
	public function count_all( $search = '', $municipality_id = null, $state_id = null, $is_active = null ) {
		$term = sanitize_text_field( (string) $search );

		if ( '' !== $term ) {
			$sql = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE postcode LIKE %s",
				'%' . $this->wpdb->esc_like( $term ) . '%'
			);

			if ( null !== $municipality_id ) {
				$sql .= $this->wpdb->prepare( ' AND municipality_id = %d', absint( $municipality_id ) );
			}
			if ( null !== $state_id ) {
				$sql .= $this->wpdb->prepare( ' AND state_id = %d', absint( $state_id ) );
			}
			if ( null !== $is_active ) {
				$sql .= $this->wpdb->prepare( ' AND is_active = %d', (int) (bool) $is_active );
			}

			return (int) $this->wpdb->get_var( $sql );
		}

		$where = array();
		if ( null !== $municipality_id ) {
			$where['municipality_id'] = absint( $municipality_id );
		}
		if ( null !== $state_id ) {
			$where['state_id'] = absint( $state_id );
		}
		if ( null !== $is_active ) {
			$where['is_active'] = (int) (bool) $is_active;
		}

		return $this->count( $where );
	}
}
