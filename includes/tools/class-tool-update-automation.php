<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_Automation extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_automation',
			'description' => 'Update an existing automation. Only provided fields change.',
			'params'      => array(
				array( 'name' => 'automation_id', 'required' => true ),
				array( 'name' => 'name', 'required' => false ),
				array( 'name' => 'prompt', 'required' => false ),
				array( 'name' => 'cadence_type', 'required' => false ),
				array( 'name' => 'cadence_value', 'required' => false ),
				array( 'name' => 'timezone', 'required' => false ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
