<?php
/**
 * Template functions, used when displaying content on frontend.
 *
 * @package SM/Core/Templating
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) or die;

add_filter( 'template_include', function ( $template ) {
	if ( sm_get_option( 'disable_layouts', false ) || sm_get_option( 'theme_compatibility' ) ) {
		return $template;
	}

	if ( is_singular( 'wpfc_sermon' ) ) {
		$default_file = 'single-wpfc_sermon.php';
	} elseif ( is_tax( get_object_taxonomies( 'wpfc_sermon' ) ) ) {
		$term = get_queried_object();

		if ( is_tax( array(
			'wpfc_preacher',
			'wpfc_sermon_series',
			'wpfc_sermon_topics',
			'wpfc_bible_book',
			'wpfc_service_type',
		) ) ) {
			$default_file = 'taxonomy-' . $term->taxonomy . '.php';

			if ( ! file_exists( get_stylesheet_directory() . '/' . $default_file ) ) {
				$default_file = 'archive-wpfc_sermon.php';
			}
		} else {
			$default_file = 'archive-wpfc_sermon.php';
		}
	} elseif ( is_post_type_archive( 'wpfc_sermon' ) ) {
		$default_file = 'archive-wpfc_sermon.php';
	} else {
		$default_file = '';
	}

	if ( $default_file ) {
		if ( file_exists( get_stylesheet_directory() . '/' . $default_file ) ) {
			return get_stylesheet_directory() . '/' . $default_file;
		}

		return SM_PATH . 'views/' . $default_file;
	}

	return $template;
} );

/**
 * Replaces default the_content and/or the_excerpt with proper sermon content.
 *
 * @param string $content The default content.
 *
 * @return string The modified content if it's Sermon related data.
 */
function add_wpfc_sermon_content( $content ) {
	if ( 'wpfc_sermon' === get_post_type() && in_the_loop() ) {
		if ( sm_get_option( 'disable_layouts' ) ) {
			return $content;
		}

		if ( doing_filter( 'the_excerpt' ) && sm_get_option( 'disable_the_excerpt' ) ) {
			return $content;
		}

		if ( ! is_feed() && ( is_archive() || is_search() ) ) {
			$content = wpfc_sermon_excerpt_v2( true );
		} elseif ( is_singular() && is_main_query() ) {
			$content = wpfc_sermon_single_v2( true );
		}
	}

	return $content;
}

add_filter( 'the_content', 'add_wpfc_sermon_content' );
add_filter( 'the_excerpt', 'add_wpfc_sermon_content' );

/**
 * Render sermon sorting/filtering.
 *
 * @param array $args Display options. See the 'sermon_sort_fields' shortcode for array items.
 *
 * @see   WPFC_Shortcodes->displaySermonSorting()
 *
 * @return string The HTML.
 *
 * @since 2.5.0 added $args
 */
