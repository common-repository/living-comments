<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Fetches post data.
 */
function livcom_fetch_post_data() {
    // Retrieve plugin options
    $category_selected = get_option( 'livcom_plugin_category_selected', [] );
    $allocation = intval( get_option( 'livcom_allocation', 7 ) );
    $num_posts = intval( get_option( 'livcom_num_posts', 5 ) );
    $possible_tones = get_option( 'livcom_plugin_possible_tones' );
    $selected_tones = get_option( 'livcom_plugin_tones_selected', [] );

    // Randomly select a tone
    if ( empty( $selected_tones ) ) {
        $selected_tones = $possible_tones;
    }
    $random_tone = $selected_tones[array_rand( $selected_tones )];

    // Initialize variables
    $random_comment_content = '';
    $random_comment_id = 0;
    $random_post_title = '';

    // Retrieve a random post ID
    $random_post_id = livcom_get_random_post_from_categories( $category_selected, $num_posts, $allocation );

    if ( $random_post_id && is_int( $random_post_id ) && $random_post_id > 0 ) {
        // Determine if we should select from the latest comments or all comments
        $select_from_latest = wp_rand( 1, 10 ) <= $allocation;
        $args = [
            'post_id' => $random_post_id,
            'status'  => 'approve',
            'orderby' => 'date',
            'order'   => 'DESC',
            'number'  => $select_from_latest ? $num_posts : 99
        ];
		
		$allow_reply_users = get_option( 'livcom_allow_reply_users', 'on' );

		// Check if reply users are allowed and modify arguments
		if ( $allow_reply_users === 'off' ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'livcom_tone',
					'compare' => 'EXISTS',
				),
			);
		}
		
        // Get comments for the selected post
        $comments = get_comments( $args );
        if ( !empty( $comments ) ) {
            $random_comment = $comments[array_rand( $comments )];
            $random_comment_content = $random_comment->comment_content;
            $random_comment_id = $random_comment->comment_ID;
        }

        // Extract the post title and content
        $random_post_title = get_the_title( $random_post_id );
        $post_content = get_post_field( 'post_content', $random_post_id );

        // Retrieve snippets
        $random_snippet = livcom_fetch_random_post_snippet( $post_content );
        $random_short_snippet = livcom_fetch_random_short_snippet( $post_content );

        return array(
            'random_tone'            => $random_tone,
            'random_post_id'         => $random_post_id,
            'random_comment_content' => $random_comment_content,
            'random_comment_id'      => $random_comment_id,
            'random_post_title'      => $random_post_title,
            'random_post_snippet'    => $random_snippet,
            'random_short_snippet'   => $random_short_snippet
        );
    }
}

/**
 * Gets a random post from specified categories.
 *
 * @param array $category_selected Array of selected category IDs.
 * @param int $num_posts Number of posts to consider.
 * @param int $allocation Allocation percentage.
 * @return int|null Post ID or null if none found.
 */
function livcom_get_random_post_from_categories( $category_selected, $num_posts, $allocation ) {
    // Ensure $category_selected is an array and contains valid integers
    $category_selected = array_filter($category_selected, function($value) {
        return is_numeric($value);
    });

    // Decide if selecting from latest posts or all posts
    $select_from_latest = wp_rand( 1, 10 ) <= $allocation;

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => $select_from_latest ? $num_posts : -1,
        'orderby'        => $select_from_latest ? 'date' : 'rand',
        'order'          => 'DESC',
        'post_status'    => 'publish',
        'category__in'   => array_map('intval', $category_selected),
    );

    // Query posts
    $query = new WP_Query( $args );

    // Choose random post
    if ( $query->have_posts() ) {
        $posts = $query->posts;
        $random_post = $posts[array_rand($posts)];
        return $random_post->ID;
    }

    // Return null if no posts found
    return null;
}

/**
 * Fetches random post snippet.
 *
 * @param string $post_content Post content.
 * @return string|false Random snippet or false on failure.
 */
