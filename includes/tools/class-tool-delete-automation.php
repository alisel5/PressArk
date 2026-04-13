<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Delete_Automation extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'delete_automation',
			'description' => 'Permanently delete a scheduled automation.',
			'params'      => array(
				array( 'name' => 'automation_id', 'required' => true ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
