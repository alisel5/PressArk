<?php
/**
 * PressArk Watchdog Templates — Pre-built automation configurations.
 *
 * Users activate templates with one click instead of writing prompts.
 * Each template bundles a carefully tuned AI prompt, cadence defaults,
 * and entitlement requirements.
 *
 * @package PressArk
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Watchdog_Templates {

	/** Prefix used in automation names to link back to templates. */
	private const NAME_PREFIX = '[Watchdog] ';

	// ── AI Prompts ──────────────────────────────────────────────────

	/**
	 * Weekly store digest prompt.
	 *
	 * Designed to produce a concise, actionable email the store owner
	 * reads over Monday coffee. Structured sections, no fluff.
	 */
	private const DIGEST_PROMPT = <<<'PROMPT'
You are a WooCommerce store analyst. Generate a weekly store digest email for the site owner.

## Instructions

1. Call the `revenue_report` tool with period "last_7_days" to get this week's revenue, order count, and average order value.
2. Call the `stock_report` tool to get current inventory status — low stock and out-of-stock products.
3. Call the `revenue_report` tool with period "last_30_days" to get the trailing month for trend comparison.

## Output Format

Write a plain-text email (no HTML) with these sections. Use "##" headers and bullet points.

## Revenue This Week
- Total revenue and order count
- Average order value
- Compare to the trailing 30-day weekly average (up/down percentage)

## Inventory Alerts
- List any out-of-stock products (name + SKU)
- List any low-stock products approaching threshold
- If none: "All products are well-stocked."

## Top Performers
- Top 3 best-selling products this week by revenue (from revenue_report data)

## Suggested Actions
Based on the data, suggest 1-3 specific, actionable steps. Examples:
- "Restock [Product X] — only 2 units left, sells ~5/week"
- "Consider a promotion on [Product Y] — sales dropped 40% vs last month"
- "Revenue is up 15% — great week, no action needed"

Keep the entire digest under 400 words. Be specific with numbers. No generic advice.
PROMPT;

	/**
	 * Daily store briefing prompt.
	 *
	 * Quick morning summary — even shorter than the weekly digest.
	 */
	private const DAILY_DIGEST_PROMPT = <<<'PROMPT'
You are a WooCommerce store analyst. Generate a brief daily store summary for the site owner.

## Instructions

1. Call the `revenue_report` tool with period "yesterday" to get yesterday's numbers.
2. Call the `stock_report` tool to check for any critical inventory issues.

## Output Format

Write a short plain-text email (no HTML). Use "##" headers and bullet points. Keep it under 200 words.

## Yesterday's Numbers
- Revenue, order count, average order value
- Notable orders (unusually large or first-time customers if available)

## Stock Warnings
- Any products that went out of stock or hit low-stock threshold
- If none: "Inventory looks good."

## Quick Note
One sentence: the single most important thing the owner should know today.
PROMPT;

	/**
	 * Negative review analyzer prompt.
	 *
	 * Triggered by the negative_review event. Reads the review, checks
	 * for patterns, and drafts a response suggestion.
	 */
	private const REVIEW_ANALYZER_PROMPT = <<<'PROMPT'
You are a WooCommerce customer experience analyst. A negative review (1-2 stars) was just posted on the store.

## Instructions

1. The triggering event data includes the review details (product, rating, text, reviewer name).
2. Call the `list_reviews` tool filtered to the reviewed product (use product_id from event context) to get the last 20 reviews.
3. Analyze all recent reviews for complaint patterns.

## Output Format

Write a plain-text analysis (no HTML). Use "##" headers.

## Review Summary
- Product name, star rating, and a one-line summary of the complaint
- Reviewer name (first name only for privacy)

## Pattern Analysis
- How many reviews in the last 30 days? What's the average rating?
- Are there other 1-2 star reviews? If yes, what do they complain about?
- Is this complaint a one-off or part of a recurring theme?
- If there IS a pattern: be direct. "3 of the last 10 reviews mention damaged packaging. This is a recurring fulfillment issue."
- If there is NO pattern: "This appears to be an isolated complaint."

