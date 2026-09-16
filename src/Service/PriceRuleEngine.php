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

namespace LinkBay\Service;

use LinkBay\Config\Config;
use LinkBay\Entity\LinkBayLog;
use LinkBay\Entity\LinkBayProduct;
use LinkBay\Entity\PriceRule;
use LinkBay\Repository\LinkBayLogRepository;
use LinkBay\Repository\LinkBayPriceRuleRepository;
use LinkBay\Repository\LinkBayProductRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Competitive price rules: adjusts the eBay price according to the current
 * stock.
 *
 * Example rules: "if stock > 50, lower the eBay price by 5% to sell off" and
 * "if stock < 5, raise the price by 10% to maximize the margin on scarcity".
 *
 * The target price is computed from the base price (the eBay price at the last
 * import). It is pushed to eBay for inventory-managed listings (SKU); classic
 * listings are marked "not_pushable" because eBay offers no seller-side API to
 * change their price.
 */
final class PriceRuleEngine
{
    public const STATUS_NO_RULE = '';
    public const STATUS_PUSHED = 'pushed';
    public const STATUS_NOT_PUSHABLE = 'not_pushable';
    public const STATUS_PUSH_ERROR = 'push_error';

    private EbayApiClient $apiClient;
    private LinkBayProductRepository $productLinkRepository;
    private LinkBayPriceRuleRepository $priceRuleRepository;
    private LinkBayLogRepository $logRepository;

    public function __construct(
        EbayApiClient $apiClient,
        LinkBayProductRepository $productLinkRepository,
        LinkBayPriceRuleRepository $priceRuleRepository,
        LinkBayLogRepository $logRepository
    ) {
        $this->apiClient = $apiClient;
        $this->productLinkRepository = $productLinkRepository;
        $this->priceRuleRepository = $priceRuleRepository;
        $this->logRepository = $logRepository;
    }

    /**
     * Evaluates every active rule against every mapped product and pushes the
     * computed target prices to eBay where possible.
     *
     * @return array{evaluated: int, matched: int, pushed: int, not_pushable: int, errors: int}
     */
    public function apply(): array
    {
        $summary = ['evaluated' => 0, 'matched' => 0, 'pushed' => 0, 'not_pushable' => 0, 'errors' => 0];
        $rules = $this->priceRuleRepository->findActive();
        $pushCandidates = [];

        foreach ($this->productLinkRepository->findAll() as $link) {
            if ($link->getIdProductAttribute() !== 0) {
                continue;
            }

            $idProduct = $link->getIdProduct();
            $product = new \Product($idProduct);
            if (!\Validate::isLoadedObject($product) || !(bool) $product->active) {
                continue;
            }

            ++$summary['evaluated'];

            $stock = $this->currentStock($link);
            $rule = $this->matchRule($rules, $stock);

            // Pas de règle qui s'applique (ou prix de base inconnu) : on efface
            // la cible précédente pour revenir au prix eBay d'origine.
            if ($rule === null || $link->getBasePrice() <= 0) {
                if ($link->getTargetPrice() !== null || $link->getIdPriceRule() !== null) {
                    $this->productLinkRepository->updatePriceState(
                        (int) $link->getId(),
                        null,
                        null,
                        self::STATUS_NO_RULE
                    );
                }

                continue;
            }

            ++$summary['matched'];

            $target = round($link->getBasePrice() * (1 + $rule->getPercent() / 100), 2);

            $this->applyToPrestashop($link, $product, $target);

            // Seules les annonces gérées par l'inventaire (avec SKU) acceptent
            // une mise à jour du prix via l'API eBay.
            if ($link->getEbaySku() !== '') {
                $pushCandidates[] = [
                    'link' => $link,
                    'price' => $target,
                    'ruleId' => (int) $rule->getId(),
                ];

                continue;
            }

            $this->productLinkRepository->updatePriceState(
                (int) $link->getId(),
                $target,
                (int) $rule->getId(),
                self::STATUS_NOT_PUSHABLE
            );
            ++$summary['not_pushable'];

            $this->log(
                $idProduct,
                $link->getEbayItemId(),
                'price_rule',
                LinkBayLog::STATUS_INFO,
                'Rule "' . $rule->getName() . '" matched (stock ' . $stock . '): target eBay price '
                . $target . ' ' . $link->getPriceCurrency()
                . '. Classic listing: eBay does not allow API price changes.'
            );
        }

        if (!empty($pushCandidates)) {
            $this->pushPrices($pushCandidates, $summary);
        }

        return $summary;
    }

