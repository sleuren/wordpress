<?php

namespace Sleuren\Classes;

/**
 * Plugin Activation
 *
 * @since 1.0.0
 */
class Activation {

	/**
	 * Code that runs on plugin activation
	 *
	 * @since 1.0.0
	 */
	public function activate() {

		// Create option to store logger status

        $option_value = array(
            'status'    => 'disabled',
            'on'        => date( 'Y-m-d H:i:s' ),
        );

        update_option( 'sleuren', $option_value, false );

        // Create option to store auto-refresh feature status

        $autorefresh_status = 'disabled';

        update_option( 'sleuren_autorefresh', $autorefresh_status, false );

        // Create debug.log file in custom location for use in WP_DEBUG_LOG constant
        
        $uploads_path = wp_upload_dir()['basedir'] . '/sleuren';

        $plain_domain = str_replace( array( ".", "-" ), "", sanitize_text_field( $_SERVER['SERVER_NAME'] ) ); // e.g. wwwgooglecom

        $unique_key = date( 'YmdHi' );

        $log_file_path = $uploads_path . '/' . $plain_domain . '_' . $unique_key .'_sleuren.log';

        $log_file_path_in_option = get_option( 'sleuren_file_path' );

        if ( $log_file_path_in_option === false ) {

	        update_option( 'sleuren_file_path', $log_file_path, false );

            $log_file_path_in_option = get_option( 'sleuren_file_path' );

        }

        if ( ! is_dir( $uploads_path ) ) {
            mkdir( $uploads_path ); // create directory in /uploads folder
        }

        if ( ! is_file( $log_file_path_in_option ) ) {
            file_put_contents( $log_file_path_in_option, '' ); // create empty log file
        } else {}        

	}

}