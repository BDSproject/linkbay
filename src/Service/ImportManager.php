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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LinkBay\Config\Config;
use LinkBay\Entity\LinkBayLog;
use LinkBay\Entity\LinkBayProduct;
use LinkBay\Repository\LinkBayCategoryMapRepository;
use LinkBay\Repository\LinkBayLogRepository;
use LinkBay\Repository\LinkBayProductRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Imports eBay listings into PrestaShop (automatic and manual).
 */
final class ImportManager
{
    private EbayApiClient $apiClient;
    private EbayProductMapper $mapper;
    private LinkBayProductRepository $productLinkRepository;
    private LinkBayLogRepository $logRepository;
    private LinkBayCategoryMapRepository $categoryMapRepository;
    private ?Client $httpClient = null;

    public function __construct(
        EbayApiClient $apiClient,
        EbayProductMapper $mapper,
        LinkBayProductRepository $productLinkRepository,
        LinkBayLogRepository $logRepository,
        LinkBayCategoryMapRepository $categoryMapRepository
    ) {
        $this->apiClient = $apiClient;
        $this->mapper = $mapper;
        $this->productLinkRepository = $productLinkRepository;
        $this->logRepository = $logRepository;
        $this->categoryMapRepository = $categoryMapRepository;
    }

    /**
     * Imports every active eBay listing and deactivates products whose listing
     * no longer exists on eBay.
     *
     * @return array{imported: int, updated: int, deactivated: int, skipped: int}
     */
    public function autoImport(): array
    {
        $summary = ['imported' => 0, 'updated' => 0, 'deactivated' => 0, 'skipped' => 0];

        $listings = $this->apiClient->getActiveListings();
        $activeItemIds = [];

        foreach ($listings as $listing) {
            $itemId = (string) ($listing['item_id'] ?? '');
            if ($itemId === '') {
                continue;
            }

            $activeItemIds[] = $itemId;

            try {
                $mapped = $this->mapper->mapItem($listing);
                $result = $this->createOrUpdateProduct($mapped);
            } catch (\Throwable $e) {
                $this->log(
                    0,
                    $itemId,
                    'import',
                    LinkBayLog::STATUS_ERROR,
                    'Import failed for listing "' . $itemId . '" (' . mb_substr((string) ($listing['title'] ?? ''), 0, 60) . '): ' . $e->getMessage()
                );
                ++$summary['skipped'];
                continue;
            }

            ++$summary[$result === 'created' ? 'imported' : ($result === 'updated' ? 'updated' : 'skipped')];
        }

        // Garde-fou : ne jamais désactiver en masse. Si aucune annonce n'a été
        // trouvée (panne eBay, identifiant vendeur erroné, liste vide…), les
        // produits existants restent intacts.
        if (empty($activeItemIds)) {
            $this->log(0, '', 'import', LinkBayLog::STATUS_SUCCESS, 'No active listing found; existing products were left untouched.');
        } else {
            $summary['deactivated'] = $this->deactivateMissing($activeItemIds);
        }

        return $summary;
    }

    /**
     * Imports a specific set of eBay items (manual import).
     *
     * @param string[] $itemIds
     *
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function manualImport(array $itemIds): array
    {
        $summary = ['imported' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($itemIds as $itemId) {
            $itemId = (string) $itemId;
            if ($itemId === '') {
                continue;
            }

            try {
                $mapped = $this->mapper->mapItem($this->apiClient->getItemDetail($itemId));
                $result = $this->createOrUpdateProduct($mapped);
            } catch (\Throwable $e) {
                $this->log(
                    0,
                    $itemId,
                    'import',
                    LinkBayLog::STATUS_ERROR,
                    'Manual import failed for listing "' . $itemId . '": ' . $e->getMessage()
                );
                ++$summary['skipped'];
                continue;
            }

            ++$summary[$result === 'created' ? 'imported' : ($result === 'updated' ? 'updated' : 'skipped')];
        }

        return $summary;
    }

    /**
     * Creates or updates the PrestaShop product for a mapped eBay item.
     *
     * @param array<string, mixed> $mapped
     */
    public function createOrUpdateProduct(array $mapped): string
    {
        $itemId = $mapped['ebay_item_id'] ?? '';
        if ($itemId === '') {
            return 'skipped';
        }

        $existing = $this->productLinkRepository->findOneByEbayItemId($itemId);

        if ($existing !== null) {
            $this->updateProduct($existing->getIdProduct(), $mapped);

            return 'updated';
        }

        $idProduct = $this->createProduct($mapped);

        return $idProduct > 0 ? 'created' : 'skipped';
    }

