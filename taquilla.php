<?php
/*
Plugin Name: Taquilla
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/
/*  
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License (GPL v2) only.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


###############################################################################
####                                                                       ####
####   Create constants for paths, etc.                                    ####
####                                                                       ####
###############################################################################

if ( !defined( 'WP_CONTENT_DIR' ) )
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( !defined( 'WP_CONTENT_URL' ) )
    define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content' );
if ( !defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );
if ( !defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
if ( !defined( 'TAQUILLA_ABSPATH' ) )
    define( 'TAQUILLA_ABSPATH', WP_PLUGIN_DIR . '/' . basename( dirname ( __FILE__ ) ) . '/' );
if ( !defined( 'TAQUILLA_URL' ) )
    define( 'TAQUILLA_URL', WP_PLUGIN_URL . '/' . basename( dirname ( __FILE__ ) ) . '/' );
if ( !defined( 'TAQUILLA_BASENAME' ) )
    define( 'TAQUILLA_BASENAME', plugin_basename( __FILE__ ) );

###############################################################################
####                                                                       ####
####   Create a class to include the code, as in:                          ####
####   http://www.devlounge.net/extras/how-to-write-a-wordpress-plugin     ####
####                                                                       ####
###############################################################################

if (!class_exists("Taquilla_Admin")) { 
	include_once ( TAQUILLA_ABSPATH . 'taquilla-admin.php' );
  if (class_exists('Taquilla_Admin')) {
      $Taquilla_Admin = new Taquilla_Admin();
      // Calls a set of functions when the plugin is activated:
      register_activation_hook( __FILE__, array( &$Taquilla_Admin, 'plugin_activation_hook' ) );
      // Calls a set of functions when the plugin is deactivated:
      register_deactivation_hook( __FILE__, array( &$Taquilla_Admin, 'plugin_deactivation_hook' ) );
	  if (isset($taquilla)) { 
	      //Actions 
		  //Filters 
	  } 
  }
}


?>
