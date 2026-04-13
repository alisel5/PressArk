<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Set_Dynamic_Tag extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_set_dynamic_tag',
			'description' => 'Connect a widget field to an Elementor dynamic tag (post title, ACF, date, etc.).',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'widget_id', 'required' => true, 'desc' => 'From elementor_read_page' ),
				array( 'name' => 'field', 'required' => true, 'desc' => 'Widget field to connect' ),
				array( 'name' => 'tag_name', 'required' => true, 'desc' => 'From elementor_list_dynamic_tags' ),
				array( 'name' => 'tag_settings', 'required' => false, 'desc' => 'Tag config object' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}
}
