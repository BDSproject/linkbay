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

namespace LinkBay\Install;

use LinkBay\Config\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Handles module installation and uninstallation.
 */
final class Installer
{
    private const HOOKS = [
        'displayBackOfficeHeader',
        'actionObjectProductUpdateAfter',
        'actionObjectProductDeleteAfter',
        'actionObjectCombinationUpdateAfter',
    ];

    private const TABLES = [
        'linkbay_product_link',
        'linkbay_log',
        'linkbay_category_map',
        'linkbay_price_rule',
        'linkbay_taxonomy_cache',
    ];

    private \Module $module;

    public function __construct(\Module $module)
    {
        $this->module = $module;
    }

    public function install(): bool
    {
        return $this->createTables()
            && $this->registerHooks()
            && $this->setDefaults()
            && $this->installTabs();
    }

    public function uninstall(): bool
    {
        return $this->dropTables()
            && $this->removeConfiguration()
            && $this->uninstallTabs();
    }

    /**
     * Creates the module tables from sql/install.sql.
     */
    private function createTables(): bool
    {
        $sqlFile = __DIR__ . '/../../sql/install.sql';
        $sql = file_get_contents($sqlFile);

        if ($sql === false) {
            return false;
        }

        $sql = str_replace(['_DB_PREFIX_', '_MYSQL_ENGINE_'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);

        $db = \Db::getInstance();

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if (!$db->execute($statement)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drops all module tables.
     */
    private function dropTables(): bool
    {
        $db = \Db::getInstance();

        foreach (self::TABLES as $table) {
            $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`');
        }

        return true;
    }

    private function registerHooks(): bool
    {
        foreach (self::HOOKS as $hook) {
            if (!$this->module->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes default configuration values and generates a cron token.
     */
    private function setDefaults(): bool
    {
        foreach (Config::DEFAULTS as $key => $value) {
            \Configuration::updateValue($key, $value);
        }

        if (!\Configuration::get(Config::CRON_TOKEN)) {
            \Configuration::updateValue(Config::CRON_TOKEN, \Tools::passwdGen(32));
        }

        if (!\Configuration::get(Config::WEBHOOK_TOKEN)) {
            \Configuration::updateValue(Config::WEBHOOK_TOKEN, \Tools::passwdGen(32));
        }

        return true;
    }

    /**
     * Creates the back office menu entries.
     */
    private function installTabs(): bool
    {
        $parentId = $this->installTab('AdminLinkBay', 'LinkBay', 0);
        $this->installTab('AdminLinkBayDashboard', 'Dashboard', $parentId);
        $this->installTab('AdminLinkBayImport', 'Import', $parentId);

        return true;
    }

    private function installTab(string $className, string $name, int $idParent): int
    {
        $idTab = (int) \Tab::getIdFromClassName($className);
        $tab = new \Tab($idTab);
        $tab->active = true;
        $tab->class_name = $className;
        $tab->module = $this->module->name;
        $tab->id_parent = $idParent;
        $tab->name = [];

        foreach (\Language::getLanguages(false) as $language) {
            if (!is_array($language)) {
                continue;
            }

            $iso = strtolower((string) ($language['iso_code'] ?? ''));
            $tabName = $name;

            if ($name === 'Dashboard') {
                $tabName = [
                    'fr' => 'Tableau de bord',
                    'de' => 'Übersicht',
                    'es' => 'Panel',
                    'it' => 'Pannello',
                ][$iso] ?? 'Dashboard';
            }

            $tab->name[(int) $language['id_lang']] = $tabName;
        }

        $tab->save();

        return (int) $tab->id;
    }

    private function uninstallTabs(): bool
    {
        foreach (['AdminLinkBayDashboard', 'AdminLinkBayImport', 'AdminLinkBay'] as $className) {
            $idTab = (int) \Tab::getIdFromClassName($className);

            if ($idTab > 0) {
                $tab = new \Tab($idTab);
                $tab->delete();
            }
        }

        return true;
    }

    private function removeConfiguration(): bool
    {
        foreach (Config::ALL_KEYS as $key) {
            \Configuration::deleteByName($key);
        }

        return true;
    }
}
