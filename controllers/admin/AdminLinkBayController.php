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

use LinkBay\Config\Config;
use LinkBay\Entity\LinkBayCategoryMap;
use LinkBay\Entity\PriceRule;
use LinkBay\Repository\LinkBayCategoryMapRepository;
use LinkBay\Repository\LinkBayLogRepository;
use LinkBay\Repository\LinkBayPriceRuleRepository;
use LinkBay\Repository\LinkBayProductRepository;
use LinkBay\Service\EbayApiClient;
use LinkBay\Service\PriceRuleEngine;
use LinkBay\Service\TokenStorage;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * LinkBay configuration page (eBay connection, import settings, category maps and logs).
 */
class AdminLinkBayController extends ModuleAdminController
{
    private const EBAY_SITES = [
        0 => 'eBay US',
        2 => 'eBay Canada',
        3 => 'eBay UK',
        15 => 'eBay Australia',
        16 => 'eBay Austria',
        23 => 'eBay Belgium (FR)',
        71 => 'eBay France',
        77 => 'eBay Germany',
        101 => 'eBay Italy',
        123 => 'eBay Belgium (NL)',
        146 => 'eBay Netherlands',
        186 => 'eBay Spain',
        193 => 'eBay Switzerland',
        205 => 'eBay Ireland',
        206 => 'eBay Malaysia',
        207 => 'eBay Philippines',
        210 => 'eBay Canada (FR)',
        211 => 'eBay Singapore',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
    }

    public function postProcess(): void
    {
        // OAuth callback from eBay.
        $code = Tools::getValue('code');
        $state = Tools::getValue('state');

        if (is_string($code) && $code !== '' && is_string($state) && $state !== '') {
            $this->handleOauthCallback($code, $state);

            return;
        }

        if (Tools::isSubmit('submitLinkBaySettings')) {
            $this->handleSaveSettings();
        } elseif (Tools::isSubmit('linkbay_connect')) {
            $this->handleConnect();
        } elseif (Tools::isSubmit('linkbay_disconnect')) {
            $this->handleDisconnect();
        } elseif (Tools::isSubmit('linkbay_test')) {
            $this->handleTestConnection();
        } elseif (Tools::isSubmit('linkbay_webhooks_enable')) {
            $this->handleEnableWebhooks();
        } elseif (Tools::isSubmit('linkbay_webhooks_disable')) {
            $this->handleDisableWebhooks();
        } elseif (Tools::isSubmit('submitCategoryMap')) {
            $this->handleSaveCategoryMap();
        } elseif (Tools::isSubmit('submitBufferSettings')) {
            $this->handleSaveBufferSettings();
        } elseif (Tools::isSubmit('submitPriceRuleAdd')) {
            $this->handleAddPriceRule();
        } elseif (Tools::isSubmit('submitPriceRuleSettings')) {
            $this->handleSavePriceRuleSettings();
        } elseif (Tools::isSubmit('price_rule_delete')) {
            $this->handleDeletePriceRule();
        } elseif (Tools::isSubmit('price_rule_toggle')) {
            $this->handleTogglePriceRule();
        } elseif (Tools::isSubmit('linkbay_price_apply_now')) {
            $this->handleApplyPriceRulesNow();
        } elseif (Tools::isSubmit('delete_category_map')) {
            $this->handleDeleteCategoryMap();
        } elseif (Tools::isSubmit('linkbay_clear_logs')) {
            $this->handleClearLogs();
        }
    }

    public function initContent(): void
    {
        $this->consumeFlash();

        $this->content = $this->renderTemplate('config.tpl', $this->getTemplateData());

        parent::initContent();
    }