## Suggested Response
Draft a professional, empathetic reply the store owner can post. Guidelines:
- Acknowledge the specific issue (don't be generic)
- Offer a concrete resolution (refund, replacement, follow-up)
- Keep it under 80 words
- Tone: professional, warm, solution-oriented

## Action Items
1-2 specific steps the store owner should take beyond replying.
If a pattern exists, the first action item should address the root cause, not just the individual review.

Keep the entire analysis under 250 words. Be specific with data from the actual reviews.
PROMPT;

	/**
	 * Auto-reply to negative reviews prompt.
	 *
	 * Triggered by the negative_review event. Reads the review, checks
	 * for patterns, drafts a professional reply, and posts it automatically.
	 * This is the Team+ upgrade over the review_analyzer (which only analyzes).
	 */
	private const REVIEW_AUTO_REPLY_PROMPT = <<<'PROMPT'
You are a WooCommerce store's customer service representative. A negative review (1-2 stars) was just posted. Your job is to read it, understand the complaint, and post a professional reply.

## Instructions

1. Read the EVENT CONTEXT to get the review details (product, rating, text, reviewer).
2. Call `list_reviews` filtered to this product (use the product_id from event context) to see recent reviews and understand if this is a pattern.
3. Draft a reply and post it using the `reply_review` tool with the review_id from the event context.

## Reply Guidelines

Your reply must:
- Acknowledge the SPECIFIC complaint (never write a generic "we're sorry you had a bad experience")
- Show you actually read and understood what they said
- Offer a concrete resolution: "Please email us at {the store_support_email from the event context} and we'll send a replacement" or "We'd like to make this right — please reach out to our support"
- Be warm but professional — not overly apologetic or corporate
- Be 40-60 words maximum — short replies look more genuine than long ones
- Sign off with just the store name, not a person's name (you don't know who the owner is)

## What NOT to do

