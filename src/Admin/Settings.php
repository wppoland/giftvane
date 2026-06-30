<?php

declare(strict_types=1);

namespace GiftCards\Admin;

use GiftCards\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Admin settings page registered as a WooCommerce submenu ("Gift Cards").
 *
 * Stores settings in the `giftcards_settings` option (array): the master toggle,
 * an optional code prefix, and the recipient email subject/body templates passed
 * through to the storefront-kit engine. All output is escaped; all input is
 * sanitised on save. The save capability is aligned to `manage_woocommerce` so
 * shop managers can save.
 */
final class Settings implements HasHooks
{
    private const OPTION = 'giftcards_settings';

    private const PAGE = 'giftcards-settings';

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Enqueue the settings-page styles and progressive-enhancement script as
     * real files (wp.org Plugin-Check clean — no inline blobs). Only on our
     * page; script is deferred and footer-loaded.
     */
    public function enqueueAssets(string $hook): void
    {
        if (! str_contains($hook, self::PAGE)) {
            return;
        }

        wp_enqueue_style(
            'giftcards-admin',
            GIFTCARDS_URL . 'assets/css/admin.css',
            [],
            \GiftCards\VERSION,
        );

        wp_enqueue_script(
            'giftcards-admin',
            GIFTCARDS_URL . 'assets/js/admin.js',
            [],
            \GiftCards\VERSION,
            ['in_footer' => true, 'strategy' => 'defer'],
        );
    }

    /**
     * Render an accessible inline-help affordance: a "?" button wired to a
     * tooltip via aria-describedby. JS upgrades it to a native popover; with no
     * JS the help text stays readable as a visible fallback span.
     */
    private function help(string $id, string $text): void
    {
        printf(
            '<button type="button" class="giftcards-help" aria-describedby="%1$s" aria-label="%2$s">?</button>'
                . '<span id="%1$s" role="tooltip" popover class="giftcards-tooltip">%3$s</span>'
                . '<span class="giftcards-help-fallback">%3$s</span>',
            esc_attr($id),
            esc_attr__('More information', 'plogins-giftcards'),
            esc_html($text),
        );
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Gift Cards', 'plogins-giftcards'),
            __('Gift Cards', 'plogins-giftcards'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::PAGE,
            self::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ],
        );

        // The menu uses manage_woocommerce; align the options.php save
        // capability so shop managers (not just admins) can save.
        add_filter(
            'option_page_capability_' . self::PAGE,
            static fn (): string => 'manage_woocommerce',
        );
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings   = $this->settings();
        $option     = self::OPTION;
        $enabled    = (bool) ($settings['enabled'] ?? false);
        $sampleCode = 'GIFT-AB12CD34';
        $sampleAmt  = wp_strip_all_tags(wc_price(50));
        ?>
        <div class="wrap giftcards-admin"
            data-sample-code="<?php echo esc_attr($sampleCode); ?>"
            data-sample-amount="<?php echo esc_attr($sampleAmt); ?>">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
                <?php if ($enabled) : ?>
                    <span class="giftcards-admin__status giftcards-admin__status--on">
                        <?php esc_html_e('Active', 'plogins-giftcards'); ?>
                    </span>
                <?php else : ?>
                    <span class="giftcards-admin__status giftcards-admin__status--off">
                        <?php esc_html_e('Disabled', 'plogins-giftcards'); ?>
                    </span>
                <?php endif; ?>
            </h1>

