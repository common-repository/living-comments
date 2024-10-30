<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Run scheduled cron tasks.
 */
function livcom_cron_check_tasks() {
    // Fetch user balance
    livcom_fetch_user_balance();

    // Fetch user statistics
    livcom_fetch_user_stats();

    // Fetch user billing information
    livcom_fetch_user_billing();

    // Fetch plan details
    livcom_fetch_plan_details();

    // Calculate daily totals
    livcom_calculate_daily_totals();
}

/**
 * Callback for LC cron job.
 */
function livcom_cron_job_callback() {
    // Check cron status
    $lc_cron_status = get_option( 'livcom_cron_status' );
    if ( 'Paused' === $lc_cron_status ) {
        $timestamp = wp_next_scheduled( 'livcom_cron_job' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'livcom_cron_job' );
            return;
        }
    }

    // Fetch post data
    $prompt_data = livcom_fetch_post_data();
    if ( ! $prompt_data || ! is_array( $prompt_data ) ) {
        error_log( 'Failed to fetch prompt data.' );
        return;
    }

	// Sanitize data
	$random_post_id = isset( $prompt_data['random_post_id'] ) ? intval( $prompt_data['random_post_id'] ) : 0;
	$random_comment_id = isset( $prompt_data['random_comment_id'] ) ? intval( $prompt_data['random_comment_id'] ) : 0;
	$random_comment_content = isset( $prompt_data['random_comment_content'] ) ? sanitize_text_field( $prompt_data['random_comment_content'] ) : '';
	$random_tone = isset( $prompt_data['random_tone'] ) ? sanitize_text_field( $prompt_data['random_tone'] ) : '';
	$random_post_title = isset( $prompt_data['random_post_title'] ) ? sanitize_text_field( $prompt_data['random_post_title'] ) : '';
	$random_post_snippet = isset( $prompt_data['random_post_snippet'] ) ? sanitize_text_field( $prompt_data['random_post_snippet'] ) : '';
	$random_short_snippet = isset( $prompt_data['random_short_snippet'] ) ? sanitize_text_field( $prompt_data['random_short_snippet'] ) : '';

    // Get user data
    $lc_user_id = absint( get_option( 'livcom_user_id' ) );
    $lc_api_key = sanitize_text_field( get_user_meta( $lc_user_id, 'livcom_api_key', true ) );
    $lc_uid = sanitize_text_field( get_user_meta( $lc_user_id, 'livcom_uid', true ) );
    $lc_user_priority = sanitize_text_field( get_option( 'livcom_user_priority', 'off' ) );
	$lc_language = sanitize_text_field( get_option( 'livcom_language', 'English' ) );

    // Sanitize array options
    $guest_names = array_map( 'sanitize_text_field', get_option( 'livcom_plugin_guest_names', array() ) );
    $dummy_users = array_map( 'sanitize_text_field', get_option( 'livcom_plugin_dummy_users', array() ) );

    // Filter arrays
    $non_empty_guest_names = array_filter( $guest_names, 'strlen' );
    $non_empty_dummy_users = array_filter( $dummy_users, 'strlen' );

    // Initialize variables
    $user_info = null;
    $uniqueUserFound = false;
    $available_options = array();

    // Determine available options
    if ( ! empty( $non_empty_guest_names ) ) {
        $available_options[] = 'getRandomGuestName';
    }
    if ( ! empty( $non_empty_dummy_users ) ) {
        $available_options[] = 'getRandomUserId';
    }
    if ( 'off' === $lc_user_priority ) {
        $available_options[] = 'requestRandomUser';
    }

    // Retrieve and log lc_allow_reply_users setting
    $allow_reply_users = get_option( 'livcom_allow_reply_users', 'on' );

    // Set and log comment reply probability
    $comment_reply_ratio = absint( get_option( 'livcom_plugin_comment_reply_ratio', 70 ) );
    $comment_reply_ratio = min( max( $comment_reply_ratio, 0 ), 100 );
    $chosen_option = wp_rand( 0, 100 );

    // Count and log comments
    $comment_count = wp_count_comments( $random_post_id );
    $num_comments = $comment_count->approved;

    // Check for plugin-generated comments
    $has_plugin_generated_comments = false;
    if ( 'off' === $allow_reply_users && $num_comments > 0 ) {
        $all_comments = get_comments( [ 'post_id' => $random_post_id, 'status' => 'approve' ] );
        foreach ( $all_comments as $comment ) {
            if ( get_comment_meta( $comment->comment_ID, 'livcom_tone', true ) ) {
                $has_plugin_generated_comments = true;
                break;
            }
        }
    }

    // Determine comment or reply probability
    if ( $num_comments === 0 || ( 'off' === $allow_reply_users && ! $has_plugin_generated_comments ) ) {
        $probability = 0; // Comment needed due to no comments or no plugin-generated comments
    } elseif ( $comment_reply_ratio === 0 && $num_comments > 0 ) {
        $probability = 101; // Reply needed
    } else {
        $probability = $chosen_option;
    }

	// Loop for user selection
	for ( $i = 0; $i < 3; $i++ ) {
		if ( ! empty( $available_options ) ) {
			// Shuffle the available options
			shuffle( $available_options );
		
			$random_option_index = array_rand( $available_options );
			$random_option = $available_options[ $random_option_index ];

			// Log attempt
			error_log( "Executing: {$random_option}. Attempt: " . ( $i + 1 ) );

			// User selection logic
			if ( 'getRandomGuestName' === $random_option ) {
				$user_info = livcom_get_random_guest_name();
			} elseif ( 'getRandomUserId' === $random_option ) {
				$user_object = livcom_get_random_user_id();
				if ( false !== $user_object ) {
					$user_info = array( 'ID' => $user_object->ID );
				}
			} elseif ( 'requestRandomUser' === $random_option ) {
				$user_info = livcom_request_random_user( $lc_api_key, $lc_uid );
			}

			// Option validation
			if ( null === $user_info ) {
				if ( 'requestRandomUser' !== $random_option ) {
					error_log( "Option: {$random_option} removed. Reason: Null user info." );
					unset( $available_options[ $random_option_index ] );
					$available_options = array_values( $available_options );
				} else {
					error_log( "Option: {$random_option} retained despite null user info." );
				}
				continue;
			}

			// Uniqueness check
			$is_unique = true;
			if ( 'requestRandomUser' !== $random_option ) {
				$is_unique = livcom_check_user_uniqueness( $user_info, $random_option, $chosen_option, $comment_reply_ratio, $random_post_id, $random_comment_id );
			}
			
			// Uniqueness check failure handling
			if ( ! $is_unique ) {
				error_log( 'Username/ID is not unique.' );

				// Check if lc_user_priority is 'on' and the specific option has more than two names
				$option_has_enough_names = ( 'getRandomGuestName' === $random_option && count( $non_empty_guest_names ) > 1 ) ||
										   ( 'getRandomUserId' === $random_option && count( $non_empty_dummy_users ) > 1 );

				if ( ! ( 'on' === $lc_user_priority && $option_has_enough_names ) ) {
					// Remove the option if the username/ID is not unique and conditions are not met
					error_log( "Option: {$random_option} removed. Reason: Non-unique username/id or insufficient names for the specific option." );
					unset( $available_options[ $random_option_index ] );
					$available_options = array_values( $available_options );
				}
			}
			
			// Comment or reply creation
			if ( $is_unique && null !== $user_info ) {
				$uniqueUserFound = true;

				if ( $probability === 0 ) {
					error_log( "Forced decision: Generate Comment" );
					// Force generate comment
					$comment_payload = livcom_request_random_comment( $lc_api_key, $lc_uid, $random_post_title, $random_post_snippet, $random_tone, $lc_language );
					if ( is_array( $comment_payload ) && isset( $comment_payload['comment'], $comment_payload['tone'] ) ) {
						$lc_comment = livcom_insert_comment_payload( $random_post_id, $user_info, $comment_payload, $random_option );
						break;
					}
				} elseif ( $probability <= $comment_reply_ratio ) {
					error_log( "Decision: Generate Comment" );
					// Normal decision for generating a comment
					$comment_payload = livcom_request_random_comment( $lc_api_key, $lc_uid, $random_post_title, $random_post_snippet, $random_tone, $lc_language );
					if ( is_array( $comment_payload ) && isset( $comment_payload['comment'], $comment_payload['tone'] ) ) {
						$lc_comment = livcom_insert_comment_payload( $random_post_id, $user_info, $comment_payload, $random_option );
						break;
					}
				} else {
					error_log( "Decision: Generate Reply" );
					// Generate reply
					$reply_payload = livcom_request_random_reply( $lc_api_key, $lc_uid, $random_comment_content, $random_short_snippet, $random_post_title, $random_tone, $lc_language );
					if ( is_array( $reply_payload ) && isset( $reply_payload['reply'], $reply_payload['tone'] ) ) {
						$lc_reply = livcom_insert_reply_payload( $random_post_id, $random_comment_id, $user_info, $reply_payload, $random_option );
						break;
					}
				}
			}
		} else {
			error_log( 'No available options to choose from.' );
			break;
		}
	}

    // Final attempt to find a unique user
    if ( ! $uniqueUserFound ) {
        error_log( "Could not find unique user after 3 attempts. Requesting random user." );
        $user_info = livcom_request_random_user( $lc_api_key, $lc_uid );

        if ( null !== $user_info ) {
            if ( $probability === 0 ) {
                error_log( "Forced decision: Generate Comment" );
                // Force generate comment
                $comment_payload = livcom_request_random_comment( $lc_api_key, $lc_uid, $random_post_title, $random_post_snippet, $random_tone, $lc_language );
                if ( is_array( $comment_payload ) && isset( $comment_payload['comment'], $comment_payload['tone'] ) ) {
                    $lc_comment = livcom_insert_comment_payload( $random_post_id, $user_info, $comment_payload, 'requestRandomUser' );
                }
            } elseif ( $probability <= $comment_reply_ratio ) {
                error_log( "Decision in final attempt: Generate Comment" );
                // Normal decision for generating a comment
                $comment_payload = livcom_request_random_comment( $lc_api_key, $lc_uid, $random_post_title, $random_post_snippet, $random_tone, $lc_language );
                if ( is_array( $comment_payload ) && isset( $comment_payload['comment'], $comment_payload['tone'] ) ) {
                    $lc_comment = livcom_insert_comment_payload( $random_post_id, $user_info, $comment_payload, 'requestRandomUser' );
                }
            } else {
                error_log( "Decision in final attempt: Generate Reply" );
                // Generate reply
                $reply_payload = livcom_request_random_reply( $lc_api_key, $lc_uid, $random_comment_content, $random_short_snippet, $random_post_title, $random_tone, $lc_language );
                if ( is_array( $reply_payload ) && isset( $reply_payload['reply'], $reply_payload['tone'] ) ) {
                    $lc_reply = livcom_insert_reply_payload( $random_post_id, $random_comment_id, $user_info, $reply_payload, 'requestRandomUser' );
                }
            }
        }
    }
}

