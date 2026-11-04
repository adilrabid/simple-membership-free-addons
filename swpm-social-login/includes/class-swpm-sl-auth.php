<?php

class SWPM_SL_Auth {

    protected SWPM_SL_Auth_Data $auth_data;

    public function authenticate($auth_data) {
        // Check if authentication data is available.
        if (empty($auth_data)) {
            SWPM_SL_Utils::log_auth_debug(__METHOD__ . " - Authentication Data is not available, can't process the auth request.", false);
            SWPM_SL_Auth_Response::set(__("Authentication Data is not available, can't process the auth request.", 'simple-membership'), SWPM_SL_Auth_Response::ERROR);

            // Login Failed, redirect back to login page.
            $this->redirect_back();
        }

        $this->auth_data = $auth_data;

        // Find connected swpm member account using the data provided by the social login provider.
        $swpm_member = SWPM_SL_Utils::get_connected_member_account($this->auth_data);

        if (empty($swpm_member)) {
            // Member not found. Check if auto registration is enabled.

            if (!empty(SWPM_SL_Utils::get_settings('enable_sl_auto_registration'))) {
                // Auto registration of new member is enabled. register new member.
                try {
                    $swpm_sl_registration = new SWPM_SL_Registration();
                    $swpm_sl_registration->register_new_member($this->auth_data);

                    // Get the new member info.
                    $swpm_member = SwpmMemberUtils::get_user_by_email($this->auth_data->email);
                } catch (\Exception $e) {
                    // Error during auto registration
                    SWPM_SL_Utils::log_auth_debug($e->getMessage(), false);
                    SWPM_SL_Auth_Response::set($e->getMessage(), SWPM_SL_Auth_Response::ERROR);

                    // Login Failed, redirect back to login page.
                    $this->redirect_back();
                }
            } else {
                SWPM_SL_Utils::log_auth_debug(__METHOD__ . " - swpm account not found for email: " . $this->auth_data->email, false);
                SWPM_SL_Auth_Response::set(__('No account found with that email address.', 'simple-membership'), SWPM_SL_Auth_Response::ERROR);

                // Login Failed, redirect back to login page.
                $this->redirect_back();
            }
        }

        $this->auth_data->set_user_name($swpm_member->user_name);

        /**
         * Member account found.
         */

        //Trigger action hook that can be used to check stuff before the login request is processed by the plugin.
        $args = array(
            'username' => $this->auth_data->user_name,
            'password' => $this->auth_data->password, // Empty password
        );
        SWPM_SL_Utils::log_auth_debug("Triggering 'swpm_before_login_request_is_processed' action hook.");
        do_action('swpm_before_login_request_is_processed', $args);

        //First, lets make sure this user is not already logged into the site as an "Admin" user. We don't want to override that admin login session.
        if (current_user_can('administrator')) {
            //This user is logged in as ADMIN then trying to do another login as a member. Stop the login request processing (we don't want to override your admin login session).
            $wp_profile_page = SIMPLE_WP_MEMBERSHIP_SITE_HOME_URL . '/wp-admin/profile.php';
            $error_msg = '';
            $error_msg .= '<p>' . __('Warning! Simple Membership plugin cannot process this login request to prevent you from getting logged out of WP Admin accidentally.', 'simple-membership') . '</p>';
            $error_msg .= '<p><a href="' . $wp_profile_page . '" target="_blank">' . __('Click here', 'simple-membership') . '</a>' . __(' to see the profile you are currently logged into in this browser.', 'simple-membership') . '</p>';
            $error_msg .= '<p>' . __('You are logged into the site as an ADMIN user in this browser. First, logout from WP Admin then you will be able to log in as a normal member.', 'simple-membership') . '</p>';
            $error_msg .= '<p>' . __('Alternatively, you can use a different browser (where you are not logged-in as ADMIN) to test the membership login.', 'simple-membership') . '</p>';
            $error_msg .= '<p>' . __('Your normal visitors or members will never see this message. This message is ONLY for ADMIN user.', 'simple-membership') . '</p>';
            wp_die($error_msg);
        }

        // Check if the active login limit reached for this member account.
        if (SwpmLimitActiveLogin::is_enabled() && SwpmLimitActiveLogin::reached_active_login_limit($swpm_member->member_id)) {

            // Currently we only offer the 'allow' login logic for this feature.
            if (SwpmLimitActiveLogin::login_limit_logic() == 'allow') {

                // Delete session tokens of swpm member, this will log out the user from swpm side.
                SwpmLimitActiveLogin::delete_session_tokens($swpm_member->member_id);

                // We also need to get the associated wp user (if any) and log that user out from WP environment.
                $wp_user = SwpmMemberUtils::get_wp_user_from_swpm_user_id($swpm_member->member_id);
                if (!empty($wp_user) && class_exists('WP_Session_Tokens')) {
                    //Remove all session tokens for the wp user from the database. This will log out the member form wp side.
                    \WP_Session_Tokens::get_instance($wp_user->ID)->destroy_all();
                }

                // If the code reaches here, the member's session has been deleted (so the user will be logged out from both the swpm and wp side).
                SWPM_SL_Utils::log_auth_debug('Active login limit reached - All active session tokens cleared for member id: ' . $swpm_member->member_id);
            }
        }

        if (! $this->check_constraints($swpm_member)) {
            // Login Failed, redirect back to login page.
            $this->redirect_back();
        }

        /**
         * The swpm authentication has completed, now set necessary cookies to log user in.
         */

        // SWPM_SL_Utils::log_auth_debug("Triggering swpm_sl_login_auth_completed_filter hook.", true);
        // $proceed_after_auth = apply_filters('swpm_sl_login_auth_completed_filter', true, $this->auth_data);
        // if (!$proceed_after_auth) {
        //     return;
        // }

        /**
         * Login to SWPM.
         */

        $this->login_to_swpm($swpm_member, $this->auth_data->remember_me);

        SWPM_SL_Utils::log_auth_debug('Authentication successful for email: ' . $this->auth_data->email . '. Triggering swpm_after_login_authentication action hook.', true);
        do_action('swpm_after_login_authentication', $this->auth_data->user_name, $this->auth_data->password, $this->auth_data->remember_me); // This is used for login_event_tracking by core plugin.

        /**
         * Login to WP as well.
         */

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            SWPM_SL_Utils::log_auth_debug(__METHOD__ . " WP User is already logged in. WP username: " . $current_user->user_name, true);
            if ($current_user->user_email == $this->auth_data->email) {
                //The wp user is already logged in. Nothing to do.
                $this->redirect_back();
                return;
            }
        }

