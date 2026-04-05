<?php
/**
 * PressArk extension manifest discovery, validation, and trust reporting.
 *
 * Third-party WordPress add-ons can place a `pressark-extension.json` file at
 * the plugin root to formally declare the operations/resources they contribute
 * plus the trust and verification expectations PressArk should surface before
 * enablement.
 *
 * @package PressArk
 * @since   5.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Extension_Manifests {

	private const MANIFEST_FILENAME = 'pressark-extension.json';
	private const STATUS_BLOCKED = 'blocked';
	private const STATUS_REVIEW = 'review';
	private const STATUS_VALIDATED = 'validated';
	private const DEFAULT_TRUST_CLASS = 'derived_summary';
	private const DEFAULT_PROMPT_CLASS = 'guarded';
	private const ALLOWED_TRUST_CLASSES = array( 'trusted_system', 'verified_evidence', 'derived_summary', 'untrusted_content' );
	private const ALLOWED_PROMPT_CLASSES = array( 'none', 'guarded', 'elevated', 'high' );
	private const ALLOWED_VERIFY_STRATEGIES = array( 'none', 'read_back', 'field_check', 'existence_check' );
	private const ALLOWED_VERIFY_INTENSITY = array( 'light', 'standard', 'thorough' );
	private const ALLOWED_INVALIDATION_SCOPES = array( 'target_posts', 'site_content', 'resource', 'site' );

	/** @var array<string,array<string,mixed>> */
	private static array $report_cache = array();

	/** @var array<string,array<string,mixed>>|null */
	private static ?array $plugin_inventory = null;

	/** @var array<int,string>|null */
	private static ?array $active_plugins = null;

	/** @var array<string,array<string,mixed>>|null */
	private static ?array $active_reports = null;

	/**
	 * Reset caches (used by tests).
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$report_cache     = array();
		self::$plugin_inventory = null;
		self::$active_plugins   = null;
		self::$active_reports   = null;
	}

	/**
	 * Get a normalized manifest report for one plugin.
	 *
	 * @param string     $plugin_file Plugin basename, e.g. my-plugin/my-plugin.php.
	 * @param array|null $plugin_data Optional get_plugins() metadata for the plugin.
	 * @param bool       $force       Whether to bypass the in-request cache.
	 * @return array<string,mixed>
	 */
	public static function get_report( string $plugin_file, ?array $plugin_data = null, bool $force = false ): array {
		$plugin_file = sanitize_text_field( $plugin_file );
		if ( '' === $plugin_file ) {
			return self::empty_report( '', array() );
		}

		if ( ! $force && isset( self::$report_cache[ $plugin_file ] ) ) {
			return self::$report_cache[ $plugin_file ];
		}

		$plugin_data = is_array( $plugin_data ) ? $plugin_data : ( self::plugin_inventory()[ $plugin_file ] ?? array() );
		$report      = self::empty_report( $plugin_file, $plugin_data );

		$manifest_path = self::locate_manifest_path( $plugin_file );
		if ( '' === $manifest_path ) {
			self::$report_cache[ $plugin_file ] = $report;
			return $report;
		}

		$report['has_manifest']  = true;
		$report['manifest_path'] = $manifest_path;

		if ( ! is_readable( $manifest_path ) ) {
			$report['errors'][] = __( 'Manifest file exists but is not readable.', 'pressark' );
			$report['valid']    = false;
			$report['status']   = self::STATUS_BLOCKED;
			self::$report_cache[ $plugin_file ] = $report;
			return $report;
		}

		$raw = file_get_contents( $manifest_path );
		if ( false === $raw ) {
			$report['errors'][] = __( 'Manifest file could not be loaded.', 'pressark' );
			$report['valid']    = false;
			$report['status']   = self::STATUS_BLOCKED;
			self::$report_cache[ $plugin_file ] = $report;
			return $report;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$report['errors'][] = __( 'Manifest must be valid JSON with an object at the top level.', 'pressark' );
			$report['valid']    = false;
			$report['status']   = self::STATUS_BLOCKED;
			self::$report_cache[ $plugin_file ] = $report;
			return $report;
		}

		$errors   = array();
		$warnings = array();
		$manifest = self::parse_manifest( $decoded, $plugin_file, $plugin_data, $errors, $warnings );

		$report['manifest']      = $manifest;
		$report['errors']        = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $errors ) ) ) );
		$report['warnings']      = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $warnings ) ) ) );
		$report['valid']         = empty( $report['errors'] );
		$report['status']        = self::derive_status( $report );
		$report['trust_warning'] = self::build_trust_warning( $manifest, $report );
		$report['summary']       = self::build_summary( $report );

		self::$report_cache[ $plugin_file ] = $report;
		return $report;
	}

	/**
	 * List installed plugins that advertise a PressArk extension manifest.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_installed(): array {
		$reports = array();
		foreach ( self::plugin_inventory() as $plugin_file => $plugin_data ) {
			$report = self::get_report( $plugin_file, $plugin_data );
			if ( empty( $report['has_manifest'] ) ) {
				continue;
			}
			$reports[] = $report;
		}

		usort( $reports, static function ( array $left, array $right ): int {
			return strcmp(
				strtolower( (string) ( $left['plugin_name'] ?? $left['plugin_file'] ?? '' ) ),
				strtolower( (string) ( $right['plugin_name'] ?? $right['plugin_file'] ?? '' ) )
			);
		} );

		return $reports;
	}

	/**
	 * Build the lightweight plugin-listing summary exposed to the AI/admin.
	 *
	 * @param string     $plugin_file Plugin basename.
	 * @param array|null $plugin_data Optional plugin metadata.
	 * @return array<string,mixed>
	 */
	public static function plugin_summary( string $plugin_file, ?array $plugin_data = null ): array {
		$report = self::get_report( $plugin_file, $plugin_data );
		if ( empty( $report['has_manifest'] ) ) {
			return array(
				'detected' => false,
			);
		}

		$summary = is_array( $report['summary'] ?? null ) ? $report['summary'] : array();

		return array(
			'detected'               => true,
			'valid'                  => ! empty( $report['valid'] ),
			'status'                 => (string) ( $report['status'] ?? self::STATUS_REVIEW ),
			'status_label'           => (string) ( $summary['status_label'] ?? __( 'Needs review', 'pressark' ) ),
			'manifest_path'          => (string) ( $report['manifest_path'] ?? '' ),
			'slug'                   => (string) ( $summary['slug'] ?? '' ),
			'operations_count'       => (int) ( $summary['operations_count'] ?? 0 ),
			'resources_count'        => (int) ( $summary['resources_count'] ?? 0 ),
			'trust_class'            => (string) ( $summary['trust_class'] ?? self::DEFAULT_TRUST_CLASS ),
			'prompt_injection_class' => (string) ( $summary['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ),
			'billing_sensitive'      => ! empty( $summary['billing_sensitive'] ),
			'has_self_test'          => ! empty( $summary['has_self_test'] ),
			'errors_count'           => count( (array) ( $report['errors'] ?? array() ) ),
			'warnings_count'         => count( (array) ( $report['warnings'] ?? array() ) ),
		);
	}

	/**
	 * Merge manifest-declared metadata into a resource definition.
	 *
	 * Active extensions can keep their PHP registration focused on the resolver
	 * while formal metadata such as trust class and provider identity live in the
	 * manifest.
	 *
	 * @param array $definition Resource definition being registered.
	 * @return array
	 */
	public static function overlay_resource_definition( array $definition ): array {
		$uri = sanitize_text_field( (string) ( $definition['uri'] ?? '' ) );
		if ( '' === $uri ) {
			return $definition;
		}

		foreach ( self::active_reports() as $report ) {
			$manifest = is_array( $report['manifest'] ?? null ) ? $report['manifest'] : array();
			foreach ( (array) ( $manifest['resources'] ?? array() ) as $resource ) {
				if ( $uri !== (string) ( $resource['uri'] ?? '' ) ) {
					continue;
				}

				if ( empty( $definition['trust_class'] ) && ! empty( $resource['trust_class'] ) ) {
					$definition['trust_class'] = $resource['trust_class'];
				}

				if ( empty( $definition['provider'] ) ) {
					$definition['provider'] = 'extension:' . sanitize_key( (string) ( $manifest['slug'] ?? 'extension' ) );
				}

				$definition['provenance'] = wp_parse_args(
					(array) ( $definition['provenance'] ?? array() ),
					array(
						'extension_slug'          => sanitize_key( (string) ( $manifest['slug'] ?? '' ) ),
						'plugin_file'             => sanitize_text_field( (string) ( $report['plugin_file'] ?? '' ) ),
						'prompt_injection_class'  => sanitize_key( (string) ( $resource['prompt_injection_class'] ?? $manifest['trust']['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ) ),
						'billing_sensitive_paths' => ! empty( $manifest['billing_sensitive'] ) ? 'yes' : 'no',
					)
				);

				return $definition;
			}
		}

		return $definition;
	}

	/**
	 * Merge manifest-declared verification/invalidation metadata into an
	 * operation execution contract.
	 *
	 * @param array $contract Existing execution contract.
	 * @param mixed $operation PressArk_Operation object when available.
	 * @return array
	 */
	public static function overlay_operation_contract( array $contract, $operation = null ): array {
		$name = sanitize_key(
			(string) (
				is_object( $operation ) && isset( $operation->name )
					? $operation->name
					: ( $contract['name'] ?? '' )
			)
		);
		if ( '' === $name ) {
			return $contract;
		}

		foreach ( self::active_reports() as $report ) {
			$manifest = is_array( $report['manifest'] ?? null ) ? $report['manifest'] : array();
			foreach ( (array) ( $manifest['operations'] ?? array() ) as $operation_def ) {
				if ( $name !== (string) ( $operation_def['name'] ?? '' ) ) {
					continue;
				}

				if ( ! empty( $operation_def['verification'] ) ) {
					$contract['verification'] = wp_parse_args(
						(array) ( $operation_def['verification'] ?? array() ),
						(array) ( $contract['verification'] ?? array() )
					);
				}

				if ( ! empty( $operation_def['read_invalidation'] ) ) {
					$contract['read_invalidation'] = wp_parse_args(
						(array) ( $operation_def['read_invalidation'] ?? array() ),
						(array) ( $contract['read_invalidation'] ?? array() )
					);
				}

				$tags = array_map( 'sanitize_key', (array) ( $contract['tags'] ?? array() ) );
				$tags[] = 'extension';
				$tags[] = 'extension_' . sanitize_key( (string) ( $manifest['slug'] ?? 'addon' ) );
				if ( ! empty( $manifest['billing_sensitive'] ) || ! empty( $operation_def['billing_sensitive'] ) ) {
					$tags[] = 'billing_sensitive';
				}
				$contract['tags'] = array_values( array_unique( array_filter( $tags ) ) );

				return $contract;
			}
		}

		return $contract;
	}

	/**
	 * Return the active manifest reports keyed by plugin file.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function active_reports(): array {
		if ( null !== self::$active_reports ) {
			return self::$active_reports;
		}

		$reports = array();
		foreach ( self::active_plugin_files() as $plugin_file ) {
			$report = self::get_report( $plugin_file, self::plugin_inventory()[ $plugin_file ] ?? array() );
			if ( empty( $report['has_manifest'] ) ) {
				continue;
			}
			$reports[ $plugin_file ] = $report;
		}

		self::$active_reports = $reports;
		return $reports;
	}

	/**
	 * Build the derived-summary resource payload for extension manifests.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_resource_payload(): array {
		$rows = array();
		foreach ( self::list_installed() as $report ) {
			$summary  = is_array( $report['summary'] ?? null ) ? $report['summary'] : array();
			$manifest = is_array( $report['manifest'] ?? null ) ? $report['manifest'] : array();
			$rows[]   = array(
				'plugin_file'            => sanitize_text_field( (string) ( $report['plugin_file'] ?? '' ) ),
				'plugin_name'            => sanitize_text_field( (string) ( $report['plugin_name'] ?? '' ) ),
				'plugin_version'         => sanitize_text_field( (string) ( $report['plugin_version'] ?? '' ) ),
				'active'                 => ! empty( $report['active'] ),
				'status'                 => sanitize_key( (string) ( $report['status'] ?? self::STATUS_REVIEW ) ),
				'valid'                  => ! empty( $report['valid'] ),
				'slug'                   => sanitize_key( (string) ( $manifest['slug'] ?? '' ) ),
				'name'                   => sanitize_text_field( (string) ( $manifest['name'] ?? '' ) ),
				'version'                => sanitize_text_field( (string) ( $manifest['version'] ?? '' ) ),
				'operations'             => array_values( array_filter( array_map( static fn( $item ) => sanitize_key( (string) ( $item['name'] ?? '' ) ), (array) ( $manifest['operations'] ?? array() ) ) ) ),
				'resources'              => array_values( array_filter( array_map( static fn( $item ) => sanitize_text_field( (string) ( $item['uri'] ?? '' ) ), (array) ( $manifest['resources'] ?? array() ) ) ) ),
				'trust_class'            => sanitize_key( (string) ( $summary['trust_class'] ?? self::DEFAULT_TRUST_CLASS ) ),
				'prompt_injection_class' => sanitize_key( (string) ( $summary['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ) ),
				'billing_sensitive'      => ! empty( $summary['billing_sensitive'] ),
				'has_self_test'          => ! empty( $summary['has_self_test'] ),
				'errors'                 => array_values( array_map( 'sanitize_text_field', (array) ( $report['errors'] ?? array() ) ) ),
				'warnings'               => array_values( array_map( 'sanitize_text_field', (array) ( $report['warnings'] ?? array() ) ) ),
			);
		}

		return $rows;
	}

	/**
	 * Locate a manifest file for a plugin.
	 */
	private static function locate_manifest_path( string $plugin_file ): string {
		$plugin_root = self::plugin_root_dir( $plugin_file );
		if ( '' === $plugin_root ) {
			return '';
		}

		$candidate = rtrim( $plugin_root, '/\\' ) . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
		return file_exists( $candidate ) ? $candidate : '';
	}

	/**
	 * Parse and normalize one manifest payload.
	 *
	 * @param array  $raw         Decoded manifest JSON.
	 * @param string $plugin_file Plugin basename.
	 * @param array  $plugin_data get_plugins() metadata.
	 * @param array  $errors      Collected blocking issues.
	 * @param array  $warnings    Collected review warnings.
	 * @return array<string,mixed>
	 */
	private static function parse_manifest( array $raw, string $plugin_file, array $plugin_data, array &$errors, array &$warnings ): array {
		$minimums = is_array( $raw['minimum_versions'] ?? null ) ? $raw['minimum_versions'] : array();
		$requires = is_array( $raw['requires'] ?? null ) ? $raw['requires'] : array();

		$manifest = array(
			'plugin_file'        => $plugin_file,
			'schema_version'     => self::parse_schema_version( $raw['schema_version'] ?? $raw['manifest_version'] ?? 1, $errors ),
			'slug'               => self::default_slug( $plugin_file, $raw['slug'] ?? '' ),
			'name'               => sanitize_text_field( (string) ( $raw['name'] ?? $plugin_data['Name'] ?? $plugin_file ) ),
			'version'            => sanitize_text_field( (string) ( $raw['version'] ?? $plugin_data['Version'] ?? '' ) ),
			'trust'              => self::parse_trust( $raw, $errors ),
			'billing_sensitive'  => self::parse_bool( $raw['billing_sensitive'] ?? $raw['touches_billing_sensitive_paths'] ?? false ),
			'verification'       => self::parse_verification( $raw['verification'] ?? $raw['expectations']['verification'] ?? array(), $errors, 'verification' ),
			'invalidation'       => self::parse_invalidation( $raw['invalidation'] ?? $raw['expectations']['invalidation'] ?? array(), $errors, 'invalidation' ),
			'self_test'          => self::parse_self_test( $raw['self_test'] ?? array(), $warnings ),
			'conformance_checks' => self::parse_text_list( $raw['conformance_checks'] ?? $raw['conformance'] ?? array(), false ),
			'requires'           => array(
				'plugins'               => self::parse_required_plugins( $requires['plugins'] ?? $raw['required_plugins'] ?? array(), $errors ),
				'capabilities'          => self::parse_text_list( $requires['capabilities'] ?? $raw['required_capabilities'] ?? array(), true ),
				'pressark_min_version'  => sanitize_text_field( (string) ( $requires['pressark_min_version'] ?? $minimums['pressark'] ?? $raw['pressark_min_version'] ?? '' ) ),
				'wordpress_min_version' => sanitize_text_field( (string) ( $requires['wordpress_min_version'] ?? $minimums['wordpress'] ?? $raw['wordpress_min_version'] ?? '' ) ),
				'php_min_version'       => sanitize_text_field( (string) ( $requires['php_min_version'] ?? $minimums['php'] ?? $raw['php_min_version'] ?? '' ) ),
			),
			'operations'         => array(),
			'resources'          => array(),
		);

		$manifest['operations'] = self::parse_operations(
			$raw['operations'] ?? array(),
			$manifest,
			$errors
		);
		$manifest['resources'] = self::parse_resources(
			$raw['resources'] ?? array(),
			$manifest,
			$errors
		);

		if ( empty( $manifest['operations'] ) && empty( $manifest['resources'] ) ) {
			$errors[] = __( 'Manifest must declare at least one operation or one resource.', 'pressark' );
		}

		self::evaluate_requirements( $manifest, $errors, $warnings );
		self::evaluate_review_warnings( $manifest, $warnings );

		return $manifest;
	}

	/**
	 * Evaluate hard requirements and soft capability/self-test warnings.
	 *
	 * @param array $manifest Normalized manifest.
	 * @param array $errors   Hard requirement failures.
	 * @param array $warnings Soft review warnings.
	 */
	private static function evaluate_requirements( array $manifest, array &$errors, array &$warnings ): void {
		$requires = is_array( $manifest['requires'] ?? null ) ? $manifest['requires'] : array();
		$current_pressark = defined( 'PRESSARK_VERSION' ) ? (string) PRESSARK_VERSION : '';
		$current_wp       = self::wordpress_version();
		$current_php      = PHP_VERSION;

		if ( '' !== (string) ( $requires['pressark_min_version'] ?? '' ) && '' !== $current_pressark && version_compare( $current_pressark, (string) $requires['pressark_min_version'], '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required version 2: current version */
				__( 'Requires PressArk %1$s or newer (current: %2$s).', 'pressark' ),
				(string) $requires['pressark_min_version'],
				$current_pressark
			);
		}

		if ( '' !== (string) ( $requires['wordpress_min_version'] ?? '' ) && '' !== $current_wp && version_compare( $current_wp, (string) $requires['wordpress_min_version'], '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required version 2: current version */
				__( 'Requires WordPress %1$s or newer (current: %2$s).', 'pressark' ),
				(string) $requires['wordpress_min_version'],
				$current_wp
			);
		}

		if ( '' !== (string) ( $requires['php_min_version'] ?? '' ) && version_compare( $current_php, (string) $requires['php_min_version'], '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required version 2: current version */
				__( 'Requires PHP %1$s or newer (current: %2$s).', 'pressark' ),
				(string) $requires['php_min_version'],
				$current_php
			);
		}

		$inventory      = self::plugin_inventory();
		$active_plugins = self::active_plugin_files();
		foreach ( (array) ( $requires['plugins'] ?? array() ) as $dependency ) {
			$file = sanitize_text_field( (string) ( $dependency['file'] ?? '' ) );
			if ( '' === $file ) {
				continue;
			}

			if ( ! isset( $inventory[ $file ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: required plugin file */
					__( 'Requires plugin %s to be installed.', 'pressark' ),
					$file
				);
				continue;
			}

			if ( ! in_array( $file, $active_plugins, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: required plugin file */
					__( 'Requires plugin %s to be active.', 'pressark' ),
					$file
				);
			}

			$min_version  = sanitize_text_field( (string) ( $dependency['min_version'] ?? '' ) );
			$have_version = sanitize_text_field( (string) ( $inventory[ $file ]['Version'] ?? '' ) );
			if ( '' !== $min_version && '' !== $have_version && version_compare( $have_version, $min_version, '<' ) ) {
				$errors[] = sprintf(
					/* translators: 1: plugin file 2: required version 3: current version */
					__( 'Requires %1$s version %2$s or newer (current: %3$s).', 'pressark' ),
					$file,
					$min_version,
					$have_version
				);
			}
		}

		if ( function_exists( 'current_user_can' ) ) {
			$missing_caps = array();
			foreach ( (array) ( $requires['capabilities'] ?? array() ) as $capability ) {
				if ( '' !== $capability && ! current_user_can( $capability ) ) {
					$missing_caps[] = $capability;
				}
			}

			if ( ! empty( $missing_caps ) ) {
				$warnings[] = sprintf(
					/* translators: %s: comma-separated capability list */
					__( 'Current admin user is missing declared extension capabilities: %s.', 'pressark' ),
					implode( ', ', $missing_caps )
				);
			}
		}

		$self_test = is_array( $manifest['self_test'] ?? null ) ? $manifest['self_test'] : array();
		$is_active = in_array( sanitize_text_field( (string) ( $manifest['plugin_file'] ?? '' ) ), $active_plugins, true );
		if ( $is_active && ! empty( $self_test['hook'] ) && function_exists( 'has_action' ) ) {
			$hook_present = has_action( (string) $self_test['hook'] ) || ( function_exists( 'has_filter' ) && has_filter( (string) $self_test['hook'] ) );
			if ( ! $hook_present ) {
				$target = sprintf(
					/* translators: %s: hook name */
					__( 'Declared self-test hook %s is not currently registered.', 'pressark' ),
					(string) $self_test['hook']
				);
				if ( ! empty( $self_test['required'] ) ) {
					$errors[] = $target;
				} else {
					$warnings[] = $target;
				}
			}
		}
	}

	/**
	 * Add non-blocking trust review warnings.
	 */
	private static function evaluate_review_warnings( array $manifest, array &$warnings ): void {
		$trust_class  = sanitize_key( (string) ( $manifest['trust']['class'] ?? self::DEFAULT_TRUST_CLASS ) );
		$prompt_class = sanitize_key( (string) ( $manifest['trust']['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ) );

		if ( ! empty( $manifest['billing_sensitive'] ) ) {
			$warnings[] = __( 'Extension declares billing-sensitive paths. Review carefully before enabling.', 'pressark' );
		}

		if ( in_array( $prompt_class, array( 'elevated', 'high' ), true ) ) {
			$warnings[] = sprintf(
				/* translators: %s: prompt-injection class */
				__( 'Extension declares %s prompt-injection exposure.', 'pressark' ),
				$prompt_class
			);
		}

		if ( in_array( $trust_class, array( 'derived_summary', 'untrusted_content' ), true ) ) {
			$warnings[] = sprintf(
				/* translators: %s: trust class */
				__( 'Extension trust class is %s, so its declarations should be treated as review material rather than verified system fact.', 'pressark' ),
				$trust_class
			);
		}

		if ( empty( $manifest['self_test']['hook'] ) && empty( $manifest['self_test']['callback'] ) && empty( $manifest['conformance_checks'] ) ) {
			$warnings[] = __( 'Extension does not declare a self-test or conformance checks.', 'pressark' );
		}
	}

	/**
	 * Derive a user-facing status bucket for one report.
	 */
	private static function derive_status( array $report ): string {
		if ( ! empty( $report['errors'] ) ) {
			return self::STATUS_BLOCKED;
		}

		$manifest = is_array( $report['manifest'] ?? null ) ? $report['manifest'] : array();
		$trust    = sanitize_key( (string) ( $manifest['trust']['class'] ?? self::DEFAULT_TRUST_CLASS ) );
		$prompt   = sanitize_key( (string) ( $manifest['trust']['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ) );

		if ( ! empty( $report['warnings'] ) || ! empty( $manifest['billing_sensitive'] ) || in_array( $prompt, array( 'elevated', 'high' ), true ) || in_array( $trust, array( 'derived_summary', 'untrusted_content' ), true ) ) {
			return self::STATUS_REVIEW;
		}

		return self::STATUS_VALIDATED;
	}

	/**
	 * Build the compact structured summary used by admin/plugin listing surfaces.
	 */
	private static function build_summary( array $report ): array {
		$manifest = is_array( $report['manifest'] ?? null ) ? $report['manifest'] : array();
		$status   = sanitize_key( (string) ( $report['status'] ?? self::STATUS_REVIEW ) );

		return array(
			'slug'                   => sanitize_key( (string) ( $manifest['slug'] ?? '' ) ),
			'operations_count'       => count( (array) ( $manifest['operations'] ?? array() ) ),
			'resources_count'        => count( (array) ( $manifest['resources'] ?? array() ) ),
			'trust_class'            => sanitize_key( (string) ( $manifest['trust']['class'] ?? self::DEFAULT_TRUST_CLASS ) ),
			'prompt_injection_class' => sanitize_key( (string) ( $manifest['trust']['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ) ),
			'billing_sensitive'      => ! empty( $manifest['billing_sensitive'] ),
			'has_self_test'          => ! empty( $manifest['self_test']['hook'] ) || ! empty( $manifest['self_test']['callback'] ),
			'status_label'           => match ( $status ) {
				self::STATUS_BLOCKED   => __( 'Blocked', 'pressark' ),
				self::STATUS_VALIDATED => __( 'Validated', 'pressark' ),
				default                => __( 'Needs Review', 'pressark' ),
			},
		);
	}

	/**
	 * Build the trust warning sentence shown in previews/admin.
	 */
	private static function build_trust_warning( array $manifest, array $report ): string {
		if ( empty( $manifest ) ) {
			return '';
		}

		$parts = array();
		$op_count  = count( (array) ( $manifest['operations'] ?? array() ) );
		$res_count = count( (array) ( $manifest['resources'] ?? array() ) );
		if ( $op_count > 0 || $res_count > 0 ) {
			$parts[] = sprintf(
				/* translators: 1: operation count 2: resource count */
				__( 'Declares %1$d operation(s) and %2$d resource(s).', 'pressark' ),
				$op_count,
				$res_count
			);
		}

		$parts[] = sprintf(
			/* translators: 1: trust class 2: prompt-injection class */
			__( 'Trust class: %1$s. Prompt-injection class: %2$s.', 'pressark' ),
			sanitize_key( (string) ( $manifest['trust']['class'] ?? self::DEFAULT_TRUST_CLASS ) ),
			sanitize_key( (string) ( $manifest['trust']['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ) )
		);

		if ( ! empty( $manifest['billing_sensitive'] ) ) {
			$parts[] = __( 'Touches billing-sensitive paths.', 'pressark' );
		}

		if ( ! empty( $report['errors'] ) ) {
			$parts[] = __( 'Activation is blocked until manifest issues are fixed.', 'pressark' );
		} elseif ( ! empty( $report['warnings'] ) ) {
			$parts[] = __( 'Review the manifest warnings before enabling.', 'pressark' );
		}

		return implode( ' ', array_filter( $parts ) );
	}

	/**
	 * Parse schema version and validate it against the supported format.
	 *
	 * @param mixed $raw_version Raw schema version value.
	 * @param array $errors      Error list to append to.
	 * @return string
	 */
	private static function parse_schema_version( $raw_version, array &$errors ): string {
		$version = sanitize_text_field( (string) $raw_version );
		if ( '' === $version ) {
			$version = '1';
		}

		if ( ! in_array( $version, array( '1', '1.0' ), true ) ) {
			$errors[] = __( 'Manifest schema_version must currently be 1 or 1.0.', 'pressark' );
			return '1';
		}

		return $version;
	}

	/**
	 * Parse the manifest trust declaration.
	 *
	 * @param array $raw    Raw manifest object.
	 * @param array $errors Error list to append to.
	 * @return array<string,string>
	 */
	private static function parse_trust( array $raw, array &$errors ): array {
		$trust_raw = is_array( $raw['trust'] ?? null ) ? $raw['trust'] : array();
		$class     = sanitize_key( (string) ( $trust_raw['class'] ?? $raw['trust_class'] ?? self::DEFAULT_TRUST_CLASS ) );
		$prompt    = sanitize_key( (string) ( $trust_raw['prompt_injection_class'] ?? $raw['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ) );

		if ( ! in_array( $class, self::ALLOWED_TRUST_CLASSES, true ) ) {
			$errors[] = sprintf(
				/* translators: %s: invalid trust class */
				__( 'Unsupported trust.class value: %s.', 'pressark' ),
				$class
			);
			$class = self::DEFAULT_TRUST_CLASS;
		}

		if ( ! in_array( $prompt, self::ALLOWED_PROMPT_CLASSES, true ) ) {
			$errors[] = sprintf(
				/* translators: %s: invalid prompt-injection class */
				__( 'Unsupported trust.prompt_injection_class value: %s.', 'pressark' ),
				$prompt
			);
			$prompt = self::DEFAULT_PROMPT_CLASS;
		}

		return array(
			'class'                  => $class,
			'prompt_injection_class' => $prompt,
		);
	}

	/**
	 * Parse operation declarations from the manifest.
	 *
	 * @param mixed $raw_operations Raw operations payload.
	 * @param array $manifest       Parent manifest defaults.
	 * @param array $errors         Error list to append to.
	 * @return array<int,array<string,mixed>>
	 */
	private static function parse_operations( $raw_operations, array $manifest, array &$errors ): array {
		if ( empty( $raw_operations ) ) {
			return array();
		}
		if ( ! is_array( $raw_operations ) ) {
			$errors[] = __( 'Manifest operations must be an array.', 'pressark' );
			return array();
		}

		$items = array();
		foreach ( $raw_operations as $index => $raw_operation ) {
			$op = is_array( $raw_operation )
				? $raw_operation
				: array( 'name' => $raw_operation );

			$name = sanitize_key( (string) ( $op['name'] ?? '' ) );
			if ( '' === $name ) {
				$errors[] = sprintf(
					/* translators: %d: operation array offset */
					__( 'Operation entry %d must include a valid name.', 'pressark' ),
					(int) $index
				);
				continue;
			}

			$items[ $name ] = array(
				'name'              => $name,
				'verification'      => self::parse_verification(
					$op['verification'] ?? $manifest['verification'] ?? array(),
					$errors,
					sprintf( 'operations[%s].verification', $name )
				),
				'read_invalidation' => self::parse_invalidation(
					$op['read_invalidation'] ?? $op['invalidation'] ?? $manifest['invalidation'] ?? array(),
					$errors,
					sprintf( 'operations[%s].read_invalidation', $name )
				),
				'billing_sensitive' => self::parse_bool( $op['billing_sensitive'] ?? $op['touches_billing_sensitive_paths'] ?? $manifest['billing_sensitive'] ?? false ),
			);
		}

		return array_values( $items );
	}

	/**
	 * Parse resource declarations from the manifest.
	 *
	 * @param mixed $raw_resources Raw resources payload.
	 * @param array $manifest      Parent manifest defaults.
	 * @param array $errors        Error list to append to.
	 * @return array<int,array<string,mixed>>
	 */
	private static function parse_resources( $raw_resources, array $manifest, array &$errors ): array {
		if ( empty( $raw_resources ) ) {
			return array();
		}
		if ( ! is_array( $raw_resources ) ) {
			$errors[] = __( 'Manifest resources must be an array.', 'pressark' );
			return array();
		}

		$items = array();
		foreach ( $raw_resources as $index => $raw_resource ) {
			$resource = is_array( $raw_resource )
				? $raw_resource
				: array( 'uri' => $raw_resource );

			$uri = sanitize_text_field( (string) ( $resource['uri'] ?? '' ) );
			if ( '' === $uri || ! str_starts_with( $uri, 'pressark://' ) ) {
				$errors[] = sprintf(
					/* translators: %d: resource array offset */
					__( 'Resource entry %d must include a pressark:// URI.', 'pressark' ),
					(int) $index
				);
				continue;
			}

			$trust_class = sanitize_key( (string) ( $resource['trust_class'] ?? $manifest['trust']['class'] ?? self::DEFAULT_TRUST_CLASS ) );
			$prompt      = sanitize_key( (string) ( $resource['prompt_injection_class'] ?? $manifest['trust']['prompt_injection_class'] ?? self::DEFAULT_PROMPT_CLASS ) );
			if ( ! in_array( $trust_class, self::ALLOWED_TRUST_CLASSES, true ) ) {
				$errors[] = sprintf(
					/* translators: 1: URI 2: invalid trust class */
					__( 'Resource %1$s declares unsupported trust_class %2$s.', 'pressark' ),
					$uri,
					$trust_class
				);
				$trust_class = self::DEFAULT_TRUST_CLASS;
			}
			if ( ! in_array( $prompt, self::ALLOWED_PROMPT_CLASSES, true ) ) {
				$errors[] = sprintf(
					/* translators: 1: URI 2: invalid prompt class */
					__( 'Resource %1$s declares unsupported prompt_injection_class %2$s.', 'pressark' ),
					$uri,
					$prompt
				);
				$prompt = self::DEFAULT_PROMPT_CLASS;
			}

			$items[ $uri ] = array(
				'uri'                    => $uri,
				'trust_class'            => $trust_class,
				'prompt_injection_class' => $prompt,
			);
		}

		return array_values( $items );
	}

	/**
	 * Parse a verification contract.
	 *
	 * @param mixed  $raw_verification Raw verification payload.
	 * @param array  $errors           Error list to append to.
	 * @param string $context          Human-readable error context.
	 * @return array<string,mixed>
	 */
	private static function parse_verification( $raw_verification, array &$errors, string $context ): array {
		if ( empty( $raw_verification ) ) {
			return array();
		}
		if ( ! is_array( $raw_verification ) ) {
			$errors[] = sprintf(
				/* translators: %s: manifest section name */
				__( '%s must be an object.', 'pressark' ),
				$context
			);
			return array();
		}

		$strategy = sanitize_key( (string) ( $raw_verification['strategy'] ?? '' ) );
		if ( '' === $strategy ) {
			$strategy = 'none';
		}
		if ( ! in_array( $strategy, self::ALLOWED_VERIFY_STRATEGIES, true ) ) {
			$errors[] = sprintf(
				/* translators: 1: context 2: invalid strategy */
				__( '%1$s declares unsupported verification strategy %2$s.', 'pressark' ),
				$context,
				$strategy
			);
			$strategy = 'none';
		}

		$intensity = sanitize_key( (string) ( $raw_verification['intensity'] ?? 'standard' ) );
		if ( ! in_array( $intensity, self::ALLOWED_VERIFY_INTENSITY, true ) ) {
			$errors[] = sprintf(
				/* translators: 1: context 2: invalid intensity */
				__( '%1$s declares unsupported verification intensity %2$s.', 'pressark' ),
				$context,
				$intensity
			);
			$intensity = 'standard';
		}

		return array(
			'strategy'     => $strategy,
			'read_tool'    => sanitize_key( (string) ( $raw_verification['read_tool'] ?? '' ) ),
			'read_args'    => is_array( $raw_verification['read_args'] ?? null ) ? $raw_verification['read_args'] : array(),
			'check_fields' => self::parse_text_list( $raw_verification['check_fields'] ?? array(), true ),
			'intensity'    => $intensity,
			'nudge'        => self::parse_bool( $raw_verification['nudge'] ?? false ),
		);
	}

	/**
	 * Parse a read invalidation contract.
	 *
	 * @param mixed  $raw_invalidation Raw invalidation payload.
	 * @param array  $errors           Error list to append to.
	 * @param string $context          Human-readable error context.
	 * @return array<string,mixed>
	 */
	private static function parse_invalidation( $raw_invalidation, array &$errors, string $context ): array {
		if ( empty( $raw_invalidation ) ) {
			return array();
		}
		if ( ! is_array( $raw_invalidation ) ) {
			$errors[] = sprintf(
				/* translators: %s: manifest section name */
				__( '%s must be an object.', 'pressark' ),
				$context
			);
			return array();
		}

		$scope = sanitize_key( (string) ( $raw_invalidation['scope'] ?? '' ) );
		if ( '' !== $scope && ! in_array( $scope, self::ALLOWED_INVALIDATION_SCOPES, true ) ) {
			$errors[] = sprintf(
				/* translators: 1: context 2: invalid scope */
				__( '%1$s declares unsupported invalidation scope %2$s.', 'pressark' ),
				$context,
				$scope
			);
			$scope = '';
		}

		return array(
			'scope'           => $scope,
			'resource_groups' => self::parse_text_list( $raw_invalidation['resource_groups'] ?? array(), true ),
			'resource_uris'   => self::parse_text_list( $raw_invalidation['resource_uris'] ?? array(), false ),
			'reason'          => sanitize_text_field( (string) ( $raw_invalidation['reason'] ?? '' ) ),
		);
	}

	/**
	 * Parse the optional self-test declaration.
	 *
	 * @param mixed $raw_self_test Raw self-test object.
	 * @param array $warnings      Warning list to append to.
	 * @return array<string,mixed>
	 */
	private static function parse_self_test( $raw_self_test, array &$warnings ): array {
		if ( empty( $raw_self_test ) ) {
			return array();
		}
		if ( ! is_array( $raw_self_test ) ) {
			$warnings[] = __( 'Manifest self_test should be an object when provided.', 'pressark' );
			return array();
		}

		return array(
			'label'    => sanitize_text_field( (string) ( $raw_self_test['label'] ?? '' ) ),
			'hook'     => sanitize_text_field( (string) ( $raw_self_test['hook'] ?? '' ) ),
			'callback' => sanitize_text_field( (string) ( $raw_self_test['callback'] ?? '' ) ),
			'required' => self::parse_bool( $raw_self_test['required'] ?? false ),
		);
	}

	/**
	 * Parse required plugin descriptors.
	 *
	 * @param mixed $raw_plugins Raw plugins payload.
	 * @param array $errors      Error list to append to.
	 * @return array<int,array<string,string>>
	 */
	private static function parse_required_plugins( $raw_plugins, array &$errors ): array {
		if ( empty( $raw_plugins ) ) {
			return array();
		}
		if ( ! is_array( $raw_plugins ) ) {
			$errors[] = __( 'Manifest requires.plugins must be an array.', 'pressark' );
			return array();
		}

		$items = array();
		foreach ( $raw_plugins as $index => $plugin_dep ) {
			$dep  = is_array( $plugin_dep ) ? $plugin_dep : array( 'file' => $plugin_dep );
			$file = self::normalize_plugin_requirement_file( $dep['file'] ?? '' );
			if ( '' === $file ) {
				$errors[] = sprintf(
					/* translators: %d: dependency offset */
					__( 'Required plugin entry %d must identify a plugin file or slug.', 'pressark' ),
					(int) $index
				);
				continue;
			}

			$items[ $file ] = array(
				'file'        => $file,
				'min_version' => sanitize_text_field( (string) ( $dep['min_version'] ?? '' ) ),
			);
		}

		return array_values( $items );
	}

	/**
	 * Normalize a plugin requirement slug/file to a plugin basename.
	 */
	private static function normalize_plugin_requirement_file( $raw_file ): string {
		$file = sanitize_text_field( (string) $raw_file );
		if ( '' === $file ) {
			return '';
		}
		if ( str_contains( $file, '/' ) ) {
			return $file;
		}
		if ( str_ends_with( $file, '.php' ) ) {
			return $file;
		}
		return $file . '/' . $file . '.php';
	}

	/**
	 * Parse a list of strings and optionally sanitize as keys.
	 *
	 * @param mixed $raw     Raw list value.
	 * @param bool  $as_keys Whether to sanitize each value via sanitize_key().
	 * @return array<int,string>
	 */
	private static function parse_text_list( $raw, bool $as_keys ): array {
		if ( empty( $raw ) ) {
			return array();
		}
		if ( ! is_array( $raw ) ) {
			$raw = array( $raw );
		}

		$items = array();
		foreach ( $raw as $value ) {
			$clean = $as_keys
				? sanitize_key( (string) $value )
				: sanitize_text_field( (string) $value );
			if ( '' === $clean ) {
				continue;
			}
			$items[ $clean ] = $clean;
		}

		return array_values( $items );
	}

	/**
	 * Convert assorted truthy values to a boolean.
	 *
	 * @param mixed $value Raw boolean-ish value.
	 */
	private static function parse_bool( $value ): bool {
		if ( function_exists( 'rest_sanitize_boolean' ) ) {
			return (bool) rest_sanitize_boolean( $value );
		}
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Default the manifest slug from the plugin basename.
	 */
	private static function default_slug( string $plugin_file, $raw_slug ): string {
		$slug = sanitize_key( (string) $raw_slug );
		if ( '' !== $slug ) {
			return $slug;
		}

		$dir = dirname( $plugin_file );
		if ( '.' !== $dir && '' !== $dir ) {
			return sanitize_key( basename( $dir ) );
		}

		return sanitize_key( basename( $plugin_file, '.php' ) );
	}

	/**
	 * Build the base empty report shape.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param array  $plugin_data Optional plugin metadata.
	 * @return array<string,mixed>
	 */
	private static function empty_report( string $plugin_file, array $plugin_data ): array {
		return array(
			'plugin_file'    => $plugin_file,
			'plugin_name'    => sanitize_text_field( (string) ( $plugin_data['Name'] ?? $plugin_file ) ),
			'plugin_version' => sanitize_text_field( (string) ( $plugin_data['Version'] ?? '' ) ),
			'active'         => in_array( $plugin_file, self::active_plugin_files(), true ),
			'manifest_path'  => '',
			'has_manifest'   => false,
			'valid'          => true,
			'status'         => self::STATUS_VALIDATED,
			'errors'         => array(),
			'warnings'       => array(),
			'manifest'       => array(
				'plugin_file' => $plugin_file,
			),
			'summary'        => array(),
			'trust_warning'  => '',
		);
	}

	/**
	 * Resolve the installed plugin inventory, loading WordPress helpers if needed.
	 *
	 * @return array<string,array>
	 */
	private static function plugin_inventory(): array {
		if ( null !== self::$plugin_inventory ) {
			return self::$plugin_inventory;
		}

		self::load_plugin_functions();
		self::$plugin_inventory = function_exists( 'get_plugins' ) ? (array) get_plugins() : array();
		return self::$plugin_inventory;
	}

	/**
	 * Resolve the active plugin basenames.
	 *
	 * @return array<int,string>
	 */
	private static function active_plugin_files(): array {
		if ( null !== self::$active_plugins ) {
			return self::$active_plugins;
		}

		$active = array_map( 'sanitize_text_field', (array) get_option( 'active_plugins', array() ) );
		self::$active_plugins = array_values( array_unique( array_filter( $active ) ) );
		return self::$active_plugins;
	}

	/**
	 * Load the WordPress plugin helpers if the environment has them.
	 */
	private static function load_plugin_functions(): void {
		if ( function_exists( 'get_plugins' ) ) {
			return;
		}

		if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Resolve a plugin's root directory.
	 */
	private static function plugin_root_dir( string $plugin_file ): string {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			return '';
		}

		$plugin_file = ltrim( str_replace( '\\', '/', $plugin_file ), '/' );
		if ( '' === $plugin_file ) {
			return '';
		}

		$root_rel = dirname( $plugin_file );
		if ( '.' === $root_rel ) {
			return WP_PLUGIN_DIR;
		}

		return rtrim( WP_PLUGIN_DIR, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $root_rel );
	}

	/**
	 * Read the active WordPress version when available.
	 */
	private static function wordpress_version(): string {
		if ( defined( 'WP_VERSION' ) ) {
			return (string) WP_VERSION;
		}
		if ( function_exists( 'get_bloginfo' ) ) {
			return (string) get_bloginfo( 'version' );
		}
		return '';
	}
}
