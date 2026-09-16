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
use LinkBay\Entity\LinkBayLog;
use LinkBay\Entity\LinkBayProduct;
use LinkBay\Repository\LinkBayLogRepository;
use LinkBay\Repository\LinkBayProductRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Bidirectional stock synchronization between eBay and PrestaShop.
 *
 * Loop prevention relies on the "last synced quantity" snapshot stored in the
 * mapping table: a value is only pushed back to eBay when it actually changed.
 */
final class StockSynchronizer
{
    private EbayApiClient $apiClient;
    private EbayProductMapper $mapper;
    private LinkBayProductRepository $productLinkRepository;
    private LinkBayLogRepository $logRepository;

    public function __construct(
        EbayApiClient $apiClient,
        EbayProductMapper $mapper,
        LinkBayProductRepository $productLinkRepository,
        LinkBayLogRepository $logRepository
    ) {
        $this->apiClient = $apiClient;
        $this->mapper = $mapper;
        $this->productLinkRepository = $productLinkRepository;
        $this->logRepository = $logRepository;
    }

    /**
     * Pulls current eBay stock and updates PrestaShop quantities.
     *
     * @return array{synced: int, errors: int}
     */
    public function syncEbayToPrestashop(): array
    {
        $summary = ['synced' => 0, 'errors' => 0];
        $itemIds = $this->productLinkRepository->findAllEbayItemIds();

        foreach ($itemIds as $itemId) {
            $link = $this->productLinkRepository->findOneByEbayItemId($itemId, 0)
                ?? $this->productLinkRepository->findOneByEbayItemId($itemId);

            try {
                $mapped = $this->mapper->mapItem(
                    $this->apiClient->getItemDetail($itemId, null, $link !== null ? $link->getMarketplaceId() : null)
                );
                $this->applyEbayStock($itemId, $mapped);
                ++$summary['synced'];
            } catch (\Throwable $e) {
                $this->log(0, $itemId, 'sync_ebay_to_ps', LinkBayLog::STATUS_ERROR, $e->getMessage());
                ++$summary['errors'];
            }
        }

        return $summary;
    }

