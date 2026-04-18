<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure route decision DTO emitted by the route arbiter.
 */
class PressArk_Route_Decision {

	public string $route;
	public array $reasons;
	public array $advisories;

	/**
	 * @param array<string,mixed> $reasons
	 * @param array<string,mixed> $advisories
	 */
	public function __construct( string $route, array $reasons = array(), array $advisories = array() ) {
		$this->route      = sanitize_key( $route );
		$this->reasons    = $reasons;
		$this->advisories = $advisories;
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public function reason( string $key, $default = null ) {
		return $this->reasons[ $key ] ?? $default;
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	public function advisory( string $key, $default = null ) {
		return $this->advisories[ $key ] ?? $default;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'route'      => $this->route,
			'reasons'    => $this->reasons,
			'advisories' => $this->advisories,
		);
	}
}
