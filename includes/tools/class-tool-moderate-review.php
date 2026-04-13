<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Moderate_Review extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'moderate_review',
			'description' => 'Approve, unapprove, spam, trash, or reply to a product review.',
			'params'      => array(
				array( 'name' => 'review_id', 'required' => true ),
				array( 'name' => 'action', 'required' => true, 'desc' => 'approve|unapprove|spam|trash|reply' ),
				array( 'name' => 'reply_content', 'required' => false, 'desc' => 'Required when action=reply' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
