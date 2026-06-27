<?php
/**
 * Debug logger for ADM Bike Woo Locations.
 *
 * Logs shipping calculations, rule evaluations, and errors to a dedicated log file.
 *
 * @package ADMBike_Woo_Locations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ADMBike_Woo_Locations_Logger {

	/**
	 * Log file name.
	 *
	 * @var string
	 */
	private static $log_file = 'admbike-woo-locations.log';

	/**
	 * Whether logging is enabled.
	 *
	 * @var bool
	 */
	private static $enabled = false;

	/**
	 * Minimum log level to record.
	 *
	 * Levels: debug, info, warning, error.
	 *
	 * @var string
	 */
	private static $min_level = 'warning';

	/**
	 * Log levels in order of severity.
	 *
	 * @var array<int, string>
	 */
	private static $levels = array(
		'debug'   => 0,
		'info'    => 1,
		'warning' => 2,
		'error'   => 3,
	);

	/**
	 * Initialize the logger.
	 *
	 * @return void
	 */
	public static function init() {
		self::$enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;

		$custom_level = get_option( 'orpot_woo_locations_log_level', '' );
		if ( $custom_level && isset( self::$levels[ $custom_level ] ) ) {
			self::$min_level = $custom_level;
		}
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function debug( $message, array $context = array() ) {
		self::log( 'debug', $message, $context );
	}

	/**
	 * Log an info message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function info( $message, array $context = array() ) {
		self::log( 'info', $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function warning( $message, array $context = array() ) {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function error( $message, array $context = array() ) {
		self::log( 'error', $message, $context );
	}

	/**
	 * Log a shipping calculation event.
	 *
	 * @param string $postcode Postcode used.
	 * @param array  $rules_matched Rules that matched.
	 * @param array  $applied_rule The rule that was applied.
	 * @param float  $cost Calculated cost.
	 * @return void
	 */
	public static function log_shipping_calculation( $postcode, array $rules_matched, array $applied_rule, $cost ) {
		$context = array(
			'postcode'      => $postcode,
			'rules_count'  => count( $rules_matched ),
			'applied_rule' => $applied_rule,
			'cost'         => $cost,
		);

		if ( empty( $applied_rule ) ) {
			self::info( 'Shipping calculation: no matching rules for postcode {postcode}', $context );
		} else {
			self::info(
				'Shipping calculation: postcode={postcode}, rule_type={rule_type}, match_type={match_type}, cost={cost}',
				array_merge(
					$context,
					array(
						'rule_type'  => $applied_rule['rule_type'] ?? 'none',
						'match_type' => $applied_rule['match_type'] ?? 'none',
					)
				)
			);
		}
	}

	/**
	 * Log a rule conflict detection.
	 *
	 * @param array $new_rule The new/updated rule.
	 * @param array $conflicts Conflicting rules.
	 * @return void
	 */
	public static function log_conflict_detection( array $new_rule, array $conflicts ) {
		if ( empty( $conflicts ) ) {
			return;
		}

		self::warning(
			'Conflict detected: new rule {match_type} conflicts with {conflict_count} existing rules',
			array(
				'new_rule'       => $new_rule,
				'conflict_count' => count( $conflicts ),
				'conflicts'      => $conflicts,
			)
		);
	}

	/**
	 * Core log method.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	private static function log( $level, $message, array $context = array() ) {
		if ( ! self::$enabled ) {
			return;
		}

		if ( ! isset( self::$levels[ $level ] ) ) {
			return;
		}

		if ( self::$levels[ $level ] < self::$levels[ self::$min_level ] ) {
			return;
		}

		$timestamp = current_time( 'mysql', true );
		$formatted = self::format( $level, $message, $context );

		$upload_dir = wp_upload_dir( null, false );
		$log_path   = trailingslashit( $upload_dir['basedir'] ) . self::$log_file;

		$handle = fopen( $log_path, 'a' );
		if ( false === $handle ) {
			return;
		}

		fwrite( $handle, $formatted . PHP_EOL );
		fclose( $handle );
	}

	/**
	 * Format a log entry.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Context data.
	 * @return string
	 */
	private static function format( $level, $message, array $context = array() ) {
		$timestamp = current_time( 'mysql', true );
		$context_str = '';

		if ( ! empty( $context ) ) {
			$context_str = ' ' . json_encode( $context );
		}

		return sprintf(
			'[%s] [%s] [%s] %s%s',
			$timestamp,
			strtoupper( $level ),
			'admbike-woo-locations',
			$message,
			$context_str
		);
	}
}
