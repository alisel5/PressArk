<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Toggle_Automation extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'toggle_automation',
			'description' => 'Pause or resume a scheduled automation.',
			'params'      => array(
				array( 'name' => 'automation_id', 'required' => true ),
				array( 'name' => 'action', 'required' => true, 'desc' => 'pause|resume' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
