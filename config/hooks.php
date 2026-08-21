<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * GiftCards\Contract\HasHooks.
 *
 * @package GiftCards
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use GiftCards\Admin\ProductFields;
use GiftCards\Admin\Settings;
use GiftCards\Service\AbilitiesService;
use GiftCards\Service\GiftCardService;

defined('ABSPATH') || exit;

return [
    GiftCardService::class,
    // Registers nothing on cores without the Abilities API (WP < 6.9).
    AbilitiesService::class,
    ...(is_admin() ? [Settings::class, ProductFields::class] : []),
];
