<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Site_Note extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'site_note',
			'description' => 'Record a site observation for future conversations. Use when discovering patterns, preferences, or issues.',
			'params'      => array(
				array( 'name' => 'note', 'required' => true, 'desc' => 'Observation to record (max 200 chars)' ),
				array( 'name' => 'category', 'required' => true, 'desc' => 'content|products|technical|preferences|issues' ),
			),
		);
	
	}
}
