<?php

class SWPM_SL_Core {
    public function __construct() {
        // Hook into WordPress actions.
        add_action('init', array($this, 'handle_init'));
        add_action('wp_enqueue_scripts', array($this, 'handle_wp_enqueue_scripts'));

        // Show connected social accounts in profile edit pages.
        add_filter('swpm_admin_edit_member_extra_rows', array($this, 'show_connected_social_account_admin_end'), 10, 2);
        add_filter('swpm_edit_profile_form_before_submit', array($this, 'show_connected_social_account_front_end'));
        add_filter('swpm_fb_edit_profile_form_before_fieldset_end', array($this, 'show_connected_social_account_fb_front_end'));
    }

    public function handle_init() {
        if (!SWPM_SL_Utils::is_plugin_active( 'simple-membership/simple-wp-membership.php' )){
            // wp_die('swpm is not active.');
            return;
        }

        require_once SWPM_SL_PATH . '/includes/class-swpm-sl-admin-menu.php';
        require_once SWPM_SL_PATH . '/includes/class-swpm-sl-auth.php';
        require_once SWPM_SL_PATH . '/includes/class-swpm-sl-auth-response.php';
        require_once SWPM_SL_PATH . '/includes/class-swpm-sl-auth-data.php';
        require_once SWPM_SL_PATH . '/includes/class-swpm-sl-auth-google.php';
        require_once SWPM_SL_PATH . '/includes/class-swpm-sl-auth-facebook.php';
        require_once SWPM_SL_PATH . '/includes/class-swpm-sl-registration.php';
        require_once SWPM_SL_PATH . '/includes/class-swpm-sl-button-renderer.php';

        //This will initialize the button renderer.
        new SWPM_SL_Button_Renderer();

        //This will use the 'swpm_after_main_admin_menu' hook to initialize the admin menu.
        new SWPM_SL_Admin_Menu();
        
        // This will check for direct link login requests and process them.
        $this->process_login_request_via_direct_link();

        //This will check for auth requests and process them.
        $this->process_auth_request();
    }

    public function process_login_request_via_direct_link(){
        if (isset($_GET['swpm_social_login']) && !empty($_GET['swpm_social_login'])) {

            if (SwpmMemberUtils::is_member_logged_in()) {
                // User is already logged in, no need to process login request.
                return;
            }

            $provider = sanitize_text_field($_GET['swpm_social_login']);
            
            $referer_url = SwpmSettings::get_instance()->get_value('login-page-url', SIMPLE_WP_MEMBERSHIP_SITE_HOME_URL);

            if(isset($_GET['swpm_redirect_to']) && !empty($_GET['swpm_redirect_to'])){ // For alr addon compatibility.
                $referer_url = add_query_arg('swpm_redirect_to', urlencode($_GET['swpm_redirect_to']), $referer_url);
            }

            $auth_url = '';
            switch ($provider) {
                case 'google':
                    $enable_google_login = SWPM_SL_Utils::get_settings('enable_google_login');
                    if (!empty($enable_google_login)) {
                        $auth_url = SWPM_SL_Auth_Google::generate_authentication_url(false, $referer_url);
                    }
                    break;
                case 'facebook':
                    $enable_facebook_login = SWPM_SL_Utils::get_settings('enable_facebook_login');
                    if (!empty($enable_facebook_login)) {
                        $auth_url = SWPM_SL_Auth_Facebook::generate_authentication_url(false, $referer_url);
                    }
                    break;
                default:
                    break;
            }

            if (!empty($auth_url)) {
                wp_redirect($auth_url);
                exit;   
            }
        }
    }

    public function process_auth_request() {
        if (isset($_GET['swpm-google-login'])) {
            new SWPM_SL_Auth_Google();
        }

        if (isset($_GET['swpm-facebook-login'])) {
            new SWPM_SL_Auth_Facebook();
        }
    }

    public function handle_wp_enqueue_scripts() {
        wp_enqueue_script('swpm-sl-public', SWPM_SL_URL . "/assets/js/swpm-sl-public.js", array(), SWPM_SL_VER, array(
            'in_footer' => true,
            'strategy' => 'defer'
        ));

        wp_localize_script('swpm-sl-public', 'swpm_sl_data', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('swpm_sl_ajax_nonce'),
        ));

        wp_enqueue_style('swpm-sl-public', SWPM_SL_URL . "/assets/css/swpm-sl-public.css", array(), SWPM_SL_VER, 'all');
    }

    public function show_connected_social_account_admin_end($output, $member_id){
        $linked_accounts = SWPM_SL_Utils::get_linked_social_accounts($member_id);
        if(empty($linked_accounts)){
            return $output;
        }

        $output .= '<tr>';
        $output .= '<th>' . __('Connected Social Account(s)', 'simple-membership') . '</th>';
        $output .= '<td class="swpm-sl-connected-social-accounts-wrap">';
        foreach($linked_accounts as $account){
            $output .= '<div class="swpm-sl-connected-social-account-line" style="margin-bottom: 5px;">';
            $output .= esc_attr($account['text']) .': ' . esc_html($account['email']);
            $output .= '</div>';
        }
        $output .= '<p class="description">';
        $output .= __('Any social accounts connected through the ', 'simple-membership') . '<a href="https://simple-membership-plugin.com/simple-membership-social-login-addon/" target="_blank">' . __('Social Login Addon', 'simple-membership') . '</a>';
        $output .= __(' will be displayed here.','simple-membership');
        $output .= '</p>';
        $output .= '</td>';
        $output .= '</tr>';

        return $output;
    }
    
    public function show_connected_social_account_front_end($output){
        $member_id = SwpmMemberUtils::get_logged_in_members_id();
        $member_id = is_numeric($member_id) ? intval($member_id) : 0;
        $linked_accounts = SWPM_SL_Utils::get_linked_social_accounts($member_id);
        if(empty($linked_accounts)){
            return $output;
        }

        $output .= '<div class="swpm-form-row swpm-social-accounts-row">';
        $output .= '<div class="swpm-form-label-wrap swpm-form-social-accounts-label-wrap">';
        $output .= '<label class="swpm-label">';
        $output .= __('Connected Social Account(s)', 'simple-membership');
        $output .= '</label>';
        $output .= '</div>';
        $output .= '<div class="swpm-form-input-wrap swpm-sl-connected-social-accounts-wrap">';
        foreach($linked_accounts as $account){
            $output .= '<div class="swpm-sl-connected-social-account-line">';
            $output .= esc_attr($account['text']) .': ' . esc_html($account['email']);
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    public function show_connected_social_account_fb_front_end($output){
        $member_id = SwpmMemberUtils::get_logged_in_members_id();
        $member_id = is_numeric($member_id) ? intval($member_id) : 0;
        $linked_accounts = SWPM_SL_Utils::get_linked_social_accounts($member_id);
        if(empty($linked_accounts)){
            return $output;
        }

        $output .= '<ul class="swpm-section swpm-social-accounts-fb-section">';
        $output .= '<li class="swpm-item">';
        $output .= '<label class="swpm-desc">';
        $output .= __('Connected Social Account(s)', 'simple-membership');
        $output .= '</label>';
        $output .= '<div class="swpm-sl-connected-social-accounts-wrap">';
        foreach($linked_accounts as $account){
            $output .= '<div class="swpm-sl-connected-social-account-line">';
            $output .= esc_attr($account['text']) .': ' . esc_html($account['email']);
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</li>';
        $output .= '</ul>';

        return $output;
    }
}
