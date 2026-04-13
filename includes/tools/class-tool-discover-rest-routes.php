<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Discover_Rest_Routes extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'discover_rest_routes',
			'description' => 'Hint: Call before call_rest_endpoint. Discover REST API endpoints grouped by namespace.',
			'type'        => 'read',
			'params'      => array(
				array( 'name' => 'namespace', 'required' => false, 'desc' => 'Filter to namespace (default: all summary)' ),
			),
		);
	
	}
}
