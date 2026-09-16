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
 * A single LinkBay action log entry.
 */
final class LinkBayLog
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_INFO = 'info';

    private ?int $id = null;
    private int $idProduct = 0;
    private string $ebayItemId = '';
    private string $action = '';
    private string $status = self::STATUS_SUCCESS;
    private string $message = '';
    private ?string $dateAdd = null;

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

    public function getEbayItemId(): string
    {
        return $this->ebayItemId;
    }

    public function setEbayItemId(string $ebayItemId): self
    {
        $this->ebayItemId = $ebayItemId;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

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

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $entity = new self();

        if (isset($row['id_log'])) {
            $entity->setId((int) $row['id_log']);
        }
        if (isset($row['id_product'])) {
            $entity->setIdProduct((int) $row['id_product']);
        }
        if (isset($row['ebay_item_id'])) {
            $entity->setEbayItemId((string) $row['ebay_item_id']);
        }
        if (isset($row['action'])) {
            $entity->setAction((string) $row['action']);
        }
        if (isset($row['status'])) {
            $entity->setStatus((string) $row['status']);
        }
        if (isset($row['message'])) {
            $entity->setMessage((string) $row['message']);
        }
        if (isset($row['date_add'])) {
            $entity->setDateAdd($row['date_add'] ? (string) $row['date_add'] : null);
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id_log' => $this->id,
            'id_product' => $this->idProduct,
            'ebay_item_id' => $this->ebayItemId,
            'action' => $this->action,
            'status' => $this->status,
            'message' => $this->message,
            'date_add' => $this->dateAdd,
        ];
    }
}
