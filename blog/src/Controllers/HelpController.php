<?php

namespace App\Controllers;

/**
 * Контроллер страницы помощи проекту.
 * Отображает реквизиты и историю донатов.
 */
class HelpController extends BaseController
{
    /**
     * Страница помощи проекту.
     */
    public function show(): void
    {
        $donationModel = new \App\Models\Donation();
        
        // Получение статистики донатов
        $totalDonated = $donationModel->getTotalAmount();
        $recentDonations = $donationModel->getRecentPublic(10);

        // Реквизиты из .env или конфига
        $requisites = [
            'card_number' => $_ENV['DONATION_CARD'] ?? '0000 0000 0000 0000',
            'wallet_yandex' => $_ENV['DONATION_YANDEX'] ?? '',
            'wallet_webmoney' => $_ENV['DONATION_WEBMONEY'] ?? '',
            'crypto_btc' => $_ENV['DONATION_BTC'] ?? '',
            'crypto_eth' => $_ENV['DONATION_ETH'] ?? '',
            'crypto_usdt' => $_ENV['DONATION_USDT'] ?? ''
        ];

        $goals = [
            'Оплата хостинга' => ['current' => $totalDonated, 'target' => 5000],
            'Развитие проекта' => ['current' => $totalDonated, 'target' => 50000],
            'Приобретение лицензий' => ['current' => $totalDonated, 'target' => 100000]
        ];

        $csrfToken = \App\Core\Csrf::generate();

        $this->render('help/show', [
            'requisites' => $requisites,
            'goals' => $goals,
            'totalDonated' => $totalDonated,
            'recentDonations' => $recentDonations,
            'csrf_token' => $csrfToken
        ]);
    }
}
