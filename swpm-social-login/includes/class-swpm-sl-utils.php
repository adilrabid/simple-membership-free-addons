<?php

class SWPM_SL_Utils {
    /**
     * Retrieve social login addon settings by option name.
     */
    public static function get_settings($option_name, $default = '') {
        $settings = get_option('swpm_sl_settings', array());
        if (isset($settings[$option_name])) {
            return $settings[$option_name];
        }

        return $default;
    }

    /**
     * Generates a random password (for auto registration).
     */
    public static function generate_random_password($length = 12) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
        $characters_length = strlen($characters);
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $characters_length - 1);
            $password .= $characters[$index];
        }

        return $password;
    }

    /**
     * Find connected SWPM member account using the data provided by the social login provider.
     *
     * @param SWPM_SL_Auth_Data $auth_data
     * 
     * @return null|object Returns swpm member info object if found, null otherwise.
     */
    public static function get_connected_member_account(SWPM_SL_Auth_Data $auth_data) {
        SWPM_SL_Utils::log_auth_debug("Attempting to find connected SWPM member account for ". $auth_data->provider);

        $provider_user_id = isset($auth_data->provider_user_id) ? $auth_data->provider_user_id : '';
        if (empty($provider_user_id)) {
            return null;
        }

        global $wpdb;

        $query = $wpdb->prepare("SELECT member_id FROM {$wpdb->prefix}swpm_members_meta_tbl WHERE meta_key = %s AND meta_value = %s LIMIT 1", "swpm_sl_connected_{$auth_data->provider}_account", $provider_user_id);
        $member_id = $wpdb->get_var($query);

        if (!empty($member_id)) {
            SWPM_SL_Utils::log_auth_debug("Found a connected SWPM member ID ".$member_id." for ".$auth_data->provider." user ID ".$provider_user_id);
            $member = SwpmMemberUtils::get_user_by_id($member_id);
        }

        // If not found, try to find by email.
        if (empty($member)) {
            SWPM_SL_Utils::log_auth_debug("No connected SWPM member account found for ". $auth_data->provider);

            // Try to find by email.
            SWPM_SL_Utils::log_auth_debug("Attempting to find SWPM member by email ". $auth_data->email);
            $member = SwpmMemberUtils::get_user_by_email($auth_data->email);

            if (!empty($member)) {
                // Member found by swpm email. That means its a first time login using this social provider.

                SWPM_SL_Utils::log_auth_debug("Found SWPM member ID ". $member->member_id ." by email ". $auth_data->email);
                SWPM_SL_Utils::log_auth_debug("Its a first time login by ". $auth_data->provider .". Linking the account to SWPM member ID ". $member->member_id ." for future logins.");

                // Link the social account to this member for future logins.
                SWPM_SL_Utils::connect_social_account_to_swpm_account($member->member_id, $auth_data);
            } else {
                SWPM_SL_Utils::log_auth_debug("No SWPM member account found by email ". $auth_data->email ." either.");
            }
        }

        return $member;
    }

    /**
     * Link the social account to this member for future logins.
     */
    public static function connect_social_account_to_swpm_account($member_id, SWPM_SL_Auth_Data $auth_data) {
        // swpm_sl_connected_google_account
        // swpm_sl_connected_facebook_account
        SwpmMembersMeta::add($member_id, "swpm_sl_connected_".$auth_data->provider."_account", $auth_data->provider_user_id);

        // swpm_sl_connected_google_email_address
        // swpm_sl_connected_facebook_email_address
        SwpmMembersMeta::add($member_id, "swpm_sl_connected_".$auth_data->provider."_email_address", $auth_data->email);

        SWPM_SL_Utils::log_auth_debug("Linked ".$auth_data->provider." user ID ". $auth_data->provider_user_id." to SWPM member ID ". $member_id);
    }

    public static function log_simple_debug($message, $success = true, $end = false) {
        $message = '[Social Login] ' . $message;
        SwpmLog::log_simple_debug($message, $success, $end);
    }

    public static function log_array_data_to_debug($array, $success = true, $end = false) {
        self::log_simple_debug('', $success);
        SwpmLog::log_array_data_to_debug($array, $success, $end);
    }

    public static function log_auth_debug($message, $success = true, $end = false) {
        $message = '[Social Login] ' . $message;
        SwpmLog::log_auth_debug($message, $success, $end);
    }

    public static function is_plugin_active($plugin) {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        return is_plugin_active($plugin);
    }

    public static function get_linked_social_accounts($member_id){
        $connected_accounts = array();

        if (empty($member_id)) {
            return $connected_accounts;
        }
        
        $linked_google_email = SwpmMembersMeta::get($member_id, 'swpm_sl_connected_google_email_address', true);
        if (!empty($linked_google_email)) {
            $connected_accounts[] = array(
                'provider' => 'google',
                'text' => __('Google', 'simple-membership'),
                'email' => $linked_google_email
            );
        }

        $linked_facebook_email = SwpmMembersMeta::get($member_id, 'swpm_sl_connected_facebook_email_address', true);
        if (!empty($linked_facebook_email)) {
            $connected_accounts[] = array(
                'provider' => 'facebook',
                'text' => __('Facebook', 'simple-membership'),
                'email' => $linked_facebook_email
            );
        }

        return $connected_accounts;
    }

}