function render_wpfc_sorting( $args = array() ) {

	if ( ! ( defined( 'WPFC_SM_SHORTCODE' ) && WPFC_SM_SHORTCODE === true ) ) :
		$action = ( sm_get_option( 'home_url_filtering' ) ? home_url() : site_url() ) . '/' . ( sm_get_option( 'common_base_slug' ) ? ( sm_get_option( 'archive_slug' ) ?: 'sermons' ) : '' );
	else :
		$action = $_SERVER['REQUEST_URI'];
	endif;

	// Filters HTML fields data.
	$filters = array(
		array(
			'className' => 'sortPreacher',
			'taxonomy'  => 'wpfc_preacher',
			'title'     => sm_get_option( 'preacher_label' ) ?: __( 'Preacher', 'sermon-manager-for-wordpress' ),
		),
		array(
			'className' => 'sortSeries',
			'taxonomy'  => 'wpfc_sermon_series',
			'title'     => __( 'Series', 'sermon-manager-for-wordpress' ),
		),
		array(
			'className' => 'sortTopics',
			'taxonomy'  => 'wpfc_sermon_topics',
			'title'     => __( 'Topic', 'sermon-manager-for-wordpress' ),
		),
		array(
			'className' => 'sortBooks',
			'taxonomy'  => 'wpfc_bible_book',
			'title'     => __( 'Book', 'sermon-manager-for-wordpress' ),
		),
		array(
			'className' => 'sortServiceTypes',
			'taxonomy'  => 'wpfc_service_type',
			'title'     => __( 'Service Type', 'sermon-manager-for-wordpress' ),
		),
	);

	$visibility_mapping = array(
		'wpfc_sermon_topics' => 'hide_topics',
		'wpfc_sermon_series' => 'hide_series',
		'wpfc_preacher'      => 'hide_preachers',
		'wpfc_bible_book'    => 'hide_books',
		'wpfc_service_type'  => 'hide_service_types',
	);

	// Save orig args for filters.
	$orig_args = $args;

	$default = array(
		'id'                  => 'wpfc_sermon_sorting',
		'classes'             => '',
		'series_filter'       => '',
		'service_type_filter' => '',
		'series'              => '',
		'preachers'           => '',
		'topics'              => '',
		'books'               => '',
		'visibility'          => 'suggest',
		'hide_topics'         => '',
		'hide_series'         => '',
		'hide_preachers'      => '',
		'hide_books'          => '',
		'hide_service_types'  => sm_get_option( 'service_type_filtering' ) ? '' : 'yes',
		'hide_filters'        => ! sm_get_option( 'hide_filters' ),
	);
	$args    = $args + $default;

	/**
	 * Allows to filter filtering args.
	 *
	 * @since 2.13.5
	 * @since 2.15.0 - add other args, except $args.
	 *
	 * @param array  $args               The args.
	 * @param array  $orig_args          The unmodified args.
	 * @param string $action             The form URL.
	 * @param array  $filters            Filters HTML form data. i.e. no idea.
	 * @param array  $visibility_mapping Taxonomy slug -> args parameter name
	 */
	$args = apply_filters( 'sm_render_wpfc_sorting_args', $args, $orig_args, $action, $filters, $visibility_mapping );

	$hide_filters = $args['hide_filters'];

	/**
	 * Allows to skip rendering of filtering completely.
	 *
	 * @since 2.13.5
	 * @since 2.15.0 - add other parameters, except $hide_filters.
	 *
	 * @param bool   $hide_filters       True to show, false to hide. Default as it is defined in settings.
	 * @param array  $args               The args.
	 * @param array  $orig_args          The unmodified args.
	 * @param string $action             The form URL.
	 * @param array  $filters            Filters HTML form data. i.e. no idea.
	 * @param array  $visibility_mapping Taxonomy slug -> args parameter name
	 */
	if ( apply_filters( 'sm_render_wpfc_sorting', $hide_filters, $args, $orig_args, $action, $filters, $visibility_mapping ) ) {
		$content = wpfc_get_partial( 'content-sermon-filtering', array(
			'action'             => $action,
			'filters'            => $filters,
			'visibility_mapping' => $visibility_mapping,
			'args'               => $args,
		) );
	} else {
		$content = '';
	}

	/**
	 * Allows to filter the output of filter rendering.
	 *
	 * @param string $content            The original content.
	 * @param array  $args               The args.
	 * @param array  $orig_args          The unmodified args.
	 * @param string $action             The form URL.
	 * @param array  $filters            Filters HTML form data. i.e. no idea.
	 * @param array  $visibility_mapping Taxonomy slug -> args parameter name
	 *
	 * @since 2.15.0
	 */
	return apply_filters( 'render_wpfc_sorting_output', $content, $args, $orig_args, $action, $filters, $visibility_mapping );
}

/**
 * Return single sermon meta key content from inside a loop.
 *
 * @param string $meta_key The meta key name.
 *
 * @return mixed|null The meta key content or null if it's blank.
 */
function get_sermon_meta( $meta_key = '' ) {
	global $post;

	return get_post_meta( $post->ID, $meta_key, true ) ?: null;
}

/**
 * Pass sermon content through WordPress functions, to render shortcodes, etc.
 *
 * @param string $meta_key Sermon meta key.
 * @param int    $post_id  Post ID.
 *
 * @return string The processed content
 */
function process_wysiwyg_output( $meta_key, $post_id = 0 ) {
	global $wp_embed;

	$post_id = $post_id ? $post_id : get_the_id();

	$content = get_post_meta( $post_id, $meta_key, true );
	$content = $wp_embed->autoembed( $content );
	$content = $wp_embed->run_shortcode( $content );
	$content = wpautop( $content );
	$content = do_shortcode( $content );

	return $content;
}

/**
 * Render sermon description.
 *
 * @param string $before Content before description.
 * @param string $after  Content after description.
 * @param bool   $return True to return, false to echo (default).
 *
 * @return string The HTML, if $return is set to true
 */
