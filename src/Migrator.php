<?php

declare(strict_types=1);

namespace GiftCards;

defined('ABSPATH') || exit;

/**
 * Idempotent schema/version migrations, run on every boot. Compares a stored
 * option against {@see self::DB_VERSION} and applies forward steps as needed.
 *
 * The schema version is tracked independently of the plugin {@see VERSION}: a
 * plugin release that ships no schema change leaves this untouched, and a schema
 * fix can ship without forcing a plugin version bump. Each forward step is
 * written to be safe to run more than once.
 *
 * The custom-table name is derived from `$wpdb->prefix` and cannot be passed as
 * a placeholder, so the direct-query / unescaped-DB-parameter sniffs are
 * disabled here with justification, mirroring the repository and restock's
 * WaitlistRepository. All user/data values are still prepared.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; name derived from $wpdb->prefix and cannot be parameterised; data values are prepared.
 */
final class Migrator
{
    private const OPTION = 'giftcards_db_version';

    private const SETTINGS = 'giftcards_settings';

    /**
     * Schema version. Bump when a forward migration step is added.
     *
     *  - "1" — initial gift-cards table.
     *  - "2" — enforce a DB-level UNIQUE index on the code column so the kit's
     *          collision-safe issuance (catch + regenerate) has a real authority
     *          to fail against on concurrent inserts.
     */
    private const DB_VERSION = '2';

    public function maybeMigrate(): void
    {
        $current = (string) get_option(self::OPTION, '0');

        if (version_compare($current, self::DB_VERSION, '>=')) {
            return;
        }

        // createGiftCardsTable() already declares the UNIQUE index, so fresh
        // installs are correct from the start. ensureCodeUniqueIndex() repairs
        // installs created before the index existed (db_version < 2).
        $this->createGiftCardsTable();
        $this->ensureCodeUniqueIndex();
        $this->seedDefaultSettings();

        update_option(self::OPTION, self::DB_VERSION, false);
    }

    /**
     * Create the gift-cards table: one row per issued card holding the unique
     * code, remaining balance, recipient email and source order id. The UNIQUE
     * index on `code` is what lets a colliding insert fail so the kit engine can
     * catch {@see \WPPoland\StorefrontKit\GiftCard\DuplicateGiftCardCodeException}
     * and regenerate.
     */
    private function createGiftCardsTable(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = $wpdb->prefix . 'giftcards';
        $collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(64) NOT NULL,
            balance decimal(19,4) NOT NULL DEFAULT 0,
            recipient_email varchar(191) NOT NULL DEFAULT '',
            order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY order_id (order_id)
        ) {$collate};";

        dbDelta($sql);
    }

    /**
     * Guarantee the UNIQUE index on `code` exists on installs that predate it.
     *
     * `dbDelta()` does not reliably add a UNIQUE index to an existing table, so
     * this issues an explicit, guarded `ALTER`. It is idempotent: if the index
     * is already present it does nothing. Because a pre-existing duplicate code
     * (only possible on the old, index-less schema where a collision could slip
     * through) would make the `ALTER` fail, any duplicates are de-duplicated
     * first — the oldest row per code keeps the code, later rows have their code
     * suffixed with their row id so they stay unique without losing the row or
     * its balance.
     */
    private function ensureCodeUniqueIndex(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'giftcards';

        if ($this->hasUniqueCodeIndex($table)) {
            return;
        }

        $this->dedupeCodes($table);

        $wpdb->query($wpdb->prepare('ALTER TABLE %i ADD UNIQUE KEY code (code)', $table));
    }

    private function hasUniqueCodeIndex(string $table): bool
    {
        global $wpdb;

        // SHOW INDEX cannot use %i; the table name is $wpdb->prefix-derived.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name derived from $wpdb->prefix; cannot be a placeholder.
        $indexes = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", 'code'));

        return is_array($indexes) && $indexes !== [];
    }

    /**
     * Rename any duplicate `code` values so the UNIQUE index can be applied
     * without dropping rows. Keeps the lowest-id row's code untouched; appends
     * `-<id>` to every later collision. Idempotent and a no-op when there are no
     * duplicates (the normal case).
     */
    private function dedupeCodes(string $table): void
    {
        global $wpdb;

        $duplicateIds = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT g.id
             FROM %i g
             JOIN (
                 SELECT code, MIN(id) AS keep_id
                 FROM %i
                 GROUP BY code
                 HAVING COUNT(*) > 1
             ) d ON g.code = d.code AND g.id <> d.keep_id',
                $table,
                $table
            )
        );

        if (! is_array($duplicateIds) || $duplicateIds === []) {
            return;
        }

        foreach ($duplicateIds as $rawId) {
            $id = (int) $rawId;

            // Suffix with the row id (unique by definition), truncating to keep
            // the column's 64-char limit. Data value -> fully prepared.
            $wpdb->query(
                $wpdb->prepare(
                    'UPDATE %i SET code = CONCAT(LEFT(code, 50), %s) WHERE id = %d',
                    $table,
                    '-' . $id,
                    $id
                )
            );
        }
    }

    /**
     * Seed the default settings once, without clobbering an existing config.
     */
    private function seedDefaultSettings(): void
    {
        if (get_option(self::SETTINGS, null) !== null) {
            return;
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require GIFTCARDS_DIR . 'config/defaults.php';

        add_option(self::SETTINGS, $defaults, '', false);
    }
}
