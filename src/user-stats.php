<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Fetches user statistics.
 */
function livcom_fetch_user_stats() {
	// Ensure lc_stats_past_week is properly initialized
	$stats_past_week = maybe_unserialize( get_option( 'livcom_stats_past_week', [] ) );

	// Check if $stats_past_week is null, not an array, or empty
	if ( is_null( $stats_past_week ) || ! is_array( $stats_past_week ) || empty( $stats_past_week ) ) {
		livcom_initialize_stats();
		$stats_past_week = maybe_unserialize( get_option( 'livcom_stats_past_week', [] ) );
		error_log( 'Stats array was null, not an array, or empty. Initialized lc_stats_past_week.' );
	}
	
    // Fetching user information
    $user_id = intval( get_option( 'livcom_user_id' ) );
    $lc_api_key = sanitize_text_field( get_user_meta( $user_id, 'livcom_api_key', true ) );
    $lc_uid = sanitize_text_field( get_user_meta( $user_id, 'livcom_uid', true ) );
    $domain = esc_url_raw( get_site_url() );

    $stats_past_week = maybe_unserialize( get_option( 'livcom_stats_past_week', [] ) );

    // Setting up headers for the API call
    $headers = array(
        'LC-API-KEY'  => $lc_api_key,
        'LC-UID'      => $lc_uid,
        'LC-DOMAIN'   => $domain,
        'Content-Type'=> 'application/json'
    );

    // Configuring request parameters
    $args = array(
        'timeout' => 120,
        'headers' => $headers,
    );

    // API call
    $response = wp_remote_get( 'https://lotus.livingcomments.com/userStats', $args );

    // Handling response
    if ( is_wp_error( $response ) ) {
        $error_message = sanitize_text_field( $response->get_error_message() );
        error_log( 'API call error: ' . $error_message );
        return $error_message;
    } else {
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Validating response structure
        if ( ! isset( $body['userStats'] ) || ! isset( $body['userStats']['num_comments'] ) || ! isset( $body['userStats']['num_replies'] ) ) {
            error_log( 'Invalid response structure from the API' );
            return 'Invalid response structure from the API';
        }

        $userStats = $body['userStats'];

        // Processing user statistics
        if ( isset( $userStats['num_comments'], $userStats['num_replies'] ) && is_numeric( $userStats['num_comments'] ) && is_numeric( $userStats['num_replies'] ) ) {
            $num_comments = intval( $userStats['num_comments'] );
            $num_replies = intval( $userStats['num_replies'] );

            // Updating options with new values
            update_option( 'livcom_latest_num_com', esc_attr( $num_comments ) );
            update_option( 'livcom_latest_num_rep', esc_attr( $num_replies ) );

            // Updating weekly stats
			while ( count( $stats_past_week ) > 167 ) {
				array_shift( $stats_past_week ); // Removes the oldest entry
				error_log( 'Trimming lc_stats_past_week: Reducing size to 167 before adding new entry.' );
			}

            $current_time = current_time( 'mysql' );

            $stats_past_week[] = array(
                'timestamp'     => sanitize_text_field( $current_time ),
                'num_comments'  => intval( $num_comments ),
                'num_replies'   => intval( $num_replies )
            );
            
			// Before updating, ensure $stats_past_week is not empty and has the correct structure
			if ( ! empty( $stats_past_week ) && is_array( $stats_past_week ) ) {
				$isValid = false;

				// Check the first element for the expected structure
				if ( isset( $stats_past_week[0] ) && is_array( $stats_past_week[0] ) ) {
					$first_element = $stats_past_week[0];
					if ( isset( $first_element['num_comments'], $first_element['num_replies'] ) 
						&& is_numeric( $first_element['num_comments'] ) 
						&& is_numeric( $first_element['num_replies'] ) ) {
						$isValid = true;
					}
				}

				if ( $isValid ) {
					update_option( 'livcom_stats_past_week', maybe_serialize( $stats_past_week ) );
					error_log( 'Updated lc_stats_past_week with new statistics.' );
				} else {
					error_log( 'Error: Invalid structure in lc_stats_past_week. Update aborted.' );
				}
			} else {
				error_log( 'Error: lc_stats_past_week is empty or not an array. Update aborted.' );
			}
        } else {
            error_log( 'num_comments or num_replies is not numeric' );
            return 'num_comments or num_replies is not numeric';
        }
    }
}

