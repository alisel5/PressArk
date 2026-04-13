<?php
/**
 * PressArk Router - Unified routing decision.
 *
 * Decides whether a request goes to: async queue, the agent loop, or the
 * approval-gated planning route.
 *
 * @package PressArk
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Router {

	public const ROUTE_ASYNC  = 'async';
	public const ROUTE_AGENT  = 'agent';
	public const ROUTE_PLAN   = 'plan';
	public const ROUTE_LEGACY = 'legacy'; // Deprecated 5.2.0 â€” all new traffic routes through agent.

	private static function ensure_plan_mode_loaded(): void {
		if ( ! class_exists( 'PressArk_Plan_Mode' ) ) {
			require_once __DIR__ . '/class-pressark-plan-mode.php';
		}
	}

	/**
	 * Probe the most likely tool candidates before reservation.
	 *
	 * v5.6.0: Pre-execution permission check (mirrors Claude Code
	 * checkPermissions pattern).
	 *
	 * @since 5.6.0
	 */
	private static function probe_permission( string $message, string $tier, string $screen, int $post_id ): array {
		if ( ! class_exists( 'PressArk_Tool_Catalog' ) || ! class_exists( 'PressArk_Tools' ) ) {
			return array();
		}

		if ( class_exists( 'PressArk_Agent' ) && PressArk_Agent::is_lightweight_chat_request( $message ) ) {
			return array();
		}

		$matches = PressArk_Tool_Catalog::instance()->discover( $message );
		if ( empty( $matches ) ) {
			return array();
		}

		$user_id      = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$first_denied = array();

		foreach ( array_slice( $matches, 0, 5 ) as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			$tool_name = sanitize_key( (string) ( $candidate['name'] ?? '' ) );
			if ( '' === $tool_name ) {
				continue;
			}

			$tool = PressArk_Tools::get_tool( $tool_name );
			if ( ! is_object( $tool ) || ! method_exists( $tool, 'check_permissions' ) ) {
				continue;
			}

			$params = array();
			if ( $post_id > 0
				&& ! isset( $params['post_id'], $params['id'], $params['product_id'], $params['order_id'] )
			) {
				$params['post_id'] = $post_id;
			}

			$permission = $tool->check_permissions( $params, $user_id, $tier );
			$permission['tool_name']        = $tool_name;
			$permission['tool_description'] = sanitize_text_field( (string) ( $candidate['description'] ?? '' ) );
			$permission['group']            = sanitize_key( (string) ( $candidate['group'] ?? '' ) );
			$permission['intent']           = $tool->is_readonly() ? 'read' : 'write';

			if ( ! empty( $permission['allowed'] ) ) {
				return $permission;
			}

			if ( self::should_defer_permission_ask_to_write_approval( $permission, $tool ) ) {
				continue;
			}

			if ( empty( $first_denied ) ) {
				$first_denied = $permission;
			}
		}

		return $first_denied;
	}

	/**
	 * Interactive write asks should fall through to the agent's preview/confirm UI.
	 */
	private static function should_defer_permission_ask_to_write_approval( array $permission, $tool ): bool {
		$behavior = sanitize_key( (string) ( $permission['behavior'] ?? '' ) );
		if ( 'ask' !== $behavior ) {
			return false;
		}

		$ui_action = sanitize_key( (string) ( $permission['ui_action'] ?? '' ) );
		if ( ! in_array( $ui_action, array( 'preview', 'confirm' ), true ) ) {
			return false;
		}

		return is_object( $tool )
			&& method_exists( $tool, 'is_readonly' )
			&& ! $tool->is_readonly();
	}

	/**
	 * Infer imperative write intent when permission probing cannot resolve a tool yet.
	 */
	private static function message_likely_requests_write( string $message ): bool {
		return 1 === preg_match(
			'/^\s*(?:please\s+)?(?:update|change|edit|modify|rewrite|replace|delete|remove|create|add|set|publish|increase|decrease|raise|lower|append|prepend|rename|move|fix|make)\b/i',
			(string) $message
		);
	}

	/**
	 * Legacy heuristic retained as one input signal for the planning policy.
	 */
	private static function should_trigger_plan( string $message, array $permission, array $options = array() ): bool {
		if ( ! empty( $options['suppress_plan'] ) ) {
			return false;
		}

		self::ensure_plan_mode_loaded();

		$raw_message = (string) ( $options['original_message'] ?? $message );
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

	/**
	 * Predict likely tool candidates to feed planning policy scoring.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function predict_tool_candidates( string $message ): array {
		if ( ! class_exists( 'PressArk_Tool_Catalog' ) ) {
			return array();
		}

		$matches = PressArk_Tool_Catalog::instance()->discover( $message );
		if ( empty( $matches ) || ! is_array( $matches ) ) {
			return array();
		}

		return array_values( array_slice( $matches, 0, 6 ) );
	}

	/**
	 * Build a backward-compatible default planning decision when the dedicated
	 * policy class is not available.
	 *
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
	 * Determine the execution route for a request.
	 *
	 * Evaluation order:
	 * 1. Hard plan (explicit /plan or policy escalation)
	 * 2. Soft plan (agent route with plan-first policy)
	 * 3. Async
	 * 4. Agent
	 *
	 * @param string                 $message      User message.
	 * @param array                  $conversation Conversation history.
	 * @param PressArk_AI_Connector  $connector    AI connector instance.
	 * @param PressArk_Action_Engine $engine       Action engine instance.
	 * @param string                 $tier         User's plan tier.
	 * @param bool                   $deep_mode    Whether deep mode is active.
	 * @param string                 $screen       Current admin screen slug.
	 * @param int                    $post_id      Current post ID.
	 * @return array{route: string, handler: null, meta: array}
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
		unset( $conversation, $engine );

		$permission    = self::probe_permission( $message, $tier, $screen, $post_id );
		$queue         = new PressArk_Task_Queue();
		$async_score   = $queue->async_score( $message );
		$legacy_signal = self::should_trigger_plan( $message, $permission, $options );

		self::ensure_plan_mode_loaded();
		$explicit_plan = PressArk_Plan_Mode::message_requests_plan( (string) ( $options['original_message'] ?? $message ) );

		$planning_decision = class_exists( 'PressArk_Planning_Policy' )
			? ( new PressArk_Planning_Policy() )->decide(
				$message,
				$permission,
				$async_score,
				array(
					'explicit_plan'      => $explicit_plan,
					'suppress_plan'      => ! empty( $options['suppress_plan'] ),
					'plan_execute'       => ! empty( $options['plan_execute'] ),
					'legacy_plan_signal' => $legacy_signal,
					'predicted_tools'    => self::predict_tool_candidates( $message ),
					'post_id'            => $post_id,
					'screen'             => $screen,
				)
			)
			: self::default_planning_decision( $legacy_signal );

		if ( 'hard_plan' === ( $planning_decision['mode'] ?? 'none' ) ) {
			return array(
				'route'   => self::ROUTE_PLAN,
				'handler' => null,
				'meta'    => array(
					'approval_mode'    => 'plan',
					'reads_first'      => true,
					'route_reason'     => $explicit_plan ? 'explicit_plan' : 'plan_policy_hard',
					'phase_route'      => 'plan_mode',
					'permission'       => $permission,
					'permission_mode'  => 'plan',
					'planning_mode'    => 'hard_plan',
					'planning_decision'=> $planning_decision,
					'async_score'      => $async_score,
				),
			);
		}

		$uses_native = $connector->supports_native_tools( $deep_mode );

		if ( 'soft_plan' === ( $planning_decision['mode'] ?? 'none' ) ) {
			return array(
				'route'   => self::ROUTE_AGENT,
				'handler' => null,
				'meta'    => array(
					'approval_mode'    => 'mixed',
					'reads_first'      => true,
					'route_reason'     => 'plan_policy_soft',
					'phase_route'      => 'classification',
					'native_tools'     => $uses_native,
					'permission'       => $permission,
					'planning_mode'    => 'soft_plan',
					'planning_decision'=> $planning_decision,
					'async_score'      => $async_score,
				),
			);
		}

		if ( $async_score >= PressArk_Task_Queue::ASYNC_THRESHOLD ) {
			return array(
				'route'   => self::ROUTE_ASYNC,
				'handler' => null,
				'meta'    => array(
					'approval_mode'    => 'confirm',
					'reads_first'      => false,
					'async_score'      => $async_score,
					'route_reason'     => 'async_threshold',
					'phase_route'      => 'retrieval_planning',
					'permission'       => $permission,
					'planning_mode'    => 'none',
					'planning_decision'=> $planning_decision,
				),
			);
		}

		return array(
			'route'   => self::ROUTE_AGENT,
			'handler' => null,
			'meta'    => array(
				'approval_mode'    => 'mixed',
				'reads_first'      => ! empty( $planning_decision['reads_first'] ),
				'route_reason'     => $uses_native ? 'native_tools' : 'prompted_tools',
				'phase_route'      => 'classification',
				'native_tools'     => $uses_native,
				'permission'       => $permission,
				'planning_mode'    => 'none',
				'planning_decision'=> $planning_decision,
				'async_score'      => $async_score,
			),
		);
	}
}
