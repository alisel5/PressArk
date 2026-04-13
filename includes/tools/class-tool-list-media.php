<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Media extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_media',
			'description' => 'List media library attachments with optional filters. Supports pagination via offset. Returns _pagination metadata.',
			'params'      => array(
				array( 'name' => 'mime_type', 'required' => false, 'desc' => 'image|video|audio|application (default: all)' ),
				array( 'name' => 'search', 'required' => false ),
				array( 'name' => 'count', 'required' => false, 'desc' => 'Max results (default: 20, max: 50)' ),
				array( 'name' => 'offset', 'required' => false, 'desc' => 'Skip first N results for pagination (default: 0)' ),
				array( 'name' => 'post_id', 'required' => false, 'desc' => 'Filter to media attached to this post' ),
			),
		);
	
	}
}
