<?php
/**
 * Targeted verification for Site Playbook sanitization and selective recall.
 *
 * Run: php pressark/tests/test-site-playbook.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}

$pressark_test_options = array();
$pressark_uuid_counter = 0;

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
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		global $pressark_test_options;
		return array_key_exists( $key, $pressark_test_options ) ? $pressark_test_options[ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		global $pressark_test_options;
		$pressark_test_options[ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		global $pressark_uuid_counter;
		$pressark_uuid_counter++;
		return sprintf( '00000000-0000-4000-8000-%012d', $pressark_uuid_counter );
	}
}

if ( ! class_exists( 'PressArk_Operation_Registry' ) ) {
	class PressArk_Operation_Registry {
		public static function group_names(): array {
			return array( 'core', 'generation', 'woocommerce', 'settings', 'seo' );
		}

		public static function is_valid_group( string $group ): bool {
			return in_array( $group, self::group_names(), true );
		}
	}
}

require_once __DIR__ . '/../includes/class-pressark-site-playbook.php';

$passed = 0;
$failed = 0;

function assert_true_sp( string $label, bool $condition ): void {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "  PASS: {$label}\n";
	} else {
		$failed++;
		echo "  FAIL: {$label}\n";
	}
}

function assert_eq_sp( string $label, $expected, $actual ): void {
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

echo "=== Site Playbook Tests ===\n\n";

$sanitized = PressArk_Site_Playbook::sanitize_option(
	array(
		'_sentinel' => 1,
		array(
			'title'       => 'Brand guardrails',
			'body'        => 'Never describe the offer as cheap. Use premium or entry plan language.',
			'task_types'  => array( 'generate', 'edit' ),
			'tool_groups' => array( 'generation', 'core' ),
		),
		array(
			'title'       => '',
			'body'        => '',
			'task_types'  => array( 'all' ),
			'tool_groups' => array( 'all' ),
		),
	)
);

assert_eq_sp( 'Sanitizer keeps only non-empty entries', 1, count( $sanitized ) );
assert_eq_sp( 'Sanitizer preserves selected task scopes', array( 'generate', 'edit' ), $sanitized[0]['task_types'] ?? array() );
assert_true_sp( 'Sanitizer generates a stable entry id', '' !== (string) ( $sanitized[0]['id'] ?? '' ) );

$pressark_test_options[ PressArk_Site_Playbook::OPTION_KEY ] = array(
	array(
		'id'          => 'playbook_brand',
		'title'       => 'Brand guardrails',
		'body'        => 'Never describe the offer as cheap. Use premium or entry plan language.',
		'task_types'  => array( 'generate', 'edit' ),
		'tool_groups' => array( 'generation', 'core' ),
		'updated_at'  => '2026-04-05 09:00:00',
	),
	array(
		'id'          => 'playbook_checkout',
		'title'       => 'Checkout approvals',
		'body'        => 'Do not modify checkout or pricing flows without explicit approval.',
		'task_types'  => array( 'edit' ),
		'tool_groups' => array( 'woocommerce', 'settings' ),
		'updated_at'  => '2026-04-05 08:00:00',
	),
	array(
		'id'          => 'playbook_source',
		'title'       => 'Truth source',
		'body'        => 'Use /pricing and /plans as canonical truth sources for current plan naming.',
		'task_types'  => array( 'all' ),
		'tool_groups' => array( 'all' ),
		'updated_at'  => '2026-04-05 07:00:00',
	),
);

$context = PressArk_Site_Playbook::resolve_prompt_context(
	'generate',
	array( 'generation', 'core' ),
	'Draft homepage copy for the entry plan pricing section.'
);

$titles = $context['titles'] ?? array();
$text   = $context['text'] ?? '';

assert_true_sp( 'Prompt context emits a Site Playbook block', false !== strpos( $text, '## Site Playbook' ) );
assert_true_sp( 'Task and group-matched entry is selected', in_array( 'Brand guardrails', $titles, true ) );
assert_true_sp( 'Universal entry is still selected when relevant budget allows', in_array( 'Truth source', $titles, true ) );
assert_true_sp( 'Irrelevant scoped entry is excluded', ! in_array( 'Checkout approvals', $titles, true ) );
assert_true_sp( 'Prompt text includes the brand instruction body', false !== strpos( $text, 'Never describe the offer as cheap.' ) );
assert_true_sp( 'Preview is generated for inspector surfaces', '' !== (string) ( $context['preview'] ?? '' ) );

echo "\nResults: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
