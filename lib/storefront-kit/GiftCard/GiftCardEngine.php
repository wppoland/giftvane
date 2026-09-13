<?php

declare(strict_types=1);

namespace WPPoland\StorefrontKit\GiftCard;

use WPPoland\StorefrontKit\Support\Formatter;

/**
 * Namespace-neutral gift-card / store-credit engine (powers the Gift Cards, 
 * Store Credit for WooCommerce plugin).
 *
 * A product flagged as a gift card (the host resolves is-gift-card + amount via
 * injected closures) generates a unique code on order completion; the code,
 * balance, recipient email and order id are persisted through the host-supplied
 * {@see GiftCardRepository} (custom table, same delegation as
 * {@see \WPPoland\StorefrontKit\Waitlist\WaitlistRepository}) and the recipient
 * is emailed. Redemption: a code field at checkout applies the remaining balance
 * as a negative cart fee, and the balance is decremented on order completion.
 *
 * Everything WooCommerce / text-domain / option specific is constructor-injected
 * via closures and arrays, nothing is hard-coded here. The code field markup
 * ships in the consuming plugin via the injected `renderField` closure.
 */
final class GiftCardEngine
{
    /**
     * How often an interrupted issue run is retried, and how many times.
     *
     * Far enough apart that a host which killed the run on execution time is
     * not handed the same job while it is still busy, and bounded because a run
     * that dies five times is not going to stop dying on the sixth.
     */
    private const RETRY_DELAY = 15 * MINUTE_IN_SECONDS;

    private const MAX_RETRIES = 5;

    /**
     * @param \Closure(): bool $isEnabled
     * @param \Closure(): array<string, mixed> $settings Resolved settings.
     * @param \Closure(\WC_Product): bool $isGiftCard Whether a product is a
     *        gift card.
     * @param \Closure(\WC_Order_Item_Product): array{0: float, 1: string} $resolveCard
     *        Returns `[amount, recipient_email]` for a purchased gift-card line.
     * @param \Closure(string, array<string, mixed>): void $renderField
     *        Echoes the checkout redeem-code field.
     * @param array<string, string> $labels Fallback strings keyed by
     *        `fee_label`, `email_subject`, `email_body`, `invalid_code`,
     *        `applied`, `retry_exhausted`.
     */
    public function __construct(
        private readonly GiftCardRepository $repository,
        private readonly string $sessionKey,
        private readonly string $fieldName,
        private readonly string $nonceAction,
        private readonly string $fieldTemplate,
        private readonly string $retryHook,
        private readonly array $labels,
        private readonly \Closure $isEnabled,
        private readonly \Closure $settings,
        private readonly \Closure $isGiftCard,
        private readonly \Closure $resolveCard,
        private readonly \Closure $renderField,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_review_order_before_payment', [$this, 'renderRedeemField'], 10);
        add_action('woocommerce_checkout_update_order_review', [$this, 'captureRedeemCode'], 10);
        add_action('woocommerce_cart_calculate_fees', [$this, 'applyRedeemDiscount'], 30);
        add_action('woocommerce_checkout_create_order', [$this, 'persistRedeemCode'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompleted'], 10, 1);

        // The only other way back into an interrupted run. `completed` fires on
        // the transition, so an order that is already Completed never fires it
        // again: without this hook the records below can resume the work and
        // nothing ever asks them to, and nothing tells the merchant that cards
        // are missing either.
        add_action($this->retryHook, [$this, 'handleOrderCompleted'], 10, 2);
    }

    public function renderRedeemField(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        ($this->renderField)($this->fieldTemplate, [
            'field_name' => $this->fieldName,
            'nonce_field' => wp_create_nonce($this->nonceAction),
            'applied_code' => $this->getAppliedCode(),
            'settings' => $this->getSettings(),
        ]);
    }

    public function captureRedeemCode(string $postedData): void
    {
        if (! $this->isEnabled() || ! WC()->session instanceof \WC_Session) {
            return;
        }

        $parsed = [];
        parse_str($postedData, $parsed);

        $code = isset($parsed[$this->fieldName]) ? $this->normalizeCode((string) $parsed[$this->fieldName]) : '';

        if ($code === '') {
            WC()->session->__unset($this->sessionKey);

            return;
        }

        WC()->session->set($this->sessionKey, $code);
    }

    public function applyRedeemDiscount(\WC_Cart $cart): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $card = $this->getAppliedCard();

        if ($card === null) {
            return;
        }

        $cartTotal = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
        $applied = min($card->balance, $cartTotal);

        if ($applied <= 0) {
            return;
        }

        $cart->add_fee(
            Formatter::interpolate($this->message('fee_label'), ['code' => $card->code]),
            -$applied,
        );
    }

    /**
     * Persist the session-held redeem code onto the order at creation time so
     * {@see redeemAppliedCard()} can decrement the balance reliably later, the
     * WC session is not guaranteed to survive until `order_status_completed`.
     */
    public function persistRedeemCode(\WC_Order $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $code = $this->getAppliedCode();

        if ($code !== '') {
            $order->update_meta_data($this->sessionKey, $code);
        }
    }

    /**
     * @param int $attempt Which retry this is, 0 when WooCommerce completed the
     *                     order. Supplied by the scheduled event below.
     */
    public function handleOrderCompleted(int $orderId, int $attempt = 0): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order) {
            return;
        }

