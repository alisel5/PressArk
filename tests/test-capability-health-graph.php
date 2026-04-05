<?php
/**
 * Targeted verification for the harness capability-health graph.
 *
 * Run: C:\xampp\php\php.exe pressark/tests/test-capability-health-graph.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
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

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
}

class PressArk_Resource_Registry {
	public static array $group_health = array();

	public static function get_group_health(): array {
		return self::$group_health;
	}
}

class PressArk_SEO_Resolver {
	public static ?string $plugin = null;

	public static function detect(): ?string {
		return self::$plugin;
	}

	public static function label( ?string $plugin = null ): string {
		$plugin = $plugin ?? self::$plugin;

		return match ( $plugin ) {
			'yoast'    => 'Yoast SEO',
			'rankmath' => 'Rank Math',
			default    => 'SEO plugin',
		};
	}
}

require_once dirname( __DIR__ ) . '/includes/class-pressark-capability-health.php';

$passed = 0;
$failed = 0;

function assert_capability( string $label, $expected, $actual ): void {
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

function assert_capability_true( string $label, bool $condition ): void {
	assert_capability( $label, true, $condition );
}

echo "=== Capability Health Graph Tests ===\n\n";

PressArk_SEO_Resolver::$plugin = 'yoast';
PressArk_Resource_Registry::$group_health = array(
	'site' => array(
		'label'          => 'Site',
		'state'          => 'healthy',
		'status'         => 'available',
		'summary'        => 'Resource provider is available.',
		'available'      => true,
		'visible'        => true,
		'hidden'         => false,
		'visible_count'  => 2,
		'hidden_count'   => 0,
		'requires'       => array(),
		'hidden_reasons' => array(),
	),
	'woocommerce' => array(
		'label'          => 'WooCommerce',
		'state'          => 'absent',
		'status'         => 'hidden',
		'summary'        => 'WooCommerce resources are hidden until WooCommerce is active.',
		'available'      => false,
		'visible'        => false,
		'hidden'         => true,
		'visible_count'  => 0,
		'hidden_count'   => 1,
		'requires'       => array( 'woocommerce' ),
		'hidden_reasons' => array( 'WooCommerce resources are hidden until WooCommerce is active.' ),
	),
);

$readiness = array(
	'facets'      => array(
		'billing'       => array(
			'mode'       => 'bundled',
			'state'      => 'degraded',
			'service_state' => 'offline_assisted',
			'handshake_state' => 'verified',
			'at_limit'   => false,
		),
		'provider'      => array(
			'mode'     => 'direct',
			'state'    => 'blocked',
			'summary'  => 'Direct provider transport is blocked by local provider or credential configuration.',
			'provider' => 'openai',
			'model'    => 'gpt-test',
		),
		'site_profile'  => array(
			'state'         => 'degraded',
			'exists'        => true,
			'needs_refresh' => true,
			'generated_at'  => '2026-04-01 10:00:00',
		),
		'content_index' => array(
			'state'        => 'degraded',
			'enabled'      => false,
			'total_chunks' => 0,
			'stale_percent' => 0.0,
		),
	),
	'tool_groups' => array(
		'woocommerce' => array(
			'state'             => 'blocked',
			'summary'           => 'WooCommerce-powered tools are unavailable until WooCommerce is active.',
			'available'         => false,
			'relevant'          => false,
			'requires'          => array( 'woocommerce' ),
			'dependency_issues' => array( 'WooCommerce-powered tools are unavailable until WooCommerce is active.' ),
			'tool_count'        => 4,
		),
		'seo' => array(
			'state'             => 'degraded',
			'summary'           => 'Site profile is present but stale, so brand/context guidance may lag behind the site.',
			'available'         => true,
			'relevant'          => true,
			'requires'          => array(),
			'dependency_issues' => array( 'Site profile is present but stale, so brand/context guidance may lag behind the site.' ),
			'tool_count'        => 3,
		),
	),
);

$graph = PressArk_Capability_Health::get_snapshot( $readiness );

assert_capability( 'Overall graph state reflects auth-blocked provider transport', 'auth_blocked', $graph['state'] ?? '' );
assert_capability( 'Bank state is degraded when service is offline-assisted', 'degraded', $graph['nodes']['bank']['state'] ?? '' );
assert_capability( 'Bank status preserves offline-assisted detail', 'offline_assisted', $graph['nodes']['bank']['status'] ?? '' );
assert_capability( 'Provider transport maps blocked readiness to auth_blocked', 'auth_blocked', $graph['nodes']['provider_transport']['state'] ?? '' );
assert_capability( 'Site profile stale status is exposed', 'stale', $graph['nodes']['site_profile']['status'] ?? '' );
assert_capability( 'Content index unavailable status is exposed', 'unavailable', $graph['nodes']['content_index']['status'] ?? '' );
assert_capability( 'SEO integration provider is surfaced', 'yoast', $graph['nodes']['seo_integrations']['provider'] ?? '' );
assert_capability( 'WooCommerce tool group becomes hidden/absent', 'absent', $graph['tool_groups']['woocommerce']['state'] ?? '' );
assert_capability_true( 'WooCommerce tool group is flagged hidden', ! empty( $graph['tool_groups']['woocommerce']['hidden'] ) );
assert_capability( 'WooCommerce resource group becomes hidden/absent', 'hidden', $graph['resource_groups']['woocommerce']['status'] ?? '' );
assert_capability_true(
	'Hidden capability notices are emitted',
	false !== strpos( wp_json_encode( $graph['notices'] ?? array() ), 'Hidden capability surfaces' )
);

$byok_graph = PressArk_Capability_Health::get_snapshot(
	array(
		'facets'      => array(
			'billing'       => array(
				'mode'            => 'byok',
				'state'           => 'ready',
				'service_state'   => 'local',
				'handshake_state' => 'byok',
			),
			'provider'      => array(
				'mode'     => 'byok',
				'state'    => 'ready',
				'provider' => 'openai',
				'model'    => 'gpt-test',
			),
			'site_profile'  => array(
				'state'         => 'ready',
				'exists'        => true,
				'needs_refresh' => false,
			),
			'content_index' => array(
				'state'        => 'ready',
				'enabled'      => true,
				'total_chunks' => 42,
				'stale_percent' => 0.0,
			),
		),
		'tool_groups' => array(),
	)
);

assert_capability( 'BYOK bank node is marked bypassed', 'bypassed', $byok_graph['nodes']['bank']['status'] ?? '' );
assert_capability( 'BYOK graph can remain healthy', 'healthy', $byok_graph['state'] ?? '' );

echo "\nResults: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
