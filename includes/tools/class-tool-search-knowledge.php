<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Search_Knowledge extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'search_knowledge',
			'description' => 'Search the site content index. Faster than read_content for finding information.',
			'params'      => array(
				array( 'name' => 'query', 'required' => true ),
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'page|post|product (default: all)' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => '1-10 (default: 5)' ),
			),
		);
	
	}
}
