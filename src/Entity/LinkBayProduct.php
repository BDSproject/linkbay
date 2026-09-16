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

namespace LinkBay\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Maps an eBay listing to a PrestaShop product or combination.
 *
 * The "last synced quantity" snapshot is stored here so that the bidirectional
 * stock synchronizer can detect real changes without entering a push/pull loop.
 */
final class LinkBayProduct
{
    private ?int $id = null;
    private int $idProduct = 0;
    private int $idProductAttribute = 0;
    private string $ebayItemId = '';
    private string $ebaySku = '';
    private string $marketplaceId = '';
    private ?float $bufferPercent = null;
    private int $lastSyncedQuantity = 0;
    private ?string $lastSyncDate = null;
    private string $syncStatus = 'pending';
    private float $basePrice = 0.0;
    private string $priceCurrency = '';
    private ?float $targetPrice = null;
    private ?int $idPriceRule = null;
    private string $priceStatus = '';
    private ?string $dateAdd = null;
    private ?string $dateUpd = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getIdProduct(): int
    {
        return $this->idProduct;
    }

    public function setIdProduct(int $idProduct): self
    {
        $this->idProduct = $idProduct;

        return $this;
    }

    public function getIdProductAttribute(): int
    {
        return $this->idProductAttribute;
    }

    public function setIdProductAttribute(int $idProductAttribute): self
    {
        $this->idProductAttribute = $idProductAttribute;

        return $this;
    }

    public function getEbayItemId(): string
    {
        return $this->ebayItemId;
    }

    public function setEbayItemId(string $ebayItemId): self
    {
        $this->ebayItemId = $ebayItemId;

        return $this;
    }

    public function getEbaySku(): string
    {
        return $this->ebaySku;
    }

    public function setEbaySku(string $ebaySku): self
    {
        $this->ebaySku = $ebaySku;

        return $this;
    }

    public function getMarketplaceId(): string
    {
        return $this->marketplaceId;
    }

    public function setMarketplaceId(string $marketplaceId): self
    {
        $this->marketplaceId = $marketplaceId;

        return $this;
    }

    public function getBufferPercent(): ?float
    {
        return $this->bufferPercent;
    }

    public function setBufferPercent(?float $bufferPercent): self
    {
        $this->bufferPercent = $bufferPercent;

        return $this;
    }

    public function getLastSyncedQuantity(): int
    {
        return $this->lastSyncedQuantity;
    }

    public function setLastSyncedQuantity(int $lastSyncedQuantity): self
    {
        $this->lastSyncedQuantity = $lastSyncedQuantity;

        return $this;
    }

    public function getLastSyncDate(): ?string
    {
        return $this->lastSyncDate;
    }

    public function setLastSyncDate(?string $lastSyncDate): self
    {
        $this->lastSyncDate = $lastSyncDate;

        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): self
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    /**
     * The eBay listing price at the last import (gross, taxes included), used
     * as the base of the dynamic price rules.
     */
    public function getBasePrice(): float
    {
        return $this->basePrice;
    }

    public function setBasePrice(float $basePrice): self
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getPriceCurrency(): string
    {
        return $this->priceCurrency;
    }

    public function setPriceCurrency(string $priceCurrency): self
    {
        $this->priceCurrency = $priceCurrency;

        return $this;
    }

    /**
     * The price computed by the last dynamic price rule evaluation (NULL when
     * no rule matched or the base price is missing).
     */
    public function getTargetPrice(): ?float
    {
        return $this->targetPrice;
    }

    public function setTargetPrice(?float $targetPrice): self
    {
        $this->targetPrice = $targetPrice;

        return $this;
    }

    /**
     * The id of the price rule that produced the target price (NULL when none).
     */
    public function getIdPriceRule(): ?int
    {
        return $this->idPriceRule;
    }

    public function setIdPriceRule(?int $idPriceRule): self
    {
        $this->idPriceRule = $idPriceRule;

        return $this;
    }

