<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Scan_Security extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'scan_security',
			'description' => 'Run a site security audit. Returns per-check status and auto_fixable flags. Only propose fix_security for issues marked auto_fixable in this scan.',
			'params'      => array(
				array( 'name' => 'severity', 'required' => false, 'desc' => 'critical|high|medium|low — filter to only issues at this severity or higher' ),
			),
		);
	
	}
}
