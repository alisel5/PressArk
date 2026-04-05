<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Durable operator-authored site instructions with selective recall.
 *
 * The playbook sits between the inferred site profile and lightweight
 * site notes:
 * - human-authored and durable
 * - scoped by task type and tool group
 * - injected only when relevant for the current run
 */
class PressArk_Site_Playbook {

	public const OPTION_KEY          = 'pressark_site_playbook';
	public const MAX_ENTRIES         = 12;
	public const MAX_TITLE_LENGTH    = 80;
	public const MAX_BODY_LENGTH     = 1200;
	public const MAX_PROMPT_ENTRIES  = 4;
	public const MAX_PROMPT_CHARS    = 2200;
	public const ENTRY_PREVIEW_CHARS = 220;
	public const BODY_SNIPPET_CHARS  = 420;

	/**
	 * Read the normalized stored playbook.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all(): array {
		return self::normalize_entries( get_option( self::OPTION_KEY, array() ) );
	}

	/**
	 * Settings API sanitize callback.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize_option( $value ): array {
		return self::normalize_entries( $value );
	}

	/**
	 * Resolve the relevant playbook slice for a prompt.
	 *
	 * @return array{
	 *   text:string,
	 *   preview:string,
	 *   entries:array<int,array<string,mixed>>,
	 *   titles:array<int,string>,
	 *   task_type:string,
	 *   tool_groups:array<int,string>
	 * }
	 */
	public static function resolve_prompt_context(
		string $task_type,
		array $tool_groups = array(),
		string $message = '',
		int $max_entries = self::MAX_PROMPT_ENTRIES,
		int $max_chars = self::MAX_PROMPT_CHARS
	): array {
		$normalized_groups = self::normalize_tool_groups( $tool_groups );
		$entries           = self::select_relevant_entries(
			self::get_all(),
			$task_type,
			$normalized_groups,
			$message,
			$max_entries,
			$max_chars
		);

		return array(
			'text'        => self::format_prompt_block( $entries, $max_chars ),
			'preview'     => self::preview_entries( $entries ),
			'entries'     => $entries,
			'titles'      => array_values(
				array_filter(
					array_map(
						static function ( array $entry ): string {
							return sanitize_text_field( (string) ( $entry['title'] ?? '' ) );
						},
						$entries
					)
				)
			),
			'task_type'   => sanitize_key( $task_type ),
			'tool_groups' => $normalized_groups,
		);
	}

	/**
	 * Human-readable task labels for the settings UI.
	 *
	 * @return array<string,string>
	 */
	public static function task_labels(): array {
		return array(
			'all'      => __( 'All tasks', 'pressark' ),
			'generate' => __( 'Generate', 'pressark' ),
			'edit'     => __( 'Edit', 'pressark' ),
			'analyze'  => __( 'Analyze', 'pressark' ),
			'diagnose' => __( 'Diagnose', 'pressark' ),
			'chat'     => __( 'Chat', 'pressark' ),
			'code'     => __( 'Code', 'pressark' ),
			'classify' => __( 'Classify', 'pressark' ),
		);
	}

