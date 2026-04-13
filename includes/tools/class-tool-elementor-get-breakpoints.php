<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Get_Breakpoints extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_get_breakpoints',
			'description' => 'Get active Elementor breakpoints with pixel thresholds and device labels.',
			'params'      => array(),
		);
	
	}
}
