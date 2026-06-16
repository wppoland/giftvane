# Gift Cards – Store Credit for WooCommerce

A WooCommerce plugin that lets a shop sell gift cards as ordinary products. When an order
completes, each gift-card line gets a unique code worth its price; the code is emailed to the
buyer's order email and can be redeemed at checkout for store credit. Partial balances stay on the
code, so one card can pay for several orders.

## Features

- Mark any product as a gift card with a single checkbox on the product editor's General tab.
- A unique code is generated on order completion and emailed to the buyer.
- A redeem-code field at checkout applies the card balance as a discount.
- Unused balance is kept on the code for later orders.
- Configurable code prefix, checkout discount label and recipient email subject/body.
- Issued codes can be shown on the order page and in order emails.
- Compatible with WooCommerce HPOS and the Cart/Checkout blocks.

## Requirements

- WordPress 6.5+
- WooCommerce 8.0+
- PHP 8.1+

## Installation

1. Copy this folder to `wp-content/plugins/giftcards`.
2. From the plugin directory run `composer install --no-dev` to pull in runtime dependencies.
3. Activate **Gift Cards – Store Credit for WooCommerce** in WordPress. WooCommerce must be active.
4. Edit a product, tick **Gift card** on the General tab, and set its price to the card value.
5. Adjust the code prefix and recipient email under **WooCommerce → Gift Cards**.

## Development

```bash
composer install        # install dev dependencies
composer cs             # coding standards (PHPCS / WPCS)
composer analyse        # static analysis (PHPStan)
```

Issues and pull requests are welcome at https://github.com/wppoland/giftcards.

## License

GPL-2.0-or-later.
