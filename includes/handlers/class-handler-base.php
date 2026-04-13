<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Abstract base class for domain action handlers.
 *
 * Provides shared helpers for error/success formatting, capability checks,
 * post resolution, plugin-dependency guards, checkpointing, and logging.
 *
 * @since 2.7.0
 */

abstract class PressArk_Handler_Base {

	/**
	 * Action logger instance — shared across all handlers.
	 *
	 * @var PressArk_Action_Logger
	 */
	protected PressArk_Action_Logger $logger;

	/**
	 * v3.7.1: Optional async task context for business idempotency.
	 * When set, destructive operations can check/record receipts
	 * so retries skip already-committed mutations.
	 *
	 * @var string Current async task_id (empty for sync requests).
	 */
	protected string $async_task_id = '';

	/**
	 * @param PressArk_Action_Logger $logger Logger instance (injected by engine).
	 */
	public function __construct( PressArk_Action_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Fast pre-execution permission probe for a tool owned by this handler.
	 *
	 * Handlers override this when they need capability-aware decisions before
	 * the action runs. The default implementation only checks entitlements.
	 *
	 * @since 5.6.0
	 */
	public function check_permissions( string $tool_name, array $params, array $context = array() ): array {
		return $this->entitlement_permission( $tool_name, $params, $context );
	}

	/**
	 * v3.7.1: Attach async task context for business idempotency.
	 * Called by the task queue before dispatching to the agent/engine.
	 *
	 * @param string $task_id Current async task ID.
	 */
	public function set_async_context( string $task_id ): void {
		$this->async_task_id = $task_id;
	}

	/**
	 * v3.7.1: Check if a destructive operation was already committed
	 * on a previous attempt of the current async task.
	 * Returns false for sync requests (no task context).
	 *
	 * @param string $operation_key Unique operation identifier.
	 * @return bool True if receipt exists (skip this operation).
	 */
	protected function has_operation_receipt( string $operation_key ): bool {
		if ( empty( $this->async_task_id ) ) {
			return false; // Sync request — no receipts.
		}
		$store = new PressArk_Task_Store();
		return $store->has_receipt( $this->async_task_id, $operation_key );
	}

	/**
	 * v3.7.1: Record that a destructive operation succeeded within
	 * the current async task, so retries will skip it.
	 *
	 * @param string $operation_key Unique operation identifier.
	 * @param string $summary       Short description of what was committed.
	 */
	protected function record_operation_receipt( string $operation_key, string $summary = '' ): void {
		if ( empty( $this->async_task_id ) ) {
			return; // Sync request — no receipts needed.
		}
		$store = new PressArk_Task_Store();
		$store->record_receipt( $this->async_task_id, $operation_key, $summary );
	}

	// ── Result Helpers ──────────────────────────────────────────────────

	/**
	 * Build a standardized error response.
	 *
	 * @param string $message Human-readable error message.
	 * @return array{success: false, message: string}
	 */
	protected function error( string $message ): array {
		return array(
			'success' => false,
			'message' => $message,
		);
	}

	/**
	 * Build a standardized success response.
	 *
	 * @param string $message Human-readable success message.
	 * @param array  $extra   Additional keys to merge into the response.
	 * @return array{success: true, message: string, ...}
	 */
	protected function success( string $message, array $extra = array() ): array {
		return array_merge(
			array(
				'success' => true,
				'message' => $message,
			),
			$extra
		);
	}

	/**
	 * Build a normalized allow decision.
	 *
	 * @since 5.6.0
	 */
	protected function permission_allow( array $extra = array() ): array {
		return array_merge(
			array(
				'allowed'   => true,
				'behavior'  => 'allow',
				'reason'    => '',
				'ui_action' => 'none',
			),
			$extra
		);
	}

	/**
	 * Build a normalized ask decision.
	 *
	 * @since 5.6.0
	 */
	protected function permission_ask( string $reason, string $ui_action = 'approval_dialog', array $extra = array() ): array {
		return array_merge(
			array(
				'allowed'   => false,
				'behavior'  => 'ask',
				'reason'    => $reason,
				'ui_action' => $ui_action,
			),
			$extra
		);
	}

	/**
	 * Build a normalized block decision.
	 *
	 * @since 5.6.0
	 */
	protected function permission_block( string $reason, array $extra = array() ): array {
		return array_merge(
			array(
				'allowed'   => false,
				'behavior'  => 'block',
				'reason'    => $reason,
				'ui_action' => 'none',
			),
			$extra
		);
	}

	/**
	 * Check only entitlements for the current tool and user.
	 *
	 * @since 5.6.0
	 */
	protected function entitlement_permission( string $tool_name, array $params, array $context = array() ): array {
		if ( ! class_exists( 'PressArk_Entitlements' ) ) {
			return $this->permission_allow();
		}

		$user_id = max(
			0,
			(int) (
				$context['user_id']
				?? get_current_user_id()
			)
		);

		return PressArk_Entitlements::check_tool_permission( $tool_name, $params, $user_id );
	}

	/**
	 * Require a capability before falling through to entitlements.
	 *
	 * @since 5.6.0
	 */
	protected function permission_require_capability(
		string $tool_name,
		array $params,
		array $context,
		string $capability,
		?int $object_id = null,
		string $message = ''
	): array {
		$has = $object_id
			? current_user_can( $capability, $object_id )
			: current_user_can( $capability );

		if ( ! $has ) {
			return $this->permission_block(
				'' !== $message
					? $message
					: __( 'You do not have permission to perform this action.', 'pressark' ),
				array( 'tool_name' => $tool_name )
			);
		}

		return $this->entitlement_permission( $tool_name, $params, $context );
	}

	/**
	 * Require one of several capabilities before entitlements.
	 *
	 * @since 5.6.0
	 * @param string[] $capabilities Capability names.
	 */
	protected function permission_require_any_capability(
		string $tool_name,
		array $params,
		array $context,
		array $capabilities,
		?int $object_id = null,
		string $message = ''
	): array {
		foreach ( array_values( array_filter( array_map( 'strval', $capabilities ) ) ) as $capability ) {
			$has = $object_id
				? current_user_can( $capability, $object_id )
				: current_user_can( $capability );

			if ( $has ) {
				return $this->entitlement_permission( $tool_name, $params, $context );
			}
		}

		return $this->permission_block(
			'' !== $message
				? $message
				: __( 'You do not have permission to perform this action.', 'pressark' ),
			array( 'tool_name' => $tool_name )
		);
	}

	/**
	 * Require WooCommerce and an optional capability before entitlements.
	 *
	 * @since 5.6.0
	 */
	protected function permission_require_wc(
		string $tool_name,
		array $params,
		array $context,
		string $capability = '',
		?int $object_id = null,
		string $message = ''
	): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->permission_block(
				__( 'WooCommerce is not active.', 'pressark' ),
				array( 'tool_name' => $tool_name )
			);
		}

