<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Site_Overview extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_site_overview',
			'description' => 'Compact site overview: name, URL, WP version, theme, content counts, plugins.',
			'params'      => array(),
		);
	
	}
}
