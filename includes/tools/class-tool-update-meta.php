<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_Meta extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_meta',
			'description' => 'Update post meta. Keys: meta_title, meta_description, og_title, og_description, og_image, focus_keyword.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
				array( 'name' => 'changes', 'required' => true, 'desc' => 'Object of meta key/value pairs' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}
}
