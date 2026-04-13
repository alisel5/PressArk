<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Resources extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_resources',
			'description' => 'List available site resources (design tokens, templates, REST routes, schemas). Each resource has a URI you can pass to read_resource.',
			'params'      => array(
				array( 'name' => 'group', 'required' => false, 'desc' => 'Filter by resource group: site, design, templates, schema, rest, woocommerce, elementor' ),
			),
		);
	
	}
}
