<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Processes text for custom words filtering.
 * 
 * @param string $text The text to be processed.
 * @return string|null Processed text or null if blocked.
 */
function livcom_process_text( $text ) {
    // Retrieve and sanitize custom words
    $custom_words = get_option( 'livcom_plugin_custom_words', [] );
    if ( ! is_array( $custom_words ) ) {
        $custom_words = array_filter( explode( ',', $custom_words ) );
    }

    // Eliminate duplicate words
    $custom_words = array_unique( $custom_words );

    // Iterate over custom words for filtering
    foreach ( $custom_words as $word ) {
        // Check for non-empty string and presence in text
        if ( $word !== '' && stripos( $text, $word ) !== false ) {
            // Increment blocked comments count
            $blocked_comments = get_option( 'livcom_blocked_comments', 0 );
            update_option( 'livcom_blocked_comments', ++$blocked_comments );

            // Return null to indicate blocked text
            return null;
        }
    }

    // Return the original text if not blocked
    return $text;
}

/**
 * Checks if author/commenter is unique
 *
 * @param string|null $author_name Author name.
 * @param int|null    $user_id     User ID.
 * @param int         $post_id     Post ID.
 * @return bool                    True if unique, false otherwise.
 */
function livcom_is_unique_author_comment( $author_name = null, $user_id = null, $post_id ) {
    // Retrieve comments for the post
    $comments = get_comments( array( 'post_id' => $post_id ) );

    // Return true if no comments
    if ( empty( $comments ) ) {
        return true;
    }

    $authors  = array();
    $user_ids = array();

    // Process comments
    foreach ( $comments as $comment ) {
        if ( is_object( $comment ) ) {
            $comment_author_type = get_comment_meta( $comment->comment_ID, 'livcom_comment_author_type', true );
            
            if ( $comment_author_type === 'getRandomGuestName' || $comment_author_type === 'getRandomUserId' ) {
                $authors[]  = $comment->comment_author;
                $user_ids[] = $comment->user_id;
            }
        } else {
            error_log( "Comment is not an object." );
        }
    }

    // Remove duplicates
    $authors  = array_unique( $authors );
    $user_ids = array_unique( $user_ids );
    
    // Check user ID uniqueness
    if ( $user_id ) {
        $is_unique = ! in_array( $user_id, $user_ids );
        return $is_unique;
    }

    // Check author name uniqueness
    if ( $author_name ) {
        $ai_prefix = get_option( 'livcom_ai_prefix' ) === 'on' ? 'AI_' : '';
        $author_name_with_prefix = $ai_prefix . $author_name;

        $is_unique = ! in_array( $author_name_with_prefix, $authors );
        return $is_unique;
    }
}

/**
 * Checks if author/commenter is unique in reply
 *
 * @param string|null $author_name Author name.
 * @param int|null    $user_id     User ID.
 * @param int         $random_comment_id Comment ID.
 * @return bool                    True if unique, false otherwise.
 */
function livcom_is_unique_author_reply( $author_name = null, $user_id = null, $random_comment_id ) {
    // Retrieve parent comment
    $parent_comment = get_comment( $random_comment_id );

    // Return true if no parent comment
    if ( ! is_object( $parent_comment ) ) {
        return true;
    }

    // Retrieve replies
    $replies = get_comments( array( 'parent' => $random_comment_id ) );

    $authors  = array( $parent_comment->comment_author );
    $user_ids = array( $parent_comment->user_id );

    // Process replies
    foreach ( $replies as $reply ) {
        if ( is_object( $reply ) ) {
            $comment_author_type = get_comment_meta( $reply->comment_ID, 'livcom_comment_author_type', true );

            if ( $comment_author_type === 'getRandomGuestName' || $comment_author_type === 'getRandomUserId' ) {
                $authors[]  = $reply->comment_author;
                $user_ids[] = $reply->user_id;
            }
        } else {
            error_log( "Reply is not an object." );
        }
    }

    // Remove duplicates
    $authors  = array_unique( $authors );
    $user_ids = array_unique( $user_ids );

    // Check user ID uniqueness
    if ( $user_id ) {
        $is_unique = ! in_array( $user_id, $user_ids );
        return $is_unique;
    }

    // Check author name uniqueness
    if ( $author_name ) {
        $ai_prefix = get_option( 'livcom_ai_prefix' ) === 'on' ? 'AI_' : '';
        $author_name_with_prefix = $ai_prefix . $author_name;

        $is_unique = ! in_array( $author_name_with_prefix, $authors );
        return $is_unique;
    }
}

