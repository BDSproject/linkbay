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
 * Maps an eBay category id to a PrestaShop category id.
 */
final class LinkBayCategoryMap
{
    private ?int $id = null;
    private string $ebayCategoryId = '';
    private int $idCategory = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEbayCategoryId(): string
    {
        return $this->ebayCategoryId;
    }

    public function setEbayCategoryId(string $ebayCategoryId): self
    {
        $this->ebayCategoryId = $ebayCategoryId;

        return $this;
    }

    public function getIdCategory(): int
    {
        return $this->idCategory;
    }

    public function setIdCategory(int $idCategory): self
    {
        $this->idCategory = $idCategory;

        return $this;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $entity = new self();

        if (isset($row['id_category_map'])) {
            $entity->setId((int) $row['id_category_map']);
        }
        if (isset($row['ebay_category_id'])) {
            $entity->setEbayCategoryId((string) $row['ebay_category_id']);
        }
        if (isset($row['id_category'])) {
            $entity->setIdCategory((int) $row['id_category']);
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id_category_map' => $this->id,
            'ebay_category_id' => $this->ebayCategoryId,
            'id_category' => $this->idCategory,
        ];
    }
}
