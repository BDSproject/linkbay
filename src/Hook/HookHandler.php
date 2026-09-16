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

namespace LinkBay\Hook;

use LinkBay\Entity\LinkBayLog;
use LinkBay\Repository\LinkBayLogRepository;
use LinkBay\Repository\LinkBayProductRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Centralizes the module hook implementations.
 */
final class HookHandler
{
    private \Module $module;
    private LinkBayProductRepository $productLinkRepository;
    private LinkBayLogRepository $logRepository;

    public function __construct(\Module $module)
    {
        $this->module = $module;
        $this->productLinkRepository = new LinkBayProductRepository();
        $this->logRepository = new LinkBayLogRepository();
    }

    /**
     * Injects CSS/JS on LinkBay back office pages only.
     *
     * @param array<string, mixed> $params
     */
    public function displayBackOfficeHeader(array $params): string
    {
        $controllerName = (string) ($params['controller_name'] ?? \Tools::getValue('controller', ''));

        $linkbayControllers = ['AdminLinkBay', 'AdminLinkBayImport', 'AdminLinkBayDashboard'];
        if (!in_array($controllerName, $linkbayControllers, true)) {
            return '';
        }

        $context = \Context::getContext();
        $controller = $context instanceof \Context ? $context->controller : null;

        if ($controller instanceof \AdminController) {
            $controller->addCSS($this->module->getPathUri() . 'views/css/linkbay.css');
        }

        return '';
    }

    /**
     * Reacts to product updates.
     *
     * @param array<string, mixed> $params
     */
    public function onProductUpdate(array $params): void
    {
        $product = $params['object'] ?? null;

        if (!$product instanceof \Product || empty($product->id)) {
            return;
        }

        $rows = $this->productLinkRepository->findByProduct((int) $product->id);
        if (empty($rows)) {
            return;
        }

        $this->log(
            (int) $product->id,
            $rows[0]->getEbayItemId(),
            'product_update',
            LinkBayLog::STATUS_INFO,
            'PrestaShop product updated; stock will be pushed on next cron run.'
        );
    }

    /**
     * Reacts to product deletion by removing stale mapping rows.
     *
     * @param array<string, mixed> $params
     */
    public function onProductDelete(array $params): void
    {
        $product = $params['object'] ?? null;

        if (!$product instanceof \Product || empty($product->id)) {
            return;
        }

        $this->productLinkRepository->deleteByProduct((int) $product->id);
    }

    /**
     * Reacts to combination updates (stock changes on combinations).
     *
     * @param array<string, mixed> $params
     */
    public function onCombinationUpdate(array $params): void
    {
        $combination = $params['object'] ?? null;

        if (!$combination instanceof \Combination || empty($combination->id) || empty($combination->id_product)) {
            return;
        }

        $rows = $this->productLinkRepository->findByProduct((int) $combination->id_product);
        if (empty($rows)) {
            return;
        }

        $this->log(
            (int) $combination->id_product,
            $rows[0]->getEbayItemId(),
            'combination_update',
            LinkBayLog::STATUS_INFO,
            'PrestaShop combination updated; stock will be pushed on next cron run.'
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
            // Never break the hook execution.
        }
    }
}