	/**
	 * Human-readable tool group labels for the settings UI.
	 *
	 * @return array<string,string>
	 */
	public static function tool_group_labels(): array {
		$labels = array(
			'all'           => __( 'All tool groups', 'pressark' ),
			'core'          => __( 'Core content', 'pressark' ),
			'generation'    => __( 'Content generation', 'pressark' ),
			'seo'           => __( 'SEO', 'pressark' ),
			'woocommerce'   => __( 'WooCommerce', 'pressark' ),
			'elementor'     => __( 'Elementor', 'pressark' ),
			'blocks'        => __( 'Blocks', 'pressark' ),
			'settings'      => __( 'Settings', 'pressark' ),
			'profile'       => __( 'Site profile', 'pressark' ),
			'security'      => __( 'Security', 'pressark' ),
			'health'        => __( 'Health', 'pressark' ),
			'database'      => __( 'Database', 'pressark' ),
			'logs'          => __( 'Logs', 'pressark' ),
			'media'         => __( 'Media', 'pressark' ),
			'menus'         => __( 'Menus', 'pressark' ),
			'taxonomy'      => __( 'Taxonomy', 'pressark' ),
			'comments'      => __( 'Comments', 'pressark' ),
			'users'         => __( 'Users', 'pressark' ),
			'plugins'       => __( 'Plugins', 'pressark' ),
			'themes'        => __( 'Themes', 'pressark' ),
			'templates'     => __( 'Templates', 'pressark' ),
			'design'        => __( 'Design', 'pressark' ),
			'patterns'      => __( 'Patterns', 'pressark' ),
			'bulk'          => __( 'Bulk edits', 'pressark' ),
			'index'         => __( 'Knowledge index', 'pressark' ),
			'custom_fields' => __( 'Custom fields', 'pressark' ),
			'forms'         => __( 'Forms', 'pressark' ),
			'email'         => __( 'Email', 'pressark' ),
			'export'        => __( 'Export', 'pressark' ),
			'scheduled'     => __( 'Scheduled tasks', 'pressark' ),
			'multisite'     => __( 'Multisite', 'pressark' ),
		);

		if ( is_callable( array( 'PressArk_Operation_Registry', 'group_names' ) ) ) {
			foreach ( (array) PressArk_Operation_Registry::group_names() as $group ) {
				$group = sanitize_key( (string) $group );
				if ( '' === $group || isset( $labels[ $group ] ) ) {
					continue;
				}
				$labels[ $group ] = ucwords( str_replace( '_', ' ', $group ) );
			}
		}

		return $labels;
	}

	/**
	 * Build a short preview string for inspectors.
	 *
	 * @param array<int,array<string,mixed>> $entries Selected playbook entries.
	 * @param int $max_chars Preview budget.
	 */
	public static function preview_entries( array $entries, int $max_chars = self::ENTRY_PREVIEW_CHARS ): string {
		if ( empty( $entries ) ) {
			return '';
		}

		$parts = array();
		foreach ( $entries as $entry ) {
			$title = sanitize_text_field( (string) ( $entry['title'] ?? '' ) );
			$body  = self::summarize_text( (string) ( $entry['body'] ?? '' ), 90 );
			if ( '' === $title && '' === $body ) {
				continue;
			}
			$parts[] = '' !== $title ? "{$title}: {$body}" : $body;
		}

		$preview = implode( ' | ', $parts );
		return mb_strlen( $preview ) > $max_chars ? mb_substr( $preview, 0, $max_chars - 1 ) . '…' : $preview;
	}

