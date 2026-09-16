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

use LinkBay\Entity\LinkBayLog;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Persistence layer for the "linkbay_log" table.
 */
final class LinkBayLogRepository
{
    private function table(): string
    {
        return '`' . _DB_PREFIX_ . 'linkbay_log`';
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
     * Inserts a log entry.
     */
    public function add(LinkBayLog $entity): bool
    {
        $now = date('Y-m-d H:i:s');
        $entity->setDateAdd($now);

        $inserted = \Db::getInstance()->execute(
            'INSERT INTO ' . $this->table() . ' (
                `id_product`, `ebay_item_id`, `action`, `status`, `message`, `date_add`
            ) VALUES (
                ' . (int) $entity->getIdProduct() . ',
                "' . pSQL($entity->getEbayItemId()) . '",
                "' . pSQL($entity->getAction()) . '",
                "' . pSQL($entity->getStatus()) . '",
                "' . pSQL($entity->getMessage()) . '",
                "' . pSQL($now) . '"
            )'
        );

        if ($inserted) {
            $entity->setId((int) \Db::getInstance()->Insert_ID());
        }

        return (bool) $inserted;
    }

    /**
     * Returns the most recent log entries.
     *
     * @return LinkBayLog[]
     */
    public function getRecent(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $rows = $this->selectRows(
            'SELECT * FROM ' . $this->table() . ' ORDER BY `id_log` DESC LIMIT ' . (int) $limit
        );

        return array_map(static fn (array $row): LinkBayLog => LinkBayLog::fromArray($row), $rows);
    }

    public function count(): int
    {
        return (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . $this->table());
    }

    /**
     * Deletes every log entry.
     */
    public function clear(): bool
    {
        return (bool) \Db::getInstance()->execute('TRUNCATE TABLE ' . $this->table());
    }

    /**
     * Deletes the error entries of the given actions from the last 24 hours.
     * Used by the dashboard repairs: once a repair has re-run the action, the
     * stale errors must not keep the problem visible on the checklist.
     *
     * @param string[] $actions
     */
    public function deleteRecentErrors(array $actions): int
    {
        if (empty($actions)) {
            return 0;
        }

        $quoted = implode(',', array_map(static fn (string $action): string => '"' . pSQL($action) . '"', $actions));
        $cutoff = date('Y-m-d H:i:s', time() - 86400);

        return (int) \Db::getInstance()->execute(
            'DELETE FROM ' . $this->table() . '
            WHERE `action` IN (' . $quoted . ')
              AND `status` = "error"
              AND `date_add` > "' . pSQL($cutoff) . '"'
        );
    }
}
