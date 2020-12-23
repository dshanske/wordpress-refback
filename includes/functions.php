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
