<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Inspect_Hooks extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'inspect_hooks',
			'description' => 'Inspect what is hooked to a WordPress action/filter for diagnosing conflicts. Use pattern to filter callbacks and limit to cap results.',
			'type'        => 'read',
			'params'      => array(
				array( 'name' => 'hook_name', 'type' => 'string', 'required' => true ),
				array( 'name' => 'pattern', 'required' => false, 'desc' => 'Filter callbacks by name pattern (substring match)' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Max callbacks to return (default: 50)' ),
			),
		);
	
	}
}
