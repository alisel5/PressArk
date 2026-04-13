<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class PressArk_Tool_Base implements PressArk_Tool {

	private ?array $definition_cache = null;

	final public function get_name(): string {
		$definition = $this->get_definition();
		return sanitize_key( (string) ( $definition['name'] ?? '' ) );
	}

	final public function get_description(): string {
		return PressArk_Tools::describe_tool_definition( $this->to_legacy_definition() );
	}

	final public function get_input_schema(): array {
		return PressArk_Tools::build_tool_parameters( $this->to_legacy_definition() );
	}

	public function is_readonly(): bool {
		return 'read' === $this->default_capability();
	}

	public function is_concurrency_safe(): bool {
		return $this->default_concurrency_safe() && $this->is_readonly();
	}

	public function check_permissions( array $input, int $user_id, string $tier ): array {
		$name               = $this->get_name();
		$permission_context = class_exists( 'PressArk_Policy_Engine' )
			? PressArk_Policy_Engine::CONTEXT_INTERACTIVE
			: 'interactive';
		$meta               = array(
			'tier'             => sanitize_key( $tier ),
			'user_id'          => max( 0, $user_id ),
			'decision_purpose' => 'tool_preflight',
		);

		if ( class_exists( 'PressArk_Permission_Service' ) ) {
			$visibility = PressArk_Permission_Service::evaluate_tool_set(
				array( $name ),
				$permission_context,
				$meta
			);
			$decision   = is_array( $visibility['decisions'][ $name ] ?? null )
				? $visibility['decisions'][ $name ]
				: array();

			if ( class_exists( 'PressArk_Permission_Decision' ) && ! empty( $decision ) ) {
				if ( PressArk_Permission_Decision::is_denied( $decision ) ) {
					return array(
						'allowed'   => false,
						'behavior'  => 'block',
						'reason'    => sanitize_text_field( (string) ( $decision['reason'] ?? __( 'Blocked by policy.', 'pressark' ) ) ),
						'ui_action' => 'none',
						'tool_name' => $name,
					);
				}

				if ( PressArk_Permission_Decision::is_ask( $decision ) ) {
					return array(
						'allowed'   => false,
						'behavior'  => 'ask',
						'reason'    => sanitize_text_field( (string) ( $decision['reason'] ?? __( 'This action needs approval before it can run.', 'pressark' ) ) ),
						'ui_action' => sanitize_key( (string) ( $decision['approval']['mode'] ?? 'confirm' ) ),
						'tool_name' => $name,
					);
				}
			}
		}

		if ( class_exists( 'PressArk_Operation_Registry' ) ) {
			$local = PressArk_Operation_Registry::check_permissions(
				$name,
				$input,
				array(
					'user_id' => max( 0, $user_id ),
					'tier'    => sanitize_key( $tier ),
					'context' => $permission_context,
					'source'  => 'tool_object',
				)
			);

			if ( ! empty( $local ) ) {
				$behavior = sanitize_key( (string) ( $local['behavior'] ?? 'allow' ) );
				if ( ! in_array( $behavior, array( 'allow', 'ask', 'block' ), true ) ) {
					$behavior = ! empty( $local['allowed'] ) ? 'allow' : 'block';
				}

				return array(
					'allowed'   => 'allow' === $behavior,
					'behavior'  => $behavior,
					'reason'    => sanitize_text_field( (string) ( $local['reason'] ?? '' ) ),
					'ui_action' => sanitize_key( (string) ( $local['ui_action'] ?? 'none' ) ),
					'tool_name' => $name,
				);
			}
		}

		return array(
			'allowed'   => true,
			'behavior'  => 'allow',
			'reason'    => '',
			'ui_action' => 'none',
			'tool_name' => $name,
		);
	}

	public function get_prompt_snippet(): string {
		$params = PressArk_Tools::build_tool_prompt_params( $this->to_legacy_definition() );
		$parts  = array(
			'name=' . $this->get_name(),
			'mode=' . $this->default_capability(),
			'safe=' . ( $this->is_concurrency_safe() ? '1' : '0' ),
			'weight=' . (string) $this->prompt_weight(),
		);

		if ( ! empty( $params ) ) {
			$param_names = array();
			foreach ( $params as $param ) {
				$param_name = sanitize_key( (string) ( $param['name'] ?? '' ) );
				if ( '' === $param_name ) {
					continue;
				}
				$param_names[] = ! empty( $param['required'] ) ? '*' . $param_name : $param_name;
			}
			if ( ! empty( $param_names ) ) {
				$parts[] = 'params=' . implode( '|', $param_names );
			}
		}

		return '@tool ' . implode( ' ', $parts ) . ' :: ' . $this->get_description();
	}

	public function execute( array $input, PressArk_Tool_Context $context ): array {
		if ( $context->is_aborted() ) {
			return array(
				'success'     => false,
				'message'     => __( 'Tool execution was cancelled before it started.', 'pressark' ),
				'action_type' => $this->get_name(),
			);
		}

		$engine = $context->get_action_engine();
		if ( ! $engine ) {
			$engine = new PressArk_Action_Engine( new PressArk_Action_Logger() );
		}

		return $engine->execute_single(
			array(
				'type'   => $this->get_name(),
				'params' => $input,
				'meta'   => $context->to_execution_meta(),
			),
			$this->is_readonly(),
			$context->get_progress_callback()
		);
	}

	final public function to_legacy_definition(): array {
		return $this->get_definition();
	}

	protected function default_capability(): string {
		return 'read';
	}

	protected function default_concurrency_safe(): bool {
		return $this->is_readonly();
	}

	protected function prompt_weight(): int {
		return 0;
	}

	abstract protected function definition(): array;

	final protected function get_definition(): array {
		if ( null === $this->definition_cache ) {
			$this->definition_cache = $this->definition();
		}

		return $this->definition_cache;
	}
}
