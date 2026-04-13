<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_Menu extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_menu',
			'description' => 'Create, modify, or assign navigation menus. Handles FSE and classic menus.',
			'params'      => array(
				array( 'name' => 'operation', 'required' => true, 'desc' => 'create_menu|add_item|remove_item|assign_location|rename_menu|delete_menu' ),
				array( 'name' => 'menu_id', 'required' => false ),
				array( 'name' => 'name', 'required' => false, 'desc' => 'For create_menu and rename_menu' ),
				array( 'name' => 'item', 'required' => false, 'desc' => '{title, url, type, object_id, position}' ),
				array( 'name' => 'item_id', 'required' => false, 'desc' => 'For remove_item on classic menus' ),
				array( 'name' => 'location', 'required' => false, 'desc' => 'Theme location slug for assign_location' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
