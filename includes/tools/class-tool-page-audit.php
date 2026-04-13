<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Page_Audit extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'page_audit',
			'description' => 'Comprehensive page audit: content + SEO + Elementor. Returns score and fix suggestions.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
			),
		);
	
	}
}
