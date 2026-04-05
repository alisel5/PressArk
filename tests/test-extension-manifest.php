<?php
/**
 * Targeted verification for PressArk extension manifests.
 *
 * Run: C:\xampp\php\php.exe pressark/tests/test-extension-manifest.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', __DIR__ . '/tmp-extension-plugins' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'PRESSARK_VERSION' ) ) {
	define( 'PRESSARK_VERSION', '5.4.0' );
}
if ( ! defined( 'WP_VERSION' ) ) {
	define( 'WP_VERSION', '6.8' );
}

$pressark_test_options    = array();
$pressark_test_transients = array();
$pressark_test_actions    = array();
$pressark_test_filters    = array();
$pressark_test_plugins    = array();

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
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = null ) {
		unset( $domain );
		return 1 === (int) $number ? $single : $plural;
	}
}
if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
	function rest_sanitize_boolean( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
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
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $ttl = 0 ) {
		global $pressark_test_transients;
		unset( $ttl );
		$pressark_test_transients[ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		global $pressark_test_transients;
		return array_key_exists( $key, $pressark_test_transients ) ? $pressark_test_transients[ $key ] : false;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		global $pressark_test_transients;
		unset( $pressark_test_transients[ $key ] );
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10 ) {
		global $pressark_test_actions;
		$pressark_test_actions[ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
		);
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook ) {
		global $pressark_test_actions;
		if ( empty( $pressark_test_actions[ $hook ] ) ) {
			return;
		}
		foreach ( $pressark_test_actions[ $hook ] as $listener ) {
			if ( is_callable( $listener['callback'] ) ) {
				call_user_func( $listener['callback'] );
			}
		}
	}
}
if ( ! function_exists( 'has_action' ) ) {
	function has_action( $hook, $callback = false ) {
		global $pressark_test_actions;
		unset( $callback );
		return ! empty( $pressark_test_actions[ $hook ] );
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10 ) {
		global $pressark_test_filters;
		$pressark_test_filters[ $hook ][] = array(
			'callback' => $callback,
			'priority' => $priority,
		);
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		global $pressark_test_filters;
		$args = func_get_args();
		array_shift( $args );
		if ( empty( $pressark_test_filters[ $hook ] ) ) {
			return $value;
		}
		foreach ( $pressark_test_filters[ $hook ] as $listener ) {
			$args[0] = call_user_func_array( $listener['callback'], $args );
		}
		return $args[0];
	}
}
if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $hook, $callback = false ) {
		global $pressark_test_filters;
		unset( $callback );
		return ! empty( $pressark_test_filters[ $hook ] );
	}
}
if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins() {
		global $pressark_test_plugins;
		return $pressark_test_plugins;
	}
}
if ( ! function_exists( 'get_plugin_updates' ) ) {
	function get_plugin_updates() {
		return array();
	}
}
if ( ! function_exists( 'activate_plugin' ) ) {
	function activate_plugin( $plugin_file ) {
		$active = (array) get_option( 'active_plugins', array() );
		if ( ! in_array( $plugin_file, $active, true ) ) {
			$active[] = $plugin_file;
			update_option( 'active_plugins', array_values( $active ) );
		}
		return true;
	}
}
if ( ! function_exists( 'deactivate_plugins' ) ) {
	function deactivate_plugins( $plugin_file ) {
		$active = array_values( array_filter(
			(array) get_option( 'active_plugins', array() ),
			static fn( $candidate ): bool => $candidate !== $plugin_file
		) );
		update_option( 'active_plugins', $active );
	}
}
if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( $plugin_file ) {
		return in_array( $plugin_file, (array) get_option( 'active_plugins', array() ), true );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return false;
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		unset( $capability );
		return true;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $field ) {
		return 'version' === $field ? WP_VERSION : 'PressArk Test';
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! class_exists( 'PressArk_Tool_Result_Artifacts' ) ) {
	class PressArk_Tool_Result_Artifacts {
		public static function list_resource_entries( int $user_id, int $limit = 8 ): array {
			unset( $user_id, $limit );
			return array();
		}

		public static function is_tool_result_uri( string $uri ): bool {
			unset( $uri );
			return false;
		}

		public static function read_resource( string $uri, int $user_id ): array {
			unset( $uri, $user_id );
			return array( 'success' => false );
		}

		public static function has_resource_entries( int $user_id ): bool {
			unset( $user_id );
			return false;
		}
	}
}

require_once __DIR__ . '/../includes/class-pressark-ext-manifests.php';
require_once __DIR__ . '/../includes/class-pressark-operation.php';
require_once __DIR__ . '/../includes/class-pressark-operation-registry.php';
require_once __DIR__ . '/../includes/class-pressark-resource-registry.php';
require_once __DIR__ . '/../includes/class-pressark-plugins.php';

$passed = 0;
$failed = 0;

function assert_true_ext( string $label, bool $condition ): void {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "  PASS: {$label}\n";
	} else {
		$failed++;
		echo "  FAIL: {$label}\n";
	}
}

function assert_eq_ext( string $label, $expected, $actual ): void {
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

$tmp_root = WP_PLUGIN_DIR;
if ( ! is_dir( $tmp_root ) ) {
	mkdir( $tmp_root, 0777, true );
}

$valid_dir = $tmp_root . '/valid-extension';
$bad_dir   = $tmp_root . '/broken-extension';
@mkdir( $valid_dir, 0777, true );
@mkdir( $bad_dir, 0777, true );
file_put_contents( $valid_dir . '/valid-extension.php', "<?php\n" );
file_put_contents( $bad_dir . '/broken-extension.php', "<?php\n" );

$valid_manifest = array(
	'schema_version' => '1.0',
	'name'           => 'Valid PressArk Extension',
	'version'        => '1.2.0',
	'trust'          => array(
		'class'                  => 'verified_evidence',
		'prompt_injection_class' => 'none',
	),
	'operations'     => array(
		array(
			'name'         => 'ext_sync',
			'verification' => array(
				'strategy'  => 'read_back',
				'read_tool' => 'read_content',
				'intensity' => 'thorough',
				'nudge'     => true,
			),
			'invalidation' => array(
				'scope'  => 'site',
				'reason' => 'Extension sync changes site state.',
			),
		),
	),
	'resources'      => array(
		array(
			'uri'                    => 'pressark://ext/demo',
			'trust_class'            => 'verified_evidence',
			'prompt_injection_class' => 'none',
		),
	),
	'self_test'      => array(
		'hook'     => 'valid_extension_self_test',
		'required' => true,
	),
);

$broken_manifest = array(
	'schema_version' => '2.0',
	'trust'          => array(
		'class'                  => 'dangerous',
		'prompt_injection_class' => 'bad',
	),
);

file_put_contents( $valid_dir . '/pressark-extension.json', json_encode( $valid_manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
file_put_contents( $bad_dir . '/pressark-extension.json', json_encode( $broken_manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

$pressark_test_plugins = array(
	'valid-extension/valid-extension.php' => array(
		'Name'        => 'Valid Extension',
		'Version'     => '1.2.0',
		'Author'      => 'PressArk',
		'Description' => 'Valid extension plugin.',
	),
	'broken-extension/broken-extension.php' => array(
		'Name'        => 'Broken Extension',
		'Version'     => '0.1.0',
		'Author'      => 'PressArk',
		'Description' => 'Broken extension plugin.',
	),
);

update_option( 'active_plugins', array( 'valid-extension/valid-extension.php' ) );

add_action( 'valid_extension_self_test', static function (): void {} );
add_action( 'pressark_register_operations', static function (): void {
	PressArk_Operation_Registry::register(
		new PressArk_Operation(
			name: 'ext_sync',
			group: 'extensions',
			capability: 'confirm',
			handler: 'system',
			method: 'ext_sync',
			preview_strategy: 'none',
			requires: null,
			label: 'Ext Sync',
			description: 'Extension sync operation',
			risk: 'moderate',
			concurrency_safe: false
		)
	);
} );
add_action( 'pressark_register_resources', static function (): void {
	PressArk_Resource_Registry::register( array(
		'uri'      => 'pressark://ext/demo',
		'name'     => 'Extension Demo Resource',
		'group'    => 'extensions',
		'resolver' => static fn(): array => array( 'ok' => true ),
		'ttl'      => 0,
	) );
} );

PressArk_Extension_Manifests::reset();
$valid_report = PressArk_Extension_Manifests::get_report( 'valid-extension/valid-extension.php', $pressark_test_plugins['valid-extension/valid-extension.php'] );
$bad_report   = PressArk_Extension_Manifests::get_report( 'broken-extension/broken-extension.php', $pressark_test_plugins['broken-extension/broken-extension.php'] );

assert_true_ext( 'Valid manifest detected', ! empty( $valid_report['has_manifest'] ) );
assert_true_ext( 'Valid manifest is valid', ! empty( $valid_report['valid'] ) );
assert_eq_ext( 'Valid manifest status is validated', 'validated', $valid_report['status'] );
assert_true_ext( 'Broken manifest is rejected', empty( $bad_report['valid'] ) );
assert_eq_ext( 'Broken manifest status is blocked', 'blocked', $bad_report['status'] );

$plugin_manager = new PressArk_Plugins();
$plugin_rows    = $plugin_manager->list_all();
$valid_row      = null;
foreach ( $plugin_rows as $row ) {
	if ( 'valid-extension/valid-extension.php' === $row['file'] ) {
		$valid_row = $row;
		break;
	}
}

assert_true_ext( 'Plugin listing exposes extension summary', ! empty( $valid_row['pressark_extension']['detected'] ) );
assert_eq_ext( 'Plugin listing extension status', 'validated', $valid_row['pressark_extension']['status'] );

$blocked_toggle = $plugin_manager->toggle( 'broken-extension/broken-extension.php', true );
assert_true_ext( 'Invalid manifest blocks activation', empty( $blocked_toggle['success'] ) );
assert_true_ext( 'Blocked activation returns manifest details', ! empty( $blocked_toggle['extension_manifest']['has_manifest'] ) );

PressArk_Operation_Registry::reset();
$ext_op       = PressArk_Operation_Registry::resolve( 'ext_sync' );
$ext_contract = PressArk_Operation_Registry::get_contract( 'ext_sync' );

assert_true_ext( 'Extension operation registered through action hook', $ext_op instanceof PressArk_Operation );
assert_eq_ext( 'Manifest verification strategy overlays operation contract', 'read_back', $ext_contract['verification']['strategy'] ?? '' );
assert_eq_ext( 'Manifest invalidation scope overlays operation contract', 'site', $ext_contract['read_invalidation']['scope'] ?? '' );
assert_true_ext( 'Extension operation tags include extension marker', in_array( 'extension', (array) ( $ext_contract['tags'] ?? array() ), true ) );

PressArk_Resource_Registry::reset();
$extension_resources = PressArk_Resource_Registry::list( 'extensions' );
$demo_resource       = null;
$manifest_resource   = null;
foreach ( $extension_resources as $resource ) {
	if ( 'pressark://ext/demo' === $resource['uri'] ) {
		$demo_resource = $resource;
	}
	if ( 'pressark://extensions/manifests' === $resource['uri'] ) {
		$manifest_resource = $resource;
	}
}

assert_true_ext( 'Extension resource registered through action hook', is_array( $demo_resource ) );
assert_eq_ext( 'Manifest trust overlays extension resource', 'verified_evidence', $demo_resource['trust_class'] ?? '' );
assert_eq_ext( 'Manifest provider overlays extension resource', 'extension:valid-extension', $demo_resource['provider'] ?? '' );
assert_true_ext( 'Built-in manifest resource is available', is_array( $manifest_resource ) );

$manifest_rows = PressArk_Extension_Manifests::list_resource_payload();
assert_eq_ext( 'Manifest payload includes both installed extensions', 2, count( $manifest_rows ) );

echo "\nExtension manifest tests complete: {$passed} passed, {$failed} failed.\n";
exit( $failed > 0 ? 1 : 0 );
