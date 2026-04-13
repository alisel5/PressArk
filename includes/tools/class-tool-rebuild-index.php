<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Rebuild_Index extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'rebuild_index',
			'description' => 'Force a full rebuild of the content index.',
			'params'      => array(),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