	/**
	 * Normalize raw stored/submitted entries.
	 *
	 * @param mixed $raw Raw playbook payload.
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_entries( $raw ): array {
		$entries = is_array( $raw ) ? array_values( $raw ) : array();
		$allowed_task_types = array_keys( self::task_labels() );
		$allowed_groups     = array_keys( self::tool_group_labels() );
		$normalized         = array();

		foreach ( $entries as $index => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$title = sanitize_text_field( (string) ( $entry['title'] ?? '' ) );
			$body  = sanitize_textarea_field( (string) ( $entry['body'] ?? '' ) );

			$title = self::summarize_text( $title, self::MAX_TITLE_LENGTH );
			$body  = self::summarize_text( $body, self::MAX_BODY_LENGTH );

			if ( '' === $title && '' === $body ) {
				continue;
			}

			if ( '' === $title ) {
				$title = self::summarize_text( $body, self::MAX_TITLE_LENGTH );
			}

			$id = sanitize_key( (string) ( $entry['id'] ?? '' ) );
			if ( '' === $id ) {
				$id = self::generate_entry_id( $title, $body, $index );
			}

			$normalized[] = array(
				'id'          => $id,
				'title'       => $title,
				'body'        => $body,
				'task_types'  => self::normalize_scope_list( $entry['task_types'] ?? array(), $allowed_task_types ),
				'tool_groups' => self::normalize_scope_list( $entry['tool_groups'] ?? array(), $allowed_groups ),
				'updated_at'  => sanitize_text_field( (string) ( $entry['updated_at'] ?? self::current_timestamp() ) ),
			);

			if ( count( $normalized ) >= self::MAX_ENTRIES ) {
				break;
			}
		}

		return $normalized;
	}

	/**
	 * Pick the most relevant instructions for the current run.
	 *
	 * @param array<int,array<string,mixed>> $entries Stored entries.
	 * @param array<int,string> $tool_groups Current active tool groups.
	 * @return array<int,array<string,mixed>>
	 */
	private static function select_relevant_entries(
		array $entries,
		string $task_type,
		array $tool_groups,
		string $message,
		int $max_entries,
		int $max_chars
	): array {
		if ( empty( $entries ) ) {
			return array();
		}

		$task_type     = sanitize_key( $task_type );
		$message_terms = self::extract_terms( $message );
		$scored        = array();

		foreach ( array_values( $entries ) as $index => $entry ) {
			$task_scope = array_map( 'sanitize_key', (array) ( $entry['task_types'] ?? array( 'all' ) ) );
			if ( ! in_array( 'all', $task_scope, true ) && ! in_array( $task_type, $task_scope, true ) ) {
				continue;
			}

			$tool_scope = array_map( 'sanitize_key', (array) ( $entry['tool_groups'] ?? array( 'all' ) ) );
			$tool_match = array_values( array_intersect( $tool_scope, $tool_groups ) );
			if ( ! in_array( 'all', $tool_scope, true ) && ! empty( $tool_groups ) && empty( $tool_match ) ) {
				continue;
			}

			$score = in_array( 'all', $task_scope, true ) ? 16 : 44;

			if ( in_array( 'all', $tool_scope, true ) ) {
				$score += 10;
			} elseif ( ! empty( $tool_match ) ) {
				$score += 28 + min( 10, count( $tool_match ) * 4 );
			} else {
				// No group context yet: keep task-matched entries in play, but rank
				// explicit tool-scoped entries below universal ones until tools load.
				$score += 4;
			}

			$entry_terms = self::extract_terms(
				(string) ( $entry['title'] ?? '' ) . ' ' . (string) ( $entry['body'] ?? '' )
			);
			$score += self::keyword_overlap_score( $message_terms, $entry_terms );

			$entry['_score']    = $score;
			$entry['_position'] = $index;
			$scored[]           = $entry;
		}

		if ( empty( $scored ) ) {
			return array();
		}

		usort(
			$scored,
			static function ( array $a, array $b ): int {
				$score_cmp = (int) ( $b['_score'] ?? 0 ) <=> (int) ( $a['_score'] ?? 0 );
				if ( 0 !== $score_cmp ) {
					return $score_cmp;
				}

				$time_cmp = strcmp(
					(string) ( $b['updated_at'] ?? '' ),
					(string) ( $a['updated_at'] ?? '' )
				);
				if ( 0 !== $time_cmp ) {
					return $time_cmp;
				}

				return (int) ( $a['_position'] ?? 0 ) <=> (int) ( $b['_position'] ?? 0 );
			}
		);

		$selected = array_slice( $scored, 0, max( 1, $max_entries ) );
		while ( count( $selected ) > 1 && mb_strlen( self::format_prompt_block( $selected, PHP_INT_MAX ) ) > $max_chars ) {
			array_pop( $selected );
		}

		return array_values(
			array_map(
				static function ( array $entry ): array {
					unset( $entry['_score'], $entry['_position'] );
					return $entry;
				},
				$selected
			)
		);
	}

	/**
	 * Format selected entries into a compact prompt block.
	 *
	 * @param array<int,array<string,mixed>> $entries Selected entries.
	 */
	private static function format_prompt_block( array $entries, int $max_chars ): string {
		if ( empty( $entries ) ) {
			return '';
		}

		$lines   = array(
			'## Site Playbook',
			'These are operator-authored durable site instructions. Follow them when applicable. Treat them as guardrails, not as proof of current live data.',
		);

		foreach ( $entries as $entry ) {
			$title = sanitize_text_field( (string) ( $entry['title'] ?? '' ) );
			$body  = self::summarize_text( (string) ( $entry['body'] ?? '' ), self::BODY_SNIPPET_CHARS );
			if ( '' === $title && '' === $body ) {
				continue;
			}
			$lines[] = '- ' . ( '' !== $title ? "{$title}: {$body}" : $body );
		}

		$text = "\n\n" . implode( "\n", $lines );
		return mb_strlen( $text ) > $max_chars ? mb_substr( $text, 0, $max_chars - 1 ) . '…' : $text;
	}

