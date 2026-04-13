<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Media extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_media',
			'description' => 'Get full details of a media attachment: EXIF, thumbnails, attached post.',
			'params'      => array(
				array( 'name' => 'attachment_id', 'required' => true ),
			),
		);
	
	}
}
