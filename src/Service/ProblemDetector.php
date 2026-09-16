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

namespace LinkBay\Service;

use LinkBay\Config\Config;
use LinkBay\Entity\LinkBayProduct;
use LinkBay\Repository\LinkBayProductRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Detects the problems a merchant must fix, for the visual dashboard.
 *
 * Each problem is a checklist item with a stable id, a title, a description,
 * the number of affected items and the id of the fix action that can be
 * triggered from the dashboard.
 */
final class ProblemDetector
{
    private EbayApiClient $apiClient;
    private LinkBayProductRepository $productLinkRepository;

    public function __construct(EbayApiClient $apiClient, LinkBayProductRepository $productLinkRepository)
    {
        $this->apiClient = $apiClient;
        $this->productLinkRepository = $productLinkRepository;
    }

    /**
     * @return array<int, array{id: string, title: string, description: string, count: int, fix: string}>
     */
    public function detect(): array
    {
        $problems = [];

        if (!$this->apiClient->isAuthenticated()) {
            $problems[] = [
                'id' => 'connection_missing',
                'title' => 'The module is not connected to eBay',
                'description' => 'Click "Connect to eBay" on the configuration page to authorize the application.',
                'count' => 1,
                'fix' => 'config',
            ];
        } elseif (!$this->apiClient->isConfigured()) {
            $problems[] = [
                'id' => 'credentials_missing',
                'title' => 'The eBay API credentials are incomplete',
                'description' => 'Enter your eBay code (RuName) on the configuration page, then click "Connect to eBay".',
                'count' => 1,
                'fix' => 'config',
            ];
        }

        if ((string) \Configuration::get(Config::EBAY_USERNAME) === '') {
            $problems[] = [
                'id' => 'username_missing',
                'title' => 'The eBay username is missing',
                'description' => 'Fill in your eBay seller username so the module can find your listings.',
                'count' => 1,
                'fix' => 'config',
            ];
        }

        $failedImports = $this->failedImportItemIds();
        if (!empty($failedImports)) {
            $problems[] = [
                'id' => 'import_failed',
                'title' => 'Some listings failed to import',
                'description' => 'The last import runs failed for these listings. Click to retry them now.',
                'count' => count($failedImports),
                'fix' => 'fix_import',
            ];
        }

        $failedSyncs = $this->countRecentErrors(['sync_ebay_to_ps', 'sync_ps_to_ebay']);
        if ($failedSyncs > 0) {
            $problems[] = [
                'id' => 'sync_failed',
                'title' => 'Stock synchronization errors',
                'description' => 'Some stock updates failed. Click to run the stock synchronization again.',
                'count' => $failedSyncs,
                'fix' => 'fix_sync',
            ];
        }

        $failedImages = $this->countRecentErrors(['image']);
        if ($failedImages > 0) {
            $problems[] = [
                'id' => 'image_failed',
                'title' => 'Some product images could not be downloaded',
                'description' => 'The image download failed for some listings. Click to retry the import of these listings.',
                'count' => $failedImages,
                'fix' => 'fix_import',
            ];
        }

        $failedPrices = $this->countRecentErrors(['price_rule']);
        if ($failedPrices > 0) {
            $problems[] = [
                'id' => 'price_failed',
                'title' => 'Some dynamic price pushes to eBay failed',
                'description' => 'The price rules could not update some eBay listings. Click to re-run the price rules.',
                'count' => $failedPrices,
                'fix' => 'fix_price',
            ];
        }

        $orphanLinks = $this->orphanCombinationLinks();
        if (!empty($orphanLinks)) {
            $problems[] = [
                'id' => 'orphan_combinations',
                'title' => 'Some combinations no longer exist on eBay',
                'description' => 'These PrestaShop combinations were removed from the eBay listing. Click to remove them from the shop.',
                'count' => count($orphanLinks),
                'fix' => 'fix_orphans',
            ];
        }

        $inactiveLinks = $this->inactiveProductLinks();
        if (!empty($inactiveLinks)) {
            $problems[] = [
                'id' => 'inactive_products',
                'title' => 'Products deactivated because their listing disappeared',
                'description' => 'These products were deactivated by the module. Click to reactivate them (e.g. if the listing was ended by mistake).',
                'count' => count($inactiveLinks),
                'fix' => 'fix_inactive',
            ];
        }

        return $problems;
    }

    /**
     * eBay item ids whose import or image download failed within the last 24 hours.
     *
     * @return string[]
     */
    public function failedImportItemIds(): array
    {
        return array_values(array_unique(array_merge(
            $this->recentFailedItems('import'),
            $this->recentFailedItems('image')
        )));
    }

    /**
     * Mapping rows whose combination no longer exists in the eBay listing.
     *
     * @return LinkBayProduct[]
     */
    public function orphanCombinationLinks(): array
    {
        $links = $this->productLinkRepository->findAll();
        $parentItems = [];

        foreach ($links as $link) {
            if ($link->getIdProductAttribute() > 0) {
                $parentItems[$link->getEbayItemId()] = true;
            }
        }

        $orphans = [];

        foreach (array_keys($parentItems) as $itemId) {
            try {
                $detail = $this->apiClient->getItemDetail($itemId);
            } catch (\Throwable $e) {
                // Listing indisponible : on ne peut pas conclure, on passe.
                continue;
            }

            $ebaySkus = [];
            foreach (($detail['variations'] ?? []) as $variation) {
                $ebaySkus[(string) ($variation['sku'] ?? '')] = true;
            }

            if (empty($ebaySkus)) {
                // Plus de déclinaisons côté eBay : la synchro d'import ré-ajoutera
                // ce qui manque, on ne déclare rien ici.
                continue;
            }

            foreach ($links as $link) {
                if ($link->getEbayItemId() !== $itemId || $link->getIdProductAttribute() <= 0) {
                    continue;
                }
                if (!isset($ebaySkus[$link->getEbaySku()])) {
                    $orphans[] = $link;
                }
            }
        }

        return $orphans;
    }

    /**
     * Mapping rows whose PrestaShop product is inactive.
     *
     * @return LinkBayProduct[]
     */
    public function inactiveProductLinks(): array
    {
        $links = [];

        foreach ($this->productLinkRepository->findAll() as $link) {
            if ($link->getIdProductAttribute() !== 0) {
                continue;
            }

            $product = new \Product($link->getIdProduct());
            if (\Validate::isLoadedObject($product) && !(bool) $product->active) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * Returns the eBay item ids whose action failed within the last 24 hours.
     *
     * @return string[]
     */
    private function recentFailedItems(string $action): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $sql = 'SELECT DISTINCT `ebay_item_id`
            FROM ' . _DB_PREFIX_ . 'linkbay_log
            WHERE `action` = "' . pSQL($action) . '"
              AND `status` = "error"
              AND `ebay_item_id` <> ""
              AND `date_add` > "' . pSQL($cutoff) . '"';

        $rows = \Db::getInstance()->executeS($sql);

        return array_map(static fn (array $row): string => (string) $row['ebay_item_id'], is_array($rows) ? $rows : []);
    }

    /**
     * @param string[] $actions
     */
    private function countRecentErrors(array $actions): int
    {
        $quoted = implode(',', array_map(static fn (string $action): string => '"' . pSQL($action) . '"', $actions));
        $cutoff = date('Y-m-d H:i:s', time() - 86400);

        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'linkbay_log
            WHERE `action` IN (' . $quoted . ')
              AND `status` = "error"
              AND `date_add` > "' . pSQL($cutoff) . '"'
        );
    }
}
