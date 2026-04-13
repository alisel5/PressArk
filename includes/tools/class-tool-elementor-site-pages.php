<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Site_Pages extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_site_pages',
			'description' => 'List all Elementor pages across the site with metadata.',
			'params'      => array(
				array( 'name' => 'post_type', 'required' => false ),
				array( 'name' => 'with_issues', 'required' => false, 'desc' => 'true = include issue counts (slower)' ),
			),
		);
	
	}
}
