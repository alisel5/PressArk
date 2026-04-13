<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Read_Content extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'read_content',
			'description' => 'Read post/page by ID, URL, or slug. Modes: summary/light (default), detail/structured, raw/full. Use section to trim raw reads.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => false ),
				array( 'name' => 'url', 'required' => false ),
				array( 'name' => 'slug', 'required' => false ),
				array( 'name' => 'mode', 'required' => false, 'desc' => 'summary|detail|raw or light|structured|full (default: summary/light)' ),
				array( 'name' => 'section', 'required' => false, 'desc' => 'head|tail|first_n_paragraphs — trim full-mode content to reduce size' ),
				array( 'name' => 'paragraphs', 'required' => false, 'desc' => 'Number of paragraphs for first_n_paragraphs section (default: 5)' ),
			),
		);
	
	}
}
