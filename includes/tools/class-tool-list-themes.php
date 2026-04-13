<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Themes extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_themes',
			'description' => 'List installed themes with active status, version, block theme flag, compatibility.',
			'params'      => array(),
		);
	
	}
}
