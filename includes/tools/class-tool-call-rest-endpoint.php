<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Call_Rest_Endpoint extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'call_rest_endpoint',
			'description' => 'Hint: POST/PUT/DELETE goes through confirm card. Call any WP REST API endpoint internally.',
			'params'      => array(
				array( 'name' => 'route', 'required' => true, 'desc' => 'REST route from discover_rest_routes' ),
				array( 'name' => 'method', 'required' => false, 'desc' => 'GET|POST|PUT|PATCH|DELETE (default: GET)' ),
				array( 'name' => 'params', 'required' => false, 'desc' => 'Query params for GET, body for POST/PUT/PATCH' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
