<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_List_Templates extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_list_templates',
			'description' => 'List all saved Elementor templates with types.',
			'params'      => array(),
		);
	
	}
}