/**
 * Calculates daily comment and reply totals.
 */
function livcom_calculate_daily_totals() {
    $storedTransient = get_transient( 'livcom_daily_totals' );
    $today = gmdate( 'Y-m-d', strtotime( current_time( 'mysql' ) ) );
    $threshold = 400;

    if ( false === $storedTransient ) {
        $stats_past_week = maybe_unserialize( get_option( 'livcom_stats_past_week', [] ) );

        $daily_comment_counts = [];
        $daily_reply_counts = [];

        $one_week_ago = strtotime( '-6 days', strtotime( $today ) );

        // Initialize stats_per_day with placeholders for each day
        $stats_per_day = [];
        $current_date = $one_week_ago;
        while ( $current_date <= strtotime( $today ) ) {
            $formatted_date = gmdate( 'Y-m-d', $current_date );
            $stats_per_day[$formatted_date] = [];
            $current_date = strtotime( '+1 day', $current_date );
        }

        $last_stats_before_week = [ 'timestamp' => '1970-01-01 00:00:00', 'num_comments' => 0, 'num_replies' => 0 ];

        foreach ( $stats_past_week as $stats ) {
            if ( !is_array( $stats ) || !isset( $stats['timestamp'], $stats['num_comments'], $stats['num_replies'] ) ) {
                continue;
            }

            $timestamp_unix = strtotime( $stats['timestamp'] );

            if ( $timestamp_unix < $one_week_ago ) {
                if ( strtotime( $last_stats_before_week['timestamp'] ) < $timestamp_unix ) {
                    $last_stats_before_week = $stats;
                }
            } else {
                $day = gmdate( 'Y-m-d', $timestamp_unix );
                $stats_per_day[$day][] = $stats;
            }
        }

        $previous_day_stats = $last_stats_before_week;
        $days = array_keys( $stats_per_day );

        foreach ( $days as $day ) {
            $stats_for_day = $stats_per_day[$day];

            if ( count( $stats_for_day ) > 0 ) {
                usort( $stats_for_day, function( $a, $b ) {
                    return strtotime( $a['timestamp'] ) - strtotime( $b['timestamp'] );
                });

                $latest_stat = end( $stats_for_day );
            } else {
                // Use the last stats if no stats are available for the day
                $latest_stat = $previous_day_stats;
            }

            $comment_diff = $latest_stat['num_comments'] - $previous_day_stats['num_comments'];
            $reply_diff = $latest_stat['num_replies'] - $previous_day_stats['num_replies'];

            if ( $comment_diff > $threshold || $reply_diff > $threshold ) {
                $starting_point = null;
                foreach ( $stats_for_day as $stat ) {
                    if ( $stat['num_comments'] > 0 || $stat['num_replies'] > 0 ) {
                        $starting_point = $stat;
                        break;
                    }
                }

                if ( $starting_point ) {
                    $comment_diff = $latest_stat['num_comments'] - $starting_point['num_comments'];
                    $reply_diff = $latest_stat['num_replies'] - $starting_point['num_replies'];
                }
            }

            $daily_comment_counts[] = $comment_diff;
            $daily_reply_counts[] = $reply_diff;

            $previous_day_stats = $latest_stat;
        }

        $daily_comment_counts = array_reverse( $daily_comment_counts );
        $daily_reply_counts = array_reverse( $daily_reply_counts );

        $daily_totals = [ 'daily_comment_counts' => $daily_comment_counts, 'daily_reply_counts' => $daily_reply_counts ];

        if ( $daily_totals != $storedTransient ) {
            set_transient( 'livcom_daily_totals', $daily_totals, 1 * HOUR_IN_SECONDS );
            update_option( 'livcom_chart_last_updated', current_time( 'mysql' ) );
        }
    } else {
        $daily_totals = $storedTransient;
    }

    return $daily_totals;
}

