<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conversation-scoped checkpoint state.
 *
 * Stage 3 compatibility note:
 * This store owns the legacy conversation-memory fields, but exports them
 * back to the historic flat checkpoint shape so existing callers keep working.
 */
class PressArk_Conversation_Checkpoint_Store {

	private string $goal            = '';
	private array  $entities        = array();
	private array  $facts           = array();
	private array  $pending         = array();
	private array  $constraints     = array();
	private array  $outcomes        = array();
	private array  $retrieval       = array();
	private array  $context_capsule = array();

	public static function from_checkpoint_array( array $data ): self {
		$store                 = new self();
		$store->goal           = sanitize_text_field( $data['goal'] ?? '' );
		$store->entities       = self::sanitize_entities( $data['entities'] ?? array() );
		$store->facts          = self::sanitize_key_value_pairs( $data['facts'] ?? array() );
		$store->pending        = self::sanitize_pending( $data['pending'] ?? array() );
		$store->constraints    = array_map( 'sanitize_text_field', array_slice( $data['constraints'] ?? array(), 0, 20 ) );
		$store->outcomes       = self::sanitize_outcomes( $data['outcomes'] ?? array() );
		$store->retrieval      = self::sanitize_retrieval( $data['retrieval'] ?? array() );
		$store->context_capsule = self::sanitize_context_capsule( $data['context_capsule'] ?? array() );

		if ( self::retrieval_is_empty( $store->retrieval ) ) {
			$store->retrieval = array();
		}

		return $store;
	}

	public function to_checkpoint_array(): array {
		return array(
			'goal'            => $this->goal,
			'entities'        => $this->entities,
			'facts'           => $this->facts,
			'pending'         => $this->pending,
			'constraints'     => $this->constraints,
			'outcomes'        => $this->outcomes,
			'retrieval'       => $this->retrieval,
			'context_capsule' => $this->context_capsule,
		);
	}

	public function is_empty(): bool {
		return '' === $this->goal
			&& empty( $this->entities )
			&& empty( $this->facts )
			&& empty( $this->pending )
			&& empty( $this->constraints )
			&& empty( $this->outcomes )
			&& self::retrieval_is_empty( $this->retrieval )
			&& empty( $this->context_capsule );
	}

	public function set_goal( string $goal ): void {
		$this->goal = sanitize_text_field( $goal );
	}

	public function get_goal(): string {
		return $this->goal;
	}

	public function add_entity( int $id, string $title, string $type ): void {
		foreach ( $this->entities as $entity ) {
			if ( (int) ( $entity['id'] ?? 0 ) === $id ) {
				return;
			}
		}

		$this->entities[] = array(
			'id'    => $id,
			'title' => sanitize_text_field( $title ),
			'type'  => sanitize_text_field( $type ),
		);
	}

	public function get_entities(): array {
		return $this->entities;
	}

	public function add_fact( string $key, string $value ): void {
		foreach ( $this->facts as &$fact ) {
			if ( ( $fact['key'] ?? '' ) === $key ) {
				$fact['value'] = sanitize_text_field( $value );
				return;
			}
		}
		unset( $fact );

		$this->facts[] = array(
			'key'   => sanitize_text_field( $key ),
			'value' => sanitize_text_field( $value ),
		);
	}

	public function get_facts(): array {
		return $this->facts;
	}

	public function add_pending( string $action, string $target, string $detail = '' ): void {
		$this->pending[] = array(
			'action' => sanitize_text_field( $action ),
			'target' => sanitize_text_field( $target ),
			'detail' => sanitize_text_field( $detail ),
		);
	}

	public function clear_pending(): void {
		$this->pending = array();
	}

	public function get_pending(): array {
		return $this->pending;
	}

	public function has_unapplied_confirms(): bool {
		foreach ( $this->pending as $entry ) {
			if ( str_contains( (string) ( $entry['detail'] ?? '' ), 'NOT YET APPLIED' ) ) {
				return true;
			}
		}

		return false;
	}

	public function add_constraint( string $constraint ): void {
		$this->constraints[] = sanitize_text_field( $constraint );
	}

	public function get_constraints(): array {
		return $this->constraints;
	}

	public function add_outcome( string $action, string $result ): void {
		$this->outcomes[] = array(
			'action' => sanitize_text_field( $action ),
			'result' => sanitize_text_field( $result ),
		);
	}

	public function get_outcomes(): array {
		return $this->outcomes;
	}

	public function set_retrieval( array $retrieval ): void {
		$retrieval       = self::sanitize_retrieval( $retrieval );
		$this->retrieval = self::retrieval_is_empty( $retrieval ) ? array() : $retrieval;
	}

	public function get_retrieval(): array {
		return $this->retrieval;
	}

	public function set_context_capsule( array $capsule ): void {
		$this->context_capsule = self::sanitize_context_capsule( $capsule );
	}

	public function get_context_capsule(): array {
		return $this->context_capsule;
	}

	public function clear_context_capsule(): void {
		$this->context_capsule = array();
	}

