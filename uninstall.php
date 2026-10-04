<?php

/**
 * Gift Cards uninstall routine.
 *
 * Drops the plugin table and removes plugin options when the user deletes the
 * plugin from the WordPress admin, on every site of a network.
 *
 * @package GiftCards
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

$giftcards_uninstall_site = static function (): void {
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping the plugin's own table on uninstall.
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'giftcards'));

    delete_option('giftcards_settings');
    delete_option('giftcards_db_version');

    // Any retry still waiting on an order whose gift cards were never issued.
    // A delete can arrive without a deactivation (WP-CLI, or a restored site),
    // and cron events are per site. The literal is GiftCardService::RETRY_HOOK,
    // which is not loadable from here.
    wp_unschedule_hook('giftcards_issue_retry');
};

// Each site in a network has its own table and options.
if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $giftcards_site_id) {
        switch_to_blog((int) $giftcards_site_id);
        $giftcards_uninstall_site();
        restore_current_blog();
    }
} else {
    $giftcards_uninstall_site();
}

// The PRO banner's dismissal is stored per user. User meta is global, not
// per-site, so one delete_metadata() with $delete_all covers the network.
delete_metadata('user', 0, 'giftcards_pro_banner_dismissed', '', true);
