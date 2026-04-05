<?php
/**
 * Operator-first recall across durable runs, tasks, traces, receipts, and site notes.
 *
 * This layer is intentionally read-only. It helps support/debug/resume workflows
 * find prior operational context without automatically stuffing extra history into
 * model prompts.
 *
 * @package PressArk
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Operational_Search {

	private const DEFAULT_LIMIT      = 24;
	private const MAX_QUERY_TERMS    = 14;
	private const MAX_SCAN_RUNS      = 180;
	private const MAX_SCAN_TASKS     = 180;
	private const MAX_SCAN_EVENTS    = 240;
	private const MAX_PER_KIND       = 8;
	private const MAX_PER_KIND_NOTES = 4;

	/**
	 * Search across durable operational history.
	 *
	 * @param array<string,mixed> $args Search configuration.
	 * @return array{
	 *   query:string,
	 *   terms:array<int,string>,
	 *   results:array<int,array<string,mixed>>,
	 *   counts:array<string,int>,
	 *   signals:array<string,int>
	 * }
	 */
	public function search( array $args = array() ): array {
		$query            = sanitize_text_field( (string) ( $args['query'] ?? '' ) );
		$extra_terms      = is_array( $args['terms'] ?? null ) ? (array) $args['terms'] : array();
		$user_id          = max( 0, (int) ( $args['user_id'] ?? 0 ) );
		$limit            = max( 1, min( 60, (int) ( $args['limit'] ?? self::DEFAULT_LIMIT ) ) );
		$exclude_run_ids  = $this->normalize_identifier_list( $args['exclude_run_ids'] ?? array() );
		$exclude_task_ids = $this->normalize_identifier_list( $args['exclude_task_ids'] ?? array() );
		$terms            = $this->build_terms( $query, $extra_terms );

		if ( empty( $terms ) ) {
			return array(
				'query'   => $query,
				'terms'   => array(),
				'results' => array(),
				'counts'  => array(),
				'signals' => array(),
			);
		}

		$results = array_merge(
			$this->search_runs( $this->load_runs( $user_id, self::MAX_SCAN_RUNS ), $terms, $query, $exclude_run_ids ),
			$this->search_tasks( $this->load_tasks( $user_id, self::MAX_SCAN_TASKS ), $terms, $query, $exclude_task_ids ),
			$this->search_events( $this->load_events( $user_id, self::MAX_SCAN_EVENTS ), $terms, $query ),
			$this->search_site_notes( $this->load_site_notes(), $terms, $query )
		);

		usort(
			$results,
			static function ( array $left, array $right ): int {
				$score_compare = (int) ( $right['score'] ?? 0 ) <=> (int) ( $left['score'] ?? 0 );
				if ( 0 !== $score_compare ) {
					return $score_compare;
				}

				$left_at  = (string) ( $left['created_at'] ?? '' );
				$right_at = (string) ( $right['created_at'] ?? '' );
				if ( $left_at === $right_at ) {
					return strcmp( (string) ( $left['key'] ?? '' ), (string) ( $right['key'] ?? '' ) );
				}

				return strcmp( $right_at, $left_at );
			}
		);

		$results = $this->limit_results( $results, $limit );

		return array(
			'query'   => $query,
			'terms'   => $terms,
			'results' => $results,
			'counts'  => $this->count_results_by_kind( $results ),
			'signals' => $this->count_results_by_signal( $results ),
		);
	}

	/**
	 * Build contextual recall for one durable run.
	 *
	 * @param array<string,mixed> $run Stored run row.
	 * @param int                 $user_id Scope to one user when non-zero.
	 * @return array<string,mixed>
	 */
	public function related_for_run( array $run, int $user_id = 0 ): array {
		$seed = $this->build_run_seed( $run );

		return $this->search(
			array(
				'query'            => $seed['query'],
				'terms'            => $seed['terms'],
				'user_id'          => $user_id,
				'limit'            => 12,
				'exclude_run_ids'  => array( sanitize_text_field( (string) ( $run['run_id'] ?? '' ) ) ),
				'exclude_task_ids' => array_filter(
					array(
						sanitize_text_field( (string) ( $run['task_id'] ?? '' ) ),
					)
				),
			)
		);
	}

	/**
	 * Build contextual recall for one durable task.
	 *
	 * @param array<string,mixed> $task Stored task row.
	 * @param int                 $user_id Scope to one user when non-zero.
	 * @return array<string,mixed>
	 */
	public function related_for_task( array $task, int $user_id = 0 ): array {
		$run  = ! empty( $task['run_id'] ) ? $this->load_run( (string) $task['run_id'] ) : null;
		$seed = $this->build_task_seed( $task, $run );

		return $this->search(
			array(
				'query'            => $seed['query'],
				'terms'            => $seed['terms'],
				'user_id'          => $user_id,
				'limit'            => 12,
				'exclude_run_ids'  => array_filter(
					array(
						sanitize_text_field( (string) ( $task['run_id'] ?? '' ) ),
					)
				),
				'exclude_task_ids' => array( sanitize_text_field( (string) ( $task['task_id'] ?? '' ) ) ),
			)
		);
	}

	/**
	 * Load recent runs with enough detail for search scoring.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function load_runs( int $user_id, int $limit ): array {
		global $wpdb;

		$table = PressArk_Run_Store::table_name();
		$where = array( "route <> 'handoff'" );
		$args  = array();

		if ( $user_id > 0 ) {
			$where[] = 'user_id = %d';
			$args[]  = $user_id;
		}

		$args[] = max( 1, min( 400, $limit ) );
		$sql    = "SELECT run_id, user_id, task_id, route, status, message, error_summary,
				correlation_id, reservation_id, parent_run_id, root_run_id,
				workflow_state, result, pending_actions, created_at, settled_at
			FROM {$table}
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY created_at DESC
			LIMIT %d';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return array_map(
			function ( array $row ): array {
				$row['workflow_state'] = json_decode( (string) ( $row['workflow_state'] ?? 'null' ), true );
				$row['result']         = json_decode( (string) ( $row['result'] ?? 'null' ), true );
				$row['pending_actions'] = json_decode( (string) ( $row['pending_actions'] ?? 'null' ), true );
				$row['user_id']        = (int) ( $row['user_id'] ?? 0 );
				return $row;
			},
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Load a single run row for contextual recall seeding.
	 *
	 * @param string $run_id Run ID.
	 * @return array<string,mixed>|null
	 */
	protected function load_run( string $run_id ): ?array {
		if ( '' === trim( $run_id ) ) {
			return null;
		}

		$store = new PressArk_Run_Store();
		return $store->get( sanitize_text_field( $run_id ) );
	}

	/**
	 * Load recent tasks with enough detail for search scoring.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function load_tasks( int $user_id, int $limit ): array {
		global $wpdb;

		$table = PressArk_Task_Store::table_name();
		$where = array();
		$args  = array();

		if ( $user_id > 0 ) {
			$where[] = 'user_id = %d';
			$args[]  = $user_id;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$args[]    = max( 1, min( 400, $limit ) );
		$sql       = "SELECT task_id, run_id, parent_run_id, root_run_id, user_id, status, retries,
				max_retries, message, fail_reason, payload, result, handoff_capsule,
				created_at, started_at, completed_at
			FROM {$table}
			{$where_sql}
			ORDER BY created_at DESC
			LIMIT %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return array_map(
			function ( array $row ): array {
				$row['payload']         = json_decode( (string) ( $row['payload'] ?? 'null' ), true );
				$row['result']          = json_decode( (string) ( $row['result'] ?? 'null' ), true );
				$row['handoff_capsule'] = json_decode( (string) ( $row['handoff_capsule'] ?? 'null' ), true );
				$row['user_id']         = (int) ( $row['user_id'] ?? 0 );
				$row['retries']         = (int) ( $row['retries'] ?? 0 );
				$row['max_retries']     = (int) ( $row['max_retries'] ?? 0 );
				return $row;
			},
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Load recent local activity events, optionally scoped to one user.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function load_events( int $user_id, int $limit ): array {
		global $wpdb;

		$event_table = PressArk_Activity_Event_Store::table_name();
		$run_table   = PressArk_Run_Store::table_name();
		$task_table  = PressArk_Task_Store::table_name();
		$where       = array();
		$args        = array();

		if ( $user_id > 0 ) {
			$where[] = "(
				( e.run_id IS NOT NULL AND e.run_id <> '' AND r.user_id = %d )
				OR
				( e.task_id IS NOT NULL AND e.task_id <> '' AND t.user_id = %d )
			)";
			$args[]  = $user_id;
			$args[]  = $user_id;
		}

		$where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$args[]    = max( 1, min( 500, $limit ) );
		$sql       = "SELECT e.event_id, e.run_id, e.task_id, e.event_type, e.reason, e.summary,
				e.payload, e.created_at, COALESCE( r.user_id, t.user_id, 0 ) AS user_id
			FROM {$event_table} e
			LEFT JOIN {$run_table} r ON e.run_id = r.run_id
			LEFT JOIN {$task_table} t ON e.task_id = t.task_id
			{$where_sql}
			ORDER BY e.created_at DESC, e.id DESC
			LIMIT %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return array_map(
			static function ( array $row ): array {
				$row['payload']   = json_decode( (string) ( $row['payload'] ?? '{}' ), true );
				$row['user_id']   = (int) ( $row['user_id'] ?? 0 );
				$row['event_type'] = str_replace( '_', '.', (string) ( $row['event_type'] ?? '' ) );
				return $row;
			},
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Load durable site notes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function load_site_notes(): array {
		$raw   = get_option( 'pressark_site_notes', '[]' );
		$notes = json_decode( is_string( $raw ) ? $raw : '[]', true );

		return is_array( $notes ) ? array_values( $notes ) : array();
	}

	/**
	 * Search run rows.
	 *
	 * @param array<int,array<string,mixed>> $runs Runs to inspect.
	 * @param array<int,string>              $terms Query terms.
	 * @param string                         $query Original query.
	 * @param array<int,string>              $exclude_run_ids Run IDs to skip.
	 * @return array<int,array<string,mixed>>
	 */
	private function search_runs( array $runs, array $terms, string $query, array $exclude_run_ids ): array {
		$results = array();

		foreach ( $runs as $run ) {
			$run_id = sanitize_text_field( (string) ( $run['run_id'] ?? '' ) );
			if ( '' === $run_id || in_array( $run_id, $exclude_run_ids, true ) ) {
				continue;
			}

			$snapshot      = $this->run_snapshot( $run );
			$decision_text = $this->decision_text_from_snapshot( $snapshot );
			$target_text   = $this->target_text_from_snapshot( $snapshot );
			$context_text  = $this->context_text_from_snapshot( $snapshot );
			$result_text   = $this->flatten_for_search( $run['result'] ?? array() );
			$pending_text  = $this->flatten_for_search( $run['pending_actions'] ?? array() );

			$match = $this->score_fields(
				array(
					'Run ID'             => $run_id,
					'Task ID'            => (string) ( $run['task_id'] ?? '' ),
					'Correlation ID'     => (string) ( $run['correlation_id'] ?? '' ),
					'Reservation ID'     => (string) ( $run['reservation_id'] ?? '' ),
					'Run message'        => (string) ( $run['message'] ?? '' ),
					'Failure summary'    => (string) ( $run['error_summary'] ?? '' ),
					'Target'             => $target_text,
					'Approved decisions' => $decision_text,
					'Context capsule'    => $context_text,
					'Pending actions'    => $pending_text,
					'Run result'         => $result_text,
				),
				$terms,
				$query
			);

			if ( $match['score'] <= 0 ) {
				continue;
			}

			$signals = $this->run_signals( $run, $snapshot );
			$results[] = array(
				'key'        => 'run:' . $run_id,
				'kind'       => 'run',
				'run_id'     => $run_id,
				'task_id'    => sanitize_text_field( (string) ( $run['task_id'] ?? '' ) ),
				'user_id'    => (int) ( $run['user_id'] ?? 0 ),
				'title'      => $this->truncate( (string) ( $run['message'] ?? ( 'Run ' . $run_id ) ), 96 ),
				'summary'    => $this->summarize_run( $run, $snapshot ),
				'matched_on' => $match['labels'],
				'signals'    => $signals,
				'created_at' => sanitize_text_field( (string) ( $run['created_at'] ?? '' ) ),
				'score'      => $match['score'] + ( in_array( 'failure', $signals, true ) ? 8 : 0 ),
			);

			if ( '' !== $decision_text ) {
				$decision_match = $this->score_fields(
					array(
						'Approved decisions' => $decision_text,
						'Target'             => $target_text,
						'Run message'        => (string) ( $run['message'] ?? '' ),
					),
					$terms,
					$query
				);

				if ( $decision_match['score'] > 0 ) {
					$results[] = array(
						'key'        => 'decision:' . $run_id,
						'kind'       => 'decision',
						'run_id'     => $run_id,
						'task_id'    => sanitize_text_field( (string) ( $run['task_id'] ?? '' ) ),
						'user_id'    => (int) ( $run['user_id'] ?? 0 ),
						'title'      => __( 'Approved Decision', 'pressark' ),
						'summary'    => $this->truncate( $decision_text, 190 ),
						'matched_on' => $decision_match['labels'],
						'signals'    => array( 'approval' ),
						'created_at' => sanitize_text_field( (string) ( $run['created_at'] ?? '' ) ),
						'score'      => $decision_match['score'] + 18,
					);
				}
			}
		}

		return $results;
	}

	/**
	 * Search task rows and receipt payloads.
	 *
	 * @param array<int,array<string,mixed>> $tasks Tasks to inspect.
	 * @param array<int,string>              $terms Query terms.
	 * @param string                         $query Original query.
	 * @param array<int,string>              $exclude_task_ids Task IDs to skip.
	 * @return array<int,array<string,mixed>>
	 */
	private function search_tasks( array $tasks, array $terms, string $query, array $exclude_task_ids ): array {
		$results = array();

		foreach ( $tasks as $task ) {
			$task_id = sanitize_text_field( (string) ( $task['task_id'] ?? '' ) );
			if ( '' === $task_id || in_array( $task_id, $exclude_task_ids, true ) ) {
				continue;
			}

			$receipts     = $this->task_receipts( $task );
			$receipt_text = $this->receipt_text( $receipts );
			$handoff_text = $this->flatten_for_search( $task['handoff_capsule'] ?? array() );
			$result_text  = $this->flatten_for_search( $task['result'] ?? array() );
			$payload_text = $this->flatten_for_search( $task['payload'] ?? array() );

			$match = $this->score_fields(
				array(
					'Task ID'         => $task_id,
					'Run ID'          => (string) ( $task['run_id'] ?? '' ),
					'Root run ID'     => (string) ( $task['root_run_id'] ?? '' ),
					'Task message'    => (string) ( $task['message'] ?? '' ),
					'Failure reason'  => (string) ( $task['fail_reason'] ?? '' ),
					'Receipt summary' => $receipt_text,
					'Handoff capsule' => $handoff_text,
					'Task result'     => $result_text,
					'Task payload'    => $payload_text,
				),
				$terms,
				$query
			);

			if ( $match['score'] > 0 ) {
				$signals = $this->task_signals( $task, $receipts );
				$results[] = array(
					'key'        => 'task:' . $task_id,
					'kind'       => 'task',
					'task_id'    => $task_id,
					'run_id'     => sanitize_text_field( (string) ( $task['run_id'] ?? '' ) ),
					'user_id'    => (int) ( $task['user_id'] ?? 0 ),
					'title'      => $this->truncate( (string) ( $task['message'] ?? ( 'Task ' . $task_id ) ), 96 ),
					'summary'    => $this->summarize_task( $task, $receipts ),
					'matched_on' => $match['labels'],
					'signals'    => $signals,
					'created_at' => sanitize_text_field( (string) ( $task['created_at'] ?? '' ) ),
					'score'      => $match['score'] + ( in_array( 'failure', $signals, true ) ? 8 : 0 ),
				);
			}

			foreach ( $receipts as $operation_key => $receipt ) {
				$receipt_match = $this->score_fields(
					array(
						'Receipt operation' => (string) $operation_key,
						'Receipt summary'   => (string) ( $receipt['summary'] ?? '' ),
						'Task message'      => (string) ( $task['message'] ?? '' ),
						'Task ID'           => $task_id,
					),
					$terms,
					$query
				);

				if ( $receipt_match['score'] <= 0 ) {
					continue;
				}

				$results[] = array(
					'key'        => 'receipt:' . $task_id . ':' . sanitize_key( (string) $operation_key ),
					'kind'       => 'receipt',
					'task_id'    => $task_id,
					'run_id'     => sanitize_text_field( (string) ( $task['run_id'] ?? '' ) ),
					'user_id'    => (int) ( $task['user_id'] ?? 0 ),
					'title'      => $this->truncate( (string) $operation_key, 96 ),
					'summary'    => $this->truncate(
						(string) ( $receipt['summary'] ?? __( 'Persisted operation receipt.', 'pressark' ) ),
						180
					),
					'matched_on' => $receipt_match['labels'],
					'signals'    => array( 'receipt' ),
					'created_at' => sanitize_text_field( (string) ( $receipt['ts'] ?? $task['completed_at'] ?? $task['created_at'] ?? '' ) ),
					'score'      => $receipt_match['score'] + 16,
				);
			}
		}

		return $results;
	}

	/**
	 * Search local activity events.
	 *
	 * @param array<int,array<string,mixed>> $events Events to inspect.
	 * @param array<int,string>              $terms Query terms.
	 * @param string                         $query Original query.
	 * @return array<int,array<string,mixed>>
	 */
	private function search_events( array $events, array $terms, string $query ): array {
		$results = array();

		foreach ( $events as $event ) {
			$event_id = sanitize_text_field( (string) ( $event['event_id'] ?? '' ) );
			if ( '' === $event_id ) {
				continue;
			}

			$payload_text = $this->flatten_for_search( $event['payload'] ?? array() );
			$match        = $this->score_fields(
				array(
					'Trace summary' => (string) ( $event['summary'] ?? '' ),
					'Trace reason'  => (string) ( $event['reason'] ?? '' ),
					'Trace event'   => (string) ( $event['event_type'] ?? '' ),
					'Trace payload' => $payload_text,
					'Run ID'        => (string) ( $event['run_id'] ?? '' ),
					'Task ID'       => (string) ( $event['task_id'] ?? '' ),
				),
				$terms,
				$query
			);

			if ( $match['score'] <= 0 ) {
				continue;
			}

			$signals = $this->event_signals( $event );
			$results[] = array(
				'key'        => 'trace:' . $event_id,
				'kind'       => 'trace',
				'run_id'     => sanitize_text_field( (string) ( $event['run_id'] ?? '' ) ),
				'task_id'    => sanitize_text_field( (string) ( $event['task_id'] ?? '' ) ),
				'user_id'    => (int) ( $event['user_id'] ?? 0 ),
				'title'      => $this->truncate(
					(string) ( $event['summary'] ?? $event['event_type'] ?? __( 'Trace event', 'pressark' ) ),
					96
				),
				'summary'    => $this->summarize_event( $event ),
				'matched_on' => $match['labels'],
				'signals'    => $signals,
				'created_at' => sanitize_text_field( (string) ( $event['created_at'] ?? '' ) ),
				'score'      => $match['score'] + ( in_array( 'fallback', $signals, true ) ? 10 : 0 ),
			);
		}

		return $results;
	}

	/**
	 * Search durable site notes.
	 *
	 * @param array<int,array<string,mixed>> $notes Site notes.
	 * @param array<int,string>              $terms Query terms.
	 * @param string                         $query Original query.
	 * @return array<int,array<string,mixed>>
	 */
	private function search_site_notes( array $notes, array $terms, string $query ): array {
		$results = array();

		foreach ( $notes as $index => $note ) {
			if ( ! is_array( $note ) ) {
				continue;
			}

			$category = sanitize_key( (string) ( $note['category'] ?? '' ) );
			$text     = sanitize_text_field( (string) ( $note['note'] ?? '' ) );
			if ( '' === $category && '' === $text ) {
				continue;
			}

			$match = $this->score_fields(
				array(
					'Site note' => $text,
					'Category'  => $category,
				),
				$terms,
				$query
			);

			if ( $match['score'] <= 0 ) {
				continue;
			}

			$results[] = array(
				'key'        => 'site_note:' . $index,
				'kind'       => 'site_note',
				'run_id'     => '',
				'task_id'    => '',
				'user_id'    => 0,
				'title'      => '' !== $category ? ucfirst( $category ) : __( 'Site Note', 'pressark' ),
				'summary'    => $this->truncate( $text, 190 ),
				'matched_on' => $match['labels'],
				'signals'    => array( 'note' ),
				'created_at' => sanitize_text_field( (string) ( $note['created_at'] ?? '' ) ),
				'score'      => $match['score'] + 6,
			);
		}

		return $results;
	}

	/**
	 * Build a contextual seed from one run.
	 *
	 * @param array<string,mixed> $run Run row.
	 * @return array{query:string,terms:array<int,string>}
	 */
	private function build_run_seed( array $run ): array {
		$snapshot      = $this->run_snapshot( $run );
		$target_text   = $this->target_text_from_snapshot( $snapshot );
		$context_text  = sanitize_text_field( (string) ( $snapshot['context_capsule']['target'] ?? '' ) );
		$decision_text = $this->decision_text_from_snapshot( $snapshot );
		$message       = sanitize_text_field( (string) ( $run['message'] ?? '' ) );

		$query = $target_text;
		if ( '' === $query ) {
			$query = $context_text;
		}
		if ( '' === $query ) {
			$query = $message;
		}

		return array(
			'query' => $query,
			'terms' => array_merge(
				$this->extract_terms_from_text( $target_text ),
				$this->extract_terms_from_text( $context_text ),
				$this->extract_terms_from_text( $decision_text ),
				$this->extract_terms_from_text( $message ),
				$this->identifier_terms(
					array(
						$run['run_id'] ?? '',
						$run['task_id'] ?? '',
						$run['correlation_id'] ?? '',
						$run['reservation_id'] ?? '',
					)
				)
			),
		);
	}

	/**
	 * Build a contextual seed from one task and its linked run.
	 *
	 * @param array<string,mixed>      $task Task row.
	 * @param array<string,mixed>|null $run  Optional linked run row.
	 * @return array{query:string,terms:array<int,string>}
	 */
	private function build_task_seed( array $task, ?array $run ): array {
		$receipt_text = $this->receipt_text( $this->task_receipts( $task ) );
		$handoff_text = sanitize_text_field( (string) ( $task['handoff_capsule']['target'] ?? '' ) );
		$message      = sanitize_text_field( (string) ( $task['message'] ?? '' ) );
		$query        = $handoff_text;

		if ( '' === $query && is_array( $run ) ) {
			$query = $this->build_run_seed( $run )['query'];
		}
		if ( '' === $query ) {
			$query = $message;
		}

		$terms = array_merge(
			$this->extract_terms_from_text( $handoff_text ),
			$this->extract_terms_from_text( $message ),
			$this->extract_terms_from_text( $receipt_text ),
			$this->identifier_terms(
				array(
					$task['task_id'] ?? '',
					$task['run_id'] ?? '',
					$task['root_run_id'] ?? '',
				)
			)
		);

		if ( is_array( $run ) ) {
			$run_seed = $this->build_run_seed( $run );
			$terms    = array_merge( $terms, $run_seed['terms'] );
			if ( '' === $query ) {
				$query = $run_seed['query'];
			}
		}

		return array(
			'query' => $query,
			'terms' => $terms,
		);
	}

	/**
	 * Normalize and dedupe free-text terms.
	 *
	 * @param string            $query Search query.
	 * @param array<int,string> $extra_terms Additional seed terms.
	 * @return array<int,string>
	 */
	private function build_terms( string $query, array $extra_terms = array() ): array {
		$terms = array();

		foreach ( array_merge( $this->extract_terms_from_text( $query ), $extra_terms ) as $term ) {
			$normalized = $this->normalize_term( (string) $term );
			if ( '' === $normalized ) {
				continue;
			}

			$terms[ $normalized ] = true;
			if ( count( $terms ) >= self::MAX_QUERY_TERMS ) {
				break;
			}
		}

		return array_keys( $terms );
	}

	/**
	 * Extract searchable terms from one text blob.
	 *
	 * @param string $text Source text.
	 * @return array<int,string>
	 */
	private function extract_terms_from_text( string $text ): array {
		$text = $this->normalize_blob( $text );
		if ( '' === $text ) {
			return array();
		}

		$raw_terms = preg_split( '/[^a-z0-9_:\\/\\.\\-]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $raw_terms ) ) {
			return array();
		}

		$stop_words = array(
			'the', 'and', 'for', 'with', 'that', 'this', 'from', 'your', 'into',
			'after', 'before', 'when', 'where', 'were', 'have', 'has', 'had',
			'just', 'than', 'then', 'them', 'they', 'their', 'same', 'work',
			'page', 'task', 'run', 'user', 'site', 'note',
		);

		$terms = array();
		foreach ( $raw_terms as $term ) {
			$term = $this->normalize_term( $term );
			if ( '' === $term || in_array( $term, $stop_words, true ) ) {
				continue;
			}

			$terms[] = $term;
			if ( count( $terms ) >= self::MAX_QUERY_TERMS ) {
				break;
			}
		}

		return $terms;
	}

	/**
	 * Normalize one term for matching.
	 */
	private function normalize_term( string $term ): string {
		$term = strtolower( trim( $term ) );
		$term = preg_replace( '/[^a-z0-9_:\\/\\.\\-]/', '', $term );
		$term = is_string( $term ) ? trim( $term, '-:./' ) : '';

		if ( '' === $term ) {
			return '';
		}

		if ( preg_match( '/^\d+$/', $term ) ) {
			return strlen( $term ) >= 2 ? $term : '';
		}

		return strlen( $term ) >= 3 ? $term : '';
	}

	/**
	 * Score a set of labeled fields against the active query terms.
	 *
	 * @param array<string,string> $fields Labeled text fields.
	 * @param array<int,string>    $terms Query terms.
	 * @param string               $query Original query.
	 * @return array{score:int,labels:array<int,string>}
	 */
	private function score_fields( array $fields, array $terms, string $query ): array {
		$labels           = array();
		$score            = 0;
		$normalized_query = $this->normalize_blob( $query );

		foreach ( $fields as $label => $raw_value ) {
			$value = $this->normalize_blob( (string) $raw_value );
			if ( '' === $value ) {
				continue;
			}

			$field_score = 0;
			if ( '' !== $normalized_query && strlen( $normalized_query ) >= 4 && str_contains( $value, $normalized_query ) ) {
				$field_score += 36;
			}

			foreach ( $terms as $term ) {
				if ( '' === $term ) {
					continue;
				}

				if ( $value === $term ) {
					$field_score += 70;
					continue;
				}

				if ( str_contains( ' ' . $value . ' ', ' ' . $term . ' ' ) ) {
					$field_score += strlen( $term ) >= 5 ? 24 : 14;
					continue;
				}

				if ( str_contains( $value, $term ) ) {
					$field_score += strlen( $term ) >= 5 ? 12 : 7;
				}
			}

			if ( $field_score > 0 ) {
				$score            += $field_score;
				$labels[ $label ] = true;
			}
		}

		return array(
			'score'  => $score,
			'labels' => array_keys( $labels ),
		);
	}

	/**
	 * Reduce large text/JSON blobs into searchable lowercase text.
	 *
	 * @param string $text Source text.
	 * @return string
	 */
	private function normalize_blob( string $text ): string {
		$text = strtolower( trim( wp_strip_all_tags( $text ) ) );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = is_string( $text ) ? trim( $text ) : '';

		return strlen( $text ) > 1200 ? substr( $text, 0, 1200 ) : $text;
	}

	/**
	 * Flatten structured arrays into concise searchable text.
	 *
	 * @param mixed $value Structured value.
	 * @param int   $depth Recursion depth guard.
	 * @return string
	 */
	private function flatten_for_search( $value, int $depth = 0 ): string {
		if ( $depth >= 4 ) {
			return '';
		}

		if ( is_string( $value ) ) {
			return strlen( $value ) > 600 ? substr( $value, 0, 600 ) : $value;
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		$parts = array();
		foreach ( array_slice( $value, 0, 24, true ) as $key => $item ) {
			$key_text  = is_string( $key ) ? sanitize_key( $key ) : '';
			$item_text = $this->flatten_for_search( $item, $depth + 1 );
			if ( '' === $key_text && '' === $item_text ) {
				continue;
			}
			$parts[] = trim( $key_text . ' ' . $item_text );
			if ( count( $parts ) >= 24 ) {
				break;
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Extract the most useful run snapshot surface for recall.
	 *
	 * @param array<string,mixed> $run Run row.
	 * @return array<string,mixed>
	 */
	private function run_snapshot( array $run ): array {
		$workflow_state = is_array( $run['workflow_state'] ?? null ) ? $run['workflow_state'] : array();
		if ( ! empty( $workflow_state ) ) {
			return $workflow_state;
		}

		$result = is_array( $run['result'] ?? null ) ? $run['result'] : array();
		if ( is_array( $result['checkpoint'] ?? null ) ) {
			return (array) $result['checkpoint'];
		}
		if ( is_array( $result['workflow_state'] ?? null ) ) {
			return (array) $result['workflow_state'];
		}

		return array();
	}

	/**
	 * Build a readable target string from a stored snapshot.
	 *
	 * @param array<string,mixed> $snapshot Workflow or checkpoint snapshot.
	 * @return string
	 */
	private function target_text_from_snapshot( array $snapshot ): string {
		$selected = is_array( $snapshot['selected_target'] ?? null ) ? $snapshot['selected_target'] : array();
		$title    = sanitize_text_field( (string) ( $selected['title'] ?? '' ) );
		$type     = sanitize_text_field( (string) ( $selected['type'] ?? '' ) );
		$id       = absint( $selected['id'] ?? 0 );

		$parts = array();
		if ( '' !== $type ) {
			$parts[] = $type;
		}
		if ( $id > 0 ) {
			$parts[] = '#' . $id;
		}
		if ( '' !== $title ) {
			$parts[] = $title;
		}

		if ( ! empty( $parts ) ) {
			return implode( ' ', $parts );
		}

		return sanitize_text_field( (string) ( $snapshot['context_capsule']['target'] ?? '' ) );
	}

	/**
	 * Build a readable approvals/plan string from a stored snapshot.
	 *
	 * @param array<string,mixed> $snapshot Workflow or checkpoint snapshot.
	 * @return string
	 */
	private function decision_text_from_snapshot( array $snapshot ): string {
		$parts = array();

		$approvals      = is_array( $snapshot['approvals'] ?? null ) ? $snapshot['approvals'] : array();
		$approval_names = array_values(
			array_filter(
				array_map(
					static function ( $row ): string {
						return sanitize_text_field( (string) ( is_array( $row ) ? ( $row['action'] ?? '' ) : '' ) );
					},
					$approvals
				)
			)
		);
		if ( ! empty( $approval_names ) ) {
			$parts[] = 'approved actions ' . implode( ', ', array_slice( $approval_names, 0, 6 ) );
		}

		$plan_state = is_array( $snapshot['plan_state'] ?? null ) ? $snapshot['plan_state'] : array();
		if ( ! empty( $plan_state['approved_at'] ) && ! empty( $plan_state['plan_text'] ) ) {
			$parts[] = 'approved plan ' . sanitize_textarea_field( (string) $plan_state['plan_text'] );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Build a readable context capsule string from a snapshot.
	 *
	 * @param array<string,mixed> $snapshot Workflow or checkpoint snapshot.
	 * @return string
	 */
	private function context_text_from_snapshot( array $snapshot ): string {
		$context = is_array( $snapshot['context_capsule'] ?? null ) ? $snapshot['context_capsule'] : array();
		if ( empty( $context ) ) {
			return '';
		}

		$parts = array();
		foreach ( array( 'task', 'active_request', 'target', 'summary' ) as $key ) {
			$value = sanitize_text_field( (string) ( $context[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}

		foreach ( array( 'historical_requests', 'completed', 'remaining', 'recent_receipts' ) as $key ) {
			foreach ( array_slice( (array) ( $context[ $key ] ?? array() ), 0, 4 ) as $value ) {
				$value = sanitize_text_field( (string) $value );
				if ( '' !== $value ) {
					$parts[] = $value;
				}
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * Extract task receipts from the payload blob.
	 *
	 * @param array<string,mixed> $task Task row.
	 * @return array<string,array<string,mixed>>
	 */
	private function task_receipts( array $task ): array {
		$payload  = is_array( $task['payload'] ?? null ) ? $task['payload'] : array();
		$receipts = is_array( $payload['_receipts'] ?? null ) ? $payload['_receipts'] : array();

		return $receipts;
	}

	/**
	 * Collapse receipts into one searchable string.
	 *
	 * @param array<string,array<string,mixed>> $receipts Receipts keyed by operation.
	 * @return string
	 */
	private function receipt_text( array $receipts ): string {
		$parts = array();
		foreach ( array_slice( $receipts, 0, 8, true ) as $operation_key => $receipt ) {
			$summary = sanitize_text_field( (string) ( is_array( $receipt ) ? ( $receipt['summary'] ?? '' ) : '' ) );
			$parts[] = sanitize_text_field( (string) $operation_key ) . ' ' . $summary;
		}

		return implode( ' ', $parts );
	}

	/**
	 * Human summary for one run hit.
	 *
	 * @param array<string,mixed> $run Run row.
	 * @param array<string,mixed> $snapshot Snapshot surface.
	 * @return string
	 */
	private function summarize_run( array $run, array $snapshot ): string {
		$parts  = array();
		$status = sanitize_key( (string) ( $run['status'] ?? '' ) );
		$route  = sanitize_key( (string) ( $run['route'] ?? '' ) );

		if ( '' !== $status ) {
			$parts[] = ucfirst( $status );
		}
		if ( '' !== $route ) {
			$parts[] = 'via ' . $route;
		}

		$error_summary = sanitize_text_field( (string) ( $run['error_summary'] ?? '' ) );
		if ( '' !== $error_summary ) {
			$parts[] = 'Failure: ' . $error_summary;
		}

		$target = $this->target_text_from_snapshot( $snapshot );
		if ( '' !== $target ) {
			$parts[] = 'Target: ' . $target;
		}

		$decision = $this->decision_text_from_snapshot( $snapshot );
		if ( '' !== $decision ) {
			$parts[] = $this->truncate( $decision, 120 );
		}

		return $this->truncate( implode( '. ', array_filter( $parts ) ), 190 );
	}

	/**
	 * Human summary for one task hit.
	 *
	 * @param array<string,mixed>               $task Task row.
	 * @param array<string,array<string,mixed>> $receipts Receipts map.
	 * @return string
	 */
	private function summarize_task( array $task, array $receipts ): string {
		$parts  = array();
		$status = sanitize_key( (string) ( $task['status'] ?? '' ) );
		if ( '' !== $status ) {
			$parts[] = ucfirst( $status );
		}

		$fail_reason = sanitize_text_field( (string) ( $task['fail_reason'] ?? '' ) );
		if ( '' !== $fail_reason ) {
			$parts[] = 'Failure: ' . $fail_reason;
		}

		if ( ! empty( $receipts ) ) {
			$first_key = (string) array_key_first( $receipts );
			$first     = is_array( $receipts[ $first_key ] ?? null ) ? $receipts[ $first_key ] : array();
			$summary   = sanitize_text_field( (string) ( $first['summary'] ?? $first_key ) );
			if ( '' !== $summary ) {
				$parts[] = 'Latest receipt: ' . $summary;
			}
		}

		$task_result = is_array( $task['result'] ?? null ) ? $task['result'] : array();
		if ( ! empty( $task_result['message'] ) ) {
			$parts[] = $this->truncate( sanitize_text_field( (string) $task_result['message'] ), 100 );
		}

		return $this->truncate( implode( '. ', array_filter( $parts ) ), 190 );
	}

	/**
	 * Human summary for one trace hit.
	 *
	 * @param array<string,mixed> $event Event row.
	 * @return string
	 */
	private function summarize_event( array $event ): string {
		$parts = array();

		$reason = sanitize_key( (string) ( $event['reason'] ?? '' ) );
		if ( '' !== $reason ) {
			$parts[] = 'Reason: ' . str_replace( '_', ' ', $reason );
		}

		if ( ! empty( $event['summary'] ) ) {
			$parts[] = sanitize_text_field( (string) $event['summary'] );
		}

		if ( ! empty( $event['run_id'] ) ) {
			$parts[] = 'Run ' . substr( sanitize_text_field( (string) $event['run_id'] ), 0, 8 );
		}
		if ( ! empty( $event['task_id'] ) ) {
			$parts[] = 'Task ' . substr( sanitize_text_field( (string) $event['task_id'] ), 0, 8 );
		}

		return $this->truncate( implode( '. ', array_filter( $parts ) ), 190 );
	}

	/**
	 * Normalize and filter identifier candidates.
	 *
	 * @param array<int,mixed> $values Raw identifiers.
	 * @return array<int,string>
	 */
	private function identifier_terms( array $values ): array {
		$terms = array();
		foreach ( $values as $value ) {
			$value = sanitize_text_field( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$terms[] = $value;
			if ( preg_match( '/(\d+)/', $value, $matches ) ) {
				$terms[] = $matches[1];
			}
		}

		return $terms;
	}

	/**
	 * Detect operator-facing signals from one run row.
	 *
	 * @param array<string,mixed> $run Run row.
	 * @param array<string,mixed> $snapshot Snapshot surface.
	 * @return array<int,string>
	 */
	private function run_signals( array $run, array $snapshot ): array {
		$signals = array();

		if ( 'failed' === (string) ( $run['status'] ?? '' ) || ! empty( $run['error_summary'] ) ) {
			$signals[] = 'failure';
		}

		if ( '' !== $this->decision_text_from_snapshot( $snapshot ) ) {
			$signals[] = 'approval';
		}

		if ( ! empty( $run['pending_actions'] ) ) {
			$signals[] = 'pending';
		}

		$result = is_array( $run['result'] ?? null ) ? $run['result'] : array();
		if ( ! empty( $result['routing_decision']['fallback'] ) ) {
			$signals[] = 'fallback';
		}

		return array_values( array_unique( $signals ) );
	}

	/**
	 * Detect operator-facing signals from one task row.
	 *
	 * @param array<string,mixed>               $task Task row.
	 * @param array<string,array<string,mixed>> $receipts Receipts map.
	 * @return array<int,string>
	 */
	private function task_signals( array $task, array $receipts ): array {
		$signals = array();

		if ( in_array( (string) ( $task['status'] ?? '' ), array( 'failed', 'dead_letter' ), true ) || ! empty( $task['fail_reason'] ) ) {
			$signals[] = 'failure';
		}

		if ( ! empty( $receipts ) ) {
			$signals[] = 'receipt';
		}

		return array_values( array_unique( $signals ) );
	}

	/**
	 * Detect operator-facing signals from one trace event.
	 *
	 * @param array<string,mixed> $event Event row.
	 * @return array<int,string>
	 */
	private function event_signals( array $event ): array {
		$signals = array();
		$reason  = sanitize_key( (string) ( $event['reason'] ?? '' ) );

		if ( preg_match( '/fallback|degraded|reroute|discover_no_hits|budget|reserve_blocked/', $reason ) ) {
			$signals[] = 'fallback';
		}
		if ( preg_match( '/failed|error|cancel|retry|blocked/', $reason ) ) {
			$signals[] = 'failure';
		}
		if ( str_starts_with( $reason, 'approval_' ) ) {
			$signals[] = 'approval';
		}
		if ( preg_match( '/reserve|settle|release/', $reason ) ) {
			$signals[] = 'billing';
		}

		return array_values( array_unique( $signals ) );
	}

	/**
	 * Limit one result set so a single kind does not crowd out everything else.
	 *
	 * @param array<int,array<string,mixed>> $results Scored results.
	 * @param int                            $limit Hard total limit.
	 * @return array<int,array<string,mixed>>
	 */
	private function limit_results( array $results, int $limit ): array {
		$counts  = array();
		$limited = array();

		foreach ( $results as $result ) {
			$kind       = sanitize_key( (string) ( $result['kind'] ?? '' ) );
			$kind_limit = 'site_note' === $kind ? self::MAX_PER_KIND_NOTES : self::MAX_PER_KIND;
			$seen       = (int) ( $counts[ $kind ] ?? 0 );

			if ( $seen >= $kind_limit ) {
				continue;
			}

			$limited[]       = $result;
			$counts[ $kind ] = $seen + 1;

			if ( count( $limited ) >= $limit ) {
				break;
			}
		}

		return $limited;
	}

	/**
	 * Count the final result set by result kind.
	 *
	 * @param array<int,array<string,mixed>> $results Final results.
	 * @return array<string,int>
	 */
	private function count_results_by_kind( array $results ): array {
		$counts = array();
		foreach ( $results as $result ) {
			$kind = sanitize_key( (string) ( $result['kind'] ?? '' ) );
			if ( '' === $kind ) {
				continue;
			}
			$counts[ $kind ] = (int) ( $counts[ $kind ] ?? 0 ) + 1;
		}

		return $counts;
	}

	/**
	 * Count the final result set by signal tag.
	 *
	 * @param array<int,array<string,mixed>> $results Final results.
	 * @return array<string,int>
	 */
	private function count_results_by_signal( array $results ): array {
		$counts = array();
		foreach ( $results as $result ) {
			foreach ( (array) ( $result['signals'] ?? array() ) as $signal ) {
				$signal = sanitize_key( (string) $signal );
				if ( '' === $signal ) {
					continue;
				}
				$counts[ $signal ] = (int) ( $counts[ $signal ] ?? 0 ) + 1;
			}
		}

		return $counts;
	}

	/**
	 * Normalize identifier lists used for exclusion filters.
	 *
	 * @param mixed $values Raw identifier list.
	 * @return array<int,string>
	 */
	private function normalize_identifier_list( $values ): array {
		if ( ! is_array( $values ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $value ): string {
						return sanitize_text_field( (string) $value );
					},
					$values
				)
			)
		);
	}

	/**
	 * Truncate long text for operator surfaces.
	 */
	private function truncate( string $text, int $max_chars = 180 ): string {
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( strlen( $text ) <= $max_chars ) {
			return $text;
		}

		return substr( $text, 0, max( 0, $max_chars - 3 ) ) . '...';
	}
}
