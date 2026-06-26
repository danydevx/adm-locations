<?php
/**
 * Shipping rules repository.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Shipping_Rule_Repository extends ADMBike_Woo_Locations_Abstract_Repository {

	/**
	 * Match type constants.
	 */
	public const MATCH_STATE          = 'state';
	public const MATCH_MUNICIPALITY  = 'municipality';
	public const MATCH_POSTCODE      = 'postcode';
	public const MATCH_POSTCODE_RANGE = 'postcode_range';

	/**
	 * Rule type constants.
	 */
	public const RULE_FREE        = 'free';
	public const RULE_PAID       = 'paid';
	public const RULE_UNAVAILABLE = 'unavailable';

	/**
	 * Get table suffix.
	 *
	 * @return string
	 */
	protected function get_table_suffix() {
		return 'admbike_locations_shipping_rules';
	}

	/**
	 * Get column formats.
	 *
	 * @return array<string, string>
	 */
	protected function get_column_formats() {
		return array(
			'match_type'      => '%s',
			'rule_type'       => '%s',
			'display_title'   => '%s',
			'customer_message' => '%s',
			'state_id'        => '%d',
			'municipality_id' => '%d',
			'postcode_id'     => '%d',
			'postcode_from'   => '%s',
			'postcode_to'     => '%s',
			'shipping_cost'   => '%f',
			'currency_code'   => '%s',
			'priority'        => '%d',
			'is_active'       => '%d',
			'wc_zone_id'      => '%d',
			'wc_zone_name'    => '%s',
			'notes'           => '%s',
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
		$match_type = isset( $data['match_type'] ) ? sanitize_key( (string) $data['match_type'] ) : '';

		$prepared = array(
			'match_type'      => $match_type,
			'rule_type'       => isset( $data['rule_type'] ) ? sanitize_key( (string) $data['rule_type'] ) : '',
			'display_title'   => isset( $data['display_title'] ) ? sanitize_text_field( (string) $data['display_title'] ) : '',
			'customer_message' => isset( $data['customer_message'] ) ? sanitize_textarea_field( (string) $data['customer_message'] ) : '',
			'state_id'        => null,
			'municipality_id' => null,
			'postcode_id'     => null,
			'postcode_from'   => '',
			'postcode_to'     => '',
			'shipping_cost'   => 0,
			'currency_code'   => 'MXN',
			'priority'        => 100,
			'is_active'       => 1,
			'wc_zone_id'      => 0,
			'wc_zone_name'    => '',
			'notes'           => '',
			'updated_at'      => $this->now(),
		);

		if ( self::MATCH_STATE === $match_type ) {
			$prepared['state_id'] = isset( $data['state_id'] ) ? absint( $data['state_id'] ) : null;
		} elseif ( self::MATCH_MUNICIPALITY === $match_type ) {
			$prepared['state_id']        = isset( $data['state_id'] ) ? absint( $data['state_id'] ) : null;
			$prepared['municipality_id'] = isset( $data['municipality_id'] ) ? absint( $data['municipality_id'] ) : null;
		} elseif ( self::MATCH_POSTCODE === $match_type ) {
			$prepared['postcode_id'] = isset( $data['postcode_id'] ) ? absint( $data['postcode_id'] ) : null;
		} elseif ( self::MATCH_POSTCODE_RANGE === $match_type ) {
			$prepared['state_id']    = isset( $data['state_id'] ) ? absint( $data['state_id'] ) : null;
			$prepared['postcode_from'] = isset( $data['postcode_from'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $data['postcode_from'] ) : '';
			$prepared['postcode_to']   = isset( $data['postcode_to'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', (string) $data['postcode_to'] ) : '';
		}

		if ( self::RULE_PAID === ( $data['rule_type'] ?? '' ) ) {
			$prepared['shipping_cost'] = isset( $data['shipping_cost'] ) ? max( 0, (float) $data['shipping_cost'] ) : 0;
			$prepared['currency_code'] = isset( $data['currency_code'] ) ? strtoupper( sanitize_text_field( (string) $data['currency_code'] ) ) : 'MXN';
		} else {
			$prepared['shipping_cost'] = 0;
		}

		$prepared['priority']  = isset( $data['priority'] ) ? absint( $data['priority'] ) : 100;
		$prepared['is_active'] = isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1;
		$prepared['wc_zone_id'] = isset( $data['wc_zone_id'] ) ? absint( $data['wc_zone_id'] ) : 0;
		$prepared['wc_zone_name'] = isset( $data['wc_zone_name'] ) ? sanitize_text_field( (string) $data['wc_zone_name'] ) : '';
		$prepared['notes']     = isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '';

		if ( empty( $data['created_at'] ) ) {
			$prepared['created_at'] = $this->now();
		}

		return $prepared;
	}

	/**
	 * Get active rules ordered by priority.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_active_rules() {
		return $this->get_items( array( 'is_active' => 1 ), 'priority ASC, id ASC' );
	}

	/**
	 * Get rules for a specific state.
	 *
	 * @param int $state_id State ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_rules_for_state( $state_id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE is_active = 1 AND state_id = %d ORDER BY priority ASC, id ASC",
			absint( $state_id )
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get rules for a specific municipality.
	 *
	 * @param int $municipality_id Municipality ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_rules_for_municipality( $municipality_id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE is_active = 1 AND municipality_id = %d ORDER BY priority ASC, id ASC",
			absint( $municipality_id )
		);

		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get paginated rules.
	 *
	 * @param string $order_by Order by clause.
	 * @param int $per_page Items per page.
	 * @param int $page Page number.
	 * @param string $search Search term (matches notes or postcode_from/to).
	 * @param string|null $rule_type Filter by rule type.
	 * @param string|null $match_type Filter by match type.
	 * @param int|null $is_active Filter by active status.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_paginated( $order_by = 'priority ASC, id ASC', $per_page = 20, $page = 1, $search = '', $rule_type = null, $match_type = null, $is_active = null ) {
		$page   = max( 1, absint( $page ) );
		$offset = ( $page - 1 ) * $per_page;
		$term   = sanitize_text_field( (string) $search );

		$sql = "SELECT * FROM {$this->table_name}";
		$where_parts = array();
		$values = array();

		if ( '' !== $term ) {
			$where_parts[] = '(notes LIKE %s OR postcode_from LIKE %s OR postcode_to LIKE %s)';
			$like = '%' . $this->wpdb->esc_like( $term ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( null !== $rule_type ) {
			$where_parts[] = 'rule_type = %s';
			$values[] = sanitize_key( $rule_type );
		}

		if ( null !== $match_type ) {
			$where_parts[] = 'match_type = %s';
			$values[] = sanitize_key( $match_type );
		}

		if ( null !== $is_active ) {
			$where_parts[] = 'is_active = %d';
			$values[] = (int) (bool) $is_active;
		}

		if ( ! empty( $where_parts ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_parts );
		}

		$allowed_order = array( 'priority', 'id', 'rule_type', 'match_type', 'created_at' );
		$order_by_safe = $this->sanitize_order_by( $order_by, $allowed_order, 'priority ASC, id ASC' );
		$sql .= ' ORDER BY ' . $order_by_safe;
		$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $per_page ), $offset );

		if ( ! empty( $values ) ) {
			$sql = $this->wpdb->prepare( $sql, $values );
		}

		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count all rules with optional filters.
	 *
	 * @param string $search Search term.
	 * @param string|null $rule_type Filter by rule type.
	 * @param string|null $match_type Filter by match type.
	 * @param int|null $is_active Filter by active status.
	 * @return int
	 */
	public function count_all( $search = '', $rule_type = null, $match_type = null, $is_active = null ) {
		$term = sanitize_text_field( (string) $search );

		$sql = "SELECT COUNT(*) FROM {$this->table_name}";
		$where_parts = array();
		$values = array();

		if ( '' !== $term ) {
			$where_parts[] = '(notes LIKE %s OR postcode_from LIKE %s OR postcode_to LIKE %s)';
			$like = '%' . $this->wpdb->esc_like( $term ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( null !== $rule_type ) {
			$where_parts[] = 'rule_type = %s';
			$values[] = sanitize_key( $rule_type );
		}

		if ( null !== $match_type ) {
			$where_parts[] = 'match_type = %s';
			$values[] = sanitize_key( $match_type );
		}

		if ( null !== $is_active ) {
			$where_parts[] = 'is_active = %d';
			$values[] = (int) (bool) $is_active;
		}

		if ( ! empty( $where_parts ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where_parts );
		}

		if ( ! empty( $values ) ) {
			$sql = $this->wpdb->prepare( $sql, $values );
		}

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Detect conflicts with a new or updated rule.
	 *
	 * A conflict occurs when rules of higher specificity (postcode > municipality > state)
	 * overlap in coverage. Returns array of conflicting rule IDs.
	 *
	 * @param array<string, mixed> $rule The rule to check.
	 * @param int|null $exclude_id Rule ID to exclude (for updates).
	 * @return array<int, array<string, mixed>>
	 */
	public function detect_conflicts( array $rule, $exclude_id = null ) {
		$match_type = $rule['match_type'] ?? '';
		$conflicts = array();

		if ( self::MATCH_STATE === $match_type ) {
			$state_id = absint( $rule['state_id'] ?? 0 );
			if ( $state_id <= 0 ) {
				return array();
			}
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE match_type IN ('municipality','postcode','postcode_range') AND state_id = %d AND is_active = 1",
				$state_id
			);
			$conflicts = $this->wpdb->get_results( $sql, ARRAY_A );

		} elseif ( self::MATCH_MUNICIPALITY === $match_type ) {
			$muni_id = absint( $rule['municipality_id'] ?? 0 );
			if ( $muni_id <= 0 ) {
				return array();
			}
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE match_type IN ('postcode','postcode_range') AND municipality_id = %d AND is_active = 1",
				$muni_id
			);
			$conflicts = $this->wpdb->get_results( $sql, ARRAY_A );

		} elseif ( self::MATCH_POSTCODE === $match_type ) {
			$pc_id = absint( $rule['postcode_id'] ?? 0 );
			if ( $pc_id <= 0 ) {
				return array();
			}
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE match_type = 'postcode_range' AND postcode_from <= (SELECT postcode FROM {$this->table_name} WHERE id = %d) AND postcode_to >= (SELECT postcode FROM {$this->table_name} WHERE id = %d) AND is_active = 1",
				$pc_id,
				$pc_id
			);
			$conflicts = $this->wpdb->get_results( $sql, ARRAY_A );

		} elseif ( self::MATCH_POSTCODE_RANGE === $match_type ) {
			$from = preg_replace( '/[^0-9A-Za-z-]/', '', (string) ( $rule['postcode_from'] ?? '' ) );
			$to   = preg_replace( '/[^0-9A-Za-z-]/', '', (string) ( $rule['postcode_to'] ?? '' ) );
			$state_id = absint( $rule['state_id'] ?? 0 );

			if ( '' === $from || '' === $to ) {
				return array();
			}

			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE match_type = 'postcode' AND ((postcode_from != '' AND postcode_from <= %s AND postcode_to >= %s) OR (state_id = %d)) AND is_active = 1",
				$to,
				$from,
				$state_id
			);
			$conflicts = $this->wpdb->get_results( $sql, ARRAY_A );
		}

		if ( null !== $exclude_id ) {
			$conflicts = array_values(
				array_filter(
					$conflicts,
					function ( $r ) use ( $exclude_id ) {
						return (int) $r['id'] !== (int) $exclude_id;
					}
				)
			);
		}

		return is_array( $conflicts ) ? $conflicts : array();
	}

	/**
	 * Get all active rules grouped by match specificity.
	 * Used for preview/resolution.
	 *
	 * @param int|null $state_id Filter by state.
	 * @param int|null $municipality_id Filter by municipality.
	 * @param string|null $postcode Filter by postcode.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_applicable_rules( $state_id = null, $municipality_id = null, $postcode = null ) {
		$state_id        = absint( $state_id );
		$municipality_id = absint( $municipality_id );
		$postcode        = preg_replace( '/[^0-9A-Za-z-]/', '', (string) $postcode );

		$candidates = array();

		if ( $postcode !== '' ) {
			$postcode_rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE is_active = 1 AND match_type = %s AND postcode_id IN (SELECT id FROM {$this->wpdb->prefix}admbike_locations_postcodes WHERE postcode = %s)",
					self::MATCH_POSTCODE,
					$postcode
				),
				ARRAY_A
			);

			if ( is_array( $postcode_rows ) ) {
				$candidates = array_merge( $candidates, $postcode_rows );
			}

			$range_rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE is_active = 1 AND match_type = %s AND postcode_from <= %s AND postcode_to >= %s",
					self::MATCH_POSTCODE_RANGE,
					$postcode,
					$postcode
				),
				ARRAY_A
			);

			if ( is_array( $range_rows ) ) {
				$candidates = array_merge( $candidates, $range_rows );
			}
		}

		if ( $municipality_id > 0 ) {
			$municipality_rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE is_active = 1 AND match_type = %s AND municipality_id = %d",
					self::MATCH_MUNICIPALITY,
					$municipality_id
				),
				ARRAY_A
			);

			if ( is_array( $municipality_rows ) ) {
				$candidates = array_merge( $candidates, $municipality_rows );
			}
		}

		if ( $state_id > 0 ) {
			$state_rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE is_active = 1 AND match_type = %s AND state_id = %d",
					self::MATCH_STATE,
					$state_id
				),
				ARRAY_A
			);

			if ( is_array( $state_rows ) ) {
				$candidates = array_merge( $candidates, $state_rows );
			}
		}

		if ( empty( $candidates ) ) {
			return array();
		}

		$matches = array();

		foreach ( $candidates as $rule ) {
			if ( ! isset( $rule['match_type'] ) ) {
				continue;
			}

			if ( self::MATCH_POSTCODE === $rule['match_type'] ) {
				$rule_postcode = '';
				if ( ! empty( $rule['postcode_id'] ) ) {
					$rule_postcode = (string) $this->wpdb->get_var(
						$this->wpdb->prepare(
							"SELECT postcode FROM {$this->wpdb->prefix}admbike_locations_postcodes WHERE id = %d",
							absint( $rule['postcode_id'] )
						)
					);
				}

				if ( '' === $rule_postcode || $rule_postcode !== $postcode ) {
					continue;
				}
			} elseif ( self::MATCH_POSTCODE_RANGE === $rule['match_type'] ) {
				if ( '' === $postcode || $rule['postcode_from'] > $postcode || $rule['postcode_to'] < $postcode ) {
					continue;
				}
			} elseif ( self::MATCH_MUNICIPALITY === $rule['match_type'] ) {
				if ( $municipality_id <= 0 || absint( $rule['municipality_id'] ) !== $municipality_id ) {
					continue;
				}
			} elseif ( self::MATCH_STATE === $rule['match_type'] ) {
				if ( $state_id <= 0 || absint( $rule['state_id'] ) !== $state_id ) {
					continue;
				}
			} else {
				continue;
			}

			$rule['_specificity'] = $this->get_rule_specificity( $rule['match_type'] );
			$matches[] = $rule;
		}

		if ( empty( $matches ) ) {
			return array();
		}

		usort(
			$matches,
			function ( $left, $right ) {
				$left_specificity  = (int) ( $left['_specificity'] ?? 0 );
				$right_specificity = (int) ( $right['_specificity'] ?? 0 );

				if ( $left_specificity !== $right_specificity ) {
					return $left_specificity <=> $right_specificity;
				}

				$left_priority  = isset( $left['priority'] ) ? (int) $left['priority'] : 100;
				$right_priority = isset( $right['priority'] ) ? (int) $right['priority'] : 100;

				if ( $left_priority !== $right_priority ) {
					return $left_priority <=> $right_priority;
				}

				return (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 );
			}
		);

		foreach ( $matches as &$match ) {
			unset( $match['_specificity'] );
		}
		unset( $match );

		return $matches;
	}

	/**
	 * Get rule specificity ordering.
	 *
	 * Lower numbers are more specific.
	 *
	 * @param string $match_type Match type.
	 * @return int
	 */
	protected function get_rule_specificity( $match_type ) {
		if ( self::MATCH_POSTCODE === $match_type ) {
			return 1;
		}

		if ( self::MATCH_POSTCODE_RANGE === $match_type ) {
			return 2;
		}

		if ( self::MATCH_MUNICIPALITY === $match_type ) {
			return 3;
		}

		if ( self::MATCH_STATE === $match_type ) {
			return 4;
		}

		return 99;
	}
}
