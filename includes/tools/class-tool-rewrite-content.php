<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Rewrite_Content extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'rewrite_content',
			'description' => 'Rewrite or improve existing post content. Returns new version for review.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'instructions', 'required' => false, 'desc' => 'improve|expand|simplify|seo_optimize|change_tone|custom (default: improve)' ),
				array( 'name' => 'tone', 'required' => false ),
				array( 'name' => 'keywords', 'required' => false, 'desc' => 'SEO keywords to weave in' ),
				array( 'name' => 'preserve_structure', 'required' => false, 'desc' => 'Keep headings/sections (default: true)' ),
			),
		);
	
	}
}
