<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Automations extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_automations',
			'description' => 'List scheduled automations: ID, name, status, cadence, next run.',
			'params'      => array(
				array( 'name' => 'status', 'required' => false, 'desc' => 'active|paused|failed (default: all)' ),
			),
		);
	
	}
}
