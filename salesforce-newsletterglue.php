<?php
/**
 * Plugin Name: Salesforce Marketing Cloud for Newsletter Glue
 * Plugin URI: https://newsletterglue.com/
 * Description: Email posts to subscribers from the WordPress editor. Works with Salesforce Marketing Cloud.
 * Author: Newsletter Glue
 * Author URI: https://newsletterglue.com
 * Requires at least: 6.0
 * Requires PHP: 7.3
 * Version: 1.0.0
 * Text Domain: newsletter-glue
 * Domain Path: /i18n/languages/
 * 
 * @package Newsletter Glue
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Class.
 */
final class NG_Salesforce {
	/** Singleton *************************************************************/

	/**
	 * Class instance.
	 * 
	 * @var $instance
	 */
	private static $instance;

	/**
	 * The lists.
	 * 
	 * @var $thelists
	 */
	public static $the_lists = null;

	/**
	 * Main Instance.
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof NG_Salesforce ) ) {
			self::$instance = new NG_Salesforce();
			self::$instance->setup_constants();
			self::$instance->includes();
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'newsletter-glue' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class.
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'newsletter-glue' ), '1.0.0' );
	}

	/**
	 * Setup plugin constants.
	 */
	private function setup_constants() {

		// Plugin version.
		if ( ! defined( 'NGSF_VERSION' ) ) {
			define( 'NGSF_VERSION', '1.0.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'NGSF_PLUGIN_DIR' ) ) {
			define( 'NGSF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'NGSF_PLUGIN_URL' ) ) {
			define( 'NGSF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'NGSF_PLUGIN_FILE' ) ) {
			define( 'NGSF_PLUGIN_FILE', __FILE__ );
		}

		// Feedback server.
		if ( ! defined( 'NGSF_FEEDBACK_SERVER' ) ) {
			define( 'NGSF_FEEDBACK_SERVER', 'https://newsletterglue.com' );
		}
	}

	/**
	 * Include required files.
	 */
	private function includes() {

		require_once NGSF_PLUGIN_DIR . 'filters.php';

		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {

		}

	}

}

/**
 * The main function.
 */
if ( ! function_exists( 'newsletterglue_salesforce' ) ) {
	/**
	 * Run NG instance.
	 */
	function newsletterglue_salesforce() {
		return NG_Salesforce::instance();
	}
}

// Get Running.
newsletterglue_salesforce();
