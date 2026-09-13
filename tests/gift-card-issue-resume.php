<?php
/**
 * Regression check: a completion run that dies part-way must still deliver
 * every gift card the customer paid for, and must never fund a second one.
 *
 * Run it with `php tests/gift-card-issue-resume.php` (no WordPress, no
 * database: the surface the engine touches is faked below, and the gift-card
 * table is a small in-memory table that enforces the real UNIQUE(code) key).
 *
 * The scenario is the one that costs the shop a customer: three cards bought
 * on one line, the second recipient's mail send is killed (a fatal inside a
 * mail plugin, or max_execution_time), and the order is completed again later.
 * All three units must end up with exactly one card each.
 *
 * @package GiftCards
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('MINUTE_IN_SECONDS', 60);

    /** Recipient whose mail send is killed, or '' for none. */
    $GLOBALS['gc_explode_on'] = '';
    /** Every wp_mail() call: [recipient, body]. */
    $GLOBALS['gc_emailed']    = [];
    $GLOBALS['gc_transients'] = [];

    function get_transient(string $key)
    {
        return $GLOBALS['gc_transients'][$key] ?? false;
    }

    function set_transient(string $key, $value, int $ttl = 0): bool
    {
        $GLOBALS['gc_transients'][$key] = $value;

        return true;
    }

    function delete_transient(string $key): bool
    {
        unset($GLOBALS['gc_transients'][$key]);

        return true;
    }

    function is_email(string $email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : false;
    }

    function sanitize_email(string $email): string
    {
        return trim($email);
    }

    function wp_generate_password(int $length = 12, bool $special = true, bool $extra = false): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $out   = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }

    function wc_price(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }

    function wp_mail(string $to, string $subject, string $body): bool
    {
        // Recorded first, then killed: the message may well have left the
        // building before the process died, which is why a resend of the same
        // code is the accepted cost and a second card is not.
        $GLOBALS['gc_emailed'][] = [$to, $body];

        if ($GLOBALS['gc_explode_on'] === $to) {
            throw new \RuntimeException('mail send killed');
        }

        return true;
    }

    /** Minimal WooCommerce stand-ins: only what the engine calls. */
    class WC_Product
    {
        public function __construct(private int $id = 1)
        {
        }

        public function get_id(): int
        {
            return $this->id;
        }
    }

    class WC_Order_Item_Product
    {
        /** @var array<string, mixed> */
        private array $meta = [];

        public int $metaSaves = 0;

        public function __construct(
            private WC_Product $product,
            private int $quantity,
        ) {
        }

        public function get_product(): WC_Product
        {
            return $this->product;
        }

        public function get_quantity(): int
        {
            return $this->quantity;
        }

        /** @return mixed */
        public function get_meta(string $key, bool $single = true)
        {
            return $this->meta[$key] ?? '';
        }

        /** @param mixed $value */
        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function save_meta_data(): void
        {
            $this->metaSaves++;
        }
    }

    class WC_Order_Item_Fee
    {
        public function __construct(private float $total)
        {
        }

        public function get_total(): float
        {
            return $this->total;
        }
    }

    class WC_Order
    {
        /** @var array<string, mixed> */
        private array $meta = [];

        /** @var array<int, WC_Order_Item_Product> */
        private array $items = [];

        /** @var array<int, WC_Order_Item_Fee> */
        private array $fees = [];

        /** Meta key whose save is killed, or '' for none. */
        public string $explodeOnSaveOf = '';

        public function __construct(private int $id = 100)
        {
        }

        public function get_id(): int
        {
            return $this->id;
        }

        /** @param array<int, WC_Order_Item_Product> $items */
        public function setItems(array $items): void
        {
            $this->items = $items;
        }

        /** @param array<int, WC_Order_Item_Fee> $fees */
        public function setFees(array $fees): void
        {
            $this->fees = $fees;
        }

        /** @return array<int, WC_Order_Item_Product> */
        public function get_items(string $types = 'line_item'): array
        {
            return $this->items;
        }

        /** @return array<int, WC_Order_Item_Fee> */
        public function get_fees(): array
        {
            return $this->fees;
        }

        /** @return mixed */
        public function get_meta(string $key, bool $single = true)
        {
            return $this->meta[$key] ?? '';
        }

        /** @param mixed $value */
        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = $value;
        }

        public function save(): int
        {
            if ($this->explodeOnSaveOf !== '' && isset($this->meta[$this->explodeOnSaveOf])) {
                throw new \RuntimeException('order save killed');
            }

            return $this->id;
        }
    }

    function wc_get_order(int $orderId)
    {
        return $GLOBALS['gc_orders'][$orderId] ?? false;
    }
}

namespace GiftCards\Tests {

    use WPPoland\StorefrontKit\GiftCard\DuplicateGiftCardCodeException;
    use WPPoland\StorefrontKit\GiftCard\GiftCardEngine;
    use WPPoland\StorefrontKit\GiftCard\GiftCardRepository;

    $kit = dirname(__DIR__) . '/lib/storefront-kit';

    require $kit . '/Support/Formatter.php';
    require $kit . '/GiftCard/DuplicateGiftCardCodeException.php';
    require $kit . '/GiftCard/GiftCardRepository.php';
    require $kit . '/GiftCard/GiftCardEngine.php';

    /** The gift-card table, with the UNIQUE(code) key the contract requires. */
    final class FakeCardTable implements GiftCardRepository
    {
        /** @var array<int, object> */
        public array $rows = [];

        private int $nextId = 1;

