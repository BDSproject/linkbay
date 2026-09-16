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

use LinkBay\Entity\PriceRule;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Persistence layer for the "linkbay_price_rule" table.
 */
final class LinkBayPriceRuleRepository
{
    private function table(): string
    {
        return '`' . _DB_PREFIX_ . 'linkbay_price_rule`';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function selectRows(string $sql): array
    {
        $rows = \Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns every rule, lowest priority first.
     *
     * @return PriceRule[]
     */
    public function findAll(): array
    {
        $rows = $this->selectRows(
            'SELECT * FROM ' . $this->table() . ' ORDER BY `priority` ASC, `id_linkbay_price_rule` ASC'
        );

        return array_map(static fn (array $row): PriceRule => PriceRule::fromArray($row), $rows);
    }

    /**
     * Returns the active rules, lowest priority first.
     *
     * @return PriceRule[]
     */
    public function findActive(): array
    {
        $rows = $this->selectRows(
            'SELECT * FROM ' . $this->table() . '
            WHERE `active` = 1
            ORDER BY `priority` ASC, `id_linkbay_price_rule` ASC'
        );

        return array_map(static fn (array $row): PriceRule => PriceRule::fromArray($row), $rows);
    }

    public function findById(int $id): ?PriceRule
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM ' . $this->table() . ' WHERE `id_linkbay_price_rule` = ' . (int) $id
        );
        if (!is_array($row)) {
            return null;
        }

        return PriceRule::fromArray($row);
    }

    public function save(PriceRule $rule): bool
    {
        $db = \Db::getInstance();
        $now = date('Y-m-d H:i:s');

        if ($rule->getId() !== null) {
            return $db->execute(
                'UPDATE ' . $this->table() . ' SET
                    `name` = "' . pSQL($rule->getName()) . '",
                    `active` = ' . (int) $rule->isActive() . ',
                    `min_stock` = ' . $this->quoteNullableInt($rule->getMinStock()) . ',
                    `max_stock` = ' . $this->quoteNullableInt($rule->getMaxStock()) . ',
                    `percent` = ' . (float) $rule->getPercent() . ',
                    `priority` = ' . (int) $rule->getPriority() . ',
                    `date_upd` = "' . pSQL($now) . '"
                WHERE `id_linkbay_price_rule` = ' . (int) $rule->getId()
            );
        }

        $inserted = $db->execute(
            'INSERT INTO ' . $this->table() . ' (
                `name`, `active`, `min_stock`, `max_stock`, `percent`, `priority`, `date_add`, `date_upd`
            ) VALUES (
                "' . pSQL($rule->getName()) . '",
                ' . (int) $rule->isActive() . ',
                ' . $this->quoteNullableInt($rule->getMinStock()) . ',
                ' . $this->quoteNullableInt($rule->getMaxStock()) . ',
                ' . (float) $rule->getPercent() . ',
                ' . (int) $rule->getPriority() . ',
                "' . pSQL($now) . '",
                "' . pSQL($now) . '"
            )'
        );

        if ($inserted) {
            $rule->setId((int) $db->Insert_ID());
        }

        return (bool) $inserted;
    }

    public function deleteById(int $id): bool
    {
        return (bool) \Db::getInstance()->delete(
            'linkbay_price_rule',
            '`id_linkbay_price_rule` = ' . (int) $id
        );
    }

    public function setActive(int $id, bool $active): bool
    {
        return (bool) \Db::getInstance()->execute(
            'UPDATE ' . $this->table() . ' SET `active` = ' . (int) $active . '
            WHERE `id_linkbay_price_rule` = ' . (int) $id
        );
    }

    /**
     * Returns a priority value after the current maximum (used for new rules).
     */
    public function nextPriority(): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(MAX(`priority`), 0) + 1 FROM ' . $this->table()
        );
    }

    private function quoteNullableInt(?int $value): string
    {
        return $value !== null ? (string) $value : 'NULL';
    }
}
