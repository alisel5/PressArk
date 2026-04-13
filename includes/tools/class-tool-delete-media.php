<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Delete_Media extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'delete_media',
			'description' => 'Permanently delete a media attachment.',
			'params'      => array(
				array( 'name' => 'attachment_id', 'required' => true ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
