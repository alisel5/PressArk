<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure route arbiter: accepts a request context and selects only the route.
 */
class PressArk_Route_Arbiter {

	public function decide( PressArk_Request_Context $context ): PressArk_Route_Decision {
		$planning_mode     = sanitize_key( (string) ( $context->planning_decision['mode'] ?? 'none' ) );
		$planning_reasons  = array_values( array_filter( array_map( 'sanitize_key', (array) ( $context->planning_decision['reason_codes'] ?? array() ) ) ) );
		$preload_groups    = array_values( array_filter( array_map( 'sanitize_key', (array) ( $context->preload_plan['groups'] ?? array() ) ) ) );
		$hard_plan_resume  = ! empty( $preload_groups ) && 'execute' === $context->continuation_mode;

		if ( 'hard_plan' === $planning_mode && ! $hard_plan_resume ) {
			$route_reason = 'plan' === $context->continuation_mode
				? 'continuation_plan_resume'
				: ( $context->explicit_plan ? 'explicit_plan' : 'plan_policy_hard' );

			return new PressArk_Route_Decision(
				PressArk_Router::ROUTE_PLAN,
				array(
					'route_reason' => $route_reason,
					'phase_route'  => 'plan_mode',
					'reason_codes' => array_values( array_unique( array_merge( $planning_reasons, array( sanitize_key( $route_reason ) ) ) ) ),
				),
				array(
					'permission_probe' => true,
					'preload_planning' => true,
					'approval_mode'    => 'plan',
					'permission_mode'  => 'plan',
					'reads_first'      => true,
				)
			);
		}

		if ( 'soft_plan' === $planning_mode ) {
			return new PressArk_Route_Decision(
				PressArk_Router::ROUTE_AGENT,
				array(
					'route_reason' => 'plan_policy_soft',
					'phase_route'  => 'classification',
					'reason_codes' => array_values( array_unique( array_merge( $planning_reasons, array( 'plan_policy_soft' ) ) ) ),
				),
				array(
					'permission_probe' => true,
					'preload_planning' => true,
					'approval_mode'    => 'mixed',
					'reads_first'      => true,
					'native_tools'     => $context->native_tools,
				)
			);
		}

		if ( $context->async_score >= PressArk_Task_Queue::ASYNC_THRESHOLD ) {
			return new PressArk_Route_Decision(
				PressArk_Router::ROUTE_ASYNC,
				array(
					'route_reason' => 'async_threshold',
					'phase_route'  => 'retrieval_planning',
					'reason_codes' => array_values( array_unique( array_merge( $planning_reasons, array( 'async_threshold' ) ) ) ),
				),
				array(
					'permission_probe' => true,
					'preload_planning' => true,
					'approval_mode'    => 'confirm',
					'reads_first'      => false,
				)
			);
		}

		$route_reason = 'execute' === $context->continuation_mode
			? ( ! empty( $preload_groups ) ? 'continuation_preloaded_execute_resume' : 'continuation_execute_resume' )
			: ( $context->native_tools ? 'native_tools' : 'prompted_tools' );

		return new PressArk_Route_Decision(
			PressArk_Router::ROUTE_AGENT,
			array(
				'route_reason' => $route_reason,
				'phase_route'  => 'classification',
				'reason_codes' => array_values( array_unique( array_merge( $planning_reasons, array( sanitize_key( $route_reason ) ) ) ) ),
			),
			array(
				'permission_probe' => true,
				'preload_planning' => true,
				'approval_mode'    => 'mixed',
				'reads_first'      => ! empty( $context->planning_decision['reads_first'] ),
				'native_tools'     => $context->native_tools,
			)
		);
	}
}
