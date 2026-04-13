<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Site_Brief extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'site_brief',
			'description' => 'Fast site overview: content counts, activity, pending updates, integrations.',
			'params'      => array(),
		);
	
	}
}
