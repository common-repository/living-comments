<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Update user plan.
 */
function livcom_update_user_plan() {
	// Verify the nonce first
    if ( ! isset( $_POST['livcom_settings_nonce'] ) || ! check_admin_referer( 'livcom_settings_update', 'livcom_settings_nonce' ) ) {
        wp_die( 'Security check failed' ); // Nonce verification failed, halt execution.
    }
	
    // Check if new plan is set
    if ( isset( $_POST['new_plan'] ) ) {
        // Sanitize and update new plan
        $new_plan = sanitize_text_field( $_POST['new_plan'] );
        update_option( 'livcom_user_plan', $new_plan );

        // Send success response
        wp_send_json_success( array( 'message' => 'Plan updated successfully.' ) );
    } else {
        // Send error response
        wp_send_json_error( array( 'message' => 'No plan provided.' ) );
    }
}

/**
 * Cancels a subscription.
 * 
 * @param string $subscriptionId The subscription ID to cancel.
 */
function livcom_cancel_subscription( $subscriptionId ) {
    $subscriptionId = sanitize_text_field( $subscriptionId );
    $user_id = get_option( 'livcom_user_id' );
    $lc_api_key = sanitize_text_field( get_user_meta( $user_id, 'livcom_api_key', true ) );
    $lc_uid = sanitize_text_field( get_user_meta( $user_id, 'livcom_uid', true ) );
    $domain = esc_url_raw( get_site_url() );

    // Prepare headers
    $headers = array(
        'LC-API-KEY'   => $lc_api_key,
        'LC-UID'       => $lc_uid,
        'LC-DOMAIN'    => $domain,
        'Content-Type' => 'application/json'
    );

    // Prepare request arguments
    $args = array(
        'timeout' => 120,
        'headers' => $headers,
        'body'    => json_encode( array( 'subscriptionId' => $subscriptionId ) ),
    );

    // Send request
    $response = wp_remote_post( 'https://lotus.livingcomments.com/cancelSubscription', $args );

    // Handle response
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        error_log( "Error in request: $error_message" );
        wp_send_json_error( "Request error." );
    } else {
        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        // Process response data
        if ( isset( $response_data['error'] ) ) {
            error_log( "Server error: " . $response_data['error'] );
            wp_send_json_error( "Processing error." );
        } elseif ( isset( $response_data['success'] ) ) {
            wp_send_json_success( "Success: " . $response_data['success'] );
        } else {
            error_log( "Unexpected server response: " . $response_body );
            wp_send_json_error( "Unexpected response." );
        }
    }
}

/**
 * Handles subscription cancellation.
 */
function livcom_cancel_subscription_handler() {
	// Verify the nonce first
    if ( ! isset( $_POST['livcom_settings_nonce'] ) || ! check_admin_referer( 'livcom_settings_update', 'livcom_settings_nonce' ) ) {
        wp_die( 'Security check failed' ); // Nonce verification failed, halt execution.
    }
	
    // Check if subscriptionId is set
    if ( isset( $_POST['subscriptionId'] ) ) {
        // Sanitize the received subscriptionId
        $subscriptionId = sanitize_text_field( $_POST['subscriptionId'] );
        
        // Proceed if subscriptionId is not empty
        if ( ! empty( $subscriptionId ) ) {
            // Cancel the subscription
            livcom_cancel_subscription( $subscriptionId );
        }
    }

    // Terminate execution
    wp_die();
}