        $wp_user =  SwpmMemberUtils::get_wp_user_from_swpm_user_id($swpm_member->member_id);
        if ($wp_user instanceof \WP_User) {
            $this->login_to_wp($wp_user, $this->auth_data->remember_me);
        } else {
            SWPM_SL_Utils::log_auth_debug(__METHOD__ . " - wp account not found for swpm member id: " . $swpm_member->member_id, false);
            // TODO: Need to check if any adjustment required.
            $force_wp_user_sync = SwpmSettings::get_instance()->get_value('force-wp-user-sync');
            if (!empty($force_wp_user_sync)) {
                //Force WP user login sync is enabled. Show error and exit out since the WP user login failed.
                $error_msg = __("Error! This site has the force WP user login feature enabled in the settings. We could not find a WP user record for the given email: ", "simple-membership") . $this->auth_data->email;
                $error_msg .= "<br /><br />" . __("This error is triggered when a member account doesn't have a corresponding WP user account. So the plugin fails to log the user into the WP User system.", "simple-membership");
                $error_msg .= "<br /><br />" . __("Contact the site admin and request them to check your email in the WP Users menu to see what happened with the WP user entry of your account.", "simple-membership");
                $error_msg .= "<br /><br />" . __("The site admin can disable the Force WP User Synchronization feature in the settings to disable this feature and this error will go away.", "simple-membership");
                $error_msg .= "<br /><br />" . __("You can use the back button of your browser to go back to the site.", "simple-membership");
                wp_die($error_msg);
            }
        }

