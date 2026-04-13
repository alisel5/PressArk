<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Optimize_Database extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'optimize_database',
			'description' => 'Run OPTIMIZE TABLE on WordPress database tables.',
			'params'      => array(),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
