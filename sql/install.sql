--
-- LinkBay module schema.
--
-- Placeholders are replaced at install time:
--   _DB_PREFIX_   -> PrestaShop table prefix
--   _MYSQL_ENGINE_ -> PrestaShop configured MySQL engine
--
-- Only module specific tables are created; no core PrestaShop table is touched.

CREATE TABLE IF NOT EXISTS `_DB_PREFIX_linkbay_product_link` (
    `id_linkbay_product` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(10) UNSIGNED NOT NULL,
    `id_product_attribute` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `ebay_item_id` VARCHAR(64) NOT NULL,
    `ebay_sku` VARCHAR(64) NOT NULL DEFAULT '',
    `marketplace_id` VARCHAR(16) NOT NULL DEFAULT '',
    `buffer_percent` DECIMAL(5,2) DEFAULT NULL,
    `last_synced_quantity` INT(11) NOT NULL DEFAULT 0,
    `last_sync_date` DATETIME DEFAULT NULL,
    `sync_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `base_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
    `price_currency` VARCHAR(3) NOT NULL DEFAULT '',
    `target_price` DECIMAL(20,6) DEFAULT NULL,
    `id_price_rule` INT(10) UNSIGNED DEFAULT NULL,
    `price_status` VARCHAR(32) NOT NULL DEFAULT '',
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_linkbay_product`),
    UNIQUE KEY `ebay_item_id` (`ebay_item_id`, `id_product_attribute`),
    KEY `id_product` (`id_product`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=UTF8;

CREATE TABLE IF NOT EXISTS `_DB_PREFIX_linkbay_price_rule` (
    `id_linkbay_price_rule` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(128) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `min_stock` INT(11) DEFAULT NULL,
    `max_stock` INT(11) DEFAULT NULL,
    `percent` DECIMAL(6,2) NOT NULL DEFAULT 0,
    `priority` INT(11) NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_linkbay_price_rule`),
    KEY `active` (`active`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=UTF8;

CREATE TABLE IF NOT EXISTS `_DB_PREFIX_linkbay_taxonomy_cache` (
    `cache_key` VARCHAR(96) NOT NULL,
    `value` TEXT NOT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`cache_key`),
    KEY `date_add` (`date_add`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=UTF8;

CREATE TABLE IF NOT EXISTS `_DB_PREFIX_linkbay_log` (
    `id_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_product` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `ebay_item_id` VARCHAR(64) NOT NULL DEFAULT '',
    `action` VARCHAR(64) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'success',
    `message` TEXT,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `id_product` (`id_product`),
    KEY `action` (`action`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=UTF8;

CREATE TABLE IF NOT EXISTS `_DB_PREFIX_linkbay_category_map` (
    `id_category_map` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `ebay_category_id` VARCHAR(64) NOT NULL,
    `id_category` INT(10) UNSIGNED NOT NULL,
    PRIMARY KEY (`id_category_map`),
    UNIQUE KEY `ebay_category_id` (`ebay_category_id`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=UTF8;
