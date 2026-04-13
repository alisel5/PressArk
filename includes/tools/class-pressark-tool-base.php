<?php
/**
 * Compatibility loader for the shared tool base runtime.
 *
 * @package PressArk
 * @since   5.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/class-pressark-tool-base.php';