- Never argue with the customer or dispute their experience
- Never offer refunds or specific dollar amounts (that's a business decision for the owner)
- Never promise changes to products or shipping (you don't have authority)
- Never mention PressArk or AI — the reply must read as if a human wrote it
- Never include emoji in the reply

## After posting

Report what you did: the review text, your reply, and whether you noticed a pattern in recent reviews. If there IS a pattern (2+ reviews mentioning the same issue), include a note: "⚠️ Pattern detected: [description]. Consider investigating."

Keep your report under 200 words.
PROMPT;

	/**
	 * Stockout prevention prompt.
	 *
	 * Triggered by low_stock events. Estimates days until stockout
	 * based on recent sales velocity.
	 */
	private const STOCKOUT_PROMPT = <<<'PROMPT'
You are a WooCommerce inventory analyst. A product just hit its low-stock threshold.

## Instructions

1. The triggering event data includes the product name, SKU, and current stock level.
2. Call the `revenue_report` tool with period "last_30_days" to estimate sales velocity for this product.
3. Call the `stock_report` tool to get full inventory context.

## Output Format

Write a short plain-text alert (no HTML). Use "##" headers.

## Stock Alert
- Product name and SKU
- Current stock level vs. low-stock threshold

## Sales Velocity
- Average units sold per week (from the last 30 days)
- Estimated days until stockout at current rate

## Recommendation
One specific action:
- If < 7 days until stockout: "URGENT — reorder immediately"
- If 7-14 days: "Order soon to avoid stockout"
- If > 14 days: "Monitor — stock is low but not critical"

Keep the entire alert under 150 words.
PROMPT;

	// ── Template Registry ───────────────────────────────────────────

	/**
	 * Get all available template definitions.
	 *
	 * @return array<string, array> Keyed by template slug.
	 */
	public static function get_templates(): array {
		return array(
			'weekly_digest' => array(
				'name'            => 'Weekly Store Digest',
				'description'     => 'AI-powered summary of your store\'s week — orders, revenue, issues, and suggested actions. Delivered every Monday morning.',
				'prompt'          => self::DIGEST_PROMPT,
				'cadence_type'    => 'weekly',
				'cadence_value'   => 0,
				'default_day'     => 'monday',
				'default_time'    => '09:00',
				'approval_policy' => 'editorial',
				'event_trigger'   => null,
				'min_tier'        => 'pro',
				'icon'            => "\xF0\x9F\x93\x8A", // chart emoji
			),
			'daily_digest' => array(
				'name'            => 'Daily Store Briefing',
				'description'     => 'A quick daily summary of yesterday\'s orders, issues, and stock levels. For high-volume stores.',
				'prompt'          => self::DAILY_DIGEST_PROMPT,
				'cadence_type'    => 'daily',
				'cadence_value'   => 0,
				'default_time'    => '08:00',
				'approval_policy' => 'editorial',
				'event_trigger'   => null,
				'min_tier'        => 'team',
				'icon'            => "\xE2\x98\x80\xEF\xB8\x8F", // sun emoji
			),
			'review_analyzer' => array(
				'name'            => 'Negative Review Analyzer',
				'description'     => 'When a 1-2 star review comes in, AI reads it, checks for patterns in recent reviews, and sends you a summary with suggested response.',
				'prompt'          => self::REVIEW_ANALYZER_PROMPT,
				'cadence_type'    => 'once',
				'cadence_value'   => 0,
				'approval_policy' => 'editorial',
				'event_trigger'   => 'negative_review',
				'event_trigger_cooldown' => 300,
				'min_tier'        => 'team',
				'icon'            => "\xE2\xAD\x90", // star emoji
			),
			'review_auto_reply' => array(
				'name'            => 'Auto-Reply to Negative Reviews',
				'description'     => 'When a 1-2 star review comes in, AI drafts and posts a professional response within minutes. You can always edit or delete the reply later.',
				'prompt'          => self::REVIEW_AUTO_REPLY_PROMPT,
				'cadence_type'    => 'once',
				'cadence_value'   => 0,
				'approval_policy' => 'merchandising',
				'event_trigger'   => 'negative_review',
				'event_trigger_cooldown' => 120,
				'min_tier'        => 'team',
				'icon'            => "\xF0\x9F\x92\xAC",
			),
			'stockout_preventer' => array(
				'name'            => 'Stockout Prevention Alert',
				'description'     => 'When a product hits low stock, AI checks sales velocity and estimates days until stockout.',
				'prompt'          => self::STOCKOUT_PROMPT,
				'cadence_type'    => 'once',
				'cadence_value'   => 0,
				'approval_policy' => 'editorial',
				'event_trigger'   => 'low_stock',
				'event_trigger_cooldown' => 3600,
				'min_tier'        => 'team',
				'icon'            => "\xF0\x9F\x93\xA6", // package emoji
			),
		);
	}

	/**
	 * Get a single template definition.
	 *
	 * @param string $key Template slug.
	 * @return array|null Template definition or null.
	 */
	public static function get_template( string $key ): ?array {
		$templates = self::get_templates();
		return $templates[ $key ] ?? null;
	}

	// ── Activation ──────────────────────────────────────────────────

	/**
	 * Activate a template — create an automation from it.
	 *
	 * @param string $template_key Template slug.
	 * @param int    $user_id      WordPress user ID.
	 * @param array  $overrides    Optional overrides (day, time, etc.).
	 * @return array { success: bool, automation_id?: string, error?: string }
	 */
	public static function activate( string $template_key, int $user_id, array $overrides = array() ): array {
		$template = self::get_template( $template_key );
		if ( ! $template ) {
			return array( 'success' => false, 'error' => 'Unknown template.' );
		}

		// Check if already active.
		if ( self::is_active( $template_key, $user_id ) ) {
			return array( 'success' => false, 'error' => 'This template is already active.' );
		}

		// Check entitlement tier.
		$license = new PressArk_License();
		$tier    = $license->get_tier();

		if ( PressArk_Entitlements::compare_tiers( $tier, $template['min_tier'] ) < 0 ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					'This template requires a %s plan or higher. You are on the %s plan.',
					PressArk_Entitlements::tier_label( $template['min_tier'] ),
					PressArk_Entitlements::tier_label( $tier )
				),
			);
		}

		// Check automation quota.
		$store       = new PressArk_Automation_Store();
		$active      = $store->count_active( $user_id );
		$max         = (int) PressArk_Entitlements::tier_value( $tier, 'max_automations' );
		if ( $max > 0 && $active >= $max ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					'You have reached the maximum of %d active automations on your %s plan.',
					$max,
					PressArk_Entitlements::tier_label( $tier )
				),
			);
		}

		// Compute first_run_at based on template defaults and user's timezone.
		$timezone_string = wp_timezone_string();
		try {
			$tz = new DateTimeZone( $timezone_string );
		} catch ( \Exception $e ) {
			$tz = new DateTimeZone( 'UTC' );
		}

		$first_run_at = self::compute_first_run(
			$template['cadence_type'],
			$overrides['day'] ?? $template['default_day'] ?? null,
			$overrides['time'] ?? $template['default_time'] ?? '09:00',
			$tz
		);

		// Get user's email for notification target.
		$user       = get_userdata( $user_id );
		$user_email = $user ? $user->user_email : '';

		// Build automation data.
		$automation_data = array(
			'user_id'                => $user_id,
			'name'                   => self::NAME_PREFIX . $template['name'],
			'prompt'                 => $template['prompt'],
			'timezone'               => $timezone_string,
			'cadence_type'           => $template['cadence_type'],
			'cadence_value'          => $template['cadence_value'],
			'first_run_at'           => $first_run_at,
			'approval_policy'        => $template['approval_policy'],
			'notification_channel'   => $overrides['notification_channel'] ?? 'email',
			'notification_target'    => $overrides['notification_target'] ?? $user_email,
			'allowed_groups'         => array( 'woocommerce' ),
		);

		// Event trigger fields.
		if ( ! empty( $template['event_trigger'] ) ) {
			$automation_data['event_trigger']          = $template['event_trigger'];
			$automation_data['event_trigger_cooldown'] = $template['event_trigger_cooldown'] ?? 3600;
			// Event-triggered automations don't use scheduled next_run_at.
			$automation_data['next_run_at']            = null;
		}

		// Apply remaining overrides.
		foreach ( array( 'approval_policy' ) as $field ) {
			if ( isset( $overrides[ $field ] ) ) {
				$automation_data[ $field ] = $overrides[ $field ];
			}
		}

		// Store template key in execution_hints for tracking.
		$automation_data['execution_hints'] = array(
			'template_key' => $template_key,
		);

		$automation_id = $store->create( $automation_data );

		// Schedule the first wake if it's a scheduled (not event-triggered) automation.
		if ( empty( $template['event_trigger'] ) ) {
			PressArk_Automation_Dispatcher::schedule_next_wake( $automation_id, $first_run_at );
			PressArk_Automation_Dispatcher::dispatch_if_due( $automation_id );
		}

		return array(
			'success'       => true,
			'automation_id' => $automation_id,
		);
	}

	// ── Status Checks ───────────────────────────────────────────────

	/**
	 * Check if a template is already active for a user.
	 *
	 * Matches by automation name prefix convention.
	 *
	 * @param string $template_key Template slug.
	 * @param int    $user_id      WordPress user ID.
	 * @return bool
	 */
	public static function is_active( string $template_key, int $user_id ): bool {
		$template = self::get_template( $template_key );
		if ( ! $template ) {
			return false;
		}

		$expected_name = self::NAME_PREFIX . $template['name'];
		$store         = new PressArk_Automation_Store();
		$automations   = $store->list_for_user( $user_id, 'active' );

		foreach ( $automations as $automation ) {
			if ( $automation['name'] === $expected_name ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the automation ID for an active template, if any.
	 *
	 * @param string $template_key Template slug.
	 * @param int    $user_id      WordPress user ID.
	 * @return string|null Automation ID or null.
	 */
	public static function get_active_id( string $template_key, int $user_id ): ?string {
		$template = self::get_template( $template_key );
		if ( ! $template ) {
			return null;
		}

		$expected_name = self::NAME_PREFIX . $template['name'];
		$store         = new PressArk_Automation_Store();
		$automations   = $store->list_for_user( $user_id, 'active' );

		foreach ( $automations as $automation ) {
			if ( $automation['name'] === $expected_name ) {
				return $automation['automation_id'];
			}
		}

		return null;
	}

	/**
	 * Deactivate (pause) the automation created from a template.
	 *
	 * @param string $template_key Template slug.
	 * @param int    $user_id      WordPress user ID.
	 * @return bool True if found and paused.
	 */
	public static function deactivate( string $template_key, int $user_id ): bool {
		$automation_id = self::get_active_id( $template_key, $user_id );
		if ( ! $automation_id ) {
			return false;
		}

		$store = new PressArk_Automation_Store();
		return $store->update( $automation_id, array( 'status' => 'paused' ) );
	}

	/**
	 * Get status of all templates for a user (for UI rendering).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<string, array> Keyed by template slug with status info.
	 */
	public static function get_statuses( int $user_id ): array {
		$templates = self::get_templates();
		$license   = new PressArk_License();
		$tier      = $license->get_tier();
		$result    = array();

		foreach ( $templates as $key => $template ) {
			$is_active   = self::is_active( $key, $user_id );
			$can_activate = PressArk_Entitlements::compare_tiers( $tier, $template['min_tier'] ) >= 0;

			$result[ $key ] = array(
				'name'         => $template['name'],
				'description'  => $template['description'],
				'icon'         => $template['icon'],
				'is_active'    => $is_active,
				'can_activate' => $can_activate,
				'min_tier'     => $template['min_tier'],
				'tier_label'   => PressArk_Entitlements::tier_label( $template['min_tier'] ),
			);

			if ( $is_active ) {
				$result[ $key ]['automation_id'] = self::get_active_id( $key, $user_id );
			}
		}

		return $result;
	}

	// ── WooCommerce Onboarding Nudge ────────────────────────────────

	/**
	 * Register the WooCommerce Watchdog onboarding nudge.
	 *
	 * Displays a dismissible card on the PressArk admin pages when:
	 * 1. WooCommerce is active
	 * 2. User has a paid plan (Pro+)
	 * 3. User hasn't dismissed the nudge
	 * 4. No Watchdog templates are active yet
	 */
	public static function register_nudge_hooks(): void {
		add_action( 'admin_notices', array( self::class, 'maybe_render_watchdog_nudge' ) );
		add_action( 'wp_ajax_pressark_dismiss_watchdog_nudge', array( self::class, 'handle_dismiss_nudge' ) );
	}

	/**
	 * Render the Watchdog onboarding nudge if conditions are met.
	 */
	public static function maybe_render_watchdog_nudge(): void {
		// Only on PressArk admin pages.
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'pressark' ) ) {
			return;
		}

		// WooCommerce must be active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Must be able to manage settings.
		if ( ! PressArk_Capabilities::current_user_can_manage_settings() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Must be on a paid plan.
		$license = new PressArk_License();
		$tier    = $license->get_tier();
		if ( ! PressArk_Entitlements::is_paid_tier( $tier ) ) {
			return;
		}

		// Check if already dismissed.
		if ( get_user_meta( $user_id, 'pressark_watchdog_nudge_dismissed', true ) ) {
			return;
		}

		// Check if any template is already active.
		$templates = self::get_templates();
		foreach ( $templates as $key => $template ) {
			if ( self::is_active( $key, $user_id ) ) {
				return;
			}
		}

		self::render_watchdog_nudge( $tier );
	}

	/**
	 * Render the nudge card HTML.
	 */
	private static function render_watchdog_nudge( string $tier ): void {
		$watchdog_url = admin_url( 'admin.php?page=pressark-watchdog' );
		$nonce        = wp_create_nonce( 'pressark_dismiss_watchdog_nudge' );

		$can_use_digest   = PressArk_Entitlements::compare_tiers( $tier, 'pro' ) >= 0;
		$can_use_triggers = PressArk_Entitlements::compare_tiers( $tier, 'team' ) >= 0;

		$features = array();
		if ( $can_use_digest ) {
			$features[] = 'Weekly AI store digest';
		}
		if ( $can_use_triggers ) {
			$features[] = 'Negative review alerts with AI-drafted responses';
			$features[] = 'Stockout prevention alerts';
		}
		$features[] = 'Failed order and refund notifications';

		?>
		<div class="notice notice-info is-dismissible pressark-watchdog-nudge" style="border-left-color:#2563EB;padding:16px 20px;position:relative;">
			<div style="display:flex;align-items:flex-start;gap:16px;">
				<div style="font-size:32px;line-height:1;"><?php echo wp_kses( pressark_icon( 'shield', 32 ), pressark_icon_allowed_html() ); ?></div>
				<div style="flex:1;">
					<h3 style="margin:0 0 6px;font-size:15px;color:#0F172A;">
						<?php esc_html_e( 'Your WooCommerce store has a new AI watchdog', 'pressark' ); ?>
					</h3>
					<p style="margin:0 0 10px;color:#475569;font-size:13px;line-height:1.5;">
						<?php esc_html_e( 'PressArk Watchdog monitors your store 24/7 and sends you AI-powered alerts and reports. Enable it in one click:', 'pressark' ); ?>
					</p>
					<ul style="margin:0 0 12px;padding:0;list-style:none;">
						<?php foreach ( $features as $feature ) : ?>
							<li style="padding:2px 0;font-size:13px;color:#334155;">
								<?php echo wp_kses( pressark_icon( 'checkCircle' ), pressark_icon_allowed_html() ); ?> <?php echo esc_html( $feature ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<a href="<?php echo esc_url( $watchdog_url ); ?>" class="button button-primary" style="background:#2563EB;border-color:#2563EB;">
						<?php esc_html_e( 'Set Up Watchdog', 'pressark' ); ?> &rarr;
					</a>
				</div>
			</div>
			<script>
			jQuery(function($){
				$('.pressark-watchdog-nudge').on('click', '.notice-dismiss', function(){
					$.post(ajaxurl, {
						action: 'pressark_dismiss_watchdog_nudge',
						_wpnonce: <?php echo wp_json_encode( $nonce ); ?>
					});
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * AJAX handler: dismiss the Watchdog nudge permanently.
	 */
	public static function handle_dismiss_nudge(): void {
		check_ajax_referer( 'pressark_dismiss_watchdog_nudge' );
		update_user_meta( get_current_user_id(), 'pressark_watchdog_nudge_dismissed', 1 );
		wp_send_json_success();
	}

	// ── Internals ───────────────────────────────────────────────────

	/**
	 * Compute the first run datetime in UTC for a scheduled template.
	 *
	 * For weekly: find the next occurrence of $day at $time.
	 * For daily: find the next occurrence of $time (today if not passed, tomorrow otherwise).
	 * For event-triggered (once): return far-future placeholder.
	 *
	 * @param string        $cadence_type 'weekly', 'daily', or 'once'.
	 * @param string|null   $day          Day of the week (for weekly).
	 * @param string        $time         Time in HH:MM format.
	 * @param DateTimeZone  $tz           User's timezone.
	 * @return string UTC datetime string (Y-m-d H:i:s).
	 */
	private static function compute_first_run( string $cadence_type, ?string $day, string $time, DateTimeZone $tz ): string {
		$utc = new DateTimeZone( 'UTC' );

		if ( 'once' === $cadence_type ) {
			// Event-triggered automations don't have a scheduled run.
			// Return a far-future placeholder that won't trigger the scheduler.
			return gmdate( 'Y-m-d H:i:s', strtotime( '+10 years' ) );
		}

		$now = new DateTime( 'now', $tz );

		if ( 'weekly' === $cadence_type && $day ) {
			// Find next $day at $time.
			$target = clone $now;
			$target->modify( 'next ' . $day );
			$parts = explode( ':', $time );
			$target->setTime( (int) ( $parts[0] ?? 9 ), (int) ( $parts[1] ?? 0 ) );

			// If "next $day" landed on today and the time has already passed,
			// push to next week.
			if ( $target <= $now ) {
				$target->modify( '+7 days' );
			}

			$target->setTimezone( $utc );
			return $target->format( 'Y-m-d H:i:s' );
		}

		if ( 'daily' === $cadence_type ) {
			$target = clone $now;
			$parts  = explode( ':', $time );
			$target->setTime( (int) ( $parts[0] ?? 8 ), (int) ( $parts[1] ?? 0 ) );

			if ( $target <= $now ) {
				$target->modify( '+1 day' );
			}

			$target->setTimezone( $utc );
			return $target->format( 'Y-m-d H:i:s' );
		}

		// Fallback: now.
		$now->setTimezone( $utc );
		return $now->format( 'Y-m-d H:i:s' );
	}
}