/**
 * Check user uniqueness for comments or replies.
 */
function livcom_check_user_uniqueness( $user_info, $random_option, $chosen_option, $comment_reply_ratio, $random_post_id, $random_comment_id ) {
    if ( $chosen_option <= $comment_reply_ratio ) {
        return ( isset( $user_info['ID'] ) && livcom_is_unique_author_comment( null, $user_info['ID'], $random_post_id ) )
            || ( isset( $user_info['username'] ) && livcom_is_unique_author_comment( $user_info['username'], null, $random_post_id ) );
    } else {
        return ( isset( $user_info['ID'] ) && livcom_is_unique_author_reply( null, $user_info['ID'], $random_comment_id ) )
            || ( isset( $user_info['username'] ) && livcom_is_unique_author_reply( $user_info['username'], null, $random_comment_id ) );
    }
}

/**
 * Insert comment payload.
 */
function livcom_insert_comment_payload( $random_post_id, $user_info, $comment_payload, $random_option ) {
    $comment_content = $comment_payload['comment'];
    $tone = $comment_payload['tone'];
    $word_length = $comment_payload['word_length'];

    return livcom_insert_comment( $random_post_id, $user_info, $comment_content, $tone, $word_length, $random_option );
}

/**
 * Insert reply payload.
 */