    private function handleSaveSettings(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        // Le Client ID / Secret sont partagés (keyset unique) : on ne les
        // écrase jamais via le formulaire. Seul le code eBay (RuName) est saisi.
        \Configuration::updateValue(Config::REDIRECT_URI, trim((string) Tools::getValue('redirect_uri', '')));

        \Configuration::updateValue(Config::EBAY_USERNAME, trim((string) Tools::getValue('ebay_username', '')));
        \Configuration::updateValue(Config::TAX_RULE, (string) (int) Tools::getValue('tax_rule', 0));
        $siteIds = [];
        foreach ((array) Tools::getValue('site_ids', []) as $siteId) {
            $siteId = (int) $siteId;
            if (array_key_exists($siteId, self::EBAY_SITES)) {
                $siteIds[] = $siteId;
            }
        }
        \Configuration::updateValue(Config::EBAY_SITE_ID, implode(',', $siteIds));

        $bufferMode = Tools::getValue('buffer_mode', 'none');
        if (!in_array($bufferMode, ['none', 'percentage', 'fixed'], true)) {
            $bufferMode = 'none';
        }
        \Configuration::updateValue(Config::BUFFER_MODE, $bufferMode);
        \Configuration::updateValue(Config::BUFFER_VALUE, (string) max(0, (float) Tools::getValue('buffer_value', 0)));
        \Configuration::updateValue(Config::DEFAULT_CATEGORY, (string) (int) Tools::getValue('default_category', 0));
        \Configuration::updateValue(Config::SYNC_FREQUENCY, (string) max(1, (int) Tools::getValue('sync_frequency', 15)));
        \Configuration::updateValue(Config::AUTO_IMPORT, (string) (int) Tools::getValue('auto_import', 0));

        $this->confirmations[] = $this->translate('Settings saved.');
    }

    private function handleConnect(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        if (!\Configuration::get(Config::REDIRECT_URI)) {
            $this->errors[] = $this->translate('Please enter your eBay code (RuName) first.');

            return;
        }

        $client = new EbayApiClient(new TokenStorage());
        if (!$client->isConfigured()) {
            $this->errors[] = $this->translate('Please fill in your eBay API credentials first.');

            return;
        }

        $state = EbayApiClient::generateOauthState();

        Tools::redirect($client->buildAuthorizationUrl($state));
    }

