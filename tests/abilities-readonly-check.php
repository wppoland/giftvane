<?php

/**
 * Every ability this plugin registers only reads, so each must carry
 * meta.annotations.readonly. The Abilities API looks there, and only there:
 * with the flag at the top level of meta it treated the abilities as writes
 * and a GET to /wp-abilities/v1/.../run returned 405.
 *
 * Run: php tests/abilities-readonly-check.php
 */

declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__);

    $abilities = [];

    function wp_register_ability(string $name, array $args): void
    {
        $GLOBALS['abilities'][$name] = $args;
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    require __DIR__ . '/../autoload.php';

    $service = (new ReflectionClass(\GiftCards\Service\AbilitiesService::class))->newInstanceWithoutConstructor();
    $service->registerAbilities();

    $failures = 0;
    if ([] === $abilities) {
        echo "FAIL: no abilities registered\n";
        $failures++;
    }
    foreach ($abilities as $name => $args) {
        if (true !== ($args['meta']['annotations']['readonly'] ?? null)) {
            echo "FAIL: {$name} is not annotated readonly, so GET returns 405\n";
            $failures++;
        }
        $required = $args['input_schema']['required'] ?? [];
        if ([] === $required && ! array_key_exists('default', $args['input_schema'] ?? [])) {
            echo "FAIL: {$name} takes no required input but has no default, so a call with no input is rejected\n";
            $failures++;
        }
    }

    echo 0 === $failures ? 'OK: ' . count($abilities) . " abilities annotated readonly\n" : '';
    exit($failures > 0 ? 1 : 0);
}
