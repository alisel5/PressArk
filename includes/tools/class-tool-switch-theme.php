<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Switch_Theme extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'switch_theme',
			'description' => 'Hint: May reset menus/widgets -- warn user first. Switch the active theme with compatibility check.',
			'params'      => array(
				array( 'name' => 'theme_slug', 'required' => true, 'desc' => 'From list_themes' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