        SWPM_SL_Utils::log_auth_debug("Triggering 'swpm_after_login' action hook.");
        do_action('swpm_after_login');

        SWPM_SL_Utils::log_auth_debug("Triggering 'swpm_sl_after_login_redirect_url' filter hook.");
        $redirect_url = apply_filters('swpm_sl_after_login_redirect_url', '', $this->auth_data);
        if (!empty($redirect_url)) {
            SWPM_SL_Utils::log_auth_debug("After triggering the default swpm_sl_after_login_redirect_url hook. Redirect URL: " . $redirect_url );
            SwpmMiscUtils::redirect_to_url($redirect_url);
        }

        // Redirect back to login page
        $this->redirect_back();
    }

    private function check_constraints($swpm_member) {
        // SWPM_SL_Utils::log_auth_debug(__METHOD__ . " function.", true);

        global $wpdb;

        $enable_expired_login = SwpmSettings::get_instance()->get_value('enable-expired-account-login', '');

        //Update the last accessed date and IP address for this login attempt. $wpdb->update(table, data, where, format, where format)
        $last_accessed_date = current_time('mysql');
        $last_accessed_ip = SwpmUtils::get_user_ip_address();
        $wpdb->update(
            $wpdb->prefix . 'swpm_members_tbl',
            array(
                'last_accessed' => $last_accessed_date,
                'last_accessed_from_ip' => $last_accessed_ip,
            ),
            array('member_id' => $swpm_member->member_id),
            array('%s', '%s'),
            array('%d')
        );

        $message = '';

        // Check the member's account status.
        $can_login = true;
        if ($swpm_member->account_state == 'inactive' && empty($enable_expired_login)) {
            $message = __('Account is inactive.', 'simple-membership');
            $can_login = false;
        } elseif (($swpm_member->account_state == 'expired') && empty($enable_expired_login)) {
            $message = __('Account has expired.', 'simple-membership');
            $can_login = false;
        } elseif ($swpm_member->account_state == 'pending') {
            $message = __('Account is pending.', 'simple-membership');
            $can_login = false;
        } elseif ($swpm_member->account_state == 'activation_required') {
            $resend_email_url = add_query_arg(
                array(
                    'swpm_resend_activation_email' => '1',
                    'swpm_member_id' => $swpm_member->member_id,
                ),
                get_home_url()
            );
            $msg = sprintf(__('You need to activate your account. If you didn\'t receive an email then %s to resend the activation email.', 'simple-membership'), '<a href="' . $resend_email_url . '">' . __('click here', 'simple-membership') . '</a>');
            $message = '<div class="swpm_login_error_activation_required">' . $msg . '</div>';
            $can_login = false;
        }

        //Check if the user's account has expired.
        if (SwpmUtils::is_subscription_expired($swpm_member) && empty($enable_expired_login)) {
            //The user's account has expired.
            if ($swpm_member->account_state == 'active') {
                //This is an additional check at login validation time to ensure the user account gets set to expired state even if the cronjob fails to do it.
                $wpdb->update($wpdb->prefix . 'swpm_members_tbl', array('account_state' => 'expired'), array('member_id' => $swpm_member->member_id), array('%s'), array('%d'));
            }

            //Account has expired and expired login is not enabled.
            $message = __('Account has expired.', 'simple-membership');
            $can_login = false;
        }

        if (! $can_login) {
            $response_type = $swpm_member->account_state == 'activation_required' ? SWPM_SL_Auth_Response::INFO : SWPM_SL_Auth_Response::ERROR;
            SWPM_SL_Auth_Response::set($message, $response_type);
            return false;
        }

        return true;
    }

    public function login_to_swpm($userData, $remember) {
        $remember = boolval($remember);

        SWPM_SL_Utils::log_auth_debug(__METHOD__ . " - Value of 'remember me' parameter: " . $remember, true);
        if ($remember) {
            //This is the same value as the WP's "remember me" cookie expiration.
            $expiration = time() + 1209600; //14 days
            $expire = $expiration + 43200; //12 hours grace period
        } else {
            //When "remember me" is not checked, we use a session cookie to match with WP.
            //Session cookie will expire when the browser is closed.
            //The $expiration is used in the event the browser session is not closed for a long time. This value is used by our validate function on page load.
            $expiration = time() + 172800; //2 days.
            //Set the expire to 0 to match with WP's cookie expiration (when "remember me" is not checked).
            $expire = 0;
            SWPM_SL_Utils::log_auth_debug("The 'Remember me' option is unchecked for this request, setting expiry to be a session cookie. The session cookie will expire when the browser is closed.", true);
        }

        $expire = apply_filters('swpm_sl_google_auth_cookie_expiry_value', $expire);

        if (SwpmUtils::is_multisite_install()) {
            //Defines cookie-related WordPress constants on a multi-site setup (if not defined already).
            wp_cookie_constants();
        }

        $secure = is_ssl();

        setcookie('swpm_in_use', 'swpm_in_use', $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true); //Switch this to the following one.
        setcookie('wp_swpm_in_use', 'wp_swpm_in_use', $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true); //Prefix the cookie with 'wp' to exclude Batcache caching.
        if (function_exists('wp_cache_serve_cache_file')) { //WP Super cache workaround
            $author_value = isset($userData->user_name) ? $userData->user_name : 'wp_swpm';
            SWPM_SL_Utils::log_auth_debug("Triggering 'swpm_comment_author_cookie_value' filter hook.");
            $author_value = apply_filters('swpm_comment_author_cookie_value', $author_value);
            setcookie("comment_author_", $author_value, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
        }

        $expiration_timestamp = SwpmUtils::get_expiration_timestamp($userData);
        $enable_expired_login = SwpmSettings::get_instance()->get_value('enable-expired-account-login', '');
        // make sure cookie doesn't live beyond account expiration date.
        // but if expired account login is enabled then ignore if account is expired
        $expiration = empty($enable_expired_login) ? min($expiration, $expiration_timestamp) : $expiration;
        $pass_frag  = substr($userData->password, 8, 4);
        $scheme     = 'auth';

        $key              = SwpmAuth::b_hash($userData->user_name . $pass_frag . '|' . $expiration, $scheme);
        $hash             = hash_hmac('md5', $userData->user_name . '|' . $expiration, $key);
        $auth_cookie      = $userData->user_name . '|' . $expiration . '|' . $hash . '|' . intval($remember);
        $auth_cookie_name = $secure ? SIMPLE_WP_MEMBERSHIP_SEC_AUTH : SIMPLE_WP_MEMBERSHIP_AUTH;
        setcookie($auth_cookie_name, $auth_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true);

        if (SwpmLimitActiveLogin::is_enabled()) {
            // Save Session Token to members meta as well
            $token_key = $auth_cookie;
            $new_session_token = SwpmLimitActiveLogin::create_new_session_token_array(!empty($remember));
            SwpmLimitActiveLogin::refresh_member_session_tokens($userData->member_id, $token_key, $new_session_token);
        }
    }

    public function login_to_wp($wp_user, $remember = false) {
        wp_set_auth_cookie($wp_user->ID, $remember); // Set new auth cookies (second parameter true means "remember me")
        wp_set_current_user($wp_user->ID); // Set the current user object
    }

    /**
     * Redirect back to the page from where the login request was initiated, fallback to login page url and then home url.
     * 
     * @param array $params Additional query param to add to the redirect url.
     */
    public function redirect_back($params = array()) {
        if (!empty($this->auth_data->referer_url)) {
            $redirect_url = $this->auth_data->referer_url;
        } else {
            $redirect_url = SwpmSettings::get_instance()->get_value('login-page-url', SIMPLE_WP_MEMBERSHIP_SITE_HOME_URL);
        }

		if (!empty($params)){
			$redirect_url = add_query_arg($params, $redirect_url);
		}

        SwpmMiscUtils::redirect_to_url($redirect_url);
    }
}
