<?php
/**
 * Recipient fields: what reaches the order line.
 * - an invalid email is dropped (the card then goes to the billing email);
 * - the message is sanitised and capped, the name capped;
 * - empty fields are not stored at all.
 *
 * Run: php tests/recipient-fields-check.php
 *
 * @package GiftCards
 */

declare(strict_types=1);

namespace GiftCards\Contract {
    interface HasHooks
    {
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/');

    function sanitize_email(string $s): string { return trim($s); }
    function is_email(string $s): bool { return (bool) filter_var($s, FILTER_VALIDATE_EMAIL); }
    function sanitize_text_field(string $s): string { return trim(strip_tags(str_replace(["\r", "\n"], ' ', $s))); }
    function sanitize_textarea_field(string $s): string { return trim(strip_tags($s)); }

    require __DIR__ . '/../src/Service/RecipientFields.php';

    use GiftCards\Service\RecipientFields as F;

    $failures = [];

    $v = F::clean('Ann', 'ann@example.com', "Happy birthday!\nLove, Bo");
    if ([F::META_NAME => 'Ann', F::META_EMAIL => 'ann@example.com', F::META_MESSAGE => "Happy birthday!\nLove, Bo"] !== $v) {
        $failures[] = 'valid values not kept as typed: ' . json_encode($v);
    }

    $v = F::clean('', 'not-an-email', '');
    if ([] !== $v) {
        $failures[] = 'invalid email or empty fields stored: ' . json_encode($v);
    }

    $v = F::clean(str_repeat('n', 300), '', '<b>hi</b>' . str_repeat('x', 900));
    if (100 !== mb_strlen($v[F::META_NAME] ?? '') || F::MESSAGE_MAX !== mb_strlen($v[F::META_MESSAGE] ?? '') || str_contains($v[F::META_MESSAGE], '<b>')) {
        $failures[] = 'name/message not capped or not sanitised';
    }

    if ([] !== $failures) {
        fwrite(STDERR, "FAIL\n - " . implode("\n - ", $failures) . "\n");
        exit(1);
    }

    echo "OK: recipient values validated, sanitised and capped\n";
}