/**
 * Fetches user balance from API.
 */
function livcom_fetch_user_balance() {
    // Fetch user ID and API details
    $user_id = get_option( 'livcom_user_id' );
    $lc_api_key = sanitize_text_field( get_user_meta( $user_id, 'livcom_api_key', true ) );
    $lc_uid = sanitize_text_field( get_user_meta( $user_id, 'livcom_uid', true ) );
    $lc_cron_status = get_option( 'livcom_cron_status' );
    $domain = esc_url_raw( get_site_url() );

    // Set API request headers
    $headers = array(
        'LC-API-KEY' => $lc_api_key,
        'LC-UID'     => $lc_uid,
        'LC-DOMAIN'  => $domain,
        'Content-Type' => 'application/json'
    );

    // Configure API request parameters
    $args = array(
        'timeout' => 10,
        'headers' => $headers,
    );

    // Make API request
    $response = wp_remote_get( 'https://lotus.livingcomments.com/userBalance', $args );

    // Handle API response errors
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        return $error_message;
    } else {
        // Check HTTP status code
        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code != 200 ) {
            return 'API error: HTTP status code ' . $http_code;
        }

        // Decode JSON response
        $json_body = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $json_body, true );

        // Check if the response is valid and contains 'balance'
        if ( is_array( $decoded ) && array_key_exists( 'balance', $decoded ) ) {
            $balance = $decoded['balance'];
        } else {
            // Log error or set a default value
            error_log('Error retrieving balance: Invalid response structure');
        }

        // Cron job scheduling checks
        $is_scheduled = wp_next_scheduled( 'livcom_cron_job' );
        if ( $balance > 0 && $lc_cron_status == 'Running' ) {
            if ( ! $is_scheduled ) {
                // Add new schedule if not exists
                $schedules = wp_get_schedules();
                if ( ! isset( $schedules['livcom_random_interval'] ) ) {
                    add_filter( 'cron_schedules', 'livcom_cron_schedule' );
                }

                // Schedule new cron job
                if ( ! wp_next_scheduled( 'livcom_cron_job' ) ) {
                    wp_schedule_event( time(), 'livcom_random_interval', 'livcom_cron_job' );
                }
            }
        } elseif ( $balance == 0 && $is_scheduled || $lc_cron_status == 'Paused' ) {
            // Unschedule existing cron job
            $timestamp = wp_next_scheduled( 'livcom_cron_job' );
            if ( $timestamp !== false ) {
                wp_unschedule_event( $timestamp, 'livcom_cron_job' );
            }
        }

        // Return balance
        return $balance;
    }
}

/**
 * Fetch user billing details.
 */
