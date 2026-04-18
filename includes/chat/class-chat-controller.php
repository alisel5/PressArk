<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-pressark-orchestration-service.php';

/**
 * Thin transport/controller facade for chat REST endpoints.
 */
class PressArk_Chat_Controller extends PressArk_Orchestration_Service {

	public function __construct( ?PressArk_Request_Compiler $request_compiler = null ) {
		parent::__construct( $request_compiler );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		parent::register_routes();
	}

	public function check_permissions(): bool {
		return parent::check_permissions();
	}

	public function validate_conversation( $value, WP_REST_Request $request, string $param ) {
		return parent::validate_conversation( $value, $request, $param );
	}

	public function validate_checkpoint( $value, WP_REST_Request $request, string $param ) {
		return parent::validate_checkpoint( $value, $request, $param );
	}

	public function handle_chat( WP_REST_Request $request ): WP_REST_Response {
		return parent::handle_chat( $request );
	}

	public function handle_chat_stream( WP_REST_Request $request ): void {
		parent::handle_chat_stream( $request );
	}

	public function handle_plan_execute( WP_REST_Request $request ): WP_REST_Response {
		return parent::handle_plan_execute( $request );
	}

	public function handle_plan_execute_stream( WP_REST_Request $request ): void {
		parent::handle_plan_execute_stream( $request );
	}

	public function handle_plan_approve( WP_REST_Request $request ): WP_REST_Response {
		return parent::handle_plan_approve( $request );
	}

	public function handle_plan_approve_stream( WP_REST_Request $request ): void {
		parent::handle_plan_approve_stream( $request );
	}

	public function handle_plan_revise( WP_REST_Request $request ): WP_REST_Response {
		return parent::handle_plan_revise( $request );
	}

	public function handle_plan_reject( WP_REST_Request $request ): WP_REST_Response {
		return parent::handle_plan_reject( $request );
	}

	public function handle_confirm_stream( WP_REST_Request $request ): void {
		parent::handle_confirm_stream( $request );
	}

	public function handle_confirm( WP_REST_Request $request ): WP_REST_Response {
		return parent::handle_confirm( $request );
	}

	public function handle_preview_keep( WP_REST_Request $request ): WP_REST_Response {
		return parent::handle_preview_keep( $request );
	}

	public function handle_preview_discard( WP_REST_Request $request ): WP_REST_Response {
		return parent::handle_preview_discard( $request );
	}
}
