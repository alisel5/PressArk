<?php
/**
 * PressArk Router - Unified routing decision.
 *
 * Decides whether a request goes to: async queue, agent, or legacy.
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
	public const ROUTE_LEGACY = 'legacy';

	/**
	 * Determine the execution route for a request.
	 *
	 * Evaluation order:
	 * 1. Async (long-running pattern match via PressArk_Task_Queue)
	 * 2. Legacy lightweight chat
	 * 3. Agent (models with native tool calling)
	 * 4. Legacy (text-only fallback)
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
		int                    $post_id = 0
	): array {
		unset( $engine, $tier, $screen, $post_id );

		$queue = new PressArk_Task_Queue();
		$async_score = $queue->async_score( $message );
		if ( $async_score >= PressArk_Task_Queue::ASYNC_THRESHOLD ) {
			return array(
				'route'   => self::ROUTE_ASYNC,
				'handler' => null,
				'meta'    => array(
					'approval_mode' => 'confirm',
					'reads_first'   => false,
					'async_score'   => $async_score,
					'route_reason'  => 'async_threshold',
					'phase_route'   => 'retrieval_planning',
				),
			);
		}

		if ( PressArk_Agent::is_lightweight_chat_request( $message, $conversation ) ) {
			return array(
				'route'   => self::ROUTE_LEGACY,
				'handler' => null,
				'meta'    => array(
					'approval_mode' => 'none',
					'reads_first'   => false,
					'route_reason'  => 'lightweight_chat',
					'phase_route'   => 'classification',
				),
			);
		}

		if ( $connector->supports_native_tools( $deep_mode ) ) {
			return array(
				'route'   => self::ROUTE_AGENT,
				'handler' => null,
				'meta'    => array(
					'approval_mode' => 'mixed',
					'reads_first'   => true,
					'route_reason'  => 'native_tools',
					'phase_route'   => 'classification',
				),
			);
		}

		return array(
			'route'   => self::ROUTE_LEGACY,
			'handler' => null,
			'meta'    => array(
				'approval_mode' => 'none',
				'reads_first'   => false,
				'route_reason'  => 'no_native_tools',
				'phase_route'   => 'classification',
			),
		);
	}
}
