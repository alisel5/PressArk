<?php
/**
 * Standalone verification for cross-run operational search.
 *
 * Run: C:\xampp\php\php.exe pressark/tests/test-operational-search.php
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
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return (string) $text;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

require_once __DIR__ . '/../includes/class-pressark-operational-search.php';

class PressArk_Test_Operational_Search extends PressArk_Operational_Search {
	public array $runs  = array();
	public array $tasks = array();
	public array $events = array();
	public array $notes = array();

	protected function load_runs( int $user_id, int $limit ): array {
		unset( $limit );
		return array_values(
			array_filter(
				$this->runs,
				static function ( array $run ) use ( $user_id ): bool {
					return 0 === $user_id || (int) ( $run['user_id'] ?? 0 ) === $user_id;
				}
			)
		);
	}

	protected function load_run( string $run_id ): ?array {
		foreach ( $this->runs as $run ) {
			if ( $run_id === (string) ( $run['run_id'] ?? '' ) ) {
				return $run;
			}
		}

		return null;
	}

	protected function load_tasks( int $user_id, int $limit ): array {
		unset( $limit );
		return array_values(
			array_filter(
				$this->tasks,
				static function ( array $task ) use ( $user_id ): bool {
					return 0 === $user_id || (int) ( $task['user_id'] ?? 0 ) === $user_id;
				}
			)
		);
	}

	protected function load_events( int $user_id, int $limit ): array {
		unset( $limit );
		return array_values(
			array_filter(
				$this->events,
				static function ( array $event ) use ( $user_id ): bool {
					return 0 === $user_id || (int) ( $event['user_id'] ?? 0 ) === $user_id;
				}
			)
		);
	}

	protected function load_site_notes(): array {
		return $this->notes;
	}
}

$passed = 0;
$failed = 0;

function assert_same_operational( string $label, $expected, $actual ): void {
	global $passed, $failed;
	if ( $expected === $actual ) {
		$passed++;
		echo "  PASS: {$label}\n";
		return;
	}

	$failed++;
	echo "  FAIL: {$label}\n";
	echo '    Expected: ' . var_export( $expected, true ) . "\n";
	echo '    Actual:   ' . var_export( $actual, true ) . "\n";
}

function assert_true_operational( string $label, bool $condition ): void {
	assert_same_operational( $label, true, $condition );
}

function result_keys( array $results ): array {
	return array_values(
		array_map(
			static function ( array $row ): string {
				return (string) ( $row['key'] ?? '' );
			},
			$results
		)
	);
}

function find_result( array $results, string $key ): ?array {
	foreach ( $results as $row ) {
		if ( $key === (string) ( $row['key'] ?? '' ) ) {
			return $row;
		}
	}

	return null;
}

$search = new PressArk_Test_Operational_Search();
$search->runs = array(
	array(
		'run_id'          => 'run_target_1',
		'user_id'         => 7,
		'task_id'         => 'task_target_1',
		'route'           => 'agent',
		'status'          => 'settled',
		'message'         => 'Update the Summer Launch landing page',
		'error_summary'   => '',
		'correlation_id'  => 'corr_target_1',
		'reservation_id'  => 'res_target_1',
		'workflow_state'  => array(
			'selected_target' => array(
				'type'  => 'page',
				'id'    => 42,
				'title' => 'Summer Launch Landing Page',
			),
			'approvals'       => array(
				array( 'action' => 'publish_changes' ),
			),
			'plan_state'      => array(
				'approved_at' => '2026-04-01 10:01:00',
				'plan_text'   => 'Approve final landing page copy and publish changes.',
			),
			'context_capsule' => array(
				'target'          => 'Summer Launch Landing Page',
				'active_request'  => 'Refresh hero copy',
				'recent_receipts' => array( 'Updated post #42' ),
			),
		),
		'result'          => array(
			'message'          => 'Landing page updated successfully.',
			'routing_decision' => array( 'fallback' => false ),
		),
		'pending_actions' => array(),
		'created_at'      => '2026-04-01 10:00:00',
		'settled_at'      => '2026-04-01 10:18:00',
	),
	array(
		'run_id'          => 'run_target_2',
		'user_id'         => 7,
		'task_id'         => 'task_target_2',
		'route'           => 'agent',
		'status'          => 'failed',
		'message'         => 'Repair the Summer Launch landing page pricing block',
		'error_summary'   => 'Pricing block rollback after validation failure',
		'correlation_id'  => 'corr_target_2',
		'reservation_id'  => 'res_target_2',
		'workflow_state'  => array(
			'selected_target' => array(
				'type'  => 'page',
				'id'    => 42,
				'title' => 'Summer Launch Landing Page',
			),
			'context_capsule' => array(
				'target'         => 'Summer Launch Landing Page',
				'active_request' => 'Repair pricing block',
			),
		),
		'result'          => array(
			'message'          => 'Rolled back pricing block changes.',
			'routing_decision' => array( 'fallback' => false ),
		),
		'pending_actions' => array(),
		'created_at'      => '2026-04-02 09:30:00',
		'settled_at'      => '2026-04-02 09:42:00',
	),
	array(
		'run_id'          => 'run_checkout_1',
		'user_id'         => 7,
		'task_id'         => 'task_checkout_1',
		'route'           => 'agent',
		'status'          => 'failed',
		'message'         => 'Fix checkout template fallback',
		'error_summary'   => 'Provider fallback kept the degraded checkout template',
		'correlation_id'  => 'corr_checkout_1',
		'reservation_id'  => 'res_checkout_1',
		'workflow_state'  => array(
			'selected_target' => array(
				'type'  => 'template',
				'id'    => 88,
				'title' => 'Checkout Template',
			),
		),
		'result'          => array(
			'message'          => 'Primary model timed out.',
			'routing_decision' => array( 'fallback' => true ),
		),
		'pending_actions' => array(),
		'created_at'      => '2026-04-03 15:00:00',
		'settled_at'      => '2026-04-03 15:07:00',
	),
	array(
		'run_id'          => 'run_other_user',
		'user_id'         => 22,
		'task_id'         => 'task_other_user',
		'route'           => 'agent',
		'status'          => 'settled',
		'message'         => 'Update the Summer Launch landing page for another user',
		'error_summary'   => '',
		'correlation_id'  => 'corr_other_user',
		'reservation_id'  => 'res_other_user',
		'workflow_state'  => array(
			'selected_target' => array(
				'type'  => 'page',
				'id'    => 42,
				'title' => 'Summer Launch Landing Page',
			),
		),
		'result'          => array(),
		'pending_actions' => array(),
		'created_at'      => '2026-04-04 11:00:00',
		'settled_at'      => '2026-04-04 11:10:00',
	),
);
$search->tasks = array(
	array(
		'task_id'         => 'task_target_1',
		'run_id'          => 'run_target_1',
		'parent_run_id'   => '',
		'root_run_id'     => 'run_target_1',
		'user_id'         => 7,
		'status'          => 'complete',
		'retries'         => 0,
		'max_retries'     => 3,
		'message'         => 'Update Summer Launch hero',
		'fail_reason'     => '',
		'payload'         => array(
			'_receipts' => array(
				'update_post' => array(
					'summary' => 'Updated post #42 Summer Launch landing page hero.',
					'ts'      => '2026-04-01 10:16:00',
				),
			),
		),
		'result'          => array( 'message' => 'Hero content saved.' ),
		'handoff_capsule' => array( 'target' => 'Summer Launch Landing Page' ),
		'created_at'      => '2026-04-01 10:02:00',
		'started_at'      => '2026-04-01 10:03:00',
		'completed_at'    => '2026-04-01 10:17:00',
	),
	array(
		'task_id'         => 'task_target_2',
		'run_id'          => 'run_target_2',
		'parent_run_id'   => '',
		'root_run_id'     => 'run_target_2',
		'user_id'         => 7,
		'status'          => 'failed',
		'retries'         => 1,
		'max_retries'     => 3,
		'message'         => 'Repair Summer Launch pricing block',
		'fail_reason'     => 'Pricing block rollback after validation failure',
		'payload'         => array(
			'_receipts' => array(
				'rollback_block' => array(
					'summary' => 'Rolled back pricing block on post #42.',
					'ts'      => '2026-04-02 09:40:00',
				),
			),
		),
		'result'          => array( 'message' => 'Rollback completed.' ),
		'handoff_capsule' => array( 'target' => 'Summer Launch Landing Page' ),
		'created_at'      => '2026-04-02 09:31:00',
		'started_at'      => '2026-04-02 09:32:00',
		'completed_at'    => '2026-04-02 09:41:00',
	),
	array(
		'task_id'         => 'task_checkout_1',
		'run_id'          => 'run_checkout_1',
		'parent_run_id'   => '',
		'root_run_id'     => 'run_checkout_1',
		'user_id'         => 7,
		'status'          => 'failed',
		'retries'         => 2,
		'max_retries'     => 3,
		'message'         => 'Repair checkout template',
		'fail_reason'     => 'Primary model timeout',
		'payload'         => array( '_receipts' => array() ),
		'result'          => array( 'message' => 'Escalated to fallback template.' ),
		'handoff_capsule' => array( 'target' => 'Checkout Template' ),
		'created_at'      => '2026-04-03 15:01:00',
		'started_at'      => '2026-04-03 15:02:00',
		'completed_at'    => '2026-04-03 15:07:00',
	),
);
$search->events = array(
	array(
		'event_id'    => 'evt_fallback_1',
		'run_id'      => 'run_checkout_1',
		'task_id'     => 'task_checkout_1',
		'event_type'  => 'provider.fallback',
		'reason'      => 'fallback_model_policy',
		'summary'     => 'Primary model unavailable, used fallback checkout template.',
		'payload'     => array(
			'query'    => 'checkout template',
			'provider' => 'fallback',
		),
		'created_at' => '2026-04-03 15:03:00',
		'user_id'    => 7,
	),
);
$search->notes = array(
	array(
		'category'   => 'templates',
		'note'       => 'Checkout template falls back to the legacy widget when stock badges render slowly.',
		'created_at' => '2026-03-30 08:00:00',
	),
);

echo "=== Operational Search Tests ===\n\n";

echo "--- Search finds prior work on the same object and respects user scoping ---\n";
$page_search = $search->search(
	array(
		'query'   => 'Summer Launch landing page',
		'user_id' => 7,
		'limit'   => 24,
	)
);
$page_keys = result_keys( $page_search['results'] );
assert_true_operational( 'run hit found for the primary page', in_array( 'run:run_target_1', $page_keys, true ) );
assert_true_operational( 'task hit found for the primary page', in_array( 'task:task_target_1', $page_keys, true ) );
assert_true_operational( 'receipt hit found for the same page', in_array( 'receipt:task_target_1:update_post', $page_keys, true ) );
assert_true_operational( 'other user run excluded by user scope', ! in_array( 'run:run_other_user', $page_keys, true ) );

echo "\n--- Search finds approved decisions ---\n";
$decision_search = $search->search(
	array(
		'query'   => 'publish changes',
		'user_id' => 7,
		'limit'   => 12,
	)
);
assert_true_operational( 'decision hit found', null !== find_result( $decision_search['results'], 'decision:run_target_1' ) );

echo "\n--- Search finds fallback traces and signals ---\n";
$fallback_search = $search->search(
	array(
		'query'   => 'fallback checkout template',
		'user_id' => 7,
		'limit'   => 12,
	)
);
$trace_hit = find_result( $fallback_search['results'], 'trace:evt_fallback_1' );
assert_true_operational( 'trace hit found', null !== $trace_hit );
assert_true_operational( 'trace hit carries fallback signal', is_array( $trace_hit ) && in_array( 'fallback', (array) ( $trace_hit['signals'] ?? array() ), true ) );

echo "\n--- Search finds site notes ---\n";
$site_note_search = $search->search(
	array(
		'query'   => 'legacy widget',
		'user_id' => 7,
		'limit'   => 12,
	)
);
assert_true_operational( 'site note hit found', null !== find_result( $site_note_search['results'], 'site_note:0' ) );

echo "\n--- Related history excludes the current task but finds nearby failures ---\n";
$related = $search->related_for_task( $search->tasks[0], 7 );
$related_keys = result_keys( $related['results'] );
assert_true_operational( 'current task excluded from related history', ! in_array( 'task:task_target_1', $related_keys, true ) );
assert_true_operational( 'nearby failed task on the same page included', in_array( 'task:task_target_2', $related_keys, true ) );

echo "\nResults: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
