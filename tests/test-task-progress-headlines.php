<?php
/**
 * Deterministic background progress headline tests.
 *
 * Run: php pressark/tests/test-task-progress-headlines.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( (string) $key ) ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

class PressArk_Execution_Ledger {
	public static function progress_snapshot( array $execution ): array {
		return $execution;
	}
}

require_once __DIR__ . '/../includes/class-pressark-task-store.php';

$passed = 0;
$failed = 0;

function assert_same_progress( string $label, $expected, $actual ): void {
	global $passed, $failed;
	if ( $expected === $actual ) {
		$passed++;
		echo "  PASS: {$label}\n";
	} else {
		$failed++;
		echo "  FAIL: {$label}\n";
		echo '    Expected: ' . var_export( $expected, true ) . "\n";
		echo '    Actual:   ' . var_export( $actual, true ) . "\n";
	}
}

$store = new PressArk_Task_Store();

echo "=== Task Progress Headline Tests ===\n\n";

echo "--- Test 1: Approval wait beats generic worker completion ---\n";
$task = array(
	'task_id'         => 'task-confirm',
	'status'          => 'complete',
	'message'         => 'Apply the requested change.',
	'retries'         => 0,
	'payload'         => array(),
	'fail_reason'     => '',
	'handoff_capsule' => array(
		'target'         => 'Sample Page #12 (page)',
		'workflow_stage' => 'preview',
	),
	'result'          => array(
		'type' => 'confirm_card',
	),
);
$events = array(
	array(
		'event_type' => 'run.transition',
		'reason'     => 'approval_wait_confirm',
		'status'     => 'waiting',
		'summary'    => 'Run paused for confirmation.',
		'payload'    => array( 'pending_action_count' => 1 ),
	),
	array(
		'event_type' => 'worker.completed',
		'reason'     => 'worker_completed',
		'status'     => 'succeeded',
		'summary'    => 'Background worker completed and persisted its result.',
		'payload'    => array( 'result_type' => 'confirm_card' ),
	),
);
$progress = $store->build_progress_snapshot( $task, $events );

assert_same_progress( 'approval task state', 'waiting', $progress['state_key'] ?? '' );
assert_same_progress( 'approval event label', 'Waiting for confirmation', $progress['event_label'] ?? '' );
assert_same_progress( 'approval headline', 'Waiting for confirmation before applying changes for Sample Page #12 (page).', $progress['headline'] ?? '' );

echo "\n--- Test 2: Slot contention becomes a waiting headline ---\n";
$task = array(
	'task_id'         => 'task-slot',
	'status'          => 'queued',
	'message'         => 'Reprice the catalog.',
	'retries'         => 0,
	'payload'         => array(),
	'fail_reason'     => '',
	'handoff_capsule' => array(
		'target'         => 'Catalog Prices (product)',
		'workflow_stage' => 'plan',
	),
	'result'          => array(),
);
$events = array(
	array(
		'event_type' => 'worker.slot_contention',
		'reason'     => 'worker_slot_contention',
		'status'     => 'waiting',
		'summary'    => 'Background worker could not acquire a concurrency slot.',
		'payload'    => array( 'active_slots' => 2 ),
	),
	array(
		'event_type' => 'worker.deferred',
		'reason'     => 'worker_deferred',
		'status'     => 'queued',
		'summary'    => 'Background worker deferred the task and re-queued it for a later slot.',
		'payload'    => array( 'delay_seconds' => 10 ),
	),
);
$progress = $store->build_progress_snapshot( $task, $events );

assert_same_progress( 'slot contention state', 'waiting', $progress['state_key'] ?? '' );
assert_same_progress( 'slot contention stage', 'Plan', $progress['stage_label'] ?? '' );
assert_same_progress( 'slot contention headline', 'Waiting for a worker slot to continue planning the work for Catalog Prices (product).', $progress['headline'] ?? '' );

echo "\n--- Test 3: Retry headline includes failure context ---\n";
$task = array(
	'task_id'         => 'task-retry',
	'status'          => 'queued',
	'message'         => 'Refresh the docs page.',
	'retries'         => 1,
	'payload'         => array(
		'_last_failure' => array(
			'class'   => 'tool_error',
			'message' => 'Tool failed to read the target file.',
		),
	),
	'fail_reason'     => '',
	'handoff_capsule' => array(
		'target'         => 'Docs #9 (page)',
		'workflow_stage' => 'gather',
	),
	'result'          => array(),
);
$events = array(
	array(
		'event_type' => 'worker.retry_scheduled',
		'reason'     => 'retry_async_failure',
		'status'     => 'retrying',
		'summary'    => 'Async retry scheduled after a retryable failure.',
		'payload'    => array( 'delay_seconds' => 30, 'failure_class' => 'tool_error' ),
	),
);
$progress = $store->build_progress_snapshot( $task, $events );

assert_same_progress( 'retry state', 'retrying', $progress['state_key'] ?? '' );
assert_same_progress( 'retry headline', 'Retrying gathering context for Docs #9 (page) after Tool failed to read the target file.', $progress['headline'] ?? '' );
assert_same_progress( 'retry event label', 'Retry scheduled in 30s', $progress['event_label'] ?? '' );

echo "\n--- Test 4: Execution ledger feeds milestone summaries ---\n";
$task = array(
	'task_id'         => 'task-ledger',
	'status'          => 'running',
	'message'         => 'Verify the updated page.',
	'retries'         => 0,
	'payload'         => array(
		'checkpoint' => array(
			'workflow_stage' => 'verify',
			'execution'      => array(
				'completed_labels' => array( 'Applied the page update' ),
				'remaining_labels' => array( 'Verify the rendered output' ),
				'blocked_labels'   => array(),
			),
		),
	),
	'fail_reason'     => '',
	'handoff_capsule' => array(
		'target' => 'Launch Page #21 (page)',
	),
	'result'          => array(),
);
$events = array(
	array(
		'event_type' => 'worker.claimed',
		'reason'     => 'worker_claimed',
		'status'     => 'running',
		'summary'    => 'Background worker claimed the queued task.',
		'payload'    => array(),
	),
);
$progress = $store->build_progress_snapshot( $task, $events );

assert_same_progress( 'verify state', 'verifying', $progress['state_key'] ?? '' );
assert_same_progress( 'verify stage', 'Verify', $progress['stage_label'] ?? '' );
assert_same_progress( 'verify milestone summary', 'Completed: Applied the page update • Next: Verify the rendered output', $progress['milestone_summary'] ?? '' );

echo "\nResults: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
