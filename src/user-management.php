<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Adds custom user profile fields
 * 
 * @param WP_User|false $user The user object.
 */
function livcom_add_custom_user_profile_fields( $user ) {
    $checked = false;

    // Get current value of the custom field if editing a user
    if ( $user ) {
        $checked = get_user_meta( $user->ID, 'livcom_approved_dummy_user', true );
    }

    ?>
    <h3><?php esc_html_e( 'Living Comments Dummy User Approval', 'living-comments' ); ?></h3>
    <table class="form-table">
        <tr>
            <th>
                <label for="livcom_approved_dummy_user"><?php esc_html_e( 'Approve for Dummy Users', 'living-comments' ); ?></label>
            </th>
            <td>
                <input type="checkbox" name="livcom_approved_dummy_user" id="livcom_approved_dummy_user" value="1" <?php checked( $checked, 1 ); ?> />
                <span class="description"><?php esc_html_e( 'Check to approve this user for use with Living Comments.', 'living-comments' ); ?></span>
            </td>
        </tr>
        <!-- Nonce field for security -->
        <tr>
            <td colspan="2">
                <?php wp_nonce_field( 'livcom_edit_user_action', 'livcom_edit_user_nonce' ); ?>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Save custom user profile fields.
 * 
 * @param int $user_id User ID.
 * @return bool|void
 */
function livcom_save_custom_user_profile_fields( $user_id ) {	
    // Verify the nonce first
    if ( ! isset( $_POST['livcom_edit_user_nonce'] ) || ! wp_verify_nonce( $_POST['livcom_edit_user_nonce'], 'livcom_edit_user_action' ) ) {
        wp_die( 'Security check failed' ); // Nonce verification failed, halt execution.
    }
	
    // Check permission
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    // Sanitize the input from $_POST
    $approved_dummy_user = isset( $_POST['livcom_approved_dummy_user'] ) ? sanitize_text_field( $_POST['livcom_approved_dummy_user'] ) : '';

    // Update user meta and dummy users option
    if ( '1' !== $approved_dummy_user ) {
        update_user_meta( $user_id, 'livcom_approved_dummy_user', 0 );

        $user = get_userdata( $user_id );
        $dummy_users_option = get_option( 'livcom_plugin_dummy_users', [] );

        if ( ! is_array( $dummy_users_option ) ) {
            $dummy_users_option = [];
        }

        if ( $user && ( $key = array_search( strtolower( $user->user_login ), array_map( 'strtolower', $dummy_users_option ) ) ) !== false ) {
            unset( $dummy_users_option[$key] );
            $dummy_users_option = array_values( $dummy_users_option );
            update_option( 'livcom_plugin_dummy_users', $dummy_users_option );
        }
    } else {
        update_user_meta( $user_id, 'livcom_approved_dummy_user', 1 );
    }
}

/**
 * Checks for existing usernames in a POST request.
 */
function livcom_plugin_check_username() {
	// Verify the nonce first
    if ( ! isset( $_POST['livcom_settings_nonce'] ) || ! check_admin_referer( 'livcom_settings_update', 'livcom_settings_nonce' ) ) {
        wp_die( 'Security check failed' ); // Nonce verification failed, halt execution.
    }
	
    // Check if the username is set in the request
    if ( isset( $_POST['username'] ) ) {
        // Sanitize the entire username string first using sanitize_text_field
        $sanitized_usernames_string = sanitize_text_field( $_POST['username'] );

        // Split sanitized usernames by comma, then trim each one
        $usernames = array_map('trim', explode(',', $sanitized_usernames_string));

        // Further sanitize each username using sanitize_user
        $usernames = array_map('sanitize_user', $usernames);

        $existingUsernames = [];

        // Check each username for existence and specific meta value
        foreach ( $usernames as $username ) {
            if ( username_exists( $username ) ) {
                $user = get_user_by( 'login', $username );
                if ( $user ) {
                    $meta_value = get_user_meta( $user->ID, 'livcom_approved_dummy_user', true );
                    if ( '1' === $meta_value ) {
                        $existingUsernames[] = $username;
                    }
                }
            }
        }

        // Send JSON response based on username existence
        if ( ! empty( $existingUsernames ) ) {
            wp_send_json_success( 'The following usernames exist: ' . implode( ', ', $existingUsernames ) );
        } else {
            wp_send_json_error( 'None of the usernames exist.' );
        }
    } else {
        // Username not provided in the request
        wp_send_json_error( 'Username not provided.' );
    }

    wp_die();
}

/**
 * Request a random user.
 *
 * @param string $lc_api_key API key.
 * @param string $lc_uid User ID.
 * @return array|null User data or null on failure.
 */
function livcom_request_random_user( $lc_api_key, $lc_uid ) {
    $lc_api_key = sanitize_text_field( $lc_api_key );
    $lc_uid = sanitize_text_field( $lc_uid );

    $domain = esc_url_raw( get_site_url() );
    if ( ! filter_var( $domain, FILTER_VALIDATE_URL ) ) {
        return null;
    }

    $selected_website_category = sanitize_text_field( get_option( 'livcom_selected_website_category' ) );

    $possible_tones = get_option( 'livcom_plugin_possible_tones' );
    $selected_tones = get_option( 'livcom_plugin_tones_selected', [] );
    $selected_tones = empty( $selected_tones ) ? $possible_tones : $selected_tones;
    $random_tone = sanitize_text_field( $selected_tones[array_rand( $selected_tones )] );

    $headers = array(
        'LC-API-KEY' => $lc_api_key,
        'LC-UID' => $lc_uid,
        'LC-DOMAIN' => $domain,
        'Content-Type' => 'application/json'
    );

    $args = array(
        'timeout' => 120,
        'headers' => $headers,
        'body' => json_encode( array(
            'random_tone' => $random_tone,
            'domain_url' => $domain,
            'website_category' => $selected_website_category
        ) ),
    );

    $response = wp_remote_post( 'https://lotus.livingcomments.com/generateRandomUser', $args );
    if ( is_wp_error( $response ) ) {
        echo "Something went wrong. Please try again later.";
        return null;
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return null;
    }

    $decoded = json_decode( $body, true );
    if ( ! isset( $decoded['username'] ) ) {
        return null;
    }

    $username = sanitize_user( $decoded['username'], true );

    $domain_option = get_option( 'livcom_email_domain_option' );
    $email_domain = 'custom' === $domain_option ? sanitize_text_field( get_option( 'livcom_custom_domain' ) ) : parse_url( $domain, PHP_URL_HOST );
    $stripped_email_domain = preg_replace( '/^www\./', '', $email_domain );

    $email = $username . '@' . $stripped_email_domain;
    return array( 'username' => $username, 'email' => $email );
}

/**
 * Generate a random guest name and email.
 * 
 * @return array|null
 */
function livcom_get_random_guest_name() {
    $guest_names = get_option( 'livcom_plugin_guest_names', array() );
    if ( ! empty( $guest_names ) ) {
        $guest_names = array_map( 'sanitize_text_field', $guest_names );

        $username = $guest_names[array_rand( $guest_names )];
        if ( $username == '' ) {
            return null;
        }

        $email_domain_option = sanitize_text_field( get_option( 'livcom_email_domain_option' ) );
        if ( $email_domain_option == 'custom' ) {
            $domain = sanitize_text_field( get_option( 'livcom_custom_domain' ) );
        } else {
            $domain = parse_url( site_url(), PHP_URL_HOST );
        }
        $stripped_domain = preg_replace( '/^www\./', '', $domain );

        $email = $username . '@' . $stripped_domain;
        return array( 'username' => $username, 'email' => $email );
    }

    return null;
}

/**
 * Retrieves a random user ID from dummy users.
 *
 * @return WP_User|false User object or false if not found.
 */
function livcom_get_random_user_id() {
    // Retrieve dummy users
    $dummy_users = get_option( 'livcom_plugin_dummy_users', array() );

    if ( is_array( $dummy_users ) && ! empty( $dummy_users ) ) {
        $random_username = sanitize_text_field( $dummy_users[ array_rand( $dummy_users ) ] );

        // Get user by username
        $user = get_user_by( 'login', $random_username );

        // Check if user exists
        if ( $user && is_a( $user, 'WP_User' ) ) {
            return $user; // Return user object
        }
    }

    return false; // Return false if no user found
}

/**
 * Sanitize guest names.
 *
 * @param mixed $input The input to be sanitized.
 * @return array Sanitized output.
 */
function livcom_sanitize_guest_names( $input ) {
    // Check if input is an array
    if ( is_array( $input ) ) {
        $output = array_map( 'sanitize_text_field', $input );
    } else {
        // Handle string input
        $names = explode( ',', $input );
        $output = array_map( 'sanitize_text_field', $names );
    }

    return $output;
}

/**
 * Sanitizes dummy user input.
 *
 * @param mixed $input The input to sanitize.
 * @return array Sanitized output.
 */
function livcom_sanitize_dummy_users( $input ) {
    if ( is_array( $input ) ) {
        $output = array_map( 'sanitize_text_field', $input );
    } else {
        $names = explode( ',', $input );
        $output = array_map( 'sanitize_text_field', $names );
    }

    return $output;
}