<?php
/*
Plugin Name: UAMSWP Content Syndication - Dining
Plugin URI: -
Description: Retrieve dining information for display from UAMS Nutrition Menu.
Author: uams, Todd McKee, MEd
Author URI: https://web.uams.edu/
Version: 1.0.0
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// This plugin uses namespaces and requires PHP 5.3 or greater.
if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
	add_action( 'admin_notices', create_function( '', // phpcs:ignore WordPress.PHP.RestrictedPHPFunctions.create_function_create_function
	"echo '<div class=\"error\"><p>" . __( 'UAMSWP Dining Syndication requires PHP 5.3 to function properly. Please upgrade PHP or deactivate the plugin.', 'uamswp-dining-syndicate' ) . "</p></div>';" ) );
	return;
} else {
	include_once __DIR__ . '/includes/dining-syndicate.php';
}