        // Orders completed before this flag stopped being written up front are
        // still judged by it: nothing recorded what those orders received, so
        // the only safe reading of the flag is "hands off".
        $processedFlag = $this->sessionKey . '_processed';

        if ($order->get_meta($processedFlag) === 'yes') {
            return;
        }

        // Two completions of the same order arriving at the same moment (a
        // gateway callback racing the shop admin) would both find no per-unit
        // record and both issue. This narrows that window to a transient read
        // and write. It is not what makes a repeat safe, the per-unit records
        // below are; it expires on its own, so an interrupted run is retried
        // rather than blocked for good.
        $lock = $this->sessionKey . '_lock_' . $orderId;

        if (get_transient($lock) !== false) {
            return;
        }

        set_transient($lock, 1, 5 * MINUTE_IN_SECONDS);

        // The lock has to die with the run it guards. `finally` does not run
        // when the process is killed rather than unwound (a fatal error, the
        // host stopping it on max execution time, a plain exit), and a shutdown
        // function does run in all three: without one, the lock left behind by
        // the killed run would turn away its own retry for the rest of its five
        // minutes. The expiry stays as the backstop for a kill so hard that PHP
        // never reaches shutdown at all.
        $released = false;
        $release  = static function () use ($lock, &$released): void {
            if ($released) {
                return;
            }

            $released = true;
            delete_transient($lock);
        };

        register_shutdown_function($release);

        // Booked before the work and cleared after it, so a run that dies
        // leaves its own retry behind. What that retry still has to do is
        // decided by the per-unit records, not by this event.
        $this->scheduleRetry($order, $attempt);

        try {
            $this->issueGiftCards($order);
            $this->redeemAppliedCard($order);

            // Written last, and only once every card on the order exists and
            // has been emailed. Written first (as it used to be), a timeout in
            // the middle of the loop below left the rest of the cards the
            // customer had paid for permanently unissued: nothing retried them,
            // because the flag said the order was done.
            $order->update_meta_data($processedFlag, 'yes');
            $order->save();
        } finally {
            $release();
        }

