<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Create_Post extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'create_post',
			'description' => 'RECOMMENDED: Create a native WordPress page or post using blocks. Preferred method for all new designs, layouts, and content.',
			'params'      => array(
				array( 'name' => 'title', 'required' => true, 'desc' => 'Post or page title' ),
				array( 'name' => 'content', 'required' => false, 'desc' => 'HTML content' ),
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'post|page (default: post)' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'draft|publish|future (default: draft)' ),
				array( 'name' => 'scheduled_date', 'required' => false, 'desc' => 'Y-m-d H:i:s, server timezone' ),
				array( 'name' => 'slug', 'required' => false, 'desc' => 'Clean URL slug' ),
				array( 'name' => 'excerpt', 'required' => false, 'desc' => 'Manual excerpt or short summary' ),
				array( 'name' => 'meta_title', 'required' => false, 'desc' => 'SEO title using semantic key routing' ),
				array( 'name' => 'meta_description', 'required' => false, 'desc' => 'SEO meta description using semantic key routing' ),
				array( 'name' => 'og_title', 'required' => false, 'desc' => 'Open Graph title' ),
				array( 'name' => 'og_description', 'required' => false, 'desc' => 'Open Graph description' ),
				array( 'name' => 'og_image', 'required' => false, 'desc' => 'Open Graph image URL' ),
				array( 'name' => 'focus_keyword', 'required' => false, 'desc' => 'Primary SEO keyword/keyphrase' ),
				array( 'name' => 'page_template', 'required' => false, 'desc' => 'Template filename for pages' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}

	protected function prompt_weight(): int {
		return 10;
	}
}