function wpfc_sermon_description( $before = '', $after = '', $return = false ) {
	$output = $before . wpautop( process_wysiwyg_output( 'sermon_description', get_the_ID() ) ) . $after;

	if ( ! $return ) {
		echo $output;
	}

	return $output;
}

/**
 * Renders the video player.
 *
 * @param string   $url  The URL of the video file.
 * @param int|bool $seek Allows seeking to specific second in audio file. Pass an int to override auto detection or
 *                       false to disable auto detection.
 *
 * @since 2.11.0
 * @since 2.12.3 added $seek
 *
 * @return string Video player HTML.
 */
function wpfc_render_video( $url = '', $seek = true ) {
	if ( ! is_string( $url ) || trim( $url ) === '' ) {
		return '';
	}

	if ( strpos( $url, 'facebook.' ) !== false ) {
		wp_enqueue_script( 'wpfc-sm-fb-player' );

		parse_str( parse_url( $url, PHP_URL_QUERY ), $query );

		return '<div class="fb-video" data-href="' . $url . '" data-width="' . ( isset( $query['width'] ) ? ( is_numeric( $query['width'] ) ? $query['width'] : '600' ) : '600' ) . '" data-allowfullscreen="' . ( isset( $query['fullscreen'] ) ? ( 'yes' === $query['width'] ? 'true' : 'false' ) : 'true' ) . '"></div>';
	}

	$player = strtolower( sm_get_option( 'player' ) ?: 'plyr' );

	if ( strtolower( 'WordPress' ) === $player ) {
		$attr = array(
			'src'     => $url,
			'preload' => 'none',
		);

		$output = wp_video_shortcode( $attr );
	} else {
		$is_youtube_long  = strpos( strtolower( $url ), 'youtube.com' );
		$is_youtube_short = strpos( strtolower( $url ), 'youtu.be' );
		$is_youtube       = $is_youtube_long || $is_youtube_short;
		$is_vimeo         = strpos( strtolower( $url ), 'vimeo.com' );
		$extra_settings   = '';
		$output           = '';

		if ( is_numeric( $seek ) || true === $seek ) {
			if ( is_numeric( $seek ) ) {
				$seconds = $seek;
			} else {
				$seconds = wpfc_get_media_url_seconds( $url );
			}

			// Sanitation just in case.
			$extra_settings = 'data-plyr_seek=\'' . intval( $seconds ) . '\'';
		}

		// Remove seek from URL.
		$url = preg_replace( '/(\?|#|&)t.*$/', '', $url );

		if ( 'plyr' === $player && ( $is_youtube || $is_vimeo ) ) {
			$output .= '<div data-plyr-provider="' . ( $is_youtube ? 'youtube' : 'vimeo' ) . '" data-plyr-embed-id="' . $url . '" class="plyr__video-embed wpfc-sermon-video-player video-' . ( $is_youtube ? 'youtube' : 'vimeo' ) . ( 'mediaelement' === $player ? 'mejs__player' : '' ) . '" ' . $extra_settings . '></div>';
		} else {
			$output .= '<video controls preload="metadata" class="wpfc-sermon-video-player ' . ( 'mediaelement' === $player ? 'mejs__player' : '' ) . '" ' . $extra_settings . '>';
			$output .= '<source src="' . $url . '">';
			$output .= '</video>';
		}
	}

	/**
	 * Allows changing of the video player to any HTML.
	 *
	 * @param string $output Video player HTML.
	 * @param string $url    Video source URL.
	 */
	return apply_filters( 'sm_video_player', $output, $url );
}

/**
 * Renders the audio player.
 *
 * @param string|int $source The URL or the attachment ID of the audio file.
 * @param int        $seek   Allows seeking to specific second in audio file.
 *
 * @since 2.12.3 added $seek
 *
 * @return string Audio player HTML.
 */
