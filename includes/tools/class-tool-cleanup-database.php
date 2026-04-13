<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Cleanup_Database extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'cleanup_database',
			'description' => 'Clean WordPress database: revisions, auto-drafts, spam, transients, orphaned meta.',
			'params'      => array(
				array( 'name' => 'items', 'required' => false, 'desc' => 'Array: revisions|auto_drafts|trashed|spam_comments|expired_transients|orphaned_meta. Omit = all.' ),
			),
		);
	
	}

	protected function default_capability(): string {
		return 'confirm';
	}
}
