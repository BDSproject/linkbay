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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Converts a normalized eBay listing (from the Sell Inventory API) into a
 * structure ready to be turned into a PrestaShop product.
 */
final class EbayProductMapper
{
    public const MAX_IMAGES = 10;

    /**
     * Maps a normalized eBay listing array (as produced by EbayApiClient).
     *
     * @param array<string, mixed> $item
     *
     * @return array{
     *     ebay_item_id: string,
     *     marketplace_id: string,
     *     title: string,
     *     description: string,
     *     description_short: string,
     *     sku: string,
     *     price: float,
     *     currency: string,
     *     quantity: int,
     *     ebay_category_id: string,
     *     ebay_category_name: string,
     *     images: string[],
     *     variations: array<int, array<string, mixed>>
     * }
     */
    public function mapItem(array $item): array
    {
        $images = [];
        foreach (($item['images'] ?? []) as $url) {
            if (is_string($url) && $url !== '' && count($images) < self::MAX_IMAGES) {
                $images[] = $url;
            }
        }

        $variations = [];
        foreach (($item['variations'] ?? []) as $variation) {
            if (!is_array($variation)) {
                continue;
            }

            $variations[] = [
                'sku' => (string) ($variation['sku'] ?? ''),
                'quantity' => (int) ($variation['quantity'] ?? 0),
                'price' => (float) ($variation['price'] ?? 0),
                'attributes' => (array) ($variation['attributes'] ?? []),
            ];
        }

        return [
            'ebay_item_id' => (string) ($item['item_id'] ?? ''),
            'marketplace_id' => (string) ($item['marketplace_id'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'description' => $this->cleanDescription((string) ($item['description'] ?? '')),
            'description_short' => $this->shortDescription((string) ($item['description'] ?? '')),
            'sku' => (string) ($item['sku'] ?? ''),
            'price' => (float) ($item['price'] ?? 0),
            'currency' => strtoupper((string) ($item['currency'] ?? '')),
            'quantity' => (int) ($item['quantity'] ?? 0),
            'ebay_category_id' => (string) ($item['category_id'] ?? ''),
            'ebay_category_name' => (string) ($item['category_name'] ?? ''),
            'images' => $images,
            'variations' => $variations,
        ];
    }

    /**
     * Cleans a raw eBay HTML description.
     */
    private function cleanDescription(string $html): string
    {
        $html = (string) preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = (string) preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        if (method_exists(\Tools::class, 'purifyHTML')) {
            try {
                return (string) \Tools::purifyHTML($html, [], true);
            } catch (\Throwable $e) {
                // Fall through to the plain tag strip below.
            }
        }

        return trim((string) strip_tags(
            $html,
            '<p><br><b><strong><i><em><ul><ol><li><h1><h2><h3><h4><table><tr><td><th><a><img><span><div>'
        ));
    }

    /**
     * Produces a plain text short description.
     */
    private function shortDescription(string $html): string
    {
        $text = trim((string) strip_tags($this->cleanDescription($html)));

        if (mb_strlen($text) > 300) {
            $text = mb_substr($text, 0, 297) . '…';
        }

        return $text;
    }
}