function wpfc_render_audio( $source = '', $seek = null ) {
	if ( is_int( $source ) || is_numeric( $source ) ) {
		$source = wp_get_attachment_url( intval( $source ) );

		if ( ! $source ) {
			return '';
		}
	} elseif ( is_string( $source ) ) {
		if ( '' === trim( $source ) ) {
			return '';
		}
	} else {
		return '';
	}

	$player = strtolower( sm_get_option( 'player' ) ?: 'plyr' );

	if ( strtolower( 'WordPress' ) === $player ) {
		$attr = array(
			'src'     => $source,
			'preload' => 'none',
		);

		$output = wp_audio_shortcode( $attr );
	} else {
		$extra_settings = '';

		if ( is_numeric( $seek ) ) {
			// Sanitation just in case.
			$extra_settings = 'data-plyr_seek=\'' . intval( $seek ) . '\'';
		}

		$output = '';

		$output .= '<audio controls preload="metadata" class="wpfc-sermon-player ' . ( 'mediaelement' === $player ? 'mejs__player' : '' ) . '" ' . $extra_settings . '>';
		$output .= '<source src="' . $source . '" type="audio/mp3">';
		$output .= '</audio>';
	}

	/**
	 * Allows changing of the audio player to any HTML.
	 *
	 * @param string $output Audio player HTML.
	 * @param string $source Audio source URL.
	 */
	return apply_filters( 'sm_audio_player', $output, $source );
}

/**
 * Render sermon attachments HTML.
 *
 * @return string
 */
function wpfc_sermon_attachments() {
	if ( ! get_sermon_meta( 'sermon_notes' ) && ! get_sermon_meta( 'sermon_bulletin' ) ) {
		return '';
	}

	$output = wpfc_get_partial( 'content-sermon-attachments' );

	/**
	 * Allows to filter the output of sermon attachments HTML.
	 *
	 * @param string $output The HTML.
	 *
	 * @since 2.11.3
	 */
	return apply_filters( 'sm_attachments_html', $output );
}

/**
 * Renders updates single sermon view.
 *
 * @param bool    $return True to return output, false to echo (default).
 * @param WP_Post $post   WP_Post instance of the sermon.
 *
 * @return string The HTML if $return is set to true.
 */
function wpfc_sermon_single_v2( $return = false, $post = null ) {
	if ( null === $post ) {
		global $post;
	} else {
		// Save global $post value for later restoration. Just in case.
		$new_post = $post;
		$old_post = $GLOBALS['post'];
		$post     = $new_post;
	}

	// Get the partial.
	$output = wpfc_get_partial( 'content-sermon-single' );

	/**
	 * Allows you to modify the sermon HTML on single sermon pages.
	 *
	 * @param string  $output The HTML that will be outputted.
	 * @param WP_Post $post   The sermon.
	 *
	 * @since 2.12.0
	 */
	$output = apply_filters( 'wpfc_sermon_single_v2', $output, $post );

	// Restore the global $post value. Just in case.
	$GLOBALS['post'] = ! empty( $GLOBALS['post'] ) ? ! empty( $old_post ) ? $old_post : $post : null;

	if ( ! $return ) {
		echo $output;
	}

	return $output;
}

/**
 * Renders updated archive sermon view.
 *
 * @param bool  $return True to return output, false to echo (default).
 * @param array $args   Passed from shortcode.
 *
 * @return string The HTML if $return is set to true.
 */
function wpfc_sermon_excerpt_v2( $return = false, $args = array() ) {
	global $post;
	global $wp_the_query;

	if ( empty( $args ) ) {
		$args = array(
			'image_size' => 'post-thumbnail',
		);
	}

	// Get the partial.
	$output = wpfc_get_partial( 'content-sermon-archive' );

	/**
	 * Allows you to modify the sermon HTML on archive pages.
	 *
	 * @param string  $output The HTML that will be outputted.
	 * @param WP_Post $post   The sermon.
	 * @param array   $args   Rendering arguments. Passed from shortcode.
	 *
	 * @since 2.12.0
	 */
	$output = apply_filters( 'wpfc_sermon_excerpt_v2', $output, $post, $args );

	if ( ! $return ) {
		echo $output;
	}

	return $output;
}

/**
 * Build <option> fields for <select> element.
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $default  Force a default value regardless the query var.
 *
 * @return string HTML <option> fields
 *
 * @since 2.5.0 added $default
 */
