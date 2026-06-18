<?php
/**
 * Plugin Name:       Gift Cards – Store Credit for WooCommerce
 * Plugin URI:        https://plogins.com/giftcards/
 * Description:        Sell gift cards that email a redeemable code to the recipient and apply as a discount at checkout.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            WPPoland.com
 * Author URI:        https://wppoland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       giftcards
 * Domain Path:       /languages
 * WC requires at least: 8.0
 *
 * @package GiftCards
 */

declare(strict_types=1);

namespace GiftCards;

defined('ABSPATH') || exit;

const VERSION     = '0.2.0';
const PLUGIN_FILE = __FILE__;

define('GIFTCARDS_DIR', plugin_dir_path(__FILE__));
define('GIFTCARDS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

// HPOS + cart/checkout blocks compatibility.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Gift Cards – Store Credit for WooCommerce requires WooCommerce to be active.', 'giftcards');
            echo '</p></div>';
        });
        return;
    }

    add_action('init', static function (): void {
        Plugin::instance()->boot();
    }, 0);
}, 10);

// Create the gift-cards table on activation. Services are registered in the
// Plugin constructor, so the container is ready here — before boot() runs.
register_activation_hook(PLUGIN_FILE, static function (): void {
    require_once __DIR__ . '/autoload.php';
    Plugin::instance()->container()->get(Migrator::class)->maybeMigrate();
});
