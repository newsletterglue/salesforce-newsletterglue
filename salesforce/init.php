<?php
/**
 * Salesforce.
 */

use FuelSdk\ET_Email_SendDefinition;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Class.
 */
class NGL_Salesforce extends NGL_Abstract_Integration {

	public $app            = 'salesforce';
	public $api_key        = null;
	public $api_secret     = null;
	public $api_url        = null;
	public $api            = null;
	const  CACHE_EXPIRE_ON = 5 * MINUTE_IN_SECONDS;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Include needed files.
		include_once 'lib/api.php';

		// Read, Write permission is required for ExactTargetWSDL.xml
		chmod( dirname( __FILE__ ) . '/lib', 0777 );

		$this->get_api_key();

		add_filter( 'newsletterglue_email_content_salesforce', array( $this, 'newsletterglue_email_content_salesforce' ), 10, 3 );

		add_filter( 'newsltterglue_salesforce_html_content', array( $this, 'html_content' ), 10, 2 );
	}

	/**
	 * Get API Key.
	 */
	public function get_api_key() {

		$integrations = get_option( 'newsletterglue_integrations' );
		$integration  = isset( $integrations[ $this->app ] ) ? $integrations[ $this->app ] : '';

		$this->api_key 		= isset( $integration[ 'api_key' ] ) ? $integration[ 'api_key' ] : '';
		$this->api_secret = isset( $integration[ 'api_secret' ] ) ? $integration[ 'api_secret' ] : '';
		$this->api_url 		= isset( $integration[ 'api_url' ] ) ? $integration[ 'api_url' ] : '';
	}

	/**
	 * Add Integration.
	 */
	public function add_integration( $args = array() ) {

		$args 		= $this->get_connection_args( $args );

		$api_key    = $args[ 'api_key' ];
		$api_secret = $args[ 'api_secret' ];
		$api_url    = $args[ 'api_url' ];

		$this->api  = new NGL_Salesforce_API( $api_key, $api_secret, $api_url );

		
		$organization = $this->api->getOrganization();
		
		// Check if account is valid.
		$account = null;
		if ( ! empty( $organization ) ) {
			foreach( $organization as $key => $data ) {
				if ( isset( $data[ 'ID' ] ) && ! empty( $data[ 'ID' ] ) ) {
					$account = $organization[ $key ];
					break;
				}
			}
		}

		// clean caches
		delete_transient( "{$this->app}_cache_sender_profiles" );
		delete_transient( "{$this->app}_cache_get_lists" );
		delete_transient( "{$this->app}_cache_send_classifications" );

		if ( ! $account ) {

			$this->remove_integration();

			$result = array( 'response' => 'invalid' );

			delete_option( 'newsletterglue_salesforce' );
			
		} else {

			$this->api_key    = $api_key;
			$this->api_secret = $api_secret;
			$this->api_url    = $api_url;

			// cache send classification
			if ( false === ( $send_classifications = get_transient( "{$this->app}_cache_send_classifications" ) ) ) {
				$send_classifications = $this->api->getSendClassifications();
				set_transient( "{$this->app}_cache_send_classifications", $send_classifications, self::CACHE_EXPIRE_ON );
			}

			$account[ 'SendClassificationCustomerKey' ] = null;
			foreach( $send_classifications as $key => $classification ) {
				if ( isset( $classification[ 'CustomerKey' ] ) && ! empty( $classification[ 'CustomerKey' ] ) ) {
					$account[ 'SendClassificationCustomerKey' ] = $classification[ 'CustomerKey' ];
					break;
				}
			}
			
			// cache sender profiles
			if ( false === ( $sender_profiles = get_transient( "{$this->app}_cache_sender_profiles" ) ) ) {
				$sender_profiles = $this->api->getSenderProfiles();
				set_transient( "{$this->app}_cache_sender_profiles", $sender_profiles, self::CACHE_EXPIRE_ON );
			}

			$default_sender = null;
			foreach( $sender_profiles as $key => $profile ) {
				if ( isset( $profile[ 'CustomerKey' ] ) && ! empty( $profile[ 'CustomerKey' ] ) ) {
					$default_sender = $profile;
					if( $profile[ 'CustomerKey' ] === 'Default' ) {
						break;
					}
				}
			}

			if( $default_sender ) {
				$account[ 'FromName' ] = $default_sender[ 'FromName' ];
				$account[ 'Email' ] = $default_sender[ 'FromAddress' ];
			}

			if ( ! $this->already_integrated( $this->app, $api_key ) ) {
				$this->save_integration( $api_key, $api_secret, $api_url, $account );
			}

			$result = array( 'response' => 'successful' );

			update_option( 'newsletterglue_salesforce', $account );

		}

		return $result;
	}

	/**
	 * Save Integration.
	 */
	public function save_integration( $api_key = '', $api_secret = '', $api_url = '', $account = array() ) {

		delete_option( 'newsletterglue_integrations' );

		$integrations = get_option( 'newsletterglue_integrations' );

		$integrations[ $this->app ] = array();
		$integrations[ $this->app ][ 'api_key' ]    = $api_key;
		$integrations[ $this->app ][ 'api_secret' ] = $api_secret;
		$integrations[ $this->app ][ 'api_url' ]    = $api_url;

		$name = isset( $account[ 'FromName' ] ) ? $account[ 'FromName' ] : newsletterglue_get_default_from_name();

		$integrations[ $this->app ][ 'connection_name' ] = sprintf( __( '%s – %s', 'newsletter-glue' ), $name, newsletterglue_get_name( $this->app ) );

		update_option( 'newsletterglue_integrations', $integrations );

		// Add default options.
		$globals = get_option( 'newsletterglue_options' );

		$options = array(
			'from_name'  => $name,
			'from_email' => isset( $account[ 'Email' ] ) ? $account[ 'Email' ] : get_option( 'admin_email' ),
			'unsub'      => $this->default_unsub(),
			'send_classification_customer_key' => isset( $account[ 'SendClassificationCustomerKey' ] ) ? $account[ 'SendClassificationCustomerKey' ] : null,
		);

		foreach( $options as $key => $value ) {
			$globals[ $this->app ][ $key ] = $value;
		}

		update_option( 'newsletterglue_options', $globals );

		update_option( 'newsletterglue_admin_name', $name );

		update_option( 'newsletterglue_admin_address', isset( $account[ 'Address' ] ) ? $account[ 'Address' ] : '' );
	}

	/**
	 * Default unsub.
	 */
	public function default_unsub() {
		return '<a href="{{ unsubscribe_link }}">' . __( 'Unsubscribe', 'newsletter-glue' ) . '</a> to stop receiving these emails.';
	}

	/**
	 * Connect.
	 */
	public function connect() {

		$this->api = new NGL_Salesforce_API( $this->api_key, $this->api_secret, $this->api_url );

	}

	/**
	 * Verify email address.
	 */
	public function verify_email( $email = '' ) {

		if ( ! $email ) {
			$response = array( 'failed' => __( 'Please enter email', 'newsletter-glue' ) );
		} elseif ( ! is_email( $email ) ) {
			$response = array( 'failed'	=> __( 'Invalid email', 'newsletter-glue' ) );
		}

		if ( ! empty( $response ) ) {
			return $response;
		}

		$this->api = new NGL_Salesforce_API( $this->api_key, $this->api_secret, $this->api_url );

		// cache sender profiles
		if ( false === ( $sender_profiles = get_transient( "{$this->app}_cache_sender_profiles" ) ) ) {
			$sender_profiles = $this->api->getSenderProfiles();
			set_transient( "{$this->app}_cache_sender_profiles", $sender_profiles, self::CACHE_EXPIRE_ON );
		}

		// Check if email is a valid sender.
		$verified = false;
		if ( ! empty( $sender_profiles ) ) {
			foreach( $sender_profiles as $key => $profile ) {
				if ( ! empty( $profile[ 'FromAddress' ] ) && ( trim( $profile[ 'FromAddress' ] ) === trim( $email ) ) ) {
					$verified = true;
					break;
				}
			}
		}

		if ( $verified ) {

			$response = array(
				'success'	=> '<strong>' . __( 'Verified', 'newsletter-glue' ) . '</strong>',
			);

		} else {

			$response = array(
				'failed'			=> __( 'Not verified', 'newsletter-glue' ),
				'failed_details'	=> '<a href="https://help.salesforce.com/s/articleView?id=sf.mc_es_domain_verification.htm" target="_blank">' . __( 'Verify email now', 'newsletter-glue' ) . ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg></a> <a href="https://newsletterglue.com/docs/email-verification-my-email-is-not-verified/" target="_blank">' . __( 'Learn more', 'newsletter-glue' ) . ' <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"></path></svg></a>',
			);

		}

		return $response;
	}

	/**
	 * Configure options array for this ESP.
	 */
	public function option_array() {
		return array(
			'lists' 	=> array(
				'type'		=> 'select',
				'callback'	=> 'get_lists',
				'title'     => __( 'Lists', 'newsletter-glue' ),
				'help'		=> __( 'Who receives your email.', 'newsletter-glue' ),
			),
		);
	}

	/**
	 * Get form defaults.
	 */
	public function get_form_defaults() {

		$this->api = new NGL_Salesforce_API( $this->api_key, $this->api_secret, $this->api_url );

		$defaults = array();

		return $defaults;

	}

	/**
	 * Get lists compat.
	 */
	public function _get_lists_compat() {
		$this->api = new NGL_Salesforce_API( $this->api_key, $this->api_secret, $this->api_url );

		return $this->get_lists();
	}

	/**
	 * Get Lists.
	 */
	public function get_lists() {
		$_lists = array();

		// cache lists
		if ( false === ( $response = get_transient( "{$this->app}_cache_get_lists" ) ) ) {
			$response = array();
			
			$lists = $this->api->getLists();
			if ( ! empty( $lists ) ) {
				foreach( $lists as $key => $data ) {
					$response[ $data[ 'ID' ] ] = $data[ 'ListName' ];
				}
			}

			
			$dataExtension = $this->api->getDataExtension();
			if ( ! empty( $dataExtension ) ) {
				foreach( $dataExtension as $key => $data ) {
					$idx = $data[ 'CustomerKey' ] . '_dex';
					$response[ $idx ] = $data[ 'Name' ];
				}
			}
			
			asort( $response );
			
			set_transient( "{$this->app}_cache_get_lists", $response, self::CACHE_EXPIRE_ON );
		}

		if ( ! empty( $response ) ) {
			$_lists = $response;
		}

		return $_lists;
	}

	/**
	 * Customize content.
	 */
	public function newsletterglue_email_content_salesforce( $content, $post, $subject ) {

		$filter = apply_filters( 'newsletterglue_auto_unsub_link', true, $this->app );

		if ( ! $filter ) {
			return $content;
		}

		if ( strstr( $content, '{{ unsubscribe_link }}' ) ) {
			return $content;
		}

		$post_id		= $post->ID;
		$data 			= get_post_meta( $post_id, '_newsletterglue', true );
		$default_unsub  = $this->default_unsub();
		$unsub		 	= ! empty( $data[ 'unsub' ] ) ? $data[ 'unsub' ] : $default_unsub;

		if ( empty( $unsub ) ) {
			$unsub = $this->default_unsub();
		}

		$unsub = str_replace( '{{ unsubscribe_link }}', '%%unsub_center_url%%', $unsub ); // phpcs:ignore

		$content .= '<p class="ngl-unsubscribe">' . wp_kses_post( $unsub ) . '</p>';

		return $content;

	}

	/**
	 * Replace universal tags with esp tags.
	 */
	public function html_content( $html, $post_id ) {

		$html = $this->convert_tags( $html, $post_id );

		return $html;
	}

	/**
	 * Get email verify help.
	 */
	public function get_email_verify_help() {
		return 'https://help.salesforce.com/s/articleView?id=sf.mc_es_domain_verification.htm';
	}

	/**
	 * Code supported tags for this ESP.
	 */
	public function get_tag( $tag, $post_id = 0, $fallback = null ) {

		switch ( $tag ) {
			case 'unsubscribe_link' :
				return '%%unsub_center_url%%';
			break;
			case 'admin_name' :
				return '%%member_busname%%';
			break;
			case 'admin_address' :
				return '%%member_addr%% %%member_city%%, %%member_state%% %%member_postalcode%% %%member_country%%';
			break;
			case 'first_name' :
				return '%%emailname_%%';
			break;
			case 'email' :
				return '%%emailaddr%%';
			break;
			case 'street' :
				return '%%member_addr%%';
			break;
			case 'city' :
				return '%%member_city%%';
			break;
			case 'state' :
				return '%%member_state%%';
			break;
			case 'postalcode' :
				return '%%member_postalcode%%';
			break;
			case 'country' :
				return '%%member_country%%';
			break;
			case 'job_id' :
				return '%%jobid%%';
			break;
			case 'member_id' :
				return '%%memberid%%';
			break;
			case 'subscriber_key' :
				return '%%_subscriberkey%%';
			break;
			case 'update_preferences' :
				return '%%profile_center_url%%';
			break;
			default :
				return apply_filters( "newsletterglue_{$this->app}_custom_tag", '', $tag, $post_id );
			break;
		}

		return false;
	}

	/**
	 * Send newsletter.
	 */
	public function send_newsletter( $post_id = 0, $data = array(), $test = false ) {

		if ( defined( 'NGL_SEND_IN_PROGRESS' ) ) {
			return;
		}

		define( 'NGL_SEND_IN_PROGRESS', 'sending' );

		$post = get_post( $post_id );

		// If no data was provided. Get it from the post.
		if ( empty( $data ) ) {
			$data = get_post_meta( $post_id, '_newsletterglue', true );
		}

		$subject 		= isset( $data['subject'] ) ? ngl_safe_title( $data[ 'subject' ] ) : ngl_safe_title( $post->post_title );
		$from_name		= isset( $data['from_name'] ) ? $data['from_name'] : newsletterglue_get_default_from_name();
		$from_email		= isset( $data['from_email'] ) ? $data['from_email'] : $this->get_current_user_email();
		$lists			= isset( $data['lists'] ) && ! empty( $data['lists'] ) ? $data['lists'] : '';
		$segments		= isset( $data['segments'] ) && ! empty( $data['segments'] ) ? $data['segments'] : '';
		$schedule   	= isset( $data['schedule'] ) ? $data['schedule'] : 'immediately';
		$unsub_groups   = isset( $data['unsub_groups'] ) ? $data['unsub_groups'] : '';

		$subject = apply_filters( 'newsletterglue_email_subject_line', $subject, $post, $data, $test, $this );

		// Empty content.
		if ( $test && isset( $post->post_status ) && $post->post_status === 'auto-draft' ) {

			$response['fail'] = $this->nothing_to_send();

			return $response;

		}

		// Do test email.
		if ( $test ) {

			$response = array();

            $test_email = $data[ 'test_email' ];
            $test_email_arr = explode( ',', $test_email );
            $test_emails = array_map( 'trim', $test_email_arr );
            if ( ! empty( $test_emails ) ) {
                foreach( $test_emails as $testid ) {
                    if ( ! is_email( $testid ) ) {
                        $response[ 'fail' ] = __( 'Please enter a valid email', 'newsletter-glue' );
                    }
                }
            }
            if ( ! empty( $response['fail'] ) ) {
                return $response;
            }

			add_filter( 'wp_mail_content_type', array( $this, 'wp_mail_content_type' ) );

			$body = newsletterglue_generate_content( $post, $subject, $this->app );

			wp_mail( $test_emails, sprintf( __( '[Test] %s', 'newsletter-glue' ), $subject ), $body ); // phpcs:ignore

			$response['success'] = $this->get_test_success_msg();

			return $response;
		}

		// Send a campaign or draft.
		$this->api = new NGL_Salesforce_API( $this->api_key, $this->api_secret, $this->api_url );

		$sendClassficationCK = newsletterglue_get_option( 'send_classification_customer_key', $this->app );

		// send classification customer key is required
		if ( empty( $sendClassficationCK ) ) {
			$status = array( 'status' => 'error' );
			newsletterglue_add_campaign_data( $post_id, $subject, $this->get_status( $status, __( 'Please setup send classification from your salesforce marketing cloud account.', 'newsletter-glue' ) ) );
			return $status;
		}

		$postEmail = $this->api->postEmail(
			ngl_safe_title( $post->post_title ),
			$subject,
			newsletterglue_generate_content( $post, $subject, $this->app )
		);

		if ( ! isset( $postEmail[0][ 'NewID' ] ) ) {
			$status = array( 'status' => 'error', 'error' => $postEmail[0][ 'StatusMessage' ] );
			newsletterglue_add_campaign_data( $post_id, $subject, $this->prepare_message( $status ) );
			return $status;
		}

		$emailId = $postEmail[0][ 'NewID' ];

		$sendDefinitionCK = uniqid();
		$email = new ET_Email_SendDefinition();
		$email->authStub = $this->api->getClient();
		$email->props = array(
			'Name'        => $subject . " (#$sendDefinitionCK)",
			'CustomerKey' => $sendDefinitionCK,
			'Description' => ngl_safe_title( $post->post_title ),
		);
		$email->props[ 'SendClassification' ] = array( 'CustomerKey' => $sendClassficationCK );
		$email->props[ 'SenderProfile' ] = array( 'FromName' => $from_name, 'FromAddress' => $from_email );

		if( str_contains( $lists, '_dex' ) ) {
			// send email to data extension
			$email->props[ 'SendDefinitionList' ] = array( 'CustomerKey' => substr( $lists, 0, -4 ), 'DataSourceTypeID' => 'CustomObject' );
		} else {
			// send email to list
			$email->props[ 'SendDefinitionList' ] = array( 'List' => array( 'ID' => $lists ), 'DataSourceTypeID' => 'List' );
		}
		
		$email->props[ 'Email' ] = array( 'ID' => $emailId );
		$postSendDefinition = $email->post();

		if ( ! $postSendDefinition->status ) {
			$status = array( 'status' => 'error', 'error' => $postSendDefinition->results[0]->StatusMessage );
			newsletterglue_add_campaign_data( $post_id, $subject, $this->prepare_message( $status ) );
			return $status;
		}

		if ( $schedule === 'draft' ) {
			$status = array( 'status' => 'draft' );
		} else {
			$status = array( 'status' => 'sent' );
			$sendEmail = $email->send();
			
			if( ! $sendEmail->status ) {
				$status = array( 'status' => 'error', 'error' => $sendEmail->results[0]->StatusMessage );
				newsletterglue_add_campaign_data( $post_id, $subject, $this->prepare_message( $status ) );
				return $status;
			}
		}

		newsletterglue_add_campaign_data( $post_id, $subject, $this->prepare_message( $status ), $emailId );

		return $status;
	}

	/**
	 * Prepare result for plugin.
	 */
	public function prepare_message( $result ) {
		$output = array();

		if ( isset( $result['status'] ) ) {

			if ( $result['status'] === 'draft' ) {
				$output[ 'status' ]		= 200;
				$output[ 'type' ]		= 'neutral';
				$output[ 'message' ]    = __( 'Saved as draft', 'newsletter-glue' );
			}

			if ( $result[ 'status' ] === 'sent' ) {
				$output[ 'status' ] 	= 200;
				$output[ 'type'   ] 	= 'success';
				$output[ 'message' ] 	= __( 'Sent', 'newsletter-glue' );
			}

			if ( $result[ 'status' ] === 'error' ) {
				$tags = array(
					"%%Member_Busname%%"          => "{{admin_name}}",
					"%%emailname_%%"              => "{{first_name}}",
					"%%emailaddr%%"               => "{{email}}",
					"%%member_addr%%"             => "{{street}}",
					"%%member_city%%"             => "{{city}}",
					"%%member_state%%"            => "{{state}}",
					"%%member_postalcode%%"       => "{{postalcode}}",
					"%%member_country%%"          => "{{country}}",
					"%%jobid%%"                   => "{{job_id}}",
					"%%memberid%%"                => "{{member_id}}",
					"%%_subscriberkey%%"          => "{{subscriber_key}}",
					"%%profile_center_url%%"      => "{{update_preferences}}",
					"%%unsub_center_url%%"        => "{{unsubscribe_link}}",
					"Missing_Profile_Center_Link" => "{{update_preferences}}",
				);

				// transform error message from multiline to single line
				$result[ 'error' ] = str_replace( array("\n", "\r"), ' ', $result[ 'error' ] );

				// transform to lowercase
				$result[ 'error' ] = strtolower( $result[ 'error' ] );

				// replace tags
				foreach( $tags as $key => $value ) {
					$result[ 'error' ] = str_replace( strtolower( $key ), $value, $result[ 'error' ] );
				}
				
				// remove duplicate errors
				$result[ 'error' ] = implode( ' ', array_unique( explode( ' ', $result[ 'error' ] ) ) );

				$output[ 'status' ] 	= 400;
				$output[ 'type' ] 		= 'error';
				$output[ 'message' ]	= $result[ 'error' ];
			}

		}

		return $output;

	}

	/**
	 * Add user to this ESP.
	 */
	public function add_user( $data ) {
		extract( $data );

		if ( empty( $email ) ) {
			return -1;
		}

		$this->api = new NGL_Salesforce_API( $this->api_key, $this->api_secret, $this->api_url );

		if ( ! empty( $list_id ) ) {

			$result = $this->api->addSubscriberToList( $email, $list_id );

		}

		return $result;

	}
}