<?php
/**
 * Functions.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get Merge Tags.
 */
function newsletterglue_get_salesforce_tags() {

	$merge_tags = array(
		'personalization'	=> array(
			'title'		=> __( 'Personalization', 'newsletter-glue' ),
			'tags'	=> array(
				'first_name'	=> array(
					'title'		=> __( 'First name', 'newsletter-glue' ),
				),
				'email' => array(
					'title'		=> __( 'Email address', 'newsletter-glue' ),
				),
				'street' => array(
					'title'		=> __( 'Street', 'newsletter-glue' ),
				),
				'city' => array(
					'title'		=> __( 'City', 'newsletter-glue' ),
				),
				'state' => array(
					'title'		=> __( 'State', 'newsletter-glue' ),
				),
				'postalcode' => array(
					'title'		=> __( 'Postal code', 'newsletter-glue' ),
				),
				'country' => array(
					'title'		=> __( 'Country', 'newsletter-glue' ),
				),
				'job_id' => array(
					'title'		=> __( 'Job ID', 'newsletter-glue' ),
				),
				'member_id' => array(
					'title'		=> __( 'Member ID', 'newsletter-glue' ),
				),
				'subscriber_key' => array(
					'title'		=> __( 'Subscriber key', 'newsletter-glue' ),
				),
			),
		),
		'read_online'		=> array(
			'title'			=> __( 'Read online', 'newsletter-glue' ),
			'tags'			=> array(
				'blog_post' => array(
					'title'		=> __( 'Blog post', 'newsletter-glue' ),
					'default_link_text'	=> __( 'Read online', 'newsletter-glue' ),
				),
				'webversion' => array(
					'title'		=> __( 'Email HTML', 'newsletter-glue' ),
					'default_link_text'	=> __( 'Read online', 'newsletter-glue' ),
				),
			),
		),
		'footer'			=> array(
			'title'			=> __( 'Footer', 'newsletter-glue' ),
			'tags'			=> array(
				'admin_name'	=> array(
					'title'		=> __( 'Admin name', 'newsletter-glue' ),
					'require_fallback' => 'yes',
				),
				'admin_address' => array(
					'title'	=> __( 'Admin address', 'newsletter-glue' ),
					'require_fallback' => 'yes',
				),
				'unsubscribe_link' => array(
					'title'	=> __( 'Unsubscribe link', 'newsletter-glue' ),
					'default_link_text'	=> __( 'Unsubscribe', 'newsletter-glue' ),
					'helper' => __( 'Your subscribers click this text to unsubscribe.', 'newsletter-glue' ),
					'require_fallback' => 'yes',
				),
				'update_preferences' => array(
					'title'		=> __( 'Update preferences', 'newsletter-glue' ),
					'default_link_text' => __( 'Update preferences', 'newsletter-glue' ),
					'require_fallback' => 'yes',
				),
			),
		),
	);

	return apply_filters( 'newsletterglue_get_salesforce_tags', $merge_tags );
}