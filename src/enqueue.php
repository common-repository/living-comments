<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Enqueue scripts and styles for the plugin.
 *
 * @param string $hook The current admin page hook.
 */
function livcom_enqueue( $hook ) {
    $base_dir = plugin_dir_url( dirname( __FILE__ ) );
	
	// Enqueue the tabs handler
	wp_enqueue_script( 'lc-tabs', $base_dir . 'js/tabs.js', array( 'jquery' ), '1.0', true );
	
	// Define an array of valid admin page slugs
	$valid_pages = [
		'toplevel_page_living-comments',
		'living-comments_page_living-comments-overview',
		'living-comments_page_living-comments-settings',
		'living-comments_page_living-comments-user',
		'living-comments_page_living-comments-history',
		'living-comments_page_living-comments-billing',
		'living-comments_page_living-comments-faq'
	];

	// Check if we're on one of the correct admin pages
	if ( !in_array($hook, $valid_pages) ) {
		return;
	}
	
    // Enqueue styles
    wp_enqueue_style( 'line-awesome', $base_dir . 'css/line-awesome.min.css', array(), '1.0' );
    wp_enqueue_style( 'bulma', $base_dir . 'css/bulma-prefixed.min.css', array(), '0.9.4' );
    wp_enqueue_style( 'lc-core', $base_dir . 'css/living-comments.css', array(), '1.0' );

    // Enqueue PayPal script if user ID is set
    $lc_user_id = get_option( 'livcom_user_id', 0 );
    if ( ! empty( $lc_user_id ) ) {
        $lc_uid = get_user_meta( $lc_user_id, 'livcom_uid', true );

        if ( ! empty( $lc_uid ) ) {
            // PayPal script parameters
            $paypal_params = array( 'custom_id' => $lc_uid );
            wp_enqueue_script( 'lc-paypal-buttons', $base_dir . 'js/paypal-buttons.js', array( 'jquery' ), '1.0', false );
            wp_enqueue_script( 'paypal-sdk', 'https://www.paypal.com/sdk/js?client-id=AZbmh12Zi3Wj8TJ3nPva2Iyz2LKhhYKkuUI1iv74qtiumc7hOfSPz9B4Z45SyxPIWgXSW8iV386K8GiC&vault=true&intent=subscription', array(), null, false );
            wp_localize_script( 'lc-paypal-buttons', 'paypal_params', $paypal_params );

            // Localize PayPal script with subscription data
            $localization_array = array(
                'admin_ajax_url' => admin_url( 'admin-ajax.php' ),
                'lc_plans'       => json_encode( get_option( 'livcom_plans' ) ),
                'today'          => gmdate( "F d" ),
                'nextMonth'      => gmdate( "F d, Y", strtotime( "+1 month", current_time( 'timestamp' ) ) ),
				'nonce'          => wp_create_nonce( 'livcom_settings_update' )
            );
            wp_localize_script( 'lc-paypal-buttons', 'lcSubscriptionData', $localization_array );
        }
    }
	
    // Enqueue scripts
    wp_enqueue_script( 'lc-plugin-settings', $base_dir . 'js/plugin-settings.js', array( 'jquery' ), '1.0', true );

	// Localize scripts for deleting comments
	$delete_comment_data = array( 
		'deleteCommentURL' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'livcom_settings_update' )
	);
	wp_localize_script( 'lc-plugin-settings', 'deleteCommentData', $delete_comment_data );

	$unhappy_comment_data = array(
		'ajaxURL' => admin_url( 'admin-ajax.php' ),
		'action'  => 'livcom_send_unhappy_comment',
		'nonce' => wp_create_nonce('livcom_settings_update')
	);
	wp_localize_script( 'lc-plugin-settings', 'unhappyCommentData', $unhappy_comment_data );
	
    wp_enqueue_script( 'lc-new-account', $base_dir . 'js/new-account.js', array( 'jquery' ), '1.0', true );

	$lc_new_account_data = array(
		'adminAjaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'livcom_settings_update' )
	);
    wp_localize_script( 'lc-new-account', 'lcNewAccountData', $lc_new_account_data );

    // Enqueue Chart.js and chart script
    wp_enqueue_script( 'chartjs', $base_dir . 'js/chart.umd.js', array(), '4.4.2', true );
    wp_enqueue_script( 'chart-script', $base_dir . 'js/chart.js', array( 'chartjs' ), '1.0', true );

    // Prepare chart data
    $lc_daily_totals = get_transient( 'livcom_daily_totals' );
    if ( false === $lc_daily_totals || ! isset( $lc_daily_totals['daily_comment_counts'] ) || ! isset( $lc_daily_totals['daily_reply_counts'] ) ) {
        $lc_daily_totals = livcom_calculate_daily_totals();
    }

    $latestStatsUpdateTime = sanitize_text_field( get_option( 'livcom_chart_last_updated', current_time( 'mysql' ) ) );
    $latestDate = strtotime( $latestStatsUpdateTime );

    $chart_data = array(
        'latestDate'     => $latestDate,
        'comment_counts' => array_reverse( $lc_daily_totals['daily_comment_counts'] ),
        'reply_counts'   => array_reverse( $lc_daily_totals['daily_reply_counts'] )
    );
    wp_localize_script( 'chart-script', 'chartData', $chart_data );

	// Enqueue words and user management script
	wp_enqueue_script( 'lc-words-users', $base_dir . 'js/words-users.js', array(), '1.0', true );

	// Localize user management data
	$custom_words = get_option( 'livcom_plugin_custom_words', [] );
	$guest_names = get_option( 'livcom_plugin_guest_names', [] );
	$dummy_users = get_option( 'livcom_plugin_dummy_users', [] );

	// Localize scripts with nonce and data
	wp_localize_script( 'lc-words-users', 'customWordsData', array( 'customWords' => $custom_words ) );
	wp_localize_script( 'lc-words-users', 'guestNamesData', array( 'guestNames' => $guest_names ) );
	wp_localize_script( 'lc-words-users', 'dummyUsersData', array( 
		'dummyUsers' => $dummy_users,
		'nonce' => wp_create_nonce('livcom_settings_update')
	));
}
