<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Best-effort pre-execution permission probing for likely tool candidates.
 */
class PressArk_Permission_Probe {

	/**
	 * Probe the most likely tool candidates before reservation.
	 *
	 * @return array<string,mixed>
	 */
	public function probe( PressArk_Request_Context $context ): array {
		if ( ! class_exists( 'PressArk_Tool_Catalog' ) || ! class_exists( 'PressArk_Tools' ) ) {
			return array();
		}

		if ( class_exists( 'PressArk_Agent' ) && PressArk_Agent::is_lightweight_chat_request( $context->message ) ) {
			return array();
		}

		$matches = PressArk_Tool_Catalog::instance()->discover( $context->message );
		if ( empty( $matches ) || ! is_array( $matches ) ) {
			return array();
		}

		$user_id      = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$first_denied = array();

		foreach ( array_slice( $matches, 0, 5 ) as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			$tool_name = sanitize_key( (string) ( $candidate['name'] ?? '' ) );
			if ( '' === $tool_name ) {
				continue;
			}

			$tool = PressArk_Tools::get_tool( $tool_name );
			if ( ! is_object( $tool ) || ! method_exists( $tool, 'check_permissions' ) ) {
				continue;
			}

			$params = array();
			if ( $context->post_id > 0 && ! isset( $params['post_id'], $params['id'], $params['product_id'], $params['order_id'] ) ) {
				$params['post_id'] = $context->post_id;
			}

			$permission = $tool->check_permissions( $params, $user_id, $context->tier );
			if ( ! is_array( $permission ) ) {
				$permission = array();
			}

			$permission['tool_name']        = $tool_name;
			$permission['tool_description'] = sanitize_text_field( (string) ( $candidate['description'] ?? '' ) );
			$permission['group']            = sanitize_key( (string) ( $candidate['group'] ?? '' ) );
			$permission['intent']           = $tool->is_readonly() ? 'read' : 'write';

			if ( ! empty( $permission['allowed'] ) ) {
				return $permission;
			}

			if ( $this->should_defer_permission_ask_to_write_approval( $permission, $tool ) ) {
				continue;
			}

			if ( empty( $first_denied ) ) {
				$first_denied = $permission;
			}
		}

		return $first_denied;
	}

	/**
	 * Interactive write asks should fall through to preview/confirm UI.
	 *
	 * @param array<string,mixed> $permission
	 * @param mixed               $tool
	 */
	private function should_defer_permission_ask_to_write_approval( array $permission, $tool ): bool {
		$behavior = sanitize_key( (string) ( $permission['behavior'] ?? '' ) );
		if ( 'ask' !== $behavior ) {
			return false;
		}

		$ui_action = sanitize_key( (string) ( $permission['ui_action'] ?? '' ) );
		if ( ! in_array( $ui_action, array( 'preview', 'confirm' ), true ) ) {
			return false;
		}

		return is_object( $tool )
			&& method_exists( $tool, 'is_readonly' )
			&& ! $tool->is_readonly();
	}
}