    /**
     * Deactivates PrestaShop products whose eBay listing disappeared.
     *
     * @param string[] $activeItemIds
     */
    public function deactivateMissing(array $activeItemIds): int
    {
        $mapped = $this->productLinkRepository->findAllEbayItemIds();
        $missing = array_diff($mapped, $activeItemIds);
        $deactivated = 0;

        foreach ($missing as $itemId) {
            $link = $this->productLinkRepository->findOneByEbayItemId($itemId);
            if ($link === null) {
                continue;
            }

            $product = new \Product($link->getIdProduct());
            if (!\Validate::isLoadedObject($product)) {
                $this->productLinkRepository->deleteByProduct($link->getIdProduct());
                continue;
            }

            $product->active = false;
            $product->update();
            ++$deactivated;

            $this->log(
                (int) $product->id,
                $itemId,
                'deactivate',
                LinkBayLog::STATUS_SUCCESS,
                'Listing no longer active on eBay; product deactivated.'
            );
        }

        return $deactivated;
    }

    /**
     * Creates a PrestaShop product from a mapped eBay item.
     *
     * @param array<string, mixed> $mapped
     */
    private function createProduct(array $mapped): int
    {
        $variations = $mapped['variations'] ?? [];
        $basePrice = $this->computeBasePrice($mapped['price'], $variations);

        $product = new \Product();
        $product->name = $this->multiLang($mapped['title']);
        $product->description = $this->multiLang($mapped['description']);
        $product->description_short = $this->multiLang($mapped['description_short']);
        $product->id_category_default = $this->resolveCategory($mapped['ebay_category_id']);
        $product->link_rewrite = $this->multiLang((string) \Tools::str2url($mapped['title']));
        $product->price = \Tools::ps_round($this->priceExcludingTax($basePrice), 6);
        $product->id_tax_rules_group = (int) \Configuration::get(Config::TAX_RULE);
        $product->reference = !empty($variations) ? $mapped['ebay_item_id'] : $mapped['sku'];
        $product->active = true;
        $product->visibility = 'both';

        if (!$product->add()) {
            $this->log(
                0,
                $mapped['ebay_item_id'],
                'import',
                LinkBayLog::STATUS_ERROR,
                'Failed to create the PrestaShop product for listing "' . $mapped['ebay_item_id'] . '" ("' . mb_substr($mapped['title'], 0, 60) . '"). Check the PrestaShop error logs for the underlying cause.'
            );

            return 0;
        }

        $product->addToCategories([(int) $product->id_category_default]);

        $position = 0;
        foreach ($mapped['images'] as $url) {
            $this->addImageToProduct($product, (string) $url, $position === 0);
            ++$position;
        }

        if (!empty($variations)) {
            $total = $this->createVariations($product, $variations, $mapped['ebay_item_id'], $basePrice, $mapped['marketplace_id']);
            $this->linkProduct((int) $product->id, 0, $mapped['ebay_item_id'], $mapped['sku'], $total, $mapped['marketplace_id'], $basePrice, (string) $mapped['currency']);
        } else {
            \StockAvailable::setQuantity((int) $product->id, 0, (int) $mapped['quantity']);
            $this->linkProduct((int) $product->id, 0, $mapped['ebay_item_id'], $mapped['sku'], (int) $mapped['quantity'], $mapped['marketplace_id'], $basePrice, (string) $mapped['currency']);
        }

        $this->log((int) $product->id, $mapped['ebay_item_id'], 'import', LinkBayLog::STATUS_SUCCESS, 'Product created from eBay listing.');

        return (int) $product->id;
    }

