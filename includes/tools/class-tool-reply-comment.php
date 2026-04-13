<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Reply_Comment extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'reply_comment',
			'description' => 'Reply to a comment as the current admin user.',
			'params'      => array(
				array( 'name' => 'comment_id', 'required' => true, 'desc' => 'Parent comment ID' ),
				array( 'name' => 'content', 'required' => true, 'desc' => 'Reply text' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
