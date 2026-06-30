<?php
/**
 * Default settings, merged under the option key `giftcards_settings`.
 *
 * The feature ships enabled. The merchant tunes the optional code prefix, the
 * checkout discount label and the recipient email templates. All gift-card logic
 * lives in the storefront-kit GiftCardEngine; these values are passed through to
 * it as the resolved settings / labels (see GiftCardService).
 *
 * Strings are wrapped in __() so the seeded defaults are translatable; the
 * GiftCardEngine receives the resolved (translated) values via the service.
 *
 * @package GiftCards
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'enabled' => true,

    // Optional prefix prepended to every generated code (e.g. "GIFT-").
    'code_prefix' => '',

    // Label of the discount line added at checkout when a code is applied.
    // {code} is interpolated with the applied gift-card code.
    'fee_label' => __('Gift card ({code})', 'plogins-giftcards'),

    // Whether the buyer's order-confirmation page and emails list the codes
    // issued by that order (so a self-purchase shows the code immediately).
    'show_codes_on_order' => true,

    // Recipient email templates. {code} and {amount} are interpolated.
    'email_subject' => __('You have received a {amount} gift card', 'plogins-giftcards'),
    'email_body'    => __("You have received a gift card worth {amount}.\n\nUse this code at checkout: {code}", 'plogins-giftcards'),
];