            <div class="giftcards-admin__intro">
                <span class="giftcards-admin__intro-icon" aria-hidden="true">&#127873;</span>
                <div>
                    <h2><?php esc_html_e('Sell gift cards in three steps', 'plogins-giftcards'); ?></h2>
                    <p>
                        <?php esc_html_e('1. Flag a product as a gift card (product editor → General tab → Gift card). 2. A buyer purchases it. 3. A unique code is emailed to the recipient and can be redeemed at checkout for a discount. Tune the wording below.', 'plogins-giftcards'); ?>
                    </p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <div class="giftcards-admin__card">
                    <h2><?php esc_html_e('General', 'plogins-giftcards'); ?></h2>
                    <p class="giftcards-admin__card-hint">
                        <?php esc_html_e('Core behaviour: whether gift cards are sold and how codes look.', 'plogins-giftcards'); ?>
                    </p>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <span class="giftcards-admin__label">
                                        <?php esc_html_e('Enable gift cards', 'plogins-giftcards'); ?>
                                        <?php $this->help('gc-help-enabled', __('Master switch. When off, no codes are issued, the checkout redeem field is hidden, and existing balances cannot be spent. Turn this off to pause the feature without losing data.', 'plogins-giftcards')); ?>
                                    </span>
                                </th>
                                <td>
                                    <label for="giftcards_enabled">
                                        <input
                                            type="checkbox"
                                            id="giftcards_enabled"
                                            name="<?php echo esc_attr($option); ?>[enabled]"
                                            value="1"
                                            <?php checked($enabled, true); ?>
                                        />
                                        <?php esc_html_e('Generate and email a code when a gift-card product is purchased, and accept codes at checkout.', 'plogins-giftcards'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="giftcards-admin__label">
                                        <label for="giftcards_code_prefix"><?php esc_html_e('Code prefix', 'plogins-giftcards'); ?></label>
                                        <?php $this->help('gc-help-prefix', __('A short tag added to the front of every code so customers and support can recognise it at a glance. Example: "GIFT-" produces GIFT-AB12CD34. Leave blank for codes with no prefix.', 'plogins-giftcards')); ?>
                                    </span>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="giftcards_code_prefix"
                                        name="<?php echo esc_attr($option); ?>[code_prefix]"
                                        value="<?php echo esc_attr((string) ($settings['code_prefix'] ?? '')); ?>"
                                        class="regular-text"
                                        placeholder="GIFT-"
                                        maxlength="12"
                                    />
                                    <p class="description"><?php esc_html_e('Optional. Prepended to every generated code, e.g. "GIFT-". Up to 12 characters.', 'plogins-giftcards'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="giftcards-admin__label">
                                        <label for="giftcards_fee_label"><?php esc_html_e('Checkout discount label', 'plogins-giftcards'); ?></label>
                                        <?php $this->help('gc-help-fee', __('The text shown on the discount line at checkout when a code is applied (e.g. "Gift card (GIFT-AB12CD34)"). Use the {code} token to include the applied code.', 'plogins-giftcards')); ?>
                                    </span>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="giftcards_fee_label"
                                        name="<?php echo esc_attr($option); ?>[fee_label]"
                                        value="<?php echo esc_attr((string) ($settings['fee_label'] ?? '')); ?>"
                                        class="regular-text"
                                    />
                                    <p class="description"><?php esc_html_e('Shown on the discount line at checkout. Use {code} for the applied code.', 'plogins-giftcards'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <span class="giftcards-admin__label">
                                        <?php esc_html_e('Show codes on order', 'plogins-giftcards'); ?>
                                        <?php $this->help('gc-help-show', __('When a customer buys a gift card for themselves, this lists the issued code(s) right on the order-confirmation page and in their order emails, so they get the code instantly without waiting for the separate recipient email.', 'plogins-giftcards')); ?>
                                    </span>
                                </th>
                                <td>
                                    <label for="giftcards_show_codes_on_order">
                                        <input
                                            type="checkbox"
                                            id="giftcards_show_codes_on_order"
                                            name="<?php echo esc_attr($option); ?>[show_codes_on_order]"
                                            value="1"
                                            <?php checked((bool) ($settings['show_codes_on_order'] ?? true), true); ?>
                                        />
                                        <?php esc_html_e('List the issued gift-card codes on the buyer\'s order-confirmation page and in their order emails.', 'plogins-giftcards'); ?>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="giftcards-admin__card">
                    <h2><?php esc_html_e('Recipient email', 'plogins-giftcards'); ?></h2>
                    <p class="giftcards-admin__card-hint">
                        <?php esc_html_e('The email sent to whoever receives the gift card. Click a token to insert it where your cursor is.', 'plogins-giftcards'); ?>
                    </p>

                    <div class="giftcards-tokens" role="group" aria-label="<?php esc_attr_e('Insert a template token', 'plogins-giftcards'); ?>">
                        <button type="button" class="giftcards-token" data-token="{code}"
                            data-inserted="<?php esc_attr_e('inserted', 'plogins-giftcards'); ?>"
                            data-copied="<?php esc_attr_e('copied', 'plogins-giftcards'); ?>">{code}</button>
                        <button type="button" class="giftcards-token" data-token="{amount}"
                            data-inserted="<?php esc_attr_e('inserted', 'plogins-giftcards'); ?>"
                            data-copied="<?php esc_attr_e('copied', 'plogins-giftcards'); ?>">{amount}</button>
                    </div>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="giftcards_email_subject"><?php esc_html_e('Subject', 'plogins-giftcards'); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="giftcards_email_subject"
                                        name="<?php echo esc_attr($option); ?>[email_subject]"
                                        value="<?php echo esc_attr((string) ($settings['email_subject'] ?? '')); ?>"
                                        class="large-text"
                                    />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="giftcards_email_body"><?php esc_html_e('Body', 'plogins-giftcards'); ?></label>
                                </th>
                                <td>
                                    <textarea
                                        id="giftcards_email_body"
                                        name="<?php echo esc_attr($option); ?>[email_body]"
                                        rows="5"
                                        class="large-text"
                                    ><?php echo esc_textarea((string) ($settings['email_body'] ?? '')); ?></textarea>
                                    <p class="description"><?php esc_html_e('{code} becomes the gift-card code; {amount} becomes the formatted value.', 'plogins-giftcards'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="giftcards-preview" aria-hidden="true">
                        <span class="giftcards-preview__label"><?php esc_html_e('Live preview', 'plogins-giftcards'); ?></span>
                        <div class="giftcards-preview__subject"></div>
                        <div class="giftcards-preview__body"></div>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitises the submitted settings before save, preserving defaults for any
     * field not on the form.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function sanitize(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = [];
        }

        $defaults = $this->settings();

        // Normalise the prefix to the same character set the engine keeps when
        // generating codes (A-Z, 0-9, hyphen), uppercased and capped at 12, so
        // what the merchant types is exactly what appears on every code — no
        // silent stripping of spaces or punctuation later.
        $rawPrefix = isset($raw['code_prefix']) ? sanitize_text_field((string) $raw['code_prefix']) : '';
        $prefix    = strtoupper((string) preg_replace('/[^A-Za-z0-9\-]/', '', $rawPrefix));
        $prefix    = substr($prefix, 0, 12);
        $feeLabel = isset($raw['fee_label']) ? sanitize_text_field((string) $raw['fee_label']) : '';
        $subject  = isset($raw['email_subject']) ? sanitize_text_field((string) $raw['email_subject']) : '';
        $body     = isset($raw['email_body']) ? sanitize_textarea_field((string) $raw['email_body']) : '';

        return array_merge($defaults, [
            'enabled'             => ! empty($raw['enabled']),
            'code_prefix'         => $prefix,
            'fee_label'           => $feeLabel !== '' ? $feeLabel : (string) ($defaults['fee_label'] ?? ''),
            'show_codes_on_order' => ! empty($raw['show_codes_on_order']),
            'email_subject'       => $subject !== '' ? $subject : (string) ($defaults['email_subject'] ?? ''),
            'email_body'          => $body !== '' ? $body : (string) ($defaults['email_body'] ?? ''),
        ]);
    }

    /**
     * Stored settings merged over packaged defaults.
     *
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $stored = get_option(self::OPTION, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require GIFTCARDS_DIR . 'config/defaults.php';

        return array_merge($defaults, $stored);
    }
}
