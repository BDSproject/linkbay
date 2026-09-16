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

namespace LinkBay\Repository;

use LinkBay\Entity\LinkBayProduct;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Persistence layer for the "linkbay_product_link" table.
 */
final class LinkBayProductRepository
{
    private function table(): string
    {
        return '`' . _DB_PREFIX_ . 'linkbay_product_link`';
    }

    /**
     * Fetches a single row, normalizing the mixed Db return type.
     *
     * @return array<string, mixed>|null
     */
    private function selectRow(string $sql): ?array
    {
        $row = \Db::getInstance()->getRow($sql);

        return is_array($row) ? $row : null;
    }

    /**
     * Fetches multiple rows, normalizing the mixed Db return type.
     *
     * @return array<int, array<string, mixed>>
     */
    private function selectRows(string $sql): array
    {
        $rows = \Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Inserts or updates a mapping row.
     */
    public function save(LinkBayProduct $entity): bool
    {
        $db = \Db::getInstance();
        $now = date('Y-m-d H:i:s');

        if ($entity->getId() !== null) {
            $entity->setDateUpd($now);

            return $db->execute(
                'UPDATE ' . $this->table() . ' SET
                    `id_product` = ' . (int) $entity->getIdProduct() . ',
                    `id_product_attribute` = ' . (int) $entity->getIdProductAttribute() . ',
                    `ebay_item_id` = "' . pSQL($entity->getEbayItemId()) . '",
                    `ebay_sku` = "' . pSQL($entity->getEbaySku()) . '",
                    `marketplace_id` = "' . pSQL($entity->getMarketplaceId()) . '",
                    `buffer_percent` = ' . $this->quoteNullableFloat($entity->getBufferPercent()) . ',
                    `last_synced_quantity` = ' . (int) $entity->getLastSyncedQuantity() . ',
                    `last_sync_date` = ' . $this->quoteDate($entity->getLastSyncDate()) . ',
                    `sync_status` = "' . pSQL($entity->getSyncStatus()) . '",
                    `base_price` = ' . (float) $entity->getBasePrice() . ',
                    `price_currency` = "' . pSQL($entity->getPriceCurrency()) . '",
                    `target_price` = ' . $this->quoteNullablePrice($entity->getTargetPrice()) . ',
                    `id_price_rule` = ' . $this->quoteNullableInt($entity->getIdPriceRule()) . ',
                    `price_status` = "' . pSQL($entity->getPriceStatus()) . '",
                    `date_upd` = "' . pSQL($now) . '"
                WHERE `id_linkbay_product` = ' . (int) $entity->getId()
            );
        }

        $entity->setDateAdd($now);
        $entity->setDateUpd($now);

        $inserted = $db->execute(
            'INSERT INTO ' . $this->table() . ' (
                `id_product`, `id_product_attribute`, `ebay_item_id`, `ebay_sku`, `marketplace_id`,
                `buffer_percent`,
                `last_synced_quantity`, `last_sync_date`, `sync_status`,
                `base_price`, `price_currency`, `target_price`, `id_price_rule`, `price_status`,
                `date_add`, `date_upd`
            ) VALUES (
                ' . (int) $entity->getIdProduct() . ',
                ' . (int) $entity->getIdProductAttribute() . ',
                "' . pSQL($entity->getEbayItemId()) . '",
                "' . pSQL($entity->getEbaySku()) . '",
                "' . pSQL($entity->getMarketplaceId()) . '",
                ' . $this->quoteNullableFloat($entity->getBufferPercent()) . ',
                ' . (int) $entity->getLastSyncedQuantity() . ',
                ' . $this->quoteDate($entity->getLastSyncDate()) . ',
                "' . pSQL($entity->getSyncStatus()) . '",
                ' . (float) $entity->getBasePrice() . ',
                "' . pSQL($entity->getPriceCurrency()) . '",
                ' . $this->quoteNullablePrice($entity->getTargetPrice()) . ',
                ' . $this->quoteNullableInt($entity->getIdPriceRule()) . ',
                "' . pSQL($entity->getPriceStatus()) . '",
                "' . pSQL($now) . '",
                "' . pSQL($now) . '"
            )'
        );

        if ($inserted) {
            $entity->setId((int) $db->Insert_ID());
        }

        return (bool) $inserted;
    }

