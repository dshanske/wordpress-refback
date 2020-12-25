<?php
/* Global Functions
 */

if ( ! function_exists( 'refback_load_domdocument' ) ) :
	function refback_load_domdocument( $content ) {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$content = mb_convert_encoding( $content, 'HTML-ENTITIES', mb_detect_encoding( $content ) );
		}
		$doc->loadHTML( $content );
		libxml_use_internal_errors( false );
		return $doc;
	}
endif;


/**
 *  Sanitize HTML. To be used on content elements after parsing.
 *
 * @param string $content The HTML to Sanitize.
 *
 * @return string Sanitized HTML.
 */
function refback_sanitize_html( $content ) {
	if ( ! is_string( $content ) ) {
		return $content;
	}

	// Strip HTML Comments.
	$content = preg_replace( '/<!--(.|\s)*?-->/', '', $content );

	// Only allow approved HTML elements
	$allowed = array(
		'a'          => array(
			'href'     => array(),
			'name'     => array(),
			'hreflang' => array(),
			'rel'      => array(),
		),
		'abbr'       => array(),
		'b'          => array(),
		'br'         => array(),
		'code'       => array(),
		'ins'        => array(),
		'del'        => array(),
		'em'         => array(),
		'i'          => array(),
		'q'          => array(),
		'strike'     => array(),
		'strong'     => array(),
		'time'       => array(),
		'blockquote' => array(),
		'pre'        => array(),
		'p'          => array(),
		'h1'         => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'h5'         => array(),
		'h6'         => array(),
		'ul'         => array(),
		'li'         => array(),
		'ol'         => array(),
		'span'       => array(),
		'img'        => array(
			'src'    => array(),
			'alt'    => array(),
			'title'  => array(),
			'srcset' => array(),
		),
		'video'      => array(
			'src'      => array(),
			'duration' => array(),
			'poster'   => array(),
		),
		'audio'      => array(
			'duration' => array(),
			'src'      => array(),
		),
		'track'      => array(),
		'source'     => array(),
	);
		return trim( wp_kses( $content, $allowed ) );
}

/**
 * Return the post_id for a URL filtered for refbacks.
 *
 * Allows redirecting to another id to add linkbacks to the home page or archive
 * page or taxonomy page.
 *
 * @since 3.1.0
 *
 * @uses apply_filters calls "webmention_post_id" on the post_ID
 *
 * @param string $url URL.
 * @return int $id Return 0 if no post ID found or a post ID.
 */
function refback_url_to_postid( $url ) {
	// Use the webmention function if available.
	if ( function_exists( 'webmention_url_to_postid' ) ) {
		return apply_filters( 'refback_post_id', webmention_url_to_postid( $url ), $id );
	}

	$id = wp_cache_get( base64_encode( $url ), 'refback_url_to_postid' );
	if ( false !== $id ) {
		return apply_filters( 'refback_post_id', $id, $url );
	}

	$id = url_to_postid( $url );

	if ( ! $id && post_type_supports( 'attachment', 'refback' ) ) {
		$ext = pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
		if ( ! empty( $ext ) ) {
			$id = attachment_url_to_postid( $url );
		}
	}
	wp_cache_set( base64_encode( $url ), $id, 'refback_url_to_postid', 300 );
	return apply_filters( 'refback_post_id', $id, $url );
}
