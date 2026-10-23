<?php
/**
 * Checkout redeem-code field.
 *
 * Echoed by the storefront-kit GiftCardEngine via the host `renderField`
 * closure ({@see \GiftCards\Service\GiftCardService::renderField()}) on
 * `woocommerce_review_order_before_payment`.
 *
 * The feedback line height is reserved in CSS so showing the "applied" message
 * after a code is entered does not shift the payment section (no CLS).
 *
 * @package GiftCards
 *
 * @var string $giftcards_field_name   Input name the engine reads on update.
 * @var string $giftcards_nonce_field  Nonce value for the redeem action.
 * @var string $giftcards_applied_code Currently applied code, if any.
 * @var float|null $giftcards_remaining Balance left after this order, null when no valid card is in the field.

 */

defined('ABSPATH') || exit;

?>
<div class="giftcards-redeem">
    <div class="giftcards-redeem__head">
        <span aria-hidden="true">&#127873;</span>
        <h3 class="giftcards-redeem__title"><?php esc_html_e('Have a gift card?', 'giftvane'); ?></h3>
    </div>
    <p class="giftcards-redeem__hint" id="giftcards-redeem-hint">
        <?php esc_html_e('Enter your code to apply its balance to this order. Any unused balance stays on the card for next time.', 'giftvane'); ?>
    </p>
    <p class="form-row giftcards-redeem__row">
        <label class="giftcards-redeem__label" for="<?php echo esc_attr($giftcards_field_name); ?>">
            <?php esc_html_e('Gift card code', 'giftvane'); ?>
        </label>
        <input
            type="text"
            id="<?php echo esc_attr($giftcards_field_name); ?>"
            name="<?php echo esc_attr($giftcards_field_name); ?>"
            value="<?php echo esc_attr($giftcards_applied_code); ?>"
            class="input-text giftcards-redeem__input"
            placeholder="<?php esc_attr_e('e.g. GIFT-AB12CD34', 'giftvane'); ?>"
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            inputmode="text"
            aria-describedby="giftcards-redeem-hint giftcards-redeem-feedback"
        />
        <button type="button" class="giftcards-redeem__apply" data-giftcards-apply>
            <?php esc_html_e('Apply', 'giftvane'); ?>
        </button>
        <input type="hidden" name="<?php echo esc_attr($giftcards_field_name); ?>_nonce" value="<?php echo esc_attr($giftcards_nonce_field); ?>" />
    </p>
    <?php require __DIR__ . '/checkout-redeem-feedback.php'; ?>
</div>
