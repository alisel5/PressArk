<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Approval-scoped checkpoint state.
 *
 * Stage 3 compatibility note:
 * This store preserves the legacy flat approval fields while making their
 * ownership explicit inside the checkpoint facade.
 */
class PressArk_Approval_State_Store {

	private array $approvals         = array();
	private array $approval_outcomes = array();
	private array $blockers          = array();

	public static function from_checkpoint_array( array $data ): self {
		$store                    = new self();
		$store->approvals         = self::sanitize_approvals( $data['approvals'] ?? array() );
		$store->approval_outcomes = self::sanitize_approval_outcomes( $data['approval_outcomes'] ?? array() );
		$store->blockers          = array_map( 'sanitize_text_field', array_slice( $data['blockers'] ?? array(), 0, 10 ) );

		return $store;
	}

	public function to_checkpoint_array(): array {
		return array(
			'approvals'         => $this->approvals,
			'approval_outcomes' => $this->approval_outcomes,
			'blockers'          => $this->blockers,
		);
	}

	public function is_empty(): bool {
		return empty( $this->approvals )
			&& empty( $this->approval_outcomes )
			&& empty( $this->blockers );
	}

	public function add_approval( string $action ): void {
		$action = sanitize_text_field( $action );
		if ( '' === $action || count( $this->approvals ) >= 10 ) {
			return;
		}

		foreach ( $this->approvals as $approval ) {
			if ( ( $approval['action'] ?? '' ) === $action ) {
				return;
			}
		}

		$this->approvals[] = array(
			'action'      => $action,
			'approved_at' => gmdate( 'c' ),
		);
		$this->record_approval_outcome(
			$action,
			class_exists( 'PressArk_Permission_Decision' ) ? PressArk_Permission_Decision::OUTCOME_APPROVED : 'approved',
			array(
				'source'      => 'approval',
				'reason_code' => 'approved',
			)
		);
	}

	public function merge_approvals( array $approvals ): void {
		foreach ( $approvals as $approval ) {
			if ( ! is_array( $approval ) ) {
				continue;
			}

			$action = sanitize_text_field( $approval['action'] ?? '' );
			if ( '' === $action ) {
				continue;
			}

			$exists = false;
			foreach ( $this->approvals as $existing ) {
				if ( ( $existing['action'] ?? '' ) === $action ) {
					$exists = true;
					break;
				}
			}
			if ( $exists || count( $this->approvals ) >= 10 ) {
				continue;
			}

			$this->approvals[] = array(
				'action'      => $action,
				'approved_at' => sanitize_text_field( $approval['approved_at'] ?? gmdate( 'c' ) ),
			);
			$this->record_approval_outcome(
				$action,
				class_exists( 'PressArk_Permission_Decision' ) ? PressArk_Permission_Decision::OUTCOME_APPROVED : 'approved',
				array(
					'source'      => 'approval',
					'reason_code' => 'approved',
					'recorded_at' => sanitize_text_field( $approval['approved_at'] ?? gmdate( 'c' ) ),
				)
			);
		}
	}

	public function get_approvals(): array {
		return $this->approvals;
	}

	public function record_approval_outcome( string $action, string $status, array $meta = array() ): void {
		if ( ! class_exists( 'PressArk_Permission_Decision' ) ) {
			return;
		}

		$action  = sanitize_key( $action );
		$outcome = PressArk_Permission_Decision::approval_outcome(
			$status,
			array_merge(
				$meta,
				array(
					'action' => $action,
				)
			)
		);
		if ( empty( $outcome ) ) {
			return;
		}

		$exists = false;
		foreach ( $this->approval_outcomes as $existing ) {
			if (
				( $existing['action'] ?? '' ) === ( $outcome['action'] ?? '' )
				&& ( $existing['status'] ?? '' ) === ( $outcome['status'] ?? '' )
				&& ( $existing['source'] ?? '' ) === ( $outcome['source'] ?? '' )
			) {
				$exists = true;
				break;
			}
		}

		if ( $exists ) {
			return;
		}

		$this->approval_outcomes[] = $outcome;
		$this->approval_outcomes   = array_slice( $this->approval_outcomes, -12 );
	}

	public function merge_approval_outcomes( array $outcomes ): void {
		foreach ( $outcomes as $outcome ) {
			if ( ! is_array( $outcome ) ) {
				continue;
			}

			$this->record_approval_outcome(
				(string) ( $outcome['action'] ?? '' ),
				(string) ( $outcome['status'] ?? '' ),
				$outcome
			);
		}
	}

	public function get_approval_outcomes(): array {
		return $this->approval_outcomes;
	}

	public function add_blocker( string $blocker ): void {
		$blocker = sanitize_text_field( $blocker );
		if ( '' === $blocker || count( $this->blockers ) >= 10 ) {
			return;
		}

		if ( in_array( $blocker, $this->blockers, true ) ) {
			return;
		}

		$this->blockers[] = $blocker;
	}

	public function merge_blockers( array $blockers ): void {
		foreach ( $blockers as $blocker ) {
			$this->add_blocker( (string) $blocker );
		}
	}

	public function clear_blockers(): void {
		$this->blockers = array();
	}

	public function get_blockers(): array {
		return $this->blockers;
	}

	private static function sanitize_approvals( array $raw ): array {
		$clean = array();
		foreach ( array_slice( $raw, 0, 10 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$clean[] = array(
				'action'      => sanitize_text_field( $item['action'] ?? '' ),
				'approved_at' => sanitize_text_field( $item['approved_at'] ?? '' ),
			);
		}

		return $clean;
	}

	private static function sanitize_approval_outcomes( array $raw ): array {
		if ( ! class_exists( 'PressArk_Permission_Decision' ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_slice( $raw, -12 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$outcome = PressArk_Permission_Decision::normalize_approval_outcome( $item );
			if ( ! empty( $outcome ) ) {
				$clean[] = $outcome;
			}
		}

		return $clean;
	}
}
