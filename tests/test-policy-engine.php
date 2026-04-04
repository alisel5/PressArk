<?php
/**
 * PressArk Policy Engine — Verification tests.
 *
 * Run with: php pressark/tests/test-policy-engine.php
 * (Requires WordPress to be bootstrapped, or mock the dependencies.)
 *
 * This file exercises:
 *   1. DENY verdict — custom rule blocks an operation
 *   2. ASK verdict — custom rule forces confirmation
 *   3. ALLOW verdict — custom rule explicitly allows
 *   4. Default fallback — no custom rules, reads allowed, unregistered denied
 *   5. Deny-first — deny wins over allow at same priority
 *   6. Ask over allow — ask wins when both match
 *   7. Wildcard matching — operation name prefix matching
 *   8. Callable rules — dynamic context-based rules
 *   9. Context filtering — rules limited to specific contexts
 *  10. Global pre-operation hook blocks execution
 *
 * @package PressArk
 * @since   5.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Minimal bootstrap for standalone testing.
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

// Load the classes under test.
require_once dirname( __DIR__ ) . '/includes/class-pressark-policy-engine.php';

// ── Minimal stubs for dependencies ──────────────────────────────────

if ( ! class_exists( 'PressArk_Operation' ) ) {
	class PressArk_Operation {
		public string $capability = 'read';
		public string $group      = 'core';
		public string $risk       = 'safe';
	}
}

if ( ! class_exists( 'PressArk_Operation_Registry' ) ) {
	class PressArk_Operation_Registry {
		private static array $ops = array(
			'read_content'      => array( 'capability' => 'read', 'group' => 'core', 'risk' => 'safe' ),
			'edit_content'      => array( 'capability' => 'confirm', 'group' => 'core', 'risk' => 'moderate' ),
			'delete_content'    => array( 'capability' => 'confirm', 'group' => 'core', 'risk' => 'destructive' ),
			'create_post'       => array( 'capability' => 'confirm', 'group' => 'core', 'risk' => 'moderate' ),
			'fix_seo'           => array( 'capability' => 'confirm', 'group' => 'seo', 'risk' => 'moderate' ),
			'edit_product'      => array( 'capability' => 'confirm', 'group' => 'woocommerce', 'risk' => 'moderate' ),
			'edit_product_meta' => array( 'capability' => 'confirm', 'group' => 'woocommerce', 'risk' => 'moderate' ),
		);

		public static function resolve( string $name ): ?PressArk_Operation {
			if ( ! isset( self::$ops[ $name ] ) ) {
				return null;
			}
			$op = new PressArk_Operation();
			$op->capability = self::$ops[ $name ]['capability'];
			$op->group      = self::$ops[ $name ]['group'];
			$op->risk       = self::$ops[ $name ]['risk'];
			return $op;
		}

		public static function exists( string $name ): bool {
			return isset( self::$ops[ $name ] );
		}

		public static function classify( string $name, array $args = array() ): string {
			return self::$ops[ $name ]['capability'] ?? 'unknown';
		}

		public static function get_group( string $name ): string {
			return self::$ops[ $name ]['group'] ?? '';
		}
	}
}

if ( ! class_exists( 'PressArk_Automation_Policy' ) ) {
	class PressArk_Automation_Policy {
		public static function check( string $op, string $policy, array $args = array() ): array {
			if ( 'editorial' === $policy && 'edit_content' === $op ) {
				return array( 'allowed' => true );
			}
			return array( 'allowed' => false, 'reason' => "Blocked by {$policy} policy." );
		}
	}
}

// Stub WordPress functions.
if ( ! function_exists( 'apply_filters' ) ) {
	$_test_filters = array();
	function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ): void {
		global $_test_filters;
		$_test_filters[ $tag ][] = array( 'callback' => $callback, 'args' => $args );
	}
	function apply_filters( string $tag, ...$args ) {
		global $_test_filters;
		$value = $args[0] ?? null;
		if ( ! empty( $_test_filters[ $tag ] ) ) {
			foreach ( $_test_filters[ $tag ] as $filter ) {
				$pass_args = array_slice( $args, 0, $filter['args'] );
				$value = call_user_func_array( $filter['callback'], $pass_args );
				$args[0] = $value;
			}
		}
		return $value;
	}
	function do_action( string $tag, ...$args ): void {
		global $_test_filters;
		if ( ! empty( $_test_filters[ $tag ] ) ) {
			foreach ( $_test_filters[ $tag ] as $filter ) {
				call_user_func_array( $filter['callback'], array_slice( $args, 0, $filter['args'] ) );
			}
		}
	}
	function remove_all_filters( string $tag ): void {
		global $_test_filters;
		unset( $_test_filters[ $tag ] );
	}
}

// ── Test runner ─────────────────────────────────────────────────────

$passed = 0;
$failed = 0;

function assert_test( string $name, bool $condition, string $detail = '' ): void {
	global $passed, $failed;
	if ( $condition ) {
		echo "  PASS: {$name}\n";
		$passed++;
	} else {
		echo "  FAIL: {$name}" . ( $detail ? " — {$detail}" : '' ) . "\n";
		$failed++;
	}
}

// ── Tests ───────────────────────────────────────────────────────────

echo "=== PressArk Policy Engine Tests ===\n\n";

// Reset state before each test.
function reset_test_state(): void {
	PressArk_Policy_Engine::flush_rules();
	remove_all_filters( 'pressark_policy_rules' );
	remove_all_filters( 'pressark_pre_operation' );
	remove_all_filters( 'pressark_post_operation' );
	remove_all_filters( 'pressark_policy_verdict' );
	remove_all_filters( 'pressark_operation_denied' );
}

// ── Test 1: DENY verdict from custom rule ───────────────────────────
reset_test_state();
add_filter( 'pressark_policy_rules', function ( $rules ) {
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::DENY,
		'match'    => PressArk_Policy_Engine::MATCH_OPERATION,
		'value'    => 'edit_content',
		'source'   => 'test',
		'reason'   => 'Editing blocked for testing.',
	);
	return $rules;
}, 10, 1 );

$verdict = PressArk_Policy_Engine::evaluate( 'edit_content', array( 'post_id' => 1 ) );
assert_test( '1. DENY — custom rule blocks operation', PressArk_Policy_Engine::is_denied( $verdict ) );
assert_test( '1. DENY — reason is preserved', str_contains( $verdict['reasons'][0], 'blocked for testing' ) );

// ── Test 2: ASK verdict from custom rule ────────────────────────────
reset_test_state();
add_filter( 'pressark_policy_rules', function ( $rules ) {
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::ASK,
		'match'    => PressArk_Policy_Engine::MATCH_GROUP,
		'value'    => 'seo',
		'source'   => 'test',
		'reason'   => 'SEO changes need human review.',
	);
	return $rules;
}, 10, 1 );

$verdict = PressArk_Policy_Engine::evaluate( 'fix_seo' );
assert_test( '2. ASK — custom rule forces confirmation', PressArk_Policy_Engine::is_ask( $verdict ) );

// ── Test 3: ALLOW verdict from custom rule ──────────────────────────
reset_test_state();
add_filter( 'pressark_policy_rules', function ( $rules ) {
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::ALLOW,
		'match'    => PressArk_Policy_Engine::MATCH_OPERATION,
		'value'    => 'create_post',
		'source'   => 'test',
		'reason'   => 'Post creation always allowed.',
	);
	return $rules;
}, 10, 1 );

$verdict = PressArk_Policy_Engine::evaluate( 'create_post' );
assert_test( '3. ALLOW — custom rule explicitly allows', PressArk_Policy_Engine::is_allowed( $verdict ) );

// ── Test 4: Default fallback — reads allowed ────────────────────────
reset_test_state();
$verdict = PressArk_Policy_Engine::evaluate( 'read_content' );
assert_test( '4a. Default — reads are allowed', PressArk_Policy_Engine::is_allowed( $verdict ) );

$verdict = PressArk_Policy_Engine::evaluate( 'totally_unknown_op' );
assert_test( '4b. Default — unregistered ops are denied', PressArk_Policy_Engine::is_denied( $verdict ) );

// ── Test 5: Deny-first — deny wins over allow ──────────────────────
reset_test_state();
add_filter( 'pressark_policy_rules', function ( $rules ) {
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::ALLOW,
		'match'    => PressArk_Policy_Engine::MATCH_OPERATION,
		'value'    => 'edit_content',
		'priority' => 100,
		'source'   => 'test',
	);
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::DENY,
		'match'    => PressArk_Policy_Engine::MATCH_OPERATION,
		'value'    => 'edit_content',
		'priority' => 100,
		'source'   => 'test',
		'reason'   => 'Deny always wins.',
	);
	return $rules;
}, 10, 1 );

$verdict = PressArk_Policy_Engine::evaluate( 'edit_content' );
assert_test( '5. Deny-first — deny wins over allow at same priority', PressArk_Policy_Engine::is_denied( $verdict ) );

// ── Test 6: Ask over allow ──────────────────────────────────────────
reset_test_state();
add_filter( 'pressark_policy_rules', function ( $rules ) {
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::ALLOW,
		'match'    => PressArk_Policy_Engine::MATCH_OPERATION,
		'value'    => 'fix_seo',
		'priority' => 100,
		'source'   => 'test',
	);
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::ASK,
		'match'    => PressArk_Policy_Engine::MATCH_OPERATION,
		'value'    => 'fix_seo',
		'priority' => 100,
		'source'   => 'test',
	);
	return $rules;
}, 10, 1 );

$verdict = PressArk_Policy_Engine::evaluate( 'fix_seo' );
assert_test( '6. Ask-over-allow — ask wins when both match', PressArk_Policy_Engine::is_ask( $verdict ) );

// ── Test 7: Wildcard matching ───────────────────────────────────────
reset_test_state();
add_filter( 'pressark_policy_rules', function ( $rules ) {
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::DENY,
		'match'    => PressArk_Policy_Engine::MATCH_OPERATION,
		'value'    => 'edit_product*',
		'source'   => 'test',
		'reason'   => 'Product ops locked.',
	);
	return $rules;
}, 10, 1 );

$verdict1 = PressArk_Policy_Engine::evaluate( 'edit_product' );
$verdict2 = PressArk_Policy_Engine::evaluate( 'edit_product_meta' );
$verdict3 = PressArk_Policy_Engine::evaluate( 'create_post' );
assert_test( '7a. Wildcard — matches exact prefix', PressArk_Policy_Engine::is_denied( $verdict1 ) );
assert_test( '7b. Wildcard — matches extended name', PressArk_Policy_Engine::is_denied( $verdict2 ) );
assert_test( '7c. Wildcard — does not match unrelated', ! PressArk_Policy_Engine::is_denied( $verdict3 ) );

// ── Test 8: Callable rules ──────────────────────────────────────────
reset_test_state();
add_filter( 'pressark_policy_rules', function ( $rules ) {
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::DENY,
		'match'    => PressArk_Policy_Engine::MATCH_CALLABLE,
		'value'    => function ( $ctx ) {
			// Only deny if post_id is 42.
			return ( $ctx['params']['post_id'] ?? 0 ) === 42;
		},
		'source'   => 'test',
		'reason'   => 'Post 42 is protected.',
	);
	return $rules;
}, 10, 1 );

$verdict_blocked = PressArk_Policy_Engine::evaluate( 'edit_content', array( 'post_id' => 42 ) );
$verdict_allowed = PressArk_Policy_Engine::evaluate( 'edit_content', array( 'post_id' => 99 ) );
assert_test( '8a. Callable — denies when condition met', PressArk_Policy_Engine::is_denied( $verdict_blocked ) );
assert_test( '8b. Callable — passes when condition not met', ! PressArk_Policy_Engine::is_denied( $verdict_allowed ) );

// ── Test 9: Context filtering ───────────────────────────────────────
reset_test_state();
add_filter( 'pressark_policy_rules', function ( $rules ) {
	$rules[] = array(
		'behavior' => PressArk_Policy_Engine::DENY,
		'match'    => PressArk_Policy_Engine::MATCH_OPERATION,
		'value'    => 'edit_content',
		'source'   => 'test',
		'reason'   => 'Blocked only in automation.',
		'contexts' => array( PressArk_Policy_Engine::CONTEXT_AUTOMATION ),
	);
	return $rules;
}, 10, 1 );

$v_auto = PressArk_Policy_Engine::evaluate( 'edit_content', array(), PressArk_Policy_Engine::CONTEXT_AUTOMATION );
$v_interactive = PressArk_Policy_Engine::evaluate( 'edit_content', array(), PressArk_Policy_Engine::CONTEXT_INTERACTIVE );
assert_test( '9a. Context — rule applies in matching context', PressArk_Policy_Engine::is_denied( $v_auto ) );
assert_test( '9b. Context — rule skipped in other context', ! PressArk_Policy_Engine::is_denied( $v_interactive ) );

// ── Test 10: Pre-operation hook blocks execution ────────────────────
reset_test_state();
add_filter( 'pressark_pre_operation', function ( $params, $op_name ) {
	if ( ( $params['post_id'] ?? 0 ) === 1 ) {
		return null; // Block.
	}
	return $params;
}, 10, 2 );

$result_blocked = PressArk_Policy_Engine::pre_operation( 'edit_content', array( 'post_id' => 1 ) );
$result_ok      = PressArk_Policy_Engine::pre_operation( 'edit_content', array( 'post_id' => 5 ) );
assert_test( '10a. Pre-operation hook — blocks when null returned', ! $result_blocked['proceed'] );
assert_test( '10b. Pre-operation hook — passes when params returned', $result_ok['proceed'] );

// ── Test 11: Default destructive → ask ──────────────────────────────
reset_test_state();
$verdict = PressArk_Policy_Engine::evaluate( 'delete_content' );
assert_test( '11. Default — destructive operations require confirmation', PressArk_Policy_Engine::is_ask( $verdict ) );

// ── Test 12: Automation fallback to Automation_Policy ────────────────
reset_test_state();
$v_allowed = PressArk_Policy_Engine::evaluate(
	'edit_content', array(), PressArk_Policy_Engine::CONTEXT_AUTOMATION,
	array( 'policy' => 'editorial' )
);
$v_blocked = PressArk_Policy_Engine::evaluate(
	'edit_product', array(), PressArk_Policy_Engine::CONTEXT_AUTOMATION,
	array( 'policy' => 'editorial' )
);
assert_test( '12a. Automation fallback — editorial allows edit_content', PressArk_Policy_Engine::is_allowed( $v_allowed ) );
assert_test( '12b. Automation fallback — editorial denies edit_product', PressArk_Policy_Engine::is_denied( $v_blocked ) );

// ── Summary ─────────────────────────────────────────────────────────
echo "\n=== Results: {$passed} passed, {$failed} failed ===\n";
exit( $failed > 0 ? 1 : 0 );