/**
 * Request a random comment.
 *
 * @param string $lc_api_key API key.
 * @param string $lc_uid Unique ID.
 * @param string $random_post_title Title of the post.
 * @param string $random_post_snippet Snippet of the post.
 * @param string $random_tone Tone of the comment.
 * @return array|null Comment data or null on failure.
 */
function livcom_request_random_comment( $lc_api_key, $lc_uid, $random_post_title, $random_post_snippet, $random_tone, $lc_language ) {
    // Sanitization
    $lc_api_key = sanitize_text_field( $lc_api_key );
    $lc_uid = sanitize_text_field( $lc_uid );
    $domain = esc_url_raw( get_site_url() );
    $random_post_title = sanitize_text_field( $random_post_title );
    $random_post_snippet = sanitize_text_field( $random_post_snippet );
    $random_tone = sanitize_text_field( $random_tone );
	$lc_language = sanitize_text_field( $lc_language );

    // Fetch and process word length option
    $word_length_options = explode( ',', get_option( 'livcom_plugin_word_length', '1,2,3' ) );
    $word_length_options = array_map( 'intval', $word_length_options );
    $word_length = $word_length_options[ array_rand( $word_length_options ) ];

    // Fetch allow emoticons option and validate
    $allow_emoticons = get_option( 'livcom_allow_emoticons', 'off' );
    $allow_emoticons = ( 'on' === $allow_emoticons ) ? 'on' : 'off';

    // Fetch custom words if available
    $custom_words = get_option( 'livcom_plugin_custom_words', array() );

    // Prepare headers
    $headers = array(
        'LC-API-KEY'   => $lc_api_key,
        'LC-UID'       => $lc_uid,
        'LC-DOMAIN'    => $domain,
        'Content-Type' => 'application/json'
    );

    // Prepare body
    $body = array(
        'random_post_title'   => $random_post_title,
        'random_post_snippet' => $random_post_snippet,
        'random_tone'         => $random_tone,
        'word_length'         => $word_length,
        'allow_emoticons'     => 'on' === $allow_emoticons,
		'lc_language'       => $lc_language,
    );

    // Add custom words if not empty
    if ( ! empty( $custom_words ) && ! in_array( '', $custom_words, true ) ) {
        $body['custom_words'] = $custom_words;
    }

    // Prepare request arguments
    $args = array(
        'timeout' => 120,
        'headers' => $headers,
        'body'    => json_encode( $body ),
    );

    // Send the request
    $response = wp_remote_post( 'https://lotus.livingcomments.com/generateComment', $args );

    // Handle response
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        error_log( "Living Comments Plugin Error: " . $error_message );
        return null;
    }

    $body = wp_remote_retrieve_body( $response );
    $json = json_decode( $body, true );

    if ( $json && isset( $json['comment'] ) ) {
        $processed_comment = livcom_process_text( $json['comment'] );
        return array(
            'comment'      => $processed_comment,
            'tone'         => $random_tone,
            'word_length'  => $word_length
        );
    }

    return null;
}

/**
 * Request a random reply.
 *
 * @param string $lc_api_key API key.
 * @param string $lc_uid Unique ID.
 * @param string $random_comment_content Comment content.
 * @param string $random_short_snippet Short snippet.
 * @param string $random_post_title Post title.
 * @param string $random_tone Tone of the reply.
 * @return array|string|null Reply data, error message, or null on failure.
 */
