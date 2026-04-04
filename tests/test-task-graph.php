<?php
/**
 * Targeted verification for the v5.3.0 task graph and plan state features.
 *
 * Run: php pressark/tests/test-task-graph.php
 *
 * This is a standalone unit test that does NOT require WordPress. It stubs
 * only the WordPress functions used by the ledger/checkpoint sanitizers.
 */

// ── WordPress stubs ─────────────────────────────────────────────────
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( trim( $key ) ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( intval( $v ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

require_once __DIR__ . '/../includes/class-pressark-execution-ledger.php';

// ── Test Helpers ────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function assert_eq( $label, $expected, $actual ) {
	global $passed, $failed;
	if ( $expected === $actual ) {
		$passed++;
		echo "  PASS: {$label}\n";
	} else {
		$failed++;
		echo "  FAIL: {$label}\n";
		echo "    Expected: " . var_export( $expected, true ) . "\n";
		echo "    Actual:   " . var_export( $actual, true ) . "\n";
	}
}

function assert_true( $label, $actual ) {
	assert_eq( $label, true, $actual );
}

function assert_false( $label, $actual ) {
	assert_eq( $label, false, $actual );
}

// ── Test 1: Backward compat — `done` normalized to `completed` ─────
echo "\n=== Test 1: Status normalization ===\n";
$old_ledger = [
	'source_message' => 'Create a blog post with SEO',
	'goal_hash'      => md5( 'Create a blog post with SEO' ),
	'request_counts' => [ 'create_post' => 1 ],
	'tasks'          => [
		[ 'key' => 'create_post', 'label' => 'Create post', 'status' => 'done', 'evidence' => 'Created #42' ],
		[ 'key' => 'optimize_seo', 'label' => 'SEO', 'status' => 'pending', 'evidence' => '' ],
	],
	'receipts'       => [],
	'current_target' => [],
	'updated_at'     => '',
];
$sanitized = PressArk_Execution_Ledger::sanitize( $old_ledger );
assert_eq( 'done → completed', 'completed', $sanitized['tasks'][0]['status'] );
assert_eq( 'pending stays pending', 'pending', $sanitized['tasks'][1]['status'] );
assert_eq( 'depends_on defaults to []', [], $sanitized['tasks'][0]['depends_on'] );
assert_eq( 'metadata defaults to []', [], $sanitized['tasks'][0]['metadata'] );

// ── Test 2: Dependency extraction from message ──────────────────────
echo "\n=== Test 2: Dependency extraction ===\n";
$ledger = PressArk_Execution_Ledger::sanitize( [
	'source_message' => '',
	'goal_hash'      => '',
	'request_counts' => [],
	'tasks'          => [],
	'receipts'       => [],
	'current_target' => [],
	'updated_at'     => '',
] );
$ledger = PressArk_Execution_Ledger::bootstrap( $ledger, 'Pick a random product, create a blog post about it, optimize SEO, and publish it' );
$tasks = $ledger['tasks'];

// Should have: select_source, create_post, optimize_seo, publish_content
assert_eq( 'task count', 4, count( $tasks ) );
assert_eq( 'select_source has no deps', [], $tasks[0]['depends_on'] );
assert_eq( 'create_post depends on select_source', [ 'select_source' ], $tasks[1]['depends_on'] );
assert_eq( 'optimize_seo depends on create_post', [ 'create_post' ], $tasks[2]['depends_on'] );
assert_true( 'publish depends on create_post', in_array( 'create_post', $tasks[3]['depends_on'] ) );
assert_true( 'publish depends on optimize_seo', in_array( 'optimize_seo', $tasks[3]['depends_on'] ) );

// ── Test 3: Blocked resolution ──────────────────────────────────────
echo "\n=== Test 3: Blocked resolution ===\n";
// Initially, only select_source should be pending, rest blocked.
$resolved = PressArk_Execution_Ledger::resolve_blocked( $ledger );
assert_eq( 'select_source is pending', 'pending', $resolved['tasks'][0]['status'] );
assert_eq( 'create_post is blocked', 'blocked', $resolved['tasks'][1]['status'] );
assert_eq( 'optimize_seo is blocked', 'blocked', $resolved['tasks'][2]['status'] );
assert_eq( 'publish_content is blocked', 'blocked', $resolved['tasks'][3]['status'] );

// ── Test 4: Completing a task unblocks dependents ───────────────────
echo "\n=== Test 4: Completion unblocking ===\n";
// Simulate completing select_source via the private mark_task_done path.
// We'll use the public add_task + resolve path instead.
$ledger2 = $resolved;
// Mark select_source as completed.
foreach ( $ledger2['tasks'] as &$t ) {
	if ( 'select_source' === $t['key'] ) {
		$t['status'] = 'completed';
		$t['evidence'] = 'Widget Pro (#55)';
	}
}
unset( $t );
$ledger2 = PressArk_Execution_Ledger::resolve_blocked( $ledger2 );
assert_eq( 'create_post unblocked to pending', 'pending', $ledger2['tasks'][1]['status'] );
assert_eq( 'optimize_seo still blocked', 'blocked', $ledger2['tasks'][2]['status'] );
assert_eq( 'publish still blocked', 'blocked', $ledger2['tasks'][3]['status'] );

// Now complete create_post.
foreach ( $ledger2['tasks'] as &$t ) {
	if ( 'create_post' === $t['key'] ) {
		$t['status'] = 'completed';
		$t['evidence'] = 'Created "Top 5 Widgets" (#100)';
	}
}
unset( $t );
$ledger2 = PressArk_Execution_Ledger::resolve_blocked( $ledger2 );
assert_eq( 'optimize_seo unblocked', 'pending', $ledger2['tasks'][2]['status'] );
assert_eq( 'publish still blocked (needs optimize_seo)', 'blocked', $ledger2['tasks'][3]['status'] );

// ── Test 5: mark_task_in_progress ───────────────────────────────────
echo "\n=== Test 5: In-progress marking ===\n";
$ledger3 = PressArk_Execution_Ledger::mark_task_in_progress( $ledger2, 'optimize_seo' );
assert_eq( 'optimize_seo is in_progress', 'in_progress', $ledger3['tasks'][2]['status'] );

// ── Test 6: next_actionable_task ────────────────────────────────────
echo "\n=== Test 6: Next actionable task ===\n";
$next = PressArk_Execution_Ledger::next_actionable_task( $ledger3 );
assert_eq( 'next actionable is in_progress optimize_seo', 'optimize_seo', $next['key'] );

// Complete optimize_seo, check next.
foreach ( $ledger3['tasks'] as &$t ) {
	if ( 'optimize_seo' === $t['key'] ) {
		$t['status'] = 'completed';
	}
}
unset( $t );
$ledger3 = PressArk_Execution_Ledger::resolve_blocked( $ledger3 );
$next2 = PressArk_Execution_Ledger::next_actionable_task( $ledger3 );
assert_eq( 'publish_content now actionable', 'publish_content', $next2['key'] );
assert_eq( 'publish_content is pending', 'pending', $next2['status'] );

// ── Test 7: add_task with dependencies ──────────────────────────────
echo "\n=== Test 7: Dynamic task addition ===\n";
$base = PressArk_Execution_Ledger::sanitize( [
	'source_message' => 'test',
	'goal_hash'      => md5( 'test' ),
	'request_counts' => [],
	'tasks'          => [
		[ 'key' => 'step_a', 'label' => 'Step A', 'status' => 'pending', 'evidence' => '' ],
	],
	'receipts'       => [],
	'current_target' => [],
	'updated_at'     => '',
] );
$base = PressArk_Execution_Ledger::add_task( $base, 'step_b', 'Step B', [ 'step_a' ], [ 'priority' => 'high' ] );
assert_eq( 'task count after add', 2, count( $base['tasks'] ) );
assert_eq( 'step_b is blocked', 'blocked', $base['tasks'][1]['status'] );
assert_eq( 'step_b depends on step_a', [ 'step_a' ], $base['tasks'][1]['depends_on'] );
assert_eq( 'step_b metadata preserved', 'high', $base['tasks'][1]['metadata']['priority'] );

// ── Test 8: progress_snapshot with new statuses ─────────────────────
echo "\n=== Test 8: Progress snapshot ===\n";
$snap_ledger = PressArk_Execution_Ledger::sanitize( [
	'source_message' => 'test',
	'goal_hash'      => md5( 'test' ),
	'request_counts' => [],
	'tasks'          => [
		[ 'key' => 'a', 'label' => 'A', 'status' => 'completed', 'evidence' => 'done' ],
		[ 'key' => 'b', 'label' => 'B', 'status' => 'in_progress', 'evidence' => '', 'depends_on' => [ 'a' ] ],
		[ 'key' => 'c', 'label' => 'C', 'status' => 'blocked', 'evidence' => '', 'depends_on' => [ 'b' ] ],
	],
	'receipts'       => [],
	'current_target' => [],
	'updated_at'     => '',
] );
$snap = PressArk_Execution_Ledger::progress_snapshot( $snap_ledger );
assert_eq( 'total tasks', 3, $snap['total_tasks'] );
assert_eq( 'completed count', 1, $snap['completed_count'] );
assert_eq( 'remaining count', 2, $snap['remaining_count'] );
assert_eq( 'blocked count', 1, $snap['blocked_count'] );
assert_eq( 'in_progress count', 1, $snap['in_progress_count'] );
assert_eq( 'next task is B (in_progress)', 'b', $snap['next_task_key'] );
assert_true( 'should_auto_resume (not all blocked)', $snap['should_auto_resume'] );

// ── Test 9: All blocked = no auto-resume ────────────────────────────
echo "\n=== Test 9: All-blocked prevents auto-resume ===\n";
$all_blocked = PressArk_Execution_Ledger::sanitize( [
	'source_message' => 'test',
	'goal_hash'      => md5( 'test' ),
	'request_counts' => [],
	'tasks'          => [
		[ 'key' => 'x', 'label' => 'X', 'status' => 'blocked', 'evidence' => '', 'depends_on' => [ 'missing' ] ],
	],
	'receipts'       => [],
	'current_target' => [],
	'updated_at'     => '',
] );
$snap2 = PressArk_Execution_Ledger::progress_snapshot( $all_blocked );
assert_false( 'should NOT auto-resume when all remaining are blocked', $snap2['should_auto_resume'] );

// ── Test 10: Merge preserves new fields ─────────────────────────────
echo "\n=== Test 10: Merge with new fields ===\n";
$server = PressArk_Execution_Ledger::sanitize( [
	'source_message' => 'test',
	'goal_hash'      => md5( 'test' ),
	'request_counts' => [],
	'tasks'          => [
		[ 'key' => 'a', 'label' => 'A', 'status' => 'pending', 'evidence' => '', 'depends_on' => [], 'metadata' => [ 'v' => '1' ] ],
	],
	'receipts'       => [],
	'current_target' => [],
	'updated_at'     => '',
] );
$client = PressArk_Execution_Ledger::sanitize( [
	'source_message' => 'test',
	'goal_hash'      => md5( 'test' ),
	'request_counts' => [],
	'tasks'          => [
		[ 'key' => 'a', 'label' => 'A', 'status' => 'completed', 'evidence' => 'Done!', 'depends_on' => [ 'x' ], 'metadata' => [ 'v' => '2', 'extra' => 'y' ] ],
		[ 'key' => 'b', 'label' => 'B', 'status' => 'pending', 'evidence' => '', 'depends_on' => [ 'a' ] ],
	],
	'receipts'       => [],
	'current_target' => [],
	'updated_at'     => '',
] );
$merged = PressArk_Execution_Ledger::merge( $server, $client );
assert_eq( 'merged task count', 2, count( $merged['tasks'] ) );
assert_eq( 'task a completed from client', 'completed', $merged['tasks'][0]['status'] );
assert_eq( 'task a evidence from client', 'Done!', $merged['tasks'][0]['evidence'] );
assert_true( 'task a deps merged (union)', in_array( 'x', $merged['tasks'][0]['depends_on'] ) );
assert_eq( 'task a metadata merged (client wins)', '2', $merged['tasks'][0]['metadata']['v'] );
assert_eq( 'task a extra metadata from client', 'y', $merged['tasks'][0]['metadata']['extra'] );

// ── Summary ─────────────────────────────────────────────────────────
echo "\n" . str_repeat( '=', 50 ) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo str_repeat( '=', 50 ) . "\n\n";

exit( $failed > 0 ? 1 : 0 );