    /**
     * Updates an existing PrestaShop product from a mapped eBay item.
     *
     * @param array<string, mixed> $mapped
     */
    private function updateProduct(int $idProduct, array $mapped): void
    {
        $product = new \Product($idProduct);
        if (!\Validate::isLoadedObject($product)) {
            $this->log(
                $idProduct,
                $mapped['ebay_item_id'] ?? '',
                'import',
                LinkBayLog::STATUS_ERROR,
                'Product id ' . $idProduct . ' no longer exists in PrestaShop; the eBay listing could not be updated.'
            );

            return;
        }

        $variations = $mapped['variations'] ?? [];
        $basePrice = $this->computeBasePrice($mapped['price'], $variations);

        // Quand une règle de prix dynamique est appliquée au produit PrestaShop,
        // l'import ne doit pas écraser ce prix par le prix de base eBay.
        $priceForPs = $this->priceExcludingTax($basePrice);
        if ((bool) \Configuration::get(Config::PRICE_RULE_APPLY_TO_PS)) {
            $parent = $this->productLinkRepository->findOneByEbayItemId((string) $mapped['ebay_item_id'], 0);
            if ($parent !== null && $parent->getTargetPrice() !== null && $parent->getPriceStatus() !== '') {
                $priceForPs = $this->priceExcludingTax($parent->getTargetPrice());
            }
        }

        $product->name = $this->multiLang($mapped['title']);
        $product->description = $this->multiLang($mapped['description']);
        $product->description_short = $this->multiLang($mapped['description_short']);
        $product->price = \Tools::ps_round($priceForPs, 6);
        $product->id_tax_rules_group = (int) \Configuration::get(Config::TAX_RULE);
        $product->active = true;

        if (!$product->update()) {
            $this->log(
                $idProduct,
                $mapped['ebay_item_id'] ?? '',
                'import',
                LinkBayLog::STATUS_ERROR,
                'Failed to update the PrestaShop product ' . $idProduct . ' for listing "' . ($mapped['ebay_item_id'] ?? '') . '". Check the PrestaShop error logs for the underlying cause.'
            );

            return;
        }

        if (!empty($variations)) {
            $total = $this->syncVariations($product, $variations, $mapped['ebay_item_id'], $basePrice, $mapped['marketplace_id']);
            $this->updateParentLink($mapped['ebay_item_id'], $total, $basePrice, (string) $mapped['currency']);
        } else {
            \StockAvailable::setQuantity($idProduct, 0, (int) $mapped['quantity']);
            $this->updateParentLink($mapped['ebay_item_id'], (int) $mapped['quantity'], $basePrice, (string) $mapped['currency']);
        }

        $this->log($idProduct, $mapped['ebay_item_id'], 'import', LinkBayLog::STATUS_SUCCESS, 'Product updated from eBay listing.');
    }

    /**
     * Creates combinations for a multi-variation listing and returns the total quantity.
     *
     * @param array<int, array<string, mixed>> $variations
     */
    private function createVariations(\Product $product, array $variations, string $ebayItemId, float $basePrice, string $marketplaceId = ''): int
    {
        $total = 0;
        $index = 0;
        $convertedBasePrice = $this->priceExcludingTax($basePrice);

        foreach ($variations as $variation) {
            $combinationId = $this->createCombination($product, $variation, $index === 0, $convertedBasePrice);
            if ($combinationId <= 0) {
                ++$index;
                continue;
            }

            $total += (int) ($variation['quantity'] ?? 0);
            $this->linkProduct(
                (int) $product->id,
                $combinationId,
                $ebayItemId,
                (string) ($variation['sku'] ?? ''),
                (int) ($variation['quantity'] ?? 0),
                $marketplaceId
            );
            ++$index;
        }

        return $total;
    }

