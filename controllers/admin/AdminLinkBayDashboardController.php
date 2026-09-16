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

use LinkBay\Repository\LinkBayCategoryMapRepository;
use LinkBay\Repository\LinkBayLogRepository;
use LinkBay\Repository\LinkBayPriceRuleRepository;
use LinkBay\Repository\LinkBayProductRepository;
use LinkBay\Service\EbayApiClient;
use LinkBay\Service\EbayProductMapper;
use LinkBay\Service\ImportManager;
use LinkBay\Service\PriceRuleEngine;
use LinkBay\Service\ProblemDetector;
use LinkBay\Service\StockSynchronizer;
use LinkBay\Service\TokenStorage;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * LinkBay visual dashboard: a checklist of the problems to fix, each with
 * a "repair automatically" action.
 */
class AdminLinkBayDashboardController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('fix_import')) {
            $this->handleFixImport();
        } elseif (Tools::isSubmit('fix_sync')) {
            $this->handleFixSync();
        } elseif (Tools::isSubmit('fix_orphans')) {
            $this->handleFixOrphans();
        } elseif (Tools::isSubmit('fix_inactive')) {
            $this->handleFixInactive();
        } elseif (Tools::isSubmit('fix_price')) {
            $this->handleFixPrice();
        }
    }

    public function initContent(): void
    {
        $detector = new ProblemDetector(
            new EbayApiClient(new TokenStorage()),
            new LinkBayProductRepository()
        );
        $problems = $detector->detect();

        $this->content = $this->renderTemplate('dashboard.tpl', [
            'controller_link' => $this->context->link->getAdminLink('AdminLinkBayDashboard'),
            'config_link' => $this->context->link->getAdminLink('AdminLinkBay'),
            'import_link' => $this->context->link->getAdminLink('AdminLinkBayImport'),
            'csrf_token' => (string) Tools::getAdminTokenLite('AdminLinkBayDashboard'),
            'problems' => $problems,
            'problems_total' => count($problems),
            'has_problems' => !empty($problems),
            'labels' => $this->getLabels(),
        ]);

        parent::initContent();
    }

    /**
     * Retries the import of the listings that failed within the last 24 hours.
     */
    private function handleFixImport(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $itemIds = $this->detector()->failedImportItemIds();

        if (empty($itemIds)) {
            $this->confirmations[] = $this->translate('No failed listing to retry — everything is already imported.');

            return;
        }

        // Les anciennes erreurs sont effacées avant la relance : ce qui échoue
        // encore réécrira de nouvelles erreurs, visibles sur la checklist.
        (new LinkBayLogRepository())->deleteRecentErrors(['import', 'image']);

        $summary = $this->importManager()->manualImport($itemIds);

        $message = $this->translate(
            'Retry finished: %imported% listing(s) imported, %updated% updated, %skipped% still failing.',
            [
                '%imported%' => (int) ($summary['imported'] ?? 0),
                '%updated%' => (int) ($summary['updated'] ?? 0),
                '%skipped%' => (int) ($summary['skipped'] ?? 0),
            ]
        );

        if ((int) ($summary['skipped'] ?? 0) > 0) {
            $this->errors[] = $message . ' ' . $this->translate('Check the logs on the configuration page for details.');
        } else {
            $this->confirmations[] = $message;
        }
    }

    /**
     * Runs the stock synchronization again (both directions).
     */
    private function handleFixSync(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        // Les anciennes erreurs sont effacées avant la relance : ce qui échoue
        // encore réécrira de nouvelles erreurs, visibles sur la checklist.
        (new LinkBayLogRepository())->deleteRecentErrors(['sync_ebay_to_ps', 'sync_ps_to_ebay']);

        $synchronizer = $this->synchronizer();
        $pull = $synchronizer->syncEbayToPrestashop();
        $push = $synchronizer->syncPrestashopToEbay();

        $pullErrors = (int) ($pull['errors'] ?? 0);
        $pushErrors = (int) ($push['errors'] ?? 0);

        $message = $this->translate(
            'Synchronization finished: %pulled% stock value(s) pulled from eBay, %pushed% pushed to eBay.',
            [
                '%pulled%' => (int) ($pull['synced'] ?? 0),
                '%pushed%' => (int) ($push['pushed'] ?? 0),
            ]
        );

        if ($pullErrors > 0 || $pushErrors > 0) {
            $this->errors[] = $message . ' ' . $this->translate(
                '%count% error(s) remain — check the logs on the configuration page for details.',
                ['%count%' => $pullErrors + $pushErrors]
            );
        } else {
            $this->confirmations[] = $message;
        }
    }

    /**
     * Removes the PrestaShop combinations that no longer exist on eBay.
     */
    private function handleFixOrphans(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $orphans = $this->detector()->orphanCombinationLinks();

        if (empty($orphans)) {
            $this->confirmations[] = $this->translate('No obsolete combination to remove — everything is up to date.');

            return;
        }

        $repository = new LinkBayProductRepository();
        $removed = 0;

        foreach ($orphans as $link) {
            try {
                $idProduct = $link->getIdProduct();
                $idCombination = $link->getIdProductAttribute();

                // Supprime la ligne de stock puis la combinaison elle-même.
                \StockAvailable::removeProductFromStockAvailable($idProduct, $idCombination);

                $combination = new \Combination($idCombination);
                if (\Validate::isLoadedObject($combination)) {
                    $combination->delete();
                }

                $repository->deleteById((int) $link->getId());
                ++$removed;
            } catch (\Throwable $e) {
                $this->errors[] = $this->translate(
                    'Failed to remove combination #%id%: %message%',
                    ['%id%' => $link->getIdProductAttribute(), '%message%' => $e->getMessage()]
                );
            }
        }

        $this->confirmations[] = $this->translate(
            '%count% obsolete combination(s) removed from the shop. Run an import if the eBay listing gained new variations.',
            ['%count%' => $removed]
        );
    }

    /**
     * Reactivates the products that the module had deactivated.
     */
    private function handleFixInactive(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $links = $this->detector()->inactiveProductLinks();

        if (empty($links)) {
            $this->confirmations[] = $this->translate('No deactivated product to reactivate.');

            return;
        }

        $reactivated = 0;

        foreach ($links as $link) {
            $product = new \Product($link->getIdProduct());
            if (\Validate::isLoadedObject($product)) {
                $product->active = true;
                $product->update();
                ++$reactivated;
            }
        }

        $this->confirmations[] = $this->translate(
            '%count% product(s) reactivated. If their eBay listing no longer exists, the next import will deactivate them again.',
            ['%count%' => $reactivated]
        );
    }

    /**
     * Re-runs the dynamic price rules.
     */
    private function handleFixPrice(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        // Les anciennes erreurs sont effacées avant la relance : ce qui échoue
        // encore réécrira de nouvelles erreurs, visibles sur la checklist.
        (new LinkBayLogRepository())->deleteRecentErrors(['price_rule']);

        $engine = new PriceRuleEngine(
            new EbayApiClient(new TokenStorage()),
            new LinkBayProductRepository(),
            new LinkBayPriceRuleRepository(),
            new LinkBayLogRepository()
        );

        try {
            $summary = $engine->apply();
        } catch (\Throwable $e) {
            $this->errors[] = $this->translate('Price rules failed: %message%', ['%message%' => $e->getMessage()]);

            return;
        }

        $this->confirmations[] = $this->translate(
            'Price rules applied: %evaluated% product(s) evaluated, %matched% matched, %pushed% pushed to eBay, %not_pushable% classic listing(s), %errors% error(s).',
            [
                '%evaluated%' => (int) ($summary['evaluated'] ?? 0),
                '%matched%' => (int) ($summary['matched'] ?? 0),
                '%pushed%' => (int) ($summary['pushed'] ?? 0),
                '%not_pushable%' => (int) ($summary['not_pushable'] ?? 0),
                '%errors%' => (int) ($summary['errors'] ?? 0),
            ]
        );
    }

    private function detector(): ProblemDetector
    {
        return new ProblemDetector(
            new EbayApiClient(new TokenStorage()),
            new LinkBayProductRepository()
        );
    }

    private function importManager(): ImportManager
    {
        return new ImportManager(
            new EbayApiClient(new TokenStorage()),
            new EbayProductMapper(),
            new LinkBayProductRepository(),
            new LinkBayLogRepository(),
            new LinkBayCategoryMapRepository()
        );
    }

    private function synchronizer(): StockSynchronizer
    {
        return new StockSynchronizer(
            new EbayApiClient(new TokenStorage()),
            new EbayProductMapper(),
            new LinkBayProductRepository(),
            new LinkBayLogRepository()
        );
    }

    private function checkCsrf(): bool
    {
        $token = (string) Tools::getValue('linkbay_token', '');

        if ($token !== '' && hash_equals((string) Tools::getAdminTokenLite('AdminLinkBayDashboard'), $token)) {
            return true;
        }

        $this->errors[] = $this->translate('Invalid security token.');

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function getLabels(): array
    {
        return [
            'title' => $this->translate('LinkBay dashboard'),
            'intro' => $this->translate('Here is the list of what needs your attention. Fix each item with one click.'),
            'all_good_title' => $this->translate('Everything is synchronized'),
            'all_good_text' => $this->translate('No problem to fix: your eBay listings are imported and the stock is synchronized.'),
            'problems_count' => $this->translate('%count% problem(s) to fix'),
            'repair' => $this->translate('Repair automatically'),
            'configure' => $this->translate('Open the configuration'),
            'import_now' => $this->translate('Import listings'),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function translate(string $id, array $parameters = []): string
    {
        return parent::trans($id, $parameters, 'Modules.Linkbay.Admin');
    }

    /**
     * Renders a module Smarty template.
     *
     * @param array<string, mixed> $params
     */
    private function renderTemplate(string $template, array $params = []): string
    {
        $this->context->smarty->assign($params);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'linkbay/views/templates/admin/' . $template);
    }
}
