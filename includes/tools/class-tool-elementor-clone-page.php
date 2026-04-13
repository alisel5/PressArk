<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Clone_Page extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_clone_page',
			'description' => 'Duplicate an Elementor page with all content and settings. IDs regenerated.',
			'params'      => array(
				array( 'name' => 'source_id', 'required' => true ),
				array( 'name' => 'title', 'required' => false, 'desc' => 'Default: "[Original] (Copy)"' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'draft|publish|private (default: draft)' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
