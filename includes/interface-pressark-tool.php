<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface PressArk_Tool {

	public function get_name(): string;

	public function get_description(): string;

	public function get_input_schema(): array;

	public function is_readonly(): bool;

	public function is_concurrency_safe(): bool;

	/**
	 * @return array{allowed: bool, reason: string}
	 */
	public function check_permissions( array $input, int $user_id, string $tier ): array;

	public function get_prompt_snippet(): string;

	public function execute( array $input, PressArk_Tool_Context $context ): array;
}
