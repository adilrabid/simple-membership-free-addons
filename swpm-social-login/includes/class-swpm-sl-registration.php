<?php

class SWPM_SL_Registration extends SwpmRegistration{
    public function register_new_member(SWPM_SL_Auth_Data $auth_data) {
        SWPM_SL_Utils::log_simple_debug("Starting new member registration process for email: ".$auth_data->email, true);

        //Check if the email belongs to an existing wp user account with admin role.
        SwpmMemberUtils::check_and_die_if_email_belongs_to_admin_user($auth_data->email);

        /**
         * Create SWPM member account
         */

        // Get the membership level for the new account.
        $membership_level_id = absint(SWPM_SL_Utils::get_settings('auto_registration_membership_level', ''));
        if (empty($membership_level_id)) {
            throw new \Exception(__('No membership level has been set for auto account creation. New accounts cannot be created via Social Login until this is configured in the settings.', 'simple-membership'));
        }

        $membership_level = SwpmUtils::get_membership_level_row_by_id($membership_level_id);
        if (empty($membership_level)) {
            throw new \Exception(__('The membership level configured for auto account creation doesn\'t exist. New accounts cannot be created via Social Login until this is corrected in the settings.', 'simple-membership'));
        }

        $user_role = $membership_level->role;

        if ($user_role == 'administrator') {
            //For security reasons we don't allow users with administrator role to be creted from the front-end. That can only be done from the admin dashboard side.
            $error_msg  = '<p>';
            $error_msg  = sprintf(__('Error! The user role for this membership level (level ID: %d) is set to "Administrator".', 'simple-membership'), $membership_level_id);
            $error_msg .= ' ' . __('For security reasons, member registration to this level is not permitted from the front end.', 'simple-membership') . '</p>';
            $error_msg .= '</p>';
            // $error_msg .= '<p>'.__('An administrator of the site can manually create a member record with this access level from the admin dashboard side.', 'simple-membership').'</p>';

            throw new Exception($error_msg);
        }

        //Check if a default account status is set per membership level.
        $membership_level_custom_fields = SwpmMembershipLevelCustom::get_instance_by_id($membership_level_id);

        // Get per membership level default account status settings (if any).
        $email_activation = get_option('swpm_email_activation_lvl_' . $membership_level_id, false);
        if ($email_activation) {
            $account_status = 'activation_required';
        } else {
            $account_status = sanitize_text_field($membership_level_custom_fields->get('default_account_status'));
            if (empty($account_status)) {
                //Fallback. Use the value from the global settings.
                $account_status = SwpmSettings::get_instance()->get_value('default-account-status', 'active');
            }
        }

        $password = SWPM_SL_Utils::generate_random_password(); // use random password for social auth.
        $auth_data->set_password($password);

        $user_name = $auth_data->user_name;
        // Check if the user_name already exists. If so, then append random numbers to the username.
        if (!empty(SwpmMemberUtils::get_user_by_user_name($user_name))) {
            SWPM_SL_Utils::log_auth_debug("The user_name: ". $user_name ." already exists. Appending random numbers to the username.");
            $user_name = $user_name . rand(1000, 9999);
            $auth_data->set_user_name($user_name);
            SWPM_SL_Utils::log_auth_debug("New user_name: ". $user_name);
        }

        $member_info = array();
        $member_info['user_name'] = $user_name;
        $member_info['first_name'] = $auth_data->first_name;
        $member_info['last_name'] = $auth_data->last_name;
        $member_info['email'] = $auth_data->email;
        $member_info['password'] = SwpmUtils::encrypt_password($password);
        $member_info['last_accessed_from_ip'] = SwpmUtils::get_user_ip_address();
        $member_info['member_since'] = SwpmUtils::get_current_date_in_wp_zone(); //date( 'Y-m-d' );
        $member_info['subscription_starts'] = SwpmUtils::get_current_date_in_wp_zone(); //date( 'Y-m-d' );
        $member_info['membership_level'] = $membership_level_id;
        $member_info['account_state'] = $account_status;

        SWPM_SL_Utils::log_simple_debug("Creating new swpm user. Account status: " . $account_status . ", Membership Level ID: " . $membership_level_id, true);

        //Trigger the before member data save filter hook. It can be used to customize the member data before it gets saved in the database.
        SWPM_SL_Utils::log_simple_debug("Triggering 'swpm_registration_data_before_save' filter hook");
        $member_info = apply_filters('swpm_registration_data_before_save', $member_info);

        if (method_exists('SwpmMemberUtils', 'check_and_die_if_existing_wp_user_exists')) {
            // Validate the final member data after username resolution and filter customizations.
            $member_email_and_username = array(
                'email' => isset($member_info['email']) ? $member_info['email'] : '',
                'user_name' => isset($member_info['user_name']) ? $member_info['user_name'] : '',
            );
            SwpmMemberUtils::check_and_die_if_existing_wp_user_exists($member_email_and_username);
        }

        global $wpdb;

        //Create a new member record in the database for the free account/member registration.
        $inserted = $wpdb->insert($wpdb->prefix . 'swpm_members_tbl', $member_info);
        
        $member_info['plain_password'] = $password;

        if (empty($inserted)) {
            throw new Exception(__("Registration for new member couldn't be done.", 'simple-membership'));
            return;
        }
        
        SWPM_SL_Utils::log_simple_debug("New swpm user created successfully.", true);
        $new_member_id = $wpdb->insert_id;

        // Link the social account to this member for future logins.
        SWPM_SL_Utils::connect_social_account_to_swpm_account($new_member_id, $auth_data);

        /**
         * Create the corresponding WP user record.
         */

        $display_name = $auth_data->full_name;

        $wp_user_info                    = array();
        $wp_user_info['user_nicename']   = implode('-', explode(' ', $member_info['user_name']));
        $wp_user_info['display_name']    = $display_name;
        $wp_user_info['user_email']      = isset($member_info['email']) ? $member_info['email'] : '';
        $wp_user_info['nickname']        = isset($member_info['user_name']) ? $member_info['user_name'] : '';
        $wp_user_info['first_name']      = isset($member_info['first_name']) ? $member_info['first_name'] : '';
        $wp_user_info['last_name']       = isset($member_info['last_name']) ? $member_info['last_name'] : '';
        $wp_user_info['user_login']      = isset($member_info['user_name']) ? $member_info['user_name'] : '';
        $wp_user_info['password']        = $member_info['plain_password'];
        $wp_user_info['role']            = $user_role;
        $wp_user_info['user_registered'] = date('Y-m-d H:i:s');
        SwpmUtils::create_wp_user($wp_user_info);

        /**
         * Send registration complete email
         */
        $this->member_info = $member_info;
        $this->email_activation = $email_activation;
        $this->send_reg_email();

        SWPM_SL_Utils::log_simple_debug("Triggering 'swpm_front_end_registration_complete' action hook");
        do_action('swpm_front_end_registration_complete'); //Keep this action hook for people who are using it (so their implementation doesn't break).

        SWPM_SL_Utils::log_simple_debug("Triggering 'swpm_front_end_registration_complete_user_data' action hook");
        do_action('swpm_front_end_registration_complete_user_data', $member_info);

        /**
         * Handle email activation scenario.
         */

        if ($email_activation) {
            //This is an email activation scenario.
            //Set the registration complete message
            $email_act_msg  = '';
            // $email_act_msg  = '<div class="swpm-registration-success-msg">';
            $email_act_msg .= __('A account activation link has sent to your email address. Please check your email and follow instructions to complete your registration.', 'simple-membership');
            // $email_act_msg .= '</div>';

            SWPM_SL_Utils::log_simple_debug("Triggering 'swpm_registration_email_activation_msg' filter hook");
            $email_act_msg = apply_filters('swpm_registration_email_activation_msg', $email_act_msg); //Can be added to the custom messages addon.

            if (!empty($auth_data->referer_url)) {
                SWPM_SL_Auth_Response::set($email_act_msg, SWPM_SL_Auth_Response::SUCCESS);
                SwpmMiscUtils::redirect_to_url($auth_data->referer_url);
            } else {
                wp_die($email_act_msg);
            }
        } else {
            //This is a non-email activation scenario.
            //Check if there is after registration redirect (for non-email activation scenario).
            $after_rego_url = SwpmSettings::get_instance()->get_value('after-rego-redirect-page-url');

            SWPM_SL_Utils::log_simple_debug("Triggering 'swpm_after_registration_redirect_url' filter hook");
            $after_rego_url = apply_filters('swpm_after_registration_redirect_url', $after_rego_url);

            if (! empty($after_rego_url)) {
                //Yes. Need to redirect to this after registration page
                SWPM_SL_Utils::log_simple_debug('After registration redirect is configured in settings. Redirecting user to: ' . $after_rego_url, true);
                SwpmMiscUtils::redirect_to_url($after_rego_url);
            } else {
                $login_page_url = SwpmSettings::get_instance()->get_value('login-page-url');

                // Allow hooks to change the value of login_page_url
                SWPM_SL_Utils::log_simple_debug("Triggering 'swpm_register_front_end_login_page_url' filter hook");
                $login_page_url = apply_filters('swpm_register_front_end_login_page_url', $login_page_url);

                $after_rego_msg = '<div class="swpm-registration-success-msg">' . __('Registration Successful. ', 'simple-membership') . __('Please', 'simple-membership') . ' <a href="' . $login_page_url . '">' . __('Log In', 'simple-membership') . '</a></div>';

                SWPM_SL_Utils::log_simple_debug("Triggering 'swpm_registration_success_msg' filter hook");
                $after_rego_msg = apply_filters('swpm_registration_success_msg', $after_rego_msg);

                // SWPM_SL_Auth_Response::set($after_rego_msg, true);
            }
        }
    }
}
