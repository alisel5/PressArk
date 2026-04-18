<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable routing request context DTO.
 */
class PressArk_Request_Context {

	public string $message;
	public string $original_message;
	public array $conversation;
	public string $tier;
	public bool $deep_mode;
	public string $screen;
	public int $post_id;
	public string $continuation_mode;
	public bool $suppress_plan;
	public bool $plan_execute;
	public bool $explicit_plan;
	public int $async_score;
	public bool $native_tools;
	public array $permission_probe;
	public array $preload_plan;
	public array $planning_decision;

	/**
	 * @param array<string,mixed> $data Initial context values.
	 */
	public function __construct( array $data = array() ) {
		$this->message            = (string) ( $data['message'] ?? '' );
		$this->original_message   = (string) ( $data['original_message'] ?? $this->message );
		$this->conversation       = is_array( $data['conversation'] ?? null ) ? (array) $data['conversation'] : array();
		$this->tier               = sanitize_key( (string) ( $data['tier'] ?? '' ) );
		$this->deep_mode          = ! empty( $data['deep_mode'] );
		$this->screen             = sanitize_text_field( (string) ( $data['screen'] ?? '' ) );
		$this->post_id            = absint( $data['post_id'] ?? 0 );
		$this->continuation_mode  = sanitize_key( (string) ( $data['continuation_mode'] ?? '' ) );
		$this->suppress_plan      = ! empty( $data['suppress_plan'] );
		$this->plan_execute       = ! empty( $data['plan_execute'] );
		$this->explicit_plan      = ! empty( $data['explicit_plan'] );
		$this->async_score        = max( 0, (int) ( $data['async_score'] ?? 0 ) );
		$this->native_tools       = ! empty( $data['native_tools'] );
		$this->permission_probe   = is_array( $data['permission_probe'] ?? null ) ? (array) $data['permission_probe'] : array();
		$this->preload_plan       = is_array( $data['preload_plan'] ?? null ) ? (array) $data['preload_plan'] : array();
		$this->planning_decision  = is_array( $data['planning_decision'] ?? null ) ? (array) $data['planning_decision'] : array();
	}

	/**
	 * @param array<string,mixed> $data Initial context values.
	 */
	public static function from_array( array $data ): self {
		return new self( $data );
	}

	/**
	 * Return a cloned DTO with selected fields replaced.
	 *
	 * @param array<string,mixed> $changes Context overrides.
	 */
	public function with( array $changes ): self {
		return new self( array_merge( $this->to_array(), $changes ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'message'           => $this->message,
			'original_message'  => $this->original_message,
			'conversation'      => $this->conversation,
			'tier'              => $this->tier,
			'deep_mode'         => $this->deep_mode,
			'screen'            => $this->screen,
			'post_id'           => $this->post_id,
			'continuation_mode' => $this->continuation_mode,
			'suppress_plan'     => $this->suppress_plan,
			'plan_execute'      => $this->plan_execute,
			'explicit_plan'     => $this->explicit_plan,
			'async_score'       => $this->async_score,
			'native_tools'      => $this->native_tools,
			'permission_probe'  => $this->permission_probe,
			'preload_plan'      => $this->preload_plan,
			'planning_decision' => $this->planning_decision,
		);
	}
}
