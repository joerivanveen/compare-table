<?php
/*
Plugin Name: Compare table
Plugin URI: https://github.com/joerivanveen/compare-table
Description: Creates a table where a visitor can compare services or items or anything really, that you provide from the admin interface.
Version: 1.0.0
Author: Joeri van Veen
Author URI: https://wp-developer.eu
License: GPLv3
Text Domain: compare-table
Domain Path: /languages/
*/

declare( strict_types=1 );

defined( 'ABSPATH' ) || die();

// This is plugin nr. 14 by Ruige hond. It identifies as: ruigehond014.
const RUIGEHOND014_VERSION = '1.0.0';

$ruigehond014_basename = plugin_basename( __FILE__ );
$ruigehond014_dirname  = dirname( __FILE__ );

if ( ! class_exists( 'ruigehond_ITOEWERKLKVEIR_0_4_1\ruigehond', false ) ) {
	include_once( "$ruigehond014_dirname/includes/ruigehond.php" ); // base class
}
include_once( "$ruigehond014_dirname/includes/ruigehond014.php" );

global $ruigehond014;
$ruigehond014 = new ruigehond014_ITOEWERKLKVEIR\ruigehond014( $ruigehond014_basename );

add_action( 'init', array( $ruigehond014, 'initialize' ) );
add_action( "activate_$ruigehond014_basename", array( $ruigehond014, 'activate' ) );
add_action( "deactivate_$ruigehond014_basename", array( $ruigehond014, 'deactivate' ) );

/**
 * setup ajax for admin interface, ajax call javascript needs to call whatever
 * comes after wp_ajax_ (so in this case: ruigehond014_handle_input)
 */
add_action( 'wp_ajax_ruigehond014_handle_input', 'ruigehond014_ITOEWERKLKVEIR_handle_input' );
function ruigehond014_ITOEWERKLKVEIR_handle_input() {
	global $ruigehond014;

	$return_object = $ruigehond014->handle_input( $_POST );

	echo wp_json_encode( $return_object, FILTER_SANITIZE_STRING );
	die(); // prevent any other output
}
