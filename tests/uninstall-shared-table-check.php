<?php

/**
 * Fails when uninstall.php drops the gift-card table while another installed
 * plugin still writes to it.
 *
 * Giftvane Pro adds a currency column to wp_giftcards and inserts its own rows
 * there. Uninstalling this plugin first used to drop the table with every card
 * and balance in it. Whichever of the two is uninstalled last drops it.
 *
 * Runs uninstall.php against a fake $wpdb with and without that plugin on disk,
 * and once more the way a bulk Delete of both plugins runs it: core's
 * delete_plugins() uninstalls them one after the other in one request, so the
 * plugin list cached by the first uninstall still names the add-on whose
 * folder is already gone.
 *
 *   php tests/uninstall-shared-table-check.php
 */

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/');
define('WP_UNINSTALL_PLUGIN', 'giftvane/giftvane.php');

$GLOBALS['pro_installed'] = false;
$GLOBALS['deleted']       = [];
$GLOBALS['plugins_cache'] = null;

// Like core: the list is cached for the request until wp_cache_delete('plugins', 'plugins').
function get_plugins(): array
{
    if ($GLOBALS['plugins_cache'] !== null) {
        return $GLOBALS['plugins_cache'];
    }
    $plugins = ['giftvane/giftvane.php' => ['TextDomain' => 'giftvane']];
    if ($GLOBALS['pro_installed']) {
        $plugins['any-folder/giftvane-pro.php'] = ['TextDomain' => 'giftvane-pro'];
    }

    return $GLOBALS['plugins_cache'] = $plugins;
}
function wp_cache_delete(string $key, string $group = ''): bool
{
    if ($key === 'plugins' && $group === 'plugins') {
        $GLOBALS['plugins_cache'] = null;
    }

    return true;
}
function is_multisite(): bool
{
    return false;
}
function delete_option(string $name): bool
{
    $GLOBALS['deleted'][] = $name;

    return true;
}
function wp_unschedule_hook(string $hook): int
{
    return 0;
}
function delete_metadata(...$args): bool
{
    return true;
}

final class FakeWpdb
{
    public string $prefix = 'wp_';
    /** @var list<string> */
    public array $queries = [];

    public function prepare(string $sql, ...$args): string
    {
        return vsprintf(str_replace('%i', '`%s`', $sql), $args);
    }

    public function query(string $sql): int
    {
        $this->queries[] = $sql;

        return 0;
    }
}

$run = static function (bool $proInstalled, bool $staleCache = false): array {
    global $wpdb;
    $wpdb                     = new FakeWpdb();
    $GLOBALS['plugins_cache'] = null;
    if ($staleCache) {
        // A bulk delete listed the add-on, uninstalled and deleted it first.
        $GLOBALS['pro_installed'] = true;
        get_plugins();
    }
    $GLOBALS['pro_installed'] = $proInstalled;
    $GLOBALS['deleted']       = [];
    require dirname(__DIR__) . '/uninstall.php';

    return [
        'dropped' => (bool) preg_grep('/DROP TABLE IF EXISTS `wp_giftcards`/', $wpdb->queries),
        'deleted' => $GLOBALS['deleted'],
    ];
};

$fail = static function (string $msg): void {
    fwrite(STDERR, 'FAIL: ' . $msg . "\n");
    exit(1);
};

$alone = $run(false);
if (! $alone['dropped']) {
    $fail('wp_giftcards not dropped when this plugin is the last one installed');
}
if (! in_array('giftcards_db_version', $alone['deleted'], true)) {
    $fail('giftcards_db_version kept when the table was dropped');
}

$shared = $run(true);
if ($shared['dropped']) {
    $fail('wp_giftcards dropped while giftvane-pro is still installed and writes to it');
}
if (in_array('giftcards_db_version', $shared['deleted'], true)) {
    $fail('giftcards_db_version deleted while its table is kept');
}

$bulk = $run(false, true);
if (! $bulk['dropped']) {
    $fail('wp_giftcards kept after a bulk delete removed giftvane-pro first: the cached plugin list still named it');
}
if (! in_array('giftcards_db_version', $bulk['deleted'], true)) {
    $fail('giftcards_db_version kept after a bulk delete removed giftvane-pro first');
}

echo "OK: uninstall keeps wp_giftcards while giftvane-pro is installed, drops it otherwise, also after a bulk delete\n";
