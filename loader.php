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

function cc_cogis_extras_class_init(){
	// Get the class fired up
	require_once( dirname( __FILE__ ) . '/includes/cc-cogis-extras.php' );
	add_action( 'bp_include', array( 'CC_Cogis_Extras', 'get_instance' ), 21 );
}
add_action( 'bp_include', 'cc_cogis_extras_class_init' );