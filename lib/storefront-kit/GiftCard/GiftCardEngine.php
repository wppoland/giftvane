<?php

declare(strict_types=1);

namespace WPPoland\StorefrontKit\GiftCard;

use WPPoland\StorefrontKit\Support\Formatter;

defined('ABSPATH') || exit;

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
        add_action('woocommerce_after_checkout_validation', [$this, 'checkRedeemStillCovers'], 10, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'persistRedeemCode'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompleted'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'restoreRedeemedBalance'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'restoreRedeemedBalance'], 10, 1);

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

    /**
     * Reads the gift-card code out of the serialised checkout form.
     *
     * The redeem field carries its own nonce (printed next to it by the
     * template, so it travels in the serialised form). Nothing is read unless
     * that nonce verifies; a stale or missing one leaves the session as it was.
     */
    public function captureRedeemCode(string $postedData): void
    {
        if (! $this->isEnabled() || ! WC()->session instanceof \WC_Session) {
            return;
        }

        $parsed = [];
        parse_str($postedData, $parsed);

        $nonceKey = $this->fieldName . '_nonce';
        $nonce    = isset($parsed[$nonceKey]) && is_string($parsed[$nonceKey])
            ? sanitize_text_field($parsed[$nonceKey])
            : '';

        if (! wp_verify_nonce($nonce, $this->nonceAction)) {
            return;
        }

        $raw  = isset($parsed[$this->fieldName]) && is_string($parsed[$this->fieldName])
            ? sanitize_text_field($parsed[$this->fieldName])
            : '';
        $code = $this->normalizeCode($raw);

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

        $card    = $this->getAppliedCard();
        $applied = $card === null ? 0.0 : $this->coverFor($card->balance, $cart);

        // Remember what the shopper was shown, so the checkout can tell when
        // the card covers less by the time the order is placed.
        if (! did_action('woocommerce_checkout_process') && WC()->session instanceof \WC_Session) {
            WC()->session->set($this->sessionKey . '_shown', $applied);
        }

        if ($card === null || $applied <= 0) {
            return;
        }

        $cart->add_fee(
            Formatter::interpolate($this->message('fee_label'), ['code' => $card->code]),
            -$applied,
        );
    }

    /**
     * Stop the order when the card now covers less than the discount the
     * shopper saw, typically because it was just spent on another order.
     * Without this the checkout recalculates silently and charges more than
     * the total on screen; with it WooCommerce refreshes the totals and the
     * shopper confirms the new amount.
     *
     * @param array<string, mixed> $data
     */
    public function checkRedeemStillCovers(array $data, \WP_Error $errors): void
    {
        if (! $this->isEnabled() || $this->getAppliedCode() === '' || ! WC()->cart instanceof \WC_Cart) {
            return;
        }

        $shown = (float) WC()->session->get($this->sessionKey . '_shown', 0);

        if ($shown <= 0) {
            return;
        }

        $card = $this->repository->findByCode($this->getAppliedCode());
        $now  = $card === null ? 0.0 : $this->coverFor($card->balance, WC()->cart);

        if ($now + 0.0001 < $shown) {
            WC()->session->set($this->sessionKey . '_shown', $now);
            // Makes WooCommerce's failure response ask the page to refresh the
            // order review, so the retry shows the total it will charge.
            WC()->session->set('refresh_totals', true);
            $errors->add('giftcards_balance', $this->message('insufficient_balance'));
        }
    }

    private function coverFor(float $balance, \WC_Cart $cart): float
    {
        return max(0.0, min($balance, (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax()));
    }

    /**
     * Take the redeemed amount off the card while the order is being created.
     *
     * The balance used to come off only when the order reached "completed",
     * which can be days after payment, so until then the same card could pay
     * for any number of other orders. Debiting here, in one conditional write,
     * means a second checkout racing for the same balance is refused before
     * its order exists; the exception is what WooCommerce shows the shopper.
     *
     * @throws \Exception When the card no longer covers the discount.
     */
    public function persistRedeemCode(\WC_Order $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $code = $this->getAppliedCode();

        if ($code === '') {
            return;
        }

        $order->update_meta_data($this->sessionKey, $code);

        if (! $this->debitOrder($order, $code)) {
            throw new \Exception(esc_html($this->message('insufficient_balance')));
        }

        // The cart is gone after checkout; a code left in the session would
        // discount the shopper's next cart with whatever balance remains.
        WC()->session->__unset($this->sessionKey);
    }

    /**
     * Debit the card for this order's own gift-card fee, once. Returns false
     * only when the card no longer covers the amount.
     */
    public function debitOrder(\WC_Order $order, string $code): bool
    {
        if ($order->get_meta($this->redeemedFlag()) === 'yes') {
            return true;
        }

        $card = $this->repository->findByCode($code);

        if ($card === null) {
            return true;
        }

        $used = $this->giftCardFeeTotal($order, $card->code);

        if ($used <= 0) {
            return true;
        }

        if (! $this->repository->debit($card->id, $used)) {
            return false;
        }

        $order->update_meta_data($this->redeemedFlag(), 'yes');
        $order->update_meta_data($this->sessionKey . '_amount', (string) $used);
        $order->update_meta_data($this->sessionKey . '_card', (string) $card->id);

        return true;
    }

    /**
     * Give the redeemed amount back when the order is cancelled or refunded.
     */
    public function restoreRedeemedBalance(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if (! $order instanceof \WC_Order || $order->get_meta($this->redeemedFlag()) !== 'yes') {
            return;
        }

        $restored = $this->sessionKey . '_restored';

        if ($order->get_meta($restored) === 'yes') {
            return;
        }

        $cardId = (int) $order->get_meta($this->sessionKey . '_card');
        $amount = (float) $order->get_meta($this->sessionKey . '_amount');

        if ($cardId <= 0 || $amount <= 0) {
            return;
        }

        // Claimed before the write, as in redeemAppliedCard(): a second credit
        // would hand the shopper money the shop never took.
        $order->update_meta_data($restored, 'yes');
        $order->save();

        $this->repository->credit($cardId, $amount);
    }

    /**
     * The amount this order discounted through our own fee line, and nothing
     * else: another plugin's negative fee is not a gift-card redemption.
     */
    private function giftCardFeeTotal(\WC_Order $order, string $code): float
    {
        $name = Formatter::interpolate($this->message('fee_label'), ['code' => $code]);
        $used = 0.0;

        foreach ($order->get_fees() as $fee) {
            $total = (float) $fee->get_total();

            if ($total < 0 && $fee->get_name() === $name) {
                $used += abs($total);
            }
        }

        return round($used, 4);
    }

    private function redeemedFlag(): string
    {
        return $this->sessionKey . '_redeemed';
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

        // A retry fires up to five times across more than an hour, and an order
        // can leave Completed inside that window: refunded, cancelled, or put
        // back on hold while a chargeback is looked at. WooCommerce fires
        // `order_status_completed` on the way in only, so nothing else ever
        // re-reads the status, and the run below does not ask. Without this, a
        // refunded order still had the rest of its cards funded and emailed,
        // and the balance the shopper redeemed still taken off their card.
        // Attempt 0 is the transition itself, where the order is Completed by
        // definition, so only the scheduled attempts are judged here. Returning
        // also books no further retry, which ends the chain; a later return to
        // Completed fires the transition again and starts a fresh one.
        if ($attempt > 0 && ! $order->has_status('completed')) {
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

        // The last attempt has no retry left to book, so a note on the order is
        // the only thing that can tell the merchant cards may be missing. It
        // therefore has to be written by the attempt that fails, and only by
        // it: booked up front, as it used to be, an order whose final attempt
        // SUCCEEDED still got the alarm, and the merchant went looking through
        // an order that was complete. `finally` covers the run that unwinds,
        // shutdown covers the run that is killed instead, the same split the
        // lock release above makes, and $noted keeps the two from doubling up.
        $finished = false;
        $noted    = false;
        $giveUp   = function () use ($order, $attempt, &$finished, &$noted): void {
            if ($finished || $noted || $attempt < self::MAX_RETRIES) {
                return;
            }

            $noted = true;

            $order->add_order_note(
                Formatter::interpolate(
                    $this->message('retry_exhausted'),
                    ['attempts' => (string) self::MAX_RETRIES],
                )
            );
        };

        if ($attempt >= self::MAX_RETRIES) {
            register_shutdown_function($giveUp);
        }

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

            $finished = true;
        } finally {
            $release();
            $giveUp();
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
            // ever is worse than stopping, so this run is the last one. Whether
            // the merchant is told about it is not decided here: this method
            // runs before the work, and only the end of the work knows whether
            // the last attempt was the one that succeeded. See the give-up note
            // in handleOrderCompleted().
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

    /**
     * Fallback for orders created outside the classic checkout (admin, REST):
     * they never passed persistRedeemCode(), so debit at completion instead.
     * An order debited at checkout carries the flag and is skipped.
     */
    private function redeemAppliedCard(\WC_Order $order): void
    {
        $code = (string) $order->get_meta($this->sessionKey);

        if ($code === '' || $order->get_meta($this->redeemedFlag()) === 'yes') {
            return;
        }

        if ($this->debitOrder($order, $code)) {
            $order->save();

            return;
        }

        $order->add_order_note(Formatter::interpolate($this->message('insufficient_balance_note'), ['code' => $code]));
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
        // The email is plain text, so the currency symbol wc_price() writes as
        // an HTML entity (&#36;, &euro;) is decoded rather than sent literally.
        $values = [
            'code' => $code,
            'amount' => html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8'),
        ];

        $subject = Formatter::interpolate($this->message('email_subject'), $values);
        $body    = Formatter::interpolate($this->message('email_body'), $values);

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
