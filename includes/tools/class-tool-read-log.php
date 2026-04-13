<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Read_Log extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'read_log',
			'description' => 'Read recent log entries with parsed error levels and timestamps.',
			'params'      => array(
				array( 'name' => 'log', 'required' => false, 'desc' => 'debug.log|php|wc/{name}.log|error.log|access.log (default: debug.log)' ),
				array( 'name' => 'lines', 'required' => false, 'desc' => 'Max 200 (default: 50)' ),
				array( 'name' => 'filter', 'required' => false, 'desc' => 'Keyword filter' ),
			),
		);
	
	}
}
