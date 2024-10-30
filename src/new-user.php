<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Send user data to the API server.
 */
function livcom_send_user_data() {
    // Verify the nonce
    if (!isset($_POST['livcom_settings_nonce']) || !wp_verify_nonce($_POST['livcom_settings_nonce'], 'livcom_settings_update')) {
        wp_send_json_error(array('message' => 'Security check failed')); // Nonce verification failed, halt execution.
    }
	
    $selected_admin_id = isset( $_POST['administrator'] ) ? intval( $_POST['administrator'] ) : 0;

    // Update lc_user_id with selected admin ID
    if ( ! empty( $selected_admin_id ) ) {
        update_option( 'livcom_user_id', $selected_admin_id );
    }

    // Generate new API key if not exists
    $api_key = get_user_meta( $selected_admin_id, 'livcom_api_key', true );
    if ( ! $api_key ) {
        $api_key = livcom_generate_api_key();
        livcom_store_api_key( $selected_admin_id, $api_key );
    }

    // Prepare data for API request
    $data = array(
        'api_key' => sanitize_text_field( get_user_meta( $selected_admin_id, 'livcom_api_key', true ) ),
        'domain' => esc_url_raw( get_site_url() ),
        'email' => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
        'subscribe' => isset( $_POST['newsletter'] ) && $_POST['newsletter'] === '1' ? 1 : 0,
        'country' => isset( $_POST['country'] ) ? sanitize_text_field( $_POST['country'] ) : '',
        'website_category' => isset( $_POST['website_category'] ) ? sanitize_text_field( $_POST['website_category'] ) : '',
        'has_cron' => wp_next_scheduled( 'livcom_cron_check_event' ) !== false,
        'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'])
    );

    // Update selected country
    update_option( 'livcom_plugin_country', $data['country'] );

    // Send data to API server
    $response = wp_remote_post( 'https://lotus.livingcomments.com/newUser', array(
        'method' => 'POST',
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body' => json_encode( $data ),
        'timeout' => 120,
    ) );

    // Default response
    $responseArray = array(
        'success' => false,
        'message' => 'There was an error while processing your request.'
    );

    // Process API response
    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
        $api_response = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $api_response['uid'] ) ) {
            update_user_meta( $selected_admin_id, 'livcom_uid', sanitize_text_field( $api_response['uid'] ) );

            // Update website category if present
            if ( isset( $api_response['website_category'] ) ) {
                update_option( 'livcom_selected_website_category', sanitize_text_field( $api_response['website_category'] ) );
            }

            // Schedule events
            if ( ! wp_next_scheduled( 'livcom_cron_check_event' ) ) {
                wp_schedule_event( time(), 'hourly', 'livcom_cron_check_event' );
            }
            $lc_cron_status = get_option( 'livcom_cron_status' );
            if ( $lc_cron_status === 'Running' && ! wp_next_scheduled( 'livcom_cron_job' ) ) {
                wp_schedule_event( time(), 'livcom_random_interval', 'livcom_cron_job' );
            }

            // Update success response
            $responseArray['success'] = true;
            $responseArray['message'] = 'The user data was sent successfully.';
        }
    }

    // Send JSON response
    wp_send_json( $responseArray );
    wp_die();
}