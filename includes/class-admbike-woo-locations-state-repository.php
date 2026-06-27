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
	 * Whether the table supports the coverage mode column.
	 *
	 * @var bool|null
	 */
	protected $supports_coverage_mode = null;

	/**
	 * Get table suffix.
	 *
	 * @return string
	 */
	protected function get_table_suffix() {
		return 'orpot_woo_locations_states';
	}

	/**
	 * Get column formats.
	 *
	 * @return array<string, string>
	 */
	protected function get_column_formats() {
		return array(
			'code'                  => '%s',
			'name'                  => '%s',
			'postcode_coverage_mode' => '%s',
			'postcode_coverage'      => '%s',
			'is_active'             => '%d',
			'created_at'            => '%s',
			'updated_at'            => '%s',
		);
	}

	/**
	 * Normalize row data.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function prepare_item_for_database( array $data ) {
		$coverage      = $this->normalize_postcode_coverage( isset( $data['postcode_coverage'] ) ? (string) $data['postcode_coverage'] : '' );
		$coverage_mode = $this->normalize_postcode_coverage_mode( isset( $data['postcode_coverage_mode'] ) ? (string) $data['postcode_coverage_mode'] : '' );

		if ( '' !== $coverage && '' === $coverage_mode ) {
			$coverage_mode = 'range';
		}

		$prepared = array(
			'code'                  => isset( $data['code'] ) ? strtoupper( sanitize_text_field( (string) $data['code'] ) ) : '',
			'name'                  => isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '',
			'postcode_coverage_mode' => $coverage_mode,
			'postcode_coverage'      => $coverage,
			'is_active'             => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
			'updated_at'            => $this->now(),
		);

		if ( ! $this->supports_coverage_mode() ) {
			unset( $prepared['postcode_coverage_mode'] );
		}

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
	 * Normalize coverage mode.
	 *
	 * @param string $mode Coverage mode.
	 * @return string
	 */
	public function normalize_postcode_coverage_mode( $mode ) {
		$mode = sanitize_key( (string) $mode );

		if ( '' === $mode ) {
			return '';
		}

		return in_array( $mode, array( 'range', 'list' ), true ) ? $mode : '';
	}

	/**
	 * Normalize a list or range coverage string.
	 *
	 * @param string $coverage Coverage input.
	 * @return string
	 */
	public function normalize_postcode_coverage( $coverage ) {
		$coverage = sanitize_textarea_field( (string) $coverage );
		if ( '' === trim( $coverage ) ) {
			return '';
		}

		$segments = preg_split( '/\s*,\s*/', trim( $coverage ) );
		if ( ! is_array( $segments ) ) {
			return '';
		}

		$normalized = array();

		foreach ( $segments as $segment ) {
			$segment = trim( (string) $segment );
			if ( '' === $segment ) {
				continue;
			}

			if ( preg_match( '/^(\d{1,10})\s*[-–]\s*(\d{1,10})$/u', $segment, $matches ) ) {
				$from = (int) $matches[1];
				$to   = (int) $matches[2];
				if ( $from > $to ) {
					$tmp  = $from;
					$from = $to;
					$to   = $tmp;
				}
				$normalized[] = sprintf( '%05d-%05d', $from, $to );
				continue;
			}

			if ( preg_match( '/^\d{1,10}$/', $segment ) ) {
				$normalized[] = sprintf( '%05d', (int) $segment );
			}
		}

		$normalized = array_values( array_unique( $normalized ) );
		sort( $normalized, SORT_NATURAL );

		return implode( ', ', $normalized );
	}

	/**
	 * Normalize a postcode for comparisons.
	 *
	 * @param string $postcode Postcode input.
	 * @return string
	 */
	public function normalize_postcode( $postcode ) {
		$code = preg_replace( '/[^0-9]/', '', (string) $postcode );

		return substr( (string) $code, 0, 5 );
	}

	/**
	 * Check whether a postcode matches a normalized coverage string.
	 *
	 * @param string $postcode Postcode.
	 * @param string $coverage Coverage string.
	 * @param string $mode Coverage mode.
	 * @return bool
	 */
	public function postcode_matches_coverage( $postcode, $coverage, $mode = 'range' ) {
		$postcode = $this->normalize_postcode( $postcode );
		$coverage = $this->normalize_postcode_coverage( $coverage );

		if ( '' === $postcode || '' === $coverage ) {
			return false;
		}

		$segments = preg_split( '/\s*,\s*/', $coverage );
		if ( ! is_array( $segments ) ) {
			return false;
		}

		foreach ( $segments as $segment ) {
			$segment = trim( (string) $segment );
			if ( '' === $segment ) {
				continue;
			}

			if ( preg_match( '/^(\d{5})-(\d{5})$/', $segment, $matches ) ) {
				if ( $postcode >= $matches[1] && $postcode <= $matches[2] ) {
					return true;
				}
				continue;
			}

			if ( preg_match( '/^\d{5}$/', $segment ) && $segment === $postcode ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get a state by code or normalized name.
	 *
	 * @param string $value State code or name.
	 * @return array<string, mixed>|null
	 */
	public function get_by_code_or_name( $value ) {
		$term = sanitize_title( sanitize_text_field( (string) $value ) );

		if ( '' === $term ) {
			return null;
		}

		$states = $this->get_active_states();
		foreach ( $states as $state ) {
			$code = isset( $state['code'] ) ? strtoupper( sanitize_text_field( (string) $state['code'] ) ) : '';
			$name = isset( $state['name'] ) ? sanitize_title( sanitize_text_field( (string) $state['name'] ) ) : '';

			if ( $code === strtoupper( sanitize_text_field( (string) $value ) ) || $name === $term ) {
				return $state;
			}
		}

		return null;
	}

	/**
	 * Get postcode locations for a state coverage string.
	 *
	 * @param int  $state_id State ID.
	 * @param bool $active_only Filter active rows.
	 * @return array<int, array<string, string>>
	 */
	public function get_coverage_locations( $state_id, $active_only = true ) {
		$state = $this->get_by_id( absint( $state_id ) );
		if ( ! $state ) {
			return array();
		}

		if ( $active_only && empty( $state['is_active'] ) ) {
			return array();
		}

		$coverage = $this->normalize_postcode_coverage( (string) ( $state['postcode_coverage'] ?? '' ) );
		if ( '' === $coverage ) {
			return array();
		}

		$locations = array();
		$segments  = preg_split( '/\s*,\s*/', $coverage );

		if ( ! is_array( $segments ) ) {
			return array();
		}

		foreach ( $segments as $segment ) {
			$segment = trim( (string) $segment );
			if ( '' === $segment ) {
				continue;
			}

			if ( preg_match( '/^(\d{5})-(\d{5})$/', $segment, $matches ) ) {
				$locations[] = array(
					'type' => 'postcode',
					'code' => $matches[1] . '...' . $matches[2],
				);
				continue;
			}

			if ( preg_match( '/^\d{5}$/', $segment ) ) {
				$locations[] = array(
					'type' => 'postcode',
					'code' => $segment,
				);
			}
		}

		return $locations;
	}

	/**
	 * Check whether the state table has the coverage mode column.
	 *
	 * @return bool
	 */
	protected function supports_coverage_mode() {
		if ( null !== $this->supports_coverage_mode ) {
			return (bool) $this->supports_coverage_mode;
		}

		$column = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW COLUMNS FROM ' . $this->table_name . ' LIKE %s',
				'postcode_coverage_mode'
			)
		);

		$this->supports_coverage_mode = ! empty( $column );

		return (bool) $this->supports_coverage_mode;
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
