<?php
/**
 * PressArk Watchdog Preferences — Per-user alert configuration.
 *
 * Stores alert preferences as a serialized array in wp_usermeta.
 * Each user can configure which alert types are enabled and
 * which notification channels each type uses.
 *
 * @package PressArk
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Watchdog_Preferences {

	/** Usermeta key for the preferences blob. */
	private const META_KEY = 'pressark_watchdog_prefs';

	/** Valid alert type identifiers. */
	private const ALERT_TYPES = array(
		'order_failed',
		'order_cancelled',
		'refund_issued',
		'low_stock',
		'out_of_stock',
		'negative_review',
		'high_value_order',
	);

	/** Valid notification channels. */
	private const CHANNELS = array( 'email', 'telegram' );

	/** Valid digest frequencies. */
	private const DIGEST_FREQUENCIES = array( 'daily', 'weekly', 'monthly' );

	/** Valid digest days. */
	private const DIGEST_DAYS = array(
		'monday', 'tuesday', 'wednesday', 'thursday',
		'friday', 'saturday', 'sunday',
	);

	/** In-memory cache for the current request. */
	private static array $cache = array();

	/**
	 * Get the full default preferences structure.
	 *
	 * @return array Default preferences.
	 */
	public static function get_defaults(): array {
		return array(
			'alerts'  => array(
				'order_failed'     => array( 'enabled' => true, 'channels' => array( 'email' ) ),
				'order_cancelled'  => array( 'enabled' => true, 'channels' => array( 'email' ) ),
				'refund_issued'    => array( 'enabled' => true, 'channels' => array( 'email' ) ),
				'low_stock'        => array( 'enabled' => true, 'channels' => array( 'email' ) ),
				'out_of_stock'     => array( 'enabled' => true, 'channels' => array( 'email' ) ),
				'negative_review'  => array( 'enabled' => true, 'channels' => array( 'email' ) ),
				'high_value_order' => array( 'enabled' => false, 'channels' => array( 'email' ), 'threshold' => 500 ),
			),
			'digest'  => array(
				'enabled'   => false,
				'frequency' => 'weekly',
				'day'       => 'monday',
				'time'      => '09:00',
				'channels'  => array( 'email' ),
			),
			'batching_window_minutes' => 15,
			'auto_reply_store_email'  => '',
		);
	}

	/**
	 * Get preferences for a user, merged with defaults.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Preferences with all keys guaranteed present.
	 */
	public static function get( int $user_id ): array {
		if ( isset( self::$cache[ $user_id ] ) ) {
			return self::$cache[ $user_id ];
		}

		$defaults = self::get_defaults();
		$stored   = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $stored ) ) {
			self::$cache[ $user_id ] = $defaults;
			return $defaults;
		}

		// Merge top-level keys.
		$prefs = array(
			'batching_window_minutes' => absint( $stored['batching_window_minutes'] ?? $defaults['batching_window_minutes'] ),
			'auto_reply_store_email'  => sanitize_email( $stored['auto_reply_store_email'] ?? '' ),
		);

		// Merge digest.
		$prefs['digest'] = wp_parse_args(
			array_intersect_key( $stored['digest'] ?? array(), $defaults['digest'] ),
			$defaults['digest']
		);

		// Merge per-alert-type settings.
		$prefs['alerts'] = array();
		foreach ( self::ALERT_TYPES as $type ) {
			$default_alert = $defaults['alerts'][ $type ];
			$stored_alert  = $stored['alerts'][ $type ] ?? array();

			$prefs['alerts'][ $type ] = array(
				'enabled'  => isset( $stored_alert['enabled'] ) ? (bool) $stored_alert['enabled'] : $default_alert['enabled'],
				'channels' => isset( $stored_alert['channels'] ) && is_array( $stored_alert['channels'] )
					? array_values( array_intersect( $stored_alert['channels'], self::CHANNELS ) )
					: $default_alert['channels'],
			);

			// Preserve threshold for high_value_order.
			if ( 'high_value_order' === $type ) {
				$prefs['alerts'][ $type ]['threshold'] = isset( $stored_alert['threshold'] )
					? (float) $stored_alert['threshold']
					: $default_alert['threshold'];
			}
		}

		self::$cache[ $user_id ] = $prefs;
		return $prefs;
	}

	/**
	 * Validate and save preferences for a user.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $prefs   Preferences to save.
	 * @return bool True on success.
	 */
	public static function save( int $user_id, array $prefs ): bool {
		$clean = array();

		// Batching window: 1–60 minutes.
		$clean['batching_window_minutes'] = max( 1, min( 60, absint( $prefs['batching_window_minutes'] ?? 15 ) ) );

		// Auto-reply store email (optional override for review auto-replies).
		$clean['auto_reply_store_email'] = sanitize_email( $prefs['auto_reply_store_email'] ?? '' );

		// Digest settings.
		$digest = $prefs['digest'] ?? array();
		$clean['digest'] = array(
			'enabled'   => ! empty( $digest['enabled'] ),
			'frequency' => in_array( $digest['frequency'] ?? '', self::DIGEST_FREQUENCIES, true )
				? $digest['frequency']
				: 'weekly',
			'day'       => in_array( strtolower( $digest['day'] ?? '' ), self::DIGEST_DAYS, true )
				? strtolower( $digest['day'] )
				: 'monday',
			'time'      => preg_match( '/^\d{2}:\d{2}$/', $digest['time'] ?? '' )
				? sanitize_text_field( $digest['time'] )
				: '09:00',
			'channels'  => ! empty( $digest['channels'] ) && is_array( $digest['channels'] )
				? array_values( array_intersect( $digest['channels'], self::CHANNELS ) )
				: array( 'email' ),
		);

		// Per-alert-type settings.
		$clean['alerts'] = array();
		$defaults = self::get_defaults();

		foreach ( self::ALERT_TYPES as $type ) {
			$alert = $prefs['alerts'][ $type ] ?? array();
			$clean['alerts'][ $type ] = array(
				'enabled'  => isset( $alert['enabled'] ) ? (bool) $alert['enabled'] : $defaults['alerts'][ $type ]['enabled'],
				'channels' => isset( $alert['channels'] ) && is_array( $alert['channels'] )
					? array_values( array_intersect( $alert['channels'], self::CHANNELS ) )
					: $defaults['alerts'][ $type ]['channels'],
			);

			if ( 'high_value_order' === $type ) {
				$clean['alerts'][ $type ]['threshold'] = max( 0, (float) ( $alert['threshold'] ?? 500 ) );
			}
		}

		unset( self::$cache[ $user_id ] );
		return (bool) update_user_meta( $user_id, self::META_KEY, $clean );
	}

	/**
	 * Check if an alert type is enabled in the user's preferences.
	 *
	 * This only checks the user's preference toggle — channel availability
	 * and entitlement gating are handled by get_alert_channels().
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $event_type Alert event type.
	 * @return bool
	 */
	public static function is_alert_enabled( int $user_id, string $event_type ): bool {
		if ( ! in_array( $event_type, self::ALERT_TYPES, true ) ) {
			return false;
		}

		$prefs = self::get( $user_id );
		return ! empty( $prefs['alerts'][ $event_type ]['enabled'] );
	}

	/**
	 * Get notification channels for an alert type, filtered by entitlements.
	 *
	 * Free tier: returns empty array (in-chat only).
	 * Pro+: email + telegram.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $event_type Alert event type.
	 * @return array List of channel names.
	 */
	public static function get_alert_channels( int $user_id, string $event_type ): array {
		if ( ! in_array( $event_type, self::ALERT_TYPES, true ) ) {
			return array();
		}

		$prefs    = self::get( $user_id );
		$channels = $prefs['alerts'][ $event_type ]['channels'] ?? array();

		if ( empty( $channels ) ) {
			return array();
		}

		// Filter by entitlements.
		$tier = self::get_user_tier( $user_id );

		if ( ! PressArk_Entitlements::can_use_feature( $tier, 'watchdog_alerts' ) ) {
			return array();
		}

		// Pro+ gets both email and telegram.
		$allowed = array( 'email', 'telegram' );

		return array_values( array_intersect( $channels, $allowed ) );
	}

	/**
	 * Get the high-value order threshold for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return float Threshold amount.
	 */
	public static function get_high_value_threshold( int $user_id ): float {
		$prefs = self::get( $user_id );
		return (float) ( $prefs['alerts']['high_value_order']['threshold'] ?? 500 );
	}

	/**
	 * Get the batching window in minutes for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Minutes.
	 */
	public static function get_batching_window( int $user_id ): int {
		$prefs = self::get( $user_id );
		return max( 1, absint( $prefs['batching_window_minutes'] ?? 15 ) );
	}

	/**
	 * Get the store support email for auto-reply templates.
	 *
	 * Falls back to WooCommerce store email, then admin email.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Sanitized email address.
	 */
	public static function get_auto_reply_email( int $user_id ): string {
		$prefs = self::get( $user_id );
		$email = $prefs['auto_reply_store_email'] ?? '';
		if ( empty( $email ) ) {
			$email = get_option( 'woocommerce_email_from_address', '' );
			if ( empty( $email ) ) {
				$email = get_option( 'admin_email', '' );
			}
		}
		return sanitize_email( $email );
	}

	/**
	 * Resolve the site's current tier.
	 *
	 * PressArk licensing is site-wide, not per-user. The $user_id parameter
	 * is accepted for future extensibility but currently unused.
	 *
	 * @param int $user_id WordPress user ID (unused, reserved for future).
	 * @return string Tier name.
	 */
	private static function get_user_tier( int $user_id ): string {
		$license = new PressArk_License();
		return $license->get_tier();
	}
}
