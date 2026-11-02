<?php
/*
* Plugin Name: SWPM Zapier Webhook Integration
* Description: Sends member's username and email to Zapier when a user completes registration via Simple Membership plugin.
* Version: 1.0
* Author: wp.insider
* Author URI: https://simple-membership-plugin.com
* License: GPL2
*/

add_action('swpm_front_end_registration_complete_user_data', 'swpm_send_data_to_zapier_webhook');//For standard registrations
add_action('swpm_front_end_registration_complete_fb', 'swpm_send_data_to_zapier_webhook');//For registrations via Form Builder (if used)

function swpm_send_data_to_zapier_webhook($member_data) {
    //TODO - Replace this with your actual Zapier webhook URL
    $zapier_webhook_url = 'https://hooks.zapier.com/hooks/catch/XXXXXXX/XXXXXXXX';

    // Prepare the data to send to Zapier
    $payload = array(
        'username'      => $member_data['user_name'],
        'member_email' => $member_data['email'],
        'member_name'  => $member_data['first_name'] . ' ' . $member_data['last_name'],
    );

    // Send the webhook using wp_remote_post
    wp_remote_post($zapier_webhook_url, array(
        'method'    => 'POST',
        'timeout'   => 20,
        'headers'   => array('Content-Type' => 'application/json'),
        'body'      => json_encode($payload),
    ));
    SwpmLog::log_simple_debug('Data sent to Zapier webhook URL: ' . $zapier_webhook_url, true);
    SwpmLog::log_array_data_to_debug($payload);
}
