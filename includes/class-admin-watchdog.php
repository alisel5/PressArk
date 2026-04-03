<?php
/**
 * PressArk Admin Watchdog — WooCommerce alert configuration UI.
 *
 * Renders the Watchdog admin page with toggle-based alert configuration,
 * digest settings, and recent event display. AJAX handlers for saving
 * preferences and triggering test digests.
 *
 * @package PressArk
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Admin_Watchdog {

	/** Rate-limit transient key for test digest. */
	private const TEST_DIGEST_COOLDOWN = 'pressark_test_digest_cooldown';

	/**
	 * Register submenu and AJAX handlers.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'wp_ajax_pressark_save_watchdog_prefs', array( __CLASS__, 'handle_save_preferences' ) );
		add_action( 'wp_ajax_pressark_test_digest', array( __CLASS__, 'handle_test_digest' ) );
	}

	/**
	 * Register the Watchdog submenu page — only when WooCommerce is active.
	 */
	public function add_submenu(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_submenu_page(
			'pressark',
			__( 'Watchdog Alerts', 'pressark' ),
			__( 'Watchdog', 'pressark' ),
			'pressark_manage_settings',
			'pressark-watchdog',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the full Watchdog admin page.
	 */
	public function render_page(): void {
		if ( ! PressArk_Capabilities::current_user_can_manage_settings() ) {
			return;
		}

		$user_id  = get_current_user_id();
		$prefs    = PressArk_Watchdog_Preferences::get( $user_id );
		$license  = new PressArk_License();
		$tier     = $license->get_tier();
		$is_free  = ! PressArk_Entitlements::is_paid_tier( $tier );
		$has_telegram = '' !== PressArk_Notification_Manager::get_user_telegram_id( $user_id );
		$nonce    = wp_create_nonce( 'pressark_watchdog_nonce' );
		$events   = PressArk_WC_Events::get_events( 20, true );

		?>
		<div class="wrap pressark-watchdog-wrap">
			<h1 style="display:flex;align-items:center;gap:10px;margin-bottom:24px;">
				<span style="font-size:28px;"><?php echo pressark_icon( 'shield', 28 ); ?></span>
				<?php esc_html_e( 'Watchdog', 'pressark' ); ?>
			</h1>

			<?php self::render_alerts_section( $prefs, $is_free, $has_telegram ); ?>
			<?php self::render_digest_section( $prefs, $is_free, $has_telegram ); ?>
			<?php self::render_auto_reply_notice( $user_id ); ?>
			<?php self::render_events_section( $events ); ?>

			<p style="margin-top:32px;color:#94A3B8;font-size:13px;">
				<?php esc_html_e( 'Watchdog monitors your WooCommerce store 24/7. Alerts are sent instantly. Digests are AI-powered summaries sent on your schedule.', 'pressark' ); ?>
			</p>
		</div>

		<?php self::render_inline_styles(); ?>
		<?php self::render_inline_script( $nonce ); ?>
		<?php
	}

	// ── Section Renderers ──────────────────────────────────────────

	/**
	 * Section 1 — Instant Alerts.
	 */
	private static function render_alerts_section( array $prefs, bool $is_free, bool $has_telegram ): void {
		$alert_types = array(
			'order_failed'     => array( 'icon' => pressark_icon( 'warning' ),     'name' => __( 'Failed Orders', 'pressark' ),     'desc' => __( 'Get notified when a payment fails', 'pressark' ) ),
			'order_cancelled'  => array( 'icon' => pressark_icon( 'xCircle' ),     'name' => __( 'Cancelled Orders', 'pressark' ),  'desc' => __( 'Know when customers cancel', 'pressark' ) ),
			'refund_issued'    => array( 'icon' => pressark_icon( 'dollar' ),      'name' => __( 'Refunds', 'pressark' ),            'desc' => __( 'Track every refund instantly', 'pressark' ) ),
			'low_stock'        => array( 'icon' => pressark_icon( 'package' ),     'name' => __( 'Low Stock', 'pressark' ),          'desc' => __( 'Catch low inventory before stockouts', 'pressark' ) ),
			'out_of_stock'     => array( 'icon' => pressark_icon( 'alertCircle' ), 'name' => __( 'Out of Stock', 'pressark' ),       'desc' => __( 'Know the moment a product sells out', 'pressark' ) ),
			'negative_review'  => array( 'icon' => pressark_icon( 'star' ),        'name' => __( 'Negative Reviews', 'pressark' ),   'desc' => __( 'Spot bad reviews before they pile up', 'pressark' ) ),
			'high_value_order' => array( 'icon' => pressark_icon( 'gift' ),        'name' => __( 'High-Value Orders', 'pressark' ),  'desc' => __( 'Celebrate big orders', 'pressark' ) ),
		);

		?>
		<div class="pressark-wd-card">
			<h2 style="margin:0 0 4px;font-size:17px;font-weight:600;color:#0F172A;">
				<?php esc_html_e( 'Instant Alerts', 'pressark' ); ?>
			</h2>
			<p style="margin:0 0 20px;color:#64748B;font-size:13px;">
				<?php esc_html_e( 'Events always appear in your PressArk chat, even on the free plan.', 'pressark' ); ?>
			</p>

			<?php foreach ( $alert_types as $type => $info ) :
				$alert_prefs = $prefs['alerts'][ $type ] ?? array();
				$enabled     = ! empty( $alert_prefs['enabled'] );
				$channels    = $alert_prefs['channels'] ?? array( 'email' );
				$has_email   = in_array( 'email', $channels, true );
				$has_tg      = in_array( 'telegram', $channels, true );
				$threshold   = $alert_prefs['threshold'] ?? 500;
			?>
			<div class="pressark-wd-alert-row" data-type="<?php echo esc_attr( $type ); ?>">
				<div class="pressark-wd-alert-info">
					<span class="pressark-wd-alert-icon"><?php echo $info['icon']; ?></span>
					<div>
						<div class="pressark-wd-alert-name"><?php echo esc_html( $info['name'] ); ?></div>
						<div class="pressark-wd-alert-desc"><?php echo esc_html( $info['desc'] ); ?></div>
					</div>
				</div>
				<div class="pressark-wd-alert-controls">
					<?php if ( 'high_value_order' === $type ) : ?>
						<span style="color:#64748B;font-size:13px;"><?php esc_html_e( 'above $', 'pressark' ); ?></span>
						<input type="number" class="pressark-wd-threshold" min="0" step="50" value="<?php echo esc_attr( $threshold ); ?>" style="width:80px;padding:4px 8px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;">
					<?php endif; ?>

					<div class="pressark-wd-channel-badges">
						<button type="button"
							class="pressark-wd-badge pressark-wd-badge-email <?php echo $has_email ? 'active' : ''; ?> <?php echo $is_free ? 'locked' : ''; ?>"
							title="<?php esc_attr_e( 'Email', 'pressark' ); ?>"
							<?php echo $is_free ? 'disabled' : ''; ?>>
							<?php echo pressark_icon( 'mail' ); ?><?php if ( $is_free ) : ?><span class="pressark-wd-pro-label"><?php esc_html_e( 'Pro', 'pressark' ); ?></span><?php endif; ?>
						</button>
						<?php
						$tg_locked = $is_free || ! $has_telegram;
						$tg_title  = ! $has_telegram
							? __( 'Telegram (not configured)', 'pressark' )
							: __( 'Telegram', 'pressark' );
						?>
						<button type="button"
							class="pressark-wd-badge pressark-wd-badge-telegram <?php echo $has_tg ? 'active' : ''; ?> <?php echo $tg_locked ? 'locked' : ''; ?>"
							title="<?php echo esc_attr( $tg_title ); ?>"
							<?php echo $tg_locked ? 'disabled' : ''; ?>>
							<?php echo pressark_icon( 'send' ); ?><?php if ( $is_free ) : ?><span class="pressark-wd-pro-label"><?php esc_html_e( 'Pro', 'pressark' ); ?></span><?php endif; ?>
						</button>
					</div>

					<label class="pressark-wd-toggle">
						<input type="checkbox" class="pressark-wd-alert-toggle" <?php checked( $enabled ); ?>>
						<span class="pressark-wd-toggle-slider"></span>
					</label>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Section 2 — Smart Digest.
	 */
	private static function render_digest_section( array $prefs, bool $is_free, bool $has_telegram ): void {
		$digest = $prefs['digest'] ?? array();
		$enabled   = ! empty( $digest['enabled'] );
		$frequency = $digest['frequency'] ?? 'weekly';
		$day       = $digest['day'] ?? 'monday';
		$time      = $digest['time'] ?? '09:00';
		$channels  = $digest['channels'] ?? array( 'email' );
		$has_email_d = in_array( 'email', $channels, true );
		$has_tg    = in_array( 'telegram', $channels, true );

		$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$hours = array();
		for ( $h = 6; $h <= 22; $h++ ) {
			$hours[ sprintf( '%02d:00', $h ) ] = gmdate( 'g:i A', mktime( $h, 0 ) );
		}
		?>
		<div class="pressark-wd-card" style="margin-top:24px;<?php echo $is_free ? 'opacity:0.6;pointer-events:none;' : ''; ?>">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
				<div>
					<h2 style="margin:0 0 4px;font-size:17px;font-weight:600;color:#0F172A;">
						<?php if ( $is_free ) : ?><?php echo pressark_icon( 'lock' ); ?> <?php endif; ?>
						<?php esc_html_e( 'Smart Digest', 'pressark' ); ?>
					</h2>
					<?php if ( $is_free ) : ?>
						<p style="margin:0;color:#D97706;font-size:13px;font-weight:500;"><?php esc_html_e( 'Upgrade to Pro to enable weekly store reports.', 'pressark' ); ?></p>
					<?php else : ?>
						<p style="margin:0;color:#64748B;font-size:13px;"><?php esc_html_e( 'AI-powered store intelligence report delivered on your schedule.', 'pressark' ); ?></p>
					<?php endif; ?>
				</div>
				<label class="pressark-wd-toggle">
					<input type="checkbox" id="pressark-wd-digest-enabled" <?php checked( $enabled ); ?> <?php echo $is_free ? 'disabled' : ''; ?>>
					<span class="pressark-wd-toggle-slider"></span>
				</label>
			</div>

			<div class="pressark-wd-digest-settings" style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
				<div>
					<label style="font-size:13px;color:#64748B;display:block;margin-bottom:4px;"><?php esc_html_e( 'Frequency', 'pressark' ); ?></label>
					<select id="pressark-wd-digest-frequency" class="pressark-wd-select">
						<option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'pressark' ); ?></option>
						<option value="weekly" <?php selected( $frequency, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'pressark' ); ?></option>
						<option value="monthly" <?php selected( $frequency, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'pressark' ); ?></option>
					</select>
				</div>

				<div id="pressark-wd-day-picker" style="<?php echo 'weekly' !== $frequency ? 'display:none;' : ''; ?>">
					<label style="font-size:13px;color:#64748B;display:block;margin-bottom:4px;"><?php esc_html_e( 'Day', 'pressark' ); ?></label>
					<select id="pressark-wd-digest-day" class="pressark-wd-select">
						<?php foreach ( $days as $d ) : ?>
							<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $day, $d ); ?>><?php echo esc_html( ucfirst( $d ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label style="font-size:13px;color:#64748B;display:block;margin-bottom:4px;"><?php esc_html_e( 'Time', 'pressark' ); ?></label>
					<select id="pressark-wd-digest-time" class="pressark-wd-select">
						<?php foreach ( $hours as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $time, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div>
					<label style="font-size:13px;color:#64748B;display:block;margin-bottom:4px;"><?php esc_html_e( 'Channels', 'pressark' ); ?></label>
					<div style="display:flex;gap:8px;align-items:center;">
						<button type="button"
							class="pressark-wd-badge pressark-wd-badge-digest-email <?php echo $has_email_d ? 'active' : ''; ?>"
							title="<?php esc_attr_e( 'Email', 'pressark' ); ?>">
							<?php echo pressark_icon( 'mail' ); ?>
						</button>
						<?php
						$tg_digest_locked = $is_free || ! $has_telegram;
						$tg_digest_title  = ! $has_telegram
							? __( 'Telegram (not configured)', 'pressark' )
							: __( 'Telegram', 'pressark' );
						?>
						<button type="button"
							class="pressark-wd-badge pressark-wd-badge-digest-telegram <?php echo $has_tg ? 'active' : ''; ?> <?php echo $tg_digest_locked ? 'locked' : ''; ?>"
							title="<?php echo esc_attr( $tg_digest_title ); ?>"
							<?php echo $tg_digest_locked ? 'disabled' : ''; ?>>
							<?php echo pressark_icon( 'send' ); ?><?php if ( $is_free ) : ?><span class="pressark-wd-pro-label"><?php esc_html_e( 'Pro', 'pressark' ); ?></span><?php endif; ?>
						</button>
					</div>
				</div>
			</div>

			<?php if ( ! $is_free ) : ?>
			<div style="margin-top:20px;padding-top:16px;border-top:1px solid #E2E8F0;">
				<button type="button" id="pressark-wd-test-digest" class="button button-secondary" style="font-size:13px;">
					<?php esc_html_e( 'Send test digest now', 'pressark' ); ?>
				</button>
				<span id="pressark-wd-test-status" style="margin-left:12px;font-size:13px;color:#64748B;"></span>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Auto-reply notice — displayed when the review_auto_reply template is active.
	 */
	private static function render_auto_reply_notice( int $user_id ): void {
		if ( ! class_exists( 'PressArk_Watchdog_Templates' ) ) {
			return;
		}
		if ( ! PressArk_Watchdog_Templates::is_active( 'review_auto_reply', $user_id ) ) {
			return;
		}
		?>
		<div class="pressark-wd-card" style="margin-top:24px;border-left:3px solid #D97706;background:#FFFBEB;">
			<p style="margin:0;font-size:13px;color:#92400E;line-height:1.5;">
				<?php echo "\xF0\x9F\x92\xAC"; ?>
				<strong><?php esc_html_e( 'Auto-Reply Active', 'pressark' ); ?></strong> &mdash;
				<?php esc_html_e( 'When active, PressArk will automatically post replies to negative reviews as the site admin. You can edit or delete any auto-reply from WooCommerce > Reviews.', 'pressark' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Section 3 — Recent Events.
	 */
	private static function render_events_section( array $events ): void {
		$severity_map = array(
			'order_failed'     => array( 'color' => '#DC2626', 'label' => __( 'Failed Order', 'pressark' ) ),
			'order_cancelled'  => array( 'color' => '#D97706', 'label' => __( 'Cancelled Order', 'pressark' ) ),
			'refund_issued'    => array( 'color' => '#DC2626', 'label' => __( 'Refund', 'pressark' ) ),
			'low_stock'        => array( 'color' => '#D97706', 'label' => __( 'Low Stock', 'pressark' ) ),
			'out_of_stock'     => array( 'color' => '#DC2626', 'label' => __( 'Out of Stock', 'pressark' ) ),
			'negative_review'  => array( 'color' => '#EA580C', 'label' => __( 'Negative Review', 'pressark' ) ),
			'high_value_order' => array( 'color' => '#2563EB', 'label' => __( 'High-Value Order', 'pressark' ) ),
		);

		?>
		<div class="pressark-wd-card" style="margin-top:24px;">
			<h2 style="margin:0 0 16px;font-size:17px;font-weight:600;color:#0F172A;">
				<?php esc_html_e( 'Recent Events', 'pressark' ); ?>
			</h2>

			<?php if ( empty( $events ) ) : ?>
				<p style="color:#94A3B8;font-size:14px;text-align:center;padding:24px 0;">
					<?php esc_html_e( 'No events recorded yet. Events will appear here as they occur.', 'pressark' ); ?>
				</p>
			<?php else : ?>
				<div class="pressark-wd-events-list">
				<?php foreach ( $events as $event ) :
					$type_info = $severity_map[ $event['type'] ] ?? array( 'color' => '#94A3B8', 'label' => $event['type'] );
					$time_ago  = self::time_ago( $event['time'] );
					$desc      = self::describe_event( $event );
				?>
					<div class="pressark-wd-event-row">
						<span class="pressark-wd-severity" style="background:<?php echo esc_attr( $type_info['color'] ); ?>;"></span>
						<span class="pressark-wd-event-time"><?php echo esc_html( $time_ago ); ?></span>
						<span class="pressark-wd-event-type"><?php echo esc_html( $type_info['label'] ); ?></span>
						<span class="pressark-wd-event-desc"><?php echo esc_html( $desc ); ?></span>
					</div>
				<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── AJAX Handlers ──────────────────────────────────────────────

	/**
	 * AJAX: Save watchdog preferences.
	 */
	public static function handle_save_preferences(): void {
		check_ajax_referer( 'pressark_watchdog_nonce', 'nonce' );

		if ( ! PressArk_Capabilities::current_user_can_manage_settings() ) {
			wp_send_json_error( array( 'error' => 'Permission denied.' ), 403 );
		}

		$user_id = get_current_user_id();

		// JS sends prefs as a JSON string via FormData.
		$prefs = json_decode( wp_unslash( $_POST['prefs'] ?? '{}' ), true );
		if ( ! is_array( $prefs ) ) {
			wp_send_json_error( array( 'error' => 'Invalid preferences data.' ) );
		}

		// Enforce entitlement gating server-side: free users cannot enable channels.
		$license = new PressArk_License();
		$tier    = $license->get_tier();
		if ( ! PressArk_Entitlements::can_use_feature( $tier, 'watchdog_alerts' ) ) {
			// Strip channels from all alerts — free users get in-chat only.
			if ( isset( $prefs['alerts'] ) && is_array( $prefs['alerts'] ) ) {
				foreach ( $prefs['alerts'] as $type => &$alert ) {
					$alert['channels'] = array();
				}
				unset( $alert );
			}
			// Disable digest.
			if ( isset( $prefs['digest'] ) ) {
				$prefs['digest']['enabled'] = false;
			}
		}

		// Telegram requires a paid tier (Pro+). Free users cannot use it.
		// The UI already locks the badge for free users, but enforce server-side too.
		if ( ! PressArk_Entitlements::is_paid_tier( $tier ) ) {
			if ( isset( $prefs['alerts'] ) && is_array( $prefs['alerts'] ) ) {
				foreach ( $prefs['alerts'] as $type => &$alert ) {
					if ( isset( $alert['channels'] ) && is_array( $alert['channels'] ) ) {
						$alert['channels'] = array_values( array_diff( $alert['channels'], array( 'telegram' ) ) );
					}
				}
				unset( $alert );
			}
			if ( isset( $prefs['digest']['channels'] ) && is_array( $prefs['digest']['channels'] ) ) {
				$prefs['digest']['channels'] = array_values( array_diff( $prefs['digest']['channels'], array( 'telegram' ) ) );
			}
		}

		$saved = PressArk_Watchdog_Preferences::save( $user_id, $prefs );

		if ( $saved ) {
			wp_send_json_success( array( 'message' => 'Preferences saved.' ) );
		} else {
			wp_send_json_error( array( 'error' => 'Failed to save preferences.' ) );
		}
	}

	/**
	 * AJAX: Send a test digest email.
	 */
	public static function handle_test_digest(): void {
		check_ajax_referer( 'pressark_watchdog_nonce', 'nonce' );

		if ( ! PressArk_Capabilities::current_user_can_manage_settings() ) {
			wp_send_json_error( array( 'error' => 'Permission denied.' ), 403 );
		}

		// Rate limit: one test digest per 60 seconds.
		// Set transient BEFORE sending to prevent TOCTOU race with concurrent requests.
		$cooldown_key = self::TEST_DIGEST_COOLDOWN . '_' . get_current_user_id();
		$cooldown     = get_transient( $cooldown_key );
		if ( $cooldown ) {
			wp_send_json_error( array( 'error' => 'Please wait 60 seconds between test digests.' ) );
		}
		set_transient( $cooldown_key, 1, 60 );

		$license = new PressArk_License();
		$tier    = $license->get_tier();
		if ( ! PressArk_Entitlements::can_use_feature( $tier, 'watchdog_digest' ) ) {
			delete_transient( $cooldown_key );
			wp_send_json_error( array( 'error' => 'Digest requires a Pro or higher plan.' ) );
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$site    = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Test Digest — %s', 'pressark' ),
			$site
		);

		$body  = "## Store Overview\n\n";
		$body .= "This is a test digest from PressArk Watchdog. When configured, this report will contain an AI-generated summary of your store's recent activity.\n\n";
		$body .= "## What You'll See\n\n";
		$body .= "- Revenue trends and order volume\n";
		$body .= "- Inventory alerts and stock status\n";
		$body .= "- Customer feedback highlights\n";
		$body .= "- Actionable recommendations\n\n";
		$body .= "Configure your digest schedule in the Watchdog settings.";

		$metadata = array( 'admin_url' => admin_url( 'admin.php?page=pressark-watchdog' ) );

		// Send to all channels the user has selected for digest.
		$prefs    = PressArk_Watchdog_Preferences::get( $user_id );
		$channels = $prefs['digest']['channels'] ?? array( 'email' );
		if ( empty( $channels ) ) {
			$channels = array( 'email' );
		}

		$sent_to = array();
		$errors  = array();

		foreach ( $channels as $channel ) {
			if ( 'email' === $channel ) {
				$target = $user->user_email;
			} elseif ( 'telegram' === $channel ) {
				$target = PressArk_Notification_Manager::get_user_telegram_id( $user_id );
			} else {
				continue;
			}

			if ( empty( $target ) ) {
				$errors[] = sprintf( '%s: no target configured', $channel );
				continue;
			}

			$result = PressArk_Notification_Manager::send( $channel, $target, $subject, $body, $metadata );

			if ( ! empty( $result['success'] ) ) {
				$sent_to[] = 'email' === $channel ? $user->user_email : 'Telegram';
			} else {
				$errors[] = sprintf( '%s: %s', $channel, $result['error'] ?? 'delivery failed' );
			}
		}

		if ( ! empty( $sent_to ) ) {
			wp_send_json_success( array( 'message' => sprintf(
				/* translators: %s: comma-separated list of destinations */
				__( 'Test digest sent to %s', 'pressark' ),
				implode( ', ', $sent_to )
			) ) );
		} else {
			wp_send_json_error( array( 'error' => implode( '; ', $errors ) ?: 'Delivery failed.' ) );
		}
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * Human-readable relative time.
	 */
	private static function time_ago( int $timestamp ): string {
		$diff = time() - $timestamp;
		if ( $diff < 60 ) {
			return __( 'just now', 'pressark' );
		}
		if ( $diff < 3600 ) {
			$m = (int) floor( $diff / 60 );
			/* translators: %d: number of minutes */
			return sprintf( _n( '%dm ago', '%dm ago', $m, 'pressark' ), $m );
		}
		if ( $diff < 86400 ) {
			$h = (int) floor( $diff / 3600 );
			/* translators: %d: number of hours */
			return sprintf( _n( '%dh ago', '%dh ago', $h, 'pressark' ), $h );
		}
		$d = (int) floor( $diff / 86400 );
		/* translators: %d: number of days */
		return sprintf( _n( '%dd ago', '%dd ago', $d, 'pressark' ), $d );
	}

	/**
	 * Build a short description for an event.
	 */
	private static function describe_event( array $event ): string {
		$d = $event['data'] ?? array();
		switch ( $event['type'] ) {
			case 'order_failed':
				return sprintf( 'Order #%s — $%s', $d['number'] ?? '?', number_format( (float) ( $d['total'] ?? 0 ), 2 ) );
			case 'order_cancelled':
				return sprintf( 'Order #%s cancelled — $%s', $d['number'] ?? '?', number_format( (float) ( $d['total'] ?? 0 ), 2 ) );
			case 'refund_issued':
				return sprintf( '$%s refunded on Order #%s', number_format( (float) ( $d['amount'] ?? 0 ), 2 ), $d['number'] ?? '?' );
			case 'low_stock':
				return sprintf( '%s — %d units left', $d['name'] ?? '?', (int) ( $d['stock'] ?? 0 ) );
			case 'out_of_stock':
				return sprintf( '%s is out of stock', $d['name'] ?? '?' );
			case 'negative_review':
				return sprintf( '%d-star on %s', (int) ( $d['rating'] ?? 0 ), $d['product_name'] ?? '?' );
			case 'high_value_order':
				return sprintf( 'Order #%s — $%s', $d['number'] ?? '?', number_format( (float) ( $d['total'] ?? 0 ), 2 ) );
			default:
				return '';
		}
	}

	// ── Inline CSS ──────────────────────────────────────────────────

	private static function render_inline_styles(): void {
		?>
		<style>
		.pressark-watchdog-wrap { max-width: 800px; }
		.pressark-wd-card {
			background: #fff;
			border: 1px solid rgba(226,232,240,0.8);
			border-radius: 12px;
			padding: 24px 28px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.02);
		}
		.pressark-wd-alert-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			padding: 12px 0;
			border-bottom: 1px solid #F1F5F9;
		}
		.pressark-wd-alert-row:last-child { border-bottom: none; }
		.pressark-wd-alert-info {
			display: flex;
			align-items: center;
			gap: 12px;
			flex: 1;
			min-width: 0;
		}
		.pressark-wd-alert-icon { font-size: 20px; flex-shrink: 0; }
		.pressark-wd-alert-name { font-size: 14px; font-weight: 600; color: #0F172A; }
		.pressark-wd-alert-desc { font-size: 12px; color: #64748B; margin-top: 1px; }
		.pressark-wd-alert-controls {
			display: flex;
			align-items: center;
			gap: 12px;
			flex-shrink: 0;
		}
		.pressark-wd-channel-badges { display: flex; gap: 6px; }
		.pressark-wd-badge {
			display: inline-flex;
			align-items: center;
			gap: 2px;
			padding: 4px 8px;
			border: 1px solid #E2E8F0;
			border-radius: 6px;
			background: #F8FAFC;
			font-size: 14px;
			cursor: pointer;
			transition: all 0.15s ease;
		}
		.pressark-wd-badge:hover:not(.locked) { border-color: #2563EB; }
		.pressark-wd-badge.active { background: #EFF6FF; border-color: #2563EB; }
		.pressark-wd-badge.locked { opacity: 0.5; cursor: not-allowed; }
		.pressark-wd-pro-label {
			font-size: 9px;
			font-weight: 700;
			color: #D97706;
			text-transform: uppercase;
			margin-left: 2px;
		}
		/* Toggle */
		.pressark-wd-toggle {
			position: relative;
			display: inline-block;
			width: 44px;
			height: 24px;
			flex-shrink: 0;
		}
		.pressark-wd-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
		.pressark-wd-toggle-slider {
			position: absolute;
			cursor: pointer;
			inset: 0;
			background: #CBD5E1;
			border-radius: 12px;
			transition: background 0.2s ease;
		}
		.pressark-wd-toggle-slider::before {
			content: '';
			position: absolute;
			width: 18px;
			height: 18px;
			left: 3px;
			bottom: 3px;
			background: #fff;
			border-radius: 50%;
			transition: transform 0.2s ease;
			box-shadow: 0 1px 3px rgba(0,0,0,0.15);
		}
		.pressark-wd-toggle input:checked + .pressark-wd-toggle-slider { background: #2563EB; }
		.pressark-wd-toggle input:checked + .pressark-wd-toggle-slider::before { transform: translateX(20px); }
		/* Digest selects */
		.pressark-wd-select {
			padding: 6px 10px;
			border: 1px solid #E2E8F0;
			border-radius: 6px;
			font-size: 13px;
			color: #0F172A;
			background: #fff;
		}
		/* Events */
		.pressark-wd-events-list { max-height: 400px; overflow-y: auto; }
		.pressark-wd-event-row {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 8px 0;
			border-bottom: 1px solid #F8FAFC;
			font-size: 13px;
		}
		.pressark-wd-event-row:last-child { border-bottom: none; }
		.pressark-wd-severity {
			width: 8px;
			height: 8px;
			border-radius: 50%;
			flex-shrink: 0;
		}
		.pressark-wd-event-time { color: #94A3B8; width: 60px; flex-shrink: 0; }
		.pressark-wd-event-type { color: #475569; font-weight: 500; width: 130px; flex-shrink: 0; }
		.pressark-wd-event-desc { color: #334155; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		/* Responsive */
		@media (max-width: 600px) {
			.pressark-wd-alert-row { flex-direction: column; align-items: flex-start; gap: 8px; }
			.pressark-wd-alert-controls { width: 100%; justify-content: flex-end; }
			.pressark-wd-digest-settings { flex-direction: column; }
			.pressark-wd-event-row { flex-wrap: wrap; }
			.pressark-wd-event-type { width: auto; }
			.pressark-wd-event-desc { width: 100%; }
		}
		</style>
		<?php
	}

	// ── Inline JS ───────────────────────────────────────────────────

	private static function render_inline_script( string $nonce ): void {
		?>
		<script>
		(function() {
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var ajaxurl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var saveTimer = null;

			function collectPrefs() {
				var prefs = { alerts: {}, digest: {} };
				document.querySelectorAll('.pressark-wd-alert-row').forEach(function(row) {
					var type = row.getAttribute('data-type');
					var toggle = row.querySelector('.pressark-wd-alert-toggle');
					var emailBadge = row.querySelector('.pressark-wd-badge-email');
					var tgBadge = row.querySelector('.pressark-wd-badge-telegram');
					var thresholdInput = row.querySelector('.pressark-wd-threshold');
					var channels = [];
					if (emailBadge && emailBadge.classList.contains('active')) channels.push('email');
					if (tgBadge && tgBadge.classList.contains('active')) channels.push('telegram');
					var alert = { enabled: toggle ? toggle.checked : false, channels: channels };
					if (thresholdInput) alert.threshold = parseFloat(thresholdInput.value) || 500;
					prefs.alerts[type] = alert;
				});
				var digestEnabled = document.getElementById('pressark-wd-digest-enabled');
				var digestFreq = document.getElementById('pressark-wd-digest-frequency');
				var digestDay = document.getElementById('pressark-wd-digest-day');
				var digestTime = document.getElementById('pressark-wd-digest-time');
				var digestEmail = document.querySelector('.pressark-wd-badge-digest-email');
				var digestTg = document.querySelector('.pressark-wd-badge-digest-telegram');
				var digestChannels = [];
				if (digestEmail && digestEmail.classList.contains('active')) digestChannels.push('email');
				if (digestTg && digestTg.classList.contains('active')) digestChannels.push('telegram');
				prefs.digest = {
					enabled: digestEnabled ? digestEnabled.checked : false,
					frequency: digestFreq ? digestFreq.value : 'weekly',
					day: digestDay ? digestDay.value : 'monday',
					time: digestTime ? digestTime.value : '09:00',
					channels: digestChannels
				};
				return prefs;
			}

			function savePrefs() {
				if (saveTimer) clearTimeout(saveTimer);
				saveTimer = setTimeout(function() {
					var body = new FormData();
					body.append('action', 'pressark_save_watchdog_prefs');
					body.append('nonce', nonce);
					body.append('prefs', JSON.stringify(collectPrefs()));
					fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
						.then(function(r) { return r.json(); })
						.then(function(resp) {
							if (!resp.success) console.error('Watchdog save error:', resp.data);
						})
						.catch(function(e) { console.error('Watchdog save failed:', e); });
				}, 500);
			}

			// Alert toggles.
			document.querySelectorAll('.pressark-wd-alert-toggle').forEach(function(cb) {
				cb.addEventListener('change', savePrefs);
			});

			// Channel badges (non-locked).
			document.querySelectorAll('.pressark-wd-badge-email:not(.locked), .pressark-wd-badge-telegram:not(.locked), .pressark-wd-badge-digest-email:not(.locked), .pressark-wd-badge-digest-telegram:not(.locked)').forEach(function(btn) {
				btn.addEventListener('click', function() {
					// Require at least one channel to remain active for digest badges.
					var isDigestBadge = btn.classList.contains('pressark-wd-badge-digest-email') || btn.classList.contains('pressark-wd-badge-digest-telegram');
					if (isDigestBadge && btn.classList.contains('active')) {
						var digestEmail = document.querySelector('.pressark-wd-badge-digest-email');
						var digestTg = document.querySelector('.pressark-wd-badge-digest-telegram');
						var activeCount = 0;
						if (digestEmail && digestEmail.classList.contains('active')) activeCount++;
						if (digestTg && digestTg.classList.contains('active')) activeCount++;
						if (activeCount <= 1) return; // Don't deselect the last channel.
					}
					btn.classList.toggle('active');
					savePrefs();
				});
			});

			// Threshold inputs.
			document.querySelectorAll('.pressark-wd-threshold').forEach(function(input) {
				input.addEventListener('blur', savePrefs);
				input.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); savePrefs(); } });
			});

			// Digest settings.
			['pressark-wd-digest-enabled', 'pressark-wd-digest-frequency', 'pressark-wd-digest-day', 'pressark-wd-digest-time'].forEach(function(id) {
				var el = document.getElementById(id);
				if (el) el.addEventListener('change', savePrefs);
			});

			// Show/hide day picker based on frequency.
			var freqSelect = document.getElementById('pressark-wd-digest-frequency');
			var dayPicker = document.getElementById('pressark-wd-day-picker');
			if (freqSelect && dayPicker) {
				freqSelect.addEventListener('change', function() {
					dayPicker.style.display = freqSelect.value === 'weekly' ? '' : 'none';
				});
			}

			// Test digest button.
			var testBtn = document.getElementById('pressark-wd-test-digest');
			var testStatus = document.getElementById('pressark-wd-test-status');
			if (testBtn) {
				testBtn.addEventListener('click', function() {
					testBtn.disabled = true;
					testStatus.textContent = <?php echo wp_json_encode( __( 'Sending...', 'pressark' ) ); ?>;
					testStatus.style.color = '#64748B';
					var body = new FormData();
					body.append('action', 'pressark_test_digest');
					body.append('nonce', nonce);
					fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
						.then(function(r) { return r.json(); })
						.then(function(resp) {
							testBtn.disabled = false;
							if (resp.success) {
								testStatus.textContent = resp.data.message || <?php echo wp_json_encode( __( 'Sent!', 'pressark' ) ); ?>;
								testStatus.style.color = '#16A34A';
							} else {
								testStatus.textContent = (resp.data && resp.data.error) || <?php echo wp_json_encode( __( 'Failed', 'pressark' ) ); ?>;
								testStatus.style.color = '#DC2626';
							}
						})
						.catch(function() {
							testBtn.disabled = false;
							testStatus.textContent = <?php echo wp_json_encode( __( 'Network error', 'pressark' ) ); ?>;
							testStatus.style.color = '#DC2626';
						});
				});
			}
		})();
		</script>
		<?php
	}
}
