<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Elementor_Edit_Popup_Trigger extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'elementor_edit_popup_trigger',
			'description' => 'Edit trigger settings on an Elementor Pro popup.',
			'params'      => array(
				array( 'name' => 'popup_id', 'required' => true, 'desc' => 'From elementor_list_popups' ),
				array( 'name' => 'trigger_type', 'required' => true, 'desc' => 'page_load|scroll_depth|click|inactivity|exit_intent' ),
				array( 'name' => 'trigger_settings', 'required' => false, 'desc' => 'Config object: {delay} for page_load, {scroll_depth} for scroll, etc.' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
