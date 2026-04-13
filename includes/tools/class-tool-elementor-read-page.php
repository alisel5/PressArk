<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Read_Page extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_read_page',
			'description' => 'Read Elementor page structure with widget IDs needed for editing. Use widget_type to filter output. Use max_depth to limit nesting depth for large pages.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'widget_type', 'required' => false, 'desc' => 'heading|button|image|text-editor|etc. — show only this widget type' ),
				array( 'name' => 'max_depth', 'required' => false, 'desc' => 'Max nesting depth to return (default: unlimited)' ),
			),
		);
	
	}
}
