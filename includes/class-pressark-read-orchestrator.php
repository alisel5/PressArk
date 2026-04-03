<?php
/**
 * PressArk Read Orchestrator — Safe batched execution of read tool calls.
 *
 * Analogous to Claude Code's partitionToolCalls + runToolsConcurrently pattern
 * (see claude-code-main/src/services/tools/toolOrchestration.ts), adapted for
 * PHP/WordPress's single-threaded reality.
 *
 * Claude Code partitions tool calls into consecutive batches:
 *   - Consecutive concurrency-safe tools → one batch, run concurrently (pool limit)
 *   - Each non-safe tool → its own serial batch
 * Results are always returned in the original model emission order.
 *
 * In WordPress we cannot safely use true parallelism (no async I/O, shared
 * globals: $wpdb, object cache, current_user, hooks, locale). Instead, "batched
 * execution" means:
 *   - Safe reads in a batch execute without inter-result dependencies.
 *   - All "reading" step events emit upfront; all "done" events emit after.
 *   - Checkpoint updates happen in batch order (deterministic).
 *   - This architecture enables future query batching (N post reads → 1 SQL).
 *   - Serial batches execute one tool at a time with full side-effect handling.
 *
 * Preview and confirm tool calls never enter this orchestrator — they are
 * handled separately in the agent loop with strict approval semantics.
 *
 * @package PressArk
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Read_Orchestrator {

	/**
	 * Maximum number of tools in a single batched group.
	 *
	 * Analogous to CLAUDE_CODE_MAX_TOOL_USE_CONCURRENCY (default 10).
	 * Limits memory usage and keeps step-event output predictable.
	 */
	private const MAX_BATCH_SIZE = 8;

	/**
	 * Partition an ordered list of read tool calls into execution batches.
	 *
	 * Algorithm (mirrors Claude Code's partitionToolCalls):
	 *   1. Walk the array in model-emission order.
	 *   2. If the tool is concurrency-safe AND the current batch is safe AND
	 *      the batch hasn't hit MAX_BATCH_SIZE → append to current batch.
	 *   3. Otherwise → close the current batch, start a new one.
	 *
	 * Each batch is an associative array:
	 *   [ 'safe' => bool, 'calls' => array<tool_call> ]
	 *
	 * @param array[] $read_calls Ordered tool calls, each with 'name', 'id', 'arguments'.
	 * @return array[] Array of batches in deterministic order.
	 */
	public static function partition( array $read_calls ): array {
		if ( empty( $read_calls ) ) {
			return array();
		}

		$batches       = array();
		$current_batch = null;

		foreach ( $read_calls as $tc ) {
			$name = $tc['name'];
			$args = $tc['arguments'] ?? array();
			$safe = PressArk_Operation_Registry::is_concurrency_safe( $name, $args );

			if ( null === $current_batch ) {
				// First tool: start the first batch.
				$current_batch = array(
					'safe'  => $safe,
					'calls' => array( $tc ),
				);
				continue;
			}

			// Can we append to the current batch?
			$can_append = $safe
				&& $current_batch['safe']
				&& count( $current_batch['calls'] ) < self::MAX_BATCH_SIZE;

			if ( $can_append ) {
				$current_batch['calls'][] = $tc;
			} else {
				// Close current batch, start a new one.
				$batches[]     = $current_batch;
				$current_batch = array(
					'safe'  => $safe,
					'calls' => array( $tc ),
				);
			}
		}

		// Flush the last batch.
		if ( null !== $current_batch ) {
			$batches[] = $current_batch;
		}

		return $batches;
	}

	/**
	 * Execute partitioned read batches and return results in deterministic order.
	 *
	 * For safe batches: emits all "reading" steps upfront, executes all tools,
	 * then emits all "done" steps. This is the PHP-safe equivalent of concurrent
	 * execution — no tool in the batch can see another's result mid-flight.
	 *
	 * For serial batches: executes one tool at a time with full step events
	 * between each, preserving the sequential contract for meta-tools and
	 * tools with checkpoint dependencies.
	 *
	 * @param array[]  $batches    Output of self::partition().
	 * @param callable $execute_fn fn(array $tc): array — executes a single tool call,
	 *                             returns the raw result array.
	 * @param callable $emit_fn    fn(string $status, array $tc, ?array $result): void
	 *                             — emits step events (reading, done).
	 * @param callable $cancel_fn  fn(): bool — returns true if the run is cancelled.
	 * @return array{results: array[], cancelled: bool}
	 *   results: ordered array of ['tool_use_id' => string, 'result' => array].
	 */
	public static function execute(
		array $batches,
		callable $execute_fn,
		callable $emit_fn,
		callable $cancel_fn
	): array {
		$all_results = array();
		$cancelled   = false;

		foreach ( $batches as $batch ) {
			if ( $cancel_fn() ) {
				$cancelled = true;
				break;
			}

			if ( $batch['safe'] && count( $batch['calls'] ) > 1 ) {
				// ── Batched execution (safe reads) ──────────────────
				$batch_result = self::execute_batch(
					$batch['calls'],
					$execute_fn,
					$emit_fn,
					$cancel_fn
				);
				$all_results = array_merge( $all_results, $batch_result['results'] );
				if ( $batch_result['cancelled'] ) {
					$cancelled = true;
					break;
				}
			} else {
				// ── Serial execution (unsafe or single tool) ────────
				foreach ( $batch['calls'] as $tc ) {
					if ( $cancel_fn() ) {
						$cancelled = true;
						break 2;
					}

					$emit_fn( 'reading', $tc, null );
					$result = $execute_fn( $tc );
					$emit_fn( 'done', $tc, $result );

					$all_results[] = array(
						'tool_use_id' => $tc['id'],
						'result'      => $result,
					);
				}
			}
		}

		return array(
			'results'   => $all_results,
			'cancelled' => $cancelled,
		);
	}

	/**
	 * Execute a batch of safe reads with grouped step events.
	 *
	 * 1. Emit "reading" for all tools in the batch.
	 * 2. Execute all tools (sequentially in PHP, but without inter-result checks).
	 * 3. Emit "done" for all tools in the batch.
	 *
	 * This mirrors "concurrent" semantics: no tool in the batch observes
	 * another tool's result during its own execution.
	 *
	 * @return array{results: array[], cancelled: bool}
	 */
	private static function execute_batch(
		array $calls,
		callable $execute_fn,
		callable $emit_fn,
		callable $cancel_fn
	): array {
		$results   = array();
		$cancelled = false;

		// Phase 1: Emit all "reading" events upfront.
		foreach ( $calls as $tc ) {
			$emit_fn( 'reading', $tc, null );
		}

		// Phase 2: Execute all tools.
		foreach ( $calls as $tc ) {
			if ( $cancel_fn() ) {
				$cancelled = true;
				break;
			}

			$result    = $execute_fn( $tc );
			$results[] = array(
				'tool_use_id' => $tc['id'],
				'result'      => $result,
				'tc'          => $tc,
			);
		}

		// Phase 3: Emit all "done" events in order.
		foreach ( $results as $entry ) {
			$emit_fn( 'done', $entry['tc'], $entry['result'] );
		}

		// Strip internal 'tc' key before returning.
		$clean = array_map(
			fn( $entry ) => array(
				'tool_use_id' => $entry['tool_use_id'],
				'result'      => $entry['result'],
			),
			$results
		);

		return array(
			'results'   => $clean,
			'cancelled' => $cancelled,
		);
	}

	/**
	 * Describe batches for debug logging.
	 *
	 * @return string Human-readable batch summary.
	 */
	public static function describe_batches( array $batches ): string {
		$parts = array();
		foreach ( $batches as $i => $batch ) {
			$names = array_map( fn( $tc ) => $tc['name'], $batch['calls'] );
			$mode  = $batch['safe'] ? 'batched' : 'serial';
			$parts[] = sprintf( 'B%d[%s:%s]', $i, $mode, implode( ',', $names ) );
		}
		return implode( ' → ', $parts );
	}
}
