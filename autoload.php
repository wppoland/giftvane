<?php
/**
 * Autoloading: prefer Composer's vendor autoloader (ships the storefront-kit and
 * the optimized classmap). Fall back to a minimal PSR-4 autoloader so the plugin
 * still boots if vendor/ is somehow absent.
 *
 * @package GiftCards
 */

declare(strict_types=1);

namespace GiftCards;

defined('ABSPATH') || exit;

$giftcards_composer = __DIR__ . '/vendor/autoload.php';
if (is_readable($giftcards_composer)) {
    require_once $giftcards_composer;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'GiftCards\\'           => __DIR__ . '/src/',
        'WPPoland\\StorefrontKit\\'    => __DIR__ . '/lib/storefront-kit/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relative = substr($class, $len);
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_readable($file)) {
            require_once $file;
        }
        return;
    }
});