	private static function sanitize_entities( array $raw ): array {
		$clean = array();
		foreach ( array_slice( $raw, 0, 50 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$clean[] = array(
				'id'    => absint( $item['id'] ?? 0 ),
				'title' => sanitize_text_field( $item['title'] ?? '' ),
				'type'  => sanitize_text_field( $item['type'] ?? '' ),
			);
		}

		return $clean;
	}

	private static function sanitize_key_value_pairs( array $raw ): array {
		$clean = array();
		foreach ( array_slice( $raw, 0, 50 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$clean[] = array(
				'key'   => sanitize_text_field( $item['key'] ?? '' ),
				'value' => sanitize_text_field( $item['value'] ?? '' ),
			);
		}

		return $clean;
	}

	private static function sanitize_pending( array $raw ): array {
		$clean = array();
		foreach ( array_slice( $raw, 0, 20 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$clean[] = array(
				'action' => sanitize_text_field( $item['action'] ?? '' ),
				'target' => sanitize_text_field( $item['target'] ?? '' ),
				'detail' => sanitize_text_field( $item['detail'] ?? '' ),
			);
		}

		return $clean;
	}

	private static function sanitize_outcomes( array $raw ): array {
		$clean = array();
		foreach ( array_slice( $raw, 0, 20 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$clean[] = array(
				'action' => sanitize_text_field( $item['action'] ?? '' ),
				'result' => sanitize_text_field( $item['result'] ?? '' ),
			);
		}

		return $clean;
	}

	private static function sanitize_context_capsule( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array(
			'task'                => sanitize_text_field( (string) ( $raw['task'] ?? '' ) ),
			'active_request'      => sanitize_text_field( (string) ( $raw['active_request'] ?? '' ) ),
			'historical_requests' => array_values( array_filter( array_map(
				'sanitize_text_field',
				array_slice( (array) ( $raw['historical_requests'] ?? array() ), 0, 3 )
			) ) ),
			'target'              => sanitize_text_field( (string) ( $raw['target'] ?? '' ) ),
			'summary'             => sanitize_text_field( (string) ( $raw['summary'] ?? '' ) ),
			'completed'           => array_values( array_filter( array_map(
				'sanitize_text_field',
				array_slice( (array) ( $raw['completed'] ?? array() ), 0, 6 )
			) ) ),
			'remaining'           => array_values( array_filter( array_map(
				'sanitize_text_field',
				array_slice( (array) ( $raw['remaining'] ?? array() ), 0, 6 )
			) ) ),
			'recent_receipts'     => array_values( array_filter( array_map(
				'sanitize_text_field',
				array_slice( (array) ( $raw['recent_receipts'] ?? array() ), 0, 6 )
			) ) ),
			'loaded_groups'       => array_values( array_filter( array_map(
				'sanitize_text_field',
				array_slice( (array) ( $raw['loaded_groups'] ?? array() ), 0, 8 )
			) ) ),
			'ai_decisions'        => array_values( array_filter( array_map(
				'sanitize_text_field',
				array_slice( (array) ( $raw['ai_decisions'] ?? array() ), 0, 5 )
			) ) ),
			'created_post_ids'    => array_values( array_filter( array_map(
				'absint',
				array_slice( (array) ( $raw['created_post_ids'] ?? array() ), 0, 5 )
			) ) ),
			'preserved_details'   => array_values( array_filter( array_map(
				'sanitize_text_field',
				array_slice( (array) ( $raw['preserved_details'] ?? array() ), 0, 8 )
			) ) ),
			'scope'               => array_values( array_filter( array_map(
				'sanitize_text_field',
				array_slice( (array) ( $raw['scope'] ?? array() ), 0, 6 )
			) ) ),
			'compression_model'   => sanitize_text_field( (string) ( $raw['compression_model'] ?? '' ) ),
			'compaction'          => self::sanitize_context_capsule_compaction( $raw['compaction'] ?? array() ),
			'updated_at'          => sanitize_text_field( (string) ( $raw['updated_at'] ?? '' ) ),
		);

		return array_filter(
			$clean,
			static function ( $value ) {
				return ! ( is_array( $value ) ? empty( $value ) : '' === (string) $value );
			}
		);
	}

	private static function sanitize_context_capsule_compaction( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array(
			'count'                   => max( 0, (int) ( $raw['count'] ?? 0 ) ),
			'last_marker'             => sanitize_key( (string) ( $raw['last_marker'] ?? '' ) ),
			'last_reason'             => sanitize_key( (string) ( $raw['last_reason'] ?? '' ) ),
			'last_round'              => absint( $raw['last_round'] ?? 0 ),
			'last_at'                 => sanitize_text_field( (string) ( $raw['last_at'] ?? '' ) ),
			'last_event'              => self::sanitize_compaction_event( $raw['last_event'] ?? array() ),
			'pending_post_compaction' => self::sanitize_compaction_pending( $raw['pending_post_compaction'] ?? array() ),
			'first_post_compaction'   => self::sanitize_compaction_observation( $raw['first_post_compaction'] ?? array() ),
		);

		return array_filter(
			$clean,
			static function ( $value, $key ) {
				if ( 'count' === $key ) {
					return $value > 0;
				}
				return ! ( is_array( $value ) ? empty( $value ) : '' === (string) $value );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	private static function sanitize_compaction_event( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_filter( array(
			'marker'                  => sanitize_key( (string) ( $raw['marker'] ?? '' ) ),
			'reason'                  => sanitize_key( (string) ( $raw['reason'] ?? '' ) ),
			'round'                   => absint( $raw['round'] ?? 0 ),
			'before_messages'         => max( 0, (int) ( $raw['before_messages'] ?? 0 ) ),
			'after_messages'          => max( 0, (int) ( $raw['after_messages'] ?? 0 ) ),
			'dropped_messages'        => max( 0, (int) ( $raw['dropped_messages'] ?? 0 ) ),
			'estimated_tokens_before' => max( 0, (int) ( $raw['estimated_tokens_before'] ?? 0 ) ),
			'estimated_tokens_after'  => max( 0, (int) ( $raw['estimated_tokens_after'] ?? 0 ) ),
			'remaining_tokens'        => max( 0, (int) ( $raw['remaining_tokens'] ?? 0 ) ),
			'context_pressure'        => sanitize_key( (string) ( $raw['context_pressure'] ?? '' ) ),
			'summary_mode'            => sanitize_key( (string) ( $raw['summary_mode'] ?? '' ) ),
			'at'                      => sanitize_text_field( (string) ( $raw['at'] ?? '' ) ),
		), static function ( $value ) {
			return ! ( is_int( $value ) ? 0 === $value : '' === (string) $value );
		} );
	}

	private static function sanitize_compaction_pending( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_filter( array(
			'marker' => sanitize_key( (string) ( $raw['marker'] ?? '' ) ),
			'reason' => sanitize_key( (string) ( $raw['reason'] ?? '' ) ),
			'round'  => absint( $raw['round'] ?? 0 ),
			'at'     => sanitize_text_field( (string) ( $raw['at'] ?? '' ) ),
		), static function ( $value ) {
			return ! ( is_int( $value ) ? 0 === $value : '' === (string) $value );
		} );
	}

	private static function sanitize_compaction_observation( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_filter( array(
			'marker'           => sanitize_key( (string) ( $raw['marker'] ?? '' ) ),
			'reason'           => sanitize_key( (string) ( $raw['reason'] ?? '' ) ),
			'observed_round'   => absint( $raw['observed_round'] ?? 0 ),
			'stop_reason'      => sanitize_key( (string) ( $raw['stop_reason'] ?? '' ) ),
			'tool_calls'       => max( 0, (int) ( $raw['tool_calls'] ?? 0 ) ),
			'had_text'         => ! empty( $raw['had_text'] ),
			'healthy'          => ! empty( $raw['healthy'] ),
			'remaining_tokens' => max( 0, (int) ( $raw['remaining_tokens'] ?? 0 ) ),
			'context_pressure' => sanitize_key( (string) ( $raw['context_pressure'] ?? '' ) ),
			'at'               => sanitize_text_field( (string) ( $raw['at'] ?? '' ) ),
		), static function ( $value ) {
			if ( is_bool( $value ) ) {
				return true;
			}
			return ! ( is_int( $value ) ? 0 === $value : '' === (string) $value );
		} );
	}

	private static function sanitize_retrieval( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$source_ids = array();
		foreach ( array_slice( $raw['source_ids'] ?? array(), 0, 10 ) as $id ) {
			$source_ids[] = absint( $id );
		}
		$source_ids = array_values( array_filter( $source_ids ) );

		$source_titles = array();
		foreach ( array_slice( $raw['source_titles'] ?? array(), 0, 5 ) as $title ) {
			$title = sanitize_text_field( (string) $title );
			if ( '' !== $title ) {
				$source_titles[] = $title;
			}
		}

		$clean = array(
			'kind'          => sanitize_text_field( $raw['kind'] ?? '' ),
			'query'         => sanitize_text_field( $raw['query'] ?? '' ),
			'count'         => absint( $raw['count'] ?? count( $source_ids ) ),
			'source_ids'    => $source_ids,
			'source_titles' => $source_titles,
			'updated_at'    => sanitize_text_field( $raw['updated_at'] ?? '' ),
		);

		return array_filter(
			$clean,
			static function ( $value ) {
				return ! ( is_array( $value ) ? empty( $value ) : '' === (string) $value );
			}
		);
	}

	private static function retrieval_is_empty( array $retrieval ): bool {
		return empty( $retrieval['kind'] ?? '' )
			&& empty( $retrieval['query'] ?? '' )
			&& empty( absint( $retrieval['count'] ?? 0 ) )
			&& empty( $retrieval['source_ids'] ?? array() )
			&& empty( $retrieval['source_titles'] ?? array() )
			&& empty( $retrieval['updated_at'] ?? '' );
	}
}
