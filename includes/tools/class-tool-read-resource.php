<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Read_Resource extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'read_resource',
			'description' => 'Read a site resource by URI. Default is summary mode; use detail for structured JSON or raw for the unformatted payload. Use list_resources first to see available URIs.',
			'params'      => array(
				array( 'name' => 'uri', 'required' => true, 'desc' => 'Resource URI from list_resources (e.g., pressark://design/theme-json, pressark://schema/post-types)' ),
				array( 'name' => 'mode', 'required' => false, 'desc' => 'summary|detail|raw (default: summary)' ),
			),
		);
	
	}
}
