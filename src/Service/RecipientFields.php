<?php

declare(strict_types=1);

namespace GiftCards\Service;

use GiftCards\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Optional recipient name, email and personal message on gift card products.
 *
 * Captured at add to cart, shown with the cart line and the order line, and
 * stored on the order line under `_giftcards_recipient_*`. The delivery reads
 * the email there (GiftCardService::resolveCard(), billing email when empty)
 * and puts the name and message into the gift card email.
 */
final class RecipientFields implements HasHooks
{
    public const META_EMAIL   = '_giftcards_recipient_email';
    public const META_NAME    = '_giftcards_recipient_name';
    public const META_MESSAGE = '_giftcards_recipient_message';

    /** Longest personal message kept, in characters. */
    public const MESSAGE_MAX = 500;

    private const NAME_MAX = 100;

    public function __construct(private readonly GiftCardService $cards)
    {
    }

    public function registerHooks(): void
    {
        if (! (bool) ($this->cards->settings()['enabled'] ?? false)) {
            return;
        }

        // After the PRO amount field (priority 10), so the amount comes first.
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render'], 20);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate'], 10, 2);
        add_filter('woocommerce_add_cart_item_data', [$this, 'capture'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'cartItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'saveLineItem'], 10, 3);
        // Underscore keys are hidden on the order line; show them by label.
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'orderItemData'], 10, 2);
    }

    public function render(): void
    {
        global $product;

        if (! $product instanceof \WC_Product || ! $this->cards->isGiftCardProduct($product)) {
            return;
        }

        echo '<fieldset class="giftcards-recipient"><legend>' . esc_html__('Send it to someone (optional)', 'giftvane') . '</legend>';

        printf(
            '<p class="form-row"><label for="giftcards_recipient_name">%1$s</label><input type="text" class="input-text" id="giftcards_recipient_name" name="giftcards_recipient_name" maxlength="%2$d" autocomplete="off" /></p>',
            esc_html__('Recipient name', 'giftvane'),
            (int) self::NAME_MAX,
        );

        printf(
            '<p class="form-row"><label for="giftcards_recipient_email">%1$s</label><input type="email" class="input-text" id="giftcards_recipient_email" name="giftcards_recipient_email" autocomplete="off" inputmode="email" aria-describedby="giftcards_recipient_email_help" /><span class="description" id="giftcards_recipient_email_help">%2$s</span></p>',
            esc_html__('Recipient email', 'giftvane'),
            esc_html__('Leave blank to receive the gift card at your own billing email.', 'giftvane'),
        );

        printf(
            '<p class="form-row"><label for="giftcards_recipient_message">%1$s</label><textarea class="input-text" id="giftcards_recipient_message" name="giftcards_recipient_message" rows="3" maxlength="%2$d"></textarea></p>',
            esc_html__('Personal message', 'giftvane'),
            (int) self::MESSAGE_MAX,
        );

        echo '</fieldset>';
    }

    /**
     * A recipient email, when given, must be valid.
     */
    public function validate(mixed $passed, mixed $productId): mixed
    {
        if (! $passed || ! $this->isGiftCardId((int) $productId)) {
            return $passed;
        }

        $email = $this->posted('giftcards_recipient_email');

        if ($email !== '' && ! is_email($email)) {
            wc_add_notice(esc_html__('Please enter a valid recipient email address, or leave it blank.', 'giftvane'), 'error');

            return false;
        }

        return $passed;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function capture(mixed $data, mixed $productId): mixed
    {
        if (! is_array($data) || ! $this->isGiftCardId((int) $productId)) {
            return $data;
        }

        $values = self::clean(
            $this->posted('giftcards_recipient_name'),
            $this->posted('giftcards_recipient_email'),
            $this->posted('giftcards_recipient_message', true),
        );

        if ($values === []) {
            return $data;
        }

        // Cards for different people stay separate cart lines; the same
        // recipient twice still merges.
        return $data + $values + ['giftcards_recipient_line' => md5((string) wp_json_encode($values))];
    }

    /**
     * Sanitised, capped recipient values keyed by meta key, empty ones left out.
     *
     * @return array<string, string>
     */
    public static function clean(string $name, string $email, string $message): array
    {
        $email = sanitize_email($email);

        $values = [
            self::META_NAME    => mb_substr(sanitize_text_field($name), 0, self::NAME_MAX),
            self::META_EMAIL   => is_email($email) ? $email : '',
            self::META_MESSAGE => mb_substr(sanitize_textarea_field($message), 0, self::MESSAGE_MAX),
        ];

        return array_filter($values, static fn (string $value): bool => $value !== '');
    }

    /**
     * @param array<int, array<string, mixed>> $itemData
     * @param array<string, mixed>             $cartItem
     * @return array<int, array<string, mixed>>
     */
    public function cartItemData(mixed $itemData, mixed $cartItem): mixed
    {
        if (! is_array($itemData) || ! is_array($cartItem)) {
            return $itemData;
        }

        foreach ($this->labels() as $key => $label) {
            if (isset($cartItem[$key]) && is_string($cartItem[$key]) && $cartItem[$key] !== '') {
                $itemData[] = ['key' => $label, 'value' => $cartItem[$key]];
            }
        }

        return $itemData;
    }

    /**
     * @param array<string, mixed> $values The cart item.
     */
    public function saveLineItem(\WC_Order_Item_Product $item, string $cartItemKey, array $values): void
    {
        unset($cartItemKey);

        foreach (array_keys($this->labels()) as $key) {
            if (isset($values[$key]) && is_string($values[$key]) && $values[$key] !== '') {
                $item->add_meta_data($key, $values[$key], true);
            }
        }
    }

    /**
     * @param array<int|string, object> $formatted
     * @return array<int|string, object>
     */
    public function orderItemData(mixed $formatted, mixed $item): mixed
    {
        if (! is_array($formatted) || ! $item instanceof \WC_Order_Item_Product) {
            return $formatted;
        }

        foreach ($this->labels() as $key => $label) {
            $value = $item->get_meta($key);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $formatted[$key] = (object) [
                'key'           => $key,
                'value'         => $value,
                'display_key'   => $label,
                'display_value' => wpautop(esc_html($value)),
            ];
        }

        return $formatted;
    }

    /**
     * @return array<string, string>
     */
    private function labels(): array
    {
        return [
            self::META_NAME    => __('Recipient name', 'giftvane'),
            self::META_EMAIL   => __('Recipient email', 'giftvane'),
            self::META_MESSAGE => __('Personal message', 'giftvane'),
        ];
    }

    private function isGiftCardId(int $productId): bool
    {
        $product = wc_get_product($productId);

        return $product instanceof \WC_Product && $this->cards->isGiftCardProduct($product);
    }

    /**
     * A posted field. Add to cart is WooCommerce's own form and carries no
     * nonce of ours, the same as every add-to-cart field; values are
     * sanitised here and again in clean().
     */
    private function posted(string $field, bool $multiline = false): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce add-to-cart form.
        if (! isset($_POST[$field]) || ! is_string($_POST[$field])) {
            return '';
        }

        return $multiline
            ? sanitize_textarea_field(wp_unslash($_POST[$field])) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce add-to-cart form.
            : sanitize_text_field(wp_unslash($_POST[$field])); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce add-to-cart form.
    }
}
