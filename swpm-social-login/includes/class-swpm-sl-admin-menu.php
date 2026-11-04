<?php

class SWPM_SL_Admin_Menu {
    public $current_tab;
    public $tabs = array();

    public function __construct() {
        add_action('swpm_after_main_admin_menu', array($this, 'swpm_sl_add_admin_menu'));
    }

    public function swpm_sl_add_admin_menu($menu_parent_slug) {
        add_submenu_page(
            $menu_parent_slug,
            __("Social login", 'simple-membership'),
            __("Social Login", 'simple-membership'),
            'manage_options',
            'swpm_sl_settings',
            array($this, 'swpm_sl_admin_menu_page')
        );
    }

    public function swpm_sl_admin_menu_page() {
        //Check current_user_can() or die.
        SwpmMiscUtils::check_user_permission_and_is_admin('Main Settings Menu');

        //Read the value of tab query arg.
        $tab = isset($_REQUEST['tab']) ? sanitize_text_field($_REQUEST['tab']) : 1;
        $this->current_tab = empty($tab) ? 1 : $tab;

        //Setup the available settings tabs array.
        $this->tabs = array(
            1 => __('General', 'simple-membership'),
            2 => __('Google', 'simple-membership'),
            3 => __('Facebook', 'simple-membership'),
        );

        // Check if login page URL is configured. Login page URL is required for social login to work.
        $login_page_url = SwpmSettings::get_instance()->get_value('login-page-url', '');
        if (empty($login_page_url)) {
            //Login page URL is not configured. Show error message and exit.
            echo '<div class="swpm-red-box">';
            _e('Error: The Login Page URL is missing. It is a ', 'simple-membership');
            echo '<a href="https://simple-membership-plugin.com/recreating-required-pages-simple-membership-plugin/" target="_blank">' . __('required page', 'simple-membership') . '</a>';
            _e('. Go to the General Settings menu of the Simple Membership plugin to configure the login page URL and then you will be able to configure the Social Login feature.', 'simple-membership');
            echo '</div>';
            return;
        }

?>
        <div class="wrap swpm-admin-menu-wrap"><!-- start wrap -->
            <!-- page title -->
            <h1><?php _e('Social Login'); ?></h1>

            <!-- start nav menu tabs -->
            <h2 class="nav-tab-wrapper">
                <?php foreach ($this->tabs as $id => $label) { ?>
                    <a class="nav-tab <?php echo ($this->current_tab == $id) ? 'nav-tab-active' : ''; ?>" href="admin.php?page=swpm_sl_settings&tab=<?php echo $id; ?>"><?php echo $label; ?></a>
                <?php } ?>
            </h2>
            <!-- end nav menu tabs -->

            <?php

            //Switch to handle the body of each of the various settings pages based on the currently selected tab
            $current_tab = $this->current_tab;
            switch ($current_tab) {
                case 3:
                    // Facebook
                    $this->facebook_settings_tab_content();
                    break;
                case 2:
                    // google
                    $this->google_settings_tab_content();
                    break;
                case 1:
                default:
                    //The default fallback (general)
                    $this->general_settings_tab_content();
                    break;
            }
            ?>
        </div><!-- end of wrap -->
    <?php
    }

