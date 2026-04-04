<?php
/**
 * PressArk Verification — Evidence-based completion contracts.
 *
 * Lightweight orchestrator that determines when and how to verify write
 * operations, builds read-back tool calls, evaluates results, and produces
 * model nudges that push the AI toward evidence-based completion.
 *
 * Verification is per-operation and tiered:
 * - 'none':            No automated verification (reads, low-risk writes).
 * - 'existence_check': Verify the created resource exists (create_post, create_product).
 * - 'field_check':     Verify specific fields match expected values (update_site_settings).
 * - 'read_back':       Full read-back of the affected resource (elementor, woocommerce).
 *
 * @package PressArk
 * @since   5.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Verification {

	/**
	 * Get the verification policy for an operation after it succeeds.
	 *
	 * Returns null if no verification is warranted (read tool, no policy,
	 * or the required read tool is not available).
	 *
	 * @param string $tool_name Write tool that just executed.
	 * @param array  $result    Execution result from the handler.
	 * @return array|null Verification policy or null.
	 */
	public static function get_policy( string $tool_name, array $result ): ?array {
		// No verification for failed writes.
		if ( empty( $result['success'] ) && empty( $result['skipped_duplicate'] ) ) {
			return null;
		}

		$op = PressArk_Operation_Registry::resolve( $tool_name );
		if ( ! $op || ! $op->has_verification() ) {
			return null;
		}

		$policy = $op->get_verification();

		// Strategy 'none' with nudge=true means: no automated read-back, but
		// the model should be reminded to verify manually.
		if ( 'none' === $policy['strategy'] ) {
			return ! empty( $policy['nudge'] ) ? $policy : null;
		}

		// Verify the read tool is available (e.g., Elementor might not be active).
		$read_tool = $policy['read_tool'] ?? '';
		if ( '' !== $read_tool && ! PressArk_Operation_Registry::exists( $read_tool ) ) {
			// Degrade: can't verify without the read tool.
			// Return policy with strategy demoted to 'none' but nudge preserved.
			return array_merge( $policy, array( 'strategy' => 'none' ) );
		}

		// Check plugin requirement of the read tool.
		if ( '' !== $read_tool ) {
			$read_op = PressArk_Operation_Registry::resolve( $read_tool );
			if ( $read_op && $read_op->requires ) {
				if ( ! self::is_plugin_active( $read_op->requires ) ) {
					return array_merge( $policy, array( 'strategy' => 'none' ) );
				}
			}
		}

		return $policy;
	}

	/**
	 * Build a read-back tool call from the verification policy and write result.
	 *
	 * Extracts the target ID (post_id, order_id, product_id) from the write
	 * result and combines it with the policy's read_tool and read_args.
	 *
	 * @param array $policy       Verification policy from get_policy().
	 * @param array $write_result Result from the write handler.
	 * @param array $write_args   Original arguments passed to the write tool.
	 * @return array|null Tool call array {name, arguments} or null if not possible.
	 */
	public static function build_readback( array $policy, array $write_result, array $write_args = array() ): ?array {
		$strategy  = $policy['strategy'] ?? 'none';
		$read_tool = $policy['read_tool'] ?? '';

		if ( 'none' === $strategy || '' === $read_tool ) {
			return null;
		}

		$args = $policy['read_args'] ?? array();

		// Extract the target identifier from the write result.
		$post_id    = absint( $write_result['post_id'] ?? $write_result['data']['id'] ?? 0 );
		$order_id   = absint( $write_result['order_id'] ?? 0 );
		$product_id = absint( $write_result['product_id'] ?? 0 );

		// Fallback to write args if result doesn't carry the ID.
		if ( ! $post_id && ! $order_id && ! $product_id ) {
			$post_id    = absint( $write_args['post_id'] ?? $write_args['id'] ?? 0 );
			$order_id   = absint( $write_args['order_id'] ?? 0 );
			$product_id = absint( $write_args['product_id'] ?? 0 );
		}

		// Route by read tool type.
		switch ( $read_tool ) {
			case 'read_content':
				if ( $post_id > 0 ) {
					$args['post_id'] = $post_id;
				} else {
					return null; // Can't verify without a target.
				}
				break;

			case 'get_product':
				$id = $product_id ?: $post_id;
				if ( $id > 0 ) {
					$args['product_id'] = $id;
				} else {
					return null;
				}
				break;

			case 'get_order':
				$id = $order_id ?: $post_id;
				if ( $id > 0 ) {
					$args['order_id'] = $id;
				} else {
					return null;
				}
				break;

			case 'elementor_read_page':
				if ( $post_id > 0 ) {
					$args['post_id'] = $post_id;
				} else {
					return null;
				}
				break;

			case 'get_site_settings':
			case 'list_themes':
			case 'list_plugins':
			case 'scan_security':
			case 'get_theme_settings':
			case 'elementor_get_styles':
			case 'get_templates':
				// These don't need a target ID — they're global reads.
				break;

			default:
				// Unknown read tool — try to pass post_id if available.
				if ( $post_id > 0 ) {
					$args['post_id'] = $post_id;
				}
				break;
		}

		return array(
			'name'      => $read_tool,
			'arguments' => $args,
		);
	}

	/**
	 * Compare write intent vs read-back result to determine verification status.
	 *
	 * For 'existence_check': passes if the read-back succeeded.
	 * For 'field_check': passes if all check_fields match.
	 * For 'read_back': passes if the read-back succeeded (detailed checks are
	 * left to the model; this provides the evidence).
	 *
	 * @param array $policy         Verification policy.
	 * @param array $write_args     Original write arguments (intent).
	 * @param array $readback_result Read-back tool result.
	 * @return array{passed: bool, evidence: string, mismatches: array}
	 */
	public static function evaluate( array $policy, array $write_args, array $readback_result ): array {
		$strategy     = $policy['strategy'] ?? 'none';
		$check_fields = $policy['check_fields'] ?? array();
		$mismatches   = array();

		// Basic success check — did the read-back itself succeed?
		$read_success = ! empty( $readback_result['success'] )
			|| ! empty( $readback_result['data'] )
			|| ( isset( $readback_result['id'] ) && $readback_result['id'] > 0 );

		if ( ! $read_success ) {
			return array(
				'passed'     => false,
				'evidence'   => 'Read-back failed: ' . sanitize_text_field( $readback_result['message'] ?? 'resource not found' ),
				'mismatches' => array( 'read_back_failed' ),
			);
		}

		// For existence_check, success of the read-back is sufficient.
		if ( 'existence_check' === $strategy ) {
			$evidence = self::build_evidence_string( $readback_result );
			return array(
				'passed'     => true,
				'evidence'   => $evidence,
				'mismatches' => array(),
			);
		}

		// For field_check and read_back, compare specific fields if declared.
		if ( ! empty( $check_fields ) ) {
			$data = $readback_result['data'] ?? $readback_result;

			foreach ( $check_fields as $field ) {
				$expected = $write_args[ $field ] ?? $write_args['changes'][ $field ] ?? null;
				$actual   = $data[ $field ] ?? null;

				// Skip fields not in the write intent (nothing to compare).
				if ( null === $expected ) {
					continue;
				}

				// Normalize for comparison.
				$expected_normalized = is_scalar( $expected ) ? (string) $expected : wp_json_encode( $expected );
				$actual_normalized   = is_scalar( $actual ) ? (string) $actual : wp_json_encode( $actual );

				if ( $expected_normalized !== $actual_normalized ) {
					$mismatches[] = array(
						'field'    => $field,
						'expected' => $expected_normalized,
						'actual'   => $actual_normalized,
					);
				}
			}
		}

		$passed   = empty( $mismatches );
		$evidence = self::build_evidence_string( $readback_result );

		if ( ! $passed ) {
			$mismatch_parts = array();
			foreach ( $mismatches as $m ) {
				$mismatch_parts[] = $m['field'] . ': expected ' . self::truncate( $m['expected'], 60 )
					. ', found ' . self::truncate( $m['actual'], 60 );
			}
			$evidence .= ' Mismatches: ' . implode( '; ', $mismatch_parts );
		}

		return array(
			'passed'     => $passed,
			'evidence'   => $evidence,
			'mismatches' => $mismatches,
		);
	}

	/**
	 * Build a model nudge string for a write operation result.
	 *
	 * Two modes:
	 * 1. Automated verification completed — include evidence in the nudge.
	 * 2. No automated verification but nudge=true — remind model to verify manually.
	 *
	 * @param string     $tool_name   Write tool name.
	 * @param array      $result      Write handler result.
	 * @param array|null $eval_result Evaluation result from evaluate(), or null if no read-back.
	 * @return string Nudge to append to tool result (empty if no nudge needed).
	 */
	public static function build_nudge( string $tool_name, array $result, ?array $eval_result = null ): string {
		$policy = self::get_policy( $tool_name, $result );
		if ( ! $policy ) {
			return '';
		}

		$op = PressArk_Operation_Registry::resolve( $tool_name );

		// Case 1: We have evaluation results from an automated read-back.
		if ( null !== $eval_result ) {
			if ( ! empty( $eval_result['passed'] ) ) {
				return "\n\nVERIFICATION: " . $tool_name . ' applied successfully.'
					. "\nEvidence: " . sanitize_text_field( $eval_result['evidence'] ?? 'read-back confirmed' )
					. "\nStatus: VERIFIED";
			}

			return "\n\nVERIFICATION: " . $tool_name . ' applied but read-back shows mismatch.'
				. "\nEvidence: " . sanitize_text_field( $eval_result['evidence'] ?? 'verification failed' )
				. "\nStatus: UNCERTAIN — confirm the change took effect before reporting completion.";
		}

		// Case 2: Nudge-only (no automated read-back, e.g., email operations or
		// degraded verification when plugin is not active).
		if ( empty( $policy['nudge'] ) ) {
			return '';
		}

		$group = $op ? $op->group : 'unknown';
		return "\n\nNOTE: This is a high-risk operation (" . $group . '). Before reporting completion, verify the result with a read tool.';
	}

	// ── Internal helpers ────────────────────────────────────────────────

	/**
	 * Build a compact evidence string from a read-back result.
	 */
	private static function build_evidence_string( array $result ): string {
		$data = $result['data'] ?? $result;
		$parts = array();

		// Common fields across different result types.
		$evidence_fields = array(
			'title'          => 'title',
			'name'           => 'name',
			'status'         => 'status',
			'post_status'    => 'status',
			'regular_price'  => 'price',
			'price'          => 'price',
			'stock_quantity' => 'stock',
			'stock_status'   => 'stock_status',
			'type'           => 'type',
			'total'          => 'total',
		);

		foreach ( $evidence_fields as $source => $label ) {
			if ( isset( $data[ $source ] ) && '' !== (string) $data[ $source ] ) {
				$value = is_scalar( $data[ $source ] ) ? (string) $data[ $source ] : wp_json_encode( $data[ $source ] );
				$parts[] = $label . '=' . self::truncate( $value, 40 );
			}
		}

		$id = absint( $data['id'] ?? $data['post_id'] ?? $data['order_id'] ?? $data['product_id'] ?? 0 );
		if ( $id > 0 ) {
			array_unshift( $parts, '#' . $id );
		}

		if ( empty( $parts ) ) {
			return 'Resource exists and is readable.';
		}

		return implode( ', ', $parts );
	}

	/**
	 * Check if a required plugin is active.
	 */
	private static function is_plugin_active( string $slug ): bool {
		switch ( $slug ) {
			case 'woocommerce':
				return class_exists( 'WooCommerce' );
			case 'elementor':
				return defined( 'ELEMENTOR_VERSION' );
			default:
				return true; // Unknown requirement — assume available.
		}
	}

	/**
	 * Truncate a string for display in evidence.
	 */
	private static function truncate( string $text, int $max ): string {
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		return mb_substr( $text, 0, $max - 3 ) . '...';
	}
}