function livcom_request_random_reply( $lc_api_key, $lc_uid, $random_comment_content, $random_short_snippet, $random_post_title, $random_tone, $lc_language ) {
    // Sanitization
    $lc_api_key = sanitize_text_field( $lc_api_key );
    $lc_uid = sanitize_text_field( $lc_uid );
    $domain = esc_url_raw( get_site_url() );
    $random_comment_content = sanitize_text_field( $random_comment_content );
    $random_short_snippet = sanitize_text_field( $random_short_snippet );
    $random_post_title = sanitize_text_field( $random_post_title );
    $random_tone = sanitize_text_field( $random_tone );

    // Fetch and process word length option
    $word_length_options = explode( ',', get_option( 'livcom_plugin_word_length', '1,2,3' ) );
    $word_length_options = array_map( 'intval', $word_length_options );
    $word_length = $word_length_options[ array_rand( $word_length_options ) ];

    // Fetch allow emoticons option and validate
    $allow_emoticons = get_option( 'livcom_allow_emoticons', 'off' );
    $allow_emoticons = ( 'on' === $allow_emoticons ) ? 'on' : 'off';

    // Fetch custom words if available
    $custom_words = get_option( 'livcom_plugin_custom_words', array() );

    // Prepare headers
    $headers = array(
        'LC-API-KEY'   => $lc_api_key,
        'LC-UID'       => $lc_uid,
        'LC-DOMAIN'    => $domain,
        'Content-Type' => 'application/json'
    );

    // Prepare body
    $body = array(
        'comment'             => $random_comment_content,
        'random_short_snippet'=> $random_short_snippet,
        'random_post_title'   => $random_post_title,
        'random_tone'         => $random_tone,
        'word_length'         => $word_length,
        'allow_emoticons'     => 'on' === $allow_emoticons,
		'lc_language'       => $lc_language,
    );

    // Add custom words if not empty
    if ( ! empty( $custom_words ) && ! in_array( '', $custom_words, true ) ) {
        $body['custom_words'] = $custom_words;
    }

    // Prepare request arguments
    $args = array(
        'timeout' => 120,
        'headers' => $headers,
        'body'    => json_encode( $body ),
    );

    // Send the request
    $response = wp_remote_post( 'https://lotus.livingcomments.com/generateReply', $args );

    // Handle response
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        return "Something went wrong: $error_message";
    }

    $body = wp_remote_retrieve_body( $response );
    $json = json_decode( $body, true );

    if ( $json && isset( $json['reply'] ) ) {
        $processed_reply = livcom_process_text( $json['reply'] );
        return array(
            'reply'       => $processed_reply,
            'tone'        => $random_tone,
            'word_length' => $word_length
        );
    }

    return null;
}

/**
 * Insert comment into a post.
 */
function livcom_insert_comment( $random_post_id, $user_info, $generated_comment, $random_tone, $word_length, $comment_author_type ) {
    // Validate post ID
    $random_post_id = absint( $random_post_id );
    if ( $random_post_id <= 0 ) {
        return null; // Invalid post ID
    }

    // Validate comment content
    if ( empty( $generated_comment ) ) {
        return null; // Empty comment content
    }

	if ( function_exists( 'sanitize_textarea_field' ) ) {
		// For newer WordPress
		$generated_comment = sanitize_textarea_field( $generated_comment );
	} else {
		// Fallback for older WordPress versions
		$generated_comment = wp_kses_post( $generated_comment );
	}

    // Initialize comment data
    $comment_data = array(
        'comment_post_ID' => $random_post_id,
        'comment_content' => $generated_comment,
        'comment_approved' => 1,
    );

    // Handle user information
    if ( isset( $user_info['ID'] ) ) {
        $user = get_userdata( $user_info['ID'] );
        if ( ! $user ) {
            return null; // Invalid user ID
        }
        $comment_data['user_id']             = $user_info['ID'];
        $comment_data['comment_author']      = sanitize_text_field( $user->display_name );
        $comment_data['comment_author_email'] = sanitize_email( $user->user_email );
        $comment_data['comment_author_url']  = esc_url_raw( $user->user_url );
    } else {
        // Validate username and email
        if ( ! isset( $user_info['username'] ) || ! isset( $user_info['email'] ) ) {
            return null;
        }
        $username = get_option( 'livcom_ai_prefix' ) == 'on' ? 'AI_' . sanitize_text_field( $user_info['username'] ) : sanitize_text_field( $user_info['username'] );
        $comment_data['comment_author']       = $username;
        $comment_data['comment_author_email'] = sanitize_email( $user_info['email'] );
    }

    // Check for blank author
    if ( empty( $comment_data['comment_author'] ) ) {
        return null;
    }

    // Insert comment
    $comment_id = wp_insert_comment( $comment_data );
    if ( $comment_id ) {
        add_comment_meta( $comment_id, 'livcom_tone', sanitize_text_field( $random_tone ), true );
        add_comment_meta( $comment_id, 'livcom_comment_author_type', sanitize_text_field( $comment_author_type ), true );
        add_comment_meta( $comment_id, 'livcom_word_length', absint( $word_length ), true );

        // Update frequency and cron job
        livcom_store_last_posted_id( $comment_id );
        $new_frequency = livcom_generate_random_frequency();
        update_option( 'livcom_plugin_frequency', $new_frequency );
        livcom_update_cron_job( get_option( 'livcom_plugin_frequency' ), $new_frequency );
        return $comment_id;
    }

    return null; // Comment insertion failed
}

