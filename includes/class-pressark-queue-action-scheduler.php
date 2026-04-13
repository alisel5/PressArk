<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action Scheduler queue backend.
 *
 * Used when Action Scheduler is available (e.g. WooCommerce sites).
 * Provides more reliable execution than WP-Cron with built-in logging,
 * concurrency controls, and admin visibility.
 *
 * @package PressArk
 * @since   2.5.0
 */
class PressArk_Queue_Action_Scheduler extends PressArk_Queue_Backend {

	/**
	 * Hook name used for all async task processing.
	 */
	private const HOOK = 'pressark_process_async_task';

	/**
	 * Action Scheduler group for PressArk tasks.
	 */
	private const GROUP = 'pressark';

	/**
	 * Hook name used for safe read fan-out batches.
	 */
	private const SAFE_READ_HOOK = 'pressark_execute_safe_read_batch_item';

	/**
	 * In-request batch state used when the Action Scheduler runner is kicked inline.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $safe_read_batches = array();

	private static bool $safe_read_hook_registered = false;

	/**
	 * Schedule a task via Action Scheduler.
	 */
	public function schedule( string $task_id, int $delay = 5 ): bool {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}

		$action_id = as_schedule_single_action(
			time() + $delay,
			self::HOOK,
			array( $task_id ),
			self::GROUP
		);

		return $action_id > 0;
	}

	/**
	 * Cancel a scheduled task via Action Scheduler.
	 */
	public function cancel( string $task_id ): bool {
		if ( ! function_exists( 'as_unschedule_action' ) ) {
			return false;
		}

		as_unschedule_action(
			self::HOOK,
			array( $task_id ),
			self::GROUP
		);

		return true;
	}

	/**
	 * Backend identifier.
	 */
	public function get_name(): string {
		return 'action_scheduler';
	}

	/**
	 * Execute a safe read batch through Action Scheduler when available.
	 *
	 * The runner is kicked inline so the current request can collect results
	 * deterministically. If Action Scheduler cannot service the batch
	 * immediately, the method falls back to inline execution for any missing
	 * results so the agent loop still progresses.
	 *
	 * @param array[]  $calls
	 * @param callable $execute_fn
	 * @param callable $emit_fn
	 * @param callable $cancel_fn
	 * @return array{results: array[], cancelled: bool}
	 */
	public static function execute_safe_read_batch(
		array $calls,
		callable $execute_fn,
		callable $emit_fn,
		callable $cancel_fn
	): array {
		if ( empty( $calls ) ) {
			return array(
				'results'   => array(),
				'cancelled' => false,
			);
		}

		if ( ! class_exists( 'ActionScheduler' ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return self::execute_safe_read_batch_inline( $calls, $execute_fn, $emit_fn, $cancel_fn );
		}

		self::register_safe_read_hook();

		$batch_id = function_exists( 'wp_generate_uuid4' )
			? wp_generate_uuid4()
			: uniqid( 'pressark-safe-read-', true );

		self::$safe_read_batches[ $batch_id ] = array(
			'calls'      => array_values( $calls ),
			'execute_fn' => $execute_fn,
			'results'    => array(),
		);

		foreach ( $calls as $index => $tc ) {
			$emit_fn( 'reading', $tc, null );
			as_schedule_single_action(
				time(),
				self::SAFE_READ_HOOK,
				array( $batch_id, (int) $index ),
				self::GROUP
			);
		}

		if ( class_exists( 'PressArk_Cron_Manager' ) ) {
			PressArk_Cron_Manager::maybe_kick_as_runner();
		}

		$batch     = self::$safe_read_batches[ $batch_id ] ?? array();
		$results   = is_array( $batch['results'] ?? null ) ? $batch['results'] : array();
		$ordered   = array();
		$cancelled = false;

		foreach ( array_values( $calls ) as $index => $tc ) {
			if ( $cancel_fn() ) {
				$cancelled = true;
				break;
			}

			if ( ! array_key_exists( $index, $results ) ) {
				$results[ $index ] = $execute_fn( $tc );
			}

			$emit_fn( 'done', $tc, $results[ $index ] );
			$ordered[] = array(
				'tool_use_id' => $tc['id'],
				'result'      => $results[ $index ],
			);
		}

		unset( self::$safe_read_batches[ $batch_id ] );

		return array(
			'results'   => $ordered,
			'cancelled' => $cancelled,
		);
	}

	/**
	 * Action Scheduler callback for one item in a safe read batch.
	 */
	public static function handle_safe_read_batch_item( string $batch_id, int $index ): void {
		if ( ! isset( self::$safe_read_batches[ $batch_id ] ) ) {
			return;
		}

		$batch = &self::$safe_read_batches[ $batch_id ];
		$calls = is_array( $batch['calls'] ?? null ) ? $batch['calls'] : array();

		if ( ! isset( $calls[ $index ] ) ) {
			return;
		}

		$execute_fn = $batch['execute_fn'] ?? null;
		if ( ! is_callable( $execute_fn ) ) {
			return;
		}

		$batch['results'][ $index ] = $execute_fn( $calls[ $index ] );
	}

	/**
	 * Register the inline safe read hook once per request.
	 */
	private static function register_safe_read_hook(): void {
		if ( self::$safe_read_hook_registered ) {
			return;
		}

		add_action( self::SAFE_READ_HOOK, array( self::class, 'handle_safe_read_batch_item' ), 10, 2 );
		self::$safe_read_hook_registered = true;
	}

	/**
	 * Deterministic inline fallback for safe read batches.
	 *
	 * @param array[]  $calls
	 * @param callable $execute_fn
	 * @param callable $emit_fn
	 * @param callable $cancel_fn
	 * @return array{results: array[], cancelled: bool}
	 */
	private static function execute_safe_read_batch_inline(
		array $calls,
		callable $execute_fn,
		callable $emit_fn,
		callable $cancel_fn
	): array {
		$results   = array();
		$cancelled = false;

		foreach ( $calls as $tc ) {
			$emit_fn( 'reading', $tc, null );
		}

		foreach ( $calls as $tc ) {
			if ( $cancel_fn() ) {
				$cancelled = true;
				break;
			}

			$results[] = array(
				'tool_use_id' => $tc['id'],
				'result'      => $execute_fn( $tc ),
				'tc'          => $tc,
			);
		}

		foreach ( $results as $entry ) {
			$emit_fn( 'done', $entry['tc'], $entry['result'] );
		}

		return array(
			'results' => array_map(
				static function ( array $entry ): array {
					unset( $entry['tc'] );
					return $entry;
				},
				$results
			),
			'cancelled' => $cancelled,
		);
	}
}
