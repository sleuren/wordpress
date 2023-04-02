<?php

/**
 * Plugin Name:       Sleuren
 * Plugin URI:        https://wordpress.org/plugins/sleuren/
 * Description:       Log errors via WP_DEBUG. Create, view and clear debug.log file.
 * Version:           1.0.0
 * Author:            sleuren
 * Author URI:        https://sleuren.com/
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       sleuren
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SLE_VERSION', '1.0.0' );
define( 'SLE_SLUG', 'sleuren' );
define( 'SLE_URL', plugins_url( '/', __FILE__ ) ); // e.g. https://www.example.com/wp-content/plugins/this-plugin/
define( 'SLE_PATH', plugin_dir_path( __FILE__ ) ); // e.g. /home/user/apps/wp-root/wp-content/plugins/this-plugin/

// Register autoloading classes
spl_autoload_register( 'sleuren_autoloader' );

/**
 * Autoload classes defined by this plugin
 * 
 * @param  string $class_name e.g. \Sleuren\Classes\The_Name
 * @since 1.2.0
 */
function sleuren_autoloader( $class_name ) {

    $namespace = 'Sleuren';

    // Only process classes within this plugin's namespace

    if ( false !== strpos( $class_name, $namespace ) ) {

        // Assemble file path for the class

        // \Sleuren\Classes\The_Name => \Classes\The_Name
        $path = str_replace( $namespace, "", $class_name );

        // \Classes\The_Name => /classes/the_name
        $path = str_replace( "\\", DIRECTORY_SEPARATOR, strtolower( $path ) );

        // /classes/the_name =>  /classes/the-name.php
        $path = str_replace( "_", "-", $path ) . '.php';

        // /classes/the-name.php => /classes/class-the-name.php
        $path = str_replace( "classes" . DIRECTORY_SEPARATOR, "classes" . DIRECTORY_SEPARATOR . "class-", $path );

        // Remove first '/'
        $path = substr( $path, 1 );

        // Get /plugin-path/classes/class-the-name.php
        $path = SLE_PATH . $path;

        if ( file_exists( $path ) ) {
            require_once( $path );
        }

    }

}

/**
 * Code that runs on plugin activation
 * 
 * @since 1.0.0
 */
function sleuren_on_activation() {
	$activation = new Sleuren\Classes\Activation;
    $activation->activate();
}

/**
 * Code that runs on plugin deactivation
 * 
 * @since 1.0.0
 */
function sleuren_on_deactivation() {
    $deactivation = new Sleuren\Classes\Deactivation;
    $deactivation->deactivate();
}

// Register code that runs on plugin activation
register_activation_hook( __FILE__, 'sleuren_on_activation');

// Register code that runs on plugin deactivation
register_deactivation_hook( __FILE__, 'sleuren_on_deactivation' );

// Bootstrap the core functionalities of this plugin
require SLE_PATH . 'bootstrap.php';