    /**
     * Where the target price stands: "" (no rule), "pushed" (sent to eBay),
     * "not_pushable" (classic listing: eBay forbids API price changes),
     * "push_error" (the eBay push failed).
     */
    public function getPriceStatus(): string
    {
        return $this->priceStatus;
    }

    public function setPriceStatus(string $priceStatus): self
    {
        $this->priceStatus = $priceStatus;

        return $this;
    }

    public function getDateAdd(): ?string
    {
        return $this->dateAdd;
    }

    public function setDateAdd(?string $dateAdd): self
    {
        $this->dateAdd = $dateAdd;

        return $this;
    }

    public function getDateUpd(): ?string
    {
        return $this->dateUpd;
    }

    public function setDateUpd(?string $dateUpd): self
    {
        $this->dateUpd = $dateUpd;

        return $this;
    }

    /**
     * Hydrates the entity from a database row.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $entity = new self();

        if (isset($row['id_linkbay_product'])) {
            $entity->setId((int) $row['id_linkbay_product']);
        }
        if (isset($row['id_product'])) {
            $entity->setIdProduct((int) $row['id_product']);
        }
        if (isset($row['id_product_attribute'])) {
            $entity->setIdProductAttribute((int) $row['id_product_attribute']);
        }
        if (isset($row['ebay_item_id'])) {
            $entity->setEbayItemId((string) $row['ebay_item_id']);
        }
        if (isset($row['ebay_sku'])) {
            $entity->setEbaySku((string) $row['ebay_sku']);
        }
        if (isset($row['marketplace_id'])) {
            $entity->setMarketplaceId((string) $row['marketplace_id']);
        }
        if (array_key_exists('buffer_percent', $row)) {
            $entity->setBufferPercent($row['buffer_percent'] !== null ? (float) $row['buffer_percent'] : null);
        }
        if (isset($row['last_synced_quantity'])) {
            $entity->setLastSyncedQuantity((int) $row['last_synced_quantity']);
        }
        if (isset($row['last_sync_date'])) {
            $entity->setLastSyncDate($row['last_sync_date'] ? (string) $row['last_sync_date'] : null);
        }
        if (isset($row['sync_status'])) {
            $entity->setSyncStatus((string) $row['sync_status']);
        }
        if (array_key_exists('base_price', $row)) {
            $entity->setBasePrice((float) $row['base_price']);
        }
        if (isset($row['price_currency'])) {
            $entity->setPriceCurrency((string) $row['price_currency']);
        }
        if (array_key_exists('target_price', $row)) {
            $entity->setTargetPrice($row['target_price'] !== null ? (float) $row['target_price'] : null);
        }
        if (array_key_exists('id_price_rule', $row)) {
            $entity->setIdPriceRule($row['id_price_rule'] !== null ? (int) $row['id_price_rule'] : null);
        }
        if (isset($row['price_status'])) {
            $entity->setPriceStatus((string) $row['price_status']);
        }
        if (isset($row['date_add'])) {
            $entity->setDateAdd($row['date_add'] ? (string) $row['date_add'] : null);
        }
        if (isset($row['date_upd'])) {
            $entity->setDateUpd($row['date_upd'] ? (string) $row['date_upd'] : null);
        }

        return $entity;
    }

    /**
     * Converts the entity into a database row (column_name => value).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id_linkbay_product' => $this->id,
            'id_product' => $this->idProduct,
            'id_product_attribute' => $this->idProductAttribute,
            'ebay_item_id' => $this->ebayItemId,
            'ebay_sku' => $this->ebaySku,
            'marketplace_id' => $this->marketplaceId,
            'buffer_percent' => $this->bufferPercent,
            'last_synced_quantity' => $this->lastSyncedQuantity,
            'last_sync_date' => $this->lastSyncDate,
            'sync_status' => $this->syncStatus,
            'base_price' => $this->basePrice,
            'price_currency' => $this->priceCurrency,
            'target_price' => $this->targetPrice,
            'id_price_rule' => $this->idPriceRule,
            'price_status' => $this->priceStatus,
            'date_add' => $this->dateAdd,
            'date_upd' => $this->dateUpd,
        ];
    }
}
