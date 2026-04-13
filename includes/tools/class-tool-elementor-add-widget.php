<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Add_Widget extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_add_widget',
			'description' => 'LAST RESORT: Add a widget to an existing Elementor page only when site is built entirely in Elementor and user confirms. Prefers native blocks.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'widget_type', 'required' => true ),
				array( 'name' => 'settings', 'required' => false, 'desc' => 'Natural language or Elementor keys' ),
				array( 'name' => 'container_id', 'required' => false, 'desc' => 'Target container (default: first found)' ),
				array( 'name' => 'position', 'required' => false, 'desc' => '0-based index, -1 = append' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}

	protected function prompt_weight(): int {
		return -10;
	}
}
