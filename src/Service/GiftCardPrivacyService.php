<?php

declare(strict_types=1);

namespace GiftCards\Service;

use GiftCards\Contract\HasHooks;
use GiftCards\Repository\GiftCardTableRepository;

defined('ABSPATH') || exit;

/**
 * Personal data exporter and eraser for Gift Cards.
 */
final class GiftCardPrivacyService implements HasHooks
{
    private const PAGE_SIZE = 100;

    public function __construct(
        private readonly GiftCardTableRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerErasers']);
    }

    /**
     * @param array<string, array<string, mixed>> $exporters
     * @return array<string, array<string, mixed>>
     */
    public function registerExporters(array $exporters): array
    {
        $exporters['giftcards-recipient'] = [
            'exporter_friendly_name' => __('Gift Cards', 'plogins-giftcards'),
            'callback'               => [$this, 'exportGiftCards'],
        ];

        return $exporters;
    }

    /**
     * @param array<string, array<string, mixed>> $erasers
     * @return array<string, array<string, mixed>>
     */
    public function registerErasers(array $erasers): array
    {
        $erasers['giftcards-recipient'] = [
            'eraser_friendly_name' => __('Gift Cards', 'plogins-giftcards'),
            'callback'             => [$this, 'eraseGiftCards'],
        ];

        return $erasers;
    }

    /**
     * @return array{data: list<array<string, mixed>>, done: bool}
     */
    public function exportGiftCards(string $email, int $page = 1): array
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $items = [];
        $rows  = $this->repository->findByRecipientEmail($email, self::PAGE_SIZE, $offset);

        foreach ($rows as $r) {
            $items[] = [
                'group_id'    => 'giftcards-recipient',
                'group_label' => __('Gift Cards', 'plogins-giftcards'),
                'item_id'     => 'giftcard-' . $r['id'],
                'data'        => [
                    ['name' => __('Gift Card Code', 'plogins-giftcards'), 'value' => $r['code']],
                    ['name' => __('Remaining Balance', 'plogins-giftcards'), 'value' => (string) $r['balance']],
                    ['name' => __('Order ID', 'plogins-giftcards'), 'value' => (string) $r['order_id']],
                    ['name' => __('Created At', 'plogins-giftcards'), 'value' => $r['created_at']],
                ],
            ];
        }

        return [
            'data' => $items,
            'done' => count($rows) < self::PAGE_SIZE,
        ];
    }

    /**
     * @return array{items_removed: int, items_retained: int, messages: list<string>, done: bool}
     */
    public function eraseGiftCards(string $email, int $page = 1): array
    {
        $anonymized = $this->repository->anonymizeByRecipientEmail($email);

        return [
            'items_removed'  => $anonymized,
            'items_retained' => 0,
            'messages'       => [],
            'done'           => true,
        ];
    }
}
