<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Manage_Webhooks extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'manage_webhooks',
			'description' => 'List, pause, activate, disable, or delete WooCommerce webhooks with health status.',
			'params'      => array(
				array( 'name' => 'action', 'required' => false, 'desc' => 'list|pause|activate|disable|delete (default: list)' ),
				array( 'name' => 'webhook_id', 'required' => false, 'desc' => 'Required for pause/activate/disable/delete' ),
				array( 'name' => 'status', 'required' => false, 'desc' => 'active|paused|disabled|all (default: all)' ),
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Default: 20' ),
			),
		);
	
	}
}
