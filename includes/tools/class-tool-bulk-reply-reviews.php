<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Bulk_Reply_Reviews extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'bulk_reply_reviews',
			'description' => 'Reply to multiple WooCommerce product reviews in one action.',
			'params'      => array(
				array( 'name' => 'reviews', 'required' => true, 'desc' => 'Array of {review_id, reply_content} objects' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
