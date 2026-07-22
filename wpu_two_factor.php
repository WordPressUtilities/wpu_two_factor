<?php
defined('ABSPATH') || die;

/*
Plugin Name: WPU Two Factor
Plugin URI: https://github.com/WordPressUtilities/wpu_two_factor
Update URI: https://github.com/WordPressUtilities/wpu_two_factor
Description: Additional features for the official two factor plugin
Version: 0.2.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_two_factor
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
Domain Path: /lang
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class wpu_two_factor {

    public $messages;
    private $options = array(
        'plugin_id' => 'wpu_two_factor'
    );

    private $disabled_providers = array(
        'Two_Factor_Dummy'
    );

    public function __construct() {
        add_action('init', array(&$this, 'init'));

        /* Load translations */
        add_action('init', array(&$this, 'load_translations'));

        /* Load messages */
        add_action('init', array(&$this, 'load_messages'));
    }

    public function init() {
        if (!class_exists('Two_Factor_Core')) {
            return;
        }

        /* Disable some providers */
        add_filter('two_factor_providers', array(&$this, 'custom_providers'));

        $notice_method = apply_filters('wpu_two_factor_notice_method', 'none');

        /* Notice when the current user has no active 2FA method */
        switch ($notice_method) {
        case 'admin_notices':
            add_action('admin_notices', array(&$this, 'no_2fa_notice'));
            break;
        case 'modal':
            add_action('admin_footer', array(&$this, 'no_2fa_modal'));
            break;
        default:
        }

    }

    /* ----------------------------------------------------------
      Dependencies
    ---------------------------------------------------------- */

    public function load_messages() {
        require_once __DIR__ . '/inc/WPUBaseMessages/WPUBaseMessages.php';
        $this->messages = new \wpu_two_factor\WPUBaseMessages($this->options['plugin_id']);
    }

    public function load_translations() {
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (strpos(__DIR__, 'mu-plugins') !== false) {
            load_muplugin_textdomain('wpu_two_factor', $lang_dir);
        } else {
            load_plugin_textdomain('wpu_two_factor', false, $lang_dir);
        }
        __('Additional features for the official two factor plugin', 'wpu_two_factor');
    }

    /* ----------------------------------------------------------
      Alert : Notice
    ---------------------------------------------------------- */

    public function no_2fa_notice() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $user_status = $this->user_get_two_factor_infos($user_id);
        $user_profile_link = sprintf('<a href="%s">%s</a>', esc_url($user_status['user_profile_url']), esc_html($user_status['user_profile_label']));

        if (!$user_status['enabled']) {
            echo $this->messages->get_message_html(__('You have not enabled any two-factor authentication method.', 'wpu_two_factor') . ' ' . $user_profile_link, 'error');
            return;
        }

        if ($user_status['has_disabled']) {
            echo $this->messages->get_message_html(__('You are using a two-factor authentication method that is no longer allowed.', 'wpu_two_factor') . ' ' . $user_profile_link, 'error');
            return;
        }
    }

    /* ----------------------------------------------------------
      Alert : Modal
    ---------------------------------------------------------- */

    public function no_2fa_modal() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        if(function_exists('get_current_screen')) {
            $scr = get_current_screen();
            if($scr && $scr->base === 'profile') {
                return;
            }
        }

        $user_status = $this->user_get_two_factor_infos($user_id);
        $user_profile_link = sprintf('<br /><br /><a class="button button-primary" href="%s">%s</a>', esc_url($user_status['user_profile_url']), esc_html($user_status['user_profile_label']));

        if (!$user_status['enabled']) {
            echo $this->get_modal_html(__('You have not enabled any two-factor authentication method.', 'wpu_two_factor') . ' ' . $user_profile_link);
            return;
        }

        if ($user_status['has_disabled']) {
            echo $this->get_modal_html(__('You are using a two-factor authentication method that is no longer allowed.', 'wpu_two_factor') . ' ' . $user_profile_link);
            return;
        }
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    public function user_get_two_factor_infos($user_id) {
        if (!$user_id) {
            return array(
                'enabled' => false,
                'has_disabled' => false
            );
        }

        $user_details = array(
            'user_profile_url' => get_edit_profile_url($user_id) . '#two-factor-options',
            'user_profile_label' => esc_html__('Configure now', 'wpu_two_factor')
        );

        $enabled = get_user_meta($user_id, '_two_factor_enabled_providers', true);
        $enabled = is_array($enabled) ? $enabled : array();

        $user_details['enabled'] = (bool) $enabled;
        $user_details['has_disabled'] = (bool) array_intersect($enabled, $this->disabled_providers);
        return $user_details;
    }

    public function custom_providers($providers) {
        foreach ($this->disabled_providers as $provider) {
            if (isset($providers[$provider])) {
                unset($providers[$provider]);
            }
        }
        return $providers;
    }

    /* ----------------------------------------------------------
      Modal HTML
    ---------------------------------------------------------- */

    public function get_modal_html($message = '') {

        $html = '<div id="wpu-two-factor-modal" class="wpu-two-factor-modal">';
        $html .= '<div class="wpu-two-factor-modal-content">';
        $html .= '<span class="wpu-two-factor-modal-close">&times;</span>';
        $html .= '<p>' . wp_kses_post($message) . '</p>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<style>
            .wpu-two-factor-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
            .wpu-two-factor-modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; }
            .wpu-two-factor-modal-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
            .wpu-two-factor-modal-close:hover, .wpu-two-factor-modal-close:focus { color: black; text-decoration: none; cursor: pointer; }
        </style>';

        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var modal = document.getElementById("wpu-two-factor-modal");
                var span = document.getElementsByClassName("wpu-two-factor-modal-close")[0];
                modal.style.display = "block";
                span.onclick = function() { modal.style.display = "none"; }
                window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }
            });
        </script>';

        return $html;
    }
}

$wpu_two_factor = new wpu_two_factor();