    public function findById(int $id): ?LinkBayProduct
    {
        $row = $this->selectRow(
            'SELECT * FROM ' . $this->table() . ' WHERE `id_linkbay_product` = ' . (int) $id
        );

        return $row ? LinkBayProduct::fromArray($row) : null;
    }

    public function findByEbayItemId(string $ebayItemId, int $idProductAttribute = 0): ?LinkBayProduct
    {
        $row = $this->selectRow(
            'SELECT * FROM ' . $this->table() . '
            WHERE `ebay_item_id` = "' . pSQL($ebayItemId) . '"
              AND `id_product_attribute` = ' . (int) $idProductAttribute
        );

        return $row ? LinkBayProduct::fromArray($row) : null;
    }

    /**
     * Returns the first mapping row for an eBay item id.
     *
     * When $idProductAttribute is null any combination is accepted; when it is
     * provided only that specific attribute id is matched.
     */
    public function findOneByEbayItemId(string $ebayItemId, ?int $idProductAttribute = null): ?LinkBayProduct
    {
        $sql = 'SELECT * FROM ' . $this->table() . ' WHERE `ebay_item_id` = "' . pSQL($ebayItemId) . '"';

        if ($idProductAttribute !== null) {
            $sql .= ' AND `id_product_attribute` = ' . (int) $idProductAttribute;
        }

        $row = $this->selectRow($sql);

        return $row ? LinkBayProduct::fromArray($row) : null;
    }

    /**
     * Returns a mapping row by eBay item id and variation SKU.
     */
    public function findByEbayItemIdAndSku(string $ebayItemId, string $ebaySku): ?LinkBayProduct
    {
        $row = $this->selectRow(
            'SELECT * FROM ' . $this->table() . '
            WHERE `ebay_item_id` = "' . pSQL($ebayItemId) . '"
              AND `ebay_sku` = "' . pSQL($ebaySku) . '"'
        );

        return $row ? LinkBayProduct::fromArray($row) : null;
    }

    /**
     * Returns every mapping row for a given PrestaShop product (all combinations).
     *
     * @return LinkBayProduct[]
     */
    public function findByProduct(int $idProduct): array
    {
        $rows = $this->selectRows(
            'SELECT * FROM ' . $this->table() . ' WHERE `id_product` = ' . (int) $idProduct
        );

        return array_map(static fn (array $row): LinkBayProduct => LinkBayProduct::fromArray($row), $rows);
    }

    /**
     * Returns all mapping rows.
     *
     * @return LinkBayProduct[]
     */
    public function findAll(): array
    {
        $rows = $this->selectRows('SELECT * FROM ' . $this->table());

        return array_map(static fn (array $row): LinkBayProduct => LinkBayProduct::fromArray($row), $rows);
    }

    /**
     * Deletes every mapping row (used on the eBay account deletion event).
     */
    public function deleteAll(): bool
    {
        return (bool) \Db::getInstance()->execute('DELETE FROM ' . $this->table());
    }

    /**
     * Returns the list of eBay item ids currently mapped.
     *
     * @return string[]
     */
    public function findAllEbayItemIds(): array
    {
        $rows = $this->selectRows(
            'SELECT DISTINCT `ebay_item_id` FROM ' . $this->table()
        );

        return array_map(static fn (array $row): string => (string) $row['ebay_item_id'], $rows);
    }

    public function delete(LinkBayProduct $entity): bool
    {
        return $this->deleteById((int) $entity->getId());
    }

    public function deleteById(int $id): bool
    {
        return (bool) \Db::getInstance()->delete(
            'linkbay_product_link',
            '`id_linkbay_product` = ' . (int) $id
        );
    }

    /**
     * Removes every mapping row linked to a PrestaShop product.
     */
    public function deleteByProduct(int $idProduct): bool
    {
        return (bool) \Db::getInstance()->delete(
            'linkbay_product_link',
            '`id_product` = ' . (int) $idProduct
        );
    }

