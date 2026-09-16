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
 * A competitive price rule: when the current stock matches the condition
 * (stock > min_stock and stock < max_stock), the eBay price is adjusted by
 * "percent" (negative to lower the price, positive to raise it).
 */
final class PriceRule
{
    private ?int $id = null;
    private string $name = '';
    private bool $active = true;
    private ?int $minStock = null;
    private ?int $maxStock = null;
    private float $percent = 0.0;
    private int $priority = 0;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    /**
     * The rule matches when the stock is strictly above this value (NULL = no lower bound).
     */
    public function getMinStock(): ?int
    {
        return $this->minStock;
    }

    public function setMinStock(?int $minStock): self
    {
        $this->minStock = $minStock;

        return $this;
    }

    /**
     * The rule matches when the stock is strictly below this value (NULL = no upper bound).
     */
    public function getMaxStock(): ?int
    {
        return $this->maxStock;
    }

    public function setMaxStock(?int $maxStock): self
    {
        $this->maxStock = $maxStock;

        return $this;
    }

    /**
     * Price adjustment in percent; negative lowers the price, positive raises it.
     */
    public function getPercent(): float
    {
        return $this->percent;
    }

    public function setPercent(float $percent): self
    {
        $this->percent = $percent;

        return $this;
    }

    /**
     * Evaluation order: lower values are evaluated first.
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

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
     * Whether the rule matches a given stock quantity.
     */
    public function matches(int $stock): bool
    {
        if ($this->minStock !== null && $stock <= $this->minStock) {
            return false;
        }

        if ($this->maxStock !== null && $stock >= $this->maxStock) {
            return false;
        }

        return true;
    }

    /**
     * Human readable condition, e.g. "stock > 50" or "3 < stock < 50".
     */
    public function conditionLabel(): string
    {
        if ($this->minStock !== null && $this->maxStock !== null) {
            return 'stock > ' . $this->minStock . ' and stock < ' . $this->maxStock;
        }
        if ($this->minStock !== null) {
            return 'stock > ' . $this->minStock;
        }
        if ($this->maxStock !== null) {
            return 'stock < ' . $this->maxStock;
        }

        return 'always';
    }

    /**
     * Hydrates the entity from a database row.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $entity = new self();

        if (isset($row['id_linkbay_price_rule'])) {
            $entity->setId((int) $row['id_linkbay_price_rule']);
        }
        if (isset($row['name'])) {
            $entity->setName((string) $row['name']);
        }
        if (isset($row['active'])) {
            $entity->setActive((bool) $row['active']);
        }
        if (array_key_exists('min_stock', $row)) {
            $entity->setMinStock($row['min_stock'] !== null ? (int) $row['min_stock'] : null);
        }
        if (array_key_exists('max_stock', $row)) {
            $entity->setMaxStock($row['max_stock'] !== null ? (int) $row['max_stock'] : null);
        }
        if (isset($row['percent'])) {
            $entity->setPercent((float) $row['percent']);
        }
        if (isset($row['priority'])) {
            $entity->setPriority((int) $row['priority']);
        }
        if (isset($row['date_add'])) {
            $entity->setDateAdd($row['date_add'] ? (string) $row['date_add'] : null);
        }
        if (isset($row['date_upd'])) {
            $entity->setDateUpd($row['date_upd'] ? (string) $row['date_upd'] : null);
        }

        return $entity;
    }
}