function livcom_fetch_random_post_snippet( $post_content ) {
    // Remove HTML tags and normalize text
    $post_content_plain_text = wp_strip_all_tags( $post_content );
    $post_content_normalized = str_replace( ["\r\n", "\r"], "\n", $post_content_plain_text );
    $post_content_normalized = preg_replace( "/\n\s*\n/", "\n", $post_content_normalized );

    // Split and filter content
    $paragraphs = array_filter( array_map( 'trim', explode( "\n", $post_content_normalized ) ) );
    $all_text = implode( ' ', $paragraphs );
    $sentences = preg_split( '/(?<=[.!?])\s+/', $all_text );

    // Calculate total words and starting position
    $total_words = 0;
    $max_start_position = 0;
    for ( $i = count( $sentences ) - 1; $i >= 0; $i-- ) {
        $total_words += str_word_count( $sentences[$i] );
        if ( $total_words >= 300 ) {
            $max_start_position = $i;
            break;
        }
    }

    // Snippet generation
    if ( $total_words < 300 ) {
        return $post_content_plain_text;
    } else {
        $random_snippet = livcom_generate_snippet( $sentences, $max_start_position, 300 );
    }

    return empty( trim( $random_snippet ) ) ? false : $random_snippet;
}

/**
 * Generates a snippet of specified word count.
 *
 * @param array $sentences Array of sentences.
 * @param int $max_start_position Maximum start position.
 * @param int $word_limit Word limit for the snippet.
 * @return string Generated snippet.
 */
function livcom_generate_snippet( $sentences, $max_start_position, $word_limit ) {
    $start_position = wp_rand( 0, $max_start_position / 2 );
    $snippet_words = array();

    for ( $i = $start_position; count( $snippet_words ) < $word_limit && $i < count( $sentences ); $i++ ) {
        $sentence_words = explode( ' ', $sentences[$i] );
        $snippet_words = array_merge( $snippet_words, $sentence_words );
    }

    return implode( ' ', array_slice( $snippet_words, 0, $word_limit ) );
}

/**
 * Fetches random short snippet from post content.
 *
 * @param string $post_content The post content.
 * @return string|bool Random snippet or false if empty.
 */
function livcom_fetch_random_short_snippet( $post_content ) {
    // Strip HTML tags
    $post_content_plain_text = wp_strip_all_tags( $post_content );

    // Normalize line breaks and spaces
    $post_content_normalized = str_replace( array( "\r\n", "\r" ), "\n", $post_content_plain_text );
    $post_content_normalized = preg_replace( "/\n\s*\n/", "\n", $post_content_normalized );

    // Split into paragraphs and filter
    $paragraphs = array_filter( array_map( 'trim', explode( "\n", $post_content_normalized ) ) );

    // Flatten paragraphs
    $all_text = implode( ' ', $paragraphs );

    // Split into sentences
    $sentences = preg_split( '/(?<=[.!?])\s+/', $all_text );

    // Determine max start position
    $max_start_position = count( $sentences ) - 1;
    $total_words = 0;
    for ( $i = count( $sentences ) - 1; $i >= 0; $i-- ) {
        $total_words += str_word_count( $sentences[$i] );
        if ( $total_words >= 50 ) {
            $max_start_position = $i;
            break;
        }
    }

    // Select starting sentence
    $start_position = wp_rand( 0, $max_start_position );

    // Collect words for snippet
    $snippet_words = array();
    for ( $i = $start_position; $i < count( $sentences ) && count( $snippet_words ) + str_word_count( $sentences[$i] ) <= 50; $i++ ) {
        $sentence_words = explode( ' ', $sentences[$i] );
        $snippet_words = array_merge( $snippet_words, $sentence_words );
    }

    // Create snippet
    $random_snippet = implode( ' ', $snippet_words );

    return empty( trim( $random_snippet ) ) ? false : $random_snippet;
}