    /**
     * Returns the first rule that matches the stock, in priority order.
     *
     * @param PriceRule[] $rules
     */
    private function matchRule(array $rules, int $stock): ?PriceRule
    {
        foreach ($rules as $rule) {
            if ($rule->matches($stock)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Pushes the computed target prices to eBay and records per-SKU results.
     *
     * @param array<int, array{link: LinkBayProduct, price: float, ruleId: int}> $candidates
     * @param array{evaluated: int, matched: int, pushed: int, not_pushable: int, errors: int} $summary
     */
    private function pushPrices(array $candidates, array &$summary): void
    {
        $updates = [];

        foreach ($candidates as $candidate) {
            $link = $candidate['link'];
            $updates[] = [
                'sku' => $link->getEbaySku(),
                'price' => (float) $candidate['price'],
                'currency' => $link->getPriceCurrency(),
            ];
        }

        try {
            $responses = $this->apiClient->updatePrices($updates);
        } catch (\Throwable $e) {
            foreach ($candidates as $candidate) {
                $link = $candidate['link'];
                $this->productLinkRepository->updatePriceState(
                    (int) $link->getId(),
                    (float) $candidate['price'],
                    (int) $candidate['ruleId'],
                    self::STATUS_PUSH_ERROR
                );
                ++$summary['errors'];
            }

            $this->log(0, '', 'price_rule', LinkBayLog::STATUS_ERROR, 'Price push to eBay failed: ' . $e->getMessage());

            return;
        }

        $resultBySku = [];
        foreach ($responses as $response) {
            $resultBySku[(string) ($response['sku'] ?? '')] = $response;
        }

        foreach ($candidates as $candidate) {
            $link = $candidate['link'];
            $response = $resultBySku[$link->getEbaySku()] ?? null;
            $ok = $response !== null && (int) ($response['statusCode'] ?? 500) < 300;

            if ($ok) {
                $this->productLinkRepository->updatePriceState(
                    (int) $link->getId(),
                    (float) $candidate['price'],
                    (int) $candidate['ruleId'],
                    self::STATUS_PUSHED
                );
                ++$summary['pushed'];

                $this->log(
                    $link->getIdProduct(),
                    $link->getEbayItemId(),
                    'price_rule',
                    LinkBayLog::STATUS_SUCCESS,
                    'Price pushed to eBay: ' . (float) $candidate['price'] . ' ' . $link->getPriceCurrency()
                );

                continue;
            }

            $message = '';
            if (is_array($response) && isset($response['errors'][0])) {
                $message = (string) ($response['errors'][0]['longMessage'] ?? $response['errors'][0]['message'] ?? '');
            }

            $this->productLinkRepository->updatePriceState(
                (int) $link->getId(),
                (float) $candidate['price'],
                (int) $candidate['ruleId'],
                self::STATUS_PUSH_ERROR
            );
            ++$summary['errors'];

            $this->log(
                $link->getIdProduct(),
                $link->getEbayItemId(),
                'price_rule',
                LinkBayLog::STATUS_ERROR,
                $message !== ''
                    ? 'eBay rejected the price update: ' . $message
                    : 'eBay did not answer for this SKU.'
            );
        }
    }

    /**
     * Optionally applies the target price to the PrestaShop product (converted
     * to the tax-excluded price, like the import does). Never touches products
     * with combinations: their PrestaShop price is not a real eBay price.
     */
    private function applyToPrestashop(LinkBayProduct $link, \Product $product, float $target): void
    {
        if (!(bool) \Configuration::get(Config::PRICE_RULE_APPLY_TO_PS)) {
            return;
        }

        if ($this->hasCombinations($link)) {
            return;
        }

        $priceExcludingTax = round($target / (1 + $this->taxRate() / 100), 6);

        if (abs((float) $product->price - $priceExcludingTax) < 0.000001) {
            return;
        }

        $product->price = $priceExcludingTax;
        $product->update();
    }

    /**
     * Current real PrestaShop stock (the buffer stock does not apply: the rule
     * reasons on the physical stock).
     */
    private function currentStock(LinkBayProduct $link): int
    {
        $idProduct = $link->getIdProduct();
        $hasCombinations = false;
        $total = 0;

        foreach ($this->productLinkRepository->findByProduct($idProduct) as $row) {
            if ($row->getIdProductAttribute() > 0) {
                $hasCombinations = true;
                $total += (int) \StockAvailable::getQuantityAvailableByProduct(
                    $idProduct,
                    $row->getIdProductAttribute()
                );
            }
        }

        if ($hasCombinations) {
            return $total;
        }

        return (int) \StockAvailable::getQuantityAvailableByProduct($idProduct, 0);
    }

    private function hasCombinations(LinkBayProduct $link): bool
    {
        foreach ($this->productLinkRepository->findByProduct($link->getIdProduct()) as $row) {
            if ($row->getIdProductAttribute() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the tax rate of the configured tax rules group.
     */
    private function taxRate(): float
    {
        $idTaxRulesGroup = (int) \Configuration::get(Config::TAX_RULE);
        if ($idTaxRulesGroup <= 0) {
            return 0.0;
        }

        $idCountry = (int) \Configuration::get('PS_COUNTRY_DEFAULT');

        $rate = (float) \Db::getInstance()->getValue(
            'SELECT t.`rate` FROM ' . _DB_PREFIX_ . 'tax_rule tr
             INNER JOIN ' . _DB_PREFIX_ . 'tax t ON t.`id_tax` = tr.`id_tax`
             WHERE tr.`id_tax_rules_group` = ' . (int) $idTaxRulesGroup . '
               AND tr.`id_country` = ' . (int) $idCountry
        );

        if ($rate > 0) {
            return $rate;
        }

        return (float) \Db::getInstance()->getValue(
            'SELECT t.`rate` FROM ' . _DB_PREFIX_ . 'tax_rule tr
             INNER JOIN ' . _DB_PREFIX_ . 'tax t ON t.`id_tax` = tr.`id_tax`
             WHERE tr.`id_tax_rules_group` = ' . (int) $idTaxRulesGroup
        );
    }

    private function log(int $idProduct, string $ebayItemId, string $action, string $status, string $message): void
    {
        try {
            $log = new LinkBayLog();
            $log->setIdProduct($idProduct)
                ->setEbayItemId($ebayItemId)
                ->setAction($action)
                ->setStatus($status)
                ->setMessage($message);

            $this->logRepository->add($log);
        } catch (\Throwable $e) {
            // Logging must never interrupt the price evaluation.
        }
    }
}