function wpfc_get_term_dropdown( $taxonomy, $default = '' ) {
	// Reset var.
	$html = '';

	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false, // todo: add option to disable/enable this globally.
	) );

	if ( 'wpfc_bible_book' === $taxonomy && sm_get_option( 'sort_bible_books', true ) ) {
		// Book order.
		$books = array(
			'Genesis',
			'Exodus',
			'Leviticus',
			'Numbers',
			'Deuteronomy',
			'Joshua',
			'Judges',
			'Ruth',
			'1 Samuel',
			'2 Samuel',
			'1 Kings',
			'2 Kings',
			'1 Chronicles',
			'2 Chronicles',
			'Ezra',
			'Nehemiah',
			'Esther',
			'Job',
			'Psalms',
			'Proverbs',
			'Ecclesiastes',
			'Song of Songs',
			'Isaiah',
			'Jeremiah',
			'Lamentations',
			'Ezekiel',
			'Daniel',
			'Hosea',
			'Joel',
			'Amos',
			'Obadiah',
			'Jonah',
			'Micah',
			'Nahum',
			'Habakkuk',
			'Zephaniah',
			'Haggai',
			'Zechariah',
			'Malachi',
			'Matthew',
			'Mark',
			'Luke',
			'John',
			'Acts',
			'Romans',
			'1 Corinthians',
			'2 Corinthians',
			'Galatians',
			'Ephesians',
			'Philippians',
			'Colossians',
			'1 Thessalonians',
			'2 Thessalonians',
			'1 Timothy',
			'2 Timothy',
			'Titus',
			'Philemon',
			'Hebrews',
			'James',
			'1 Peter',
			'2 Peter',
			'1 John',
			'2 John',
			'3 John',
			'Jude',
			'Revelation',
			'Topical',
		);

		$ordered_terms   = array();
		$unordered_terms = array();

		// Assign every book a number.
		foreach ( $terms as $term ) {
			if ( array_search( $term->name, $books ) !== false ) {
				$ordered_terms[ array_search( $term->name, $books ) ] = $term;
			} else {
				$unordered_terms[] = $term;
			}
		}

		// Order the numbers (books).
		ksort( $ordered_terms );

		$terms = array_merge( $ordered_terms, $unordered_terms );
	}

	foreach ( $terms as $term ) {
		$html .= '<option value="' . $term->slug . '" ' . ( ( '' === $default ? get_query_var( $taxonomy ) === $term->slug : $term->slug === $default ) ? 'selected' : '' ) . '>' . $term->name . '</option>';
	}

	return $html;
}

/**
 * Allows user to override the partial file for rendering by placing it in either:
 * - `/wp-contents/themes/<theme_name>/partials/<partial_name>.php`
 * - `/wp-contents/themes/<theme_name>/template-parts/<partial_name>.php`
 * - `/wp-contents/themes/<theme_name>/<partial_name>.php`
 *
 * @param string $name File name of the partial file to load. Can include `.php`, but not required.
 * @param array  $args Array of variable => content, to use in the partial.
 *
 * @return string The contents of the partial.
 *
 * @since 2.13.0
 */
function wpfc_get_partial( $name = '', $args = array() ) {
	if ( '' === $name ) {
		$content = '';
	} else {
		$partial                      = null;
		$GLOBALS['wpfc_partial_args'] = $args;

		if ( false === strpos( $name, '.php' ) ) {
			$name .= '.php';
		}

		foreach (
			array(
				'partials/',
				'template-parts/',
				'',
			) as $path
		) {
			$partial = locate_template( $path . $name );

			if ( $partial ) {
				break;
			}
		}

		ob_start();

		if ( $partial ) {
			load_template( $partial, false );
		} else {
			if ( file_exists( SM_PATH . 'views/partials/' . $name ) ) {
				load_template( SM_PATH . 'views/partials/' . $name, false );
			} else {
				echo '<p><b>Sermon Manager</b>: Failed loading partial "<i>' . str_replace( '.php', '', $name ) . '</i>", file does not exist.</p>';
			}
		}

		$content = ob_get_clean();
	}

	/**
	 * Allows to filter the partial content.
	 *
	 * @param string $content The partial content.
	 * @param string $name    The partial file name.
	 *
	 * @since 2.13.0
	 */
	return apply_filters( 'wpfc_get_partial', $content, $name );
}

/**
 * Append the terms of Sermon Manager taxonomies to the list
 * of sermon (post) classes generated by post_class().
 *
 * @param array $classes An array of existing post classes.
 * @param array $class   An array of additional classes added to the post (not needed).
 * @param int   $post_id The post ID.
 *
 * @return array Modified class list.
 */
