<?php

class SWPM_SL_Button_Renderer {
    public function __construct() {
        add_action('wp_ajax_swpm_sl_get_auth_url', array($this, 'get_social_auth_url'));
        add_action('wp_ajax_nopriv_swpm_sl_get_auth_url', array($this, 'get_social_auth_url'));

        add_filter('swpm_after_login_form_output', array($this, 'render_social_login_buttons'));
    }

    public function render_social_login_buttons($content) {
        $social_buttons = array();

        // Add social login buttons here...

        $enable_google_login = SWPM_SL_Utils::get_settings('enable_google_login');
        if (! empty($enable_google_login)) {
            $social_buttons[] = array(
                'provider' => 'google',
                'icon_url' => SWPM_SL_URL . "/assets/img/google_logo.svg",
                'button_text' => __('Login with Google', 'simple-membership'),
                'class_names' => 'swpm_sl_google_login_btn',
            );
        }

        $enable_facebook_login = SWPM_SL_Utils::get_settings('enable_facebook_login');
        if (! empty($enable_facebook_login)) {
            $social_buttons[] = array(
                'provider' => 'facebook',
                'icon_url' => SWPM_SL_URL . "/assets/img/facebook_logo.svg",
                'button_text' => __('Login with Facebook', 'simple-membership'),
                'class_names' => 'swpm_sl_facebook_login_btn',
            );
        }

        $social_buttons = apply_filters('swpm_sl_social_login_buttons', $social_buttons);

        if (! empty($social_buttons)) {
            $response = SWPM_SL_Auth_Response::get();
            ob_start()
            ?>
            <div class="swpm_sl_login_buttons_section">
                <div class="swpm_sl_login_section_header"><?php _e('Or', 'simple-membership') ?></div>
                <div class="swpm_sl_login_buttons_wrap">
                    <?php foreach ($social_buttons as $sl_btn) { ?>
                        <button class="swpm_sl_login_btn <?php echo esc_attr($sl_btn['class_names']) ?>" data-provider="<?php echo esc_attr($sl_btn['provider']) ?>">
                            <img src="<?php echo esc_attr($sl_btn['icon_url']) ?>" class="swpm_sl_login_btn_icon" alt="<?php echo esc_attr($sl_btn['provider']) ?>">
                            <span class="swpm_sl_login_btn_text">
                                <?php echo esc_html($sl_btn['button_text']) ?>
                            </span>
                        </button>
                    <?php } ?>
                </div>
                <?php if (! empty($response)) { ?>
                    <div class="swpm_sl_login_response_wrap">
                        <?php echo wp_kses_post($response) ?>
                    </div>
                <?php } ?>
            </div>
            <?php
            $content .= ob_get_clean();
            return $content;
        }
    }

    /**
     * Construct and retrieve the social authentication url with custom data (remember_me, referer etc.)
     */
    public function get_social_auth_url() {
        if (!check_ajax_referer('swpm_sl_ajax_nonce', 'swpm_sl_nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Nonce validation failed!', 'simple-membership'),
            ));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $remember_me = isset($_POST['remember_me']) ? boolval($_POST['remember_me']) : false;
        $referer_url = isset($_POST['referer_url']) ? sanitize_url($_POST['referer_url']) : '';

        $auth_url = '';

        switch ($provider) {
            case 'facebook':
                $enable_facebook_login = SWPM_SL_Utils::get_settings('enable_facebook_login');
                if (!empty($enable_facebook_login)) {
                    $auth_url = SWPM_SL_Auth_Facebook::generate_authentication_url($remember_me, $referer_url);
                }
                break;
            case 'google':
                $enable_google_login = SWPM_SL_Utils::get_settings('enable_google_login');
                if (!empty($enable_google_login)) {
                    $auth_url = SWPM_SL_Auth_Google::generate_authentication_url($remember_me, $referer_url);
                }
                break;
        }

        if (empty($auth_url)) {
            wp_send_json_error(array(
                'message' => __('Authentication url could not be generated!', 'simple-membership'),
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Authentication url generated successfully!', 'simple-membership'),
            'auth_url' => $auth_url,
        ));
    }
}
