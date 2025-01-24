<?php
/*
Plugin Name: Compare table
Plugin URI: https://github.com/joerivanveen/compare-table
Description: Creates a table where a visitor can compare services or items or anything really, that you provide from the admin interface.
Version: 2.0.1
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Author: Joeri van Veen
Author URI: https://wp-developer.eu
License: GPLv3
Text Domain: compare-table
Domain Path: /languages/
*/

declare( strict_types=1 );

defined( 'ABSPATH' ) || die();

// This is plugin nr. 14 by Ruige hond. It identifies as: ruigehond014.
const RUIGEHOND014_VERSION = '2.0.1';

$ruigehond014_basename = plugin_basename( __FILE__ );
$ruigehond014_dirname  = dirname( __FILE__ );

if ( ! class_exists( 'ruigehond_0_5_0\ruigehond', false ) ) {
	include_once( "$ruigehond014_dirname/includes/ruigehond.php" ); // base class
}
include_once( "$ruigehond014_dirname/includes/ruigehond014.php" );

global $ruigehond014;
$ruigehond014 = new ruigehond014\ruigehond014( $ruigehond014_basename );

add_action( 'init', array( $ruigehond014, 'initialize' ) );
add_action( "activate_$ruigehond014_basename", array( $ruigehond014, 'activate' ) );
add_action( "deactivate_$ruigehond014_basename", array( $ruigehond014, 'deactivate' ) );

/**
 * Setup ajax for frontend, currently only refreshes a column
 */
add_action( 'wp_ajax_ruigehond014_table_data', 'ruigehond014_table_data' );
add_action( 'wp_ajax_nopriv_ruigehond014_table_data', 'ruigehond014_table_data' );
function ruigehond014_table_data() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'ruigehond014_nonce' ) ) {
		return;
	}
	/* This is for the frontend form, it can handle several requests */
	if ( false === isset( $_POST['endpoint'] ) ) {
		_ajax_wp_die_handler( 'Endpoint must be set.' );
	}
	if ( 0 === ( $type_id = (int) $_POST['type_id'] ) ) {
		_ajax_wp_die_handler( 'Please supply type_id for the table.' );
	}
	global $ruigehond014;
	$response = array();
	switch ( wp_unslash( $_POST['endpoint'] ) ) {
		case 'fetch':
			$response['table'] = $ruigehond014->get_table_html( array( 'type' => $type_id ) );
			break;
	}
	//$response['timestamp'] = (int) $_POST['timestamp'];
	echo wp_json_encode( $response, JSON_PRETTY_PRINT );
	die(); // prevent further output
}

/**
 * setup ajax for admin interface, ajax call javascript needs to call whatever
 * comes after wp_ajax_ (so in this case: ruigehond014_handle_input)
 */
add_action( 'wp_ajax_ruigehond014_handle_input', 'ruigehond014_handle_input' );
function ruigehond014_handle_input() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ruigehond014_nonce' ) ) {
		return;
	}

	global $ruigehond014;
	/**
	 * The plugin will send these variables to the ajax call, but not always all of them of course.
	 * Note: since the plugin is built to use string values, we convert to (original) string after sanitizing
	 *
	 * id
	 * handle
	 * table_name
	 * column_name
	 * value
	 * type_id
	 * field_id
	 * subject_id
	 * disable
	 * nonce
	 * order[0]
	 */
	$sanitized_post = array();
	foreach (
		array(
			'id'          => 'int',
			'handle'      => 'string',
			'table_name'  => 'string',
			'column_name' => 'string',
			'value'       => 'string',
			'type_id'     => 'int',
			'field_id'    => 'int',
			'subject_id'  => 'int',
			'disable'     => 'bool',
			'nonce'       => 'string',
			'order'       => 'int[]',
			'timestamp'   => 'int',
		) as $key => $type
	) {
		if ( isset( $_POST[ $key ] ) ) {
			switch ( $type ) {
				case 'int':
					$sanitized_post[ $key ] = (string) (int) $_POST[ $key ];
					break;
				case 'bool':
					$sanitized_post[ $key ] = ( 'true' === $_POST[ $key ] ) ? 'true' : 'false';
					break;
				case 'int[]':
					$sanitized_post[ $key ] = array_map( static function ( $value ) {
						return (string) (int) $value;
					}, $_POST[ $key ] );
					break;
				default: //string
					$sanitized_post[ $key ] = wp_kses_post( $_POST[ $key ] );
			}
		}
	}
	$return_object = $ruigehond014->handle_input( $sanitized_post );

	echo wp_json_encode( $return_object, JSON_PRETTY_PRINT );
	die(); // prevent any other output
}
