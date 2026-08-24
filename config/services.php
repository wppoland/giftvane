<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container. Keep services thin; product logic lives in storefront-kit engines
 * instantiated here with this plugin's text-domain / option storage / asset URLs.
 *
 * @package GiftCards
 */

declare(strict_types=1);

use GiftCards\Admin\ProductFields;
use GiftCards\Admin\Settings;
use GiftCards\Container;
use GiftCards\Migrator;
use GiftCards\Repository\GiftCardTableRepository;
use GiftCards\Service\AbilitiesService;
use GiftCards\Service\GiftCardService;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());

    // Custom-table storage for issued cards, shared by everything that reads or
    // writes a balance.
    $c->singleton(
        GiftCardTableRepository::class,
        static fn (): GiftCardTableRepository => new GiftCardTableRepository(),
    );

    // Thin adapter over the storefront-kit GiftCardEngine.
    $c->singleton(GiftCardService::class, static fn (Container $c): GiftCardService => new GiftCardService(
        $c->get(GiftCardTableRepository::class),
    ));

    // Gift-card data exposed to the WordPress Abilities API (WP 6.9+). Inert on
    // older cores, and not admin-only: abilities are also called over REST.
    $c->singleton(AbilitiesService::class, static fn (Container $c): AbilitiesService => new AbilitiesService(
        $c->get(GiftCardTableRepository::class),
        $c->get(GiftCardService::class),
    ));

    $c->singleton(\GiftCards\Service\GiftCardPrivacyService::class, static fn (Container $c): \GiftCards\Service\GiftCardPrivacyService => new \GiftCards\Service\GiftCardPrivacyService(
        $c->get(GiftCardTableRepository::class),
    ));

    // Admin (only needed in wp-admin context).
    if (is_admin()) {
        $c->singleton(Settings::class, static fn (): Settings => new Settings());
        $c->singleton(ProductFields::class, static fn (): ProductFields => new ProductFields());
    }
};
