<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_List_Scheduled_Tasks extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'list_scheduled_tasks',
			'description' => 'List all WordPress WP-Cron scheduled tasks.',
			'params'      => array(),
		);
	
	}
}
