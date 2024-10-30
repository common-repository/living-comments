<?php
// If uninstall.php is not called by WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

// List of options to remove
$options_to_remove = array(
    'livcom_plugin_word_length',
    'livcom_allow_emoticons',
    'livcom_plugin_comment_reply_ratio',
    'livcom_allocation',
    'livcom_num_posts',
    'livcom_latest_num_rep',
    'livcom_latest_num_com',
    'livcom_plugin_frequency_min',
    'livcom_plugin_frequency_max',
    'livcom_plugin_frequency',
    'livcom_ai_prefix',
    'livcom_email_domain_option',
    'livcom_user_priority',
    'livcom_cron_status',
    'livcom_website_category',
    'livcom_plugin_possible_tones',
    'livcom_plugin_tones_icons',
    'livcom_plugin_tones_selected',
    'livcom_stats_past_week',
    'livcom_plugin_category_selected',
    'livcom_plugin_custom_words',
    'livcom_chart_last_updated',
    'livcom_user_subs'
);

// Remove each option from the options table
foreach ( $options_to_remove as $option ) {
    delete_option( $option );
    // For site options in Multisite
    delete_site_option( $option );
}

// Clear any scheduled events related to the plugin
wp_clear_scheduled_hook( 'livcom_cron_check_event' );
wp_clear_scheduled_hook( 'livcom_cron_job' );

?>