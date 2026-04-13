<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Find_Replace extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_find_replace',
			'description' => 'Find and replace text across all Elementor pages, templates, and headers/footers.',
			'params'      => array(
				array( 'name' => 'find', 'required' => true, 'desc' => 'Case-insensitive text' ),
				array( 'name' => 'replace', 'required' => true ),
				array( 'name' => 'post_id', 'required' => false, 'desc' => 'Limit to one page (default: all)' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}
}
