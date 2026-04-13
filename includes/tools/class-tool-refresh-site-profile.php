<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Refresh_Site_Profile extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'refresh_site_profile',
			'description' => 'Regenerate the site profile by re-analyzing all content.',
			'params'      => array(),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