    /**
     * Updates the sync snapshot of a mapping row without touching other fields.
     */
    public function updateSyncState(int $id, int $quantity, string $status): bool
    {
        return (bool) \Db::getInstance()->execute(
            'UPDATE ' . $this->table() . ' SET
                `last_synced_quantity` = ' . (int) $quantity . ',
                `last_sync_date` = "' . pSQL(date('Y-m-d H:i:s')) . '",
                `sync_status` = "' . pSQL($status) . '"
            WHERE `id_linkbay_product` = ' . (int) $id
        );
    }

    /**
     * Deletes mapping rows whose eBay item id is no longer present in the given set.
     *
     * @param string[] $keptItemIds
     */
    public function deleteWhereItemIdNotIn(array $keptItemIds): int
    {
        if (empty($keptItemIds)) {
            return 0;
        }

        $quoted = implode(',', array_map(
            static fn (string $id): string => '"' . pSQL($id) . '"',
            $keptItemIds
        ));

        $affected = $this->selectRows(
            'SELECT `id_linkbay_product` FROM ' . $this->table() . ' WHERE `ebay_item_id` NOT IN (' . $quoted . ')'
        );
        $ids = array_map(static fn (array $row): int => (int) $row['id_linkbay_product'], $affected);

        if (empty($ids)) {
            return 0;
        }

        \Db::getInstance()->delete(
            'linkbay_product_link',
            '`id_linkbay_product` IN (' . implode(',', $ids) . ')'
        );

        return count($ids);
    }

    /**
     * Quotes a date string for SQL or returns the literal NULL.
     */
    private function quoteDate(?string $value): string
    {
        return $value !== null ? '"' . pSQL($value) . '"' : 'NULL';
    }

    /**
     * Quotes a nullable float for SQL or returns the literal NULL.
     */
    private function quoteNullableFloat(?float $value): string
    {
        return $value !== null ? (string) round($value, 2) : 'NULL';
    }

    /**
     * Quotes a nullable decimal price (6 decimals) for SQL or returns NULL.
     */
    private function quoteNullablePrice(?float $value): string
    {
        return $value !== null ? (string) round($value, 6) : 'NULL';
    }

    /**
     * Quotes a nullable integer for SQL or returns the literal NULL.
     */
    private function quoteNullableInt(?int $value): string
    {
        return $value !== null ? (string) $value : 'NULL';
    }

    /**
     * Updates the dynamic price snapshot of a mapping row.
     */
    public function updatePriceState(int $id, ?float $targetPrice, ?int $idPriceRule, string $priceStatus): bool
    {
        return (bool) \Db::getInstance()->execute(
            'UPDATE ' . $this->table() . ' SET
                `target_price` = ' . $this->quoteNullablePrice($targetPrice) . ',
                `id_price_rule` = ' . $this->quoteNullableInt($idPriceRule) . ',
                `price_status` = "' . pSQL($priceStatus) . '",
                `date_upd` = "' . pSQL(date('Y-m-d H:i:s')) . '"
            WHERE `id_linkbay_product` = ' . (int) $id
        );
    }

    /**
     * Updates the base price snapshot (eBay price at import time).
     */
    public function updateBasePrice(int $id, float $basePrice, string $currency): bool
    {
        return (bool) \Db::getInstance()->execute(
            'UPDATE ' . $this->table() . ' SET
                `base_price` = ' . (float) $basePrice . ',
                `price_currency` = "' . pSQL($currency) . '",
                `date_upd` = "' . pSQL(date('Y-m-d H:i:s')) . '"
            WHERE `id_linkbay_product` = ' . (int) $id
        );
    }

    /**
     * Updates the per-product stock buffer percentage (NULL = use the global
     * buffer setting).
     */
    public function updateBufferPercent(int $id, ?float $percent): bool
    {
        return (bool) \Db::getInstance()->execute(
            'UPDATE ' . $this->table() . ' SET `buffer_percent` = ' . $this->quoteNullableFloat($percent) . '
            WHERE `id_linkbay_product` = ' . (int) $id
        );
    }
}