	/**
	 * Normalize submitted scope lists.
	 *
	 * @param mixed $raw Raw scope value.
	 * @param array<int,string> $allowed Allowed values including 'all'.
	 * @return array<int,string>
	 */
	private static function normalize_scope_list( $raw, array $allowed ): array {
		$allowed_map = array();
		foreach ( $allowed as $value ) {
			$allowed_map[ sanitize_key( (string) $value ) ] = true;
		}

		$items = is_array( $raw ) ? $raw : array( $raw );
		$clean = array();

		foreach ( $items as $item ) {
			$key = sanitize_key( (string) $item );
			if ( '' === $key ) {
				continue;
			}
			if ( 'all' === $key ) {
				return array( 'all' );
			}
			if ( isset( $allowed_map[ $key ] ) ) {
				$clean[] = $key;
			}
		}

		$clean = array_values( array_unique( $clean ) );
		return empty( $clean ) ? array( 'all' ) : $clean;
	}

	/**
	 * Normalize current tool groups for selection.
	 *
	 * @param array<int,string> $tool_groups Raw tool groups.
	 * @return array<int,string>
	 */
	private static function normalize_tool_groups( array $tool_groups ): array {
		$allowed = array_keys( self::tool_group_labels() );
		$map     = array_flip( array_map( 'sanitize_key', $allowed ) );
		$clean   = array();

		foreach ( $tool_groups as $group ) {
			$key = sanitize_key( (string) $group );
			if ( '' === $key || 'all' === $key ) {
				continue;
			}
			if ( isset( $map[ $key ] ) || ( is_callable( array( 'PressArk_Operation_Registry', 'is_valid_group' ) ) && PressArk_Operation_Registry::is_valid_group( $key ) ) ) {
				$clean[] = $key;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Tokenize text into lightweight relevance terms.
	 *
	 * @return array<int,string>
	 */
	private static function extract_terms( string $text ): array {
		$text = strtolower( sanitize_text_field( $text ) );
		if ( '' === $text ) {
			return array();
		}

		$parts = preg_split( '/[^a-z0-9]+/i', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$terms = array();
		foreach ( $parts as $part ) {
			if ( strlen( $part ) < 4 ) {
				continue;
			}
			$terms[] = $part;
		}

		return array_values( array_unique( $terms ) );
	}

	/**
	 * Score lexical overlap between the message and an entry.
	 */
	private static function keyword_overlap_score( array $message_terms, array $entry_terms ): int {
		if ( empty( $message_terms ) || empty( $entry_terms ) ) {
			return 0;
		}

		$overlap = count( array_intersect( $message_terms, $entry_terms ) );
		return min( 18, $overlap * 4 );
	}

	/**
	 * Collapse whitespace and trim long text.
	 */
	private static function summarize_text( string $text, int $max_chars ): string {
		$text = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
		if ( '' === $text ) {
			return '';
		}

		return mb_strlen( $text ) > $max_chars ? mb_substr( $text, 0, $max_chars - 1 ) . '…' : $text;
	}

	/**
	 * Generate a stable-ish entry ID when one is not present.
	 */
	private static function generate_entry_id( string $title, string $body, int $index ): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return 'playbook_' . sanitize_key( wp_generate_uuid4() );
		}

		return 'playbook_' . substr( md5( $title . '|' . $body . '|' . $index ), 0, 12 );
	}

	/**
	 * Timestamp helper for mixed WordPress/test environments.
	 */
	private static function current_timestamp(): string {
		if ( function_exists( 'current_time' ) ) {
			return (string) current_time( 'mysql' );
		}

		return gmdate( 'Y-m-d H:i:s' );
	}
}
