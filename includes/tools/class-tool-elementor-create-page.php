<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Create_Page extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_create_page',
			'description' => 'Create a new Elementor page only if user explicitly requests Elementor. Default to native WordPress pages for new content.',
			'params'      => array(
				array( 'name' => 'title', 'required' => true ),
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'page|post (default: page)' ),
				array( 'name' => 'template', 'required' => false, 'desc' => 'default|canvas|full-width' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'draft|publish (default: draft)' ),
				array( 'name' => 'parent', 'required' => false, 'desc' => 'Parent page ID (pages only)' ),
				array( 'name' => 'widgets', 'required' => false, 'desc' => 'Array of {type, settings}. Types: heading, text-editor, button, image, spacer, divider' ),
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
