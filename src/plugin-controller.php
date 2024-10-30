<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Adds plugin menu.
 */
function livcom_plugin_menu() {
    // Adding the main menu page
    $lc_main_menu_hook_suffix = add_menu_page(
        'Living Comments Settings', // Page title
        'Living Comments',          // Menu title
        'manage_options',           // Capability required to see this menu
        'living-comments',          // Menu slug
        'livcom_plugin_settings_page',  // Function to display the page content
        'dashicons-format-chat',    // Icon for the menu
        81                          // Position of the menu in the dashboard
    );

    // Array defining the submenus
    $tabs = [
        'living-comments'             => 'Account Overview',
        'living-comments-settings'    => 'Comment/Reply',
        'living-comments-user'        => 'User Management',
        'living-comments-history'     => 'Comment History',
        'living-comments-billing'     => 'Billing',
        'living-comments-faq'         => 'FAQ'
    ];

    // Looping through each submenu and adding it
    foreach ( $tabs as $slug => $title ) {
        $hook_suffix = add_submenu_page(
            'living-comments',        // Parent menu slug
            $title,                   // Page title
            $title,                   // Menu title
            'manage_options',         // Capability required to see this submenu
            $slug,                    // Menu slug
            'livcom_plugin_settings_page' // Function to display the page content
        );
    }
}

/**
 * Sets the active submenu item for the Living Comments plugin.
 */
function livcom_set_active_submenu() {
    global $plugin_page, $submenu_file;

    $base_slug = 'living-comments';
    $tabs = ['overview', 'settings', 'user', 'history', 'billing', 'faq'];

    if ( isset( $_GET['page'] ) ) {
        $page = sanitize_key( $_GET['page'] );
        if ( in_array( $page, $tabs ) ) {
            $submenu_file = $base_slug . '-' . $page;
        }
    }
}

/**
 * Custom admin notice.
 */
function livcom_custom_admin_notice() {
    ?>
    <style>
        /* Custom styles for living-comments-plugin notices */
        .living-comments-plugin .notice.notice-warning {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
        }
        .living-comments-plugin > h1, 
        .living-comments-plugin > .is-flex {
            padding-top: 15px;
        }
    </style>
    <?php
}

/**
 * Hides admin notices
 */
function livcom_hide_notices() {
    echo '<style>
        .notice, .update-nag, .updated { display: none !important; }
        </style>';
}

/**
 * Generate notification message
 */
function livcom_generate_notification_message() {
    // Retrieve plugin options
    $category_selected = get_option( 'livcom_plugin_category_selected', [] );
    $num_posts = intval( get_option( 'livcom_num_posts', 5 ) );
    $allocation = intval( get_option( 'livcom_allocation', 7 ) );

    // Get random post ID
    $random_post_id = livcom_get_random_post_from_categories( $category_selected, $num_posts, $allocation );
    $post_status = $random_post_id ? get_post_status( $random_post_id ) : null;

    // Initialize notification message
    $lc_notif_message = '';

    // Check for valid post and status
    if ( !$random_post_id || !is_int( $random_post_id ) || $random_post_id <= 0 || $post_status !== 'publish' ) {
        $lc_notif_message = "Please select a category with at least one published blog post to begin generating comments/replies.";
    }

    return $lc_notif_message;
}

/**
 * Admin notices for plugin.
 */
function livcom_plugin_admin_notices() {
    // Check if settings were updated
    if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
        livcom_display_notification_message( 'Your settings have been successfully saved.', 'check-square' );
        return; // Exit early if settings were updated
    }

    // Check if a comment was deleted
    if ( isset( $_GET['comment-deleted'] ) && 'true' === $_GET['comment-deleted'] ) {
        livcom_display_notification_message( 'The comment has been successfully deleted.', 'trash-alt' );
    }
	
    // Retrieve user's plan
    $lc_user_plan = get_option( 'livcom_user_plan' );

	// Determine if a notification should be displayed
	$show_notification = false;

	if ( 'Basic 15' === $lc_user_plan ) {
		if ( 1 === wp_rand( 1, 3 ) ) {
			$show_notification = true;
		}
	} else {
		if ( 1 === wp_rand( 1, 20 ) ) {
			$show_notification = true;
		}
	}

	// Display a notification if decided
	if ( $show_notification ) {
		// Randomly choose which notification to display
		if ( wp_rand( 1, 2 ) === 1 ) {
			// Display the review notification
			echo '<div class="notification is-warning has-background-warning-light has-text-warning-dark">
					<i class="las la-bullhorn la-lg"></i> We\'re a new plugin! We\'d greatly appreciate your <a href="https://wordpress.org/support/plugin/living-comments/reviews/" target="_blank" rel="noopener noreferrer" class="has-text-weight-bold">review on WordPress</a> or a mention on your website.
				  </div>';
		} else {
			// Display the feature website notification
			echo '<div class="notification is-warning has-background-warning-light has-text-warning-dark">
					<i class="las la-bullhorn la-lg"></i> We want to hear from you! If you want us to feature your website on our official <a href="https://www.livingcomments.com" target="_blank" rel="noopener noreferrer" class="has-text-weight-bold">website</a>, e-mail us at hello@livingcomments.com (limited spots).
				  </div>';
		}
	}

	// Generate and display category notification message
	$lc_notif_message = livcom_generate_notification_message();
	if ( ! empty( $lc_notif_message ) ) {
		echo '<div class="notification is-danger has-background-danger-light has-text-danger-dark">
				<i class="las la-exclamation-triangle la-lg"></i> ' . esc_html( $lc_notif_message ) . '
			  </div>';
	}
}