    public function general_settings_tab_content() {
        if (isset($_POST['swpm_sl_save_settings'])) {

            if (!check_admin_referer('swpm_sl_save_general_settings')) {
                wp_die(__('Nonce verification failed!', 'simple-membership'));
            }

            $settings = get_option('swpm_sl_settings', array());

            $settings['enable_sl_auto_registration'] = isset($_POST["enable_sl_auto_registration"]) ? '1' : '';
            $settings['auto_registration_membership_level'] = isset($_POST["auto_registration_membership_level"]) ? sanitize_text_field($_POST["auto_registration_membership_level"]) : '';

            update_option('swpm_sl_settings', $settings);

            echo '<div id="message" class="notice notice-success">';
            echo '<p>' . __('Settings Saved!', 'simple-membership') . '</p>';
            echo '</div>';
        }

        $settings = get_option('swpm_sl_settings', array());

        $enable_sl_auto_registration = isset($settings['enable_sl_auto_registration']) && !empty($settings['enable_sl_auto_registration']) ? ' checked="checked"' : '';
        $auto_registration_membership_level = isset($settings['auto_registration_membership_level']) && !empty($settings['auto_registration_membership_level']) ? absint(sanitize_text_field($settings['auto_registration_membership_level'])) : '';
        $levels = SwpmMembershipLevelUtils::get_all_membership_levels_in_array();

		echo '<div class="swpm-grey-box">';
		echo '<p>';
		_e( 'This addon allows you to configure social login for Simple Membership plugin. Read the ', 'simple-membership' );
		echo '<a href="https://simple-membership-plugin.com/simple-membership-social-login-addon/" target="_blank">' . __( 'Social Login Addon Documentation' ) . '</a>';
		_e( ' for setup instructions and details.', 'simple-membership' );
		echo '</p>';
		echo '</div>';

        ?>

        <form action="" method="post">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Enable Automatic Account Creation', 'simple-membership') ?></th>
                    <td>
                        <input name="enable_sl_auto_registration" type="checkbox" <?php echo esc_attr($enable_sl_auto_registration) ?> value="1" />
                        <p class="description"><?php _e('When enabled, members who log in via a social provider (example: Google, Facebook) and do not have an existing account will automatically have a new membership account created for them using the email address from the social profile.', 'simple-membership') ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <?php _e('Default Membership Level for New Accounts', 'simple-membership'); ?>
                    </th>
                    <td>
                        <select name="auto_registration_membership_level">
                            <?php foreach ($levels as $level_id => $level_alias) { ?>
                                <option <?php echo ($level_id == $auto_registration_membership_level) ? "selected='selected'" : ""; ?> value="<?php echo esc_attr($level_id); ?>"><?php echo esc_attr($level_alias) ?></option>
                            <?php } ?>
                        </select>
                        <p class="description"><?php _e('Select the membership level to which all new users created through the auto-registration process will be assigned. This will be their initial level upon account creation.', 'simple-membership') ?></p>
                    </td>
                </tr>
            </table>

            <?php wp_nonce_field('swpm_sl_save_general_settings') ?>

            <input type="submit" name="swpm_sl_save_settings" value="Save" class="button-primary" />
        </form>
    <?php
    }

    public function google_settings_tab_content() {
        if (isset($_POST['swpm_sl_save_settings'])) {

            if (!check_admin_referer('swpm_sl_save_google_settings')) {
                wp_die(__('Nonce verification failed!', 'simple-membership'));
            }

            $settings = get_option('swpm_sl_settings', array());

            $settings['enable_google_login'] = isset($_POST["enable_google_login"]) ? '1' : '';
            $settings['google_client_id'] = isset($_POST["google_client_id"]) ? sanitize_text_field($_POST["google_client_id"]) : '';
            $settings['google_client_secret'] = isset($_POST["google_client_secret"]) ? sanitize_text_field($_POST["google_client_secret"]) : '';

            update_option('swpm_sl_settings', $settings);

            echo '<div id="message" class="notice notice-success">';
            echo '<p>' . __('Settings Saved!', 'simple-membership') . '</p>';
            echo '</div>';
        }

        $settings = get_option('swpm_sl_settings', array());

        $enable_google_login = isset($settings['enable_google_login']) && !empty($settings['enable_google_login']) ? ' checked="checked"' : '';
        $google_client_id = isset($settings['google_client_id']) ? sanitize_text_field($settings['google_client_id']) : '';
        $google_client_secret = isset($settings['google_client_secret']) ? sanitize_text_field($settings['google_client_secret']) : '';
        $redirect_uri = SWPM_SL_Auth_Google::auth_redirect_url();
        ?>

        <form action="" method="post">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Enable Google Login', 'simple-membership') ?></th>
                    <td>
                        <input name="enable_google_login" type="checkbox" <?php echo esc_attr($enable_google_login) ?> value="1" />
                        <p class="description"><?php _e('Check this box to allow members to log in and register using their Google account. You must enter your Google Client ID and Client Secret below for this feature to work.', 'simple-membership') ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('Google Client ID', 'simple-membership') ?></th>
                    <td>
                        <input name="google_client_id" type="text" value="<?php echo esc_attr($google_client_id) ?>" size="75%" />
                        <p class="description"><?php _e('Enter the Client ID obtained from your Google Cloud Console project.', 'simple-membership') ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('Google Client Secret', 'simple-membership') ?></th>
                    <td>
                        <input name="google_client_secret" type="text" value="<?php echo esc_attr($google_client_secret) ?>" size="75%" />
                        <p class="description"><?php _e('Enter the Client Secret obtained from your Google Cloud Console.', 'simple-membership') ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('Authorized Redirect URI', 'simple-membership') ?></th>
                    <td>
                        <input name="google_auth_redirect_url" type="url" value="<?php echo esc_url($redirect_uri) ?>" size="75%" readonly onfocus="this.select()"/>
                        <p class="description"><?php _e('You must copy this URL and paste it into the "Authorized redirect URIs" field within your Google Cloud Console project settings. This step is required for Google to securely return the user to your site after successful authentication.', 'simple-membership') ?></p>
                    </td>
                </tr>

            </table>

            <?php wp_nonce_field('swpm_sl_save_google_settings') ?>

            <input type="submit" name="swpm_sl_save_settings" value="Save" class="button-primary" />
        </form>
    <?php
    }