    private function handleOauthCallback(string $code, string $state): void
    {
        if (!EbayApiClient::validateOauthState($state)) {
            $this->setFlash('error', $this->translate('Invalid OAuth state. Please try connecting again.'));
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLinkBay'));
        }

        $client = new EbayApiClient(new TokenStorage());

        try {
            $tokens = $client->exchangeAuthorizationCode($code);
            $client->storeTokens($tokens);
            $this->setFlash('success', $this->translate('eBay account connected successfully.'));
        } catch (\Throwable $e) {
            $this->setFlash('error', $this->translate('Failed to connect to eBay: %message%', ['%message%' => $e->getMessage()]));
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminLinkBay'));
    }

    private function handleDisconnect(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        \Configuration::deleteByName(Config::ACCESS_TOKEN);
        \Configuration::deleteByName(Config::REFRESH_TOKEN);
        \Configuration::deleteByName(Config::TOKEN_EXPIRES_AT);

        $this->confirmations[] = $this->translate('eBay account disconnected.');
    }

    private function handleTestConnection(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $client = new EbayApiClient(new TokenStorage());

        try {
            $client->testConnection();
            $this->confirmations[] = $this->translate('Connection to eBay is working.');
        } catch (\Throwable $e) {
            $this->errors[] = $this->translate('Connection test failed: %message%', ['%message%' => $e->getMessage()]);
        }
    }

    /**
     * Creates the eBay notification subscriptions (account alerts webhooks).
     */
    private function handleEnableWebhooks(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $client = new EbayApiClient(new TokenStorage());
        $url = $this->context->link->getModuleLink('linkbay', 'webhook');
        $verificationToken = (string) \Configuration::get(Config::WEBHOOK_TOKEN);

        $destinationId = $client->findOrCreateDestination($url, $verificationToken);
        if ($destinationId === null) {
            $this->errors[] = $this->translate(
                'Failed to register the webhook destination. Check that the webhook URL is reachable from the internet: %url%',
                ['%url%' => $url]
            );

            return;
        }

        $authSub = $client->createNotificationSubscription('AUTHORIZATION_REVOCATION', $destinationId);
        $deletionSub = $client->createNotificationSubscription('MARKETPLACE_ACCOUNT_DELETION', $destinationId);

        if ($authSub === null || $deletionSub === null) {
            $this->errors[] = $this->translate('Failed to enable the eBay account alerts. Please try again.');

            return;
        }

        \Configuration::updateValue(Config::WEBHOOK_AUTH_REVOCATION_SUB, $authSub);
        \Configuration::updateValue(Config::WEBHOOK_ACCOUNT_DELETION_SUB, $deletionSub);

        $this->confirmations[] = $this->translate('eBay account alerts enabled (authorization revocation and account deletion).');
    }

    /**
     * Deletes the eBay notification subscriptions.
     */
    private function handleDisableWebhooks(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $client = new EbayApiClient(new TokenStorage());

        foreach ([Config::WEBHOOK_AUTH_REVOCATION_SUB, Config::WEBHOOK_ACCOUNT_DELETION_SUB] as $key) {
            $subscriptionId = (string) \Configuration::get($key);
            if ($subscriptionId !== '') {
                $client->deleteNotificationSubscription($subscriptionId);
            }
            \Configuration::updateValue($key, '');
        }

        $this->confirmations[] = $this->translate('eBay account alerts disabled.');
    }

    /**
     * Saves the per-product stock buffer percentages.
     */
    private function handleSaveBufferSettings(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $repository = new LinkBayProductRepository();
        $buffers = Tools::getValue('buffer_percent', []);
        $saved = 0;

        foreach ((array) $buffers as $idLink => $percent) {
            $idLink = (int) $idLink;
            $raw = (string) $percent;

            $value = null;
            if ($raw !== '') {
                $value = min(100, max(0, (float) $raw));
            }

            if ($repository->updateBufferPercent($idLink, $value)) {
                ++$saved;
            }
        }

        $this->confirmations[] = $this->translate('Stock buffers saved (%count% products).', ['%count%' => $saved]);
    }

    /**
     * Creates a competitive price rule.
     */
    private function handleAddPriceRule(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $name = trim((string) Tools::getValue('price_rule_name', ''));
        $minStockRaw = (string) Tools::getValue('price_rule_min_stock', '');
        $maxStockRaw = (string) Tools::getValue('price_rule_max_stock', '');
        $percent = (float) Tools::getValue('price_rule_percent', 0);
        $priority = (int) Tools::getValue('price_rule_priority', 0);

        $minStock = $minStockRaw === '' ? null : max(0, (int) $minStockRaw);
        $maxStock = $maxStockRaw === '' ? null : max(0, (int) $maxStockRaw);

        if ($name === '' || ($minStock === null && $maxStock === null)) {
            $this->errors[] = $this->translate('Please give the rule a name and at least one stock condition.');

            return;
        }

        if ($minStock !== null && $maxStock !== null && $minStock >= $maxStock) {
            $this->errors[] = $this->translate('The lower bound must be smaller than the upper bound.');

            return;
        }

        $percent = max(-99, min(900, $percent));

        $rule = new PriceRule();
        $rule->setName($name)
            ->setActive(true)
            ->setMinStock($minStock)
            ->setMaxStock($maxStock)
            ->setPercent($percent)
            ->setPriority($priority > 0 ? $priority : (new LinkBayPriceRuleRepository())->nextPriority());

        (new LinkBayPriceRuleRepository())->save($rule);

        $this->confirmations[] = $this->translate('Price rule added. It will be applied on the next cron run.');
    }

    /**
     * Saves the global price rule settings.
     */
    private function handleSavePriceRuleSettings(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        \Configuration::updateValue(Config::PRICE_RULE_APPLY_TO_PS, (string) (int) Tools::getValue('price_rule_apply_to_ps', 0));

        $this->confirmations[] = $this->translate('Price rule settings saved.');
    }

    private function handleDeletePriceRule(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $id = (int) Tools::getValue('id_price_rule', 0);
        if ($id > 0) {
            (new LinkBayPriceRuleRepository())->deleteById($id);
            $this->confirmations[] = $this->translate('Price rule deleted.');
        }
    }

    private function handleTogglePriceRule(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $id = (int) Tools::getValue('id_price_rule', 0);
        if ($id > 0) {
            $repository = new LinkBayPriceRuleRepository();
            $rule = $repository->findById($id);
            if ($rule !== null) {
                $repository->setActive($id, !$rule->isActive());
                $this->confirmations[] = $this->translate('Price rule updated.');
            }
        }
    }

    /**
     * Evaluates and pushes the price rules immediately.
     */
    private function handleApplyPriceRulesNow(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

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

    private function handleSaveCategoryMap(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $ebayCategoryId = trim((string) Tools::getValue('ebay_category_id', ''));
        $idCategory = (int) Tools::getValue('id_category', 0);

        if ($ebayCategoryId === '' || $idCategory <= 0) {
            $this->errors[] = $this->translate('Please provide an eBay category id and a PrestaShop category.');

            return;
        }

        $repository = new LinkBayCategoryMapRepository();
        $map = $repository->findByEbayCategoryId($ebayCategoryId) ?? new LinkBayCategoryMap();
        $map->setEbayCategoryId($ebayCategoryId)->setIdCategory($idCategory);
        $repository->save($map);

        $this->confirmations[] = $this->translate('Category mapping saved.');
    }

    private function handleDeleteCategoryMap(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        $idMap = (int) Tools::getValue('id_category_map', 0);
        if ($idMap > 0) {
            (new LinkBayCategoryMapRepository())->deleteById($idMap);
            $this->confirmations[] = $this->translate('Category mapping removed.');
        }
    }

    private function handleClearLogs(): void
    {
        if (!$this->checkCsrf()) {
            return;
        }

        (new LinkBayLogRepository())->clear();
        $this->confirmations[] = $this->translate('Logs cleared.');
    }

    private function checkCsrf(): bool
    {
        $token = (string) Tools::getValue('linkbay_token', '');

        if ($token !== '' && hash_equals((string) Tools::getAdminTokenLite('AdminLinkBay'), $token)) {
            return true;
        }

        $this->errors[] = $this->translate('Invalid security token.');

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function getTemplateData(): array
    {
        $client = new EbayApiClient(new TokenStorage());
        $idLang = (int) $this->context->language->id;
        $cronToken = (string) \Configuration::get(Config::CRON_TOKEN);

        return [
            'controller_link' => $this->context->link->getAdminLink('AdminLinkBay'),
            'csrf_token' => (string) Tools::getAdminTokenLite('AdminLinkBay'),
            'is_connected' => $client->isAuthenticated(),
            'has_credentials' => $client->isConfigured(),
            'support_email' => $this->configValue(Config::SUPPORT_EMAIL),
            'redirect_uri' => (string) \Configuration::get(Config::REDIRECT_URI),
            'ebay_username' => (string) \Configuration::get(Config::EBAY_USERNAME),
            'tax_rule' => (int) \Configuration::get(Config::TAX_RULE),
            'tax_rule_options' => $this->getTaxRuleOptions(),
            'site_ids' => $this->selectedSiteIds(),
            'default_category' => (int) \Configuration::get(Config::DEFAULT_CATEGORY),
            'sync_frequency' => (int) \Configuration::get(Config::SYNC_FREQUENCY),
            'auto_import' => (bool) \Configuration::get(Config::AUTO_IMPORT),
            'cron_token' => $cronToken,
            'cron_url' => $this->context->link->getModuleLink('linkbay', 'cron', ['token' => $cronToken]),
            'webhooks_enabled' => (string) \Configuration::get(Config::WEBHOOK_AUTH_REVOCATION_SUB) !== '',
            'webhook_url' => $this->context->link->getModuleLink('linkbay', 'webhook'),
            'buffer_mode' => (string) \Configuration::get(Config::BUFFER_MODE),
            'buffer_value' => (string) \Configuration::get(Config::BUFFER_VALUE),
            'buffer_products' => $this->getBufferProducts(),
            'price_rules' => $this->getPriceRules(),
            'price_rule_next_priority' => (new LinkBayPriceRuleRepository())->nextPriority(),
            'price_rule_apply_to_ps' => (bool) \Configuration::get(Config::PRICE_RULE_APPLY_TO_PS),
            'price_rule_products' => $this->getPriceRuleProducts(),
            'compat_modules' => $this->getCompatibilityConcerns(),
            'categories' => $this->getCategoryOptions(),
            'site_options' => $this->getSiteOptions(),
            'category_maps' => $this->getCategoryMaps($idLang),
            'logs' => $this->getLogs(),
            'labels' => $this->getLabels(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPriceRules(): array
    {
        $rules = [];

        foreach ((new LinkBayPriceRuleRepository())->findAll() as $rule) {
            $percent = $rule->getPercent();
            $percentText = rtrim(rtrim(number_format(abs($percent), 2, '.', ''), '0'), '.') . '%';
            if ($percent > 0) {
                $percentText = '+' . $percentText;
            } elseif ($percent < 0) {
                $percentText = '−' . $percentText;
            }

            $rules[] = [
                'id' => (int) $rule->getId(),
                'name' => $rule->getName(),
                'active' => $rule->isActive(),
                'condition' => $this->priceConditionLabel($rule),
                'percent' => $percentText,
                'percent_positive' => $percent >= 0,
                'priority' => $rule->getPriority(),
            ];
        }

        return $rules;
    }

    private function priceConditionLabel(PriceRule $rule): string
    {
        if ($rule->getMinStock() !== null && $rule->getMaxStock() !== null) {
            return $this->translate('If stock is above %min% and below %max%', [
                '%min%' => $rule->getMinStock(),
                '%max%' => $rule->getMaxStock(),
            ]);
        }
        if ($rule->getMinStock() !== null) {
            return $this->translate('If stock is above %min%', ['%min%' => $rule->getMinStock()]);
        }
        if ($rule->getMaxStock() !== null) {
            return $this->translate('If stock is below %max%', ['%max%' => $rule->getMaxStock()]);
        }

        return $this->translate('Always');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPriceRuleProducts(): array
    {
        $rows = [];
        $repository = new LinkBayProductRepository();
        $ruleNames = [];
        foreach ((new LinkBayPriceRuleRepository())->findAll() as $rule) {
            $ruleNames[(int) $rule->getId()] = $rule->getName();
        }

        foreach ($repository->findAll() as $link) {
            if ($link->getIdProductAttribute() !== 0) {
                continue;
            }

            $product = new \Product($link->getIdProduct());
            if (!\Validate::isLoadedObject($product)) {
                continue;
            }

            $idLang = (int) ($this->context->language->id ?? 1);
            $name = is_array($product->name) ? (string) ($product->name[$idLang] ?? reset($product->name)) : (string) $product->name;

            $hasCombinations = false;
            $stock = 0;
            foreach ($repository->findByProduct($link->getIdProduct()) as $row) {
                if ($row->getIdProductAttribute() > 0) {
                    $hasCombinations = true;
                    $stock += (int) \StockAvailable::getQuantityAvailableByProduct(
                        $link->getIdProduct(),
                        $row->getIdProductAttribute()
                    );
                }
            }
            if (!$hasCombinations) {
                $stock = (int) \StockAvailable::getQuantityAvailableByProduct($link->getIdProduct(), 0);
            }

            $rows[] = [
                'name' => $name,
                'stock' => $stock,
                'base_price' => $link->getBasePrice(),
                'currency' => $link->getPriceCurrency(),
                'rule_name' => $link->getIdPriceRule() !== null ? (string) ($ruleNames[$link->getIdPriceRule()] ?? '') : '',
                'target_price' => $link->getTargetPrice(),
                'price_status' => $link->getPriceStatus(),
            ];
        }

        return $rows;
    }

    /**
     * Detects active modules that also react to the same PrestaShop product
     * lifecycle hooks as LinkBay. They do not conflict by default, but two
     * modules rewriting the price / stock / tax of the same product can fight.
     * LinkBay only manages products mapped to eBay, so the risk is limited to
     * articles shared with another channel.
     *
     * @return array<int, string> Module names.
     */
    private function getCompatibilityConcerns(): array
    {
        $hooks = [
            'actionObjectProductUpdateAfter',
            'actionObjectCombinationUpdateAfter',
            'actionObjectProductDeleteAfter',
        ];

        $found = [];

        foreach ($hooks as $hook) {
            $rows = \Db::getInstance()->executeS(
                'SELECT DISTINCT m.`name`
                 FROM ' . _DB_PREFIX_ . 'module m
                 INNER JOIN ' . _DB_PREFIX_ . 'hook_module hm ON hm.`id_module` = m.`id_module` AND hm.`id_shop` IN (0, ' . (int) $this->context->shop->id . ')
                 INNER JOIN ' . _DB_PREFIX_ . 'hook h ON h.`id_hook` = hm.`id_hook`
                 WHERE h.`name` = "' . pSQL($hook) . '" AND m.`active` = 1'
            );

            foreach ((array) $rows as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name !== '' && $name !== 'linkbay') {
                    $found[$name] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function getCategoryOptions(): array
    {
        $categories = \Category::getSimpleCategories((int) $this->context->language->id);
        if (!is_array($categories)) {
            $categories = [];
        }

        return array_map(static fn (array $category): array => [
            'id' => (int) $category['id_category'],
            'name' => (string) $category['name'],
        ], $categories);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function getSiteOptions(): array
    {
        $options = [];

        foreach (self::EBAY_SITES as $id => $label) {
            $options[] = ['value' => $id, 'label' => $label];
        }

        return $options;
    }

    /**
     * Lists the mapped products with their per-product stock buffer.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getBufferProducts(): array
    {
        $rows = [];
        $repository = new LinkBayProductRepository();

        foreach ($repository->findAll() as $link) {
            if ($link->getIdProductAttribute() !== 0) {
                continue;
            }

            $product = new \Product($link->getIdProduct());
            if (!\Validate::isLoadedObject($product)) {
                continue;
            }

            $idLang = (int) ($this->context->language->id ?? 1);
            $name = is_array($product->name) ? (string) ($product->name[$idLang] ?? reset($product->name)) : (string) $product->name;

            $rows[] = [
                'id' => (int) $link->getId(),
                'product_id' => $link->getIdProduct(),
                'name' => $name,
                'ebay_item_id' => $link->getEbayItemId(),
                'buffer_percent' => $link->getBufferPercent(),
            ];
        }

        return $rows;
    }

    /**
     * @return int[]
     */
    private function selectedSiteIds(): array
    {
        $ids = [];

        foreach (explode(',', (string) \Configuration::get(Config::EBAY_SITE_ID)) as $siteId) {
            $siteId = (int) trim($siteId);
            if (array_key_exists($siteId, self::EBAY_SITES)) {
                $ids[] = $siteId;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function getTaxRuleOptions(): array
    {
        $options = [['id' => 0, 'name' => $this->translate('No tax rule')]];

        foreach (\TaxRulesGroup::getTaxRulesGroups() as $group) {
            if (!is_array($group)) {
                continue;
            }
            $options[] = [
                'id' => (int) ($group['id_tax_rules_group'] ?? 0),
                'name' => (string) ($group['name'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getCategoryMaps(int $idLang): array
    {
        $maps = [];

        foreach ((new LinkBayCategoryMapRepository())->findAll() as $map) {
            $maps[] = [
                'id' => $map->getId(),
                'ebay_category_id' => $map->getEbayCategoryId(),
                'id_category' => $map->getIdCategory(),
                'category_name' => (string) (new \Category($map->getIdCategory()))->getName($idLang),
            ];
        }

        return $maps;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getLogs(): array
    {
        $logs = [];

        foreach ((new LinkBayLogRepository())->getRecent(50) as $log) {
            $logs[] = [
                'id' => $log->getId(),
                'id_product' => $log->getIdProduct(),
                'ebay_item_id' => $log->getEbayItemId(),
                'action' => $log->getAction(),
                'status' => $log->getStatus(),
                'message' => $log->getMessage(),
                'date_add' => $log->getDateAdd(),
            ];
        }

        return $logs;
    }

    /**
     * @return array<string, string>
     */
    private function getLabels(): array
    {
        return [
            'title' => $this->translate('LinkBay configuration'),
            'ebay_code' => $this->translate('eBay code (RuName)'),
            'ebay_code_help' => $this->translate('Paste the eBay code provided with your license. It links this shop to your dedicated eBay connection — nothing else to configure.'),
            'support_contact' => $this->translate('Support: '),
            'support_help' => $this->translate('Need help configuring the module? Contact Beassisted at: '),
            'compat' => $this->translate('Compatibility & conflicts'),
            'compat_scoped' => $this->translate('LinkBay only manages the products mapped to eBay. It never modifies the products you manage elsewhere, and never changes the global tax settings of the shop.'),
            'compat_tax' => $this->translate('Tax rule: LinkBay assigns the selected tax rule to imported products only. If you use a third-party VAT/tax module, choose "No tax rule" so LinkBay leaves the taxes to it (no conflict).'),
            'compat_concerns_title' => $this->translate('Modules that also react to product changes'),
            'compat_concerns_text' => $this->translate('These active modules also listen to the same PrestaShop hooks. They do not conflict by default; just make sure they do not rewrite the price, stock or tax of the same eBay-mapped product for another channel.'),
            'compat_none' => $this->translate('No module detected that interferes with the product lifecycle.'),
            'connection' => $this->translate('eBay connection'),
            'connected' => $this->translate('Connected'),
            'not_connected' => $this->translate('Not connected'),
            'credentials_missing' => $this->translate('API credentials not configured'),
            'connect' => $this->translate('Connect to eBay'),
            'disconnect' => $this->translate('Disconnect'),
            'test' => $this->translate('Test connection'),
            'api_settings' => $this->translate('eBay API settings'),
            'ebay_username' => $this->translate('eBay username'),
            'ebay_username_help' => $this->translate('Your eBay seller username, used to fetch your active listings.'),
            'tax_rule' => $this->translate('Tax rule for imported products'),
            'tax_rule_help' => $this->translate('eBay prices include taxes; the module converts them to the tax-excluded price used by PrestaShop. Leave "No tax rule" to import prices as-is.'),
            'site_id' => $this->translate('eBay marketplaces'),
            'site_help' => $this->translate('Select every marketplace where your listings are published; the module will import and sync them all.'),
            'save' => $this->translate('Save settings'),
            'import_settings' => $this->translate('Import settings'),
            'default_category' => $this->translate('Default PrestaShop category'),
            'sync_frequency' => $this->translate('Cron frequency (minutes)'),
            'auto_import' => $this->translate('Enable automatic import'),
            'cron' => $this->translate('Cron'),
            'cron_help' => $this->translate('Call this URL on a schedule (e.g. every 15 minutes).'),
            'cron_token' => $this->translate('Secret token'),
            'webhooks' => $this->translate('eBay account alerts (webhooks)'),
            'webhooks_help' => $this->translate('eBay notifies the module when the app authorization is revoked or when the account requests data deletion. Stock and price sync stay on the 15-minute cron: eBay does not offer seller-side item webhooks.'),
            'webhook_url' => $this->translate('Webhook URL'),
            'webhooks_enable' => $this->translate('Enable account alerts'),
            'webhooks_disable' => $this->translate('Disable account alerts'),
            'webhooks_enabled' => $this->translate('Enabled'),
            'webhooks_disabled' => $this->translate('Disabled'),
            'buffer' => $this->translate('Stock buffer'),
            'buffer_help' => $this->translate('Keep a safety margin in PrestaShop: send less stock to eBay than you really have (e.g. 10 real, 8 sent), so you can still honor orders if a product is damaged or missing.'),
            'buffer_mode' => $this->translate('Default buffer'),
            'buffer_none' => $this->translate('No buffer (send the real stock)'),
            'buffer_percentage' => $this->translate('Percentage of the stock to send'),
            'buffer_fixed' => $this->translate('Fixed quantity to keep in reserve'),
            'buffer_value' => $this->translate('Value (% or quantity)'),
            'buffer_per_product' => $this->translate('Per-product buffer (% of the stock to send — empty = use the default)'),
            'buffer_save' => $this->translate('Save buffers'),
            'price_rules' => $this->translate('Competitive price rules (dynamic pricing)'),
            'price_rules_help' => $this->translate('Adjust the eBay price automatically according to your stock. Example: if the stock is above 50, lower the price by 5% to sell off; if the stock is below 5, raise it by 10% to maximize your margin on scarcity.'),
            'price_rule_name' => $this->translate('Rule name'),
            'price_rule_min_stock' => $this->translate('If stock is above (leave empty for no lower bound)'),
            'price_rule_max_stock' => $this->translate('If stock is below (leave empty for no upper bound)'),
            'price_rule_percent' => $this->translate('Adjust the eBay price by (%)'),
            'price_rule_percent_help' => $this->translate('Negative to lower the price (e.g. -5), positive to raise it (e.g. +10).'),
            'price_rule_priority' => $this->translate('Priority'),
            'price_rule_priority_help' => $this->translate('Rules are evaluated from the lowest priority; the first matching rule wins.'),
            'price_rule_add' => $this->translate('Add rule'),
            'price_rule_apply_to_ps' => $this->translate('Also apply the computed price to the PrestaShop product'),
            'price_rule_apply_to_ps_help' => $this->translate('Useful for classic listings, whose eBay price cannot be changed by API: the PrestaShop price then follows your strategy.'),
            'price_rule_apply_now' => $this->translate('Apply the rules now'),
            'price_rule_settings_save' => $this->translate('Save settings'),
            'price_rules_empty' => $this->translate('No price rule yet. Add your first rule below.'),
            'price_rule_enabled' => $this->translate('Enabled'),
            'price_rule_disabled' => $this->translate('Disabled'),
            'price_rule_products_title' => $this->translate('Prices per product'),
            'price_rule_product' => $this->translate('Product'),
            'price_rule_stock' => $this->translate('Stock'),
            'price_rule_base_price' => $this->translate('eBay base price'),
            'price_rule_matched' => $this->translate('Matched rule'),
            'price_rule_target' => $this->translate('Target price'),
            'price_rule_status' => $this->translate('Status'),
            'price_status_pushed' => $this->translate('Pushed to eBay'),
            'price_status_not_pushable' => $this->translate('Classic listing (eBay forbids API price changes)'),
            'price_status_push_error' => $this->translate('Push to eBay failed'),
            'price_status_none' => $this->translate('Base price (no rule matched)'),
            'category_map' => $this->translate('Category mapping'),
            'ebay_category_id' => $this->translate('eBay category ID'),
            'prestashop_category' => $this->translate('PrestaShop category'),
            'add_mapping' => $this->translate('Add mapping'),
            'delete' => $this->translate('Delete'),
            'no_mappings' => $this->translate('No category mappings yet.'),
            'logs' => $this->translate('Logs'),
            'clear_logs' => $this->translate('Clear logs'),
            'no_logs' => $this->translate('No logs yet.'),
            'action' => $this->translate('Action'),
            'status' => $this->translate('Status'),
            'message' => $this->translate('Message'),
            'date' => $this->translate('Date'),
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
     * Reads a module configuration value, falling back to the install default
     * so that shops upgraded without a reinstall keep working.
     */
    private function configValue(string $key): string
    {
        $value = (string) \Configuration::get($key);

        return $value !== '' ? $value : (string) (Config::DEFAULTS[$key] ?? '');
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

    private function setFlash(string $type, string $message): void
    {
        // Stored through the Cookie magic __set; consumed on the next render.
        // @phpstan-ignore-next-line
        $this->context->cookie->linkbay_flash_type = $type;
        // @phpstan-ignore-next-line
        $this->context->cookie->linkbay_flash_message = $message;
        $this->context->cookie->write();
    }

    private function consumeFlash(): void
    {
        $cookie = $this->context->cookie;

        if (isset($cookie->linkbay_flash_message) && $cookie->linkbay_flash_message !== '') {
            $type = isset($cookie->linkbay_flash_type) ? (string) $cookie->linkbay_flash_type : 'success';
            $message = (string) $cookie->linkbay_flash_message;

            if ($type === 'error') {
                $this->errors[] = $message;
            } else {
                $this->confirmations[] = $message;
            }
        }

        unset($cookie->linkbay_flash_type, $cookie->linkbay_flash_message);
        $cookie->write();
    }
}