/**
 * Displays notification message.
 *
 * @param string $message Notification message.
 * @param string $icon Icon class.
 */
function livcom_display_notification_message( $message, $icon ) {
    echo '<div class="notification is-primary has-background-primary-light has-text-primary-dark">
            <button class="delete mt-2 p-1" type="button"></button>
            <i class="las la-' . esc_attr( $icon ) . '  la-lg"></i> ' . esc_html( $message ) . '
          </div>';

    // Remove query args from URL
    $url = remove_query_arg( ['settings-updated', 'comment-deleted'] );
    echo "<script>window.history.replaceState({}, '', '" . esc_js( $url ) . "');</script>";
}

/**
 * Generate random frequency
 */
function livcom_generate_random_frequency() {
    // Sanitize options
    $min = intval( get_option( 'livcom_plugin_frequency_min', 4 ) );
    $max = intval( get_option( 'livcom_plugin_frequency_max', 8 ) );

    // Set default values if negative
    if ( $min <= 0 ) {
        $min = 4;
    }
    if ( $max <= 0 ) {
        $max = 8;
    }

    // Swap if min is greater than max
    if ( $min > $max ) {
        list( $min, $max ) = array( $max, $min );
    }

    // Return random frequency
    return wp_rand( $min, $max );
}

/**
 * Register plugin settings
 */
function livcom_register_plugin_settings() {
    // Register settings for plugin options group
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_category_selected' );
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_frequency_min' );
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_frequency_max' );
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_word_length' );
    register_setting( 'livcom_plugin_options_group', 'livcom_allow_emoticons' );
	register_setting( 'livcom_plugin_options_group', 'livcom_allow_reply_users' );
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_comment_reply_ratio' );
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_tones_selected' );
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_custom_words', 'livcom_sanitize_custom_words' );
    register_setting( 'livcom_plugin_options_group', 'livcom_allocation' );
    register_setting( 'livcom_plugin_options_group', 'livcom_num_posts' );
    register_setting( 'livcom_plugin_options_group', 'livcom_latest_num_rep' );
    register_setting( 'livcom_plugin_options_group', 'livcom_latest_num_com' );
    register_setting( 'livcom_plugin_options_group', 'livcom_ai_prefix' );
    register_setting( 'livcom_plugin_options_group', 'livcom_email_domain_option' );
    register_setting( 'livcom_plugin_options_group', 'livcom_custom_domain' );
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_guest_names', 'livcom_sanitize_guest_names' );
    register_setting( 'livcom_plugin_options_group', 'livcom_plugin_dummy_users', 'livcom_sanitize_dummy_users' );
    register_setting( 'livcom_plugin_options_group', 'livcom_user_priority' );
    register_setting( 'livcom_plugin_options_group', 'livcom_language' );
}

/**
 * Sanitizes custom words input.
 *
 * @param mixed $input The input to sanitize.
 * @return array Sanitized words.
 */
function livcom_sanitize_custom_words( $input ) {
    if ( is_array( $input ) ) {
        // Handle array input
        return array_map( 'sanitize_text_field', $input );
    } else {
        // Sanitize string input
        $words = explode( ',', $input );
        return array_map( 'sanitize_text_field', $words );
    }
}

/**
 * Generate API key.
 *
 * Tries to use random_bytes() if available (PHP 7.0 and up),
 * falls back to openssl_random_pseudo_bytes() if random_bytes() is not available (PHP 5.3.0 and up),
 * and ultimately falls back to mt_rand() for older PHP versions.
 *
 * @return string API key in hexadecimal format.
 */
function livcom_generate_api_key() {
    $length = 32; // The desired number of bytes before hex encoding.

    // Use random_bytes if available (PHP 7.0+)
    if ( function_exists( 'random_bytes' ) ) {
        try {
            return bin2hex( random_bytes( $length ) );
        } catch ( Exception $e ) {
            // Fall through to next method in case of an error.
        }
    }

    // Fallback to openssl_random_pseudo_bytes if random_bytes is not available
    if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
        $bytes = openssl_random_pseudo_bytes( $length, $strong );
        // Ensure the bytes are strong and secure
        if ( $strong === true ) {
            return bin2hex( $bytes );
        }
    }

	// Ultimate fallback to wp_rand
	$bytes = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$bytes .= chr( wp_rand( 0, 255 ) );
	}
	return bin2hex( $bytes );
}

