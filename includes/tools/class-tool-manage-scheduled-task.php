<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Manage_Scheduled_Task extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'manage_scheduled_task',
			'description' => 'Run, remove, or remove_all instances of a WP-Cron hook.',
			'params'      => array(
				array( 'name' => 'action', 'required' => true, 'desc' => 'run|remove|remove_all' ),
				array( 'name' => 'hook', 'required' => true ),
				array( 'name' => 'timestamp', 'required' => false, 'desc' => 'Target timestamp for remove (default: next occurrence)' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