function sm_add_additional_sermon_classes( $classes, $class, $post_id ) {
	if ( 'wpfc_sermon' !== get_post_type( $post_id ) ) {
		return $classes;
	}

	$additional_classes = array();

	$taxonomies = array(
		'wpfc_preacher',
		'wpfc_sermon_series',
		'wpfc_bible_book',
		'wpfc_sermon_topics',
	);

	foreach ( $taxonomies as $taxonomy ) {
		foreach ( (array) get_the_terms( $post_id, $taxonomy ) as $term ) {
			if ( empty( $term->slug ) ) {
				continue;
			}

			if ( ! in_array( $term->slug, $classes ) ) {
				$term_class = sanitize_html_class( $term->slug, $term->term_id );

				if ( is_numeric( $term_class ) || ! trim( $term_class, '-' ) ) {
					$term_class = $term->term_id;
				}

				$additional_classes[] = esc_attr( sanitize_html_class( $taxonomy . '-' . $term_class, $taxonomy . '-' . $term->term_id ) );
			}
		}
	}

	if ( is_archive() ) {
		$additional_classes[] = 'wpfc-sermon';
	} else {
		$additional_classes[] = 'wpfc-sermon-single';
	}

	/**
	 * Allows filtering of additional Sermon Manager classes
	 *
	 * @param array $classes The array of added classes
	 *
	 * @since 2.12.0
	 */
	$additional_classes = apply_filters( 'wpfc_sermon_classes', $additional_classes, $classes, $post_id );

	return array_merge( $additional_classes, $classes );
}

// Append custom classes to individual sermons.
add_filter( 'post_class', 'sm_add_additional_sermon_classes', 10, 3 );

/**
 * Saves whole Sermon HTML markup into post content for better search compatibility.
 *
 * @param int     $post_ID    Post ID.
 * @param WP_Post $post       Post object.
 * @param bool    $skip_check Disables check of "SM_SAVING_POST" constant.
 *
 * @since 2.8
 */
function sm_render_sermon_into_content( $post_ID = 0, $post = null, $skip_check = false ) {
	global $wpdb, $skip_content_check;

	if ( null === $post ) {
		$post = get_post( $post_ID );
	}

	if ( 'wpfc_sermon' !== $post->post_type ) {
		return;
	}

	if ( ! $skip_check ) {
		if ( defined( 'SM_SAVING_POST' ) ) {
			return;
		} else {
			define( 'SM_SAVING_POST', 1 );
		}
	}

	$content       = '';
	$bible_passage = get_post_meta( $post_ID, 'bible_passage', true );
	$has_preachers = has_term( '', 'wpfc_preacher', $post );
	$has_series    = has_term( '', 'wpfc_sermon_series', $post );

	if ( $bible_passage ) {
		$content .= __( 'Bible Text:', 'sermon-manager-for-wordpress' ) . ' ' . $bible_passage;
	}

	if ( $has_preachers ) {
		if ( $bible_passage ) {
			$content .= ' | ';
		}

		$content .= ( sm_get_option( 'preacher_label' ) ? sm_get_option( 'preacher_label', 'Preacher' ) . ':' : __( 'Preacher:', 'sermon-manager-for-wordpress' ) ) . ' ';
		$content .= strip_tags( get_the_term_list( $post->ID, 'wpfc_preacher', '', ', ', '' ) );
	}

	if ( $has_series ) {
		if ( $has_preachers ) {
			$content .= ' | ';
		}
		$content .= strip_tags( get_the_term_list( $post->ID, 'wpfc_sermon_series', __( 'Series:', 'sermon-manager-for-wordpress' ) . ' ', ', ', '' ) );
	}

	$description = strip_tags( trim( get_post_meta( $post->ID, 'sermon_description', true ) ) );

	if ( '' !== $description ) {
		$content .= PHP_EOL . PHP_EOL;
		$content .= $description;
	}

	/**
	 * Allows to modify sermon content that will be saved as "post_content".
	 *
	 * @param string  $content    Textual content (no HTML).
	 * @param int     $post_ID    ID of the sermon.
	 * @param WP_Post $post       Sermon post object.
	 * @param bool    $skip_check Basically, a way to identify if the function is being executed from the update function or not.
	 *
	 * @since 2.11.0
	 */
	$content = apply_filters( 'sm_sermon_post_content', $content, $post_ID, $post, $skip_check );
	$content = apply_filters( "sm_sermon_post_content_$post_ID", $content, $post_ID, $post, $skip_check );

	if ( ! $skip_content_check ) {
		if ( ! sm_get_option( 'post_content_enabled', 1 ) ) {
			$content = '';
		}
	}

	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET `post_content` = %s WHERE `ID` = %s", array(
		$content,
		$post_ID,
	) ) );
}

// Render sermon HTML for search compatibility.
add_action( 'wp_insert_post', 'sm_render_sermon_into_content', 10, 2 );
