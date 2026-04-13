<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Search_Content extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'search_content',
			'description' => 'Search posts/pages by keyword. Supports date/meta filtering and pagination via offset. Returns _pagination metadata.',
			'params'      => array(
				array( 'name' => 'query', 'required' => true ),
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'post|page|any (default: any)' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Max results (default: 20, max: 100)' ),
				array( 'name' => 'offset', 'required' => false, 'desc' => 'Skip first N results for pagination (default: 0)' ),
				array( 'name' => 'after', 'required' => false, 'desc' => 'Published after date (strtotime-compatible)' ),
				array( 'name' => 'before', 'required' => false, 'desc' => 'Published before date (strtotime-compatible)' ),
				array( 'name' => 'meta_key', 'required' => false ),
				array( 'name' => 'meta_value', 'required' => false ),
				array( 'name' => 'meta_compare', 'required' => false, 'desc' => '=|!=|>|<|LIKE|EXISTS|NOT EXISTS|IN|BETWEEN' ),
			),
		);
	
	}
}
