<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Reply_Review extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'reply_review',
			'description' => 'Reply to a WooCommerce product review.',
			'params'      => array(
				array( 'name' => 'review_id', 'required' => true ),
				array( 'name' => 'reply_content', 'required' => true, 'desc' => 'Reply text to post under the review' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
