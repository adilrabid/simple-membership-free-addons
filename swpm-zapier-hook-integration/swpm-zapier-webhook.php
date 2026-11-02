<?php
/*
* Plugin Name: SWPM Zapier Webhook Integration
* Description: Sends member's username and email to Zapier when a user completes registration via Simple Membership plugin.
* Version: 1.2
* Author: wp.insider
* Author URI: https://simple-membership-plugin.com
* License: GPL2
*/

//slug - swpm_zapier_

//Direct access to this file is not permitted
if (!defined('ABSPATH')) {
    exit; //Exit if accessed directly
}

define('SWPM_ZAPIER_VER', '1.2');

add_action('plugins_loaded', 'swpm_zapier_plugins_loaded');

function swpm_zapier_plugins_loaded() {
    if (!class_exists('SimpleWpMembership')) {
        return; //Simple Membership is not active.
    }

    //Settings (Simple Membership > Settings > Addon Settings).
    add_action('swpm_addon_settings_section', 'swpm_zapier_settings_ui');
    add_action('swpm_addon_settings_save', 'swpm_zapier_settings_save');

    add_action('swpm_front_end_registration_complete_user_data', 'swpm_send_data_to_zapier_webhook');//For standard registrations
    add_action('swpm_front_end_registration_complete_fb', 'swpm_send_data_to_zapier_webhook');//For registrations via Form Builder (if used)
}

//Add settings link in plugins listing page
function swpm_zapier_add_settings_link($links, $file) {
    if ($file == plugin_basename(__FILE__)) {
        $settings_link = '<a href="admin.php?page=simple_wp_membership_settings&tab=7">' . __('Settings', 'simple-membership') . '</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'swpm_zapier_add_settings_link', 10, 2);

function swpm_zapier_settings_ui() {
    $webhook_url = SwpmSettings::get_instance()->get_value('swpm-addon-zapier-webhook-url');
    ?>
    <h3><?php _e('Zapier Webhook Addon Settings', 'simple-membership'); ?></h3>
    <p><?php _e('Read the', 'simple-membership'); ?> <a href="https://simple-membership-plugin.com/zapier-webhook-integration-for-simple-membership-plugin/" target="_blank"><?php _e('usage documentation', 'simple-membership'); ?></a> <?php _e('to learn how to use the Zapier Webhook Addon', 'simple-membership'); ?></p>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Zapier Webhook URL', 'simple-membership'); ?></th>
                <td>
                    <input name="swpm-addon-zapier-webhook-url" type="url" size="80" value="<?php echo esc_attr($webhook_url); ?>" placeholder="https://hooks.zapier.com/hooks/catch/..." />
                    <p class="description"><?php _e('The "Catch Hook" URL of your Zap (Webhooks by Zapier). The username, email and name of each new member is sent to this URL when they complete registration. Leave it empty to stop sending data.', 'simple-membership'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

function swpm_zapier_settings_save() {
    if (!isset($_POST['swpm-addon-zapier-webhook-url'])) {
        return;
    }
    $webhook_url = esc_url_raw(trim(wp_unslash($_POST['swpm-addon-zapier-webhook-url'])), array('https'));

    $settings = SwpmSettings::get_instance();
    $settings->set_value('swpm-addon-zapier-webhook-url', $webhook_url);
    $settings->save();

    $message = array(
        'succeeded' => true,
        'message'   => '<p>' . __('Zapier Webhook Settings Updated!', 'simple-membership') . '</p>',
    );
    SwpmTransfer::get_instance()->set('status', $message);
}

function swpm_send_data_to_zapier_webhook($member_data) {
    $zapier_webhook_url = SwpmSettings::get_instance()->get_value('swpm-addon-zapier-webhook-url');
    //Lets developers set or change the URL in code (for example a different Zap per membership level).
    $zapier_webhook_url = apply_filters('swpm_zapier_webhook_url', $zapier_webhook_url, $member_data);

    //Not configured yet (empty, or the placeholder URL from version 1.0/1.1): don't send anything.
    if (empty($zapier_webhook_url) || strpos($zapier_webhook_url, 'XXXXXXX') !== false) {
        SwpmLog::log_simple_debug('[Zapier Addon] The Zapier webhook URL is not set (Settings > Addon Settings). No data sent.', false);
        return;
    }

    //Form Builder forms may not have all the fields.
    $first_name = isset($member_data['first_name']) ? $member_data['first_name'] : '';
    $last_name  = isset($member_data['last_name']) ? $member_data['last_name'] : '';

    // Prepare the data to send to Zapier
    $payload = array(
        'username'     => isset($member_data['user_name']) ? $member_data['user_name'] : '',
        'member_email' => isset($member_data['email']) ? $member_data['email'] : '',
        'member_name'  => trim($first_name . ' ' . $last_name),
    );

    // Send the webhook using wp_remote_post
    $response = wp_remote_post($zapier_webhook_url, array(
        'method'    => 'POST',
        'timeout'   => 20,
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => json_encode($payload),
    ));

    if (is_wp_error($response)) {
        SwpmLog::log_simple_debug('[Zapier Addon] Failed to send data to the Zapier webhook URL: ' . $zapier_webhook_url . '. Error: ' . $response->get_error_message(), false);
        return;
    }
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code < 200 || $response_code >= 300) {
        SwpmLog::log_simple_debug('[Zapier Addon] The Zapier webhook URL returned HTTP ' . $response_code . ': ' . $zapier_webhook_url . '. Response: ' . wp_remote_retrieve_body($response), false);
        return;
    }

    SwpmLog::log_simple_debug('Data sent to Zapier webhook URL: ' . $zapier_webhook_url, true);
    SwpmLog::log_array_data_to_debug($payload, true);
}
