<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Get_Email_Log extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'get_email_log',
			'description' => 'Check recently sent emails: recipient, subject, status, timestamp.',
			'params'      => array(
				array( 'name' => 'limit', 'required' => false, 'desc' => 'Max results (default: 20, max: 50)' ),
			),
		);
	
	}
}
