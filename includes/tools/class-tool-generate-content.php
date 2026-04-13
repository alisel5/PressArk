<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Generate_Content extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'generate_content',
			'description' => 'Generate AI content for review. Does NOT publish — use edit_content/create_post to apply.',
			'params'      => array(
				array( 'name' => 'type', 'required' => true, 'desc' => 'blog_post|product_description|page_content|email_draft|social_media|meta_tags|custom' ),
				array( 'name' => 'topic', 'required' => true ),
				array( 'name' => 'tone', 'required' => false, 'desc' => 'professional|casual|friendly|formal|humorous|technical|persuasive (default: professional)' ),
				array( 'name' => 'length', 'required' => false, 'desc' => 'short|medium|long|detailed (default: medium)' ),
				array( 'name' => 'keywords', 'required' => false, 'desc' => 'Array of SEO keywords' ),
				array( 'name' => 'target_audience', 'required' => false ),
				array( 'name' => 'reference_post_id', 'required' => false, 'desc' => 'Post ID for style reference' ),
				array( 'name' => 'additional_instructions', 'required' => false ),
			),
		);
	
	}
}