        public function issue(string $code, float $balance, string $recipientEmail, int $orderId): int
        {
            foreach ($this->rows as $row) {
                if ($row->code === $code) {
                    throw new DuplicateGiftCardCodeException('duplicate code');
                }
            }

            $id = $this->nextId++;

            $this->rows[$id] = (object) [
                'id'              => $id,
                'code'            => $code,
                'balance'         => $balance,
                'recipient_email' => $recipientEmail,
                'order_id'        => $orderId,
            ];

            return $id;
        }

        public function findByCode(string $code): ?object
        {
            foreach ($this->rows as $row) {
                if ($row->code === $code) {
                    return $row;
                }
            }

            return null;
        }

        public function updateBalance(int $id, float $balance): void
        {
            if (isset($this->rows[$id])) {
                $this->rows[$id]->balance = $balance;
            }
        }
    }

    $failures = 0;

    /** @param mixed $expected @param mixed $actual */
    function check(string $what, $expected, $actual): void
    {
        global $failures;

        if ($expected === $actual) {
            echo "ok   {$what}\n";

            return;
        }

        $failures++;
        echo "FAIL {$what}\n";
        echo '     expected: ' . var_export($expected, true) . "\n";
        echo '     actual:   ' . var_export($actual, true) . "\n";
    }

    function stop(): void
    {
        global $failures;

        if ($failures > 0) {
            echo "\n{$failures} failed\n";
            exit(1);
        }
    }

    function engineFor(FakeCardTable $table): GiftCardEngine
    {
        return new GiftCardEngine(
            repository: $table,
            sessionKey: 'giftcards_redeem_code',
            fieldName: 'giftcards_redeem_code',
            nonceAction: 'giftcards_redeem',
            fieldTemplate: 'checkout-redeem-field',
            labels: [
                'fee_label'     => 'Gift card ({code})',
                'email_subject' => 'You have received a {amount} gift card',
                'email_body'    => "Your code: {code}",
                'invalid_code'  => 'no',
                'applied'       => 'yes',
            ],
            isEnabled: static fn (): bool => true,
            settings: static fn (): array => ['code_prefix' => 'GC-'],
            isGiftCard: static fn (\WC_Product $product): bool => true,
            resolveCard: static fn (\WC_Order_Item_Product $item): array => [25.0, 'recipient@example.test'],
            renderField: static function (string $template, array $context): void {
            },
        );
    }

    // --- Three cards on one line, the second send is killed. ----------------

    $table  = new FakeCardTable();
    $engine = engineFor($table);

    $item  = new \WC_Order_Item_Product(new \WC_Product(7), 3);
    $order = new \WC_Order(100);
    $order->setItems([$item]);
    $GLOBALS['gc_orders'][100] = $order;

    $GLOBALS['gc_explode_on'] = 'recipient@example.test';

    try {
        $engine->handleOrderCompleted(100);
        check('the killed run did not return normally', true, false);
    } catch (\RuntimeException $e) {
        check('the run was killed mid-loop', 'mail send killed', $e->getMessage());
    }

    $killedRunCards  = count($table->rows);
    $killedRunEmails = count($GLOBALS['gc_emailed']);

    // The next completion of the same order: the lock has expired, the mail
    // works again.
    $GLOBALS['gc_explode_on'] = '';
    $GLOBALS['gc_transients'] = [];

    $engine->handleOrderCompleted(100);

    check('three paid units, three cards', 3, count($table->rows));

    $codes = array_values(array_map(static fn (object $row): string => $row->code, $table->rows));
    check('every card has its own code', 3, count(array_unique($codes)));

    $emailedCodes = [];

    foreach ($GLOBALS['gc_emailed'] as [$to, $body]) {
        $emailedCodes[] = trim(str_replace('Your code:', '', $body));
    }

    foreach ($codes as $code) {
        check("card {$code} reached its recipient", true, in_array($code, $emailedCodes, true));
    }

    foreach ($emailedCodes as $code) {
        check("nothing was emailed that was not issued ({$code})", true, in_array($code, $codes, true));
    }

    // The accepted cost of recording the code before the email: the one card
    // whose send was interrupted can be emailed twice, with the same code.
    check('exactly one code was emailed twice', 4, count($emailedCodes));
    check('the killed run had funded one card', 1, $killedRunCards);
    check('the killed run had handed one message to wp_mail', 1, $killedRunEmails);

    // A third completion (completed, refunded, completed again) must be free.
    $engine->handleOrderCompleted(100);

    check('a repeat completion issues nothing', 3, count($table->rows));
    check('a repeat completion emails nothing', 4, count($GLOBALS['gc_emailed']));

    stop();

    // --- A redeemed balance may not be taken off the card twice. -----------

    $table  = new FakeCardTable();
    $engine = engineFor($table);
    $cardId = $table->issue('GC-REDEEMME', 100.0, 'holder@example.test', 1);

    $order = new \WC_Order(200);
    $order->update_meta_data('giftcards_redeem_code', 'GC-REDEEMME');
    $order->setFees([new \WC_Order_Item_Fee(-30.0)]);
    $GLOBALS['gc_orders'][200] = $order;
    $GLOBALS['gc_transients']  = [];

    // Killed exactly where it hurts: after the balance was decremented, before
    // the order could be marked as done.
    $order->explodeOnSaveOf = 'giftcards_redeem_code_processed';

    try {
        $engine->handleOrderCompleted(200);
        check('the run died writing the processed flag', true, false);
    } catch (\RuntimeException $e) {
        check('the run died writing the processed flag', 'order save killed', $e->getMessage());
    }

    check('the balance was taken once', 70.0, $table->rows[$cardId]->balance);

    $order->explodeOnSaveOf   = '';
    $GLOBALS['gc_transients'] = [];

    $engine->handleOrderCompleted(200);

    check('the repeat completion did not take it again', 70.0, $table->rows[$cardId]->balance);

    stop();
    echo "\nall checks passed\n";
}
