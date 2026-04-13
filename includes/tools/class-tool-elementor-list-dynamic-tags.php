<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_List_Dynamic_Tags extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_list_dynamic_tags',
			'description' => 'List available Elementor dynamic tags grouped by category.',
			'params'      => array(),
		);
	
	}
}
