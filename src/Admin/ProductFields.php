<?php

declare(strict_types=1);

namespace GiftCards\Admin;

use GiftCards\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Adds the "This is a gift card" flag to the product editor (General tab) and
 * persists it as the `_giftcards_is_gift_card` product meta the engine reads via
 * its `isGiftCard` closure.
 *
 * The flag is the only thing the host has to resolve in admin; the amount is the
 * product price (resolved per-line by {@see \GiftCards\Service\GiftCardService})
 * and the recipient defaults to the order billing email, so a card is always
 * deliverable without extra configuration.
 */
final class ProductFields implements HasHooks
{
    private const META = '_giftcards_is_gift_card';

    private const NONCE = 'giftcards_product_data';

    public function registerHooks(): void
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'renderField']);
        add_action('woocommerce_admin_process_product_object', [$this, 'saveField']);
    }

    public function renderField(): void
    {
        woocommerce_wp_checkbox([
            'id'          => self::META,
            'label'       => __('Gift card', 'giftvane'),
            // Visible hint below the field, never hidden behind a tooltip.
            'description' => __('When checked, buying this product issues a unique code worth the product price and emails it to the recipient on order completion. Set the price to the card value (e.g. a $50 card > price $50). The recipient gets the code by email; the buyer also sees it on the order confirmation.', 'giftvane'),
            // Show the help text visibly below the field (not hidden in a tip).
            'desc_tip'    => false,
        ]);

        wp_nonce_field(self::NONCE, 'giftcards_product_data_nonce');
    }

    public function saveField(\WC_Product $product): void
    {
        $nonce = isset($_POST['giftcards_product_data_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['giftcards_product_data_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, self::NONCE)) {
            return;
        }

        if (! current_user_can('edit_product', $product->get_id())) {
            return;
        }

        $isGiftCard = isset($_POST[self::META]) ? 'yes' : 'no';

        $product->update_meta_data(self::META, $isGiftCard);
    }
}