    /**
     * Pulls the current stock of a single listing from eBay into PrestaShop
     * (used by the real-time webhook).
     *
     * @return array{synced: int, error?: string}
     */
    public function syncItemFromEbay(string $itemId): array
    {
        $link = $this->productLinkRepository->findOneByEbayItemId($itemId, 0)
            ?? $this->productLinkRepository->findOneByEbayItemId($itemId);

        if ($link === null) {
            return ['synced' => 0, 'error' => 'No PrestaShop product is linked to listing "' . $itemId . '" yet; the next cron import will create it.'];
        }

        try {
            $mapped = $this->mapper->mapItem(
                $this->apiClient->getItemDetail($itemId, null, $link->getMarketplaceId())
            );
            $this->applyEbayStock($itemId, $mapped);

            return ['synced' => 1];
        } catch (\Throwable $e) {
            $this->log((int) $link->getIdProduct(), $itemId, 'sync_ebay_to_ps', LinkBayLog::STATUS_ERROR, $e->getMessage());

            return ['synced' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Pushes PrestaShop stock changes back to eBay.
     *
     * @return array{pushed: int, errors: int}
     */
    public function syncPrestashopToEbay(): array
    {
        $summary = ['pushed' => 0, 'errors' => 0];

        $requests = [];
        $pending = [];

        foreach ($this->productLinkRepository->findAll() as $link) {
            if ($link->getIdProductAttribute() !== 0) {
                continue;
            }

            // Only inventory-managed listings (with a SKU) can be updated back
            // on eBay; classic listings are read-only from PrestaShop.
            if ($link->getEbaySku() === '') {
                continue;
            }

            $currentStock = $this->getCurrentStock($link);

            // Le buffer stock garde une marge de sécurité dans PrestaShop :
            // on n'envoie sur eBay qu'une partie du stock réel.
            $pushedQuantity = $this->applyBuffer($link, $currentStock);

            if ($pushedQuantity === $link->getLastSyncedQuantity()) {
                continue;
            }

            $requests[] = [
                'sku' => $link->getEbaySku(),
                'quantity' => $pushedQuantity,
            ];
            $pending[] = ['link' => $link, 'quantity' => $pushedQuantity];
        }

        if (empty($requests)) {
            return $summary;
        }

        try {
            $responses = $this->apiClient->updateStock($requests);
        } catch (\Throwable $e) {
            foreach ($pending as $item) {
                $this->log(
                    (int) $item['link']->getIdProduct(),
                    $item['link']->getEbayItemId(),
                    'sync_ps_to_ebay',
                    LinkBayLog::STATUS_ERROR,
                    $e->getMessage()
                );
            }
            $summary['errors'] = count($pending);

            return $summary;
        }

        // Traite chaque résultat individuellement : un échec sur un SKU ne
        // doit pas empêcher les autres d'être marqués comme synchronisés.
        $resultBySku = [];
        foreach ($responses as $response) {
            $resultBySku[(string) ($response['sku'] ?? '')] = $response;
        }

        foreach ($pending as $item) {
            $response = $resultBySku[$item['link']->getEbaySku()] ?? null;
            $ok = $response !== null && (int) ($response['statusCode'] ?? 500) < 300;

            if ($ok) {
                $this->productLinkRepository->updateSyncState(
                    (int) $item['link']->getId(),
                    (int) $item['quantity'],
                    'synced'
                );
                $this->log(
                    (int) $item['link']->getIdProduct(),
                    $item['link']->getEbayItemId(),
                    'sync_ps_to_ebay',
                    LinkBayLog::STATUS_SUCCESS,
                    'Stock pushed to eBay: ' . (int) $item['quantity']
                );
                ++$summary['pushed'];
                continue;
            }

            $message = '';
            if ($response !== null && isset($response['errors'][0])) {
                $message = (string) ($response['errors'][0]['longMessage'] ?? $response['errors'][0]['message'] ?? '');
            }

            $this->log(
                (int) $item['link']->getIdProduct(),
                $item['link']->getEbayItemId(),
                'sync_ps_to_ebay',
                LinkBayLog::STATUS_ERROR,
                $message !== '' ? 'eBay rejected the stock update: ' . $message : 'eBay did not answer for this SKU.'
            );
            ++$summary['errors'];
        }

        return $summary;
    }

    /**
     * Applies eBay stock values to the matching PrestaShop product or combinations.
     *
     * @param array<string, mixed> $mapped
     */
    private function applyEbayStock(string $itemId, array $mapped): void
    {
        $parent = $this->productLinkRepository->findOneByEbayItemId($itemId, 0)
            ?? $this->productLinkRepository->findOneByEbayItemId($itemId);

        if ($parent === null) {
            return;
        }

        $variations = $mapped['variations'] ?? [];

        if (!empty($variations)) {
            $total = 0;

            foreach ($variations as $variation) {
                $sku = (string) ($variation['sku'] ?? '');
                $quantity = (int) ($variation['quantity'] ?? 0);

                $link = $this->productLinkRepository->findByEbayItemIdAndSku($itemId, $sku);
                if ($link === null || $link->getIdProductAttribute() === 0) {
                    continue;
                }

                \StockAvailable::setQuantity($link->getIdProduct(), $link->getIdProductAttribute(), $quantity);
                $this->productLinkRepository->updateSyncState((int) $link->getId(), $quantity, 'synced');
                $total += $quantity;
            }

            $this->productLinkRepository->updateSyncState((int) $parent->getId(), $total, 'synced');

            return;
        }

        $quantity = (int) ($mapped['quantity'] ?? 0);
        \StockAvailable::setQuantity($parent->getIdProduct(), 0, $quantity);
        $this->productLinkRepository->updateSyncState((int) $parent->getId(), $quantity, 'synced');
    }

    /**
     * Computes the current PrestaShop stock for a mapping row (summing combinations).
     */
    private function getCurrentStock(LinkBayProduct $link): int
    {
        $idProduct = $link->getIdProduct();
        $hasCombinations = false;
        $total = 0;

        foreach ($this->productLinkRepository->findByProduct($idProduct) as $row) {
            if ($row->getIdProductAttribute() > 0) {
                $hasCombinations = true;
                $total += (int) \StockAvailable::getQuantityAvailableByProduct(
                    $idProduct,
                    $row->getIdProductAttribute()
                );
            }
        }

        if ($hasCombinations) {
            return $total;
        }

        return (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, 0);
    }

    /**
     * Applies the stock buffer (per-product override, then the global mode)
     * to the quantity pushed to eBay, keeping a safety margin in PrestaShop.
     *
     * Example: 10 items in real stock with an 80% buffer send only 8 to eBay.
     */
    private function applyBuffer(LinkBayProduct $link, int $realStock): int
    {
        $override = $link->getBufferPercent();

        if ($override !== null && $override >= 0 && $override <= 100) {
            return max(0, (int) floor($realStock * $override / 100));
        }

        $mode = (string) \Configuration::get(Config::BUFFER_MODE);
        $value = (float) \Configuration::get(Config::BUFFER_VALUE);

        if ($mode === 'percentage') {
            return max(0, (int) floor($realStock * $value / 100));
        }

        if ($mode === 'fixed') {
            return max(0, $realStock - (int) $value);
        }

        return $realStock;
    }

    private function log(int $idProduct, string $ebayItemId, string $action, string $status, string $message): void
    {
        try {
            $log = new LinkBayLog();
            $log->setIdProduct($idProduct)
                ->setEbayItemId($ebayItemId)
                ->setAction($action)
                ->setStatus($status)
                ->setMessage($message);

            $this->logRepository->add($log);
        } catch (\Throwable $e) {
            // Logging must never interrupt synchronization.
        }
    }
}
