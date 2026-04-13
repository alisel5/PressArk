<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Site_Map extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_site_map',
			'description' => 'Full site structure: all pages, recent posts, homepage config, blog page. Use post_type to limit output to a specific content type.',
			'params'      => array(
				array( 'name' => 'post_type', 'required' => false, 'desc' => 'page|post|product — show only this type (default: all types)' ),
			),
		);
	
	}
}
