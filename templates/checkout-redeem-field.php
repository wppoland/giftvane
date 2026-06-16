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
 * @var array<string, mixed> $giftcards_settings Resolved settings.
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template-scope variables supplied by the host renderField closure.
 */

defined('ABSPATH') || exit;

$giftcards_is_applied = '' !== trim((string) $giftcards_applied_code);
?>
<div class="giftcards-redeem">
    <div class="giftcards-redeem__head">
        <span aria-hidden="true">&#127873;</span>
        <h3 class="giftcards-redeem__title"><?php esc_html_e('Have a gift card?', 'giftcards'); ?></h3>
    </div>
    <p class="giftcards-redeem__hint" id="giftcards-redeem-hint">
        <?php esc_html_e('Enter your code to apply its balance to this order. Any unused balance stays on the card for next time.', 'giftcards'); ?>
    </p>
    <p class="form-row giftcards-redeem__row">
        <label class="giftcards-redeem__label" for="<?php echo esc_attr($giftcards_field_name); ?>">
            <?php esc_html_e('Gift card code', 'giftcards'); ?>
        </label>
        <input
            type="text"
            id="<?php echo esc_attr($giftcards_field_name); ?>"
            name="<?php echo esc_attr($giftcards_field_name); ?>"
            value="<?php echo esc_attr($giftcards_applied_code); ?>"
            class="input-text giftcards-redeem__input"
            placeholder="<?php esc_attr_e('e.g. GIFT-AB12CD34', 'giftcards'); ?>"
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            inputmode="text"
            aria-describedby="giftcards-redeem-hint giftcards-redeem-feedback"
        />
        <button type="button" class="giftcards-redeem__apply" data-giftcards-apply>
            <?php esc_html_e('Apply', 'giftcards'); ?>
        </button>
        <input type="hidden" name="<?php echo esc_attr($giftcards_field_name); ?>_nonce" value="<?php echo esc_attr($giftcards_nonce_field); ?>" />
    </p>
    <p class="giftcards-redeem__feedback<?php echo $giftcards_is_applied ? ' giftcards-redeem__feedback--applied' : ''; ?>"
        id="giftcards-redeem-feedback" role="status" aria-live="polite">
        <span class="giftcards-redeem__seal" aria-hidden="true"></span>
        <span class="giftcards-redeem__feedback-text">
        <?php
        if ($giftcards_is_applied) {
            echo esc_html__('Gift card applied — the balance is shown in your order total.', 'giftcards');
        }
        ?>
        </span>
    </p>
</div>
