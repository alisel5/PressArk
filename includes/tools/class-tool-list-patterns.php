<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Patterns extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_patterns',
			'description' => 'List registered block patterns with names, categories, and composition.',
			'params'      => array(
				array( 'name' => 'category', 'required' => false, 'desc' => 'Pattern category slug filter' ),
				array( 'name' => 'search', 'required' => false ),
			),
		);
	
	}
}
