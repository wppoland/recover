<?php
/**
 * Plugin Name:       Recover - Abandoned Cart Recovery for WooCommerce
 * Plugin URI:        https://plogins.com/recover/
 * Description:        Capture carts that are left behind and email customers a one-click link to finish checkout.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Tested up to:      7.0
 * Requires Plugins:  woocommerce
 * Author:            WPPoland.com
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       recover
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.6
 *
 * @package Recover
 */

declare(strict_types=1);

namespace Recover;

defined('ABSPATH') || exit;

const VERSION         = '0.1.0';
const PLUGIN_FILE     = __FILE__;
const PLUGIN_DIR      = __DIR__;
const MIN_PHP_VERSION = '8.1.0';
const MIN_WC_VERSION  = '8.0.0';
const CRON_HOOK       = 'recover_process_carts';

// HPOS + cart/checkout blocks compatibility.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

// Require PHP 8.1+ before doing anything else.
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', static function (): void {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                __('Recover requires PHP %1$s or higher. You are running PHP %2$s.', 'recover'),
                MIN_PHP_VERSION,
                PHP_VERSION,
            )),
        );
    });
    return;
}

require_once __DIR__ . '/autoload.php';

add_action('plugins_loaded', static function (): void {
    if (! defined('WC_VERSION')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Recover - Abandoned Cart Recovery for WooCommerce requires WooCommerce to be installed and activated.', 'recover'),
            );
        });
        return;
    }

    if (version_compare(WC_VERSION, MIN_WC_VERSION, '<')) {
        add_action('admin_notices', static function (): void {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: 1: Required WC version, 2: Current WC version */
                    __('Recover requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'recover'),
                    MIN_WC_VERSION,
                    WC_VERSION,
                )),
            );
        });
        return;
    }

    add_action('init', static function (): void {
        Plugin::instance()->boot();
    }, 0);
}, 10);

register_activation_hook(PLUGIN_FILE, static function (): void {
    require_once PLUGIN_DIR . '/autoload.php';
    Plugin::instance()->container()->get(Migrator::class)->maybeMigrate();

    if (! wp_next_scheduled(CRON_HOOK)) {
        wp_schedule_event(time() + 300, 'hourly', CRON_HOOK);
    }
});

register_deactivation_hook(PLUGIN_FILE, static function (): void {
    $timestamp = wp_next_scheduled(CRON_HOOK);
    if (false !== $timestamp) {
        wp_unschedule_event($timestamp, CRON_HOOK);
    }
    wp_clear_scheduled_hook(CRON_HOOK);
});
