<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PressArk_Tool_Analyze_Comment_Moderation extends PressArk_Tool_Base {

	protected function definition(): array {
		return array(
			'name'        => 'analyze_comment_moderation',
			'description' => 'Hint: Use to explain why a comment is held. Analyzes hold reasons via check_comment().',
			'type'        => 'read',
			'params'      => array(
				array( 'name' => 'comment_id', 'required' => true ),
			),
		);
	
	}
}
