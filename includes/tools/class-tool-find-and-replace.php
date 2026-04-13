<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Find_And_Replace extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'find_and_replace',
			'description' => 'Find and replace text across posts/pages. Dry run first, then apply.',
			'params'      => array(
				array( 'name' => 'find', 'required' => true, 'desc' => 'Case-insensitive text to find' ),
				array( 'name' => 'replace', 'required' => true ),
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'Default: any' ),
				array( 'name' => 'search_in', 'required' => false, 'desc' => 'content|title|both|all (default: content)' ),
				array( 'name' => 'dry_run', 'required' => false, 'desc' => 'true = preview only (default: true)' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
