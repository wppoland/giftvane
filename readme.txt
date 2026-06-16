=== Gift Cards – Store Credit for WooCommerce ===
Contributors: wppoland
Tags: woocommerce, gift card, store credit, gift voucher, coupon code
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell WooCommerce gift cards, gift vouchers and store credit codes that customers redeem at checkout.

== Description ==

Sell a gift card or gift voucher as an ordinary WooCommerce product. Tick the "Gift card" box on any product and set its price to the card value. When the order is marked complete, the plugin generates a unique store credit code worth that price, records its balance in its own table, and emails the code to the buyer's order email address.

To spend a card, the customer enters the code in a field on the checkout. The balance is applied as a discount on that order. If the order costs less than the card is worth, the leftover stays on the code for a later purchase, so one card can cover several orders until it runs out.

The buyer also sees the code(s) their order issued on the order-confirmation page and in their WooCommerce order emails, so they have the code in hand without hunting through their inbox.

The code is built and tracked on GitHub. Source and bug reports: https://github.com/wppoland/giftcards

= What it does =

* Turns any product into a gift card with one checkbox on the product editor's General tab; the price is the card value.
* Generates a unique code on order completion and emails it to the buyer's order email address.
* Adds a redeem-code field to the checkout that applies the card balance as a discount.
* Keeps the unused balance on the code after a partial spend, so it works across multiple orders.
* Lets you set the code prefix, the checkout discount label and the recipient email subject and body.
* Optionally lists the issued codes on the buyer's order page and in their order emails.
* Works with WooCommerce HPOS (custom order tables) and the Cart and Checkout blocks.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/giftcards`, or install it from Plugins → Add New.
2. Activate it. WooCommerce must be active.
3. Edit a product, tick **Gift card** on the General tab, and set its price to the value of the card.
4. Set the code prefix and the recipient email under **WooCommerce → Gift Cards**.

== Frequently Asked Questions ==

= Does it need WooCommerce? =

Yes. WooCommerce 8.0 or later must be installed and active.

= How do I set the value of a gift card? =

The value is the price of the gift-card product. Buying two of a $50 card issues two $50 codes.

= Who receives the code? =

The code is emailed to the order's billing email address, and the buyer can also see it on the order-confirmation page and in their order emails. There is no separate "send to a friend" field in this version.

= How does redeeming a code work? =

The customer types the code into the field at checkout. The balance comes off that order as a discount, and anything left over stays on the code for next time.

= Can a gift card be used more than once? =

Yes. If checkout uses only part of the balance, the remaining store credit stays on the same code for a later order.

= Does each quantity create a separate code? =

Yes. Buying two units of a gift card product issues two separate store credit codes with the product price as each value.

= Can I customise the email? =

Yes. Set the email subject and body under WooCommerce → Gift Cards, with tokens for the code and amount.

= Does it work with WooCommerce checkout blocks? =

Yes. Gift Cards declares compatibility with WooCommerce HPOS and Cart/Checkout Blocks.

== Screenshots ==

1. The Gift Cards settings page under WooCommerce.

== Changelog ==

= 0.2.0 =
* The recipient email subject and body set under **WooCommerce → Gift Cards** are now used for the email that's sent. Earlier these stored values were ignored and a built-in default was always used.
* Added a setting for the checkout discount label shown when a code is applied; it accepts a {code} token.
* Added a setting to list the issued codes on the buyer's order-confirmation page and order emails. It is on by default.
* The default email and label text is now translatable.
* Reworked the settings page: inline help, click-to-insert email tokens and a live preview of the email.
* Reworked the checkout redeem field and added a copy button to the issued-codes list.
* Storefront styles now follow the theme and respect dark mode and reduced-motion settings, with no layout shift at checkout. The markup is keyboard-accessible with ARIA labels and focus styles, and all CSS/JS ships as separate files.

= 0.1.0 =
* Initial release.
