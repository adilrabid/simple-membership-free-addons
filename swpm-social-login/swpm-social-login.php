<?php
/*
Plugin Name: SWPM Social Login
Description: Integrates Google, Facebook, and other social login options for Simple Membership users. Enables quick registration & sign-in via social accounts.
Plugin URI: https://simple-membership-plugin.com/simple-membership-social-login-addon/
Author: wp.insider
Author URI: https://simple-membership-plugin.com/
Version: 1.0.5
*/

//Exit if accessed directly
if (! defined('ABSPATH')) {
    exit();
}

define('SWPM_SL_VER', '1.0.5');
define('SWPM_SL_SITE_HOME_URL', home_url());
define('SWPM_SL_PATH', dirname(__FILE__) . '/');
define('SWPM_SL_URL', plugins_url('', __FILE__));
define('SWPM_SL_DIRNAME', dirname(plugin_basename(__FILE__)));

// Add settings link in plugins listing page
function swpm_sl_add_settings_link($links, $file) {
    if ($file == plugin_basename(__FILE__)) {
        $settings_link = '<a href="admin.php?page=swpm_sl_settings">Settings</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'swpm_sl_add_settings_link', 10, 2);

require_once SWPM_SL_PATH . '/includes/class-swpm-sl-utils.php';
require_once SWPM_SL_PATH . '/class-swpm-sl-core.php';
new SWPM_SL_Core();