<?php
/**
 * Feedback line under the checkout redeem-code field: the balance left on the
 * card after this order. Rendered with the field, and again as a checkout
 * fragment on every order-review refresh ({@see \GiftCards\Service\GiftCardService::feedbackFragment()}),
 * because WooCommerce does not re-render the field itself over AJAX.
 *
 * @package GiftCards
 *
 * @var float|null $giftcards_remaining Balance left after this order, null when no valid card is in the field.
 */

defined('ABSPATH') || exit;
?>
<p class="giftcards-redeem__feedback<?php echo null !== $giftcards_remaining ? ' giftcards-redeem__feedback--applied' : ''; ?>"
    id="giftcards-redeem-feedback" role="status" aria-live="polite">
    <span class="giftcards-redeem__seal" aria-hidden="true"></span>
    <span class="giftcards-redeem__feedback-text">
    <?php
    if (null !== $giftcards_remaining) {
        /* translators: %s: the gift card balance left after this order, formatted as a price. */
        printf(esc_html__('Gift card applied, %s left after this order.', 'giftvane'), wp_kses_post(wc_price($giftcards_remaining)));
    }
    ?>
    </span>
</p>
