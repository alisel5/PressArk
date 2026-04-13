<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Check_Email_Delivery extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'check_email_delivery',
			'description' => 'Check email delivery config: SMTP plugins, wp_mail hooks.',
			'type'        => 'read',
			'params'      => array(),
		);
	
	}
}
