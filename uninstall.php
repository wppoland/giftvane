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

// Giftvane Pro adds a column to the gift-card table and writes its own rows
// there. While it is installed, active or not, the table stays (with its schema
// version) and the add-on's own uninstall drops it: whichever of the two is
// removed last does. Matched on the text domain, so a renamed folder counts.
// A bulk Delete uninstalls both in one request and core clears its cached
// plugin list only after the loop, so read the folders fresh.
if (! function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
wp_cache_delete('plugins', 'plugins');
$giftcards_keep_table = in_array('giftvane-pro', array_column(get_plugins(), 'TextDomain'), true);

$giftcards_uninstall_site = static function () use ($giftcards_keep_table): void {
    global $wpdb;

    if (! $giftcards_keep_table) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping the plugin's own table on uninstall.
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'giftcards'));
        delete_option('giftcards_db_version');
    }

    delete_option('giftcards_settings');

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
