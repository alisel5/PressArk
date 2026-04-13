<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Clear_Log extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'clear_log',
			'description' => 'Clear/truncate a log file. Only debug.log supported.',
			'params'      => array(
				array( 'name' => 'log', 'required' => true, 'desc' => 'Only "debug.log" supported' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
