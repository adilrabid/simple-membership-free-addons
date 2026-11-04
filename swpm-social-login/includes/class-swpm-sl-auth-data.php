<?php

class SWPM_SL_Auth_Data {

    public $provider_user_id = ''; // Unique user ID from the auth provider
    public $email = '';
    public $password = '';
    public $user_name = '';
    public $full_name = '';
    public $first_name = '';
    public $last_name = '';

    public $provider = ''; // e.g., 'google', 'facebook', etc.
    public $referer_url = '';
    public $id_token = '';
    public $access_token = '';

    public $remember_me = false;

    public function set_provider_user_id($provider_user_id) {
        $this->provider_user_id = sanitize_text_field($provider_user_id);
    }

    public function set_email($email) {
        $this->email = sanitize_email($email);
    }

    public function set_password($password) {
        $this->password = sanitize_text_field($password);
    }

    public function set_user_name($user_name) {
        $this->user_name = sanitize_text_field($user_name);
    }

    public function set_first_name($first_name) {
        $this->first_name = sanitize_text_field($first_name);
    }

    public function set_last_name($last_name) {
        $this->last_name = sanitize_text_field($last_name);
    }

    public function set_provider($provider) {
        $this->provider = strtolower(sanitize_text_field($provider));
    }

    public function set_remember_me($remember_me) {
        $this->remember_me = (bool) $remember_me;
    }

    public function set_name($full_name) {
        $this->full_name = $full_name;

        // Trim whitespace and split the name into an array of words
        $name_parts = explode(" ", trim($full_name));

        // Handle names with a single part.
        if (count($name_parts) === 1) {
            $this->set_first_name($name_parts[0]);
            $this->set_last_name('');
            return;
        }

        // Get the last name by taking the last element of the array
        $last_name = array_pop($name_parts);
        $this->set_last_name($last_name);

        // Join the remaining elements to form the first name (including middle names)
        $first_name = implode(" ", $name_parts);
        $this->set_first_name($first_name);
    }

    public function set_referer_url($url){
        $url = sanitize_url($url);
        if (empty($url)) {
            $this->referer_url = '';
            return;
        }
        //Only allow a URL of this site (or the hosts allowed by the 'allowed_redirect_hosts' filter hook), since the visitor is redirected to it after the login.
        //Fallback to the login page URL.
        $fallback_url = SwpmSettings::get_instance()->get_value('login-page-url', SIMPLE_WP_MEMBERSHIP_SITE_HOME_URL);
        $this->referer_url = wp_validate_redirect($url, $fallback_url);
    }
    
    public function set_id_token($token){
        $this->id_token = $token;
    }

    public function set_access_token($token){
        $this->access_token = $token;
    }
}