function livcom_insert_reply_payload( $random_post_id, $random_comment_id, $user_info, $reply_payload, $random_option ) {
    $reply_content = $reply_payload['reply'];
    $tone = $reply_payload['tone'];
    $word_length = $reply_payload['word_length'];

    return livcom_insert_reply( $random_post_id, $random_comment_id, $user_info, $reply_content, $tone, $word_length, $random_option );
}

/**
 * Adds custom cron schedule.
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified cron schedules.
 */
function livcom_cron_schedule( $schedules ) {
    // Retrieve saved frequency and ensure it's within bounds
    $frequency = intval( get_option( 'livcom_plugin_frequency', 4 ) );
    $frequency = max( 4, min( 1440, $frequency ) );

    // Calculate interval in seconds
    $interval = $frequency * 60;

	// Add custom schedule based on frequency
	$schedules['livcom_random_interval'] = array(
		'interval' => $interval,
		'display'  => sprintf( 'Every %d minutes', $frequency )
	);

    return $schedules;
}

/**
 * Pauses the scheduled cron job.
 */
function livcom_pause_cron_job() {
    // Verify nonce for security
    check_admin_referer( 'livcom_pause_cron_job_action' );

    // Retrieve next scheduled cron job timestamp
    $timestamp = wp_next_scheduled( 'livcom_cron_job' );

    // Unschedules cron job if scheduled
    if ( false !== $timestamp ) {
        wp_unschedule_event( $timestamp, 'livcom_cron_job' );
        update_option( 'livcom_cron_status', 'Paused' );
    }

    // Redirect to the settings page
    wp_redirect( admin_url( 'admin.php?page=living-comments' ) );
    exit;
}

