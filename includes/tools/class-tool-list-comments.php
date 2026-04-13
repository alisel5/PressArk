<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Comments extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_comments',
			'description' => 'List comments with filters and aggregate counts. Pingbacks excluded by default.',
			'params'      => array(
				array( 'name' => 'status', 'required' => false, 'desc' => 'approve|hold|spam|trash|all (default: all)' ),
				array( 'name' => 'post_id', 'required' => false ),
				array( 'name' => 'search', 'required' => false ),
				array( 'name' => 'author_email', 'required' => false ),
				array( 'name' => 'include_pingbacks', 'required' => false, 'desc' => 'true to include pingbacks (default: false)' ),
				array( 'name' => 'count', 'required' => false, 'desc' => 'Max results (default: 20, max: 50)' ),
			),
		);
	
	}
}
