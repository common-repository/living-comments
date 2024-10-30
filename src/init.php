<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Initializes main plugin hooks.
 */
function livcom_init_hooks() {
    // Main plugin hooks
    add_filter( 'cron_schedules', 'livcom_cron_schedule' );
    add_action( 'livcom_cron_check_event', 'livcom_cron_check_tasks' );
    add_action( 'livcom_cron_job', 'livcom_cron_job_callback' );
    add_action( 'admin_post_livcom_pause_cron_job', 'livcom_pause_cron_job' );
    add_action( 'admin_post_livcom_resume_cron_job', 'livcom_resume_cron_job' );
    add_action( 'update_option_livcom_plugin_frequency_min', 'livcom_update_cron_job', 10, 2 );
    add_action( 'update_option_livcom_plugin_frequency_max', 'livcom_update_cron_job', 10, 2 );
    add_action( 'admin_enqueue_scripts', 'livcom_enqueue' );
    add_action( 'wp_ajax_livcom_send_user_data', 'livcom_send_user_data' );
    add_action( 'admin_menu', 'livcom_plugin_menu' );
    add_action( 'admin_init', 'livcom_register_plugin_settings' );
    add_action( 'wp_ajax_livcom_delete_comment', 'livcom_delete_comment' );
    add_action( 'wp_ajax_livcom_send_unhappy_comment', 'livcom_send_unhappy_comment' );
    add_action( 'wp_ajax_livcom_cancel_subscription', 'livcom_cancel_subscription_handler' );
    add_action( 'show_user_profile', 'livcom_add_custom_user_profile_fields', 1 );
    add_action( 'edit_user_profile', 'livcom_add_custom_user_profile_fields', 1 );
    add_action( 'user_new_form', 'livcom_add_custom_user_profile_fields', 1 );
    add_action( 'personal_options_update', 'livcom_save_custom_user_profile_fields' );
    add_action( 'edit_user_profile_update', 'livcom_save_custom_user_profile_fields' );
    add_action( 'user_register', 'livcom_save_custom_user_profile_fields' );
    add_action( 'wp_ajax_livcom_check_username', 'livcom_plugin_check_username' );
    add_action( 'wp_ajax_livcom_refresh_actions', 'livcom_refresh_actions' );
    add_action( 'wp_ajax_update_user_plan', 'livcom_update_user_plan' );
    add_action( 'admin_head', 'livcom_custom_admin_notice' );
	add_action( 'admin_head', 'livcom_hide_notices' );
	add_action( 'admin_init', 'livcom_set_active_submenu' );
}

add_action( 'init', 'livcom_init_hooks' );

$plugin_file = plugin_basename( LIVCOM_MAIN_FILE );

// Activation and deactivation hooks
register_activation_hook( $plugin_file, 'livcom_activate' );
register_deactivation_hook( $plugin_file, 'livcom_deactivate' );

/**
 * Handles activation procedures for the plugin.
 */
