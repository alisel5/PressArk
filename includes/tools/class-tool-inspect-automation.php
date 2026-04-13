<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Inspect_Automation extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'inspect_automation',
			'description' => 'Get automation details: prompt, schedule, last run result, and failure history.',
			'params'      => array(
				array( 'name' => 'automation_id', 'required' => true ),
			),
		);
	
	}
}
