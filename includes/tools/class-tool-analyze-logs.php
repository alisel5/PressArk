<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Analyze_Logs extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'analyze_logs',
			'description' => 'Analyze a log file: error counts, frequent errors, problematic plugins, fatals.',
			'params'      => array(
				array( 'name' => 'log', 'required' => false, 'desc' => 'Default: debug.log' ),
			),
		);
	
	}
}
