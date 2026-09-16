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

use LinkBay\Cron\CronProcessor;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Cron endpoint.
 *
 * URL: /index.php?fc=module&module=linkbay&controller=cron&token=SECRET&type=all
 *
 * The "type" parameter accepts "stock", "import" or "all" (default).
 */
class LinkBayCronModuleFrontController extends ModuleFrontController
{
    /**
     * @var bool Do not render the front office theme.
     */
    public $display_header = false;
    public $display_footer = false;

    public function initContent(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $token = (string) Tools::getValue('token', '');

        if (!CronProcessor::validateToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid or missing token.']);
            exit;
        }

        $type = (string) Tools::getValue('type', CronProcessor::TYPE_ALL);
        $allowedTypes = [
            CronProcessor::TYPE_STOCK,
            CronProcessor::TYPE_IMPORT,
            CronProcessor::TYPE_PRICE,
            CronProcessor::TYPE_ALL,
        ];
        if (!in_array($type, $allowedTypes, true)) {
            $type = CronProcessor::TYPE_ALL;
        }

        $processor = new CronProcessor();

        try {
            $result = $processor->run($type);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        echo json_encode($result);
        exit;
    }
}
