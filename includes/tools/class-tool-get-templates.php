<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Templates extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_templates',
			'description' => 'Read FSE block templates and parts. Lists summaries by default; use slug with mode=detail or mode=raw for one template.',
			'params'      => array(
				array( 'name' => 'type', 'required' => false, 'desc' => 'wp_template|wp_template_part (default: wp_template)' ),
				array( 'name' => 'slug', 'required' => false, 'desc' => 'Omit to list all' ),
				array( 'name' => 'mode', 'required' => false, 'desc' => 'summary|detail|raw (default: summary for lists, detail with slug)' ),
			),
		);
	
	}
}