/**
 * Inserts a reply to a comment.
 */
function livcom_insert_reply( $random_post_id, $random_comment_id, $user_info, $reply_content, $lc_tone, $word_length, $comment_author_type ) {
    // Validate post and comment IDs
    $random_post_id    = absint( $random_post_id );
    $random_comment_id = absint( $random_comment_id );
    
    if ( $random_post_id <= 0 || $random_comment_id <= 0 ) {
        return null; // Invalid post or comment ID
    }
    
    // Validate reply content
    if ( empty( $reply_content ) ) {
        return null; // Empty reply content
    }

	if ( function_exists( 'sanitize_textarea_field' ) ) {
		// For newer WordPress
		$reply_content = sanitize_textarea_field( $reply_content );
	} else {
		// Fallback for older WordPress versions
		$reply_content = wp_kses_post( $reply_content );
	}
    
    // Initialize reply data
    $reply_data = array(
        'comment_post_ID'  => $random_post_id,
        'comment_content'  => $reply_content,
        'comment_parent'   => $random_comment_id,
        'comment_approved' => 1,
    );

    // Handle user information
    if ( isset( $user_info['ID'] ) ) {
        $user = get_userdata( $user_info['ID'] );
        if ( ! $user ) {
            return null; // Invalid user ID
        }
        $reply_data['user_id']             = $user_info['ID'];
        $reply_data['comment_author']      = sanitize_text_field( $user->display_name );
        $reply_data['comment_author_email'] = sanitize_email( $user->user_email );
        $reply_data['comment_author_url']  = esc_url_raw( $user->user_url );
    } else {
        // Validate username and email
        if ( ! isset( $user_info['username'] ) || ! isset( $user_info['email'] ) ) {
            return null;
        }
        $username = get_option( 'livcom_ai_prefix' ) == 'on' ? 'AI_' . sanitize_text_field( $user_info['username'] ) : sanitize_text_field( $user_info['username'] );
        $reply_data['comment_author']       = $username;
        $reply_data['comment_author_email'] = sanitize_email( $user_info['email'] );
    }

    // Ensure non-empty author before insertion
    if ( empty( $reply_data['comment_author'] ) ) {
        return null;
    }

    // Insert reply
    $reply_id = wp_insert_comment( $reply_data );
    if ( $reply_id ) {
        add_comment_meta( $reply_id, 'livcom_tone', sanitize_text_field( $lc_tone ), true );
        add_comment_meta( $reply_id, 'livcom_comment_author_type', sanitize_text_field( $comment_author_type ), true );
        add_comment_meta( $reply_id, 'livcom_word_length', absint( $word_length ), true );

        // Update frequency and cron job
        livcom_store_last_posted_id( $reply_id );
        $new_frequency = livcom_generate_random_frequency();
        update_option( 'livcom_plugin_frequency', $new_frequency );
        livcom_update_cron_job( get_option( 'livcom_plugin_frequency' ), $new_frequency );
        return $reply_id;
    }

    return null; // Reply insertion failed
}

/**
 * Store last posted ID.
 *
 * @param int $id Post ID.
 */
function livcom_store_last_posted_id( $id ) {
    $id = absint( $id );

    // Return if $id isn't valid.
    if ( ! $id ) {
        return;
    }

    // Retrieve existing IDs.
    $last_posted_ids = get_option( 'livcom_last_posted', [] );

    // Ensure $last_posted_ids is an array.
    if ( ! is_array( $last_posted_ids ) ) {
        $last_posted_ids = [];
    }

    // Prepend new ID.
    array_unshift( $last_posted_ids, ['id' => $id, 'reported' => false] );

    // Limit to last 20 IDs.
    $last_posted_ids = array_slice( $last_posted_ids, 0, 20 );

    // Update option with new array.
    update_option( 'livcom_last_posted', $last_posted_ids );
}