<?php
/**
 * PRO upsell content, generated from the plogins.com registry by
 * scripts/gen-pro-upsell.mjs. The admin upsell renders this; curate the
 * feature list to fit this plugin's settings screen (do not invent features).
 *
 * @package plogins-giftcards-pro
 */

defined('ABSPATH') || exit;

return [
    'name'       => 'Gift Cards Pro',
    'url'        => 'https://plogins.com/plogins-giftcards-pro/pricing/',
    'sellable'   => true,
    'price_from' => 29,
    'currency'   => 'EUR',
    'price_pln'  => 129,
    'lead'       => [
        'en' => 'The features below ship in the current PRO release.',
        'pl' => 'Poniższe funkcje są dostępne w bieżącym wydaniu PRO.',
    ],
    'features'   => [
        [
            'en' => ['title' => 'Custom amount', 'desc' => 'Let the customer enter a gift-card amount within per-product or store-wide min/max bounds.'],
            'pl' => ['title' => 'Własna kwota', 'desc' => 'Klient wpisuje kwotę karty w granicach min/max ustawionych dla produktu lub sklepu.'],
        ],
        [
            'en' => ['title' => 'Scheduled delivery', 'desc' => 'Choose the date the code is sent, PRO issues the card and emails it on that day via daily cron.'],
            'pl' => ['title' => 'Zaplanowana dostawa', 'desc' => 'Wybierz datę wysłania kodu, PRO wystawia kartę i wysyła e-mail dopiero w wybranym dniu.'],
        ],
        [
            'en' => ['title' => 'Balance shortcode', 'desc' => 'The [giftcards_balance] shortcode lets recipients check remaining balance after entering their code.'],
            'pl' => ['title' => 'Shortcode salda', 'desc' => 'Shortcode [giftcards_balance], obdarowany sprawdza pozostałe saldo po wpisaniu kodu.'],
        ],
        [
            'en' => ['title' => 'Redemption history', 'desc' => 'WooCommerce → Gift card lookup, search a code and view partial-redemption history.'],
            'pl' => ['title' => 'Historia realizacji', 'desc' => 'WooCommerce → Gift card lookup, wyszukaj kod i zobacz historię częściowych realizacji.'],
        ],
        [
            'en' => ['title' => 'Custom card designs', 'desc' => 'Branded HTML emails with three templates (Classic, Celebration, Minimal), optional accent colour and header banner.'],
            'pl' => ['title' => 'Własne projekty kart', 'desc' => 'Branded HTML e-maile z trzema szablonami (Classic, Celebration, Minimal), opcjonalnym kolorem i banerem.'],
        ],
        [
            'en' => ['title' => 'Bulk generation', 'desc' => 'WooCommerce → Bulk gift cards, issue up to 500 codes per batch and download CSV.'],
            'pl' => ['title' => 'Generowanie masowe', 'desc' => 'WooCommerce → Bulk gift cards, wystaw do 500 kodów na partię i pobierz CSV.'],
        ],
        [
            'en' => ['title' => 'PDF gift cards', 'desc' => 'Printable PDF attachment on recipient emails with amount, code and shop link.'],
            'pl' => ['title' => 'Karty PDF', 'desc' => 'Drukowalny załącznik PDF w e-mailu do odbiorcy, kwota, kod i link do sklepu.'],
        ],
        [
            'en' => ['title' => 'Multi-currency', 'desc' => 'Denomination currency per card; optional checkout guard when cart currency does not match.'],
            'pl' => ['title' => 'Multi-currency', 'desc' => 'Waluta denominacji na każdej karcie; opcjonalna blokada realizacji przy innej walucie kasy.'],
        ],
    ],
];
