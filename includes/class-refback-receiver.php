<?php
/**
 * Refback Receiver Class
 *
 * @author David Shanske
 */
class Refback_Receiver {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		add_action( 'pre_get_posts', array( static::class, 'post' ), 10 );

		add_action( 'do_refbacks', array( static::class, 'do_refback' ), 10 );

		add_filter( 'duplicate_comment_id', array( static::class, 'disable_wp_check_dupes' ), 20, 2 );

		// Refback helper
		add_filter( 'refback_comment_data', array( static::class, 'refback_verify' ), 11, 1 );

		// Refback data handler
		add_filter( 'refback_comment_data', array( static::class, 'default_title_filter' ), 21, 1 );
		add_filter( 'refback_comment_data', array( static::class, 'default_content_filter' ), 22, 1 );
		add_filters( 'semantic_linkbacks_enhance_comment_types', array( static::class, 'semantic_linkbacks' ), 11 );
	}

	/**
	 * Add refbacks to Semantic Linkbacks Enhancement.
	 *
	 * @param array $comment_types Comment Types.
	 * @return array Comment Types.
	 */
	public static function semantic_linkbacks( $comment_types ) {
		$comment_types[] = 'refback';
		return array_unique( $comment_types );
	}

	/**
	 * Inverse of parse_url
	 *
	 * Slightly modified from p3k-utils (https://github.com/aaronpk/p3k-utils)
	 * Copyright 2017 Aaron Parecki, used with permission under MIT License
	 *
	 * @link http://php.net/parse_url
	 * @param  string $parsed_url the parsed URL (wp_parse_url)
	 * @return string             the final URL
	 */
	public static function build_url( $parsed_url ) {
		$scheme   = ! empty( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host     = ! empty( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port     = ! empty( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user     = ! empty( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass     = ! empty( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$path     = ! empty( $parsed_url['path'] ) ? $parsed_url['path'] : '';
		$query    = ! empty( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
		$fragment = ! empty( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	}


	public static function normalize_url( $url, $force_ssl = false ) {
		$parts = wp_parse_url( $url );
		if ( array_key_exists( 'path', $parts ) && '' === $parts['path'] ) {
			return false;
		}

		// wp_parse_url returns just "path" for naked domains
		if ( count( $parts ) === 1 && array_key_exists( 'path', $parts ) ) {
			$parts['host'] = $parts['path'];
			unset( $parts['path'] );
		}
		if ( ! array_key_exists( 'scheme', $parts ) ) {
			$parts['scheme'] = $force_ssl ? 'https' : 'http';
		} elseif ( $force_ssl ) {
			$parts['scheme'] = 'https';
		}
		if ( ! array_key_exists( 'path', $parts ) ) {
			$parts['path'] = '/';
		}

		// Invalid scheme
		if ( ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
					return false;
		}
		return self::build_url( $parts );
	}


	public static function post() {
		if ( is_admin() ) {
			return;
		}

		$source = wp_get_raw_referer();
		if ( ! $source ) {
			return;
		}

		$target = get_self_link();

		if ( ! isset( $target ) ) {
			return;
		}

		$source = self::normalize_url( $source );
		$target = self::normalize_url( $target );

		// Do not accept self refbacks.
		if ( wp_parse_url( $target, PHP_URL_HOST ) === wp_parse_url( $source, PHP_URL_HOST ) ) {
			return;
		}

		// This needs to be stored here as it might not be available later.
		$comment_author_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
		$comment_agent     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		$comment_date     = current_time( 'mysql' );
		$comment_date_gmt = get_gmt_from_date( $comment_date );

		// change this if your theme can't handle the Refbacks comment type
		$comment_type = REFBACK_COMMENT_TYPE;

		// change this if you want to auto approve your refbacks
		$comment_approved = 0;

		$comment_meta = array(
			'protocol'   => 'refback',
			'source_url' => $source,
		);

		$commentdata = compact( 'comment_type', 'comment_approved', 'comment_agent', 'comment_date', 'comment_date_gmt', 'comment_meta', 'source', 'target' );

		// Fork into the background to avoid slowing each retrieval.
		wp_schedule_single_event( time(), 'do_refbacks', array( $commentdata ) );

	}

	public static function do_refback( $commentdata ) {
		if ( empty( $commentdata ) ) {
			return;
		}

		if ( ! array_key_exists( 'target', $commentdata ) && ! array_key_exists( 'source', $commentdata ) ) {
			return;
		}

		$comment_post_id = url_to_postid( $commentdata['target'] );

		// check if post id exists.
		if ( ! $comment_post_id ) {
			return;
		}

		if ( url_to_postid( $commentdata['source'] ) === $comment_post_id ) {
			return;
		}

		$post = get_post( $comment_post_id );
		if ( ! $post ) {
			return;
		}

		$commentdata['comment_post_ID']   = $comment_post_id;
		$commentdata['comment_author_IP'] = $comment_author_ip;
		// Set Comment Author URL to Source
		$commentdata['comment_author_url'] = esc_url_raw( $commentdata['source'] );
		// Save Source to Meta to Allow Author URL to be Changed and Parsed
		$commentdata['comment_meta'] = array(
			'refback_source_url' => $commentdata['comment_author_url'],
		);

		$commentdata['comment_parent'] = '';

		// add empty fields
		$commentdata['comment_author_email'] = '';

		$args     = array(
			'post_id'    => $comment_post_id,
			'author_url' => $source,
			'count'      => true,
		);
		$comments = get_comments( $args );
		if ( 0 !== $comments ) {
			return;
		}

		/**
		 * Filter Comment Data for Refbacks.
		 *
		 * All verification functions and content generation functions are added to the comment data.
		 *
		 * @param array $commentdata
		 *
		 * @return array|null|WP_Error $commentdata The Filtered Comment Array or a WP_Error object.
		 */
		$commentdata = apply_filters( 'refback_comment_data', $commentdata );

		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			/**
			 * Fires if Error is Returned from Filter.
			 *
			 * Added to support deletion.
			 *
			 * @param array $commentdata
			 */
			do_action( 'refback_data_error', $commentdata );

			return;
		}

		// disable flood control
		remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// update or save refback
		if ( empty( $commentdata['comment_ID'] ) ) {
			// save comment
			$commentdata['comment_ID'] = wp_new_comment( $commentdata, true );

			/**
			 * Fires when a refback is created.
			 *
			 * Mirrors comment_post and pingback_post.
			 *
			 * @param int $comment_ID Comment ID.
			 * @param array $commentdata Comment Array.
			 */
			do_action( 'refback_post', $commentdata['comment_ID'], $commentdata );
		} else {
			// update comment
			wp_update_comment( $commentdata );
			/**
			 * Fires after a refback is updated in the database.
			 *
			 * The hook is needed as the comment_post hook uses filtered data
			 *
			 * @param int   $comment_ID The comment ID.
			 * @param array $data       Comment data.
			 */
			do_action( 'edit_refback', $commentdata['comment_ID'], $commentdata );
		}
		// re-add flood control
		add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}

	public static function refback_verify( $data ) {
		if ( ! $data || is_wp_error( $data ) ) {
			return $data;
		}

		if ( ! is_array( $data ) || empty( $data ) ) {
			return;
		}

		$request = new Refback_Request();
		$return  = $request->fetch( $data['source'] );

		// check if source is accessible
		if ( is_wp_error( $return ) ) {
			return $return;
		}

			// check if source really links to target
		if ( ! strpos(
			htmlspecialchars_decode( $request->get_body() ),
			str_replace(
				array(
					'http://www.',
					'http://',
					'https://www.',
					'https://',
				),
				'',
				untrailingslashit( preg_replace( '/#.*/', '', $data['target'] ) )
			)
		) ) {
			return new WP_Error(
				'target_not_found',
				esc_html__( 'Cannot find target link', 'refback' ),
				array(
					'status' => 400,
					'data'   => $data,
				)
			);
		}

		if ( ! function_exists( 'wp_kses_post' ) ) {
			include_once ABSPATH . 'wp-includes/kses.php';
		}

		$commentdata = array(
			'content_type'           => $request->get_content_type(),
			'remote_source_original' => $request->get_body(),
			'remote_source'          => refback_sanitize_html( $request->get_body() ),
		);

		return array_merge( $commentdata, $data );
	}

	/**
	 * Disable the WordPress `check dupes` functionality
	 *
	 * @param int $dupe_id ID of the comment identified as a duplicate.
	 * @param array $commentdata Data for the comment being created.
	 *
	 * @return int
	 */
	public static function disable_wp_check_dupes( $dupe_id, $commentdata ) {
		if ( ! $dupe_id ) {
			return $dupe_id;
		}

		$comment_dupe = get_comment( $dupe_id, ARRAY_A );

		if ( $comment_dupe['comment_post_ID'] === $commentdata['comment_post_ID'] ) {
			return $dupe_id;
		}

		if (
		( isset( $commentdata['comment_type'] ) && 'refback' === $commentdata['comment_type'] ) ||
		( isset( $commentdata['comment_meta'] ) && ! empty( $commentdata['comment_meta']['semantic_linkbacks_type'] ) )
		) {
			return 0;
		}

		return $dupe_id;
	}

	/**
	 * Try to make a nice title (username)
	 *
	 * @param array $commentdata the comment-data
	 *
	 * @return array the filtered comment-data
	 */
	public static function default_title_filter( $commentdata ) {
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			return $commentdata;
		}

		$match = array();

		$meta_tags = wp_get_meta_tags( $commentdata['remote_source_original'] );

		// use meta-author
		if ( array_key_exists( 'author', $meta_tags ) ) {
			$commentdata['comment_author'] = $meta_tags['author'];
		} elseif ( array_key_exists( 'og:title', $meta_tags ) ) {
			// Use Open Graph Title if set
			$commentdata['comment_author'] = $meta_tags['og:title'];
		} elseif ( preg_match( '/<title>(.+)<\/title>/i', $commentdata['remote_source_original'], $match ) ) { // use title
			$commentdata['comment_author'] = trim( $match[1] );
		} else {
			// or host
			$host = wp_parse_url( $commentdata['comment_author_url'], PHP_URL_HOST );
			// strip leading www, if any
			$commentdata['comment_author'] = preg_replace( '/^www\./', '', $host );
		}

		return $commentdata;
	}

	/**
	 * Try to make a nice comment
	 *
	 * @param array $commentdata the comment-data
	 *
	 * @return array the filtered comment-data
	 */
	public static function default_content_filter( $commentdata ) {
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			return $commentdata;
		}

		// get post format
		$post_id     = $commentdata['comment_post_ID'];
		$post_format = get_post_format( $post_id );

		// replace "standard" with "Article"
		if ( ! $post_format || 'standard' === $post_format ) {
			$post_format = 'Article';
		} else {
			$post_formatstrings = get_post_format_strings();
			// get the "nice" name
			$post_format = $post_formatstrings[ $post_format ];
		}

		$host = wp_parse_url( $commentdata['comment_author_url'], PHP_URL_HOST );

		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );

		// generate default text
		// translators: This post format was mentioned on this URL with this domain name
		$commentdata['comment_content'] = sprintf( __( 'This %1$s was mentioned on <a href="%2$s">%3$s</a>', 'refback' ), $post_format, esc_url( $commentdata['comment_author_url'] ), $host );

		return $commentdata;
	}
}
