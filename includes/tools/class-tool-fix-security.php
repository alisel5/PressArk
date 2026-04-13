<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Fix_Security extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'fix_security',
			'description' => 'Apply auto-fixable security fixes. Only pass fix IDs returned by the latest scan as auto_fixable findings. Never pass both by default. If the scan shows none, do not call this tool.',
			'params'      => array(
				array( 'name' => 'fixes', 'required' => true, 'desc' => 'Array of fix IDs: "delete_exposed_files", "disable_xmlrpc"' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
