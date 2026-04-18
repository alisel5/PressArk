<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tool-session continuity state.
 *
 * Stage 3 compatibility note:
 * The legacy checkpoint snapshot only persists loaded groups, bundle ids, and
 * read continuity. Loaded tool names and discovery/load counters are tracked
 * here as runtime-only metadata until a later wire-format stage adopts them.
 */
class PressArk_Tool_Session_State_Store {

	private array $loaded_tool_groups    = array();
	private array $bundle_ids            = array();
	private array $read_state            = array();
	private array $read_invalidation_log = array();

	private array $loaded_tools     = array();
	private int   $discover_calls   = 0;
	private int   $load_calls       = 0;
	private array $continuity_state = array();

	public static function from_checkpoint_array( array $data ): self {
		$store                     = new self();
		$store->loaded_tool_groups = self::sanitize_loaded_tool_groups( $data['loaded_tool_groups'] ?? array() );
		$store->bundle_ids         = self::sanitize_bundle_ids( $data['bundle_ids'] ?? array() );
		$store->read_state         = class_exists( 'PressArk_Read_Metadata' )
			? PressArk_Read_Metadata::sanitize_snapshot_collection( $data['read_state'] ?? array() )
			: array();
		$store->read_invalidation_log = class_exists( 'PressArk_Read_Metadata' )
			? PressArk_Read_Metadata::sanitize_invalidation_log( $data['read_invalidation_log'] ?? array() )
			: array();

		return $store;
	}

	public function to_checkpoint_array(): array {
		return array(
			'loaded_tool_groups'    => $this->loaded_tool_groups,
			'bundle_ids'            => $this->bundle_ids,
			'read_state'            => $this->read_state,
			'read_invalidation_log' => $this->read_invalidation_log,
		);
	}

	public function is_empty(): bool {
		return empty( $this->loaded_tool_groups )
			&& empty( $this->bundle_ids )
			&& empty( $this->read_state )
			&& empty( $this->read_invalidation_log );
	}

	public function set_loaded_tool_groups( array $groups ): void {
		$this->loaded_tool_groups = self::sanitize_loaded_tool_groups( $groups );
	}

	public function get_loaded_tool_groups(): array {
		return $this->loaded_tool_groups;
	}

	public function add_bundle_id( string $bundle_id ): void {
		$bundle_id = sanitize_text_field( $bundle_id );
		if ( '' === $bundle_id || in_array( $bundle_id, $this->bundle_ids, true ) ) {
			return;
		}

		$this->bundle_ids[] = $bundle_id;
	}

	public function merge_bundle_ids( array $bundle_ids ): void {
		foreach ( $bundle_ids as $bundle_id ) {
			$this->add_bundle_id( (string) $bundle_id );
		}
	}

	public function remove_oldest_bundle_id(): ?string {
		$evicted = array_shift( $this->bundle_ids );
		return is_string( $evicted ) && '' !== $evicted ? $evicted : null;
	}

	public function get_bundle_ids(): array {
		return $this->bundle_ids;
	}

	public function has_bundle( string $bundle_id ): bool {
		return in_array( $bundle_id, $this->bundle_ids, true );
	}

	public function get_read_state(): array {
		return $this->read_state;
	}

	public function get_read_invalidation_log(): array {
		return $this->read_invalidation_log;
	}

	public function record_read_snapshot( array $snapshot ): void {
		if ( ! class_exists( 'PressArk_Read_Metadata' ) ) {
			return;
		}

		$clean = PressArk_Read_Metadata::sanitize_snapshot( $snapshot );
		if ( empty( $clean['handle'] ) ) {
			return;
		}

		$items            = PressArk_Read_Metadata::sanitize_snapshot_collection(
			array_merge( $this->read_state, array( $clean ) )
		);
		$this->read_state = $items;
	}

	public function set_read_state( array $read_state ): void {
		$this->read_state = class_exists( 'PressArk_Read_Metadata' )
			? PressArk_Read_Metadata::sanitize_snapshot_collection( $read_state )
			: array();
	}

	public function set_read_invalidation_log( array $log ): void {
		$this->read_invalidation_log = class_exists( 'PressArk_Read_Metadata' )
			? PressArk_Read_Metadata::sanitize_invalidation_log( $log )
			: array();
	}

	public function apply_write_invalidation( string $tool_name, array $args, array $result ): void {
		if ( ! class_exists( 'PressArk_Read_Metadata' ) ) {
			return;
		}

		$descriptor = PressArk_Read_Metadata::build_invalidation_from_write( $tool_name, $args, $result );
		if ( empty( $descriptor['id'] ) ) {
			return;
		}

		$applied                     = PressArk_Read_Metadata::apply_invalidation( $this->read_state, $descriptor );
		$this->read_state            = $applied['snapshots'] ?? array();
		$this->read_invalidation_log = PressArk_Read_Metadata::sanitize_invalidation_log(
			array_merge( $this->read_invalidation_log, array( $applied['invalidation'] ?? array() ) )
		);
		if ( class_exists( 'PressArk_Resource_Registry' ) ) {
			PressArk_Resource_Registry::apply_invalidation( $applied['invalidation'] ?? array() );
		}
	}

	public function set_runtime_tool_session_state( array $state ): void {
		$this->loaded_tools = array_values( array_filter( array_map(
			'sanitize_key',
			(array) ( $state['loaded_tools'] ?? array() )
		) ) );
		$this->discover_calls = max( 0, (int) ( $state['discover_calls'] ?? 0 ) );
		$this->load_calls     = max( 0, (int) ( $state['load_calls'] ?? 0 ) );
		$this->continuity_state = self::sanitize_runtime_continuity_state( $state['continuity_state'] ?? array() );
	}

	public function get_runtime_tool_session_state(): array {
		return array_filter( array(
			'loaded_tools'    => $this->loaded_tools,
			'discover_calls'  => $this->discover_calls,
			'load_calls'      => $this->load_calls,
			'continuity_state'=> $this->continuity_state,
		), static function ( $value ) {
			return ! ( is_array( $value ) ? empty( $value ) : 0 === (int) $value );
		} );
	}

	private static function sanitize_loaded_tool_groups( array $groups ): array {
		$clean = array();
		foreach ( array_slice( $groups, 0, 15 ) as $group ) {
			$group = sanitize_text_field( (string) $group );
			if ( '' !== $group && ! in_array( $group, $clean, true ) ) {
				$clean[] = $group;
			}
		}

		return $clean;
	}

	private static function sanitize_bundle_ids( array $bundle_ids ): array {
		$clean = array();
		foreach ( array_slice( $bundle_ids, 0, 20 ) as $bundle_id ) {
			$bundle_id = sanitize_text_field( (string) $bundle_id );
			if ( '' !== $bundle_id && ! in_array( $bundle_id, $clean, true ) ) {
				$clean[] = $bundle_id;
			}
		}

		return $clean;
	}

	private static function sanitize_runtime_continuity_state( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}

			if ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = is_numeric( $value ) ? $value + 0 : sanitize_text_field( (string) $value );
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
			}
		}

		return $clean;
	}
}
