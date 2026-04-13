<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Logs extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_logs',
			'description' => 'List available log files with sizes and last modified dates.',
			'params'      => array(),
		);
	
	}
}
