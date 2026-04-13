<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Regenerate_Thumbnails extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'regenerate_thumbnails',
			'description' => 'Regenerate thumbnail sizes for images. Max 20 per call.',
			'params'      => array(
				array( 'name' => 'media_id', 'required' => false, 'desc' => 'Single attachment ID' ),
				array( 'name' => 'media_ids', 'required' => false, 'desc' => 'Array of attachment IDs' ),
				array( 'name' => 'post_id', 'required' => false, 'desc' => 'Regenerate all images on this post' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