/**
 * Resume cron job.
 */
function livcom_resume_cron_job() {
    // Verify nonce for security
    check_admin_referer( 'livcom_resume_cron_job_action' );

    // Schedule cron job if not already scheduled
    if ( ! wp_next_scheduled( 'livcom_cron_job' ) ) {
        wp_schedule_event( time(), 'livcom_random_interval', 'livcom_cron_job' );
        update_option( 'livcom_cron_status', 'Running' );
    }

    // Redirect to settings page
    wp_redirect( admin_url( 'admin.php?page=living-comments' ) );
    exit;
}

/**
 * Updates the LC cron job.
 * 
 * @param mixed $old_value The old value.
 * @param mixed $new_value The new value.
 */
function livcom_update_cron_job( $old_value, $new_value ) {
    // Retrieve API key and UID
    $user_id = get_option( 'livcom_user_id' );
    $lc_api_key = get_user_meta( $user_id, 'livcom_api_key', true );
    $lc_uid = get_user_meta( $user_id, 'livcom_uid', true );

    // Check if API key or UID is empty
    if ( empty( $lc_api_key ) || empty( $lc_uid ) ) {
        $timestamp = wp_next_scheduled( 'livcom_cron_job' );
        if ( $timestamp !== false ) {
            wp_unschedule_event( $timestamp, 'livcom_cron_job' );
        }
        // Optionally log this action or handle it accordingly
        error_log('API key or UID is empty, unscheduled livcom_cron_job');
        return 'API key or UID is empty, cron job unscheduled';
    }

    $old_value = intval( $old_value );
    $new_value = intval( $new_value );
    
    // Proceed only if values differ
    if ( $old_value !== $new_value ) {
        // Unschedule existing cron job
        $timestamp = wp_next_scheduled( 'livcom_cron_job' );
        if ( $timestamp !== false ) {
            wp_unschedule_event( $timestamp, 'livcom_cron_job' );
        }

        // Generate and validate new frequency
        $new_frequency = livcom_generate_random_frequency();
        $new_frequency = max( 4, min( 1440, intval( $new_frequency ) ) );
        update_option( 'livcom_plugin_frequency', $new_frequency );

        // Create/update custom cron schedule
        add_filter( 'cron_schedules', function( $schedules ) use ( $new_frequency ) {
            $schedules['livcom_random_interval'] = array(
                'interval' => $new_frequency * 60,
                'display'  => esc_html__( 'Custom Interval' )
            );
            return $schedules;
        });

        // Schedule new cron job
        if ( ! wp_next_scheduled( 'livcom_cron_job' ) ) {
            wp_schedule_event( time(), 'livcom_random_interval', 'livcom_cron_job' );
        }
    }
}
