<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Audit_Page extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_audit_page',
			'description' => 'Audit an Elementor page for issues: alt text, buttons, headings, thin content. Scored report.',
			'params'      => array(
				array( 'name' => 'post_id', 'required' => true ),
			),
		);
	
	}
}
