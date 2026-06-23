<?php
/**
 * Base repository for custom tables.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class ADMBike_Woo_Locations_Abstract_Repository {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	protected $wpdb;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table_name = '';

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $database Database instance.
	 */
	public function __construct( $database = null ) {
		$this->wpdb = $database;

		if ( null === $this->wpdb ) {
			global $wpdb;

			$this->wpdb = $wpdb;
		}

		$this->table_name = $this->wpdb->prefix . $this->get_table_suffix();
	}

	/**
	 * Get table suffix.
	 *
	 * @return string
	 */
	abstract protected function get_table_suffix();

	/**
	 * Get insert/update formats.
	 *
	 * @return array<string, string>
	 */
	abstract protected function get_column_formats();

	/**
	 * Prepare item values for persistence.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	abstract protected function prepare_item_for_database( array $data );

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->table_name;
	}

	/**
	 * Get a row by ID.
	 *
	 * @param int $id Record ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		$query = $this->wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", absint( $id ) );
		$row   = $this->wpdb->get_row( $query, ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Get rows by criteria.
	 *
	 * @param array<string, mixed> $where Where conditions.
	 * @param string               $order_by Order by clause.
	 * @param int                  $limit Results limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_items( array $where = array(), $order_by = 'id ASC', $limit = 0 ) {
		$sql          = "SELECT * FROM {$this->table_name}";
		$where_parts  = array();
		$where_values = array();

		foreach ( $where as $column => $value ) {
			$where_parts[]  = sanitize_key( $column ) . ' = %s';
			$where_values[] = (string) $value;
		}

		if ( ! empty( $where_parts ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_parts );
		}

		$allowed_order = array( 'id', 'name', 'code', 'postcode', 'created_at', 'updated_at', 'priority' );
		$order_by_safe = $this->sanitize_order_by( $order_by, $allowed_order, 'id ASC' );

		$sql .= ' ORDER BY ' . $order_by_safe;

		if ( $limit > 0 ) {
			$offset = 0;
			if ( isset( $where['_offset'] ) ) {
				$offset = absint( $where['_offset'] );
				unset( $where['_offset'] );
			}
			$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $limit ), $offset );
		}

		if ( ! empty( $where_values ) ) {
			$sql = $this->wpdb->prepare( $sql, $where_values );
		}

		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get paginated rows.
	 *
	 * @param array<string, mixed> $where Where conditions.
	 * @param string               $order_by Order by clause.
	 * @param int                  $per_page Items per page.
	 * @param int                  $page Page number (1-based).
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( array $where = array(), $order_by = 'id ASC', $per_page = 20, $page = 1 ) {
		$page    = max( 1, absint( $page ) );
		$offset  = ( $page - 1 ) * $per_page;
		$where['_offset'] = $offset;

		return $this->get_items( $where, $order_by, absint( $per_page ) );
	}

	/**
	 * Count rows matching criteria.
	 *
	 * @param array<string, mixed> $where Where conditions.
	 * @return int
	 */
	public function count( array $where = array() ) {
		$sql          = "SELECT COUNT(*) FROM {$this->table_name}";
		$where_parts  = array();
		$where_values = array();

		foreach ( $where as $column => $value ) {
			if ( '_search' === $column ) {
				continue;
			}
			$where_parts[]  = sanitize_key( $column ) . ' = %s';
			$where_values[] = (string) $value;
		}

		if ( ! empty( $where_parts ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_parts );
		}

		if ( ! empty( $where_values ) ) {
			$sql = $this->wpdb->prepare( $sql, $where_values );
		}

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Search rows by a text term on a given column.
	 *
	 * @param string $column Column to search.
	 * @param string $term Search term.
	 * @param array<string, mixed> $extra_where Additional where conditions.
	 * @param string $order_by Order by clause.
	 * @param int $limit Results limit.
	 * @param int $offset Results offset.
	 * @return array<int, array<string, mixed>>
	 */
	protected function search_by_column( $column, $term, array $extra_where = array(), $order_by = 'id ASC', $limit = 0, $offset = 0 ) {
		$column = sanitize_key( $column );
		$term   = sanitize_text_field( (string) $term );

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE {$column} LIKE %s",
			'%' . $this->wpdb->esc_like( $term ) . '%'
		);

		foreach ( $extra_where as $col => $val ) {
			$sql .= $this->wpdb->prepare( ' AND ' . sanitize_key( $col ) . ' = %s', (string) $val );
		}

		$allowed_order = array( 'id', 'name', 'code', 'postcode', 'created_at', 'updated_at', 'priority' );
		$order_by_safe = $this->sanitize_order_by( $order_by, $allowed_order, 'id ASC' );

		$sql .= ' ORDER BY ' . $order_by_safe;

		if ( $limit > 0 ) {
			$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $limit ), absint( $offset ) );
		}

		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert a row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|false
	 */
	public function create( array $data ) {
		$data = $this->prepare_item_for_database( $data );

		$result = $this->wpdb->insert( $this->table_name, $data, $this->get_formats_for_data( $data ) );

		if ( false === $result ) {
			return false;
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a row.
	 *
	 * @param int                  $id Record ID.
	 * @param array<string, mixed> $data Row data.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		$existing = $this->get_by_id( $id );

		if ( empty( $existing ) ) {
			return false;
		}

		$data = $this->prepare_item_for_database( array_merge( $existing, $data ) );

		$result = $this->wpdb->update(
			$this->table_name,
			$data,
			array( 'id' => absint( $id ) ),
			$this->get_formats_for_data( $data ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a row.
	 *
	 * @param int $id Record ID.
	 * @return bool
	 */
	public function delete( $id ) {
		$result = $this->wpdb->delete( $this->table_name, array( 'id' => absint( $id ) ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Get formats for the provided data array.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( array $data ) {
		$formats        = $this->get_column_formats();
		$matched_format = array();

		foreach ( array_keys( $data ) as $column ) {
			$matched_format[] = isset( $formats[ $column ] ) ? $formats[ $column ] : '%s';
		}

		return $matched_format;
	}

	/**
	 * Create a current MySQL datetime string.
	 *
	 * @return string
	 */
	protected function now() {
		return current_time( 'mysql', true );
	}

	/**
	 * Sanitize an ORDER BY clause against an allowlist.
	 *
	 * @param string $order_by Raw order by string.
	 * @param array<int, string> $allowed List of allowed columns.
	 * @param string $default Default if nothing passes allowlist.
	 * @return string
	 */
	protected function sanitize_order_by( $order_by, array $allowed, $default ) {
		$parts = explode( ' ', trim( $order_by ), 2 );
		$col   = $parts[0];
		$dir   = isset( $parts[1] ) ? strtoupper( trim( $parts[1] ) ) : 'ASC';

		if ( ! in_array( $col, $allowed, true ) ) {
			return $default;
		}

		if ( ! in_array( $dir, array( 'ASC', 'DESC' ), true ) ) {
			$dir = 'ASC';
		}

		return $col . ' ' . $dir;
	}
}
