<?php
/**
 * Deactivation plugin.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reset connection on SF deactivation.
 */
register_deactivation_hook( NGSF_PLUGIN_FILE, 'newsletterglue_sf_deactivation' );
function newsletterglue_sf_deactivation() {
    $integrations = get_option( 'newsletterglue_integrations' );
    if( isset( $integrations[ 'salesforce' ] ) ) {
        delete_option( 'newsletterglue_integrations' );
    }
}