<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Edit_Block extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'edit_block',
			'description' => 'Hint: Dynamic blocks (is_dynamic=true): only change attributes, not content. Surgical Gutenberg block edit by index.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => false ),
				array( 'name' => 'url', 'required' => false ),
				array( 'name' => 'slug', 'required' => false ),
				array( 'name' => 'block_index', 'required' => true, 'desc' => 'From read_blocks. "2.1" for inner blocks.' ),
				array( 'name' => 'updates', 'required' => true, 'desc' => '"content" for text, or attribute names' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}
}
