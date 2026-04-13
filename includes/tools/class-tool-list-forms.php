<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Forms extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_forms',
			'description' => 'Hint: Call first -- detects which form plugin is active. Lists forms with email config and SMTP status.',
			'params'      => array(),
		);
	
	}
}
