<?php

/**
 * Gift Cards uninstall routine.
 *
 * Drops the plugin table and removes plugin options when the user deletes the
 * plugin from the WordPress admin.
 *
 * @package GiftCards
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Drop the gift-cards table.
$giftcards_table = $wpdb->prefix . 'giftcards';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, cannot be parameterised.
$wpdb->query( "DROP TABLE IF EXISTS {$giftcards_table}" );

// Remove options.
delete_option( 'giftcards_settings' );
delete_option( 'giftcards_db_version' );

// The PRO banner's dismissal is stored per user, so it belongs to the
// plugin rather than to the site content. User meta is global, not
// per-site, which is why this uses delete_metadata's \$delete_all rather
// than a loop over the users of one blog.
delete_metadata('user', 0, 'giftcards_pro_banner_dismissed', '', true);