    public function facebook_settings_tab_content() {
        if (isset($_POST['swpm_sl_save_settings'])) {
            if (!check_admin_referer('swpm_sl_save_facebook_settings')) {
                wp_die(__('Nonce verification failed!', 'simple-membership'));
            }

            $settings = get_option('swpm_sl_settings', array());

            $settings['enable_facebook_login'] = isset($_POST["enable_facebook_login"]) ? '1' : '';
            $settings['facebook_app_id'] = isset($_POST["facebook_app_id"]) ? sanitize_text_field($_POST["facebook_app_id"]) : '';
            $settings['facebook_app_secret'] = isset($_POST["facebook_app_secret"]) ? sanitize_text_field($_POST["facebook_app_secret"]) : '';

            update_option('swpm_sl_settings', $settings);

            echo '<div id="message" class="notice notice-success">';
            echo '<p>' . __('Settings Saved!', 'simple-membership') . '</p>';
            echo '</div>';
        }

        $settings = get_option('swpm_sl_settings', array());

        $enable_facebook_login = isset($settings['enable_facebook_login']) && !empty($settings['enable_facebook_login']) ? ' checked="checked"' : '';
        $facebook_app_id = isset($settings['facebook_app_id']) ? sanitize_text_field($settings['facebook_app_id']) : '';
        $facebook_app_secret = isset($settings['facebook_app_secret']) ? sanitize_text_field($settings['facebook_app_secret']) : '';
        $facebook_redirect_url = SWPM_SL_Auth_Facebook::auth_redirect_url();
        ?>

        <form action="" method="post">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Enable facebook Login', 'simple-membership') ?></th>
                    <td>
                        <input name="enable_facebook_login" type="checkbox" <?php echo esc_attr($enable_facebook_login) ?> value="1" />
                        <p class="description"><?php _e('Check this box to allow members to log in and register using their Facebook account. You must enter your Facebook App ID and App Secret below for this feature to work.', 'simple-membership') ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('Facebook App ID', 'simple-membership') ?></th>
                    <td>
                        <input name="facebook_app_id" type="text" value="<?php echo esc_attr($facebook_app_id) ?>" size="75%" />
                        <p class="description"><?php _e('Enter the App ID from your Meta for Developers application dashboard. This is the unique public identifier for your app.', 'simple-membership') ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('Facebook App Secret', 'simple-membership') ?></th>
                    <td>
                        <input name="facebook_app_secret" type="text" value="<?php echo esc_attr($facebook_app_secret) ?>" size="75%" />
                        <p class="description"><?php _e('Enter the App Secret from your Meta for Developers dashboard. This key authenticates your site with Facebook.', 'simple-membership') ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('Valid OAuth Redirect URI', 'simple-membership') ?></th>
                    <td>
                        <input name="facebook_auth_redirect_url" type="url" value="<?php echo esc_url($facebook_redirect_url) ?>" size="75%" readonly onfocus="this.select()"/>
                        <p class="description"><?php _e('You must copy this URL and paste it into the "Valid OAuth Redirect URIs" field within your Meta for Developers App settings.', 'simple-membership') ?></p>
                    </td>
                </tr>
            </table>

            <?php wp_nonce_field('swpm_sl_save_facebook_settings') ?>

            <input type="submit" name="swpm_sl_save_settings" value="Save" class="button-primary" />
        </form>
    <?php
    }
}
