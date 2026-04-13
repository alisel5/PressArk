<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Posts extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_posts',
			'description' => 'Query posts/pages with filters. Includes word count, sticky flag, post format. Supports pagination via offset. Returns _pagination metadata.',
			'params'      => array(
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'post|page|product|any (default: any)' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'publish|draft|any (default: any)' ),
				array( 'name' => 'search', 'required' => false ),
				array( 'name' => 'count', 'required' => false, 'desc' => 'Max results (default: 20, max: 50)' ),
				array( 'name' => 'offset', 'required' => false, 'desc' => 'Skip first N results for pagination (default: 0)' ),
				array( 'name' => 'needs_seo', 'required' => false, 'desc' => 'true = only posts missing SEO title' ),
				array( 'name' => 'min_words', 'required' => false ),
				array( 'name' => 'max_words', 'required' => false ),
				array( 'name' => 'modified_after', 'required' => false, 'desc' => 'Y-m-d date filter' ),
			),
		);
	
	}
}
