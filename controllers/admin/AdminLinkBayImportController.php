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
use LinkBay\Repository\LinkBayProductRepository;
use LinkBay\Service\EbayApiClient;
use LinkBay\Service\EbayProductMapper;
use LinkBay\Service\ImportManager;
use LinkBay\Service\TokenStorage;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * LinkBay manual import page: browse eBay categories (dropdown) and select listings.
 *
 * The page stays light: the taxonomy comes from a local cache and the listings
 * are only fetched when the merchant actually selects a category.
 */
class AdminLinkBayImportController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function postProcess(): void
    {
        if (Tools::isSubmit('submitLinkBayImport')) {
            $this->handleImport();
        }
    }

    public function initContent(): void
    {
        $this->content = $this->renderTemplate('import.tpl', $this->getTemplateData());

        parent::initContent();
    }

    private function handleImport(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $itemIds = Tools::getValue('item_ids', []);
        if (!is_array($itemIds)) {
            $itemIds = [];
        }

        $itemIds = array_values(array_filter(array_map('strval', $itemIds), static fn (string $id): bool => $id !== ''));

        if (empty($itemIds)) {
            $this->errors[] = $this->translate('Please select at least one listing to import.');

            return;
        }

        $manager = $this->buildImportManager();

        try {
            $summary = $manager->manualImport($itemIds);
            $this->confirmations[] = $this->translate(
                'Import finished: %imported% created, %updated% updated, %skipped% skipped.',
                [
                    '%imported%' => (int) ($summary['imported'] ?? 0),
                    '%updated%' => (int) ($summary['updated'] ?? 0),
                    '%skipped%' => (int) ($summary['skipped'] ?? 0),
                ]
            );
        } catch (\Throwable $e) {
            $this->errors[] = $this->translate('Import failed: %message%', ['%message%' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getTemplateData(): array
    {
        $client = new EbayApiClient(new TokenStorage());

        if (!$client->isAuthenticated()) {
            return $this->emptyTemplateData();
        }

        // "all" = toutes les annonces (action explicite, lourde) ;
        // un nombre = navigation dans une catégorie ; vide = vue légère par défaut.
        $categoryParam = (string) Tools::getValue('category', '');
        $showAll = $categoryParam === 'all';
        $currentCategory = !$showAll && $categoryParam !== '' && ctype_digit($categoryParam)
            ? (int) $categoryParam
            : -1;

        $categories = [];
        $listings = [];
        $parentCategory = -1;
        $currentCategoryName = '';
        $error = '';

        try {
            // Les catégories viennent du cache local : aucun appel API après
            // le premier chargement de chaque niveau.
            $categories = $this->loadCategories($client, $currentCategory);

            if ($showAll) {
                $listings = $this->loadAllListings($client);
            } elseif ($currentCategory > 0) {
                $listings = $this->loadListings($client, $currentCategory);
                $info = $client->getCategoryInfo((string) $currentCategory);
                $parentCategory = (int) ($info['Category']['CategoryParentID'] ?? -1);
                $currentCategoryName = (string) ($info['Category']['CategoryName'] ?? '');
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'is_connected' => true,
            'error' => $error,
            'current_category' => $currentCategory,
            'show_all' => $showAll,
            'current_category_name' => $currentCategoryName,
            'parent_category' => $parentCategory,
            'categories' => $categories,
            'listings' => $listings,
            'controller_link' => $this->context->link->getAdminLink('AdminLinkBayImport'),
            'csrf_token' => (string) Tools::getAdminTokenLite('AdminLinkBayImport'),
            'labels' => $this->getLabels(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTemplateData(): array
    {
        return [
            'is_connected' => false,
            'error' => $this->translate('Please connect LinkBay to eBay on the configuration page first.'),
            'current_category' => -1,
            'show_all' => false,
            'current_category_name' => '',
            'parent_category' => -1,
            'categories' => [],
            'listings' => [],
            'controller_link' => $this->context->link->getAdminLink('AdminLinkBayImport'),
            'csrf_token' => (string) Tools::getAdminTokenLite('AdminLinkBayImport'),
            'labels' => $this->getLabels(),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, leaf: bool, parent: int}>
     */
    private function loadCategories(EbayApiClient $client, int $parent): array
    {
        $categories = $client->getCategories($parent, 1);

        return array_map(static fn (array $category): array => [
            'id' => (string) ($category['CategoryID'] ?? ''),
            'name' => (string) ($category['CategoryName'] ?? ''),
            'leaf' => (bool) ($category['LeafCategory'] ?? false),
            'parent' => (int) ($category['CategoryParentID'] ?? -1),
        ], $categories);
    }

    /**
     * Loads every active listing (explicit "all categories" action).
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadAllListings(EbayApiClient $client): array
    {
        $listings = [];

        foreach ($client->getActiveListings() as $item) {
            $listings[] = $this->normalizeListing($item);
        }

        return $listings;
    }

    /**
     * Loads only the listings published in the selected category.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadListings(EbayApiClient $client, int $category): array
    {
        $listings = [];

        foreach ($client->getListingsInCategory((string) $category) as $item) {
            $listings[] = $this->normalizeListing($item);
        }

        return $listings;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function normalizeListing(array $item): array
    {
        return [
            'item_id' => (string) ($item['item_id'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'sku' => (string) ($item['sku'] ?? ''),
            'quantity' => (int) ($item['quantity'] ?? 0),
            'price' => (string) ($item['price'] ?? '0'),
            'category_id' => (string) ($item['category_id'] ?? ''),
            // Le détail eBay contient déjà le chemin de catégorie : aucun
            // appel API supplémentaire n'est nécessaire.
            'category_name' => (string) ($item['category_name'] ?? ''),
            'marketplace_id' => (string) ($item['marketplace_id'] ?? ''),
        ];
    }

    private function buildImportManager(): ImportManager
    {
        $tokenStorage = new TokenStorage();

        return new ImportManager(
            new EbayApiClient($tokenStorage),
            new EbayProductMapper(),
            new LinkBayProductRepository(),
            new LinkBayLogRepository(),
            new LinkBayCategoryMapRepository()
        );
    }

    private function checkCsrf(): bool
    {
        $token = (string) Tools::getValue('linkbay_token', '');

        if ($token !== '' && hash_equals((string) Tools::getAdminTokenLite('AdminLinkBayImport'), $token)) {
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
            'title' => $this->translate('Manual import'),
            'categories' => $this->translate('eBay categories'),
            'choose_category' => $this->translate('Choose a category…'),
            'choose_category_hint' => $this->translate('Select a category in the dropdown to display your listings. "All categories" lists every active listing.'),
            'all_categories' => $this->translate('All categories'),
            'browsing' => $this->translate('You are browsing: %name%'),
            'listings' => $this->translate('Active listings'),
            'select' => $this->translate('Select'),
            'item' => $this->translate('Item'),
            'title_col' => $this->translate('Title'),
            'price' => $this->translate('Price'),
            'quantity' => $this->translate('Quantity'),
            'category' => $this->translate('Category'),
            'marketplace' => $this->translate('Marketplace'),
            'import_selected' => $this->translate('Import selected'),
            'no_listings' => $this->translate('No listings found in this category.'),
            'no_categories' => $this->translate('No subcategories.'),
            'leaf' => $this->translate('leaf'),
            'back' => $this->translate('Back'),
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
     * @param array<string, mixed> $params
     */
    private function renderTemplate(string $template, array $params = []): string
    {
        $this->context->smarty->assign($params);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'linkbay/views/templates/admin/' . $template);
    }
}
