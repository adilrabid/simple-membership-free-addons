<?php

class SWPM_SL_Auth_Response {
    const SUCCESS = 'SUCCESS';
    const ERROR = 'ERROR';
    const INFO = 'INFO';

    public static function set($message, $type, $extra = null) {
        SwpmTransfer::get_instance()->set('swpm_sl_response', array(
            'message' => $message,
            'type' => $type,
            'extra' => $extra,
        ));
    }

    public static function get() {
        $message = SwpmTransfer::get_instance()->get('swpm_sl_response');
        if (empty($message)) {
            return false;
        }

        $output = '';

        switch ($message['type']) {
            case SWPM_SL_Auth_Response::SUCCESS:
                $output .= '<div class="swpm-sl-response swpm-sl-response-success">';
                break;
            case SWPM_SL_Auth_Response::ERROR:
                $output .= '<div class="swpm-sl-response swpm-sl-response-error">';
                break;
            case SWPM_SL_Auth_Response::INFO:
                $output .= '<div class="swpm-sl-response swpm-sl-response-info">';
                break;
            default:
                // neutral
                $output .= '<div class="swpm-sl-response">';
                break;
        }

        $output .= $message['message'];

        $extra = isset($message['extra']) ? $message['extra'] : array();
        if (is_string($extra)) {
            $output .= $extra;
        } else if (is_array($extra) && ! empty($extra)) {
            $output .= '<ol>';
            foreach ($extra as $key => $value) {
                $output .= '<li>' . $value . '</li>';
            }
            $output .= '</ol>';
        }

        $output .= '</div>';

        return $output;
    }
}
