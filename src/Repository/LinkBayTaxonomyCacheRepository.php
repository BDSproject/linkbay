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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Small key-value cache for the eBay taxonomy (category lists and category
 * metadata).
 *
 * The taxonomy changes very rarely, so caching it locally makes the import
 * page instant instead of requiring one API round-trip per category level.
 */
final class LinkBayTaxonomyCacheRepository
{
    /** Default entry lifetime: 7 days. */
    public const TTL = 604800;

    private function table(): string
    {
        return '`' . _DB_PREFIX_ . 'linkbay_taxonomy_cache`';
    }

    /**
     * Returns the cached value when present and fresh enough, or null.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key, ?int $ttl = null): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT `value`, `date_add` FROM ' . $this->table() . ' WHERE `cache_key` = "' . pSQL($key) . '"'
        );

        if (!is_array($row)) {
            return null;
        }

        $ttl = $ttl ?? self::TTL;
        if ((int) strtotime((string) $row['date_add']) + $ttl < time()) {
            \Db::getInstance()->delete('linkbay_taxonomy_cache', '`cache_key` = "' . pSQL($key) . '"');

            return null;
        }

        $value = json_decode((string) $row['value'], true);

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value): bool
    {
        $json = json_encode($value);
        if ($json === false) {
            return false;
        }

        return (bool) \Db::getInstance()->execute(
            'INSERT INTO ' . $this->table() . ' (`cache_key`, `value`, `date_add`)
             VALUES ("' . pSQL($key) . '", "' . pSQL($json) . '", "' . pSQL(date('Y-m-d H:i:s')) . '")
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `date_add` = VALUES(`date_add`)'
        );
    }

    /**
     * Deletes entries older than twice the TTL (housekeeping).
     */
    public function prune(): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::TTL * 2);

        return (int) \Db::getInstance()->execute(
            'DELETE FROM ' . $this->table() . ' WHERE `date_add` < "' . pSQL($cutoff) . '"'
        );
    }
}