    /**
     * Creates or updates combinations for a multi-variation listing.
     *
     * @param array<int, array<string, mixed>> $variations
     */
    private function syncVariations(\Product $product, array $variations, string $ebayItemId, float $basePrice, string $marketplaceId = ''): int
    {
        $total = 0;
        $convertedBasePrice = $this->priceExcludingTax($basePrice);

        foreach ($variations as $variation) {
            $sku = (string) ($variation['sku'] ?? '');
            $quantity = (int) ($variation['quantity'] ?? 0);

            $link = $this->productLinkRepository->findByEbayItemIdAndSku($ebayItemId, $sku);

            if ($link === null || $link->getIdProductAttribute() === 0) {
                $combinationId = $this->createCombination($product, $variation, false, $convertedBasePrice);
                if ($combinationId > 0) {
                    $this->linkProduct((int) $product->id, $combinationId, $ebayItemId, $sku, $quantity, $marketplaceId);
                }
            } else {
                $combinationId = $link->getIdProductAttribute();
                \StockAvailable::setQuantity((int) $product->id, $combinationId, $quantity);
                $this->productLinkRepository->updateSyncState((int) $link->getId(), $quantity, 'synced');

                // Met à jour l'impact prix de la combinaison existante.
                $combination = new \Combination($combinationId);
                if (\Validate::isLoadedObject($combination)) {
                    $combination->price = \Tools::ps_round(
                        $this->priceExcludingTax((float) ($variation['price'] ?? 0)) - $convertedBasePrice,
                        6
                    );
                    $combination->update();
                }
            }

            $total += $quantity;
        }

        return $total;
    }

    /**
     * Creates a single combination from a variation descriptor.
     *
     * @param array<string, mixed> $variation
     */
    private function createCombination(\Product $product, array $variation, bool $isDefault, float $convertedBasePrice): int
    {
        $attributeIds = [];
        foreach (($variation['attributes'] ?? []) as $name => $value) {
            $groupId = $this->findOrCreateAttributeGroup((string) $name);
            if ($groupId <= 0) {
                continue;
            }
            $attributeIds[] = $this->findOrCreateAttributeValue($groupId, (string) $value);
        }

        $combination = new \Combination();
        $combination->id_product = (int) $product->id;
        $combination->reference = (string) ($variation['sku'] ?? '');
        $combination->price = \Tools::ps_round(
            $this->priceExcludingTax((float) ($variation['price'] ?? 0)) - $convertedBasePrice,
            6
        );
        $combination->default_on = $isDefault;

        if (!$combination->add()) {
            $this->log(
                (int) $product->id,
                '',
                'import',
                LinkBayLog::STATUS_ERROR,
                'Failed to create the combination "' . (string) ($variation['sku'] ?? '') . '" for product ' . (int) $product->id . '. Check the PrestaShop error logs for the underlying cause.'
            );

            return 0;
        }

        if (!empty($attributeIds)) {
            $combination->setAttributes($attributeIds);
        }

        \StockAvailable::setQuantity((int) $product->id, (int) $combination->id, (int) ($variation['quantity'] ?? 0));

        return (int) $combination->id;
    }

    /**
     * Computes the product base price (minimum variation price when applicable).
     *
     * @param array<int, array<string, mixed>> $variations
     */
    private function computeBasePrice(float $itemPrice, array $variations): float
    {
        if (empty($variations)) {
            return $itemPrice;
        }

        $prices = array_map(static fn (array $variation): float => (float) ($variation['price'] ?? 0), $variations);
        $prices = array_filter($prices, static fn (float $price): bool => $price > 0);

        return !empty($prices) ? (float) min($prices) : $itemPrice;
    }

