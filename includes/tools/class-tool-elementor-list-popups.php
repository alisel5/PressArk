<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_List_Popups extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_list_popups',
			'description' => 'List Elementor Pro popups with trigger config and display conditions.',
			'params'      => array(
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Default: 20, max: 50' ),
			),
		);
	
	}
}
