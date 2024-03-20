<?php
/**
 * Filters.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Custom location for SF integration.
 */
add_filter( 'newsletterglue_get_path', 'newsletterglue_sf_get_path', 10, 2 );
function newsletterglue_sf_get_path( $path, $app ) {

	if ( $app === 'salesforce' ) {
		$path = NGSF_PLUGIN_DIR . 'salesforce';
	}

    return $path;
}

/**
 * Custom location for SF integration.
 */
add_filter( 'newsletterglue_get_url', 'newsletterglue_sf_get_url', 10, 2 );
function newsletterglue_sf_get_url( $path, $app ) {

	if ( $app === 'salesforce' ) {
		$path = NGSF_PLUGIN_URL . 'salesforce';
	}

    return $path;
}

/**
 * Add SF support.
 */
add_filter( 'newsletterglue_get_supported_apps', 'newsletterglue_sf_add_support', 99 );
function newsletterglue_sf_add_support( $apps ) {

	$apps['salesforce'] = __( 'Salesforce Marketing Cloud', 'newsletter-glue' );

	return $apps;
}

/**
 * Add SF support.
 */
add_filter( 'newsletterglue_get_esp_list', 'newsletterglue_sf_add_esp', 99 );
function newsletterglue_sf_add_esp( $list ) {

	$list[] = array(
		'value' 	=> 'salesforce',
		'label'		=> 'Salesforce Marketing Cloud',
		'bg'			=> '#FFF',
		'help'          => 'https://mc.login.exacttarget.com/hub-cas/login',
		'key_name'      => 'Client ID',
		'secret_name'   => 'Client Secret',
		'url_name'      => 'Auth Base URL',
		'extra_setting' => 'both',
	);

	return $list;
}
