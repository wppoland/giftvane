<?php

declare(strict_types=1);

namespace GiftCards\Repository;

use WPPoland\StorefrontKit\GiftCard\DuplicateGiftCardCodeException;
use WPPoland\StorefrontKit\GiftCard\GiftCardRepository;

defined('ABSPATH') || exit;

/**
 * Custom-table storage for the storefront-kit {@see GiftCardRepository}.
 *
 * A gift-card record is `{code, balance, recipient_email, order_id}`, persisted
 * in `{$wpdb->prefix}giftcards`. The table is created by the
 * {@see \GiftCards\Migrator}. Storage lives here (not in the kit) so the library
 * hard-codes no table name and no `$wpdb` access, the same delegation the kit
 * uses for the waitlist. The `$wpdb->prefix`-derived table name cannot be passed
 * as a placeholder, so the direct-query / unescaped-DB-parameter sniffs are
 * disabled with justification, mirroring restock's WaitlistRepository.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; name derived from $wpdb->prefix and cannot be parameterised; queries are prepared.
 */
final class GiftCardTableRepository implements GiftCardRepository
{
    public function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'giftcards';
    }

    /**
     * Persist a freshly issued gift card.
     *
     * The DB-level UNIQUE index on `code` (see {@see \GiftCards\Migrator}) is the
     * authority on uniqueness: if a concurrent issue inserted the same code
     * between the kit's pre-check and this insert, the insert is rejected and we
     * translate that into {@see DuplicateGiftCardCodeException} so the kit engine
     * regenerates a new code instead of silently producing a duplicate.
     *
     * @throws DuplicateGiftCardCodeException When the code collides with an
     *         existing row (the UNIQUE index rejects the insert).
     */
    public function issue(string $code, float $balance, string $recipientEmail, int $orderId): int
    {
        global $wpdb;

        // Clear any prior error so we only inspect this insert's outcome.
        $wpdb->last_error = '';

        $result = $wpdb->insert(
            $this->table(),
            [
                'code'            => $code,
                'balance'         => $balance,
                'recipient_email' => $recipientEmail,
                'order_id'        => $orderId,
                'created_at'      => current_time('mysql', true),
            ],
            ['%s', '%f', '%s', '%d', '%s'],
        );

        if (false === $result) {
            if ($this->isDuplicateKeyError((string) $wpdb->last_error)) {
                throw new DuplicateGiftCardCodeException(
                    'Gift card code collided with an existing row.'
                );
            }

            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Whether a `$wpdb` error string is a UNIQUE/PRIMARY duplicate-key failure.
     *
     * MySQL/MariaDB report this as error 1062 with a "Duplicate entry" message;
     * match on both so the detection is resilient to localised messages and to
     * drivers that surface only the numeric code.
     */
    private function isDuplicateKeyError(string $error): bool
    {
        if ($error === '') {
            return false;
        }

        return str_contains($error, '1062')
            || stripos($error, 'Duplicate entry') !== false;
    }

    /**
     * @return object{id:int,code:string,balance:float,recipient_email:string,order_id:int}|null
     */
    public function findByCode(string $code): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, code, balance, recipient_email, order_id FROM %i WHERE code = %s LIMIT 1',
                $this->table(),
                $code,
            ),
        );

        if (! is_object($row)) {
            return null;
        }

        return (object) [
            'id'              => (int) $row->id,
            'code'            => (string) $row->code,
            'balance'         => (float) $row->balance,
            'recipient_email' => (string) $row->recipient_email,
            'order_id'        => (int) $row->order_id,
        ];
    }

    public function updateBalance(int $id, float $balance): void
    {
        global $wpdb;

        $wpdb->update(
            $this->table(),
            ['balance' => $balance],
            ['id' => $id],
            ['%f'],
            ['%d'],
        );
    }

    /**
     * Hard ceiling on rows returned for a single order's gift-card display, so a
     * malformed or abusive order (e.g. an enormous line quantity) can never make
     * the order-confirmation page or email render an unbounded table.
     */
    private const MAX_ORDER_CARDS = 200;

    /**
     * Codes (with their current balance) issued by a given order, oldest first.
     *
     * Used by the order-confirmation display so a buyer who purchased a gift
     * card sees the issued code(s) on the thank-you page and in order emails,
     * without waiting for the recipient email. Reads only the rows belonging to
     * that order; it never exposes other orders' cards. Bounded by
     * {@see self::MAX_ORDER_CARDS} to keep the query and the rendered table from
     * ever growing without limit.
     *
     * @return list<array{code: string, balance: float}>
     */
    public function findByOrderId(int $orderId): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT code, balance FROM %i WHERE order_id = %d ORDER BY id ASC LIMIT %d',
                $this->table(),
                $orderId,
                self::MAX_ORDER_CARDS,
            ),
        );

        if (! is_array($rows)) {
            return [];
        }

        $cards = [];

        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $cards[] = [
                'code'    => (string) $row->code,
                'balance' => (float) $row->balance,
            ];
        }

        return $cards;
    }
}