function livcom_fetch_user_billing() {
    // Retrieve user details and sanitize inputs
    $user_id = get_option( 'livcom_user_id' );
    $lc_api_key = sanitize_text_field( get_user_meta( $user_id, 'livcom_api_key', true ) );
    $lc_uid = sanitize_text_field( get_user_meta( $user_id, 'livcom_uid', true ) );
    $domain = esc_url_raw( get_site_url() );
    $user_timezone = sanitize_text_field( get_option( 'timezone_string' ) );

    // Set request headers
    $headers = array(
        'LC-API-KEY' => $lc_api_key,
        'LC-UID' => $lc_uid,
        'LC-DOMAIN' => $domain,
        'Content-Type' => 'application/json'
    );

    // Set request arguments
    $args = array(
        'timeout' => 120,
        'headers' => $headers,
    );

    // Perform API call
    $response = wp_remote_get( 'https://lotus.livingcomments.com/userBilling', $args );

    // Handle response errors
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        error_log( 'API call error: ' . $error_message );
        return $error_message;
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        $headers = wp_remote_retrieve_headers( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Validate response structure
        if ( !isset( $body['userBilling'] ) || !isset( $body['userBilling']['plan_name'] ) ) {
            error_log( 'Invalid response structure from the API: ' . print_r( $body, true ) );
            return 'Invalid response structure from the API';
        }

        $userBilling = $body['userBilling'];

        // Update plan name
        if ( isset( $userBilling['plan_name'] ) && !empty( $userBilling['plan_name'] ) ) {
            $plan_name = sanitize_text_field( $userBilling['plan_name'] );
            update_option( 'livcom_user_plan', esc_attr( $plan_name ) );
        } else {
            error_log( 'Plan name is not set or empty' );
            return 'Plan name is not set or empty';
        }
		
		// Update website category
		if ( isset( $userBilling['website_category'] ) && !empty( $userBilling['website_category'] ) ) {
			$website_category = sanitize_text_field( $userBilling['website_category'] );
			update_option( 'livcom_selected_website_category', esc_attr( $website_category ) );
		}

		// Process and update subscriptions
		if ( isset( $userBilling['subscriptions'] ) && is_array( $userBilling['subscriptions'] ) ) {
			$serverTimezone = new DateTimeZone( sanitize_text_field( $body['serverTimezone'] ) );

			// Ensure $user_timezone contains a valid timezone string
			if ( empty( $user_timezone ) || !in_array( $user_timezone, timezone_identifiers_list() ) ) {
				// Log the issue for debugging
				error_log( 'Invalid or empty user timezone: ' . $user_timezone );

				// Fallback to a default timezone, e.g., UTC or the server's timezone
				$user_timezone = 'UTC';
			}
			$userTimezone = new DateTimeZone( $user_timezone );

			$subscriptions = $userBilling['subscriptions'];
			foreach ( $subscriptions as &$subscription ) {
				foreach ( [ 'start_date', 'end_date', 'created_at', 'updated_at' ] as $timeField ) {
					if ( isset( $subscription[$timeField] ) ) {
						$date = new DateTime( $subscription[$timeField], $serverTimezone );
						$date->setTimezone( $userTimezone );
						$subscription[$timeField] = $date->format( 'Y-m-d H:i:s' );
					}
				}
			}

			update_option( 'livcom_user_subs', $subscriptions );
		} else {
			error_log( 'Subscriptions is not set or is not an array' );
			return 'Subscriptions is not set or is not an array';
		}
    }
}

/**
 * Fetches plan details from API.
 */
function livcom_fetch_plan_details() {
    $args = array( 'timeout' => 120 );

    // API call
    $response = wp_remote_get( 'https://lotus.livingcomments.com/planDetails', $args );

    // Handle API error
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        error_log( 'API call error: ' . $error_message );
        return $error_message;
    } else {
        // Process response
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Validate response structure
        if ( ! isset( $body['planDetails'] ) ) {
            error_log( 'Invalid response structure from the API' );
            return 'Invalid response structure from the API';
        }

        $planDetails = $body['planDetails'];

        // Initialize or sanitize plan details
        if ( ! is_array( $planDetails ) ) {
            $planDetails = array();
        } else {
            foreach ( $planDetails as $key => $plan ) {
                // Sanitize plan name
                if ( isset( $plan['name'] ) ) {
                    $planDetails[$key]['name'] = sanitize_text_field( $plan['name'] );
                }
                
                // Ensure price is an integer
                if ( isset( $plan['price'] ) ) {
                    $planDetails[$key]['price'] = intval( $plan['price'] );
                }
            }
        }

        // Update option with plan details
        update_option( 'livcom_plans', $planDetails );
    }
}

/**
 * Refresh actions for user stats.
 */
function livcom_refresh_actions() {
    // Fetch user billing details
    livcom_fetch_user_billing();

    // Fetch user balance
    livcom_fetch_user_balance();

    // Terminate execution
    wp_die();
}