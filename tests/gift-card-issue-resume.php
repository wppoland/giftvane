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
    /** Recipient whose mail send kills the process outright, or '' for none. */
    $GLOBALS['gc_exit_on'] = '';
    /** Every wp_mail() call: [recipient, body]. */
    $GLOBALS['gc_emailed']    = [];
    $GLOBALS['gc_transients'] = [];
    /** Scheduled single events: "hook|json(args)" => timestamp. */
    $GLOBALS['gc_events'] = [];
    /** How many events were booked in total, including ones since fired. */
    $GLOBALS['gc_scheduled'] = 0;
    /**
     * Where the transient store is mirrored, for the child process below.
     *
     * A fatal error, a max_execution_time stop and exit() all skip `finally`
     * and all run shutdown functions, and none of them can be staged inside the
     * process doing the asserting: it would take the asserting with it. So the
     * killed run happens in a child, and the child writes every transient
     * through to this file for the parent to read afterwards.
     */
    $GLOBALS['gc_store'] = (string) (getenv('GC_STORE') ?: '');

    function gc_persist(): void
    {
        if ($GLOBALS['gc_store'] === '') {
            return;
        }

        file_put_contents($GLOBALS['gc_store'], (string) json_encode($GLOBALS['gc_transients']));
    }

    function get_transient(string $key)
    {
        return $GLOBALS['gc_transients'][$key] ?? false;
    }

    function set_transient(string $key, $value, int $ttl = 0): bool
    {
        $GLOBALS['gc_transients'][$key] = $value;
        gc_persist();

        return true;
    }

    function delete_transient(string $key): bool
    {
        unset($GLOBALS['gc_transients'][$key]);
        gc_persist();

        return true;
    }

    /** @param array<int, mixed> $args */
    function gc_event_key(string $hook, array $args): string
    {
        return $hook . '|' . (string) json_encode($args);
    }

    /** @param array<int, mixed> $args */
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = [])
    {
        $GLOBALS['gc_events'][gc_event_key($hook, $args)] = $timestamp;
        $GLOBALS['gc_scheduled']++;

        return true;
    }

    /**
     * Fire a booked single event the way WP-Cron fires one.
     *
     * wp-cron.php unschedules a single event before it calls the hook, so the
     * event is already gone while the handler runs. A fake that left it booked
     * would report a leak the schedule never has.
     *
     * @param array<int, mixed> $args
     */
    function gc_fire(callable $handler, string $hook, array $args): void
    {
        wp_clear_scheduled_hook($hook, $args);
        $handler(...$args);
    }

    /** @param array<int, mixed> $args */
    function wp_next_scheduled(string $hook, array $args = [])
    {
        return $GLOBALS['gc_events'][gc_event_key($hook, $args)] ?? false;
    }

    /** @param array<int, mixed> $args */
    function wp_clear_scheduled_hook(string $hook, array $args = [])
    {
        $key     = gc_event_key($hook, $args);
        $cleared = isset($GLOBALS['gc_events'][$key]) ? 1 : 0;
        unset($GLOBALS['gc_events'][$key]);

        return $cleared;
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

        if ($GLOBALS['gc_exit_on'] === $to) {
            // What a fatal error or an execution-time stop looks like from in
            // here: `finally` never runs, shutdown functions do. Recorded
            // through the transient store first, so the parent can tell a run
            // that died here from one that never got this far.
            set_transient('gc_reached_the_kill', 1, 60);

            exit(7);
        }

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

        /** The meta as the last successful save left it. */
        /** @var array<string, mixed> */
        private array $saved = [];

        /** @var array<int, WC_Order_Item_Product> */
        private array $items = [];

        /** @var array<int, WC_Order_Item_Fee> */
        private array $fees = [];

        /** Meta key whose save is killed, or '' for none. */
        public string $explodeOnSaveOf = '';

        /**
         * The order's current status.
         *
         * `completed` by default because every run here starts from a
         * completion; a retry fires later, and by then the merchant may have
         * moved the order somewhere else.
         */
        private string $status = 'completed';

        /** @var array<int, string> */
        public array $notes = [];

        public function __construct(private int $id = 100)
        {
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function set_status(string $status): void
        {
            $this->status = $status;
        }

        public function get_status(): string
        {
            return $this->status;
        }

        /** @param string|array<int, string> $status */
        public function has_status($status): bool
        {
            return is_array($status)
                ? in_array($this->status, $status, true)
                : $this->status === $status;
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

        public function add_order_note(string $note, int $isCustomerNote = 0, bool $addedByUser = false): int
        {
            $this->notes[] = $note;

            return count($this->notes);
        }

        public function save(): int
        {
            if ($this->explodeOnSaveOf !== '' && isset($this->meta[$this->explodeOnSaveOf])) {
                // A save that never completed did not write the meta either,
                // and the next request reads the order back from the database.
                // Keeping it in memory would let a later run see a flag that
                // was never stored, and pass on the strength of it.
                $this->meta = $this->saved;

                throw new \RuntimeException('order save killed');
            }

            $this->saved = $this->meta;

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
            retryHook: 'giftcards_issue_retry',
            labels: [
                'fee_label'     => 'Gift card ({code})',
                'email_subject' => 'You have received a {amount} gift card',
                'email_body'    => "Your code: {code}",
                'invalid_code'  => 'no',
                'applied'       => 'yes',
                'retry_exhausted' => 'Gift card issuing did not finish on {attempts} attempts for this order.',
            ],
            isEnabled: static fn (): bool => true,
            settings: static fn (): array => ['code_prefix' => 'GC-'],
            isGiftCard: static fn (\WC_Product $product): bool => true,
            resolveCard: static fn (\WC_Order_Item_Product $item): array => [25.0, 'recipient@example.test'],
            renderField: static function (string $template, array $context): void {
            },
        );
    }

    // --- The child: a run that is killed rather than unwound. ---------------
    //
    // Reached only when this file re-runs itself as a child process, which the
    // parent does a few lines down.

    if (getenv('GC_CHILD') === '1') {
        $table  = new FakeCardTable();
        $engine = engineFor($table);

        $order = new \WC_Order(100);
        $order->setItems([new \WC_Order_Item_Product(new \WC_Product(7), 3)]);
        $GLOBALS['gc_orders'][100] = $order;

        $GLOBALS['gc_exit_on'] = 'recipient@example.test';

        // Ends inside the first send, the way a fatal error or an
        // execution-time stop would.
        $engine->handleOrderCompleted(100);

        exit(0);
    }

    // --- The lock may not outlive the run it guards. ------------------------

    $store = (string) tempnam(sys_get_temp_dir(), 'gc-lock-');

    exec(
        sprintf(
            'GC_CHILD=1 GC_STORE=%s %s %s',
            escapeshellarg($store),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
        ),
        $childOutput,
        $childStatus,
    );

    $persisted = json_decode((string) file_get_contents($store), true);
    $persisted = is_array($persisted) ? $persisted : [];
    unlink($store);

    check('the child died where it was told to', 7, $childStatus);
    check('the child got as far as the kill', true, isset($persisted['gc_reached_the_kill']));
    check('a killed run leaves no lock standing', false, isset($persisted['giftcards_redeem_code_lock_100']));

    stop();

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

    // An exception unwinds through `finally`, so this run released its own
    // lock; the child above is what covers the kill that does not unwind.
    check('the unwound run released its lock', [], $GLOBALS['gc_transients']);

    // Nothing else would come back to this order: `completed` fires on the
    // transition and the order is already Completed. The retry the dead run
    // booked is the only thing that can finish the job.
    check(
        'the killed run booked its own retry',
        true,
        wp_next_scheduled('giftcards_issue_retry', [100, 1]) !== false,
    );

    // Fired the way WP-Cron fires it, with the arguments it was booked with.
    $GLOBALS['gc_explode_on'] = '';

    gc_fire([$engine, 'handleOrderCompleted'], 'giftcards_issue_retry', [100, 1]);

    check('three paid units, three cards', 3, count($table->rows));
    check('the finished run left no retry behind', [], $GLOBALS['gc_events']);

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
    check('the order that finished cleared its retry', [], $GLOBALS['gc_events']);

    stop();

    // --- A run that keeps dying stops retrying, and says so. ---------------

    $table  = new FakeCardTable();
    $engine = engineFor($table);

    $order = new \WC_Order(300);
    $order->setItems([new \WC_Order_Item_Product(new \WC_Product(9), 1)]);
    $GLOBALS['gc_orders'][300] = $order;
    $GLOBALS['gc_explode_on']  = 'recipient@example.test';
    $GLOBALS['gc_events']      = [];
    $GLOBALS['gc_scheduled']   = 0;

    // Six runs: the completion itself and the five retries it is worth.
    for ($attempt = 0; $attempt <= 5; $attempt++) {
        $GLOBALS['gc_transients'] = [];

        try {
            if ($attempt === 0) {
                $engine->handleOrderCompleted(300, 0);
            } else {
                gc_fire([$engine, 'handleOrderCompleted'], 'giftcards_issue_retry', [300, $attempt]);
            }
        } catch (\RuntimeException) {
            // Every run on this order dies in the same send.
        }
    }

    check('five retries were booked and no sixth', 5, $GLOBALS['gc_scheduled']);
    check('the run that gave up booked nothing', [], $GLOBALS['gc_events']);
    check('the merchant is told, once', 1, count($order->notes));
    check(
        'the note says what happened',
        'Gift card issuing did not finish on 5 attempts for this order.',
        $order->notes[0] ?? '',
    );

    stop();

    // --- A retry may not act on an order that has left Completed. ----------
    //
    // `order_status_completed` fires on the transition, so it is Completed by
    // definition. The scheduled retry is not: it comes round up to five times
    // over more than an hour, and the merchant can refund or cancel in that
    // window. Both halves of the run are destructive - the issuing loop funds
    // cards and mails codes, and the redemption takes the balance off the
    // shopper's card - so both have to stop.

    $table  = new FakeCardTable();
    $engine = engineFor($table);
    $cardId = $table->issue('GC-REFUNDME', 100.0, 'holder@example.test', 1);

    $order = new \WC_Order(400);
    $order->setItems([new \WC_Order_Item_Product(new \WC_Product(11), 2)]);
    $order->setFees([new \WC_Order_Item_Fee(-30.0)]);
    $order->update_meta_data('giftcards_redeem_code', 'GC-REFUNDME');
    $GLOBALS['gc_orders'][400] = $order;
    $GLOBALS['gc_transients']  = [];
    $GLOBALS['gc_events']      = [];
    $GLOBALS['gc_emailed']     = [];
    $GLOBALS['gc_explode_on']  = 'recipient@example.test';

    try {
        $engine->handleOrderCompleted(400);
    } catch (\RuntimeException) {
        // Dies in the first send, one unit funded, the second still owed.
    }

    check(
        'the killed run booked a retry on the order about to be refunded',
        true,
        wp_next_scheduled('giftcards_issue_retry', [400, 1]) !== false,
    );

    $fundedBeforeRefund  = count($table->rows);
    $emailedBeforeRefund = count($GLOBALS['gc_emailed']);

    check('one unit was funded before the refund', 2, $fundedBeforeRefund);
    check('the balance was untouched before the refund', 100.0, $table->rows[$cardId]->balance);

    // The merchant refunds before the retry comes round.
    $order->set_status('refunded');
    $GLOBALS['gc_explode_on'] = '';
    $GLOBALS['gc_transients'] = [];

    gc_fire([$engine, 'handleOrderCompleted'], 'giftcards_issue_retry', [400, 1]);

    check('a refunded order funds no further card', $fundedBeforeRefund, count($table->rows));
    check('a refunded order sends no further code', $emailedBeforeRefund, count($GLOBALS['gc_emailed']));
    check('a refunded order burns no balance', 100.0, $table->rows[$cardId]->balance);
    check('a refunded order books no further retry', [], $GLOBALS['gc_events']);

    // Back to Completed, and the work it still owes runs: the guard stops a
    // retry on an order that moved, it does not strand the order for good.
    $order->set_status('completed');
    $GLOBALS['gc_transients'] = [];

    $engine->handleOrderCompleted(400);

    check('back in Completed, the second card is funded', 3, count($table->rows));
    check('back in Completed, the balance is taken once', 70.0, $table->rows[$cardId]->balance);

    stop();

    // --- The give-up note belongs to a run that did NOT finish. ------------
    //
    // The note was booked before the work, by the same call that decided not to
    // schedule a sixth retry. So the fifth retry SUCCEEDING still told the
    // merchant that issuing never finished, and sent them through a complete
    // order looking for a missing code.

    $table  = new FakeCardTable();
    $engine = engineFor($table);

    $order = new \WC_Order(500);
    $order->setItems([new \WC_Order_Item_Product(new \WC_Product(13), 1)]);
    $GLOBALS['gc_orders'][500] = $order;
    $GLOBALS['gc_events']      = [];
    $GLOBALS['gc_scheduled']   = 0;
    $GLOBALS['gc_emailed']     = [];

    for ($attempt = 0; $attempt <= 5; $attempt++) {
        $GLOBALS['gc_transients'] = [];

        // Every run dies except the last one, which is the whole point.
        $GLOBALS['gc_explode_on'] = $attempt === 5 ? '' : 'recipient@example.test';

        try {
            if ($attempt === 0) {
                $engine->handleOrderCompleted(500, 0);
            } else {
                gc_fire([$engine, 'handleOrderCompleted'], 'giftcards_issue_retry', [500, $attempt]);
            }
        } catch (\RuntimeException) {
            // Every run but the last dies in the same send.
        }
    }

    check('the last attempt issued the card', 1, count($table->rows));
    check('the order that finished left no retry behind', [], $GLOBALS['gc_events']);
    check('a final attempt that succeeds raises no alarm', 0, count($order->notes));

    stop();
    echo "\nall checks passed\n";
}