		if ( '' !== $capability ) {
			return $this->permission_require_capability( $tool_name, $params, $context, $capability, $object_id, $message );
		}

		return $this->entitlement_permission( $tool_name, $params, $context );
	}

	/**
	 * Require Elementor and an optional capability before entitlements.
	 *
	 * @since 5.6.0
	 */
	protected function permission_require_elementor(
		string $tool_name,
		array $params,
		array $context,
		string $capability = '',
		?int $object_id = null,
		string $message = ''
	): array {
		if ( ! PressArk_Elementor::is_active() ) {
			return $this->permission_block(
				__( 'Elementor is not active.', 'pressark' ),
				array( 'tool_name' => $tool_name )
			);
		}

		if ( '' !== $capability ) {
			return $this->permission_require_capability( $tool_name, $params, $context, $capability, $object_id, $message );
		}

		return $this->entitlement_permission( $tool_name, $params, $context );
	}

	/**
	 * Require Elementor write support before entitlements.
	 *
	 * @since 5.6.0
	 */
	protected function permission_require_elementor_write(
		string $tool_name,
		array $params,
		array $context,
		string $minimum_version = '',
		string $capability = '',
		?int $object_id = null,
		string $message = ''
	): array {
		$permission = $this->permission_require_elementor( $tool_name, $params, $context );
		if ( 'allow' !== ( $permission['behavior'] ?? 'allow' ) ) {
			return $permission;
		}

		if ( '' !== $minimum_version && defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, $minimum_version, '<' ) ) {
			return $this->permission_block(
				sprintf(
					/* translators: 1: current Elementor version, 2: minimum required version */
					__( 'Elementor %1$s is too old for safe write operations. Please update to %2$s or newer.', 'pressark' ),
					ELEMENTOR_VERSION,
					$minimum_version
				),
				array( 'tool_name' => $tool_name )
			);
		}

		if ( '' !== $capability ) {
			return $this->permission_require_capability( $tool_name, $params, $context, $capability, $object_id, $message );
		}

		return $this->entitlement_permission( $tool_name, $params, $context );
	}

	/**
	 * Resolve a common post_id shape for permission probes.
	 *
	 * @since 5.6.0
	 */
	protected function permission_post_id( array $params ): int {
		return absint( $params['post_id'] ?? $params['id'] ?? 0 );
	}

	/**
	 * v5.6.0: Streaming progress via on_progress callback (inspired by Claude Code Tool.ts pattern).
	 *
	 * Sanitizes incremental progress payloads before they are emitted and
	 * ensures callback failures never interrupt the underlying tool.
	 *
	 * @param callable|null $on_progress Optional progress callback.
	 * @param array         $data        Incremental progress payload.
	 */
	protected function emit_progress( ?callable $on_progress, array $data ): void {
		if ( ! is_callable( $on_progress ) ) {
			return;
		}

		$sanitized = $this->sanitize_progress_payload( $data );
		if ( empty( $sanitized ) ) {
			return;
		}

		$serialized = wp_json_encode( $sanitized );
		$tokens     = (int) ceil( max( 0, is_string( $serialized ) ? mb_strlen( $serialized ) : 0 ) / 4 );

		try {
			$on_progress( $sanitized );
		} catch ( \Throwable $e ) {
			PressArk_Error_Tracker::warning(
				'HandlerBase',
				'Progress callback failed during handler execution',
				array(
					'handler'          => static::class,
					'progress_tokens'  => $tokens,
					'progress_keys'    => array_keys( $sanitized ),
					'error'            => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Sanitize streamed progress payloads so transient UI updates do not expose
	 * secrets or personal data.
	 *
	 * @param mixed       $value Progress value.
	 * @param string|null $key   Current field name.
	 * @return mixed
	 */
	private function sanitize_progress_payload( $value, ?string $key = null ) {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $child_key => $child_value ) {
				$normalized_key = is_string( $child_key ) ? $child_key : (string) $child_key;
				$sanitized_value = $this->sanitize_progress_payload( $child_value, $normalized_key );
				if ( null === $sanitized_value ) {
					continue;
				}
				$sanitized[ $normalized_key ] = $sanitized_value;
			}
			return $sanitized;
		}

		if ( is_object( $value ) ) {
			return $this->sanitize_progress_payload( (array) $value, $key );
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		$text     = sanitize_text_field( (string) $value );
		$key_name = strtolower( (string) $key );

		if ( '' === $text ) {
			return '';
		}

		if ( '' !== $key_name && preg_match( '/(email|e-mail|api[_-]?key|token|secret|password|authorization|auth)/i', $key_name ) ) {
			return '[redacted]';
		}

		if ( preg_match( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text ) ) {
			return '[redacted-email]';
		}

		if ( preg_match( '/\b(?:sk|pk|rk|api)[-_][A-Za-z0-9_-]{8,}\b/', $text ) ) {
			return '[redacted-secret]';
		}

		if ( mb_strlen( $text ) > 240 ) {
			$text = mb_substr( $text, 0, 240 ) . '...';
		}

		return $text;
	}

	// ── Capability Helpers ──────────────────────────────────────────────

	/**
	 * Check a WordPress capability, returning an error array on failure.
	 *
	 * Supports both general capabilities ('manage_options') and
	 * object-level capabilities ('edit_post', $post_id).
	 *
	 * @param string   $cap WordPress capability name.
	 * @param int|null $id  Optional object ID for object-level checks.
	 * @return array|null null if the user has the capability, error array otherwise.
	 */
	protected function require_cap( string $cap, ?int $id = null ): ?array {
		$has = $id
			? current_user_can( $cap, $id )
			: current_user_can( $cap );

		if ( ! $has ) {
			return $this->error( __( 'You do not have permission to perform this action.', 'pressark' ) );
		}

		return null;
	}

	// ── Post Helpers ────────────────────────────────────────────────────

	/**
	 * Get a post by ID, or return an error array.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|array WP_Post on success, error array on failure.
	 */
	protected function get_post_or_fail( int $post_id ) {
		if ( ! $post_id ) {
			return $this->error( __( 'Invalid post ID.', 'pressark' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->error( __( 'Post not found.', 'pressark' ) );
		}

		return $post;
	}

	/**
	 * Resolve a post ID from params, supporting post_id, id, url, and slug.
	 *
	 * @param array $params Action parameters.
	 * @return int|array Post ID on success, error array on failure.
	 */
	protected function resolve_post_id( array $params ) {
		$post_id = absint( $params['post_id'] ?? $params['id'] ?? 0 );

		// Resolve from URL if provided.
		if ( empty( $post_id ) && ! empty( $params['url'] ) ) {
			$post_id = url_to_postid( esc_url_raw( $params['url'] ) );
			if ( ! $post_id ) {
				return $this->error( __( 'Could not resolve URL to a post ID. Try providing post_id directly.', 'pressark' ) );
			}
		}

		// Resolve from slug if provided.
		if ( empty( $post_id ) && ! empty( $params['slug'] ) ) {
			$post_type = sanitize_text_field( $params['post_type'] ?? 'page' );
			$found     = get_page_by_path(
				sanitize_text_field( $params['slug'] ),
				OBJECT,
				$post_type
			);
			if ( $found ) {
				$post_id = $found->ID;
			} else {
				return $this->error(
					sprintf(
						/* translators: 1: WordPress post type, 2: requested slug */
						__( "No %1\$s found with slug '%2\$s'.", 'pressark' ),
						$post_type,
						$params['slug']
					)
				);
			}
		}

		if ( ! $post_id ) {
			return $this->error( __( 'Invalid post ID.', 'pressark' ) );
		}

		return $post_id;
	}

	// ── Plugin-Dependency Guards ────────────────────────────────────────

	/**
	 * Guard clause: require WooCommerce to be active.
	 *
	 * @return array|null null if WooCommerce is active, error array otherwise.
	 */
	protected function require_wc(): ?array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->error( __( 'WooCommerce is not active.', 'pressark' ) );
		}
		return null;
	}

	/**
	 * Guard clause: require Elementor to be active.
	 *
	 * @return array|null null if Elementor is active, error array otherwise.
	 */
	protected function require_elementor(): ?array {
		if ( ! PressArk_Elementor::is_active() ) {
			return $this->error( __( 'Elementor is not active.', 'pressark' ) );
		}
		return null;
	}

	// ── Preview Helpers ─────────────────────────────────────────────────

	/**
	 * Default preview for any action type that lacks a specific preview method.
	 *
	 * @param string $type   Action type name.
	 * @param array  $params Normalized action params.
	 * @return array Preview data (without 'type' key — caller adds that).
	 */
	public function default_preview( string $type, array $params ): array {
		return array(
			'changes' => array(
				array(
					'field'  => ucfirst( str_replace( '_', ' ', $type ?: 'Action' ) ),
					'before' => __( 'Current state', 'pressark' ),
					'after'  => __( 'Will be modified', 'pressark' ),
				),
			),
		);
	}

	/**
	 * Humanize a meta key for display in preview cards.
	 *
	 * @param string $key Raw meta key.
	 * @return string Human-readable label.
	 */
	protected function humanize_meta_key( string $key ): string {
		static $map = null;
		if ( $map === null ) {
			$map = array(
				'meta_title'                 => __( 'SEO Title', 'pressark' ),
				'meta_description'           => __( 'Meta Description', 'pressark' ),
				'og_title'                   => __( 'OG Title', 'pressark' ),
				'og_description'             => __( 'OG Description', 'pressark' ),
				'_pressark_meta_title'       => __( 'SEO Title', 'pressark' ),
				'_pressark_meta_description' => __( 'Meta Description', 'pressark' ),
				'_yoast_wpseo_title'         => __( 'SEO Title (Yoast)', 'pressark' ),
				'_yoast_wpseo_metadesc'      => __( 'Meta Description (Yoast)', 'pressark' ),
				'rank_math_title'            => __( 'SEO Title (RankMath)', 'pressark' ),
				'rank_math_description'      => __( 'Meta Description (RankMath)', 'pressark' ),
			);
		}
		return $map[ $key ] ?? ucfirst( str_replace( array( '_', '-' ), ' ', ltrim( $key, '_' ) ) );
	}

	// ── Checkpoint ──────────────────────────────────────────────────────

	/**
	 * Create a PressArk revision checkpoint for a post.
	 *
	 * Stores a WordPress revision tagged with `_pressark_checkpoint` meta
	 * so it can be identified as a pre-action snapshot.
	 *
	 * @param int    $post_id Post ID to checkpoint.
	 * @param string $action  Action name that triggered the checkpoint.
	 * @return int Revision ID (0 if revision creation failed).
	 */
	protected function create_checkpoint( int $post_id, string $action = '' ): int {
		$rev_id = wp_save_post_revision( $post_id );

		if ( $rev_id && ! is_wp_error( $rev_id ) ) {
			update_metadata( 'post', $rev_id, '_pressark_checkpoint', true );
			if ( $action ) {
				update_metadata( 'post', $rev_id, '_pressark_action', $action );
			}
		}

		return (int) $rev_id;
	}

	// ── SEO Meta Key Resolution ─────────────────────────────────────────

	/**
	 * Resolve a semantic SEO meta key to the correct plugin-specific key.
	 *
	 * Delegates to PressArk_SEO_Resolver. Kept for backward compatibility.
	 *
	 * @param string $key Semantic or raw meta key.
	 * @return string Resolved meta key.
	 */
	protected function resolve_meta_key( string $key ): string {
		return PressArk_SEO_Resolver::resolve_key( $key );
	}

	/**
	 * Detect which SEO plugin is active (cached per request).
	 *
	 * Delegates to PressArk_SEO_Resolver. Kept for backward compatibility.
	 *
	 * @return string|null Plugin slug or null if none detected.
	 */
	public static function detect_seo_plugin(): ?string {
		return PressArk_SEO_Resolver::detect();
	}
}
