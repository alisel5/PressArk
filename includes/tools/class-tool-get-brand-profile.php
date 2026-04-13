<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Brand_Profile extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_brand_profile',
			'description' => 'AI-generated site profile: brand voice, content DNA, audience, WC details.',
			'params'      => array(),
		);
	
	}
}
