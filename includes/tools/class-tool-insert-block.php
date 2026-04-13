<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Insert_Block extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'insert_block',
			'description' => 'Insert a Gutenberg block at a specific position without touching existing content.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'block_type', 'required' => true, 'desc' => 'core/paragraph|core/heading|core/image|core/button|core/list|core/separator|core/spacer|core/html' ),
				array( 'name' => 'attrs', 'required' => false, 'desc' => 'Block attributes' ),
				array( 'name' => 'content', 'required' => false, 'desc' => 'Inner HTML' ),
				array( 'name' => 'position', 'required' => false, 'desc' => '-1 = append at end' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}
}
