<?php
/**
 * PressArk Router compatibility facade.
 *
 * The active route decision now lives in `PressArk_Route_Arbiter`; this class
 * remains as the backward-compatible adapter that preserves the legacy
 * `resolve()` payload shape for existing callers.
 *
 * @package PressArk
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Router {

	public const ROUTE_ASYNC = 'async';
	public const ROUTE_AGENT = 'agent';
	public const ROUTE_PLAN  = 'plan';

	/**
	 * Compatibility-only constant retained for older integrations.
	 *
	 * New route arbitration never emits this route.
	 */
	public const ROUTE_LEGACY = 'legacy';

	private static function ensure_plan_mode_loaded(): void {
		if ( ! class_exists( 'PressArk_Plan_Mode' ) ) {
			require_once __DIR__ . '/class-pressark-plan-mode.php';
		}
	}

	/**
	 * Backward-compatible entrypoint used by older callers.
	 *
	 * @return array{route:string,handler:null,meta:array<string,mixed>}
	 */
	public static function resolve(
		string                 $message,
		array                  $conversation,
		PressArk_AI_Connector  $connector,
		PressArk_Action_Engine $engine,
		string                 $tier,
		bool                   $deep_mode = false,
		string                 $screen = '',
		int                    $post_id = 0,
		array                  $options = array()
	): array {
		unset( $engine );

		$context = PressArk_Request_Context::from_array(
			array(
				'message'           => $message,
				'original_message'  => (string) ( $options['original_message'] ?? $message ),
				'conversation'      => $conversation,
				'tier'              => $tier,
				'deep_mode'         => $deep_mode,
				'screen'            => $screen,
				'post_id'           => $post_id,
				'continuation_mode' => (string) ( $options['continuation_mode'] ?? '' ),
				'suppress_plan'     => ! empty( $options['suppress_plan'] ),
				'plan_execute'      => ! empty( $options['plan_execute'] ),
			)
		);

		return self::resolve_context( $context, $connector );
	}

	/**
	 * Resolve routing for an already-normalized request context.
	 *
	 * @return array{route:string,handler:null,meta:array<string,mixed>}
	 */
	public static function resolve_context(
		PressArk_Request_Context $context,
		?PressArk_AI_Connector $connector = null
	): array {
		$permission_probe = new PressArk_Permission_Probe();
		$preload_planner  = new PressArk_Tool_Preload_Planner();
		$arbiter          = new PressArk_Route_Arbiter();

		if ( ! $context->explicit_plan && self::message_requests_plan( $context->original_message, $context->suppress_plan ) ) {
			$context = $context->with(
				array(
					'explicit_plan' => true,
				)
			);
		}

		$context = $context->with(
			array(
				'async_score'  => self::resolve_async_score( $context ),
				'native_tools' => $connector ? $connector->supports_native_tools( $context->deep_mode ) : false,
			)
		);

		$permission = $permission_probe->probe( $context );
		$context    = $context->with(
			array(
				'permission_probe' => $permission,
			)
		);

		$preload_plan = $preload_planner->plan( $context, $permission );
		$context      = $context->with(
			array(
				'preload_plan' => $preload_plan,
			)
		);

		$planning_decision = self::resolve_planning_decision( $context, $permission, $preload_plan, $preload_planner );
		$context           = $context->with(
			array(
				'planning_decision' => $planning_decision,
			)
		);

		$decision = $arbiter->decide( $context );

		return self::build_compatibility_payload( $decision, $context );
	}

	/**
	 * Adapt the new routing DTOs back to the historical router payload shape.
	 *
	 * @return array{route:string,handler:null,meta:array<string,mixed>}
	 */
	public static function build_compatibility_payload(
		PressArk_Route_Decision $decision,
		PressArk_Request_Context $context
	): array {
		$planning_decision = is_array( $context->planning_decision ) ? $context->planning_decision : array();
		$preload_plan      = is_array( $context->preload_plan ) ? $context->preload_plan : array();
		$preloaded_groups  = self::normalize_groups( (array) ( $preload_plan['groups'] ?? $preload_plan['preloaded_groups'] ?? array() ) );
		$planning_mode     = sanitize_key(
			(string) (
				$planning_decision['mode']
				?? ( ! empty( $preloaded_groups ) ? 'hard_plan' : 'none' )
			)
		);
		$max_discover_calls = isset( $planning_decision['max_discover_calls'] )
			? max( 0, (int) $planning_decision['max_discover_calls'] )
			: max( 0, (int) ( $preload_plan['max_discover_calls'] ?? PressArk_Tool_Preload_Planner::default_max_discover_calls() ) );

		$meta = array(
			'approval_mode'     => sanitize_key( (string) $decision->advisory( 'approval_mode', 'mixed' ) ),
			'reads_first'       => ! empty( $decision->advisory( 'reads_first', false ) ),
			'route_reason'      => sanitize_key( (string) $decision->reason( 'route_reason', '' ) ),
			'phase_route'       => sanitize_key( (string) $decision->reason( 'phase_route', 'classification' ) ),
			'permission'        => is_array( $context->permission_probe ) ? $context->permission_probe : array(),
			'planning_mode'     => $planning_mode,
			'planning_decision' => $planning_decision,
			'task_type'         => sanitize_key( (string) ( $preload_plan['task_type'] ?? '' ) ),
			'preloaded_groups'  => $preloaded_groups,
			'preload_plan'      => $preload_plan,
			'max_discover_calls'=> $max_discover_calls,
			'async_score'       => max( 0, (int) $context->async_score ),
			'route_decision'    => $decision->to_array(),
		);

		if ( null !== $decision->advisory( 'native_tools', null ) ) {
			$meta['native_tools'] = ! empty( $decision->advisory( 'native_tools', false ) );
		}

		$permission_mode = sanitize_key( (string) $decision->advisory( 'permission_mode', '' ) );
		if ( '' !== $permission_mode ) {
			$meta['permission_mode'] = $permission_mode;
		}

		return array(
			'route'   => $decision->route,
			'handler' => null,
			'meta'    => $meta,
		);
	}

	private static function resolve_async_score( PressArk_Request_Context $context ): int {
		if ( 'execute' === $context->continuation_mode ) {
			return 0;
		}

		$queue = new PressArk_Task_Queue();

		return max( 0, (int) $queue->async_score( $context->message ) );
	}

	/**
	 * @param array<string,mixed> $permission
	 * @param array<string,mixed> $preload_plan
	 * @return array<string,mixed>
	 */
	private static function resolve_planning_decision(
		PressArk_Request_Context $context,
		array $permission,
		array $preload_plan,
		?PressArk_Tool_Preload_Planner $preload_planner = null
	): array {
		$legacy_signal = self::should_trigger_plan( $context, $permission );

		$planning_decision = class_exists( 'PressArk_Planning_Policy' )
			? ( new PressArk_Planning_Policy() )->decide(
				$context->message,
				$permission,
				$context->async_score,
				array(
					'explicit_plan'      => $context->explicit_plan,
					'suppress_plan'      => $context->suppress_plan,
					'plan_execute'       => $context->plan_execute,
					'continuation_mode'  => $context->continuation_mode,
					'legacy_plan_signal' => $legacy_signal,
					'predicted_tools'    => (array) ( $preload_plan['predicted_tools'] ?? array() ),
					'post_id'            => $context->post_id,
					'screen'             => $context->screen,
				)
			)
			: self::default_planning_decision( $legacy_signal );

		$preload_planner = $preload_planner ?: new PressArk_Tool_Preload_Planner();

		return $preload_planner->apply_planning_advisory( $planning_decision, $preload_plan );
	}

	private static function message_requests_plan( string $message, bool $suppress_plan = false ): bool {
		if ( $suppress_plan ) {
			return false;
		}

		self::ensure_plan_mode_loaded();

		return PressArk_Plan_Mode::message_requests_plan( $message );
	}

	/**
	 * Legacy heuristic retained only as one input signal for the planning policy.
	 *
	 * @param array<string,mixed> $permission
	 */
	private static function should_trigger_plan( PressArk_Request_Context $context, array $permission ): bool {
		if ( $context->suppress_plan ) {
			return false;
		}

		self::ensure_plan_mode_loaded();

		$raw_message = '' !== trim( $context->original_message ) ? $context->original_message : $context->message;
		$normalized  = PressArk_Plan_Mode::strip_plan_directive( $raw_message );
		if ( '' === trim( $normalized ) ) {
			return false;
		}

		if ( PressArk_Plan_Mode::message_requests_plan( $raw_message ) ) {
			return true;
		}

		$intent = sanitize_key( (string) ( $permission['intent'] ?? '' ) );
		if ( 'write' !== $intent && ! self::message_likely_requests_write( $normalized ) ) {
			return false;
		}

		return mb_strlen( $normalized ) > 120
			|| 1 === preg_match( '/\b(?:all|every|bulk|site-?wide|across|multiple|batch)\b/i', $normalized )
			|| 1 === preg_match( '/\b\d+\s+(?:products?|posts?|pages?|orders?|items?|records?)\b/i', $normalized )
			|| 1 === preg_match( '/\b(?:dozens?|hundreds?)\b/i', $normalized );
	}

	private static function message_likely_requests_write( string $message ): bool {
		$message = (string) $message;

		return 1 === preg_match(
			'/^\s*(?:please\s+)?(?:update|change|edit|modify|rewrite|replace|delete|remove|create|add|set|publish|increase|decrease|raise|lower|append|prepend|rename|move|fix|make)\b/i',
			$message
		) || (
			1 === preg_match( '/\b(?:product|products|catalog|catalogue|store|shop|woo|woocommerce)\b/i', $message )
			&& 1 === preg_match( '/\b(?:price|pricing|sale|discount|markdown|markup|off|regular price|sale price)\b/i', $message )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function default_planning_decision( bool $legacy_signal ): array {
		return array(
			'mode'                   => $legacy_signal ? 'hard_plan' : 'none',
			'approval_required'      => $legacy_signal,
			'reads_first'            => $legacy_signal,
			'reason_codes'           => $legacy_signal ? array( 'legacy_plan_signal' ) : array(),
			'complexity_score'       => 0,
			'risk_score'             => 0,
			'breadth_score'          => 0,
			'uncertainty_score'      => 0,
			'destructive_score'      => 0,
			'predicted_write_count'  => 0,
			'predicted_domain_count' => 0,
		);
	}

	/**
	 * @param array<int,string> $groups
	 * @return array<int,string>
	 */
	private static function normalize_groups( array $groups ): array {
		return array_values(
			array_filter(
				array_unique(
					array_map( 'sanitize_text_field', $groups )
				)
			)
		);
	}
}
