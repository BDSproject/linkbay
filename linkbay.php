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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * LinkBay main module class.
 *
 * Connects PrestaShop to eBay: imports active listings and keeps stock
 * synchronized in both directions through a cron endpoint.
 */
class LinkBay extends Module
{
    public function __construct()
    {
        $this->name = 'linkbay';
        $this->tab = 'market_place';
        $this->version = '1.0.0';
        $this->author = 'Beassisted';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.8.0',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('LinkBay', [], 'Modules.Linkbay.Admin');
        $this->description = $this->trans(
            'Connects PrestaShop to eBay: import active listings and synchronize stock bidirectionally.',
            [],
            'Modules.Linkbay.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall this module? All LinkBay tables and settings will be removed.',
            [],
            'Modules.Linkbay.Admin'
        );

        $this->registerAutoloader();
    }

    /**
     * Installs the module and its dependencies.
     */
    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        return (new \LinkBay\Install\Installer($this))->install();
    }

    /**
     * Uninstalls the module and removes all its data.
     */
    public function uninstall(): bool
    {
        $removed = (new \LinkBay\Install\Installer($this))->uninstall();

        return $removed && parent::uninstall();
    }

    /**
     * Redirects the legacy configuration entry point to the dedicated controller.
     */
    public function getContent(): void
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLinkBay'));
    }

    /**
     * Injects the module assets into the back office header.
     *
     * @param array<string, mixed> $params
     *
     * @return string
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        return (new \LinkBay\Hook\HookHandler($this))->displayBackOfficeHeader($params);
    }

    /**
     * Reacts to product updates (potential immediate stock push to eBay).
     *
     * @param array<string, mixed> $params
     */
    public function hookActionObjectProductUpdateAfter($params): void
    {
        (new \LinkBay\Hook\HookHandler($this))->onProductUpdate($params);
    }

    /**
     * Reacts to product deletion (clean up mapping rows).
     *
     * @param array<string, mixed> $params
     */
    public function hookActionObjectProductDeleteAfter($params): void
    {
        (new \LinkBay\Hook\HookHandler($this))->onProductDelete($params);
    }

    /**
     * Reacts to combination updates (stock changes on combinations).
     *
     * @param array<string, mixed> $params
     */
    public function hookActionObjectCombinationUpdateAfter($params): void
    {
        (new \LinkBay\Hook\HookHandler($this))->onCombinationUpdate($params);
    }

    /**
     * Registers a PSR-4 autoloader for the "LinkBay" namespace.
     *
     * Prefers the Composer generated autoloader when present and falls back to a
     * lightweight file-based loader so the module works without running Composer.
     */
    private function registerAutoloader(): void
    {
        $composerAutoloader = __DIR__ . '/vendor/autoload.php';

        if (file_exists($composerAutoloader)) {
            require_once $composerAutoloader;

            return;
        }

        spl_autoload_register(static function (string $class): void {
            $prefix = 'LinkBay\\';

            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
