<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Analyze_Seo extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'analyze_seo',
			'description' => 'Deep SEO analysis with subscores (indexing_health, search_appearance, content_quality, social_sharing) for a single page or full site. Use limit/offset to paginate site-wide scans.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true, 'desc' => 'Post ID or "all" for site-wide scan' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Max pages to scan in site-wide mode (default: 50, max: 100)' ),
				array( 'name' => 'offset', 'required' => false, 'desc' => 'Skip first N pages in site-wide scan (default: 0)' ),
			),
		);
	
	}
}
