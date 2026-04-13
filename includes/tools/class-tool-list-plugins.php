<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Plugins extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_plugins',
			'description' => 'List installed WordPress plugins with status, version, and updates.',
			'params'      => array(),
		);
	
	}
}
