<?php
defined('ABSPATH') || die;

/*
Plugin Name: WPU Two Factor
Plugin URI: https://github.com/WordPressUtilities/wpu_two_factor
Update URI: https://github.com/WordPressUtilities/wpu_two_factor
Description: Additional features for the official two factor plugin
Version: 0.1.0
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

    private $disabled_providers = array(
        'Two_Factor_Dummy'
    );

    public function __construct() {
        add_action('init', array(&$this, 'init'));

        /* Load translations */
        add_action('init', array(&$this, 'load_translations'));
    }

    public function init() {
        if (!is_plugin_active('two-factor/two-factor.php') || !class_exists('Two_Factor_Core')) {
            return;
        }

        /* Disable some providers */
        add_filter('two_factor_providers', array(&$this, 'custom_providers'));

        /* Notice when the current user has no active 2FA method */
        add_action('admin_notices', array(&$this, 'no_2fa_notice'));
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

    public function no_2fa_notice() {

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        /* Raw meta: the two_factor_providers filter strips disabled providers from the enabled list */
        $enabled = get_user_meta($user_id, '_two_factor_enabled_providers', true);
        $enabled = is_array($enabled) ? $enabled : array();
        $has_disabled = (bool) array_intersect($enabled, $this->disabled_providers);

        if (!$has_disabled && Two_Factor_Core::is_user_using_two_factor($user_id)) {
            return;
        }

        $message = $has_disabled
        ? __('You are using a two-factor authentication method that is no longer allowed.', 'wpu_two_factor')
        : __('You have not enabled any two-factor authentication method.', 'wpu_two_factor');

        printf(
            '<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
            esc_html($message),
            esc_url(get_edit_profile_url($user_id) . '#two-factor-options'),
            esc_html__('Configure now', 'wpu_two_factor')
        );
    }

    public function custom_providers($providers) {
        foreach ($this->disabled_providers as $provider) {
            if (isset($providers[$provider])) {
                unset($providers[$provider]);
            }
        }
        return $providers;
    }
}

$wpu_two_factor = new wpu_two_factor();
