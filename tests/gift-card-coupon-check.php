<?php
/**
 * Gift card code in the coupon field (block checkout), against stubbed
 * WooCommerce:
 * - the virtual coupon is capped at the balance (net share when tax is added on top);
 * - a real shop coupon with the same code wins;
 * - a second gift card is refused, and the redeem-field fee is skipped when
 *   the same card is already a coupon (no double discount);
 * - the order debit takes discount + tax once, refuses a short balance and
 *   tolerates only a rounding cent.
 *
 * Run: php tests/gift-card-coupon-check.php
 *
 * @package GiftCards
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('MINUTE_IN_SECONDS', 60);

    $GLOBALS['gc_real_coupons'] = [];
    $GLOBALS['gc_tax_on_top']   = false;

    function esc_html(string $s): string { return $s; }
    function did_action(string $h): int { return 0; }
    function wc_get_coupon_id_by_code(string $code): int { return $GLOBALS['gc_real_coupons'][$code] ?? 0; }
    function wc_tax_enabled(): bool { return $GLOBALS['gc_tax_on_top']; }
    function wc_prices_include_tax(): bool { return false; }

    class WC_Session
    {
        /** @var array<string, mixed> */
        public array $data = [];
        public function get(string $k, mixed $d = null): mixed { return $this->data[$k] ?? $d; }
        public function set(string $k, mixed $v): void { $this->data[$k] = $v; }
        public function __unset(string $k): void { unset($this->data[$k]); }
    }

    class WC_Cart
    {
        /** @var list<string> */
        public array $coupons = [];
        /** @var list<array{0: string, 1: float}> */
        public array $fees = [];
        public function get_applied_coupons(): array { return $this->coupons; }
        public function get_subtotal(): float { return 100.0; }
        public function get_subtotal_tax(): float { return 23.0; }
        public function add_fee(string $name, float $amount): void { $this->fees[] = [$name, $amount]; }
    }

    class WC_Coupon
    {
        public function __construct(private string $code, private int $id = 0) {}
        public function get_id(): int { return $this->id; }
        public function get_code(): string { return $this->code; }
    }

    class WC_Order_Item_Coupon
    {
        public function __construct(private string $code, private float $discount, private float $tax) {}
        public function get_code(): string { return $this->code; }
        public function get_discount(): float { return $this->discount; }
        public function get_discount_tax(): float { return $this->tax; }
    }

    class WC_Order
    {
        /** @var array<string, mixed> */
        public array $meta = [];
        /** @param list<WC_Order_Item_Coupon> $coupons */
        public function __construct(public array $coupons = []) {}
        public function get_meta(string $k): mixed { return $this->meta[$k] ?? ''; }
        public function update_meta_data(string $k, mixed $v): void { $this->meta[$k] = $v; }
        public function get_fees(): array { return []; }
        public function get_items(string $type = 'line_item'): array { return 'coupon' === $type ? $this->coupons : []; }
        public function save(): int { return 1; }
    }

    final class GcWoo
    {
        public WC_Cart $cart;
        public WC_Session $session;
        public function __construct() { $this->cart = new WC_Cart(); $this->session = new WC_Session(); }
    }

    function WC(): GcWoo { return $GLOBALS['gc_woo']; }

    require __DIR__ . '/../lib/storefront-kit/Support/Formatter.php';
    require __DIR__ . '/../lib/storefront-kit/GiftCard/GiftCardRepository.php';
    require __DIR__ . '/../lib/storefront-kit/GiftCard/GiftCardEngine.php';
}

namespace GcCouponCheck {
    use WPPoland\StorefrontKit\GiftCard\GiftCardEngine;
    use WPPoland\StorefrontKit\GiftCard\GiftCardRepository;

    final class Cards implements GiftCardRepository
    {
        /** @var array<string, object{id:int,code:string,balance:float,recipient_email:string,order_id:int}> */
        public array $cards = [];
        public int $debits = 0;
        public function add(int $id, string $code, float $balance): void
        {
            $this->cards[$code] = (object) ['id' => $id, 'code' => $code, 'balance' => $balance, 'recipient_email' => '', 'order_id' => 0];
        }
        public function issue(string $code, float $balance, string $recipientEmail, int $orderId): int { return 0; }
        public function findByCode(string $code): ?object { return isset($this->cards[$code]) ? clone $this->cards[$code] : null; }
        public function updateBalance(int $id, float $balance): void {}
        public function debit(int $id, float $amount): bool
        {
            foreach ($this->cards as $card) {
                if ($card->id === $id && $card->balance >= $amount) {
                    $card->balance -= $amount;
                    $this->debits++;
                    return true;
                }
            }
            return false;
        }
        public function credit(int $id, float $amount): void {}
    }

    $GLOBALS['gc_woo'] = new \GcWoo();
    $cards  = new Cards();
    $cards->add(1, 'GIFT50', 50.0);
    $cards->add(2, 'GIFT20', 20.0);

