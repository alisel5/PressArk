<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Run_Automation_Now extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'run_automation_now',
			'description' => 'Trigger an immediate run of an automation regardless of schedule.',
			'params'      => array(
				array( 'name' => 'automation_id', 'required' => true ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
