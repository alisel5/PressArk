<?php
/**
 * Targeted verification for workflow removal compatibility.
 *
 * Run: php pressark/tests/test-workflow-removal-compat.php
 *
 * This is a standalone test. It verifies that former workflow-shaped
 * requests now route through the agent, approval-boundary pause snapshots
 * round-trip through checkpoint resume, legacy workflow-era metadata still
 * hydrates cleanly, and duplicate non-idempotent writes stay suppressed.
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
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}
if ( ! function_exists( 'wp_list_pluck' ) ) {
	function wp_list_pluck( $list, $field ) {
		$values = array();
		foreach ( (array) $list as $item ) {
			if ( is_array( $item ) && array_key_exists( $field, $item ) ) {
				$values[] = $item[ $field ];
			}
		}
		return $values;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		return (object) array(
			'ID'         => absint( $post_id ),
			'post_title' => 'Stub Post ' . absint( $post_id ),
			'post_type'  => 'page',
		);
	}
}

class PressArk_Task_Queue {
	public const ASYNC_THRESHOLD = 10;

	public static int $score = 0;

	public function async_score( string $message ): int {
		unset( $message );
		return self::$score;
	}
}

class PressArk_AI_Connector {
	private bool $native_tools;

	public function __construct( bool $native_tools = true ) {
		$this->native_tools = $native_tools;
	}

	public function supports_native_tools( bool $deep_mode = false ): bool {
		unset( $deep_mode );
		return $this->native_tools;
	}
}

class PressArk_Action_Engine {}

class PressArk_Agent {
	public static bool $lightweight = false;

	public static function is_lightweight_chat_request( string $message, array $conversation ): bool {
		unset( $message, $conversation );
		return self::$lightweight;
	}
}

require_once __DIR__ . '/../includes/class-pressark-execution-ledger.php';
require_once __DIR__ . '/../includes/class-pressark-checkpoint.php';
require_once __DIR__ . '/../includes/class-pressark-run-store.php';
require_once __DIR__ . '/../includes/class-pressark-router.php';

$passed = 0;
$failed = 0;

function assert_true_wrc( string $label, bool $condition ): void {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "  PASS: {$label}\n";
	} else {
		$failed++;
		echo "  FAIL: {$label}\n";
	}
}

function assert_eq_wrc( string $label, $expected, $actual ): void {
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

echo "=== Workflow Removal Compatibility Tests ===\n\n";

echo "--- Former workflow-shaped requests route through agent ---\n";
$connector = new PressArk_AI_Connector( true );
$engine    = new PressArk_Action_Engine();
$messages  = array(
	'content_create' => 'Create a landing page for our winter sale.',
	'content_edit'   => 'Edit the product page hero copy and CTA.',
	'seo_fix'        => 'Fix the SEO title and meta description for the homepage.',
	'woo_ops'        => 'Update WooCommerce prices for the spring collection.',
);

foreach ( $messages as $label => $message ) {
	$routing = PressArk_Router::resolve( $message, array(), $connector, $engine, 'pro', true, '', 0 );
	assert_eq_wrc( "{$label} routes to agent", PressArk_Router::ROUTE_AGENT, $routing['route'] ?? '' );
	assert_true_wrc(
		"{$label} has no workflow handler",
		array_key_exists( 'handler', $routing ) && null === $routing['handler']
	);
	assert_eq_wrc( "{$label} uses native-tools route reason", 'native_tools', $routing['meta']['route_reason'] ?? '' );
}

echo "\n--- Approval-boundary snapshots resume through checkpoints ---\n";
$checkpoint = PressArk_Checkpoint::from_array( array() );
$checkpoint->sync_execution_goal( 'Create one landing page for our winter sale.' );
$checkpoint->set_selected_target( array(
	'id'    => 42,
	'title' => 'Winter Sale Landing Page',
	'type'  => 'page',
) );
$checkpoint->record_execution_write(
	'create_post',
	array(
		'title'  => 'Winter Sale Landing Page',
		'status' => 'draft',
	),
	array(
		'success'     => true,
		'post_id'     => 42,
		'post_title'  => 'Winter Sale Landing Page',
		'post_status' => 'draft',
		'url'         => 'https://example.com/winter-sale',
	)
);

$pause_state = PressArk_Run_Store::build_pause_state(
	array(
		'type'          => 'confirm_card',
		'checkpoint'    => $checkpoint->to_array(),
		'loaded_groups' => array( 'content', 'seo' ),
	),
	'preview'
);

assert_true_wrc( 'Pause snapshot is created from checkpoint payload', is_array( $pause_state ) && ! empty( $pause_state ) );
assert_eq_wrc( 'Pause snapshot stamps preview stage', 'preview', $pause_state['workflow_stage'] ?? '' );
assert_eq_wrc( 'Pause snapshot carries loaded tool groups', array( 'content', 'seo' ), $pause_state['loaded_tool_groups'] ?? array() );

$resumed = PressArk_Checkpoint::from_array( array() );
$resumed->absorb_run_snapshot( $pause_state );

assert_eq_wrc( 'Resumed checkpoint keeps selected target id', 42, (int) ( $resumed->get_selected_target()['id'] ?? 0 ) );
assert_eq_wrc( 'Resumed checkpoint keeps preview stage', 'preview', $resumed->get_workflow_stage() );
assert_eq_wrc( 'Resumed checkpoint keeps loaded tool groups', array( 'content', 'seo' ), $resumed->get_loaded_tool_groups() );
assert_true_wrc(
	'Duplicate create_post is still suppressed after snapshot resume',
	PressArk_Execution_Ledger::should_skip_duplicate(
		$resumed->get_execution(),
		'create_post',
		array( 'title' => 'Winter Sale Landing Page' )
	)
);

echo "\n--- Legacy workflow-era metadata still hydrates on resume ---\n";
$legacy_checkpoint = PressArk_Checkpoint::from_array( array() );
$legacy_checkpoint->absorb_run_snapshot( array(
	'workflow_stage'      => 'preview',
	'target'              => array(
		'post_id' => 77,
		'title'   => 'SEO Refresh Landing Page',
		'type'    => 'page',
	),
	'loaded_tool_groups'  => array( 'seo', 'content' ),
	'approvals'           => array(
		array(
			'action'      => 'preview_requested',
			'approved_at' => '2026-04-04T00:00:00Z',
		),
	),
	'blockers'            => array( 'Awaiting approval' ),
	'retrieval_bundle_ids'=> array( 'bundle_alpha' ),
) );

assert_eq_wrc( 'Legacy snapshot keeps workflow stage', 'preview', $legacy_checkpoint->get_workflow_stage() );
assert_eq_wrc( 'Legacy snapshot restores selected target id', 77, (int) ( $legacy_checkpoint->get_selected_target()['id'] ?? 0 ) );
assert_eq_wrc( 'Legacy snapshot restores loaded tool groups', array( 'seo', 'content' ), $legacy_checkpoint->get_loaded_tool_groups() );
assert_true_wrc(
	'Legacy snapshot restores approvals',
	'preview_requested' === ( $legacy_checkpoint->get_approvals()[0]['action'] ?? '' )
);
assert_true_wrc( 'Legacy snapshot restores blockers', in_array( 'Awaiting approval', $legacy_checkpoint->get_blockers(), true ) );
assert_true_wrc( 'Legacy snapshot restores retrieval bundles', in_array( 'bundle_alpha', $legacy_checkpoint->get_bundle_ids(), true ) );

echo "\nResults: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