    /**
     * Converts an eBay gross price (taxes included) into the tax-excluded
     * price PrestaShop stores, using the configured tax rule.
     */
    private function priceExcludingTax(float $grossPrice): float
    {
        $taxRuleId = (int) \Configuration::get(Config::TAX_RULE);
        if ($taxRuleId <= 0 || $grossPrice <= 0) {
            return $grossPrice;
        }

        $rate = $this->defaultTaxRate($taxRuleId);
        if ($rate <= 0) {
            return $grossPrice;
        }

        return $grossPrice / (1 + $rate / 100);
    }

    /**
     * Returns the tax rate of a tax rules group for the shop default country.
     */
    private function defaultTaxRate(int $idTaxRulesGroup): float
    {
        $idCountry = (int) \Configuration::get('PS_COUNTRY_DEFAULT');

        $rate = (float) \Db::getInstance()->getValue(
            'SELECT t.`rate` FROM ' . _DB_PREFIX_ . 'tax_rule tr
             INNER JOIN ' . _DB_PREFIX_ . 'tax t ON t.`id_tax` = tr.`id_tax`
             WHERE tr.`id_tax_rules_group` = ' . (int) $idTaxRulesGroup . '
               AND tr.`id_country` = ' . (int) $idCountry
        );

        if ($rate > 0) {
            return $rate;
        }

        return (float) \Db::getInstance()->getValue(
            'SELECT t.`rate` FROM ' . _DB_PREFIX_ . 'tax_rule tr
             INNER JOIN ' . _DB_PREFIX_ . 'tax t ON t.`id_tax` = tr.`id_tax`
             WHERE tr.`id_tax_rules_group` = ' . (int) $idTaxRulesGroup
        );
    }

    /**
     * Resolves the PrestaShop category for an eBay category id.
     */
    private function resolveCategory(string $ebayCategoryId): int
    {
        $map = $this->categoryMapRepository->findByEbayCategoryId($ebayCategoryId);
        if ($map !== null && $map->getIdCategory() > 0) {
            return $map->getIdCategory();
        }

        $default = (int) \Configuration::get(Config::DEFAULT_CATEGORY);
        if ($default > 0) {
            return $default;
        }

        return (int) \Configuration::get('PS_HOME_CATEGORY');
    }

    /**
     * Downloads an eBay image and attaches it to a product.
     */
    private function addImageToProduct(\Product $product, string $url, bool $cover): bool
    {
        if ($url === '') {
            return false;
        }

        try {
            $response = $this->httpClient()->get($url);
            $content = (string) $response->getBody();
        } catch (GuzzleException $e) {
            $this->log(
                (int) $product->id,
                '',
                'image',
                LinkBayLog::STATUS_ERROR,
                'Failed to download the eBay image "' . $url . '" for product ' . (int) $product->id . ': ' . $e->getMessage()
            );

            return false;
        }

        if ($content === '') {
            $this->log(
                (int) $product->id,
                '',
                'image',
                LinkBayLog::STATUS_ERROR,
                'The eBay image "' . $url . '" returned an empty response for product ' . (int) $product->id . '.'
            );

            return false;
        }

        $tmpFile = tempnam(_PS_TMP_IMG_DIR_, 'linkbay_');
        if ($tmpFile === false) {
            return false;
        }

        file_put_contents($tmpFile, $content);

        $image = new \Image();
        $image->id_product = (int) $product->id;
        $image->position = \Image::getHighestPosition((int) $product->id) + 1;
        $image->cover = $cover;

        if (!$image->add()) {
            @unlink($tmpFile);

            return false;
        }

        $image->associateTo(\Shop::getContextListShopID());

        $newPath = $image->getPathForCreation();
        \ImageManager::resize($tmpFile, $newPath . '.jpg');

        foreach (\ImageType::getImagesTypes('products') as $imageType) {
            \ImageManager::resize(
                $tmpFile,
                $newPath . '-' . stripslashes($imageType['name']) . '.jpg',
                (int) $imageType['width'],
                (int) $imageType['height'],
                'jpg'
            );
        }

        @unlink($tmpFile);

        return true;
    }

