<?php
/*
Plugin Name: CC COGIS extras
Description: Adds extras to the COGIS group space
Version: 1.0
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . '/includes/cc-cogis-extras.php' );

// Instantiate the booger.
add_action( 'plugins_loaded', array( 'CC_Cogis_Extras', 'get_instance' ) );