    $engine = new GiftCardEngine(
        repository: $cards,
        sessionKey: 'giftcards_redeem_code',
        fieldName: 'giftcards_redeem_code',
        nonceAction: 'giftcards_redeem',
        fieldTemplate: 'checkout-redeem-field',
        retryHook: 'giftcards_issue_retry',
        labels: ['fee_label' => 'Gift card ({code})', 'insufficient_balance' => 'short', 'one_card_only' => 'one card'],
        isEnabled: static fn (): bool => true,
        settings: static fn (): array => [],
        isGiftCard: static fn (): bool => false,
        resolveCard: static fn (): array => [0.0, ''],
        renderField: static function (): void {},
    );

    $failures = [];

    // GC-1: cap. WooCommerce lowercases coupon codes; the amount is the balance.
    $data = $engine->virtualCouponData(false, 'gift50');
    if (! is_array($data) || 50.0 !== $data['amount'] || 'fixed_cart' !== $data['discount_type'] || true !== $data['virtual']) {
        $failures[] = 'virtual coupon not capped at balance: ' . json_encode($data);
    }
    // Tax on top at 23%: 50 net + 11.50 tax would take 61.50 off a 50 card.
    $GLOBALS['gc_tax_on_top'] = true;
    $data = $engine->virtualCouponData(false, 'gift50');
    if (40.65 !== ($data['amount'] ?? null)) {
        $failures[] = 'tax-on-top amount should be 40.65, got ' . json_encode($data['amount'] ?? null);
    }
    $GLOBALS['gc_tax_on_top'] = false;
    if (false !== $engine->virtualCouponData(false, 'nosuch')) {
        $failures[] = 'unknown code became a coupon';
    }
    $GLOBALS['gc_real_coupons']['gift50'] = 9;
    if (false !== $engine->virtualCouponData(false, 'gift50')) {
        $failures[] = 'real coupon overridden by a gift card';
    }
    $GLOBALS['gc_real_coupons'] = [];

    // GC-2: a second card is refused; the same card is not.
    WC()->cart->coupons = ['gift50'];
    try {
        $engine->validateCardCoupon(true, new \WC_Coupon('gift20'));
        $failures[] = 'second gift card accepted';
    } catch (\Exception $e) {
        if ('one card' !== $e->getMessage()) {
            $failures[] = 'wrong refusal message: ' . $e->getMessage();
        }
    }
    WC()->cart->coupons = [];
    WC()->session->set('giftcards_redeem_code', 'GIFT50');
    if (true !== $engine->validateCardCoupon(true, new \WC_Coupon('gift50'))) {
        $failures[] = 'same card in field and coupon refused';
    }

    // GC-3: same card in the redeem field and the coupon field: no fee on top.
    WC()->cart->coupons = ['gift50'];
    $engine->applyRedeemDiscount(WC()->cart);
    if ([] !== WC()->cart->fees) {
        $failures[] = 'fee applied on top of the coupon: double discount';
    }
    WC()->cart->coupons = [];
    $engine->applyRedeemDiscount(WC()->cart);
    if (1 !== count(WC()->cart->fees)) {
        $failures[] = 'redeem field fee no longer applied without a coupon (classic changed)';
    }
    WC()->session->__unset('giftcards_redeem_code');

    // GC-4: debit = discount + tax, once.
    $order = new \WC_Order([new \WC_Order_Item_Coupon('gift50', 40.65, 9.35)]);
    $engine->persistStoreApiRedeem($order);
    if (1 !== $cards->debits || abs($cards->cards['GIFT50']->balance) > 0.0001 || 'GIFT50' !== $order->meta['giftcards_redeem_code']) {
        $failures[] = 'coupon debit wrong: balance ' . $cards->cards['GIFT50']->balance;
    }
    $engine->persistStoreApiRedeem($order);
    if (1 !== $cards->debits) {
        $failures[] = 'order debited twice';
    }

    // GC-5: a rounding cent drains the card; a real shortfall is refused.
    $order = new \WC_Order([new \WC_Order_Item_Coupon('gift20', 16.27, 3.74)]);
    $engine->persistStoreApiRedeem($order);
    if (2 !== $cards->debits || abs($cards->cards['GIFT20']->balance) > 0.0001) {
        $failures[] = 'rounding cent not tolerated';
    }
    $cards->add(3, 'GIFT10', 10.0);
    try {
        $engine->persistStoreApiRedeem(new \WC_Order([new \WC_Order_Item_Coupon('gift10', 15.0, 0.0)]));
        $failures[] = 'short balance debited';
    } catch (\Exception $e) {
        // Refused, as it should be.
    }

    if ([] !== $failures) {
        fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
        exit(1);
    }

    echo "OK: gift card coupon capped, single, debited once\n";
}