    /**
     * Inserts a mapping row between an eBay item and a PrestaShop product/combination.
     *
     * The base price (eBay gross price) and currency are stored on the parent
     * rows: they are the reference used by the dynamic price rules.
     */
    private function linkProduct(
        int $idProduct,
        int $idProductAttribute,
        string $ebayItemId,
        string $ebaySku,
        int $quantity,
        string $marketplaceId = '',
        float $basePrice = 0.0,
        string $priceCurrency = ''
    ): void {
        $link = new LinkBayProduct();
        $link->setIdProduct($idProduct)
            ->setIdProductAttribute($idProductAttribute)
            ->setEbayItemId($ebayItemId)
            ->setEbaySku($ebaySku)
            ->setMarketplaceId($marketplaceId)
            ->setLastSyncedQuantity($quantity)
            ->setSyncStatus('synced');

        if ($idProductAttribute === 0) {
            $link->setBasePrice($basePrice)
                ->setPriceCurrency($priceCurrency);
        }

        try {
            $this->productLinkRepository->save($link);
        } catch (\Throwable $e) {
            // A mapping row is never allowed to break the import itself.
            $this->log($idProduct, $ebayItemId, 'import', LinkBayLog::STATUS_ERROR, 'Failed to save the mapping row: ' . $e->getMessage());
        }
    }

    /**
     * Updates the parent mapping row quantity snapshot and base price.
     */
    private function updateParentLink(string $ebayItemId, int $quantity, float $basePrice = 0.0, string $priceCurrency = ''): void
    {
        $link = $this->productLinkRepository->findOneByEbayItemId($ebayItemId, 0);
        if ($link !== null) {
            $this->productLinkRepository->updateSyncState((int) $link->getId(), $quantity, 'synced');

            if ($basePrice > 0) {
                $this->productLinkRepository->updateBasePrice((int) $link->getId(), $basePrice, $priceCurrency);
            }
        }
    }

    /**
     * Finds or creates an attribute group by its public name.
     */
    private function findOrCreateAttributeGroup(string $name): int
    {
        $id = (int) \Db::getInstance()->getValue(
            'SELECT `id_attribute_group` FROM ' . _DB_PREFIX_ . 'attribute_group_lang
            WHERE `name` = "' . pSQL($name) . '"'
        );

        if ($id > 0) {
            return $id;
        }

        $attributeGroup = new \AttributeGroup();
        $attributeGroup->name = $this->multiLang($name);
        $attributeGroup->public_name = $this->multiLang($name);
        $attributeGroup->group_type = 'select';

        if (!$attributeGroup->add()) {
            return 0;
        }

        return (int) $attributeGroup->id;
    }

    /**
     * Finds or creates an attribute value inside a group.
     */
    private function findOrCreateAttributeValue(int $idGroup, string $value): int
    {
        $id = (int) \Db::getInstance()->getValue(
            'SELECT a.`id_attribute` FROM ' . _DB_PREFIX_ . 'attribute a
            INNER JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON a.`id_attribute` = al.`id_attribute`
            WHERE a.`id_attribute_group` = ' . (int) $idGroup . ' AND al.`name` = "' . pSQL($value) . '"'
        );

        if ($id > 0) {
            return $id;
        }

        $attribute = new \ProductAttribute();
        $attribute->id_attribute_group = $idGroup;
        $attribute->name = $this->multiLang($value);

        if (!$attribute->add()) {
            return 0;
        }

        return (int) $attribute->id;
    }

    /**
     * Builds a value for every active language.
     *
     * @return array<int, string>
     */
    private function multiLang(string $value): array
    {
        $result = [];
        foreach (\Language::getLanguages(false) as $language) {
            if (!is_array($language)) {
                continue;
            }
            $result[(int) $language['id_lang']] = $value;
        }

        return $result;
    }

    /**
     * Writes a log entry without letting logging failures break the import.
     */
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
            // Logging must never interrupt the import flow.
        }
    }

    private function httpClient(): Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client([
                'timeout' => 30,
                'connect_timeout' => 10,
            ]);
        }

        return $this->httpClient;
    }
}
