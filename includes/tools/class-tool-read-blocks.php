<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Read_Blocks extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'read_blocks',
			'description' => 'Hint: Call before editing blocks; edit individual blocks, never rewrite post_content. Read Gutenberg block tree with indexes.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
			),
		);
	
	}
}
