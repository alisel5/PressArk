<?php
/**
 * PressArk Watchdog Alerter — Lightweight, non-AI alert dispatcher.
 *
 * Fires instant notifications when WooCommerce events occur.
 * No token consumption, no async queue — direct synchronous dispatch
 * with MySQL-backed atomic batching to prevent spam on high-volume stores.
 *
 * v5.2.0: Initial implementation with transient-based batching.
 * v5.2.1: Replaced transients with atomic MySQL table to fix race
 *         conditions under concurrent load. Added stale batch recovery
 *         so missed cron/Action Scheduler callbacks self-heal.
 *
 * @package PressArk
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Watchdog_Alerter {

	/** Action Scheduler / WP Cron hook name for batch flush. */
	public const FLUSH_HOOK = 'pressark_flush_alert_batch';

	/**
	 * Return the DDL for the alert_batches table.
	 *
	 * Called by the migrator (dbDelta) — kept here next to the class
	 * that owns the table, following the pattern of PressArk_Cost_Ledger.
	 *
	 * @return string CREATE TABLE statement.
	 */
	public static function get_schema(): string {
		global $wpdb;
		$table           = $wpdb->prefix . 'pressark_alert_batches';
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			event_type VARCHAR(50) NOT NULL,
			object_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			event_data TEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			batch_key VARCHAR(120) NOT NULL,
			PRIMARY KEY (id),
			KEY idx_batch_key (batch_key),
			KEY idx_created_at (created_at)
		) {$charset_collate};";
	}

	/**
	 * Main entry point — called from WC_Events after logging.
	 *
	 * Before processing the new event, recovers any stale batches
	 * that were never flushed (missed cron). Then handles the new event
	 * with atomic cooldown + batch logic.
	 *
	 * @param string $event_type Event type identifier.
	 * @param int    $object_id  WC object ID (order, product, comment).
	 * @param array  $data       Event data array.
	 */
	public static function fire( string $event_type, int $object_id, array $data ): void {
		// Self-heal: flush any stale batches before processing the new event.
		self::recover_stale_batches();

		$admin_users = self::get_pressark_admins();

		foreach ( $admin_users as $user ) {
			$user_id = (int) $user->ID;

			if ( ! PressArk_Watchdog_Preferences::is_alert_enabled( $user_id, $event_type ) ) {
				continue;
			}

			$channels = PressArk_Watchdog_Preferences::get_alert_channels( $user_id, $event_type );
			if ( empty( $channels ) ) {
				continue;
			}

			if ( 'high_value_order' === $event_type ) {
				$threshold = PressArk_Watchdog_Preferences::get_high_value_threshold( $user_id );
				if ( ( $data['total'] ?? 0 ) < $threshold ) {
					continue;
				}
			}

			$window_minutes = PressArk_Watchdog_Preferences::get_batching_window( $user_id );
			$batch_key      = self::make_batch_key( $user_id, $event_type );

			// Atomic cooldown check: try to claim the "first sender" slot.
			if ( self::atomic_claim_cooldown( $batch_key, $window_minutes ) ) {
				// We claimed cooldown — send this event immediately.
				$alert = self::format_alert( $event_type, $data );
				self::dispatch_to_channels( $channels, $user_id, $alert );

				// Schedule flush for anything that accumulates during cooldown.
				self::schedule_flush( $user_id, $event_type, $window_minutes );
			} else {
				// Cooldown active — atomically append to batch.
				self::atomic_append( $user_id, $event_type, $object_id, $data, $batch_key );
			}
		}

		// After instant alert dispatch, check for event-triggered automations.
		self::dispatch_triggered_automations( $event_type, $object_id, $data );
	}

	/**
	 * Handle scheduled batch flush.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $event_type Event type identifier.
	 */
	public static function handle_flush_batch( int $user_id, string $event_type ): void {
		self::flush_batch( $user_id, $event_type );
	}

	/**
	 * Flush pending batch for a user + event type.
	 *
	 * Atomically claims all pending rows by deleting them in a single
	 * statement and reading back via RETURNING (MySQL 8.0.21+) or a
	 * SELECT-then-DELETE with an advisory lock for older MySQL.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $event_type Event type identifier.
	 */
	public static function flush_batch( int $user_id, string $event_type ): void {
		global $wpdb;
		$table     = $wpdb->prefix . 'pressark_alert_batches';
		$batch_key = self::make_batch_key( $user_id, $event_type );

		if ( ! self::table_ready() ) {
			return;
		}

		// Acquire advisory lock to serialize flush operations and block
		// concurrent appends from inserting between SELECT and DELETE.
		$lock_name = 'pressark_ab_' . $user_id . '_' . $event_type;
		if ( ! self::acquire_lock( $lock_name ) ) {
			return; // Another process is flushing — safe to skip.
		}

		try {
			// Select only real event rows — exclude cooldown sentinel (object_id = 0).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Watchdog batching intentionally reads internal alert-batch rows under a flush lock; table name is internal and caching is not relevant.
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, object_id, event_data, created_at
				 FROM {$table}
				 WHERE batch_key = %s AND object_id > 0
				 ORDER BY created_at ASC",
				$batch_key
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( empty( $rows ) ) {
				// No batched events — delete cooldown so next event sends immediately.
				self::delete_cooldown( $batch_key );
				return;
			}

			// Collect IDs for atomic delete.
			$ids = wp_list_pluck( $rows, 'id' );

			// Delete the claimed rows atomically.
			$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Watchdog batching intentionally deletes claimed internal batch rows using a placeholder list derived from previously selected row IDs.
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE id IN ({$id_placeholders})",
				...$ids
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			// Check if any NEW rows arrived during our flush (late appends).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Watchdog batching intentionally rechecks for late-arriving internal batch rows before releasing cooldown.
			$remaining = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE batch_key = %s AND object_id > 0",
				$batch_key
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $remaining > 0 ) {
				// Late arrivals exist — re-schedule flush, keep cooldown active.
				self::schedule_flush( $user_id, $event_type,
					PressArk_Watchdog_Preferences::get_batching_window( $user_id )
				);
			} else {
				// No stragglers — delete cooldown so next event sends immediately.
				self::delete_cooldown( $batch_key );
			}

			// Build events array for formatting.
			$events = array();
			foreach ( $rows as $row ) {
				$decoded = json_decode( $row->event_data, true );
				if ( is_array( $decoded ) ) {
					$events[] = array(
						'object_id' => (int) $row->object_id,
						'data'      => $decoded,
						'time'      => strtotime( $row->created_at ),
					);
				}
			}

			if ( empty( $events ) ) {
				return;
			}

			$channels = PressArk_Watchdog_Preferences::get_alert_channels( $user_id, $event_type );
			if ( empty( $channels ) ) {
				return;
			}

			$window = PressArk_Watchdog_Preferences::get_batching_window( $user_id );

			if ( count( $events ) === 1 ) {
				$alert = self::format_alert( $event_type, $events[0]['data'] );
			} else {
				$alert = self::format_batch( $event_type, $events, $window );
			}

			self::dispatch_to_channels( $channels, $user_id, $alert );
		} finally {
			self::release_lock( $lock_name );
		}
	}

	/**
	 * Recover stale batches that were never flushed.
	 *
	 * Queries for distinct batch_keys whose oldest row is older than
	 * (batching_window + 2 minutes). For each, runs flush_batch().
	 * This self-heals if the scheduled action never fires.
	 *
	 * Runs at most once per request via static flag.
	 */
	private static function recover_stale_batches(): void {
		static $recovered = false;
		if ( $recovered ) {
			return;
		}
		$recovered = true;

		global $wpdb;
		$table = $wpdb->prefix . 'pressark_alert_batches';

		if ( ! self::table_ready() ) {
			return;
		}

		// Find batches where the oldest entry exceeds the maximum
		// possible window (60 min max) + 2 min grace = 62 min.
		// Per-user thresholds are checked inside flush_batch().
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 62 * 60 ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Watchdog recovery intentionally scans grouped internal alert batches for stale entries.
		$stale = $wpdb->get_results( $wpdb->prepare(
			"SELECT batch_key, MIN(created_at) AS oldest
			 FROM {$table}
			 GROUP BY batch_key
			 HAVING oldest < %s
			 LIMIT 20",
			$cutoff
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $stale ) ) {
			return;
		}

		foreach ( $stale as $row ) {
			// Parse user_id and event_type from batch_key.
			$parts = self::parse_batch_key( $row->batch_key );
			if ( $parts ) {
				self::flush_batch( $parts['user_id'], $parts['event_type'] );
			}
		}
	}

	// ── Atomic storage helpers ─────────────────────────────────────

	/**
	 * Atomically claim the cooldown slot for a batch_key.
	 *
	 * Uses a MySQL advisory lock to serialize the check-then-insert
	 * sequence, preventing two concurrent fire() calls from both
	 * claiming the cooldown and both sending immediately.
	 *
	 * The cooldown row is deleted by flush_batch() when the window ends.
	 *
	 * @param string $batch_key      Composite key.
	 * @param int    $window_minutes Batching window.
	 * @return bool True if cooldown was claimed (send immediately).
	 */
	private static function atomic_claim_cooldown( string $batch_key, int $window_minutes ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'pressark_alert_batches';

		if ( ! self::table_ready() ) {
			return true; // Table not ready — degrade to immediate send.
		}

		// Use advisory lock to serialize the check+insert sequence.
		$lock_name = 'pressark_cd_' . md5( $batch_key );
		if ( ! self::acquire_lock( $lock_name ) ) {
			return false; // Couldn't get lock — treat as "in cooldown" (safe).
		}

		try {
			// Check for an existing cooldown sentinel (object_id = 0).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cooldown claiming intentionally probes the internal alert-batch table under a lock.
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table}
				 WHERE batch_key = %s AND object_id = 0
				 LIMIT 1",
				$batch_key
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $existing ) {
				return false; // Cooldown active.
			}

			// Insert the sentinel.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, array(
				'user_id'    => 0,
				'event_type' => '_cooldown',
				'object_id'  => 0,
				'event_data' => wp_json_encode( array( 'window' => $window_minutes ) ),
				'batch_key'  => $batch_key,
			) );

			return (int) $wpdb->rows_affected > 0;
		} finally {
			self::release_lock( $lock_name );
		}
	}

	/**
	 * Delete the cooldown sentinel for a batch_key.
	 *
	 * @param string $batch_key Composite key.
	 */
	private static function delete_cooldown( string $batch_key ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'pressark_alert_batches';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cooldown cleanup intentionally deletes the internal sentinel row for one batch key.
		$wpdb->delete( $table, array(
			'batch_key' => $batch_key,
			'object_id' => 0,
		) );
	}

	/**
	 * Atomically append an event to the batch.
	 *
	 * Acquires the same flush lock so the INSERT cannot land between
	 * flush_batch()'s SELECT and DELETE, which would strand the row.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $event_type Event type.
	 * @param int    $object_id  WC object ID.
	 * @param array  $data       Event data.
	 * @param string $batch_key  Composite key.
	 */
	private static function atomic_append( int $user_id, string $event_type, int $object_id, array $data, string $batch_key ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'pressark_alert_batches';

		if ( ! self::table_ready() ) {
			return;
		}

		// Acquire the same lock used by flush_batch() so the insert
		// cannot land between flush's SELECT and DELETE.
		$lock_name = 'pressark_ab_' . $user_id . '_' . $event_type;
		if ( ! self::acquire_lock( $lock_name ) ) {
			// Lock contention — insert without lock (worst case: row is
			// stranded and recovered by recover_stale_batches).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, array(
				'user_id'    => $user_id,
				'event_type' => $event_type,
				'object_id'  => $object_id,
				'event_data' => wp_json_encode( $data ),
				'batch_key'  => $batch_key,
			) );
			return;
		}

		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, array(
				'user_id'    => $user_id,
				'event_type' => $event_type,
				'object_id'  => $object_id,
				'event_data' => wp_json_encode( $data ),
				'batch_key'  => $batch_key,
			) );
		} finally {
			self::release_lock( $lock_name );
		}
	}

	// ── Locking ────────────────────────────────────────────────────

	/**
	 * Acquire a MySQL advisory lock for serializing flush operations.
	 *
	 * @param string $lock_name Lock name.
	 * @return bool True if acquired.
	 */
	private static function acquire_lock( string $lock_name ): bool {
		global $wpdb;
		// 2 second timeout — fail fast under contention.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Watchdog batching intentionally uses MySQL advisory locks to serialize flush and append operations.
		$got = $wpdb->get_var( $wpdb->prepare(
			"SELECT GET_LOCK(%s, 2)",
			$lock_name
		) );
		return (int) $got === 1;
	}

	/**
	 * Release a MySQL advisory lock.
	 *
	 * @param string $lock_name Lock name.
	 */
	private static function release_lock( string $lock_name ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Watchdog batching intentionally releases the matching MySQL advisory lock.
		$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK(%s)", $lock_name ) );
	}

	// ── Batch key helpers ──────────────────────────────────────────

	/**
	 * Build a composite batch key from user_id + event_type.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $event_type Event type.
	 * @return string Batch key.
	 */
	private static function make_batch_key( int $user_id, string $event_type ): string {
		return 'u' . $user_id . '_' . $event_type;
	}

	/**
	 * Parse a batch key back into user_id and event_type.
	 *
	 * @param string $batch_key Composite key.
	 * @return array{user_id: int, event_type: string}|null Parsed parts or null.
	 */
	private static function parse_batch_key( string $batch_key ): ?array {
		if ( ! preg_match( '/^u(\d+)_(.+)$/', $batch_key, $m ) ) {
			return null;
		}
		return array(
			'user_id'    => (int) $m[1],
			'event_type' => $m[2],
		);
	}

	// ── Table readiness ────────────────────────────────────────────

	/**
	 * Check if the alert_batches table exists.
	 *
	 * Cached per-request to avoid repeated SHOW TABLES queries.
	 *
	 * @return bool True if the table is usable.
	 */
	private static function table_ready(): bool {
		static $ready = null;
		if ( null !== $ready ) {
			return $ready;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pressark_alert_batches';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table readiness intentionally checks information_schema for the internal alert-batch table.
		$ready = (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
			DB_NAME,
			$table
		) );
		return $ready;
	}

	// ── Scheduling ─────────────────────────────────────────────────

	/**
	 * Schedule the batch flush at the end of the cooldown window.
	 *
	 * @param int    $user_id        WordPress user ID.
	 * @param string $event_type     Event type.
	 * @param int    $window_minutes Batching window in minutes.
	 */
	private static function schedule_flush( int $user_id, string $event_type, int $window_minutes ): void {
		$timestamp = time() + ( $window_minutes * 60 );
		$args      = array( $user_id, $event_type );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( ! as_has_scheduled_action( self::FLUSH_HOOK, $args ) ) {
				as_schedule_single_action( $timestamp, self::FLUSH_HOOK, $args );
			}
		} else {
			if ( ! wp_next_scheduled( self::FLUSH_HOOK, $args ) ) {
				wp_schedule_single_event( $timestamp, self::FLUSH_HOOK, $args );
			}
		}
	}

	// ── Notification dispatch ──────────────────────────────────────

	/**
	 * Get all admin users with PressArk access.
	 *
	 * @return \WP_User[] Array of user objects.
	 */
	private static function get_pressark_admins(): array {
		return get_users( array(
			'role__in' => array( 'administrator', 'shop_manager' ),
			'fields'   => 'all',
		) );
	}

	/**
	 * Dispatch an alert to the specified notification channels.
	 *
	 * @param array $channels Array of channel names.
	 * @param int   $user_id  WordPress user ID.
	 * @param array $alert    Array with 'subject' and 'body' keys.
	 */
	private static function dispatch_to_channels( array $channels, int $user_id, array $alert ): void {
		foreach ( $channels as $channel ) {
			$target = self::resolve_channel_target( $channel, $user_id );
			if ( empty( $target ) ) {
				continue;
			}

			PressArk_Notification_Manager::send(
				$channel,
				$target,
				$alert['subject'],
				$alert['body'],
				array( 'admin_url' => admin_url( 'admin.php?page=pressark' ) )
			);
		}
	}

	// ── Event-triggered automations ───────────────────────────────

	/**
	 * Valid event_trigger values for the automations table.
	 */
	public const VALID_EVENT_TRIGGERS = array(
		'order_failed',
		'order_cancelled',
		'refund_issued',
		'low_stock',
		'out_of_stock',
		'negative_review',
		'high_value_order',
	);

	/**
	 * Dispatch automations configured to trigger on a specific event.
	 *
	 * Called after instant alert dispatch in fire(). Non-blocking because
	 * dispatch_one enqueues to the async task queue rather than running
	 * AI inline.
	 *
	 * @param string $event_type Event type identifier.
	 * @param int    $object_id  WC object ID.
	 * @param array  $data       Event data array.
	 */
	public static function dispatch_triggered_automations( string $event_type, int $object_id, array $data ): void {
		if ( ! in_array( $event_type, self::VALID_EVENT_TRIGGERS, true ) ) {
			return;
		}

		// Global rate limit: max 20 event-triggered dispatches per minute
		// across all event types to prevent DoS from bulk stock changes.
		$rate_key = 'pressark_evt_dispatch_count';
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 20 ) {
			PressArk_Error_Tracker::warning( 'WatchdogAlerter', 'Event trigger global rate limit reached', array(
				'event_type' => $event_type,
			) );
			return;
		}

		$store       = new PressArk_Automation_Store();
		$automations = $store->find_by_event_trigger( $event_type );

		if ( empty( $automations ) ) {
			return;
		}

		// Cap dispatches per event to prevent DoS on high-volume stores.
		$max_dispatches = 5;
		$dispatched     = 0;

		foreach ( $automations as $automation ) {
			if ( $dispatched >= $max_dispatches ) {
				PressArk_Error_Tracker::warning( 'WatchdogAlerter', 'Event trigger dispatch cap reached', array(
					'event_type' => $event_type,
					'capped_at'  => $max_dispatches,
				) );
				break;
			}

			$automation_id = $automation['automation_id'];
			$cooldown      = (int) ( $automation['event_trigger_cooldown'] ?? 3600 );

			// Atomic cooldown check + claim — prevents double-dispatch.
			if ( ! $store->claim_event_trigger( $automation_id, $cooldown ) ) {
				PressArk_Error_Tracker::debug( 'WatchdogAlerter', 'Event trigger skipped: cooldown active', array(
					'automation_id' => $automation_id,
					'event_type'    => $event_type,
				) );
				continue;
			}

			// Check entitlements — event triggers are Team+ feature.
			$user_id = $automation['user_id'];
			$previous_user = get_current_user_id();
			wp_set_current_user( $user_id );

			$license = new PressArk_License();
			$tier    = $license->get_tier();

			if ( ! PressArk_Entitlements::can_use_feature( $tier, 'watchdog_triggers' ) ) {
				if ( $previous_user > 0 ) {
					wp_set_current_user( $previous_user );
				}
				PressArk_Error_Tracker::debug( 'WatchdogAlerter', 'Event trigger skipped: tier insufficient', array(
					'automation_id' => $automation_id,
					'tier'          => $tier,
				) );
				continue;
			}

			// Sanitize event data for prompt injection safety.
			$safe_data = self::sanitize_event_data_for_prompt( $event_type, $data );

			// Inject store support email for negative_review auto-reply templates.
			if ( 'negative_review' === $event_type ) {
				$store_email = PressArk_Watchdog_Preferences::get_auto_reply_email( (int) $automation['user_id'] );
				$safe_data['store_support_email'] = sanitize_email( $store_email );
			}

			// Build the event-context prompt override.
			$event_context = sprintf(
				"EVENT CONTEXT: A %s event just occurred (object ID: %d). Details: %s. Analyze this event and take appropriate action based on the automation instructions below.\n\n%s",
				sanitize_text_field( $event_type ),
				$object_id,
				wp_json_encode( $safe_data ),
				$automation['prompt']
			);

			try {
				PressArk_Automation_Dispatcher::dispatch_one(
					$automation,
					$store,
					true,           // skip_claim — event triggers bypass cron claim
					$event_context  // prompt_override — prepend event context
				);
				$dispatched++;

				// Increment global rate limiter (60-second window).
				set_transient( $rate_key, $count + $dispatched, 60 );

				PressArk_Error_Tracker::info( 'WatchdogAlerter', 'Event-triggered automation dispatched', array(
					'automation_id' => $automation_id,
					'event_type'    => $event_type,
					'object_id'     => $object_id,
				) );
			} catch ( \Throwable $e ) {
				PressArk_Error_Tracker::error( 'WatchdogAlerter', 'Event-triggered dispatch failed', array(
					'automation_id' => $automation_id,
					'event_type'    => $event_type,
					'error'         => $e->getMessage(),
				) );
			} finally {
				if ( $previous_user > 0 ) {
					wp_set_current_user( $previous_user );
				}
			}
		}
	}

	/**
	 * Sanitize event data before injecting into AI prompt.
	 *
	 * Strips HTML, limits string lengths, and only allows known safe keys
	 * to prevent prompt injection via malicious WC data (e.g. crafted
	 * review text or customer names).
	 *
	 * @param string $event_type Event type.
	 * @param array  $data       Raw event data.
	 * @return array Sanitized data safe for prompt injection.
	 */
	private static function sanitize_event_data_for_prompt( string $event_type, array $data ): array {
		// Allowlist of keys per event type.
		$allowed_keys = array(
			'order_failed'     => array( 'number', 'total', 'customer', 'old_status', 'customer_ip', 'payment_method', 'gateway_error' ),
			'order_cancelled'  => array( 'number', 'total', 'customer', 'old_status', 'customer_ip' ),
			'refund_issued'    => array( 'number', 'amount', 'reason', 'customer' ),
			'low_stock'        => array( 'name', 'stock', 'threshold' ),
			'out_of_stock'     => array( 'name' ),
			'negative_review'  => array( 'product_name', 'product_id', 'rating', 'excerpt', 'reviewer', 'review_id', 'store_support_email' ),
			'high_value_order' => array( 'number', 'total', 'customer', 'items_count' ),
		);

		$keys = $allowed_keys[ $event_type ] ?? array();
		$safe = array();

		foreach ( $keys as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				continue;
			}

			$value = $data[ $key ];

			if ( is_string( $value ) ) {
				// Strip HTML, collapse whitespace, remove newlines, limit length.
				// Prevents prompt injection via crafted review text or names.
				$value = wp_strip_all_tags( $value );
				$value = preg_replace( '/[\r\n]+/', ' ', $value );
				$value = preg_replace( '/\s{2,}/', ' ', $value );
				$value = sanitize_text_field( mb_substr( trim( $value ), 0, 150 ) );
			} elseif ( is_numeric( $value ) ) {
				$value = is_float( $value ) ? (float) $value : (int) $value;
			} else {
				continue; // Skip unexpected types.
			}

			$safe[ $key ] = $value;
		}

		return $safe;
	}

	/**
	 * Resolve the notification target for a channel + user.
	 *
	 * @param string $channel Channel name.
	 * @param int    $user_id WordPress user ID.
	 * @return string Target identifier.
	 */
	private static function resolve_channel_target( string $channel, int $user_id ): string {
		switch ( $channel ) {
			case 'email':
				$user = get_userdata( $user_id );
				return $user ? $user->user_email : '';
			case 'telegram':
				return PressArk_Notification_Manager::get_user_telegram_id( $user_id );
			default:
				return '';
		}
	}

	// ── Cross-event pattern detection ─────────────────────────────

	/**
	 * Analyze a batch of events for cross-event patterns.
	 *
	 * Detects:
	 * - Same IP across multiple failed orders (card testing)
	 * - Same customer email across multiple failures (payment method issue)
	 * - Same gateway error message (systemic gateway problem)
	 * - Velocity anomaly (many events in a very short window)
	 *
	 * @param string $event_type Event type.
	 * @param array  $events     Array of batch entries with 'data' key.
	 * @return array Array of pattern strings to append to the batch alert.
	 */
	private static function detect_patterns( string $event_type, array $events ): array {
		$patterns = array();
		$count    = count( $events );

		if ( $count < 2 ) {
			return $patterns;
		}

		// Only run pattern detection on order-related events.
		if ( ! in_array( $event_type, array( 'order_failed', 'order_cancelled', 'refund_issued' ), true ) ) {
			return $patterns;
		}

		// Group by IP.
		$ips = array();
		foreach ( $events as $entry ) {
			$ip = $entry['data']['customer_ip'] ?? '';
			if ( $ip && '::1' !== $ip && '127.0.0.1' !== $ip ) {
				$ips[ $ip ] = ( $ips[ $ip ] ?? 0 ) + 1;
			}
		}

		// Detect IP clustering — 3+ failures from same IP is suspicious.
		foreach ( $ips as $ip => $ip_count ) {
			if ( $ip_count >= 3 ) {
				$patterns[] = sprintf(
					'POSSIBLE CARD TESTING: %d of %d failures share IP %s — consider blocking this IP',
					$ip_count,
					$count,
					self::mask_ip( $ip )
				);
			}
		}

		// Group by customer email.
		$emails = array();
		foreach ( $events as $entry ) {
			$email = $entry['data']['customer'] ?? '';
			if ( $email ) {
				$emails[ $email ] = ( $emails[ $email ] ?? 0 ) + 1;
			}
		}

		foreach ( $emails as $email => $email_count ) {
			if ( $email_count >= 2 ) {
				$patterns[] = sprintf(
					'Same customer (%s) failed %d times — likely payment method issue, not fraud',
					$email,
					$email_count
				);
			}
		}

		// Group by gateway error.
		$errors = array();
		foreach ( $events as $entry ) {
			$error = $entry['data']['gateway_error'] ?? '';
			if ( $error ) {
				// Normalize: lowercase, take first 50 chars for grouping.
				$key = mb_substr( strtolower( $error ), 0, 50 );
				$errors[ $key ] = ( $errors[ $key ] ?? 0 ) + 1;
			}
		}

		foreach ( $errors as $error_key => $error_count ) {
			if ( $error_count >= 2 ) {
				$patterns[] = sprintf(
					'Gateway pattern: %d orders hit the same error — "%s" — possible gateway issue',
					$error_count,
					mb_substr( $error_key, 0, 80 )
				);
			}
		}

		// Velocity check — if all events happened within 5 minutes, flag it.
		if ( $count >= 3 ) {
			$times = array_column( $events, 'time' );
			$times = array_filter( $times );
			if ( ! empty( $times ) ) {
				$span = max( $times ) - min( $times );
				if ( $span <= 300 ) {
					$patterns[] = sprintf(
						'High velocity: %d failures in %d seconds — automated attack likely',
						$count,
						$span
					);
				}
			}
		}

		return $patterns;
	}

	/**
	 * Mask an IP address for privacy — show first two octets only.
	 *
	 * @param string $ip IP address.
	 * @return string Masked IP like "185.234.x.x".
	 */
	private static function mask_ip( string $ip ): string {
		$parts = explode( '.', $ip );
		if ( count( $parts ) === 4 ) {
			return $parts[0] . '.' . $parts[1] . '.x.x';
		}
		// IPv6 or other — just show first 10 chars.
		return mb_substr( $ip, 0, 10 ) . '...';
	}

	// ── Message formatting ─────────────────────────────────────────

	/**
	 * Format a single alert notification.
	 *
	 * @param string $event_type Event type.
	 * @param array  $data       Event data.
	 * @return array Array with 'subject' and 'body' keys.
	 */
	public static function format_alert( string $event_type, array $data ): array {
		$number   = sanitize_text_field( $data['number'] ?? '' );
		$total    = isset( $data['total'] ) ? number_format( (float) $data['total'], 2 ) : '0.00';
		$customer = sanitize_text_field( $data['customer'] ?? '' );
		$name     = sanitize_text_field( $data['name'] ?? '' );
		$stock    = absint( $data['stock'] ?? 0 );
		$amount   = isset( $data['amount'] ) ? number_format( (float) $data['amount'], 2 ) : '0.00';
		$rating   = absint( $data['rating'] ?? 0 );
		$excerpt  = sanitize_text_field( $data['excerpt'] ?? '' );
		$product  = sanitize_text_field( $data['product_name'] ?? '' );

		switch ( $event_type ) {
			case 'order_failed':
				return array(
					'subject' => "⚠️ Order #{$number} failed — \${$total} lost",
					'body'    => "Order #{$number} has failed.\n\nTotal: \${$total}\nCustomer: {$customer}\n\nCheck WooCommerce for details.",
				);
			case 'order_cancelled':
				return array(
					'subject' => "Order #{$number} cancelled by {$customer}",
					'body'    => "Order #{$number} was cancelled.\n\nTotal: \${$total}\nCustomer: {$customer}",
				);
			case 'refund_issued':
				$reason = sanitize_text_field( $data['reason'] ?? '' );
				return array(
					'subject' => "💰 Refund issued: \${$amount} on Order #{$number}",
					'body'    => "A refund of \${$amount} was issued on Order #{$number}.\n\nCustomer: {$customer}"
						. ( $reason ? "\nReason: {$reason}" : '' ),
				);
			case 'low_stock':
				return array(
					'subject' => "📦 Low stock: {$name} — {$stock} units remaining",
					'body'    => "Product \"{$name}\" is running low on stock.\n\nRemaining: {$stock} units\n\nConsider restocking soon.",
				);
			case 'out_of_stock':
				return array(
					'subject' => "🚨 Out of stock: {$name}",
					'body'    => "Product \"{$name}\" is now out of stock.\n\nCustomers will not be able to purchase this item until stock is replenished.",
				);
			case 'negative_review':
				return array(
					'subject' => "⭐ {$rating}-star review on {$product}: \"{$excerpt}\"",
					'body'    => "A {$rating}-star review was posted on \"{$product}\".\n\nExcerpt: \"{$excerpt}\"\nReviewer: " . sanitize_text_field( $data['reviewer'] ?? '' ),
				);
			case 'high_value_order':
				$items = absint( $data['items_count'] ?? 0 );
				return array(
					'subject' => "🎉 High-value order #{$number}: \${$total} from {$customer}",
					'body'    => "A high-value order has been placed!\n\nOrder: #{$number}\nTotal: \${$total}\nItems: {$items}\nCustomer: {$customer}",
				);
			default:
				return array(
					'subject' => "PressArk Alert: {$event_type}",
					'body'    => wp_json_encode( $data ),
				);
		}
	}

	/**
	 * Format a batched notification.
	 *
	 * @param string $event_type     Event type.
	 * @param array  $events         Array of batch entries.
	 * @param int    $window_minutes Batching window used.
	 * @return array Array with 'subject' and 'body' keys.
	 */
	public static function format_batch( string $event_type, array $events, int $window_minutes = 15 ): array {
		$count  = count( $events );
		$window = $window_minutes;

		$total_amount = 0.0;
		$lines        = array();

		foreach ( $events as $entry ) {
			$d      = $entry['data'];
			$single = self::format_alert( $event_type, $d );
			$lines[] = '• ' . $single['subject'];

			if ( isset( $d['total'] ) ) {
				$total_amount += (float) $d['total'];
			}
			if ( isset( $d['amount'] ) ) {
				$total_amount += (float) $d['amount'];
			}
		}

		$formatted_total = number_format( $total_amount, 2 );
		$type_label      = str_replace( '_', ' ', $event_type );

		$subject = match ( $event_type ) {
			'order_failed'    => "⚠️ {$count} failed orders in the last {$window} minutes — \${$formatted_total} total",
			'order_cancelled' => "{$count} cancelled orders in the last {$window} minutes — \${$formatted_total} total",
			'refund_issued'   => "💰 {$count} refunds in the last {$window} minutes — \${$formatted_total} total",
			'low_stock'       => "📦 {$count} products low on stock in the last {$window} minutes",
			'out_of_stock'    => "🚨 {$count} products out of stock in the last {$window} minutes",
			'negative_review' => "⭐ {$count} negative reviews in the last {$window} minutes",
			'high_value_order' => "🎉 {$count} high-value orders in the last {$window} minutes — \${$formatted_total} total",
			default            => "PressArk: {$count} {$type_label} alerts in the last {$window} minutes",
		};

		$body = "Batched alert summary:\n\n" . implode( "\n", $lines );

		// Cross-event pattern detection.
		$patterns = self::detect_patterns( $event_type, $events );
		if ( ! empty( $patterns ) ) {
			$body .= "\n\n--- Intelligence ---\n";
			foreach ( $patterns as $pattern ) {
				$body .= "\n" . $pattern;
			}

			// Upgrade subject for fraud indicators.
			if ( 'order_failed' === $event_type ) {
				$has_fraud_pattern = false;
				foreach ( $patterns as $p ) {
					if ( str_contains( $p, 'CARD TESTING' ) || str_contains( $p, 'automated attack' ) ) {
						$has_fraud_pattern = true;
						break;
					}
				}
				if ( $has_fraud_pattern ) {
					$subject = "FRAUD ALERT: {$count} failed orders in {$window} minutes — possible card testing attack";
				}
			}
		}

		// Safety truncation for Telegram's 4096-char limit.
		if ( mb_strlen( $body ) > 3900 ) {
			$body = mb_substr( $body, 0, 3900 ) . "\n\n…truncated. Check WooCommerce for full details.";
		}

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Clean up old batch rows that somehow survived past recovery.
	 *
	 * Should be called from the daily cleanup cron.
	 */
	public static function cleanup_old_batches(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'pressark_alert_batches';

		if ( ! self::table_ready() ) {
			return;
		}

		// Delete anything older than 24 hours — batches should never live this long.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Housekeeping intentionally performs a bounded delete against the internal alert-batch table.
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE created_at < %s LIMIT 500",
			gmdate( 'Y-m-d H:i:s', time() - 86400 )
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
