<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_Media extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_media',
			'description' => 'Update media attachment: alt, title, caption, description, or set as featured image.',
			'params'      => array(
				array( 'name' => 'attachment_id', 'required' => true ),
				array( 'name' => 'changes', 'required' => true, 'desc' => '{alt, title, caption, description, set_featured_for}' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
