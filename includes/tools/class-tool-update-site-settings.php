<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Update_Site_Settings extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'update_site_settings',
			'description' => 'RECOMMENDED: Update WordPress site design settings including theme options and global styles.',
			'params'      => array(
				array( 'name' => 'changes', 'required' => true, 'desc' => 'Allowed keys: blogname, blogdescription, timezone_string, date_format, time_format, posts_per_page, permalink_structure, default_comment_status, show_on_front, page_on_front, page_for_posts' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'preview';
	}

	protected function prompt_weight(): int {
		return 5;
	}
}
