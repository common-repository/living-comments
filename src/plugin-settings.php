<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Settings page for the LC plugin.
 */
function livcom_plugin_settings_page() {
    // Check user permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
    }

    // Fetch categories
    $categories = get_categories();

    // Process selected categories
    $category_selected = get_option( 'livcom_plugin_category_selected', [] );
    $category_selected = is_array( $category_selected ) ? array_map( 'absint', $category_selected ) : [];
    if ( empty( $category_selected ) ) {
        $category_selected = array_map( function( $category ) {
            return absint( $category->term_id );
        }, $categories );
    }

    // Process possible tones
    $possible_tones = get_option( 'livcom_plugin_possible_tones', [] );
    $possible_tones = is_array( $possible_tones ) ? array_map( 'sanitize_text_field', $possible_tones ) : [];

    // Process selected tones
    $tones_selected = get_option( 'livcom_plugin_tones_selected', [] );
    $tones_selected = is_array( $tones_selected ) ? array_map( 'sanitize_text_field', $tones_selected ) : [];
    if ( empty( $tones_selected ) ) {
        $tones_selected = $possible_tones;
    }

    // Frequency settings
    $frequency_min = absint( get_option( 'livcom_plugin_frequency_min', 4 ) );
    $frequency_max = absint( get_option( 'livcom_plugin_frequency_max', 10 ) );
    $frequency = absint( get_option( 'livcom_plugin_frequency', 4 ) );

    // Custom words processing
    $custom_words = get_option( 'livcom_plugin_custom_words', [] );
    $custom_words = is_array( $custom_words ) ? array_map( 'sanitize_text_field', $custom_words ) : [];
	
	// Fetch user plan
	$plan = get_option( 'livcom_user_plan', 'Basic 15' );

    // Fetch user balance
    $user_balance = livcom_fetch_user_balance();

    // Cron job status and scheduling
    $lc_cron_status = sanitize_text_field( get_option( 'livcom_cron_status' ) );
    if ( $lc_cron_status === 'Running' && ! wp_next_scheduled( 'livcom_cron_job' ) && $user_balance > 0 ) {
        wp_schedule_event( time(), 'livcom_random_interval', 'livcom_cron_job' );
    }

    // URLs for cron actions
    $pause_url = esc_url_raw( admin_url( 'admin-post.php?action=livcom_pause_cron_job' ) );
    $nonce_pause_url = wp_nonce_url( $pause_url, 'livcom_pause_cron_job_action' );

    $resume_url = esc_url_raw( admin_url( 'admin-post.php?action=livcom_resume_cron_job' ) );
    $nonce_resume_url = wp_nonce_url( $resume_url, 'livcom_resume_cron_job_action' );

    // Other settings
    $lc_num_posts = absint( get_option( 'livcom_num_posts', 5 ) );
    $lc_latest_num_rep = absint( get_option( 'livcom_latest_num_rep', 0 ) );
    $lc_latest_num_com = absint( get_option( 'livcom_latest_num_com', 0 ) );

    $latestStatsUpdateTime = sanitize_text_field( get_option( 'livcom_chart_last_updated', current_time( 'mysql' ) ) );
    $latestDate = strtotime( $latestStatsUpdateTime );

    // API and user details
    $user_id = get_option( 'livcom_user_id', false );
    $lc_api_key = sanitize_text_field( get_user_meta( $user_id, 'livcom_api_key', true ) );
    $lc_uid = sanitize_text_field( get_user_meta( $user_id, 'livcom_uid', true ) );

    // Subscriptions processing
    $lc_user_subs = get_option( 'livcom_user_subs' );
    $subscriptions = maybe_unserialize( $lc_user_subs );
    $subscriptions = is_array( $subscriptions ) ? $subscriptions : [];

    $hasPastDueSubscription = false;
    foreach ( $subscriptions as $subscription ) {
        if ( isset( $subscription['status'] ) && sanitize_text_field( $subscription['status'] ) == 'Past Due' ) {
            $hasPastDueSubscription = true;
            break;
        }
    }

    // Helper function for plan class
    /**
     * Get CSS class for a plan.
     */
    function livcom_get_plan_class( $plan ) {
        $plan = sanitize_text_field( $plan );
        switch ( $plan ) {
            case "Lite 300":
                return "tag is-size-6 is-success";
            case "Standard 900":
                return "tag is-size-6 is-info";
            case "Gold 3000":
                return "tag is-size-6 is-gold";
            case "Elite 9000":
                return "tag is-size-6 is-black";
            default:
                return "tag is-size-6 is-link is-light";
        }
    }
	/**
	 * Retrieves detailed data for a given comment.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @param array      $id      Comment details including ID and reported status.
	 * @param array      $livcom_plugin_tones_icons Array of tone icons.
	 *
	 * @return string HTML string of the comment details.
	 */
	function livcom_get_comment_details( $comment, $id, $lc_plugin_tones_icons ) {
		$comment_link = get_comment_link( $comment );
		$author_name = esc_html( $comment->comment_author );
		$content = wp_kses_post( $comment->comment_content );
		$datetime = gmdate( 'F d, Y g:i a', strtotime( $comment->comment_date ) );
		$post = get_post( $comment->comment_post_ID );
		$comment_id = 'comment-id-' . esc_attr($id['id']);

		$post_title = ( ! $post || is_wp_error( $post ) ) ? "Post not found" : esc_html( $post->post_title );

		$lc_tone = esc_html( get_comment_meta( $id['id'], 'livcom_tone', true ) );
		$lc_word_length = intval( get_comment_meta( $id['id'], 'livcom_word_length', true ) );
		$lc_tone_icon = isset( $lc_plugin_tones_icons[$lc_tone] ) ? esc_attr( $lc_plugin_tones_icons[$lc_tone] ) : '';

		$is_reply = ( $comment->comment_parent != 0 );
		$parent_author = $is_reply ? esc_html( get_comment( $comment->comment_parent )->comment_author ) : null;

		// Building the HTML string
		$html = '<tr>';
		$html .= '<td scope="row" class="is-fullwidth has-text-weight-bold">' . $author_name . '</td>';
		$html .= '<td class="has-text-centered"><span class="icon"><i class="' . $lc_tone_icon . ' is-size-4"></i></span><p class="is-size-7">' . $lc_tone . '</p></td>';
		$html .= '<td class="has-text-centered">' . livcom_get_word_length( $lc_word_length ) . '</td>';
		$html .= '<td>';
		$html .= $is_reply ? '<p class="has-text-weight-bold"><i class="las la-reply is-size-6"></i> In reply to ' . $parent_author . ' on Post: ' . $post_title . ' <a href="' . esc_url( $comment_link ) . '" target="_blank"><i class="las la-external-link-alt is-size-5"></i></a></p>' : '<p class="has-text-weight-bold">On Post: ' . $post_title . ' <a href="' . esc_url( $comment_link ) . '" target="_blank"><i class="las la-external-link-alt is-size-5"></i></a></p>';
		$html .= '<p class="is-size-6">' . $content . '</p><p class="is-size-7 has-text-grey">Posted on ' . $datetime . '</p></td>';
		$html .= '<td><button class="button is-small delete-comment-btn ' . $comment_id . '" type="button"><i class="las la-trash-alt is-size-4 has-text-grey"></i></button>';
		$html .= $id['reported'] === false ? '<button class="button is-small unhappy-comment-btn ' . $comment_id . '" type="button"><i class="las la-frown is-size-4 has-text-grey"></i></button>' : '<button class="button is-small disabled" type="button" id="unhappyReported"><i class="las la-check is-size-4 has-text-success"></i></button>';
		$html .= '</td></tr>';

		return $html;
	}

	/**
	 * Generates HTML for displaying word length.
	 *
	 * @param int $lc_word_length Length of the word.
	 *
	 * @return string HTML string for word length indication.
	 */
	function livcom_get_word_length( $lc_word_length ) {
		$html = '';
		for ( $i = 0; $i < $lc_word_length; $i++ ) {
			$class = '';
			switch ( $lc_word_length ) {
				case 1:
					$class = 'has-text-primary';
					break;
				case 2:
					$class = 'has-text-custom-medium';
					break;
				case 3:
					$class = 'has-text-danger';
					break;
			}
			$html .= '<i class="las la-square is-size-7 ' . $class . '"></i>';
		}
		return $html;
	}
	
	/**
	 * Displays a section when no subscriptions are present.
	 */
	function livcom_display_no_subscriptions_section() {
		echo '<section class="hero is-primary mb-3">';
		echo '<div class="hero-body">';
		echo '<div class="columns is-vcentered">';
		echo '<div class="column is-narrow">';
		echo '<p class="title is-size-1"><i class="las la-exclamation-circle"></i></p>';
		echo '</div>';
		echo '<div class="column">';
		echo '<p class="subtitle is-size-5">You have no subscriptions. If you need more comments, kindly proceed to the Account Overview tab to Change Plan.</p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Displays a single subscription.
	 * @param array $subscription Subscription details.
	 */
	function livcom_display_single_subscription( $subscription ) {
		$class = livcom_get_subscription_class( $subscription['plan_id'] );
		$plan = livcom_get_plan_name( $subscription['plan_id'] );

		echo '<section class="hero ' . esc_attr( $class ) . ' mb-3">';
		echo '<div class="hero-body">';
		echo '<p class="subtitle is-size-6 mb-5">Your active subscription</p>';
		echo '<div class="title d-flex align-items-center mb-5">';
		echo '<span class="mr-6">' . esc_html( $plan ) . '</span>';
		echo '<button type="button" class="button is-outlined ml-6 is-small has-background-grey-lighter p-0 pl-2 pr-2 has-text-grey" id="cancel-subscription" data-subscription-id="' . esc_attr( $subscription['subscription_id'] ) . '" data-target="#cancel-subscription-modal" aria-haspopup="true"><span>Cancel Subscription</span><span class="icon is-small"><i class="las la-times"></i></span></button>';
		echo '</div>';
		echo '<p class="subtitle is-size-6">Renews on: ' . esc_html( gmdate( 'F d, Y', strtotime( $subscription['end_date'] ) ) ) . '</p>';
		if ( $subscription['status'] == 'Past Due' ) {
			echo '<div class="notification is-warning is-light mt-4 p-3">';
			echo '<div class="is-flex is-align-items-center">';
			echo '<span class="icon"><i class="las la-exclamation is-size-3 mr-2"></i></span>';
			echo '<p class="is-size-6"><strong>Subscription renewal failed.</strong><br>We\'ll try to renew your subscription 3 more times, every 5 days from the renewal date listed above. If we can\'t, your subscription will end. You can subscribe to a new plan anytime in the \'Account Overview\' tab and we\'ll cancel your current subscription for you.</p>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Displays all subscriptions in a table.
	 * @param array $subscriptions Array of subscription details.
	 */
	function livcom_display_subscriptions_table( $subscriptions ) {
		$activeSubscriptionFound = false;

		foreach ( $subscriptions as $subscription ) {
			if ( $subscription['status'] == 'Active' || $subscription['status'] == 'Past Due' ) {
				$activeSubscriptionFound = true;
				break;
			}
		}

		if ( !$activeSubscriptionFound ) {
			echo '<section class="hero is-primary mb-3">';
			echo '<div class="hero-body">';
			echo '<div class="columns is-vcentered">';
			echo '<div class="column is-narrow">';
			echo '<p class="title is-size-1"><i class="las la-exclamation-circle"></i></p>';
			echo '</div>';
			echo '<div class="column">';
			echo '<p class="subtitle is-size-5">You have no active subscriptions. If you need more comments, kindly proceed to the Account Overview tab to Change Plan.</p>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			echo '</section>';
		}

		// Display billing table
		echo '<table id="billing-table" class="table is-fullwidth is-bordered is-striped">';
		echo '<tr><th>Subscription ID</th><th>Plan</th><th>Status</th><th>Last Updated</th></tr>';

		foreach ( $subscriptions as $subscription ) {
			$plan = livcom_get_plan_name( $subscription['plan_id'] );

			echo '<tr>';
			echo '<td>' . esc_html( $subscription['subscription_id'] ) . '</td>';
			echo '<td>' . esc_html( $plan ) . '</td>';
			echo '<td>' . esc_html( $subscription['status'] ) . '</td>';
			echo '<td>' . esc_html( gmdate( 'F d, Y', strtotime( $subscription['updated_at'] ) ) ) . '</td>';
			echo '</tr>';
		}

		echo '</table>';
	}

	/**
	 * Retrieves the CSS class for a subscription based on its plan ID.
	 * @param int $plan_id The plan ID.
	 * @return string The corresponding CSS class.
	 */
	function livcom_get_subscription_class( $plan_id ) {
		switch ( $plan_id ) {
			case 1: return 'is-success';
			case 2: return 'is-info';
			case 3: return 'is-gold';
			case 4: return 'is-black';
			default: return 'is-info';
		}
	}

	/**
	 * Converts a plan ID to a readable plan name.
	 * @param int $plan_id The plan ID.
	 * @return string The plan name.
	 */
	function livcom_get_plan_name( $plan_id ) {
		switch ( $plan_id ) {
			case 1: return 'Lite 300';
			case 2: return 'Standard 900';
			case 3: return 'Gold 3000';
			case 4: return 'Elite 9000';
			default: return 'Basic 15';
		}
	}
	
	/**
	 * Load FAQ data from the JSON file.
	 * 
	 * @return array Array of FAQs or an empty array if file not found.
	 */
	function livcom_load_faq_data() {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			WP_Filesystem();
		}

		$json_file = plugin_dir_path( __FILE__ ) . '../json/faq.json';
		if ( $wp_filesystem->exists( $json_file ) ) {
			$jsonData = $wp_filesystem->get_contents( $json_file );
			return json_decode( $jsonData, true );
		}

		return array(); // Handle the case where the file does not exist.
	}

	/**
	 * Render FAQ table.
	 * 
	 * @param array $faqContent Array of FAQs.
	 */
	function livcom_render_faq_table( $faqContent ) {
		?>
			<div class="mb-1" style="display: flex; justify-content: space-between; align-items: center;">
				<span class="is-size-5 has-text-weight-semibold">Have a question about Living Comments? We're here to help with your most common questions answered.</span>
				<a href="javascript:void(0)" id="toggleAll" class="expand-collapse-link has-text-weight-bold">Expand All <i class="las la-expand"></i></a>
			</div>
			<div class="faq-container">
				<table id="faq-table" class="table is-fullwidth is-bordered is-striped is-hoverable">
					<?php
					$index = 0;
					foreach ( $faqContent as $item ) {
						if ( $index % 2 === 0 ) {
							echo '<tr>';
						}

						echo '<th scope="row">';
						echo '<div class="columns is-vcentered">';
						echo '<div class="column">';
						echo '<div class="faq-question is-flex" onclick="toggleContent(' . esc_js($index) . ')">';
						echo '<div class="faq-question-text has-text-left is-size-6 has-text-weight-medium py-2">' . esc_html($item['question']) . '</div>';
						echo '<div class="faq-chevron ml-auto is-flex is-align-items-center" id="faq-chevron-' . esc_attr($index) . '">';
						echo '<i class="las la-angle-down la-lg"></i>';
						echo '</div>';
						echo '</div>';
						echo '<div class="faq-answer is-hidden">';
						$allowed_html = wp_kses_allowed_html('post');
						$answerContent = wpautop(wp_kses($item['answer'], $allowed_html));
						$answerContentWithClasses = str_replace('<p>', '<p class="is-size-6 has-text-weight-normal has-text-left">', $answerContent);
						echo wp_kses_post( $answerContentWithClasses );
						echo '</div>';
						echo '</div>';
						echo '</div>';
						echo '</th>';

						if ( $index % 2 === 1 || $index === count($faqContent) - 1 ) {
							echo '</tr>';
						}

						$index++;
					}
					?>
				</table>
			</div>
		<?php
	}

    ?>
<!-- Form for plugin settings -->
<form method="post" action="options.php" id="lc-plugin-settings">
    <?php
    settings_fields( 'livcom_plugin_options_group' );
    do_settings_sections( 'livcom_plugin_options_group' );
	wp_nonce_field( 'livcom_settings_update', 'livcom_settings_nonce' );
    ?>

    <div class="living-comments-plugin wrap is-mobile is-tablet is-desktop is-widescreen is-fullhd">
        <!-- Plugin Header -->
        <div class="is-flex is-align-items-center">
            <figure class="media-left">
                <p class="image is-96x96 is-flex is-align-items-center is-justify-content-center">
                    <img src="<?php echo esc_url( plugins_url( '../svg/living-comments-logo.svg', __FILE__ ) ); ?>" alt="Living Comments Logo">
                </p>
            </figure>
            <h1 class="title is-2">Living Comments</h1>
        </div>

        <!-- Tabs Navigation -->
        <div class="tabs">
            <ul>
                <li id="overview-tab"><a>Account Overview</a></li>
                <li id="settings-tab"><a>Comment/Reply</a></li>
                <li id="user-tab"><a>User Management</a></li>
                <li id="history-tab"><a>Comment History</a></li>
                <li id="billing-tab">
                    <a>
                        <?php
                        if ( $hasPastDueSubscription ) {
                            echo '<span class="dashicons dashicons-warning mr-1 mt-1 is-size-5"></span>';
                        }
                        ?> Billing
                    </a>
                </li>
                <li id="faq-tab"><a>FAQ</a></li>
            </ul>
        </div>

        <?php
        // Display admin notices
        livcom_plugin_admin_notices();
        ?>
  
		<div class="tab-content">
			<!-- Overview Tab Content -->
			<div id="overview-tab-content" class="tab-pane is-active">
				<table id="overview-table" class="table is-fullwidth is-bordered">
					<tr>
						<th scope="row">Unique ID</th>
						<td class="p-2">
							<?php
							$lc_uid = str_replace( '_', '-', $lc_uid );
							$site_url = esc_url( get_site_url() );
							if ( empty( $lc_uid ) ): 
							?>
							<div class="is-flex is-align-items-center">
								<button id="setupButton" class="button is-primary has-background-primary-dark py-0" type="button">
									<i class="las la-power-off la-lg has-text-weight-bold mr-1"></i> Get Started
								</button>
							</div>

							<!-- Get Started Modal -->
							<div id="modal" class="modal">
								<div class="modal-background"></div>
								<div class="modal-content">
									<div class="box">
										<h2 class="is-size-4 mb-2">Get Started With Living Comments!</h2>
										<p class="subtitle is-6">Bring your blog to life with Living Comments. Our advanced AI plugin crafts context-aware comments and replies, fueling engaging and meaningful conversations. Watch as it builds a lively community, elevating the quality of your content and boosting user interaction. Sign up today and enjoy 15 free comments on us!</p>
										<div class="field">
											<label class="label">Domain URL</label>
											<div class="control">
												<span><?php echo esc_url( $site_url ); ?></span>
											</div>
										</div>
										<div class="field">
											<label class="label">Country</label>
											<div class="control">
												<div class="select">
													<select id="countrySelect" name="country">
														<?php
														global $wp_filesystem;
														
														// Initialize the WordPress Filesystem API.
														if ( ! $wp_filesystem ) {
															require_once( ABSPATH . 'wp-admin/includes/file.php' );
															WP_Filesystem();
														}

														$json_file = plugin_dir_path( __FILE__ ) . '../json/countries.json';

														if ( $wp_filesystem->exists( $json_file ) ) {
															$json = $wp_filesystem->get_contents( $json_file );
															$countries = json_decode( $json, true );
															$default_country = 'US';

															foreach ( $countries as $code => $name ) :
														?>
															<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $code, $default_country ); ?>>
																<?php echo esc_html( $name ); ?>
															</option>
														<?php
															endforeach;
														} else {
															// Error handling for unavailable country data
															echo '<option value="">Countries data not available</option>';
														}
														?>
													</select>
												</div>
											</div>
										</div>
									<!-- Website Category Dropdown -->
									<div class="field">
										<label class="label">Website Category</label>
										<div class="control">
											<div class="select">
												<select id="websiteCategory" name="website_category">
													<?php
													$web_categories = get_option( 'livcom_website_category' );
													foreach ( $web_categories as $web_category ) :
													?>
														<option value="<?php echo esc_attr( $web_category ); ?>"><?php echo esc_html( $web_category ); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										</div>
									</div>
									<!-- End of Website Category Dropdown -->

									<!-- Administrator Dropdown -->
									<div class="field">
										<label class="label">Select Administrator</label>
										<div class="control">
											<div class="select">
												<select id="administratorSelect" name="administrator">
													<?php
													$args = array(
														'role' => 'Administrator',
													);
													$users = get_users( $args );
													foreach ( $users as $user ) :
													?>
														<option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										</div>
										<p class="help">This account is used to safeguard your API key and schedule tasks.</p>
									</div>
									<!-- End of Administrator Dropdown -->
									<!-- Email Input Field -->
									<div class="field">
										<label class="label">Your Email*</label>
										<div class="control">
											<input class="input" type="email" id="email" name="email" placeholder="Enter a valid email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
										</div>
									</div>

									<!-- Newsletter Checkbox -->
									<div class="field">
										<div class="control">
											<label class="checkbox help">
												<input type="checkbox" id="newsletter" name="newsletter">
												Subscribe to our newsletter (optional)
											</label>
										</div>
									</div>

									<!-- Terms and Conditions Note -->
									<div class="field">
										<p class="help">
											By signing up for an account, you accept our 
											<a href="https://www.livingcomments.com/terms" target="_blank">Terms and Conditions</a> 
											and 
											<a href="https://www.livingcomments.com/privacy" target="_blank">Privacy Policy</a>.
										</p>
									</div>

									<!-- Action Buttons -->
									<div class="field is-grouped">
										<div class="control">
											<button class="button is-primary" id="signupButton">Sign Up</button>
										</div>
										<div class="control">
											<button class="button is-light" id="cancelButton">Cancel</button>
										</div>
									</div>

									<!-- Modal Close Button -->
									<button class="modal-close is-large" aria-label="close" type="button"></button>
									</div>
								</div>
							</div>

									<?php else: ?>
										<span><?php echo esc_html( $lc_uid ); ?></span>
									<?php endif; ?>
						</td>
				    </tr>
					<tr>
						<th scope="row">Status</th>
						<td class="p-2" style="display: flex; align-items: center; border:none;">
							<?php
							if ( ! $lc_uid ) {
								echo '<i class="las la-hand-point-up mr-1 is-size-4"></i> Welcome Bonus: <span class="has-text-weight-bold mx-1">15 FREE COMMENTS</span> after signing up.';
							} elseif ( ! $lc_api_key ) {
								echo '<span class="status-indicator red"></span>';
								echo 'API key not found. Please contact support.';
							} elseif ( $user_balance === 'API down' ) {
								echo '<span class="status-indicator red"></span>';
								echo 'Living Comments API Outage';
							} elseif ( $lc_cron_status === 'Running' && $user_balance > 0 ) {
								echo '<span class="status-indicator green"></span>';
								echo '<span class="ml-1">' . esc_html( $lc_cron_status ) . '</span>';
								echo '<span class="loading-dots"><span>.</span><span>.</span><span>.</span></span>';
								echo '<a class="control-link is-flex is-align-items-center ml-4" href="' . esc_url( $nonce_pause_url ) . '">';
								echo '<span class="icon mr-1"><i class="las la-pause la-lg has-text-dark has-text-weight-semibold"></i></span>';
								echo '<span class="has-text-dark is-size-7 has-text-weight-semibold">Pause</span>';
								echo '</a>';
							} elseif ( $lc_cron_status === 'Paused' && $user_balance > 0 ) {
								echo '<span class="status-indicator orange"></span>';
								echo '<span class="ml-1">' . esc_html( $lc_cron_status ) . '</span>';
								echo '<a class="control-link is-flex is-align-items-center ml-4" href="' . esc_url( $nonce_resume_url ) . '">';
								echo '<span class="icon mr-1"><i class="las la-step-forward la-lg has-text-dark has-text-weight-semibold"></i></span>';
								echo '<span class="has-text-dark is-size-7 has-text-weight-semibold">Run</span>';
								echo '</a>';
							} elseif ( $plan === 'Basic 15' && $user_balance == 0 ) {
								echo '<span class="status-indicator gray"></span>';
								echo '<span class="ml-1 mr-3">' . esc_html( $lc_cron_status ) . '</span>';
								echo '<span class="has-text-danger">(Out of Credits - Upgrade Plan)</span>';
							} elseif ( $user_balance == 0 ) {
								echo '<span class="status-indicator gray"></span>';
								echo '<span class="ml-1 mr-3">' . esc_html( $lc_cron_status ) . '</span>';
								echo '<span class="has-text-danger">(Out of Credits - See Billing Tab)</span>';
							} else {
								echo '<span class="status-indicator gray"></span>';
								echo '<span class="ml-1 mr-3">' . esc_html( $lc_cron_status ) . '</span>';
								echo '<span class="has-text-grey">(Possible Server Downtime)</span>';
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row">Your Plan</th>
						<td class="p-2">
							<div class="is-flex is-align-items-center">
								<?php
								$class = livcom_get_plan_class( $plan );
								printf( '<span class="%s">%s</span>', esc_attr( $class ), esc_html( $plan ) );
								?>

								<?php if ( $user_id && get_user_meta( $user_id, 'livcom_uid', true ) ) : ?>
									<div class="dropdown is-hoverable ml-3">
										<div class="dropdown-trigger">
											<button class="button is-small is-primary is-rounded has-background-primary-dark" aria-haspopup="true" aria-controls="plan-selection-dropdown" type="button">
												<span class="is-size-6 has-text-weight-semibold">Change Plan <i class="las la-angle-down"></i></span>
											</button>
										</div>
										<div class="dropdown-menu" id="plan-selection-dropdown" role="menu">
											<div class="dropdown-content">
												<!-- Plan Options -->
												<a href="#" class="dropdown-item is-size-6" data-plan-id="1" data-plan-description="300" data-container-id="paypal-button-container-P-6SM84032FL413744GMU2ZSBQ">
													<span class="tag is-size-6 is-success">Lite 300</span> <small>(300 comments per month)</small>
												</a>
												<a href="#" class="dropdown-item is-size-6" data-plan-id="2" data-plan-description="900" data-container-id="paypal-button-container-P-7A3380872Y505124CMU25FAA">
													<span class="tag is-size-6 is-info">Standard 900</span> <small>(900 comments per month)</small>
												</a>
												<a href="#" class="dropdown-item is-size-6" data-plan-id="3" data-plan-description="3000" data-container-id="paypal-button-container-P-650807610V687564PMU25F6Q">
													<span class="tag is-size-6 is-gold">Gold 3000</span> <small>(3000 comments per month)</small>
												</a>
												<a href="#" class="dropdown-item is-size-6" data-plan-id="4" data-plan-description="9000" data-container-id="paypal-button-container-P-4US3981716125154JMU25GNY">
													<span class="tag is-size-6 is-black">Elite 9000</span> <small>(9000 comments per month)</small>
												</a>
											</div>
										</div>
									</div>
								<?php endif; ?>
							</div>
							<!-- Modal for Change Plan -->
							<div class="modal" id="change-plan">
								<div class="modal-background"></div>
								<div class="modal-card">
									<header class="modal-card-head">
										<p class="modal-card-title"><i class="las la-shopping-cart"></i> Checkout</p>
										<button id="payments-close" class="delete" type="button" aria-label="close"></button>
									</header>
									<section class="modal-card-body">
										<!-- Current and New Plan Display -->
										<div class="columns p-0 m-0">
											<!-- Current Plan Display -->
											<div class="column is-half p-1">
												<div class="field is-horizontal is-flex is-align-items-flex-start">
													<label class="label mb-0 is-size-7">Current Plan:</label>
														<?php
														$class = livcom_get_plan_class( $plan );
														echo '<div class="current-plan-content">';
														printf( '<span class="%s is-size-7 ml-2 mt-0">%s</span>', esc_attr( $class ), esc_html( $plan ) );

														if ( $plan === 'Basic 15' ) {
															printf( '<span class="help ml-2">%s</span>', 'The initial 15 credits under the Basic 15 plan are a one-time allocation and do not renew monthly. Need more credits? Upgrade now!' );
														} else {
															printf( '<span class="help ml-2">%s</span>', 'Your current plan and subscription will be cancelled and replaced with the new plan.' );
														}

														echo '</div>';
														?>
												</div>
											</div>

											<!-- New Plan Display -->
											<div class="column is-half p-1 has-background-white-ter">
												<div class="columns">
													<div class="column is-narrow">
														<label class="label mb-0 is-size-7">New Plan:</label>
														<div class="has-text-centered">
															<i class="las la-angle-double-right mt-6 is-size-4"></i>
														</div>
													</div>
													<div class="column">
														<div id="selected-plan" class="ml-2">
															<p id="selected-billing-cycle" class="help mt-0"></p>
															<span id="selected-plan-name" class="is-size-6 has-text-weight-semibold">No plan selected yet</span>
															<p id="selected-plan-description" class="is-size-6"></p>
															<p id="selected-plan-price" class="is-size-6 has-text-weight-semibold"></p>
															<p id="selected-plan-cpc" class="help is-size-7"></p>
															<p class="help is-size-7 m-0">Cancel anytime within your Billing tab.</p>
														</div>
													</div>
												</div>
											</div>
										</div>
										<!-- PayPal Button Containers -->
										<div class="columns is-multiline is-flex is-justify-content-center mt-5">
											<div class="column is-full has-text-centered p-0">
												<div class="button-wrapper">
													<div id="paypal-button-container-P-6SM84032FL413744GMU2ZSBQ"></div>
												</div>
											</div>
											<div class="column is-full has-text-centered p-0">
												<div class="button-wrapper">
													<div id="paypal-button-container-P-7A3380872Y505124CMU25FAA"></div>
												</div>
											</div>
											<div class="column is-full has-text-centered p-0">
												<div class="button-wrapper">
													<div id="paypal-button-container-P-650807610V687564PMU25F6Q"></div>
												</div>
											</div>
											<div class="column is-full has-text-centered p-0">
												<div class="button-wrapper">
													<div id="paypal-button-container-P-4US3981716125154JMU25GNY"></div>
												</div>
											</div>
										</div>
										</section>

										<!-- Modal Footer -->
										<footer class="modal-card-foot">
											<div>
												<label id="terms-message" class="help mb-2 mt-0">
													By subscribing, you accept our 
													<a href="https://www.livingcomments.com/terms" target="_blank">Terms and Conditions</a> 
													and 
													<a href="https://www.livingcomments.com/privacy" target="_blank">Privacy Policy</a>. 
													Your subscription will automatically renew every 30 days. To stop the renewal, go to the Billing tab and cancel. 
													Note: We don't offer refunds.
												</label>
											</div>
										</footer>
										</div>
										</div>
										<!-- Success Modal -->
										<div class="modal" id="success-modal">
											<div class="modal-background"></div>
											<div class="modal-card">
												<header class="modal-card-head">
													<p class="modal-card-title"><i class="las la-check-circle"></i> Success</p>
												</header>
												<section class="modal-card-body">
													<p class="is-size-6">You've made a great choice! Please give us a moment to update your account, then hit the Refresh My Account button below. Thanks for your patience!</p>
													<div class="countdown-container">
														<i class="las la-circle-notch la-5x has-text-primary-dark" id="icon-spinner"></i>
														<div id="countdown" class="has-text-weight-bold is-size-5 has-text-primary-dark">60</div>
													</div>
												</section>
												<footer class="modal-card-foot">
													<button class="button is-primary" id="refresh-button" type="button">Refresh My Account</button>
												</footer>
											</div>
										</div>

										<!-- Error Modal -->
										<div class="modal" id="error-modal">
											<div class="modal-background"></div>
											<div class="modal-card">
												<header class="modal-card-head">
													<p class="modal-card-title"><i class="las la-exclamation-circle"></i> Something went wrong</p>
												</header>
												<section class="modal-card-body">
													<p class="is-size-6" id="error-message"><strong>Subscription creation failed:</strong> Please refresh the page and try again.</p>
												</section>
												<footer class="modal-card-foot">
													<button class="button is-primary" id="refresh-error-button" type="button">Refresh Page</button>
												</footer>
											</div>
										</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Credits</th>
						<td class="p-2">
							<?php
							if ( is_numeric( $user_balance ) ) {
								echo '<span class="is-size-6">' . esc_html( number_format( $user_balance, 0 ) ) . '</span>';
							} else {
								echo '<span class="is-size-6">N/A</span>';
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row">User Statistics<br><span class="help">(Updates hourly)</span></th>
						<td class="p-2">
							<p class="is-size-6 has-background-white-ter p-4">
								<strong>All time:</strong> 
								<span class="tag is-dark has-background-grey-lighter has-text-black has-text-weight-semibold"><?php echo esc_html( number_format( $lc_latest_num_com, 0 ) ); ?></span> 
								Comments / 
								<span class="tag is-dark has-background-grey-lighter has-text-black has-text-weight-semibold"><?php echo esc_html( number_format( $lc_latest_num_rep, 0 ) ); ?></span> 
								Replies / 
								<span class="tag is-dark has-background-grey-lighter has-text-black has-text-weight-semibold"><?php echo esc_html( number_format( $lc_latest_num_rep + $lc_latest_num_com, 0 ) ); ?></span> 
								Total Generated
							</p>
							<input type="hidden" name="livcom_latest_num_rep" value="<?php echo esc_attr( $lc_latest_num_rep ); ?>">
							<input type="hidden" name="livcom_latest_num_com" value="<?php echo esc_attr( $lc_latest_num_com ); ?>">
							<h3 class="is-size-5 mt-5"><i class="las la-calendar-week"></i> Your Weekly Report</h3>
							<div id="chart-container" class="box">
								<canvas id="lcStatsChart"></canvas>
							</div>
							<p class="p-0 mb-2 mt-5">
								<?php
								if ( ! empty( $latestStatsUpdateTime ) ) {
									echo '<strong>Last Updated:</strong> ' . esc_html( gmdate( 'F d, Y g:i a', $latestDate ) );
								} else {
									echo 'No updates yet.';
								}
								?>
								(This chart works based on your WordPress timezone settings and data may be delayed ~1 hour or longer.)
							</p>
						</td>
					</tr>
				</table>
			</div>
			<div id="settings-tab-content" class="tab-pane">
				<table id="settings-table" class="table is-fullwidth is-bordered">
					<!-- Settings Table Rows -->
					<tr>
						<th scope="row">Engagement</th>
						<td class="p-2">
							<?php
							$selected_plan = intval( get_option( 'livcom_allocation', 7 ) );
							$lc_num_posts = intval( get_option( 'livcom_num_posts', 5 ) );

							$plans = array(
								array('name' => '<i class="las la-stopwatch"></i> Timely', 'value' => 10, 'color' => 'Timely', 'description' => 'Post comments/replies randomly with a:<br><strong>100%</strong> chance in the latest <span class="latest-post-count"><strong>' . esc_html( $lc_num_posts ) . '</strong></span> posts/comments'),
								array('name' => '<i class="las la-seedling"></i> Natural', 'value' => 7, 'color' => 'Natural', 'description' => 'Post comments/replies randomly with a:<br><strong>70%</strong> chance in the latest <span class="latest-post-count"><strong>' . esc_html( $lc_num_posts ) . '</strong></span> posts/comments<br><strong>30%</strong> chance in any random post/comment'),
								array('name' => '<i class="las la-balance-scale"></i> Balanced', 'value' => 5, 'color' => 'Balanced', 'description' => 'Post comments/replies randomly with a:<br><strong>50%</strong> chance in the latest <span class="latest-post-count"><strong>' . esc_html( $lc_num_posts ) . '</strong></span> posts/comments<br><strong>50%</strong> chance in any random post/comment'),
								array('name' => '<i class="las la-sync"></i> Recycle', 'value' => 3, 'color' => 'Recycle', 'description' => 'Post comments/replies randomly with a:<br><strong>30%</strong> chance in the latest <span class="latest-post-count"><strong>' . esc_html( $lc_num_posts ) . '</strong></span> posts/comments<br><strong>70%</strong> chance in any random post/comment'),
								array('name' => '<i class="las la-layer-group"></i> Comprehensive', 'value' => 0, 'color' => 'Comprehensive', 'description' => 'Post comments/replies randomly with a:<br><strong>100%</strong> chance in any random post/comment')
							);

							echo '<div style="content">';
							echo '<div class="columns is-multiline is-mobile">';
							foreach ( $plans as $plan ) {
								$selected = ( $selected_plan === $plan['value'] ) ? 'selected-plan' : '';
								echo "<div class='column'><div class='plan-item " . esc_attr( $selected ) . " p-4 " . esc_attr( $plan['color'] ) . "' data-value='" . esc_attr( $plan['value'] ) . "'><strong class='plan-name'>" . wp_kses_post( $plan['name'] ) . "</strong><div class='dropdown-container'><small class='dropdown-text' style='display: none;'>Latest</small><div class='dropdown-placeholder'></div><small class='dropdown-post-comment' style='display: none;'>posts</small></div><div class='is-size-7'>" . wp_kses_post( $plan['description'] ) . "</div></div></div>";
							}
							echo '</div>';
							echo '</div>';
							echo "<select id='num-posts-dropdown' name='livcom_num_posts' style='display: none;'>";
							for ( $i = 3; $i <= 10; $i += 2 ) {
								if ( $i == 9 ) {
									$i = 10;
								}
								$selected = ( $lc_num_posts == $i ) ? 'selected' : '';
								echo "<option value='" . esc_attr( $i ) . "' " . esc_attr( $selected ) . ">" . esc_html( $i ) . "</option>";
							}
							echo "</select>";
							echo "<input type='hidden' id='livcom_allocation' name='livcom_allocation' value='" . esc_attr( $selected_plan ) . "' />";
							?>
						</td>
					</tr>
					<?php
					/**
					 * Display categories in a hierarchical order.
					 */
					function livcom_display_categories( $parent_id, $depth, $category_selected ) {
						$categories = get_categories( array( 'parent' => $parent_id, 'orderby' => 'name', 'order' => 'ASC' ) );

						foreach ( $categories as $category ) {
							if ( $category->name == "Uncategorized" && $depth == 0 ) continue;

							$depth = intval( $depth );
							// Add an em dash followed by a space if the depth is greater than 0
							$prefix = $depth > 0 ? str_repeat( '<i class="las la-minus"></i> ', $depth ) : '';

							// Allowed HTML tags and attributes
							$allowed_html = array(
								'i' => array(
									'class' => array()
								)
							);

							// Print the category with the prefix and whether it's selected
							echo "<label>" . wp_kses( $prefix, $allowed_html ) . "<input type='checkbox' name='livcom_plugin_category_selected[]' value='" . esc_attr( $category->term_id ) . "'" . ( in_array( $category->term_id, $category_selected ) ? ' checked="checked"' : '' ) . ">" . esc_html( $category->name ) . "</label>";

							// If this category has children, display them
							if ( ! empty( get_categories( array( 'parent' => $category->term_id ) ) ) ) {
								livcom_display_categories( $category->term_id, $depth + 1, $category_selected );
							}
						}

						if ( $depth == 0 ) {
							foreach ( $categories as $category ) {
								if ( $category->name == "Uncategorized" ) {
									echo "<label><input type='checkbox' name='livcom_plugin_category_selected[]' value='" . esc_attr( $category->term_id ) . "'" . ( in_array( $category->term_id, $category_selected ) ? ' checked="checked"' : '' ) . ">" . esc_html( $category->name ) . "</label>";
								}
							}
						}
					}
					?>
					<tr>
					  <th scope="row">Select Language</th>
					  <td class="is-fullwidth">
						<div class="field">
						  <div class="field has-addons">
							<div class="control is-flex is-flex-wrap-wrap">
							  <div class="select is-fullwidth">
								<select id="languageSelect" name="livcom_language">
								  <?php
								  global $wp_filesystem;

								  // Initialize the WordPress Filesystem API.
								  if ( ! $wp_filesystem ) {
									require_once( ABSPATH . 'wp-admin/includes/file.php' );
									WP_Filesystem();
								  }

								  $json_file = plugin_dir_path( __FILE__ ) . '../json/languages.json';

								  if ( $wp_filesystem->exists( $json_file ) ) {
									$json = $wp_filesystem->get_contents( $json_file );
									$languages = json_decode( $json, true );
									$default_language = get_option('livcom_language', 'English');

									foreach ( $languages['languages'] as $language ) :
									  // Use 'backend' value for option value, and 'display' for the visible part
									  $selected = selected( $language['backend'], $default_language, false );
								  ?>
									  <option value="<?php echo esc_attr( $language['backend'] ); ?>" <?php selected( $language['backend'], $default_language ); ?>>
										  <?php echo esc_html( $language['display'] ); ?>
									  </option>
								  <?php
									endforeach;
								  } else {
									// Error handling for unavailable language data
									echo '<option value="">English</option>';
								  }
								  ?>
								</select>
							  </div>
							</div>
						  </div>
						  <span class="help mb-2">Select your preferred language for generating comments and replies.</span>
						</div>  
					  </td>
					</tr>
					<tr>
						<th scope="row">Select Categories</th>
						<td class="p-2">
							<div class="multiselect" style="max-height: 166px; overflow-y: auto;">
								<label>
									<input type="checkbox" id="livcom_plugin_category_all" <?php echo ( count( $category_selected ) === count( $categories ) ) ? 'checked' : ''; ?>>All Categories
								</label>
								<?php
								livcom_display_categories( 0, 0, $category_selected );
								?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Autopost Schedule</th>
						<td class="p-2">
							<div class="field has-addons">
								<div class="control">
									<a class="button is-static">Every</a>
								</div>
								<div class="control">
									<input class="input" type="number" min="4" max="1440" value="<?php echo absint( $frequency_min ); ?>" name="livcom_plugin_frequency_min" id="livcom_plugin_frequency_min">
								</div>
								<div class="control">
									<a class="button is-static">to</a>
								</div>
								<div class="control">
									<input class="input" type="number" min="4" max="1440" value="<?php echo absint( $frequency_max ); ?>" name="livcom_plugin_frequency_max" id="livcom_plugin_frequency_max">
								</div>
								<div class="control">
									<a class="button is-static">minutes</a>
								</div>
							</div>
							<div class="columns is-variable is-1-mobile">
								<div class="column is-narrow">
									<p><span id="recommendedPlan"></span></p>
								</div>
								<div class="column is-narrow">
									<p><span id="commentsPerDay"></span></p>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Word Length</th>
						<td class="p-2">
							<div class="field is-grouped is-grouped-multiline is-align-items-center">
								<div class="control">
									<div class="buttons has-addons">
										<span class="button is-primary is-light is-hovered is-rounded py-0" id="shortLengthButton">Short</span>
										<span class="button is-warning is-light is-hovered is-rounded py-0" id="mediumLengthButton">Medium</span>
										<span class="button is-danger is-light is-hovered is-rounded py-0" id="longLengthButton">Long</span>
									</div>
								</div>
								<div class="control">
									<label class="label is-inline-flex is-align-items-center mb-0 ml-6">
										<span class="mr-2">Allow emojis?</span>
										<input type="hidden" name="livcom_allow_emoticons" value="off">
										<input id="livcom_allow_emoticons" type="checkbox" name="livcom_allow_emoticons" class="switch is-rounded is-success is-hidden" <?php checked( get_option( 'livcom_allow_emoticons', 'on' ), 'on' ); ?>>
										<label for="livcom_allow_emoticons" class="checkbox-toggle"></label>
									</label>
									<span class="ml-2 is-size-7">(approx. 1 in every 20 comments/replies)</span>
								</div>
							</div>
							<input type="hidden" name="livcom_plugin_word_length" id="livcom_plugin_word_length" value="<?php echo esc_attr( get_option( 'livcom_plugin_word_length', '' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">Comment/Reply Ratio</th>
						<td class="p-2">
							<input type="range" min="0" max="100" step="1" value="<?php echo esc_attr( get_option( 'livcom_plugin_comment_reply_ratio', 70 ) ); ?>" name="livcom_plugin_comment_reply_ratio" id="livcom_plugin_comment_reply_ratio" style="width: 450px;" class="mr-3">
							<span id="livcom_plugin_comment_reply_ratio_display"><?php echo esc_html( get_option( 'livcom_plugin_comment_reply_ratio', 70 ) ); ?></span>% Comments / <span id="livcom_plugin_reply_ratio_display"><?php echo esc_html( 100 - get_option( 'livcom_plugin_comment_reply_ratio', 70 ) ); ?></span>% Replies
							<p class="help">
								<span id="force-comment"></span>
								<span id="random-nature-message">Due to its random nature, the outcome may not align precisely with your selected ratio.</span>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Reply To Users</th>
						<td class="p-2">
							<div class="field is-grouped is-grouped-multiline is-align-items-center">
								<div class="control">
									<label class="label is-inline-flex is-align-items-center mb-0">
										<input type="hidden" name="livcom_allow_reply_users" value="off">
										<input id="livcom_allow_reply_users" type="checkbox" name="livcom_allow_reply_users" class="switch is-rounded is-success is-hidden" <?php checked( get_option( 'livcom_allow_reply_users', 'on' ), 'on' ); ?>>
										<label for="livcom_allow_reply_users" class="checkbox-toggle"></label>
									</label>
									<span class="ml-4 is-size-7"><strong class="is-size-6">ON</strong> - Reply to comments from both real users and those generated by the plugin. <strong class="ml-4 is-size-6">OFF</strong> - Only reply to comments generated by the plugin.</span>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Select Tones</th>
						<td class="p-2">
							<div class="multiselect-tones buttons">
								<button class="button is-hovered" id="livcom_plugin_tone_all" type="button">
									<span class="icon"><i class="las la-check-square is-size-5"></i></span>
									<span>Select All</span>
								</button>
								<?php
								$lc_plugin_tones_icons = get_option( 'livcom_plugin_tones_icons' );
								$counter = 0; // Initialize the counter
								foreach ( $possible_tones as $tone ) {
									$counter++; // Increment the counter for each iteration

									// Determine the class based on the counter
									$colorClass = $counter <= 25 ? 'is-primary' : ( $counter <= 35 ? 'is-warning' : 'is-danger' );
									$selected = in_array( $tone, $tones_selected ) ? "$colorClass is-light is-active" : $colorClass;
									$iconClass = isset( $lc_plugin_tones_icons[$tone] ) ? $lc_plugin_tones_icons[$tone] : '';

									echo "<button data-base-class='" . esc_attr( $colorClass ) . "' class='button tone-button is-hovered " . esc_attr( $selected ) . "' data-tone='" . esc_attr( $tone ) . "' type='button'>
											<span class='icon'><i class='" . esc_attr( $iconClass ) . " is-size-4'></i></span>
											<span>" . esc_html( $tone ) . "</span>
										  </button>";
								}
								?>
							</div>
							<div id="livcom_plugin_tones_container">
								<?php
								// Initialize the hidden input fields based on the $tones_selected data
								foreach ( $tones_selected as $tone ) {
									echo '<input type="hidden" name="livcom_plugin_tones_selected[]" value="' . esc_attr( $tone ) . '">';
								}
								?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Words to Filter</th>
						<td class="p-2">
							<article class="message is-warning">
								<div class="message-body">
									<span><strong>Disclaimer</strong>: All comments/replies generated are already subjected to moderation to filter out any inappropriate content before delivery. We'll instruct our system to omit your words from future comments or replies, but please note that Large Language Models have significant limitations in this area. Comments generated that still contain these words will be blocked on your end but will lead to a reduction in your balance, so try keeping the words to a minimum.</span>
								</div>
							</article>
							<div class="field has-addons" style="max-width:850px;">
								<div class="control is-expanded">
									<input id="livcom_plugin_custom_word_input" class="input" type="text" style="width: 100%;" placeholder="Enter words separated by commas. Case-insensitive.">
								</div>
								<div class="control is-flex is-align-items-center">
									<button id="livcom_plugin_add_word_button" class="button is-warning" type="button"><i class="las la-plus la-lg has-text-weight-bold"></i> <span class=" is-size-7">Add Words</span></button>
									<button id="livcom_plugin_delete_all_words_button" class="button is-danger is-light ml-2" type="button"><i class="las la-trash-alt la-lg has-text-weight-bold"></i> <span class="is-size-7">Clear All</span></button>
									<!-- Display livcom_blocked_comments -->
									<span id="livcom_blocked_comments_display" class="ml-3 is-size-7">
										Blocked: <?php echo esc_html( get_option( 'livcom_blocked_comments', 0 ) ); ?>
									</span>
								</div>
							</div>
							<div id="livcom_plugin_custom_words_container">
								<?php 
								$custom_words_unique = array_unique( $custom_words );
								foreach ( $custom_words_unique as $word ) : 
									if ( ! empty( $word ) ) :
								?>
										<div class="livcom_plugin_custom_word tag is-medium is-warning">
											<?php echo esc_html( $word ); ?> <span class="livcom_plugin_remove_word_button delete is-small">x</span>
											<input type="hidden" name="livcom_plugin_custom_words[]" value="<?php echo esc_attr( $word ); ?>">
										</div>
								<?php 
									endif;
								endforeach; 
								?>
							</div>
						</td>
					</tr>
				</table>
			</div>
			<div id="user-tab-content" class="tab-pane">
				<table id="user-table" class="table is-fullwidth is-bordered">
					<?php
					// Strip 'www.' from the host name
					$host = parse_url( site_url(), PHP_URL_HOST );
					$stripped_host = preg_replace( '/^www\./', '', $host );
					?>

					<tr>
						<th scope="row">Domain for Comment Authors</th>
						<td class="is-fullwidth">
							<div class="field">
								<span class="help mb-2">Choose an email domain you want to use with all usernames associated with generated comments/replies (e.g., username@fake-domain.com):</span>
								<div class="field has-addons">
									<div class="control is-flex is-flex-wrap-wrap">
										<div class="select">
											<select id="livcom_email_domain_option" name="livcom_email_domain_option" onchange="toggleDomainInput(this.value)">
												<option value="default" <?php selected( get_option( 'livcom_email_domain_option' ), 'default' ); ?>>Default (<?php echo esc_html( $stripped_host ); ?>)</option>
												<option value="custom" <?php selected( get_option( 'livcom_email_domain_option' ), 'custom' ); ?>>Custom</option>
											</select>
										</div>
										<div class="control is-expanded">
											<input class="input" type="text" id="livcom_custom_domain" name="livcom_custom_domain" placeholder="Enter a custom-domain.com" value="<?php echo esc_attr( get_option( 'livcom_custom_domain' ) ); ?>" style="<?php echo ( get_option( 'livcom_email_domain_option' ) == 'custom' ? 'display:inline-block;' : 'display:none;' ); ?>" />
										</div>
									</div>
								</div>
							</div>	
						</td>
					</tr>
					<tr>
						<th scope="row">AI_ Prefix</th>
						<td class="is-fullwidth">
							<div class="field">
								<span class="help mb-2">Append an 'AI_' prefix to names (does not apply to dummy users) to inform other users that the comment or reply has been generated by an AI system (e.g., AI_CottonCloud563)?</span>
								<div class="control">
									<input type="hidden" name="livcom_ai_prefix" value="off">
									<input id="livcom_ai_prefix" type="checkbox" name="livcom_ai_prefix" class="switch is-rounded is-success is-hidden" <?php checked( get_option( 'livcom_ai_prefix', 'off' ), 'on' ); ?>>
									<label for="livcom_ai_prefix" class="checkbox-toggle"></label>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<?php 
						// Retrieve options directly here
						$guest_names = get_option( 'livcom_plugin_guest_names', array() );
						$dummy_users = get_option( 'livcom_plugin_dummy_users', array() );

						// Empty checks for arrays. Check the first element if it exists
						$guest_names_empty = ( ! isset( $guest_names[0] ) || empty( $guest_names[0] ) );
						$dummy_users_empty = ( ! isset( $dummy_users[0] ) || empty( $dummy_users[0] ) );

						// If both arrays are empty, set livcom_user_priority to 'off'
						if ( $guest_names_empty && $dummy_users_empty ) {
							update_option( 'livcom_user_priority', 'off' );
						}
						?>
						<th scope="row">Prioritize Guests / Dummy Users</th>
						<td class="is-fullwidth">
							<div class="field">
								<span class="help mb-2">If the guest names and/or dummy users you've added below are not unique within the selected blog post or comment thread after 3 attempts, we'll generate a random username. If this setting is off, generating a random username will have the same probability as the other choices.</span>
								<div class="control">
									<input type="hidden" name="livcom_user_priority" value="off">
									<input id="livcom_user_priority" type="checkbox" name="livcom_user_priority" class="switch is-rounded is-success is-hidden" <?php echo ( $guest_names_empty && $dummy_users_empty ) ? 'disabled' : ''; ?> <?php checked( esc_attr( get_option( 'livcom_user_priority' ) ), 'on' ); ?>>
									<label for="livcom_user_priority" class="checkbox-toggle"></label>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Add Guests</th>
						<td class="is-fullwidth">
							<div class="field">
								<article class="message is-primary">
									<div class="message-body">
										<span>By default, we will generate a new random name when a comment or reply is generated but you have the option to add your own names here:</span>
										<ol class="help mt-2 ml-3">
											<li>Names can only contain alphanumeric characters, spaces, hyphens, and underscores. Must be 3 to 60 characters in length.</li>
										</ol>
									</div>
								</article>
								<div class="control mt-3">
									<div class="field has-addons" style="max-width:800px;">
										<div class="control is-expanded">
											<input id="livcom_plugin_guest_name_input" class="input" type="text" style="width: 100%;" placeholder="Enter author names separated by commas.">
										</div>
										<div class="control is-flex is-align-items-center">
											<button id="livcom_plugin_add_guest_button" class="button is-info" type="button"><i class="las la-plus la-lg has-text-weight-bold"></i> <span class="is-size-7">Add Guests</span></button>
											<button id="livcom_plugin_delete_all_button" class="button is-danger is-light ml-2" type="button"><i class="las la-trash-alt la-lg has-text-weight-bold"></i> <span class="is-size-7">Clear All</span></button>
											<span id="livcom_plugin_guest_names_counter" class="ml-3 is-size-7">Names: 0</span>
										</div>
									</div>
									<div id="livcom_plugin_guest_names_container">
										<?php 
										$guest_names = get_option( 'livcom_plugin_guest_names', array() );
										$guest_names_unique = array_unique( $guest_names );
										foreach ( $guest_names_unique as $name ) : 
											if ( ! empty( $name ) ) :
										?>
												<div class="livcom_plugin_guest_name tag is-medium is-info">
													<?php echo esc_html( $name ); ?> <span class="livcom_plugin_remove_guest_button delete is-small">x</span>
													<input type="hidden" name="livcom_plugin_guest_names[]" value="<?php echo esc_attr( $name ); ?>">
												</div>
										<?php 
											endif;
										endforeach; 
										?>
									</div>
									<!-- Pagination buttons -->
									<div id="pagination" class="buttons mt-3"></div>
								</div>
							</div>
						</td>
					</tr>	
					<tr>
						<th scope="row">Add Dummy Users</th>
						<td class="is-fullwidth">
							<div class="field">
								<article class="message is-primary">
									<div class="message-body">
										<span>You must approve a user before using them with our plugin to ensure no real users are added accidentally. Within the WordPress Admin Panel, go to Users, then select Add New to create a user or choose to edit a user that you've already created. When creating or editing a user, scroll down to Living Comments Dummy User Approval and tick "Approve for Dummy Users." After approving the dummy users, add the usernames in the input box below. Verify everything is in order before hitting Save All Changes.</span>
										<ol class="help mt-2 ml-3">
											<li>Usernames can only contain alphanumeric characters, hyphens, and underscores. They must be at least 3 characters long and must not exceed 60 characters.</li>
										</ol>
									</div>
								</article>
								<div class="mt-2 is-flex is-align-items-center">
									<span class="is-size-6" id="livcom_plugin_username_check_message" style="display: none;">
										Checking approved user accounts. This operation may take a moment.
									</span>
									<button class="button is-small ml-3" id="livcom_plugin_username_check_cancel" type="button" style="display: none;">Cancel operation</button>
								</div>
								<div class="control mt-3">
									<div class="field has-addons" style="max-width:800px;">
										<div class="control is-expanded">
											<input id="livcom_plugin_dummy_user_input" class="input" type="text" style="width: 100%;" placeholder="Enter usernames separated by commas. Case-insensitive.">
										</div>
										<div class="control is-flex is-align-items-center">
											<button id="livcom_plugin_add_dummy_button" class="button is-link" type="button"><i class="las la-plus la-lg has-text-weight-bold"></i> <span class="is-size-7">Add Users</span></button>
											<button id="livcom_plugin_delete_all_dummy_button" class="button is-danger is-light ml-2" type="button"><i class="las la-trash-alt la-lg has-text-weight-bold"></i> <span class="is-size-7">Clear All</span></button>
											<span id="livcom_plugin_dummy_users_counter" class="ml-3 is-size-7">Users: 0</span>
										</div>
									</div>
									<div id="livcom_plugin_dummy_users_container">
										<?php 
										$dummy_users = get_option( 'livcom_plugin_dummy_users', array() );
										$dummy_users_unique = array_unique( $dummy_users );
										foreach ( $dummy_users_unique as $user ) : 
											if ( ! empty( $user ) ) :
										?>
												<div class="livcom_plugin_dummy_user tag is-medium is-link">
													<?php echo esc_html( $user ); ?> <span class="livcom_plugin_remove_dummy_button delete is-small">x</span>
													<input type="hidden" name="livcom_plugin_dummy_users[]" value="<?php echo esc_attr( $user ); ?>">
												</div>
										<?php 
											endif;
										endforeach; 
										?>
									</div>
									<!-- Pagination buttons -->
									<div id="dummy_pagination" class="buttons mt-3"></div>
								</div>
							</div>
						</td>
					</tr>
				</table>
			</div>
			<div id='history-tab-content' class='tab-pane'>
				<table id='history-table' class='table is-fullwidth is-bordered is-striped'>
					<?php
					$last_posted_ids = get_option( 'livcom_last_posted', [] );
					$lc_plugin_tones_icons = get_option( 'livcom_plugin_tones_icons' );

					if ( ! empty( $last_posted_ids ) && ! wp_next_scheduled( 'livcom_cron_check_event' ) ) {
						wp_schedule_event( time(), 'hourly', 'livcom_cron_check_event' );
					}

					if ( empty( $last_posted_ids ) ) {
						echo '<p class="is-size-5 has-text-weight-semibold">No comments or replies to display.</p>';
					} else {
						echo '<thead>';
						echo '<tr><th class="has-text-weight-normal" colspan="5">Showing last 10 comments/replies generated</th></tr>';
						echo '<tr><th>Author</th><th>Tone</th><th>Length</th><th>Generated Comment/Reply</th><th>Action</th></tr>';
						echo '</thead>';

						$displayed_count = 0;
						foreach ( $last_posted_ids as $id ) {
							if ( $displayed_count >= 10 ) {
								break;
							}

							$comment = get_comment( $id['id'] );
							if ( ! $comment || is_wp_error( $comment ) || $comment->comment_approved !== '1' ) {
								continue;
							}

							echo wp_kses_post( livcom_get_comment_details( $comment, $id, $lc_plugin_tones_icons ) );
							$displayed_count++;
						}
					}
					?>
				</table>
			</div>
			<div id="delete-comment-modal" class="modal delete-comment-modal">
				<div class="modal-background"></div>
				<div class="modal-card">
					<header class="modal-card-head">
						<p class="modal-card-title"><i class="las la-trash-alt"></i> Confirm Deletion</p>
						<button type="button" class="delete delete-comment-modal-close" aria-label="close"></button>
					</header>
					<section class="modal-card-body">
						<p class="is-size-6">Are you sure you want to delete this comment/reply?<br>
						<small class="mt-5 has-text-danger">Warning: Once deleted, you cannot undo this operation.</small></p>
					</section>
					<footer class="modal-card-foot">
						<button type="button" class="button cancel-delete-comment-btn">Keep this comment</button>
						<button type="button" class="button is-danger confirm-delete-comment-btn">Delete this comment</button>
					</footer>
				</div>
			</div>
			<div id="unhappy-comment-modal" class="modal unhappy-comment-modal">
				<div class="modal-background"></div>
				<div class="modal-card">
					<header class="modal-card-head">
						<p class="modal-card-title"><i class="las la-flag"></i> Report this comment?</p>
						<button type="button" class="delete unhappy-comment-modal-close" aria-label="close"></button>
					</header>
					<section class="modal-card-body">
						<p class="is-size-6">We welcome your anonymous report as a way to improve our services. Your comment will be reviewed diligently, and if needed, appropriate adjustments will be made to our services.</p>
					</section>
					<footer class="modal-card-foot">
						<button type="button" class="button cancel-unhappy-comment-btn">Don't send a report.</button>
						<button type="button" class="button is-danger confirm-unhappy-comment-btn">I'm unhappy, send in a report.</button>
					</footer>
				</div>
			</div>
			<div id="report-success-modal" class="modal">
				<div class="modal-background"></div>
				<div class="modal-card">
					<header class="modal-card-head">
						<p class="modal-card-title"><i class="las la-check-circle"></i> Success</p>
					</header>
					<section class="modal-card-body">
						<p class="is-size-6">Thank you for reporting the comment. Your feedback will help us improve our services for everyone!</p>
					</section>
					<footer class="modal-card-foot">
						<button class="button is-primary" id="report-refresh-button" type="button">Refresh Page</button>
					</footer>
				</div>
			</div>
			<div id="billing-tab-content" class="tab-pane">
				<?php
				$lc_user_subs = get_option( 'livcom_user_subs' );

				if ( !$lc_user_subs ) {
					livcom_display_no_subscriptions_section();
				} else {
					$subscriptions = maybe_unserialize( $lc_user_subs );
					if ( !$subscriptions ) {
						livcom_display_no_subscriptions_section();
					} else {
						foreach ( $subscriptions as $subscription ) {
							if ( $subscription['status'] == 'Active' || $subscription['status'] == 'Past Due' ) {
								livcom_display_single_subscription( $subscription );
							}
						}
						livcom_display_subscriptions_table( $subscriptions );
					}
				}
				?>
			</div>
			<div id="cancel-subscription-modal" class="modal">
				<div class="modal-background"></div>
				<div class="modal-card">
					<header class="modal-card-head">
						<p class="modal-card-title"><i class="las la-hand-paper"></i> Confirm Cancellation</p>
						<button type="button" class="delete" aria-label="close"></button>
					</header>
					<section class="modal-card-body">
						<p class="is-size-6">
							Are you sure you want to cancel your subscription? You can switch to a different plan in the Account Overview tab.
							<br>
							<small class="mt-5 has-text-danger">Warning: Once your request has been submitted, you cannot undo this operation.</small>
						</p>
					</section>
					<footer class="modal-card-foot">
						<button type="button" class="button is-primary" id="keep-plan">Keep my subscription</button>
						<button type="button" class="button" id="confirm-cancel">Cancel my subscription</button>
					</footer>
				</div>
			</div>
			<div class="modal" id="cancel-success-modal">
				<div class="modal-background"></div>
				<div class="modal-card">
					<header class="modal-card-head">
						<p class="modal-card-title"><i class="las la-check-circle"></i> Success</p>
					</header>
					<section class="modal-card-body">
						<p class="is-size-6">Your subscription cancellation request has been successfully sent. Please refresh account to see the changes.</p>
					</section>
					<footer class="modal-card-foot">
						<button class="button is-primary" id="cancel-refresh-button" type="button">Refresh My Account</button>
					</footer>
				</div>
			</div>
			<div id="faq-tab-content" class="tab-pane">
				<?php
				// Load FAQ data.
				$faqContent = livcom_load_faq_data();

				// Render the FAQ table.
				livcom_render_faq_table( $faqContent );

				?>
			</div>
			<input type="submit" name="submit" id="submit" class="button is-danger mt-3" value="Save All Changes">
		</div>
	</div>
</form>




    <?php
}