<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Insert_Pattern extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'insert_pattern',
			'description' => 'Insert a block pattern into a post at a specified position.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'pattern', 'required' => true, 'desc' => 'Pattern name from list_patterns' ),
				array( 'name' => 'position', 'required' => false, 'desc' => '-1 = append at end' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}
}
