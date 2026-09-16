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

use LinkBay\Entity\LinkBayCategoryMap;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Persistence layer for the "linkbay_category_map" table.
 */
final class LinkBayCategoryMapRepository
{
    private function table(): string
    {
        return '`' . _DB_PREFIX_ . 'linkbay_category_map`';
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

    public function save(LinkBayCategoryMap $entity): bool
    {
        $db = \Db::getInstance();

        if ($entity->getId() !== null) {
            return (bool) $db->execute(
                'UPDATE ' . $this->table() . ' SET
                    `ebay_category_id` = "' . pSQL($entity->getEbayCategoryId()) . '",
                    `id_category` = ' . (int) $entity->getIdCategory() . '
                WHERE `id_category_map` = ' . (int) $entity->getId()
            );
        }

        $inserted = $db->execute(
            'INSERT INTO ' . $this->table() . ' (`ebay_category_id`, `id_category`)
            VALUES ("' . pSQL($entity->getEbayCategoryId()) . '", ' . (int) $entity->getIdCategory() . ')'
        );

        if ($inserted) {
            $entity->setId((int) $db->Insert_ID());
        }

        return (bool) $inserted;
    }

    public function findById(int $id): ?LinkBayCategoryMap
    {
        $row = $this->selectRow(
            'SELECT * FROM ' . $this->table() . ' WHERE `id_category_map` = ' . (int) $id
        );

        return $row ? LinkBayCategoryMap::fromArray($row) : null;
    }

    public function findByEbayCategoryId(string $ebayCategoryId): ?LinkBayCategoryMap
    {
        $row = $this->selectRow(
            'SELECT * FROM ' . $this->table() . ' WHERE `ebay_category_id` = "' . pSQL($ebayCategoryId) . '"'
        );

        return $row ? LinkBayCategoryMap::fromArray($row) : null;
    }

    /**
     * @return LinkBayCategoryMap[]
     */
    public function findAll(): array
    {
        $rows = $this->selectRows('SELECT * FROM ' . $this->table() . ' ORDER BY `ebay_category_id` ASC');

        return array_map(static fn (array $row): LinkBayCategoryMap => LinkBayCategoryMap::fromArray($row), $rows);
    }

    public function deleteById(int $id): bool
    {
        return (bool) \Db::getInstance()->delete(
            'linkbay_category_map',
            '`id_category_map` = ' . (int) $id
        );
    }
}