function livcom_activate() {
    error_log( 'Living Comments Plugin: activated.' );

    // Define default option values
    $defaults = array(
        'livcom_plugin_word_length'          => '1,2,3',
        'livcom_allow_emoticons'             => 'on',
		'livcom_allow_reply_users'           => 'on',
        'livcom_plugin_comment_reply_ratio'  => 50,
        'livcom_allocation'                  => 7,
        'livcom_num_posts'                   => 7,
        'livcom_latest_num_rep'              => 0,
        'livcom_latest_num_com'              => 0,
        'livcom_plugin_frequency_min'        => 4,
        'livcom_plugin_frequency_max'        => 8,
        'livcom_ai_prefix'                   => 'off',
        'livcom_email_domain_option'         => 'default',
        'livcom_user_priority'               => 'off',
        'livcom_cron_status'                 => 'Paused',
        'livcom_website_category'            => array(
            'News', 'Entertainment', 'Lifestyle', 'Technology', 'Finance', 'Sports', 'Travel', 'Automotive', 'Health', 'Food and Drink', 'Business', 'Education', 'Gaming', 'Home and Garden', 'Law', 'Real Estate', 'Science', 'Other'
        ),
        'livcom_plugin_tones_selected'       => array(
            "Positive", "Supportive", "Enthusiastic", "Empowering", "Appreciative", "Joyful", "Inspirational", "Encouraging", "Optimistic", "Respectful", "Sympathetic", "Sincere", "Caring", "Funny", "Witty", "Ecstatic", "Surprised", "Confident", "Engaging", "Fascinating", "Lighthearted", "Genuine", "Thoughtful", "Playful", "Intriguing", "Neutral", "Casual", "Curious", "Formal", "Informal", "Informative", "Worried", "Opinionated", "Judgmental", "Assertive"
        ),
        'livcom_plugin_possible_tones'       => array(
            "Positive", "Supportive", "Enthusiastic", "Empowering", "Appreciative", "Joyful", "Inspirational", "Encouraging", "Optimistic", "Respectful", "Sympathetic", "Sincere", "Caring", "Funny", "Witty", "Ecstatic", "Surprised", "Confident", "Engaging", "Fascinating", "Lighthearted", "Genuine", "Thoughtful", "Playful", "Intriguing", "Neutral", "Casual", "Curious", "Formal", "Informal", "Informative", "Worried", "Opinionated", "Judgmental", "Assertive", "Sarcastic", "Frustrated", "Exaggerated", "Disapproving", "Resentful", "Critical", "Controversial", "Toxic"
        ),
        'livcom_plugin_tones_icons'          => array(
            'Positive' => 'las la-smile',
            'Supportive' => 'las la-hands-helping',
            'Enthusiastic' => 'las la-grin-alt',
            'Empowering' => 'las la-fist-raised',
            'Appreciative' => 'las la-heart',
            'Joyful' => 'las la-grin-beam',
            'Inspirational' => 'las la-lightbulb',
            'Encouraging' => 'las la-thumbs-up',
            'Optimistic' => 'las la-smile-wink',
            'Respectful' => 'las la-handshake',
            'Sympathetic' => 'las la-praying-hands',
            'Sincere' => 'las la-hand-holding-heart',
            'Caring' => 'las la-people-carry',
            'Funny' => 'las la-grin-squint-tears',
            'Witty' => 'las la-coffee',
            'Ecstatic' => 'las la-grin-stars',
            'Surprised' => 'las la-surprise',
            'Confident' => 'las la-smile-beam',
            'Engaging' => 'las la-users',
            'Fascinating' => 'las la-binoculars',
            'Lighthearted' => 'las la-laugh-squint',
            'Genuine' => 'las la-apple-alt',
            'Thoughtful' => 'las la-brain',
            'Playful' => 'las la-paper-plane',
            'Intriguing' => 'las la-mask',
            'Neutral' => 'las la-meh',
            'Casual' => 'las la-glass-martini',
            'Curious' => 'las la-question',
            'Formal' => 'lab la-black-tie',
            'Informal' => 'las la-tshirt',
            'Informative' => 'las la-graduation-cap',
            'Worried' => 'las la-flushed',
            'Opinionated' => 'las la-comment-dots',
            'Judgmental' => 'las la-gavel',
            'Assertive' => 'las la-hand-rock',
            'Sarcastic' => 'las la-grin-wink',
            'Frustrated' => 'las la-grin-beam-sweat',
            'Exaggerated' => 'las la-volume-up',
            'Disapproving' => 'las la-thumbs-down',
            'Resentful' => 'las la-grimace',
            'Critical' => 'las la-exclamation-triangle',
            'Controversial' => 'las la-fire-alt',
            'Toxic' => 'las la-skull-crossbones',
        )
    );
	
    // Define the plans
    $lc_plans = array(
        1 => array(
            'name' => 'Lite 300',
            'price' => 15
        ),
        2 => array(
            'name' => 'Standard 900',
            'price' => 36
        ),
        3 => array(
            'name' => 'Gold 3000',
            'price' => 90
        ),
        4 => array(
            'name' => 'Elite 9000',
            'price' => 180
        )
    );

    // Set default options if not already set
    foreach ( $defaults as $option => $value ) {
        if ( ! get_option( $option ) ) {
            update_option( $option, $value );
        }
    }

    // Add livcom_plans option
    if ( ! get_option( 'livcom_plans' ) ) {
        update_option( 'livcom_plans', $lc_plans );
    }

    // Set default options if not already set
    foreach ( $defaults as $option => $value ) {
        if ( ! get_option( $option ) ) {
            update_option( $option, $value );
        }
    }

    // Handle special case for 'livcom_plugin_frequency'
    if ( ! get_option( 'livcom_plugin_frequency' ) ) {
        update_option( 'livcom_plugin_frequency', livcom_generate_random_frequency() );
    }

    // Initialize the cron job
    livcom_init_cron_job();

    // Initialize livcom_stats_past_week
    livcom_initialize_stats();
}

/**
 * Schedules the cron job if not already scheduled and status is Running
 */
function livcom_init_cron_job() {
    if ( 'Running' === get_option( 'livcom_cron_status' ) && ! wp_next_scheduled( 'livcom_cron_job' ) ) {
        wp_schedule_event( time(), 'livcom_random_interval', 'livcom_cron_job' );
    }
}

/**
 * Initializes livcom_stats_past_week with default values
 */
function livcom_initialize_stats() {
    $stats = get_option( 'livcom_stats_past_week' );

    // Check if the option exists and has the correct number of entries
    if ( false === $stats || ! is_array( $stats ) || count( $stats ) < 168 ) {
        $new_stats = array();
        $current_time = current_time( 'mysql' );

        for ( $i = 0; $i < 168; $i++ ) {
            $timestamp = gmdate( 'Y-m-d H:i:s', strtotime( $current_time . " -" . ( 167 - $i ) . " hours" ) );
            $new_stats[$i] = array( 'timestamp' => $timestamp, 'num_comments' => 0, 'num_replies' => 0 );
        }

        // Update or add the option as needed
        update_option( 'livcom_stats_past_week', $new_stats, '', 'no' );
    }
}

/**
 * Handles deactivation procedures for the plugin.
 */
function livcom_deactivate() {
    error_log( 'Living Comments Plugin: deactivated.' );

    // Unscheduling livcom_cron_job
    $timestamp_cron_job = wp_next_scheduled( 'livcom_cron_job' );
    if ( false !== $timestamp_cron_job ) {
        wp_unschedule_event( $timestamp_cron_job, 'livcom_cron_job' );
    }

    // Unscheduling livcom_cron_check_event
    $timestamp_cron_check = wp_next_scheduled( 'livcom_cron_check_event' );
    if ( false !== $timestamp_cron_check ) {
        wp_unschedule_event( $timestamp_cron_check, 'livcom_cron_check_event' );
    }
}
