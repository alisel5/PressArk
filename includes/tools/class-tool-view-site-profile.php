<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_View_Site_Profile extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'view_site_profile',
			'description' => 'View auto-generated site profile: industry, style, tone, topics.',
			'params'      => array(),
		);
	
	}
}
