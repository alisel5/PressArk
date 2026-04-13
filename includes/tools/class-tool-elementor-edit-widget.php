<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Edit_Widget extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_edit_widget',
			'description' => 'LAST RESORT: Minimally patch an existing Elementor widget only when user explicitly says "Elementor". Not recommended for new designs. Native WordPress blocks are preferred for better performance and control.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'widget_id', 'required' => true, 'desc' => 'From elementor_read_page' ),
				array( 'name' => 'changes', 'required' => false, 'desc' => 'Settings to change (natural language or Elementor keys)' ),
				array( 'name' => 'device', 'required' => false, 'desc' => 'desktop|tablet|mobile (default: desktop)' ),
				array( 'name' => 'field', 'required' => false, 'desc' => 'Repeater field name for item_index edits' ),
				array( 'name' => 'item_index', 'required' => false, 'desc' => '0-based repeater item index' ),
				array( 'name' => 'item_fields', 'required' => false, 'desc' => 'Fields for the repeater item' ),
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
