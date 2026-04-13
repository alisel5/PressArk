<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Find_Widgets extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_find_widgets',
			'description' => 'Find Elementor widgets by type, content, or section. Returns IDs and settings.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'widget_type', 'required' => false, 'desc' => 'heading|button|image|text-editor|etc.' ),
				array( 'name' => 'search', 'required' => false ),
				array( 'name' => 'section_id', 'required' => false ),
			),
		);
	
	}
}
