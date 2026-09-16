<?php

declare(strict_types=1);

/**
 * CLI entry point for the LinkBay cron (development / local cron).
 *
 * Usage:
 *   docker exec -w /var/www/html linkbay-prestashop-1 php modules/linkbay/cron-cli.php all
 *   docker exec -w /var/www/html linkbay-prestashop-1 php modules/linkbay/cron-cli.php stock
 *   docker exec -w /var/www/html linkbay-prestashop-1 php modules/linkbay/cron-cli.php import
 *   docker exec -w /var/www/html linkbay-prestashop-1 php modules/linkbay/cron-cli.php price
 *
 * The "type" argument accepts "stock", "import", "price" or "all" (default).
 * This file is not used by the HTTP cron endpoint (controllers/front/cron.php).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require '/var/www/html/config/config.inc.php';

require __DIR__ . '/vendor/autoload.php';

$type = (string) ($argv[1] ?? 'all');
$result = (new \LinkBay\Cron\CronProcessor())->run($type);

echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
