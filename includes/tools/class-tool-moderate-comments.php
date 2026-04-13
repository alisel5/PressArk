<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Moderate_Comments extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'moderate_comments',
			'description' => 'Moderate one or more comments. Spam/unspam notifies Akismet.',
			'params'      => array(
				array( 'name' => 'comment_ids', 'required' => true, 'desc' => 'Array of comment IDs' ),
				array( 'name' => 'action', 'required' => true, 'desc' => 'approve|unapprove|spam|unspam|trash|untrash' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
