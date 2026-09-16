<?php

declare(strict_types=1);

/**
 * Copyright since 2024 Beassisted
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 *
 * @author    Beassisted
 * @copyright Copyright (c) 2024-present Beassisted
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace LinkBay\Cron;

use LinkBay\Config\Config;
use LinkBay\Repository\LinkBayCategoryMapRepository;
use LinkBay\Repository\LinkBayLogRepository;
use LinkBay\Repository\LinkBayPriceRuleRepository;
use LinkBay\Repository\LinkBayProductRepository;
use LinkBay\Service\EbayApiClient;
use LinkBay\Service\EbayProductMapper;
use LinkBay\Service\ImportManager;
use LinkBay\Service\PriceRuleEngine;
use LinkBay\Service\StockSynchronizer;
use LinkBay\Service\TokenStorage;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Executes the cron synchronization workflow.
 */
final class CronProcessor
{
    public const TYPE_STOCK = 'stock';
    public const TYPE_IMPORT = 'import';
    public const TYPE_PRICE = 'price';
    public const TYPE_ALL = 'all';

    /**
     * Runs the requested cron task and returns a serializable result.
     *
     * @return array<string, mixed>
     */
    public function run(string $type): array
    {
        $tokenStorage = new TokenStorage();
        $apiClient = new EbayApiClient($tokenStorage);

        if (!$apiClient->isAuthenticated()) {
            return ['success' => false, 'error' => 'LinkBay is not connected to eBay.'];
        }

        $mapper = new EbayProductMapper();
        $productLinkRepository = new LinkBayProductRepository();
        $logRepository = new LinkBayLogRepository();
        $categoryMapRepository = new LinkBayCategoryMapRepository();
        $importManager = new ImportManager($apiClient, $mapper, $productLinkRepository, $logRepository, $categoryMapRepository);
        $stockSynchronizer = new StockSynchronizer($apiClient, $mapper, $productLinkRepository, $logRepository);

        $result = ['success' => true, 'type' => $type];

        if ($type === self::TYPE_IMPORT || $type === self::TYPE_ALL) {
            if ((bool) \Configuration::get(Config::AUTO_IMPORT)) {
                try {
                    $result['import'] = $importManager->autoImport();
                } catch (\Throwable $e) {
                    $result['import'] = ['error' => $e->getMessage()];
                }
            } else {
                $result['import'] = ['skipped' => 'auto import disabled'];
            }
        }

        if ($type === self::TYPE_STOCK || $type === self::TYPE_ALL) {
            try {
                $result['ebay_to_prestashop'] = $stockSynchronizer->syncEbayToPrestashop();
            } catch (\Throwable $e) {
                $result['ebay_to_prestashop'] = ['error' => $e->getMessage()];
            }

            try {
                $result['prestashop_to_ebay'] = $stockSynchronizer->syncPrestashopToEbay();
            } catch (\Throwable $e) {
                $result['prestashop_to_ebay'] = ['error' => $e->getMessage()];
            }
        }

        if ($type === self::TYPE_PRICE || $type === self::TYPE_ALL) {
            try {
                $priceRuleEngine = new PriceRuleEngine(
                    $apiClient,
                    $productLinkRepository,
                    new LinkBayPriceRuleRepository(),
                    $logRepository
                );
                $result['price_rules'] = $priceRuleEngine->apply();
            } catch (\Throwable $e) {
                $result['price_rules'] = ['error' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Validates the secret token used to protect the cron endpoint.
     */
    public static function validateToken(string $providedToken): bool
    {
        $expected = (string) \Configuration::get(Config::CRON_TOKEN);

        return $expected !== '' && hash_equals($expected, $providedToken);
    }
}