        // Reached only when every card exists and the order is marked done, so
        // the retry this run booked has nothing left to pick up.
        $this->clearRetry($orderId, $attempt);
    }

    /**
     * Book the next attempt at an interrupted issue run.
     */
    private function scheduleRetry(\WC_Order $order, int $attempt): void
    {
        if ($attempt >= self::MAX_RETRIES) {
            // A retry only fires because the run that booked it never came back
            // to clear it, so reaching here proves this order has now failed to
            // finish that many times. Retrying it every quarter of an hour for
            // ever is worse than stopping, so this run is the last one and it
            // says so on the order the merchant would open, which is the only
            // place anything would have told them cards can be missing.
            $order->add_order_note(
                Formatter::interpolate(
                    $this->message('retry_exhausted'),
                    ['attempts' => (string) self::MAX_RETRIES],
                )
            );

            return;
        }

        $args = [$order->get_id(), $attempt + 1];

        if (wp_next_scheduled($this->retryHook, $args) !== false) {
            return;
        }

        wp_schedule_single_event(time() + self::RETRY_DELAY, $this->retryHook, $args);
    }

    private function clearRetry(int $orderId, int $attempt): void
    {
        wp_clear_scheduled_hook($this->retryHook, [$orderId, $attempt + 1]);
    }

    /**
     * Issue one card per purchased unit, recording each one as it is issued.
     *
     * The record is per unit and it is written before the email, so an
     * interrupted run costs at worst a repeated email carrying the code that
     * was already issued, never a second funded card and never a card the
     * customer paid for and never received.
     */
    private function issueGiftCards(\WC_Order $order): void
    {
        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();

            if (! $product instanceof \WC_Product || ! (bool) ($this->isGiftCard)($product)) {
                continue;
            }

            [$amount, $recipientEmail] = $this->resolveCardDetails($item);

            if ($amount <= 0 || ! is_email($recipientEmail)) {
                continue;
            }

            $quantity = max(1, (int) $item->get_quantity());
            $issued   = $this->issuedUnits($item);

            for ($i = 0; $i < $quantity; $i++) {
                $code = (string) ($issued[$i]['code'] ?? '');

                if ($code === '') {
                    $code = $this->issueUniqueCard($amount, $recipientEmail, $order->get_id());

                    // Could not persist a card this run (a code collision that
                    // outlasted the retries, or a transient DB error): leave the
                    // unit unrecorded so the next completion tries again.
                    if ($code === '') {
                        continue;
                    }

                    // The card is funded from here on, so the code is stored
                    // before the email goes out.
                    $issued[$i] = ['code' => $code, 'emailed' => false];
                    $this->saveIssuedUnits($item, $issued);
                }

                if (! empty($issued[$i]['emailed'])) {
                    continue;
                }

                $this->sendRecipientEmail($recipientEmail, $code, $amount);

                $issued[$i]['emailed'] = true;
                $this->saveIssuedUnits($item, $issued);
            }
        }
    }

    /**
     * The cards already issued for one order line, keyed by unit index.
     *
     * @return array<int, array{code: string, emailed: bool}>
     */
    private function issuedUnits(\WC_Order_Item_Product $item): array
    {
        $stored = $item->get_meta($this->issuedMetaKey(), true);

        if (! is_array($stored)) {
            return [];
        }

        $units = [];

        foreach ($stored as $index => $unit) {
            if (! is_array($unit)) {
                continue;
            }

            $code = (string) ($unit['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $units[(int) $index] = ['code' => $code, 'emailed' => ! empty($unit['emailed'])];
        }

        return $units;
    }

    /**
     * @param array<int, array{code: string, emailed: bool}> $units
     */
    private function saveIssuedUnits(\WC_Order_Item_Product $item, array $units): void
    {
        $item->update_meta_data($this->issuedMetaKey(), $units);
        $item->save_meta_data();
    }

    /**
     * Underscore-prefixed, so WooCommerce keeps it out of the order line the
     * customer and the shop manager read.
     */
    private function issuedMetaKey(): string
    {
        return '_' . $this->sessionKey . '_issued';
    }

    private function redeemAppliedCard(\WC_Order $order): void
    {
        $code = (string) $order->get_meta($this->sessionKey);

        if ($code === '') {
            return;
        }

        // Unlike issuing, this one is claimed before the work, not after. The
        // work is a single balance write with nothing slow in front of it, and
        // the two failures are not equal: a second decrement takes the shopper's
        // remaining balance away twice, while a lost decrement costs the shop
        // one discount it already gave.
        $redeemedFlag = $this->sessionKey . '_redeemed';

        if ($order->get_meta($redeemedFlag) === 'yes') {
            return;
        }

        $card = $this->repository->findByCode($code);

        if ($card === null) {
            return;
        }

        $used = 0.0;

        foreach ($order->get_fees() as $fee) {
            $total = (float) $fee->get_total();

            if ($total < 0) {
                $used += abs($total);
            }
        }

        $newBalance = max(0.0, $card->balance - $used);

        $order->update_meta_data($redeemedFlag, 'yes');
        $order->save();

        $this->repository->updateBalance($card->id, $newBalance);
    }

    /**
     * @return object{id:int,code:string,balance:float,recipient_email:string,order_id:int}|null
     */
    public function getAppliedCard(): ?object
    {
        $code = $this->getAppliedCode();

        if ($code === '') {
            return null;
        }

        $card = $this->repository->findByCode($code);

        if ($card === null || $card->balance <= 0) {
            return null;
        }

        return $card;
    }

    public function getAppliedCode(): string
    {
        if (! WC()->session instanceof \WC_Session) {
            return '';
        }

        $code = WC()->session->get($this->sessionKey);

        return is_string($code) ? $code : '';
    }

    public function generateCode(): string
    {
        $prefix = (string) ($this->getSettings()['code_prefix'] ?? '');
        $random = strtoupper(wp_generate_password(12, false, false));
        $code = $prefix . $random;

        return $this->normalizeCode($code);
    }

    /**
     * Issue one gift card with a code that is unique even under concurrency.
     *
     * Uniqueness is guaranteed at two layers: the kit pre-checks each candidate
     * via {@see GiftCardRepository::findByCode()} (cheap, filters the common
     * case), and the host's DB-level UNIQUE index is the authority, if a
     * concurrent issue inserts the same code between our check and our insert,
     * {@see GiftCardRepository::issue()} throws
     * {@see DuplicateGiftCardCodeException} and we regenerate. After a bounded
     * number of attempts the entropy is widened so a usable code is always
     * issued without an unbounded loop. Returns the issued code, or '' if no
     * code could be issued.
     */
    private function issueUniqueCard(float $amount, string $recipientEmail, int $orderId): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = $attempt < 5 ? $this->generateCode() : $this->generateWideCode();

            if ($code === '' || $this->repository->findByCode($code) !== null) {
                continue;
            }

            try {
                $this->repository->issue($code, $amount, $recipientEmail, $orderId);

                return $code;
            } catch (DuplicateGiftCardCodeException) {
                // A concurrent issue won the race for this code; regenerate.
                continue;
            }
        }

        return '';
    }

    private function generateWideCode(): string
    {
        $prefix = (string) ($this->getSettings()['code_prefix'] ?? '');

        return $this->normalizeCode($prefix . strtoupper(wp_generate_password(20, false, false)));
    }

    private function sendRecipientEmail(string $recipientEmail, string $code, float $amount): void
    {
        $subject = Formatter::interpolate($this->message('email_subject'), [
            'code' => $code,
            'amount' => wp_strip_all_tags(wc_price($amount)),
        ]);

        $body = Formatter::interpolate($this->message('email_body'), [
            'code' => $code,
            'amount' => wp_strip_all_tags(wc_price($amount)),
        ]);

        wp_mail($recipientEmail, $subject, $body);
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function resolveCardDetails(\WC_Order_Item_Product $item): array
    {
        $resolved = ($this->resolveCard)($item);

        if (! is_array($resolved) || ! isset($resolved[0], $resolved[1])) {
            return [0.0, ''];
        }

        return [(float) $resolved[0], sanitize_email((string) $resolved[1])];
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $code) ?? '');
    }

    private function isEnabled(): bool
    {
        return (bool) ($this->isEnabled)();
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $settings = ($this->settings)();

        return is_array($settings) ? $settings : [];
    }

    private function message(string $labelKey): string
    {
        return $this->labels[$labelKey] ?? '';
    }
}