/**
 * Store API key in user meta.
 * 
 * @param int $user_id User ID.
 * @param string $api_key API Key.
 */
function livcom_store_api_key( $user_id, $api_key ) {
    $user_id = absint( $user_id ); 
    $sanitized_api_key = sanitize_text_field( $api_key );
    update_user_meta( $user_id, 'livcom_api_key', $sanitized_api_key );
}

/**
 * Delete a comment.
 */
function livcom_delete_comment() {
	// Verify the nonce first
    if ( ! isset( $_POST['livcom_settings_nonce'] ) || ! check_admin_referer( 'livcom_settings_update', 'livcom_settings_nonce' ) ) {
        wp_die( 'Security check failed' ); // Nonce verification failed, halt execution.
    }
	
    // Check if comment_id is set and is a valid number
    if ( isset( $_POST['comment_id'] ) && is_numeric( $_POST['comment_id'] ) ) {
        $comment_id = intval( $_POST['comment_id'] );

        // Validate that it's a positive integer
        if ( $comment_id > 0 ) {
            // Attempt to delete the comment
            if ( wp_delete_comment( $comment_id, true ) ) {
                wp_send_json_success();
            } else {
                wp_send_json_error( 'Error deleting comment.' );
            }
        } else {
            wp_send_json_error( 'Invalid comment ID.' );
        }
    } else {
        wp_send_json_error( 'Comment ID not provided.' );
    }
}

/**
 * Send unhappy comment
 */
function livcom_send_unhappy_comment() {
	// Verify the nonce first
    if ( ! isset( $_POST['livcom_settings_nonce'] ) || ! check_admin_referer( 'livcom_settings_update', 'livcom_settings_nonce' ) ) {
        wp_die( 'Security check failed' ); // Nonce verification failed, halt execution.
    }
	
    if ( isset( $_POST['comment_id'] ) ) {
        // Sanitize the comment ID
        $comment_id = intval( $_POST['comment_id'] );

        // Log the function call
        error_log( "Executing: sendUnhappyComment" );

        // Retrieve and validate the comment
        $comment = get_comment( $comment_id );
		if ( ! $comment ) {
            wp_send_json_error( "Comment not found" );
            return;
        }

        // Sanitize the comment content
        $comment_content = sanitize_text_field( $comment->comment_content );

        // Get and sanitize the post title
        $post_id = $comment->comment_post_ID;
        $post = get_post( $post_id );
        $post_title = $post ? sanitize_text_field( $post->post_title ) : 'Unknown Post';

        // Retrieve, sanitize and validate API key and user data
        $user_id = get_option( 'livcom_user_id', 0 );
        $lc_api_key = sanitize_text_field( get_user_meta( $user_id, 'livcom_api_key', true ) );
        $lc_uid = sanitize_text_field( get_user_meta( $user_id, 'livcom_uid', true ) );
        $domain = esc_url_raw( get_site_url() );

        // Setup headers
        $headers = array(
            'LC-API-KEY' => $lc_api_key,
            'LC-UID' => $lc_uid,
            'LC-DOMAIN' => $domain,
            'Content-Type' => 'application/json'
        );

        // Setup body
        $body = array(
            'comment_content' => $comment_content,
            'post_title' => $post_title
        );

        // Request arguments
        $args = array(
            'timeout' => 120,
            'headers' => $headers,
            'body' => json_encode( $body ),
        );

        // Send API request
        $response = wp_remote_post( 'https://lotus.livingcomments.com/unhappyComment', $args );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            wp_send_json_error( "Something went wrong: $error_message" );
            return;
        } else {
            $body = wp_remote_retrieve_body( $response );
            error_log( print_r( $body, true ) );
            $json = json_decode( $body, true );

            if ( $json && isset( $json['success'] ) && $json['success'] ) {
                // Update reported status
                livcom_update_reported_status( $comment_id );

                wp_send_json_success( 'Comment sent successfully.' );
                return;
            } else {
                wp_send_json_error( 'Unexpected response from server.' );
                return;
            }
        }
    }
}

/**
 * Update reported status
 */
function livcom_update_reported_status( $comment_id ) {
    $lc_last_posted = get_option( 'livcom_last_posted', array() );

    foreach ( $lc_last_posted as $key => $value ) {
        if ( is_array( $value ) && isset( $value['id'] ) && $value['id'] == $comment_id ) {
            $lc_last_posted[$key]['reported'] = true;
            break;
        }
    }

    update_option( 'livcom_last_posted', $lc_last_posted );
}