<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Available_Tools extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_available_tools',
			'description' => 'List available tools beyond current set. Use when needing unlisted capabilities.',
			'params'      => array(
				array( 'name' => 'category', 'required' => false, 'desc' => 'media|comments|users|email|health|scheduled|generation|bulk|export|profile|logs|index|plugins|themes|database|woocommerce|elementor' ),
			),
		);
	
	}
}
