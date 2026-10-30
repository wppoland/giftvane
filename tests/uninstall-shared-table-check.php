<?php

/**
 * Fails when uninstall.php drops the gift-card table while another installed
 * plugin still writes to it.
 *
 * Giftvane Pro adds a currency column to wp_giftcards and inserts its own rows
 * there. Uninstalling this plugin first used to drop the table with every card
 * and balance in it. Whichever of the two is uninstalled last drops it.
 *
 * Runs uninstall.php against a fake $wpdb with and without that plugin on disk.
 *
 *   php tests/uninstall-shared-table-check.php
 */

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/');
define('WP_UNINSTALL_PLUGIN', 'giftvane/giftvane.php');

$GLOBALS['pro_installed'] = false;
$GLOBALS['deleted']       = [];

function get_plugins(): array
{
    $plugins = ['giftvane/giftvane.php' => ['TextDomain' => 'giftvane']];
    if ($GLOBALS['pro_installed']) {
        $plugins['any-folder/giftvane-pro.php'] = ['TextDomain' => 'giftvane-pro'];
    }

    return $plugins;
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

$run = static function (bool $proInstalled): array {
    global $wpdb;
    $wpdb                     = new FakeWpdb();
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

echo "OK: uninstall keeps wp_giftcards while giftvane-pro is installed, drops it otherwise\n";
