<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Design_System extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_design_system',
			'description' => 'Read theme.json design system: colors, typography, spacing, layout. Requires theme.json.',
			'params'      => array(
				array( 'name' => 'section', 'required' => false, 'desc' => 'all|colors|typography|spacing|layout|elements (default: all)' ),
			),
		);
	
	}
}
