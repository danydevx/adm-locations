<?php
/**
 * WooCommerce Shipping Zones sync for ADM Bike Woo Locations.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Shipping_Zone_Sync {

	/**
	 * Repository instances.
	 *
	 * @var array<string, object>
	 */
	protected $repositories = array();

	/**
	 * Sync a shipping rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function sync_rule_by_id( $rule_id ) {
		$rule = $this->shipping_rules_repo()->get_by_id( $rule_id );

		if ( ! $rule ) {
			return new WP_Error( 'orpot_woo_locations_rule_not_found', __( 'Shipping rule not found.', 'admbike-woo-locations' ) );
		}

		return $this->sync_rule( $rule );
	}

	/**
	 * Sync a shipping rule into WooCommerce Shipping Zones.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @return array<string, mixed>|WP_Error
	 */
	public function sync_rule( array $rule ) {
		if ( ! class_exists( 'WC_Shipping_Zone' ) || ! class_exists( 'WC_Shipping_Zones' ) ) {
			return new WP_Error( 'orpot_woo_locations_wc_missing', __( 'WooCommerce Shipping Zones are not available.', 'admbike-woo-locations' ) );
		}

		$rule_id = absint( $rule['id'] ?? 0 );
		if ( $rule_id <= 0 ) {
			return new WP_Error( 'orpot_woo_locations_invalid_rule', __( 'Invalid shipping rule.', 'admbike-woo-locations' ) );
		}

		if ( empty( $rule['is_active'] ) || ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_UNAVAILABLE === ( $rule['rule_type'] ?? '' ) ) {
			return $this->delete_zone_for_rule( $rule );
		}

		$payload = $this->build_zone_payload( $rule );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$zone_id = absint( $rule['wc_zone_id'] ?? 0 );
		$conflicts = $this->detect_postcode_conflicts( $payload['locations'], $zone_id );
		if ( ! empty( $conflicts ) ) {
			$labels = array_map(
				static function ( $item ) {
					return ! empty( $item['name'] ) ? $item['name'] : ( 'zone ' . (int) ( $item['zone_id'] ?? 0 ) );
				},
				$conflicts
			);

			return new WP_Error(
				'orpot_woo_locations_postcode_conflict',
				sprintf(
					/* translators: %s: comma separated list of zone names. */
					__( 'Conflicting postcode coverage already exists in WooCommerce zones: %s', 'admbike-woo-locations' ),
					implode( ', ', $labels )
				)
			);
		}

		$zone = $zone_id > 0 ? new WC_Shipping_Zone( $zone_id ) : new WC_Shipping_Zone();
		if ( ! ( $zone instanceof WC_Shipping_Zone ) || absint( $zone->get_id() ) <= 0 ) {
			$zone = new WC_Shipping_Zone();
		}

		$zone->set_zone_name( $payload['zone_name'] );
		$zone->set_zone_order( absint( $rule['priority'] ?? 100 ) );
		$zone->save();
		$zone->set_locations( $payload['locations'] );
		$zone->save();

		$instance_id = $this->sync_zone_method_instance( $zone, $payload['method_id'], $payload, $rule );
		if ( ! $instance_id ) {
			return new WP_Error( 'orpot_woo_locations_method_failed', __( 'Failed to add a WooCommerce shipping method to the zone.', 'admbike-woo-locations' ) );
		}

		$this->enable_shipping_method_instance( (int) $instance_id );
		$this->configure_shipping_method( $instance_id, $payload, $rule );
		$this->persist_zone_reference( $rule_id, (int) $zone->get_id(), $payload['zone_name'] );
		$this->clear_shipping_cache();

		ADMBike_Woo_Locations_Logger::info(
			'Shipping zone synced',
			array(
				'rule_id'   => $rule_id,
				'zone_id'   => (int) $zone->get_id(),
				'zone_name' => $payload['zone_name'],
				'location_count' => count( $payload['locations'] ),
				'method_id' => $payload['method_id'],
			)
		);

		return array(
			'zone_id'   => (int) $zone->get_id(),
			'zone_name' => $payload['zone_name'],
			'method_id' => $payload['method_id'],
		);
	}

	/**
	 * Delete the WooCommerce zone linked to a rule.
	 *
	 * @param array<string, mixed>|int $rule Rule row or rule ID.
	 * @return true|WP_Error
	 */
	public function delete_zone_for_rule( $rule ) {
		if ( ! is_array( $rule ) ) {
			$rule = (array) $this->shipping_rules_repo()->get_by_id( absint( $rule ) );
		}

		$rule_id = absint( $rule['id'] ?? 0 );
		$zone_id = absint( $rule['wc_zone_id'] ?? 0 );

		if ( $zone_id > 0 ) {
			$this->delete_zone( $zone_id );
		}

		if ( $rule_id > 0 ) {
			$this->persist_zone_reference( $rule_id, 0, '' );
		}

		$this->clear_shipping_cache();

		ADMBike_Woo_Locations_Logger::info(
			'Shipping zone removed',
			array(
				'rule_id' => $rule_id,
				'zone_id' => $zone_id,
			)
		);

		return true;
	}

	/**
	 * Get the shipping rules repository.
	 *
	 * @return ADMBike_Woo_Locations_Shipping_Rule_Repository
	 */
	protected function shipping_rules_repo() {
		if ( ! isset( $this->repositories['shipping_rules'] ) ) {
			$this->repositories['shipping_rules'] = new ADMBike_Woo_Locations_Shipping_Rule_Repository();
		}

		return $this->repositories['shipping_rules'];
	}

	/**
	 * Get the state repository.
	 *
	 * @return ADMBike_Woo_Locations_State_Repository
	 */
	protected function states_repo() {
		if ( ! isset( $this->repositories['states'] ) ) {
			$this->repositories['states'] = new ADMBike_Woo_Locations_State_Repository();
		}

		return $this->repositories['states'];
	}

	/**
	 * Get the municipality repository.
	 *
	 * @return ADMBike_Woo_Locations_Municipality_Repository
	 */
	protected function municipalities_repo() {
		if ( ! isset( $this->repositories['municipalities'] ) ) {
			$this->repositories['municipalities'] = new ADMBike_Woo_Locations_Municipality_Repository();
		}

		return $this->repositories['municipalities'];
	}

	/**
	 * Get the postcode repository.
	 *
	 * @return ADMBike_Woo_Locations_Postcode_Repository
	 */
	protected function postcodes_repo() {
		if ( ! isset( $this->repositories['postcodes'] ) ) {
			$this->repositories['postcodes'] = new ADMBike_Woo_Locations_Postcode_Repository();
		}

		return $this->repositories['postcodes'];
	}

	/**
	 * Build the zone payload from a rule.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function build_zone_payload( array $rule ) {
		$state = null;
		$state_code = '';
		$state_name = '';
		$match_type = isset( $rule['match_type'] ) ? sanitize_key( (string) $rule['match_type'] ) : '';

		if ( ! empty( $rule['state_id'] ) ) {
			$state = $this->states_repo()->get_by_id( absint( $rule['state_id'] ) );
		} elseif ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE === $match_type && ! empty( $rule['display_title'] ) ) {
			$state = $this->states_repo()->get_by_code_or_name( (string) $rule['display_title'] );
		}

		if ( $state ) {
			$state_code = isset( $state['code'] ) ? strtoupper( (string) $state['code'] ) : '';
			$state_name  = isset( $state['name'] ) ? sanitize_text_field( (string) $state['name'] ) : '';
		}

		if ( '' === $state_name ) {
			$state_name = __( 'Estado', 'admbike-woo-locations' );
		}

		$zone_name = $this->build_zone_name( $rule, $state_name );
		$locations = array(
			array(
				'type' => 'country',
				'code' => 'MX',
			),
		);

		if ( '' !== $state_code ) {
			$locations[] = array(
				'type' => 'state',
				'code' => 'MX:' . $state_code,
			);
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_STATE === $match_type && $state && ! empty( $state['id'] ) ) {
			$state_locations = $this->build_state_postcode_locations( absint( $state['id'] ) );
			if ( is_wp_error( $state_locations ) ) {
				return $state_locations;
			}

			$locations = array_merge( $locations, $state_locations );
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_MUNICIPALITY === $match_type && ! empty( $rule['municipality_id'] ) ) {
			$municipality_locations = $this->build_municipality_postcode_locations( absint( $rule['municipality_id'] ) );
			if ( is_wp_error( $municipality_locations ) ) {
				return $municipality_locations;
			}

			$locations = array_merge( $locations, $municipality_locations );
		} elseif ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE === $match_type ) {
			$postcode = $this->get_rule_postcode_code( $rule );
			if ( '' === $postcode ) {
				return new WP_Error( 'orpot_woo_locations_postcode_missing', __( 'The selected postcode could not be resolved.', 'admbike-woo-locations' ) );
			}

			$locations[] = array(
				'type' => 'postcode',
				'code' => $postcode,
			);
		} elseif ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE === $match_type ) {
			$from = $this->sanitize_postcode( (string) ( $rule['postcode_from'] ?? '' ) );
			$to   = $this->sanitize_postcode( (string) ( $rule['postcode_to'] ?? '' ) );

			if ( '' === $from || '' === $to ) {
				return new WP_Error( 'orpot_woo_locations_range_missing', __( 'The selected postcode range is incomplete.', 'admbike-woo-locations' ) );
			}

			$locations[] = array(
				'type' => 'postcode',
				'code' => $from . '...' . $to,
			);
		}

		ADMBike_Woo_Locations_Logger::debug(
			'Shipping zone payload built',
			array(
				'rule_id'  => absint( $rule['id'] ?? 0 ),
				'zone_name' => $zone_name,
				'locations' => $locations,
			)
		);

		return array(
			'zone_name' => $zone_name,
			'locations' => $locations,
			'method_id' => ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE === ( $rule['rule_type'] ?? '' ) ? 'free_shipping' : 'flat_rate',
			'method_title' => $this->build_method_title( $rule ),
			'rule_type' => sanitize_key( (string) ( $rule['rule_type'] ?? '' ) ),
			'shipping_cost' => isset( $rule['shipping_cost'] ) ? (float) $rule['shipping_cost'] : 0.0,
		);
	}

	/**
	 * Build a zone name.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @param string               $state_name State name.
	 * @return string
	 */
	protected function build_zone_name( array $rule, $state_name ) {
		$zone_bits = array( 'ADM', $state_name );

		$match_type = isset( $rule['match_type'] ) ? sanitize_key( (string) $rule['match_type'] ) : '';

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_MUNICIPALITY === $match_type && ! empty( $rule['municipality_id'] ) ) {
			$municipality = $this->municipalities_repo()->get_by_id( absint( $rule['municipality_id'] ) );
			if ( $municipality && ! empty( $municipality['name'] ) ) {
				$zone_bits[] = sanitize_text_field( (string) $municipality['name'] );
			}
		} elseif ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE === $match_type ) {
			$zone_bits[] = $this->get_rule_postcode_code( $rule );
		} elseif ( ADMBike_Woo_Locations_Shipping_Rule_Repository::MATCH_POSTCODE_RANGE === $match_type ) {
			$zone_bits[] = $this->sanitize_postcode( (string) ( $rule['postcode_from'] ?? '' ) ) . '-' . $this->sanitize_postcode( (string) ( $rule['postcode_to'] ?? '' ) );
		}

		return trim( implode( ' - ', array_filter( $zone_bits ) ) );
	}

	/**
	 * Build the customer-facing method title.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @return string
	 */
	protected function build_method_title( array $rule ) {
		if ( ! empty( $rule['display_title'] ) ) {
			return sanitize_text_field( (string) $rule['display_title'] );
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_FREE === ( $rule['rule_type'] ?? '' ) ) {
			return __( 'Envío gratuito', 'admbike-woo-locations' );
		}

		if ( ADMBike_Woo_Locations_Shipping_Rule_Repository::RULE_PAID === ( $rule['rule_type'] ?? '' ) ) {
			return sprintf(
				/* translators: %s: shipping cost. */
				__( 'Envío %s', 'admbike-woo-locations' ),
				wc_format_localized_price( (float) ( $rule['shipping_cost'] ?? 0 ) )
			);
		}

		return __( 'ADM Shipping', 'admbike-woo-locations' );
	}

	/**
	 * Configure the shipping method instance.
	 *
	 * @param int                  $instance_id Method instance ID.
	 * @param array<string, mixed>  $payload Zone payload.
	 * @param array<string, mixed>  $rule Rule row.
	 * @return void
	 */
	protected function configure_shipping_method( $instance_id, array $payload, array $rule ) {
		$method_id = (string) $payload['method_id'];
		$settings = array(
			'title' => $payload['method_title'],
		);

		if ( 'free_shipping' === $method_id ) {
			$settings['requires'] = '';
			$settings['min_amount'] = '';
			$settings['ignore_discounts'] = 'no';
			update_option( 'woocommerce_free_shipping_' . absint( $instance_id ) . '_settings', $settings );
			return;
		}

		$settings['tax_status'] = 'taxable';
		$settings['cost']       = isset( $payload['shipping_cost'] ) ? wc_format_decimal( (float) $payload['shipping_cost'] ) : '0';
		$settings['type']       = 'class';
		update_option( 'woocommerce_flat_rate_' . absint( $instance_id ) . '_settings', $settings );
	}

	/**
	 * Sync the shipping method instance for a zone.
	 *
	 * @param WC_Shipping_Zone        $zone Zone object.
	 * @param string                  $method_id Shipping method ID.
	 * @param array<string, mixed>    $payload Zone payload.
	 * @param array<string, mixed>    $rule Rule row.
	 * @return int
	 */
	protected function sync_zone_method_instance( $zone, $method_id, array $payload, array $rule ) {
		$existing_methods = method_exists( $zone, 'get_shipping_methods' ) ? (array) $zone->get_shipping_methods( true ) : array();
		$existing_instance_id = 0;

		foreach ( $existing_methods as $method ) {
			if ( ! is_object( $method ) || empty( $method->id ) ) {
				continue;
			}

			$method_instance_id = isset( $method->instance_id ) ? absint( $method->instance_id ) : 0;

			if ( (string) $method->id === (string) $method_id ) {
				$existing_instance_id = $method_instance_id;
				continue;
			}

			if ( method_exists( $zone, 'delete_shipping_method' ) && $method_instance_id > 0 ) {
				$zone->delete_shipping_method( $method_instance_id );
			}
		}

		if ( $existing_instance_id <= 0 ) {
			$existing_instance_id = absint( $zone->add_shipping_method( $method_id ) );
		}

		if ( $existing_instance_id > 0 ) {
			$this->enable_shipping_method_instance( $existing_instance_id );
			$this->configure_shipping_method( $existing_instance_id, $payload, $rule );
		}

		return $existing_instance_id;
	}

	/**
	 * Explicitly enable a shipping method instance inside the zone table.
	 *
	 * @param int $instance_id Instance ID.
	 * @return void
	 */
	protected function enable_shipping_method_instance( $instance_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'woocommerce_shipping_zone_methods',
			array(
				'is_enabled'   => 1,
				'method_order' => 1,
			),
			array(
				'instance_id' => absint( $instance_id ),
			),
			array( '%d', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Update the rule row with the synchronized zone reference.
	 *
	 * @param int    $rule_id Rule ID.
	 * @param int    $zone_id Zone ID.
	 * @param string $zone_name Zone name.
	 * @return void
	 */
	protected function persist_zone_reference( $rule_id, $zone_id, $zone_name ) {
		$this->shipping_rules_repo()->update(
			$rule_id,
			array(
				'wc_zone_id'   => absint( $zone_id ),
				'wc_zone_name' => sanitize_text_field( $zone_name ),
			)
		);
	}

	/**
	 * Delete a WooCommerce shipping zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @return void
	 */
	protected function delete_zone( $zone_id ) {
		$zone_id = absint( $zone_id );

		if ( $zone_id <= 0 ) {
			return;
		}

		if ( method_exists( 'WC_Shipping_Zones', 'delete_zone' ) ) {
			WC_Shipping_Zones::delete_zone( $zone_id );
			return;
		}

		$zone = new WC_Shipping_Zone( $zone_id );
		if ( method_exists( $zone, 'delete' ) ) {
			$zone->delete();
		}
	}

	/**
	 * Determine whether a shipping zone still exists.
	 *
	 * @param int $zone_id Zone ID.
	 * @return bool
	 */
	protected function zone_exists( $zone_id ) {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return false;
		}

		return (bool) WC_Shipping_Zones::get_zone( absint( $zone_id ) );
	}

	/**
	 * Clear WooCommerce shipping caches after a zone change.
	 *
	 * @return void
	 */
	protected function clear_shipping_cache() {
		if ( function_exists( 'wc_delete_shipping_transients' ) ) {
			wc_delete_shipping_transients();
		}
	}

	/**
	 * Build postcode locations for a municipality.
	 *
	 * @param int $municipality_id Municipality ID.
	 * @return array<int, array<string, string>>
	 */
	protected function build_municipality_postcode_locations( $municipality_id ) {
		$locations = $this->municipalities_repo()->get_coverage_locations( $municipality_id );

		if ( empty( $locations ) ) {
			return new WP_Error( 'orpot_woo_locations_municipality_postcodes_missing', __( 'The selected municipality does not have any postcode coverage to sync.', 'admbike-woo-locations' ) );
		}

		return $locations;
	}

	/**
	 * Build postcode locations for a state.
	 *
	 * @param int $state_id State ID.
	 * @return array<int, array<string, string>>|WP_Error
	 */
	protected function build_state_postcode_locations( $state_id ) {
		$locations = $this->states_repo()->get_coverage_locations( $state_id );

		if ( empty( $locations ) ) {
			return array();
		}

		return $locations;
	}

	/**
	 * Format postcode location for a single code or contiguous range.
	 *
	 * @param int $from From.
	 * @param int $to To.
	 * @return array<string, string>
	 */
	protected function format_postcode_location( $from, $to ) {
		$from_code = sprintf( '%05d', absint( $from ) );
		$to_code   = sprintf( '%05d', absint( $to ) );

		if ( $from_code === $to_code ) {
			return array(
				'type' => 'postcode',
				'code' => $from_code,
			);
		}

		return array(
			'type' => 'postcode',
			'code' => $from_code . '...' . $to_code,
		);
	}

	/**
	 * Resolve a postcode row to a string.
	 *
	 * @param int $postcode_id Postcode ID.
	 * @return string
	 */
	protected function get_postcode_value( $postcode_id ) {
		$postcode = $this->postcodes_repo()->get_by_id( absint( $postcode_id ) );
		if ( ! $postcode || empty( $postcode['postcode'] ) ) {
			return '';
		}

		return $this->sanitize_postcode( (string) $postcode['postcode'] );
	}

	/**
	 * Resolve a rule exact postcode code.
	 *
	 * @param array<string, mixed> $rule Rule row.
	 * @return string
	 */
	protected function get_rule_postcode_code( array $rule ) {
		$code = isset( $rule['postcode_code'] ) ? $this->sanitize_postcode( (string) $rule['postcode_code'] ) : '';
		if ( '' !== $code ) {
			return $code;
		}

		if ( empty( $rule['postcode_id'] ) ) {
			return '';
		}

		return $this->get_postcode_value( absint( $rule['postcode_id'] ) );
	}

	/**
	 * Sanitize a postcode.
	 *
	 * @param string $postcode Postcode.
	 * @return string
	 */
	protected function sanitize_postcode( $postcode ) {
		return preg_replace( '/[^0-9A-Za-z-]/', '', strtoupper( (string) $postcode ) );
	}

	/**
	 * Detect overlapping postcode conflicts with other zones.
	 *
	 * @param array<int, array<string, string>> $locations Zone locations.
	 * @param int                               $exclude_zone_id Zone ID to exclude.
	 * @return array<int, array<string, mixed>>
	 */
	protected function detect_postcode_conflicts( array $locations, $exclude_zone_id = 0 ) {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}

		$target_ranges = $this->extract_postcode_ranges( $locations );
		if ( empty( $target_ranges ) ) {
			return array();
		}

		$conflicts = array();
		$zones     = WC_Shipping_Zones::get_shipping_zones();

		foreach ( $zones as $zone ) {
			if ( ! $zone instanceof WC_Shipping_Zone ) {
				continue;
			}

			if ( absint( $zone->get_id() ) === absint( $exclude_zone_id ) ) {
				continue;
			}

			foreach ( (array) $zone->get_zone_locations() as $location ) {
				if ( ! is_object( $location ) || empty( $location->type ) || 'postcode' !== $location->type || empty( $location->code ) ) {
					continue;
				}

				$existing_ranges = $this->postcode_code_to_ranges( (string) $location->code );
				if ( empty( $existing_ranges ) ) {
					continue;
				}

				foreach ( $target_ranges as $target_range ) {
					foreach ( $existing_ranges as $existing_range ) {
						if ( $this->ranges_overlap( $target_range, $existing_range ) ) {
							$conflicts[] = array(
								'zone_id' => (int) $zone->get_id(),
								'name'    => method_exists( $zone, 'get_zone_name' ) ? (string) $zone->get_zone_name() : '',
								'code'    => (string) $location->code,
							);
							break 3;
						}
					}
				}
			}
		}

		return $conflicts;
	}

	/**
	 * Extract postcode ranges from a locations array.
	 *
	 * @param array<int, array<string, string>> $locations Zone locations.
	 * @return array<int, array{from:int,to:int}>
	 */
	protected function extract_postcode_ranges( array $locations ) {
		$ranges = array();

		foreach ( $locations as $location ) {
			if ( empty( $location['type'] ) || 'postcode' !== $location['type'] || empty( $location['code'] ) ) {
				continue;
			}

			$ranges = array_merge( $ranges, $this->postcode_code_to_ranges( (string) $location['code'] ) );
		}

		return $ranges;
	}

	/**
	 * Convert a postcode code into one or more numeric ranges.
	 *
	 * @param string $code Postcode code.
	 * @return array<int, array{from:int,to:int}>
	 */
	protected function postcode_code_to_ranges( $code ) {
		$code = $this->sanitize_postcode( $code );
		if ( '' === $code ) {
			return array();
		}

		if ( false !== strpos( $code, '...' ) ) {
			list( $from, $to ) = array_map( array( $this, 'sanitize_postcode' ), explode( '...', $code, 2 ) );
			if ( '' === $from || '' === $to ) {
				return array();
			}

			return array(
				array(
					'from' => (int) $from,
					'to'   => (int) $to,
				),
			);
		}

		if ( false !== strpos( $code, '*' ) ) {
			$prefix = rtrim( str_replace( '*', '', $code ) );
			$digits = preg_replace( '/\D/', '', $prefix );
			if ( '' === $digits ) {
				return array();
			}

			$width = 5;
			$pad   = max( 0, $width - strlen( $digits ) );
			$from  = (int) ( $digits . str_repeat( '0', $pad ) );
			$to    = (int) ( $digits . str_repeat( '9', $pad ) );

			return array(
				array(
					'from' => $from,
					'to'   => $to,
				),
			);
		}

		return array(
			array(
				'from' => (int) $code,
				'to'   => (int) $code,
			),
		);
	}

	/**
	 * Check if two ranges overlap.
	 *
	 * @param array{from:int,to:int} $left Left range.
	 * @param array{from:int,to:int} $right Right range.
	 * @return bool
	 */
	protected function ranges_overlap( array $left, array $right ) {
		return $left['from'] <= $right['to'] && $right['from'] <= $left['to'];
	}
}
