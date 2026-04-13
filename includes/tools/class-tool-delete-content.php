<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Delete_Content extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'delete_content',
			'description' => 'Move a post/page to trash.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
