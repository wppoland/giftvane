<?php
/**
 * Auto-complete of gift-card-only orders: only when every line is a virtual
 * gift card. A mixed cart, a gift card that ships, or an empty order keeps
 * WooCommerce's own status.
 *
 * Single issuance on repeated Completed transitions is covered by
 * tests/gift-card-issue-resume.php.
 *
 * Run: php tests/auto-complete-check.php
 *
 * @package GiftCards
 */

declare(strict_types=1);

namespace GiftCards\Contract {
    interface HasHooks
    {
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/');

    class WC_Product
    {
        public function __construct(public bool $gift, public bool $virtual) {}
        public function is_virtual(): bool { return $this->virtual; }
    }
    class WC_Order_Item_Product
    {
        public function __construct(private ?WC_Product $product) {}
        public function get_product(): ?WC_Product { return $this->product; }
    }

    require __DIR__ . '/../src/Service/GiftCardService.php';

    use GiftCards\Service\GiftCardService as S;

    $gift   = static fn (bool $virtual = true): WC_Order_Item_Product => new WC_Order_Item_Product(new WC_Product(true, $virtual));
    $other  = new WC_Order_Item_Product(new WC_Product(false, true));
    $isGift = static fn (WC_Product $p): bool => $p->gift;

    $failures = [];

    if (! S::onlyVirtualGiftCards([$gift(), $gift()], $isGift)) {
        $failures[] = 'all virtual gift cards not completed';
    }
    if (S::onlyVirtualGiftCards([$gift(), $other], $isGift)) {
        $failures[] = 'mixed cart completed';
    }
    if (S::onlyVirtualGiftCards([$gift(false)], $isGift)) {
        $failures[] = 'gift card that ships completed';
    }
    if (S::onlyVirtualGiftCards([], $isGift)) {
        $failures[] = 'empty order completed';
    }
    if (S::onlyVirtualGiftCards([new WC_Order_Item_Product(null)], $isGift)) {
        $failures[] = 'deleted product completed';
    }

    if ([] !== $failures) {
        fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
        exit(1);
    }

    echo "OK: only all-virtual-gift-card orders auto-complete\n";
}
