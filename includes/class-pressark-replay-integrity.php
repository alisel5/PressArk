<?php
/**
 * Replay integrity helpers for transcript repair, round grouping, and sidecars.
 *
 * @package PressArk
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Replay_Integrity {

	private const MAX_REPLAY_MESSAGES    = 28;
	private const MAX_REPLAY_EVENTS      = 16;
	private const MAX_REPLACEMENT_ENTRIES = 32;
	private const MAX_CONTENT_CHARS      = 50000;
	private const MAX_STATE_TAIL_ROUNDS  = 4;
	private const MISSING_TOOL_RESULT_MESSAGE = '[Tool result missing during replay repair.]';

	/**
	 * Canonicalize and sanitize replay state loaded from storage.
	 */
	public static function sanitize_state( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$messages = self::sanitize_messages( (array) ( $raw['messages'] ?? array() ) );
		$journal  = self::sanitize_replacement_journal( (array) ( $raw['replacement_journal'] ?? array() ) );
		$events   = self::sanitize_event_list( (array) ( $raw['events'] ?? array() ) );
		$resume   = self::sanitize_event( $raw['last_resume'] ?? array(), 'resume' );
		$updated  = self::sanitize_string( (string) ( $raw['updated_at'] ?? '' ) );

		$state = array(
			'messages'            => $messages,
			'replacement_journal' => $journal,
			'events'              => $events,
			'last_resume'         => $resume,
			'updated_at'          => $updated,
		);

		return array_filter(
			$state,
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return ! empty( $value );
				}
				return '' !== (string) $value;
			}
		);
	}

	/**
	 * Merge server-owned replay state with a client mirror.
	 */
	public static function merge_state( array $server, array $client ): array {
		$server = self::sanitize_state( $server );
		$client = self::sanitize_state( $client );

		if ( empty( $server ) ) {
			return $client;
		}
		if ( empty( $client ) ) {
			return $server;
		}

		$client_is_newer = self::timestamp_value( $client['updated_at'] ?? '' ) >= self::timestamp_value( $server['updated_at'] ?? '' );
		$newer           = $client_is_newer ? $client : $server;
		$older           = $client_is_newer ? $server : $client;

		$merged = $newer;

		if ( empty( $merged['messages'] ) && ! empty( $older['messages'] ) ) {
			$merged['messages'] = $older['messages'];
		}

		$merged['replacement_journal'] = self::merge_replacement_lists(
			(array) ( $newer['replacement_journal'] ?? array() ),
			(array) ( $older['replacement_journal'] ?? array() )
		);
		$merged['events'] = self::merge_event_lists(
			(array) ( $newer['events'] ?? array() ),
			(array) ( $older['events'] ?? array() )
		);

		$newer_resume_ts = self::timestamp_value( $newer['last_resume']['at'] ?? '' );
		$older_resume_ts = self::timestamp_value( $older['last_resume']['at'] ?? '' );
		if ( empty( $newer['last_resume'] ) || $older_resume_ts > $newer_resume_ts ) {
			$merged['last_resume'] = $older['last_resume'] ?? array();
		}

		if ( empty( $merged['updated_at'] ) ) {
			$merged['updated_at'] = $older['updated_at'] ?? '';
		}

		return self::sanitize_state( $merged );
	}

	/**
	 * Build a compact debug sidecar for responses and tests.
	 */
	public static function debug_sidecar( array $state ): array {
		$state = self::sanitize_state( $state );
		if ( empty( $state ) ) {
			return array();
		}

		$last_event = array();
		if ( ! empty( $state['events'] ) ) {
			$last_event = (array) end( $state['events'] );
			reset( $state['events'] );
		}

		return array_filter(
			array(
				'message_count'     => count( (array) ( $state['messages'] ?? array() ) ),
				'replacement_count' => count( (array) ( $state['replacement_journal'] ?? array() ) ),
				'event_count'       => count( (array) ( $state['events'] ?? array() ) ),
				'last_event'        => $last_event,
				'last_resume'       => (array) ( $state['last_resume'] ?? array() ),
			),
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return ! empty( $value );
				}
				return null !== $value && 0 !== $value;
			}
		);
	}

	/**
	 * Canonicalize and sanitize transcript messages for storage.
	 *
	 * @param array $messages Raw messages array.
	 * @return array[]
	 */
	public static function sanitize_messages( array $messages ): array {
		$canonical = self::canonicalize_messages( $messages );
		return array_slice( $canonical, -self::MAX_REPLAY_MESSAGES );
	}

	/**
	 * Canonicalize a live transcript without applying storage caps.
	 *
	 * Canonical transcript form:
	 * - assistant tool calls are OpenAI-style assistant messages with tool_calls
	 * - tool results are role=tool messages
	 * - assistant text content is plain text or null
	 */
	public static function canonicalize_messages( array $messages ): array {
		$canonical = array();

		foreach ( $messages as $message ) {
			foreach ( self::canonicalize_message( $message ) as $piece ) {
				if ( is_array( $piece ) && ! empty( $piece['role'] ) ) {
					$canonical[] = $piece;
				}
			}
		}

		return $canonical;
	}

	/**
	 * Repair assistant/tool/result invariants before a provider call or resume.
	 *
	 * @return array{messages:array,event:array,changed:bool}
	 */
	public static function repair_messages( array $messages, string $phase = 'provider_call' ): array {
		$canonical = self::canonicalize_messages( $messages );
		$repaired  = array();
		$pending   = null;

		$dropped_orphans           = 0;
		$dropped_dupes             = 0;
		$dropped_incomplete_rounds = 0;
		$dropped_partial_results   = 0;

		foreach ( $canonical as $message ) {
			if ( self::assistant_has_tool_calls( $message ) ) {
				if ( null !== $pending ) {
					$discarded = self::discard_pending_tool_round( $pending );
					$dropped_incomplete_rounds += (int) ( $discarded['rounds'] ?? 0 );
					$dropped_partial_results   += (int) ( $discarded['results'] ?? 0 );
				}

				$pending = array(
					'assistant' => $message,
					'ids'       => self::assistant_tool_call_ids( $message ),
					'results'   => array(),
				);
				continue;
			}

			if ( null !== $pending && 'tool' === ( $message['role'] ?? '' ) ) {
				$tool_call_id = (string) ( $message['tool_call_id'] ?? '' );
				if ( '' === $tool_call_id || ! in_array( $tool_call_id, $pending['ids'], true ) ) {
					$dropped_orphans++;
					continue;
				}
				if ( isset( $pending['results'][ $tool_call_id ] ) ) {
					$dropped_dupes++;
					continue;
				}

				$pending['results'][ $tool_call_id ] = $message;
				if ( count( $pending['results'] ) >= count( $pending['ids'] ) ) {
					self::flush_pending_tool_round( $repaired, $pending );
					$pending = null;
				}
				continue;
			}

			if ( null !== $pending ) {
				$discarded = self::discard_pending_tool_round( $pending );
				$dropped_incomplete_rounds += (int) ( $discarded['rounds'] ?? 0 );
				$dropped_partial_results   += (int) ( $discarded['results'] ?? 0 );
			}

			if ( 'tool' === ( $message['role'] ?? '' ) ) {
				$dropped_orphans++;
				continue;
			}

			$repaired[] = $message;
		}

		if ( null !== $pending ) {
			$discarded = self::discard_pending_tool_round( $pending );
			$dropped_incomplete_rounds += (int) ( $discarded['rounds'] ?? 0 );
			$dropped_partial_results   += (int) ( $discarded['results'] ?? 0 );
		}

		$changed = $canonical !== $repaired;
		$event   = array();

		if ( $changed ) {
			$event = self::sanitize_event( array(
				'type'                    => 'repair',
				'phase'                   => $phase,
				'message_count_before'    => count( $canonical ),
				'message_count_after'     => count( $repaired ),
				'dropped_orphan_results'  => $dropped_orphans,
				'dropped_duplicate_results'=> $dropped_dupes,
				'dropped_incomplete_rounds' => $dropped_incomplete_rounds,
				'dropped_partial_results' => $dropped_partial_results,
				'at'                      => gmdate( 'c' ),
			), 'repair' );
		}

		return array(
			'messages' => $repaired,
			'event'    => $event,
			'changed'  => $changed,
		);
	}

	/**
	 * Choose a compaction window that preserves full assistant API rounds.
	 *
	 * @return array{
	 *   start_index:int,
	 *   recent_messages:array,
	 *   dropped_messages:array,
	 *   dropped_rounds:int,
	 *   kept_rounds:int,
	 *   used_rounds:bool
	 * }
	 */
	public static function select_round_compaction_window( array $messages, int $keep_rounds = 2, int $fallback_tail = 4 ): array {
		$canonical = self::canonicalize_messages( $messages );
		$total     = count( $canonical );
		$keep_rounds = max( 1, $keep_rounds );
		$fallback_tail = max( 2, $fallback_tail );

		if ( $total <= 1 ) {
			return array(
				'start_index'     => $total,
				'recent_messages' => array(),
				'dropped_messages'=> array(),
				'dropped_rounds'  => 0,
				'kept_rounds'     => 0,
				'used_rounds'     => false,
			);
		}

		$assistant_starts = array();
		foreach ( $canonical as $index => $message ) {
			if ( 'assistant' === ( $message['role'] ?? '' ) ) {
				$assistant_starts[] = $index;
			}
		}

		$used_rounds   = ! empty( $assistant_starts );
		$total_rounds  = count( $assistant_starts );
		$kept_rounds   = 0;
		$start_index   = max( 1, $total - $fallback_tail );

		if ( $used_rounds ) {
			$round_offset = max( 0, $total_rounds - $keep_rounds );
			$start_index  = max( 1, (int) $assistant_starts[ $round_offset ] );
			$kept_rounds  = min( $keep_rounds, $total_rounds );
		}

		return array(
			'start_index'      => $start_index,
			'recent_messages'  => array_slice( $canonical, $start_index ),
			'dropped_messages' => array_slice( $canonical, 1, max( 0, $start_index - 1 ) ),
			'dropped_rounds'   => $used_rounds ? max( 0, $total_rounds - $kept_rounds ) : 0,
			'kept_rounds'      => $kept_rounds,
			'used_rounds'      => $used_rounds,
		);
	}

	/**
	 * Build a bounded transcript snapshot for checkpoint persistence.
	 *
	 * @return array{messages:array,trimmed:bool,dropped_messages:int,dropped_rounds:int}
	 */
	public static function snapshot_messages( array $messages ): array {
		$canonical = self::repair_messages( $messages, 'snapshot' )['messages'];
		if ( count( $canonical ) <= self::MAX_REPLAY_MESSAGES ) {
			return array(
				'messages'         => array_slice( $canonical, -self::MAX_REPLAY_MESSAGES ),
				'trimmed'          => false,
				'dropped_messages' => 0,
				'dropped_rounds'   => 0,
			);
		}

		$window  = self::select_round_compaction_window( $canonical, self::MAX_STATE_TAIL_ROUNDS, self::MAX_REPLAY_MESSAGES - 1 );
		$anchor  = array_slice( $canonical, 0, 1 );
		$saved   = array_merge( $anchor, $window['recent_messages'] );

		if ( count( $saved ) > self::MAX_REPLAY_MESSAGES ) {
			$saved = array_merge(
				array_slice( $saved, 0, 1 ),
				array_slice( $saved, -( self::MAX_REPLAY_MESSAGES - 1 ) )
			);
		}

		return array(
			'messages'         => array_slice( $saved, -self::MAX_REPLAY_MESSAGES ),
			'trimmed'          => true,
			'dropped_messages' => count( (array) $window['dropped_messages'] ),
			'dropped_rounds'   => (int) ( $window['dropped_rounds'] ?? 0 ),
		);
	}

	/**
	 * Normalize a replay event for storage.
	 */
	public static function sanitize_event( $raw, string $default_type = '' ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$event = array(
			'type'                     => sanitize_key( (string) ( $raw['type'] ?? $default_type ) ),
			'phase'                    => sanitize_key( (string) ( $raw['phase'] ?? '' ) ),
			'source'                   => sanitize_key( (string) ( $raw['source'] ?? '' ) ),
			'reason'                   => sanitize_key( (string) ( $raw['reason'] ?? '' ) ),
			'round'                    => max( 0, (int) ( $raw['round'] ?? 0 ) ),
			'message_count_before'     => max( 0, (int) ( $raw['message_count_before'] ?? 0 ) ),
			'message_count_after'      => max( 0, (int) ( $raw['message_count_after'] ?? 0 ) ),
			'inserted_missing_results' => max( 0, (int) ( $raw['inserted_missing_results'] ?? 0 ) ),
			'dropped_orphan_results'   => max( 0, (int) ( $raw['dropped_orphan_results'] ?? 0 ) ),
			'dropped_duplicate_results'=> max( 0, (int) ( $raw['dropped_duplicate_results'] ?? 0 ) ),
			'dropped_incomplete_rounds'=> max( 0, (int) ( $raw['dropped_incomplete_rounds'] ?? 0 ) ),
			'dropped_partial_results'  => max( 0, (int) ( $raw['dropped_partial_results'] ?? 0 ) ),
			'dropped_messages'         => max( 0, (int) ( $raw['dropped_messages'] ?? 0 ) ),
			'dropped_rounds'           => max( 0, (int) ( $raw['dropped_rounds'] ?? 0 ) ),
			'kept_rounds'              => max( 0, (int) ( $raw['kept_rounds'] ?? 0 ) ),
			'used_checkpoint_replay'   => ! empty( $raw['used_checkpoint_replay'] ),
			'repaired'                 => ! empty( $raw['repaired'] ),
			'tool_use_id'              => self::sanitize_string( (string) ( $raw['tool_use_id'] ?? '' ) ),
			'tool_name'                => sanitize_key( (string) ( $raw['tool_name'] ?? '' ) ),
			'inline_tokens'            => max( 0, (int) ( $raw['inline_tokens'] ?? 0 ) ),
			'artifact_uri'             => self::sanitize_string( (string) ( $raw['artifact_uri'] ?? '' ) ),
			'mode'                     => sanitize_key( (string) ( $raw['mode'] ?? '' ) ),
			'at'                       => self::sanitize_string( (string) ( $raw['at'] ?? '' ) ),
		);

		return array_filter(
			$event,
			static function ( $value ) {
				if ( is_bool( $value ) ) {
					return true;
				}
				if ( is_int( $value ) ) {
					return 0 !== $value;
				}
				return '' !== (string) $value;
			}
		);
	}

	/**
	 * Normalize a replacement journal entry for storage.
	 */
	public static function sanitize_replacement_entry( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$entry = array(
			'tool_use_id'   => self::sanitize_string( (string) ( $raw['tool_use_id'] ?? '' ) ),
			'tool_name'     => sanitize_key( (string) ( $raw['tool_name'] ?? '' ) ),
			'result_hash'   => sanitize_key( (string) ( $raw['result_hash'] ?? '' ) ),
			'artifact_uri'  => self::sanitize_string( (string) ( $raw['artifact_uri'] ?? '' ) ),
			'reason'        => sanitize_key( (string) ( $raw['reason'] ?? '' ) ),
			'round'         => max( 0, (int) ( $raw['round'] ?? 0 ) ),
			'inline_tokens' => max( 0, (int) ( $raw['inline_tokens'] ?? 0 ) ),
			'stored_at'     => self::sanitize_string( (string) ( $raw['stored_at'] ?? '' ) ),
			'replacement'   => self::sanitize_value( $raw['replacement'] ?? array(), 0 ),
		);

		return array_filter(
			$entry,
			static function ( $value ) {
				if ( is_int( $value ) ) {
					return 0 !== $value;
				}
				if ( is_array( $value ) ) {
					return ! empty( $value );
				}
				return '' !== (string) $value;
			}
		);
	}

	/**
	 * Normalize a replacement journal list.
	 */
	public static function sanitize_replacement_journal( array $entries ): array {
		$clean = array();
		foreach ( array_slice( $entries, -self::MAX_REPLACEMENT_ENTRIES ) as $entry ) {
			$entry = self::sanitize_replacement_entry( $entry );
			if ( empty( $entry['tool_use_id'] ) ) {
				continue;
			}
			$clean[] = $entry;
		}

		return self::merge_replacement_lists( $clean, array() );
	}

	/**
	 * Find a frozen replacement entry by tool-use ID.
	 */
	public static function find_replacement_entry( array $journal, string $tool_use_id ): ?array {
		$tool_use_id = self::sanitize_string( $tool_use_id );
		if ( '' === $tool_use_id ) {
			return null;
		}

		foreach ( self::sanitize_replacement_journal( $journal ) as $entry ) {
			if ( ( $entry['tool_use_id'] ?? '' ) === $tool_use_id ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Compute a stable hash for a tool result payload.
	 */
	public static function result_hash( array $result ): string {
		$json = wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) ) {
			$json = '';
		}
		return sanitize_key( md5( $json ) );
	}

	private static function canonicalize_message( $message ): array {
		if ( ! is_array( $message ) ) {
			return array();
		}

		$role = sanitize_key( (string) ( $message['role'] ?? '' ) );
		if ( ! in_array( $role, array( 'user', 'assistant', 'tool' ), true ) ) {
			return array();
		}

		if ( 'tool' === $role ) {
			$tool_call_id = self::sanitize_string( (string) ( $message['tool_call_id'] ?? '' ) );
			if ( '' === $tool_call_id ) {
				return array();
			}

			if ( self::is_replay_placeholder_tool_content( $message['content'] ?? '' ) ) {
				return array();
			}

			$content = self::normalize_tool_content( $message['content'] ?? '' );
			return array(
				array(
					'role'         => 'tool',
					'tool_call_id' => $tool_call_id,
					'content'      => $content,
				),
			);
		}

		if ( 'assistant' === $role ) {
			$canonical = self::canonicalize_assistant_message( $message );
			return $canonical ? array( $canonical ) : array();
		}

		$content = $message['content'] ?? '';
		if ( self::is_anthropic_tool_result_blocks( $content ) ) {
			return self::tool_result_blocks_to_tool_messages( (array) $content );
		}

		return array(
			array(
				'role'    => 'user',
				'content' => self::normalize_text_content( $content ),
			),
		);
	}

	private static function canonicalize_assistant_message( array $message ): ?array {
		$tool_calls = array();
		$text       = null;

		if ( is_array( $message['tool_calls'] ?? null ) ) {
			$tool_calls = self::sanitize_openai_tool_calls( (array) $message['tool_calls'] );
		}

		if ( is_array( $message['content'] ?? null ) ) {
			$text_parts = array();
			foreach ( (array) $message['content'] as $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}

				$type = sanitize_key( (string) ( $block['type'] ?? '' ) );
				if ( 'text' === $type ) {
					$text_parts[] = self::sanitize_string( (string) ( $block['text'] ?? '' ) );
					continue;
				}

				if ( 'tool_use' === $type ) {
					$tool_name = sanitize_key( (string) ( $block['name'] ?? '' ) );
					$tool_id   = self::sanitize_string( (string) ( $block['id'] ?? '' ) );
					if ( '' === $tool_name || '' === $tool_id ) {
						continue;
					}

					$args = $block['input'] ?? array();
					if ( ! is_array( $args ) ) {
						$args = array();
					}

					$tool_calls[] = array(
						'id'       => $tool_id,
						'type'     => 'function',
						'function' => array(
							'name'      => $tool_name,
							'arguments' => wp_json_encode( $args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
						),
					);
				}
			}
			$text = implode( '', array_filter( $text_parts ) );
		} else {
			$text = self::normalize_text_content( $message['content'] ?? null );
		}

		$assistant = array( 'role' => 'assistant' );
		if ( null !== $text && ( '' !== $text || empty( $tool_calls ) ) ) {
			$assistant['content'] = $text;
		} elseif ( ! empty( $tool_calls ) ) {
			$assistant['content'] = null;
		}
		if ( ! empty( $tool_calls ) ) {
			$assistant['tool_calls'] = $tool_calls;
		}

		return $assistant;
	}

	private static function sanitize_openai_tool_calls( array $tool_calls ): array {
		$clean = array();

		foreach ( $tool_calls as $call ) {
			if ( ! is_array( $call ) ) {
				continue;
			}

			$function = is_array( $call['function'] ?? null ) ? $call['function'] : array();
			$name     = sanitize_key( (string) ( $function['name'] ?? $call['name'] ?? '' ) );
			$id       = self::sanitize_string( (string) ( $call['id'] ?? '' ) );
			if ( '' === $name || '' === $id ) {
				continue;
			}

			$args = $function['arguments'] ?? $call['arguments'] ?? '{}';
			if ( is_array( $args ) ) {
				$args = wp_json_encode( $args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
			if ( ! is_string( $args ) || '' === trim( $args ) ) {
				$args = '{}';
			}

			$clean[] = array(
				'id'       => $id,
				'type'     => 'function',
				'function' => array(
					'name'      => $name,
					'arguments' => self::sanitize_string( $args ),
				),
			);
		}

		return $clean;
	}

	private static function is_anthropic_tool_result_blocks( $content ): bool {
		if ( ! is_array( $content ) || empty( $content ) ) {
			return false;
		}

		foreach ( $content as $block ) {
			if ( ! is_array( $block ) || 'tool_result' !== sanitize_key( (string) ( $block['type'] ?? '' ) ) ) {
				return false;
			}
		}

		return true;
	}

	private static function tool_result_blocks_to_tool_messages( array $blocks ): array {
		$messages = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$tool_use_id = self::sanitize_string( (string) ( $block['tool_use_id'] ?? '' ) );
			if ( '' === $tool_use_id ) {
				continue;
			}

			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $tool_use_id,
				'content'      => self::normalize_tool_content( $block['content'] ?? '' ),
			);
		}

		return $messages;
	}

	private static function assistant_has_tool_calls( array $message ): bool {
		return 'assistant' === ( $message['role'] ?? '' )
			&& ! empty( $message['tool_calls'] )
			&& is_array( $message['tool_calls'] );
	}

	private static function assistant_tool_call_ids( array $message ): array {
		$ids = array();
		foreach ( (array) ( $message['tool_calls'] ?? array() ) as $call ) {
			$id = self::sanitize_string( (string) ( $call['id'] ?? '' ) );
			if ( '' !== $id ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private static function flush_pending_tool_round( array &$messages, ?array &$pending ): void {
		if ( empty( $pending['assistant'] ) || empty( $pending['ids'] ) ) {
			$pending = null;
			return;
		}

		$messages[] = $pending['assistant'];
		foreach ( $pending['ids'] as $tool_call_id ) {
			if ( isset( $pending['results'][ $tool_call_id ] ) ) {
				$messages[] = $pending['results'][ $tool_call_id ];
			}
		}

		$pending = null;
	}

	private static function discard_pending_tool_round( ?array &$pending ): array {
		if ( ! is_array( $pending ) ) {
			return array(
				'rounds'  => 0,
				'results' => 0,
			);
		}

		$discarded = array(
			'rounds'  => empty( $pending['assistant'] ) ? 0 : 1,
			'results' => count( (array) ( $pending['results'] ?? array() ) ),
		);

		$pending = null;
		return $discarded;
	}

	private static function is_replay_placeholder_tool_content( $content ): bool {
		if ( is_string( $content ) ) {
			$decoded = json_decode( $content, true );
		} elseif ( is_array( $content ) ) {
			$decoded = $content;
		} else {
			$decoded = null;
		}

		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['replay_placeholder'] ) ) {
				return true;
			}

			$message = self::sanitize_string( (string) ( $decoded['message'] ?? '' ) );
			if ( self::MISSING_TOOL_RESULT_MESSAGE === $message ) {
				return true;
			}
		}

		return false;
	}

	private static function sanitize_event_list( array $events ): array {
		$clean = array();
		foreach ( array_slice( $events, -self::MAX_REPLAY_EVENTS ) as $event ) {
			$event = self::sanitize_event( $event );
			if ( empty( $event['type'] ) ) {
				continue;
			}
			$clean[] = $event;
		}

		return self::merge_event_lists( $clean, array() );
	}

	private static function merge_event_lists( array $preferred, array $fallback ): array {
		$map = array();

		foreach ( array_merge( $fallback, $preferred ) as $event ) {
			$event = self::sanitize_event( $event );
			if ( empty( $event['type'] ) ) {
				continue;
			}
			$map[ self::event_fingerprint( $event ) ] = $event;
		}

		$events = array_values( $map );
		usort(
			$events,
			static function ( array $left, array $right ): int {
				return self::timestamp_value( $left['at'] ?? '' ) <=> self::timestamp_value( $right['at'] ?? '' );
			}
		);

		return array_slice( $events, -self::MAX_REPLAY_EVENTS );
	}

	private static function merge_replacement_lists( array $preferred, array $fallback ): array {
		$map = array();

		foreach ( array_merge( $fallback, $preferred ) as $entry ) {
			$entry = self::sanitize_replacement_entry( $entry );
			if ( empty( $entry['tool_use_id'] ) ) {
				continue;
			}
			$map[ $entry['tool_use_id'] ] = $entry;
		}

		$entries = array_values( $map );
		usort(
			$entries,
			static function ( array $left, array $right ): int {
				return self::timestamp_value( $left['stored_at'] ?? '' ) <=> self::timestamp_value( $right['stored_at'] ?? '' );
			}
		);

		return array_slice( $entries, -self::MAX_REPLACEMENT_ENTRIES );
	}

	private static function event_fingerprint( array $event ): string {
		$parts = array(
			$event['type'] ?? '',
			$event['phase'] ?? '',
			$event['source'] ?? '',
			$event['reason'] ?? '',
			$event['tool_use_id'] ?? '',
			$event['tool_name'] ?? '',
			$event['at'] ?? '',
		);

		return md5( implode( '|', $parts ) );
	}

	private static function sanitize_value( $value, int $depth = 0 ) {
		if ( $depth > 5 ) {
			return null;
		}

		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( array_slice( $value, 0, 40, true ) as $key => $item ) {
				$clean_key = is_int( $key ) ? $key : sanitize_key( (string) $key );
				if ( '' === (string) $clean_key && ! is_int( $clean_key ) ) {
					continue;
				}
				$clean[ $clean_key ] = self::sanitize_value( $item, $depth + 1 );
			}
			return $clean;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return self::sanitize_string( $value );
		}

		return self::sanitize_string( wp_json_encode( $value ) );
	}

	private static function normalize_text_content( $content ) {
		if ( null === $content ) {
			return null;
		}
		if ( is_string( $content ) ) {
			return self::sanitize_string( $content );
		}
		if ( is_scalar( $content ) ) {
			return self::sanitize_string( (string) $content );
		}
		if ( is_array( $content ) ) {
			return self::sanitize_string( wp_json_encode( $content ) );
		}
		return self::sanitize_string( wp_json_encode( $content ) );
	}

	private static function normalize_tool_content( $content ): string {
		if ( is_string( $content ) ) {
			return self::sanitize_string( $content );
		}
		if ( is_scalar( $content ) || null === $content ) {
			return self::sanitize_string( (string) $content );
		}
		return self::sanitize_string( wp_json_encode( $content ) );
	}

	private static function sanitize_string( string $value ): string {
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$value = wp_check_invalid_utf8( $value );
		}
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
		if ( ! is_string( $value ) ) {
			$value = '';
		}
		if ( mb_strlen( $value ) > self::MAX_CONTENT_CHARS ) {
			$value = mb_substr( $value, 0, self::MAX_CONTENT_CHARS );
		}
		return $value;
	}

	private static function timestamp_value( string $value ): int {
		$timestamp = strtotime( $value );
		return false === $timestamp ? 0 : (int) $timestamp;
	}
}
