=== Plogins Gift Cards - Store Credit for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, gift card, store credit, gift voucher, coupon code
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell WooCommerce gift cards, gift vouchers and store credit codes that customers redeem at checkout.

== Description ==

Sell a gift card or gift voucher as an ordinary WooCommerce product. Tick the "Gift card" box on any product and set its price to the card value. When the order is marked complete, the plugin generates a unique store credit code worth that price, records its balance in its own table, and emails the code to the buyer's order email address.

To spend a card, the customer enters the code in a field on the checkout. The balance is applied as a discount on that order. If the order costs less than the card is worth, the leftover stays on the code for a later purchase, so one card can cover several orders until it runs out.

The buyer also sees the code(s) their order issued on the order-confirmation page and in their WooCommerce order emails, so they have the code in hand without hunting through their inbox.

The code is built and tracked on GitHub. Source and bug reports: [github.com/wppoland/plogins-giftcards](https://github.com/wppoland/plogins-giftcards)

= Documentation and links =

* **Documentation**: [plogins.com/plogins-giftcards/docs/](https://plogins.com/plogins-giftcards/docs/)
* **Plugin page**: [plogins.com/plogins-giftcards/](https://plogins.com/plogins-giftcards/)
* **Source code**: [github.com/wppoland/plogins-giftcards](https://github.com/wppoland/plogins-giftcards)
* **Bug reports and feature requests**: [github.com/wppoland/plogins-giftcards/issues](https://github.com/wppoland/plogins-giftcards/issues)


= What it does =

* Turns any product into a gift card with one checkbox on the product editor's General tab; the price is the card value.
* Generates a unique code on order completion and emails it to the buyer's order email address.
* Adds a redeem-code field to the checkout that applies the card balance as a discount.
* Keeps the unused balance on the code after a partial spend, so it works across multiple orders.
* Lets you set the code prefix, the checkout discount label and the recipient email subject and body.
* Optionally lists the issued codes on the buyer's order page and in their order emails.
* Works with WooCommerce HPOS (custom order tables).
* Lets an AI assistant in your admin look up a code's balance and list the codes an order issued, through the WordPress Abilities API on WordPress 6.9 and later. Reading only; it cannot change a balance or issue a code.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/plogins-giftcards`, or install it from Plugins > Add New.
2. Activate it. WooCommerce must be active.
3. Edit a product, tick **Gift card** on the General tab, and set its price to the value of the card.
4. Set the code prefix and the recipient email under **WooCommerce > Gift Cards**.

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

Yes. Set the email subject and body under WooCommerce > Gift Cards, with tokens for the code and amount.

= Does it work with WooCommerce checkout blocks? =

HPOS, yes. Redeeming a gift card is currently a classic-checkout feature; on the
block checkout the redemption field does not appear.


= Does this plugin work on WordPress Multisite? =

Yes. This plugin is compatible with WordPress Multisite. Network activate it or activate it on individual sites; each site keeps its own settings and data.

== Screenshots ==

1. Redeeming a gift card at checkout, where a shopper applies a code to their order.
2. The Gift Cards settings page under WooCommerce.

== External Services ==

This plugin does not connect to, send data to, or rely on any external service, API or CDN. Everything runs on your own site. Gift-card codes and balances are stored in a single custom database table (`{prefix}giftcards`), the gift-card flag and any recipient address are kept in WooCommerce product and order-item meta (`_giftcards_is_gift_card`, `_giftcards_recipient_email`), and settings live in the `giftcards_settings` and `giftcards_db_version` options. The email carrying a code is delivered through your site's own WooCommerce/WordPress mailer to the order's billing address; no message or customer data leaves your server.

== Translations ==

Plogins Gift Cards is fully translatable and ships the `plogins-giftcards.pot` template. Translations are delivered by WordPress.org language packs from translate.wordpress.org, which is where Polish, German and Spanish are being contributed; the package itself carries no compiled translation files.

== Changelog ==

= 1.1.6 =
* Fixed: the PRO upgrade promo kept selling to people who had already bought the paid edition. Only the banner could be dismissed, so the sidebar promo and the locked feature cards followed a paying customer around for good. The promo now checks whether the paid edition is active and steps aside when it is.
* Fixed: arrow glyphs in the admin menu paths, and in the strings handed to translators. An arrow inside a translatable string makes the glyph every translator's problem and changes the layout in any locale that drops it.

= 1.1.5 =
* Fixed: deleting the plugin left the per-user "dismiss" flag from the PRO notice in the database. Uninstall now removes it for every user, not just the one who dismissed it.

= 1.1.4 =
* The translation template was regenerated. It still named an older version of the plugin and pointed at source lines that had since moved, which is what translation tools read to show a string in context.

= 1.1.3 =
* Renamed to Plogins Gift Cards - Store Credit for WooCommerce so the name leads with the brand rather than a generic word, which is what the WordPress.org plugin review team asks for. The plugin slug is unchanged.

= 1.1.2 =
* Tested against WordPress 7.1. Verified by activating this build on a clean 7.1 install with WooCommerce 11.1, not by editing the header.

= 1.1.1 =
* Fixed the PRO promo on the settings screen quoting a price in PLN. PRO is priced and charged in EUR, so an admin on a Polish site was shown a zloty amount and then billed in euro, and the zloty figure was a fixed conversion that drifted from the real charge as the rate moved. The promo now shows the euro price that is actually taken.

= 1.1.0 =
* An AI assistant working in your wp-admin can now read your gift cards for you, through the WordPress Abilities API (WordPress 6.9 and later). Ask it what is left on a code, which codes an order issued, whether a product is set up as a gift card, or how gift cards are configured on the shop.
* Reading only. Nothing an assistant can call changes a balance, issues a code or voids one, and no recipient address is ever returned. Only shop managers can use these, and on WordPress 6.8 and earlier nothing changes.

= 1.0.4 =
* Translations: completed Polish, German and Spanish for the PRO upgrade panel.

= 1.0.3 =
* Fixed low-contrast admin headings under an OS dark-mode preference.

= 1.0.2 =
* Added bundled Polish, German and Spanish translations for the plugin interface.

= 1.0.1 =
* First stable release.

= 0.2.1 =
* Renamed to Plogins Gift Cards for WooCommerce for a more distinctive plugin name.

= 0.2.0 =
* The recipient email subject and body set under **WooCommerce > Gift Cards** are now used for the email that's sent. Earlier these stored values were ignored and a built-in default was always used.
* Added a setting for the checkout discount label shown when a code is applied; it accepts a {code} token.
* Added a setting to list the issued codes on the buyer's order-confirmation page and order emails. It is on by default.
* The default email and label text is now translatable.
* Reworked the settings page: inline help, click-to-insert email tokens and a live preview of the email.
* Reworked the checkout redeem field and added a copy button to the issued-codes list.
* Storefront styles now follow the theme and respect dark mode and reduced-motion settings, with no layout shift at checkout. The markup is keyboard-accessible with ARIA labels and focus styles, and all CSS/JS ships as separate files.

= 0.1.0 =
* Initial release.
