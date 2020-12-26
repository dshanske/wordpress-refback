<?php
/**
 * Plugin Name: Refback
 * Plugin URI: https://github.com/dshanske/wordpress-refback
 * Description: Refback Support for WordPress
 * Author: David Shanske
 * Author URI: https://david.shanske.com
 * Version: 2.0.1
 * License:
 * License URI:
 * Text Domain: refback
 * Domain Path: /languages
 */

add_action( 'plugins_loaded', array( 'Refback_Plugin', 'init' ) );

// initialize admin settings
//require_once dirname( __FILE__ ) . '/includes/class-Refback-admin.php';

/**
 * Refback Plugin Class
 *
 * @author Matthias Pfefferle
 */
class Refback_Plugin {

	/**
	 * Initialize Refback Plugin
	 */
	public static function init() {
		// If this plugin is enabled, it will enable refback support for posts and any items the webmentions plugin enables for if installed.
		$support = get_option( 'webmention_support_post_types', array( 'post', 'pages' ) );
		foreach( $support as $type ) {
			add_post_type_support( $type, 'refbacks' );
		}

		//  Add Global Functions
		require_once dirname( __FILE__ ) . '/includes/functions.php';

		// Add Refback Receiver
		require_once dirname( __FILE__ ) . '/includes/class-refback-request.php';

		// initialize Refback Receiver
		require_once dirname( __FILE__ ) . '/includes/class-refback-receiver.php';
		add_action( 'init', array( 'Refback_Receiver', 'init' ) );

		// Default Comment Status
		add_filter( 'get_default_comment_status', array( 'Refback_Plugin', 'get_default_comment_status' ), 11, 3 );
		add_filter( 'pings_open', array( 'Refback_Plugin', 'pings_open' ), 10, 2 );
	}

	public static function get_default_comment_status( $status, $post_type, $comment_type ) {
		if ( 'refback' === $comment_type ) {
			return post_type_supports( $post_type, 'refbacks' ) ? 'open' : 'closed';
		}
		// Since support for the pingback comment type is used to keep pings open...
		if ( ( 'pingback' === $comment_type ) ) {
			return ( post_type_supports( $post_type, 'refbacks' ) ? 'open' : $status );
		}

		return $status;
	}

	/**
	 * Return true if page is enabled for Homepage Refbacks
	 *
	 * @param bool $open    Whether the current post is open for pings.
	 * @param int  $post_id The post ID.
	 *
	 * @return boolean if pings are open
	 */
	public static function pings_open( $open, $post_id ) {
		if ( get_option( 'refback_home_mentions' ) === $post_id ) {
			return true;
		}

		return $open;
	}
}

if ( ! function_exists( 'get_self_link' ) ) {
	function get_self_link() {
		$host = wp_parse_url( home_url() );
		return set_url_scheme( 'http://' . $host['host'] . wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}
}
