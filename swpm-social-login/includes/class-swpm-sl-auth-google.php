<?php

class SWPM_SL_Auth_Google extends SWPM_SL_Auth {

    const PROVIDER = 'google';

    public function __construct() {
        $enable_google_login = SWPM_SL_Utils::get_settings('enable_google_login');
        if (!empty($enable_google_login)) {
            $this->handle_google_login();
        }
    }

    public function handle_google_login() {
        // Check if any error (access permission denied).
        if (isset($_GET['error'])) {
            if ($_GET['error'] == 'access_denied') {
                SWPM_SL_Auth_Response::set(__('Access denied from google!', 'simple-membership'), SWPM_SL_Auth_Response::ERROR);
            } else {
                SWPM_SL_Auth_Response::set(__('Google authentication failed!', 'simple-membership'), SWPM_SL_Auth_Response::ERROR);
            }

            $this->redirect_back();
        }

        if (! isset($_GET['code'])) {
            return;
        }

        if (! isset($_GET['state'])) {
            return;
        }

        $additional_data_encoded = $_GET['state'];
        $additional_data = json_decode(base64_decode($additional_data_encoded), true);
        if (! isset($additional_data['nonce']) || ! wp_verify_nonce($additional_data['nonce'], 'swpm_sl_google_auth')) {
            wp_die(__('Nonce Verification Failed', 'simple-membership'));
            return;
        }

        try {
            $token_data = self::exchange_authorization_code_for_token($_GET['code']);

            // $access_token = isset($token_data['access_token']) ? $token_data['access_token'] : '';
            // $user_info = self::get_user_info_from_access_token($access_token);

            $id_token = isset($token_data['id_token']) ? $token_data['id_token'] : '';
            $user_info = self::get_user_info_from_id_token($id_token);
            $additional_data['id_token'] = $id_token;

            $auth_data = self::prepare_auth_data($user_info, $additional_data);

        } catch (\Exception $e) {
            SWPM_SL_Utils::log_auth_debug($e->getMessage());
            SWPM_SL_Auth_Response::set($e->getMessage(), SWPM_SL_Auth_Response::ERROR);

            $this->redirect_back();
        }

        do_action('swpm_sl_before_proceed_to_auth', $auth_data, $this);

        $this->authenticate($auth_data);
    }

    public static function prepare_auth_data($user_info, $additional_data = array()){
        $email = isset($user_info->email) ? $user_info->email : '';
        if (! is_email($email)) {
            throw new \Exception('Invalid email!');
        }

        $username = explode('@', $email)[0];
        $name = isset($user_info->name) ? $user_info->name : '';

        $remember = false;
        $id_token = '';
        if (isset($additional_data['remember_me'])) {
            $remember = boolval($additional_data['remember_me']);
        }
        if (isset($additional_data['id_token'])) {
            $id_token = $additional_data['id_token'];
        }

        $user_id = isset($user_info->sub) ? $user_info->sub : '';

        $auth_data = new SWPM_SL_Auth_Data();
        $auth_data->set_provider_user_id($user_id);
        $auth_data->set_provider(self::PROVIDER);
        $auth_data->set_email($email);
        $auth_data->set_name($name);
        $auth_data->set_user_name($username);
        $auth_data->set_remember_me($remember);
        $auth_data->set_id_token($id_token);

        if (isset($additional_data['referer_url'])) {
            // The url from where the login request was initiated.
            $auth_data->set_referer_url($additional_data['referer_url']);
        }

        return $auth_data;
    }

    /**
     * Exchange the authorization code for an access token
     */
    public static function exchange_authorization_code_for_token($auth_code){
        $client_id     = SWPM_SL_Utils::get_settings('google_client_id');
        $client_secret = SWPM_SL_Utils::get_settings('google_client_secret');
        $redirect_uri  = self::auth_redirect_url();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
            'code'          => $auth_code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        )));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $token_response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $token_data = json_decode($token_response, true);

        if (isset($token_data['error'])) {
            $error_msg = isset($token_data['error_description']) ? $token_data['error_description'] : __('Google access token could not be retrieved.', 'simple-membership');
            throw new \Exception($error_msg);
        }

        return $token_data;
    }

    /**
     * Use the access token to get user info
     */
    public static function get_user_info_from_access_token($access_token){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $access_token,
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $user_info_json = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);

        $user_info = json_decode($user_info_json);

        return $user_info;
    }

    /**
     * Use the id token to get user info
     */
    public static function get_user_info_from_id_token($id_token){
        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FAILONERROR => false, // we handle errors manually
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $user_info = json_decode($response);

        if ($httpCode !== 200 || isset($user_info->error_description)) {
            throw new Exception($user_info->error_description);
        }

        // REQUIRED checks
        if ($user_info->aud != SWPM_SL_Utils::get_settings('google_client_id')) {
            throw new Exception('Audience mismatch');
        }

        if ($user_info->exp < time()) {
            throw new Exception('Token expired');
        }

        if (!in_array($user_info->iss, array( 'https://accounts.google.com', 'accounts.google.com' ))) {
            throw new Exception('Invalid issuer');
        }

        // OPTIONAL but recommended
        // if (($user_info->email_verified ?? 'false') !== 'true') {
        //     throw new Exception('Email not verified');
        // }

        return $user_info;
    }

    /**
     * Constructs authentication url for google
     *
     * @param boolean $remember_me 
     * @param string $referer_url The url of form where the authentication request was initiated.
     * 
     * @return string The authentication url.
     */
    public static function generate_authentication_url($remember_me = false, $referer_url = '') {
        $client_id     = SWPM_SL_Utils::get_settings('google_client_id');
        $redirect_uri  = self::auth_redirect_url();

        $state = [
            'nonce'       => wp_create_nonce('swpm_sl_google_auth'),
            'referer_url' => $referer_url,
            'remember_me' => $remember_me,
        ];

        // Encode state
        $state_param = base64_encode(json_encode($state));

        // Scopes
        $scope = urlencode('email profile');

        // Authorization endpoint
        $auth_url = "https://accounts.google.com/o/oauth2/v2/auth" .
            "?response_type=code" .
            "&client_id=" . urlencode($client_id) .
            "&redirect_uri=" . urlencode($redirect_uri) .
            "&scope={$scope}" .
            "&state=" . urlencode($state_param) .
            "&prompt=select_account". // to force account selection
            "&access_type=online"; // or offline if you need refresh token

        return $auth_url;
    }

    public static function auth_redirect_url() {
        $redirect_url = SwpmSettings::get_instance()->get_value('login-page-url', '');

        if (!empty($redirect_url)) {
            $redirect_url = add_query_arg(
                array(
                    'swpm-google-login' => 1,
                ),
                trailingslashit(sanitize_url($redirect_url))
            );
        }

        return $redirect_url;
    }
}
