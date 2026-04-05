<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin management for PressArk.
 * Lists, activates, and deactivates WordPress plugins.
 */
class PressArk_Plugins {

	/**
	 * List all installed plugins with status and update info.
	 */
	public function list_all(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$all_plugins = get_plugins();
		$active      = get_option( 'active_plugins', array() );
		$updates     = function_exists( 'get_plugin_updates' ) ? get_plugin_updates() : array();
		$results     = array();

		foreach ( $all_plugins as $file => $data ) {
			$is_active  = in_array( $file, $active, true );
			$has_update = isset( $updates[ $file ] );
			$row        = array(
				'file'             => $file,
				'name'             => $data['Name'],
				'version'          => $data['Version'],
				'author'           => $data['AuthorName'] ?? $data['Author'],
				'description'      => wp_strip_all_tags( $data['Description'] ),
				'active'           => $is_active,
				'update_available' => $has_update,
				'new_version'      => $has_update ? $updates[ $file ]->update->new_version : null,
			);
			if ( class_exists( 'PressArk_Extension_Manifests' ) ) {
				$extension_summary = PressArk_Extension_Manifests::plugin_summary( $file, $data );
				if ( ! empty( $extension_summary['detected'] ) ) {
					$row['pressark_extension'] = $extension_summary;
				}
			}
			$results[] = $row;
		}

		return $results;
	}

	/**
	 * Activate or deactivate a plugin.
	 */
	public function toggle( string $plugin_file, bool $activate = true ): array {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$manifest_report = class_exists( 'PressArk_Extension_Manifests' )
			? PressArk_Extension_Manifests::get_report( $plugin_file )
			: array();

		// Safety: don't allow deactivating PressArk itself.
		if ( strpos( $plugin_file, 'pressark' ) !== false ) {
			return array( 'success' => false, 'message' => 'Cannot deactivate PressArk through itself.' );
		}

		if ( $activate ) {
			if ( ! empty( $manifest_report['has_manifest'] ) && empty( $manifest_report['valid'] ) ) {
				return array(
					'success'            => false,
					'message'            => $this->manifest_block_message( $manifest_report ),
					'extension_manifest' => $manifest_report,
				);
			}

			$result = activate_plugin( $plugin_file );
			if ( is_wp_error( $result ) ) {
				return array( 'success' => false, 'message' => $result->get_error_message() );
			}
			$name = $this->get_plugin_name( $plugin_file );
			$response = array( 'success' => true, 'message' => "Activated \"{$name}\"." );
			if ( ! empty( $manifest_report['has_manifest'] ) ) {
				$response['extension_manifest'] = $manifest_report;
				if ( ! empty( $manifest_report['trust_warning'] ) ) {
					$response['message'] .= ' ' . sanitize_text_field( (string) $manifest_report['trust_warning'] );
				}
			}
			return $response;
		} else {
			deactivate_plugins( $plugin_file );
			$name = $this->get_plugin_name( $plugin_file );
			$response = array( 'success' => true, 'message' => "Deactivated \"{$name}\"." );
			if ( ! empty( $manifest_report['has_manifest'] ) ) {
				$response['extension_manifest'] = $manifest_report;
			}
			return $response;
		}
	}

	/**
	 * Get detailed info for a specific plugin.
	 */
	public function get_info( string $plugin_file ): ?array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all = get_plugins();
		if ( ! isset( $all[ $plugin_file ] ) ) {
			return null;
		}
		$data           = $all[ $plugin_file ];
		$data['active'] = is_plugin_active( $plugin_file );
		$data['file']   = $plugin_file;
		if ( class_exists( 'PressArk_Extension_Manifests' ) ) {
			$extension_summary = PressArk_Extension_Manifests::plugin_summary( $plugin_file, $data );
			if ( ! empty( $extension_summary['detected'] ) ) {
				$data['pressark_extension'] = $extension_summary;
			}
		}
		return $data;
	}

	/**
	 * Get a plugin's display name from its file path.
	 */
	private function get_plugin_name( string $file ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all = get_plugins();
		return $all[ $file ]['Name'] ?? $file;
	}

	/**
	 * Render a concise failure message for invalid extension manifests.
	 *
	 * @param array<string,mixed> $report Normalized manifest report.
	 */
	private function manifest_block_message( array $report ): string {
		$name    = sanitize_text_field( (string) ( $report['plugin_name'] ?? $report['plugin_file'] ?? __( 'extension', 'pressark' ) ) );
		$issue   = sanitize_text_field( (string) ( $report['errors'][0] ?? __( 'Manifest validation failed.', 'pressark' ) ) );
		$warning = sanitize_text_field( (string) ( $report['trust_warning'] ?? '' ) );

		$message = sprintf(
			/* translators: 1: plugin name 2: first manifest error */
			__( 'Blocked activation for "%1$s": %2$s', 'pressark' ),
			$name,
			$issue
		);

		if ( '' !== $warning ) {
			$message .= ' ' . $warning;
		}

		return $message;
	}
}
