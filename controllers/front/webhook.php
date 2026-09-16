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
use LinkBay\Repository\LinkBayCategoryMapRepository;
use LinkBay\Repository\LinkBayLogRepository;
use LinkBay\Repository\LinkBayProductRepository;
use LinkBay\Service\EbayApiClient;
use LinkBay\Service\EbayProductMapper;
use LinkBay\Service\StockSynchronizer;
use LinkBay\Service\TokenStorage;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * eBay webhook endpoint (real-time stock and price synchronization).
 *
 * eBay pushes ITEM_AVAILABILITY and ITEM_PRICE_REVISION events here. The
 * endpoint answers the destination challenge (SHA-256 of
 * challengeCode + verificationToken + endpoint), verifies the ECDSA signature
 * of each delivery against the eBay public key, and runs a targeted
 * synchronization for the affected listing. The 15-minute cron remains the
 * safety net for missed deliveries.
 *
 * URL: /module/linkbay/webhook
 */
class LinkBayWebhookModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    /** Config key caching the eBay signature public key (1 hour). */
    private const PUBLIC_KEY_CACHE_KEY = 'LINKBAY_WEBHOOK_PUBLIC_KEY';

    public function postProcess(): void
    {
        // Challenge de création de destination : eBay envoie un GET
        // ?challenge_code=… et attend {"challengeResponse":"<hex>"} où hex est
        // le SHA-256 de challengeCode + verificationToken + endpoint (dans cet
        // ordre exact).
        $challengeCode = (string) Tools::getValue('challenge_code', '');
        if ($challengeCode !== '') {
            $verificationToken = (string) \Configuration::get(Config::WEBHOOK_TOKEN);
            $endpoint = $this->context->link->getModuleLink('linkbay', 'webhook');
            $response = hash('sha256', $challengeCode . $verificationToken . $endpoint);

            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo json_encode(['challengeResponse' => $response]);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');

        $rawBody = (string) file_get_contents('php://input');
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            $this->answer(400, ['error' => 'Invalid payload.']);
        }

        // Vérification ECDSA de la signature (clé publique eBay par kid).
        if (!$this->verifySignature($rawBody)) {
            $this->answer(403, ['error' => 'Invalid signature.']);
        }

        // 3. Traiter l'événement : synchronisation ciblée de l'annonce concernée.
        $topic = (string) ($payload['topic'] ?? '');
        $data = $payload['data'] ?? [];
        $itemId = $this->extractItemId($data);

        // Traitement des événements.
        if ($topic === 'AUTHORIZATION_REVOCATION') {
            // L'utilisateur a révoqué l'autorisation de l'application : on
            // déconnecte le module pour éviter les erreurs en boucle.
            \Configuration::deleteByName(Config::ACCESS_TOKEN);
            \Configuration::deleteByName(Config::REFRESH_TOKEN);
            \Configuration::deleteByName(Config::TOKEN_EXPIRES_AT);
            $this->log(0, '', 'webhook', 'error', 'eBay authorization was revoked; the module has been disconnected. Click "Connect to eBay" to reconnect.');
            $this->answer(200, ['status' => 'ok', 'topic' => $topic]);
        }

        if ($topic === 'MARKETPLACE_ACCOUNT_DELETION') {
            // Demande de suppression du compte eBay : on supprime les données
            // personnelles gérées par le module (liens et journaux).
            (new LinkBayProductRepository())->deleteAll();
            (new LinkBayLogRepository())->clear();
            \Configuration::deleteByName(Config::ACCESS_TOKEN);
            \Configuration::deleteByName(Config::REFRESH_TOKEN);
            \Configuration::deleteByName(Config::TOKEN_EXPIRES_AT);
            $this->log(0, '', 'webhook', 'success', 'eBay account deletion requested; the module data has been removed.');
            $this->answer(200, ['status' => 'ok', 'topic' => $topic]);
        }

        try {
            if ($topic === 'ITEM_PRICE_REVISION') {
                $this->runPriceRefresh($itemId);
            } elseif ($topic === 'ITEM_AVAILABILITY') {
                $this->runTargetedStockSync($itemId);
            } else {
                // Sujet inconnu : le cron de secours couvrira l'événement.
                $this->answer(200, ['status' => 'ignored', 'topic' => $topic]);
            }
        } catch (\Throwable $e) {
            $this->log(0, $itemId, 'webhook', 'error', $topic . ': ' . $e->getMessage());
            $this->answer(200, ['status' => 'error', 'message' => $e->getMessage()]);
        }

        $this->answer(200, ['status' => 'ok', 'topic' => $topic, 'item_id' => $itemId]);
    }

    /**
     * Verifies the ECDSA signature of a delivery using eBay's public key.
     */
    private function verifySignature(string $rawBody): bool
    {
        $header = (string) ($_SERVER['HTTP_X_EBAY_SIGNATURE'] ?? '');

        if ($header === '') {
            return false;
        }

        $envelope = json_decode(base64_decode($header, true) ?: '', true);

        if (!is_array($envelope)) {
            return false;
        }

        $kid = (string) ($envelope['kid'] ?? '');
        $signature = base64_decode($this->base64UrlDecode((string) ($envelope['signature'] ?? '')), true);

        if ($kid === '' || $signature === false) {
            return false;
        }

        $publicKeyPem = $this->fetchPublicKeyPem($kid);
        if ($publicKeyPem === '') {
            return false;
        }

        return (bool) openssl_verify($rawBody, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
    }

    /**
     * Fetches (and caches for one hour) the eBay ECDSA public key for a kid.
     */
    private function fetchPublicKeyPem(string $kid): string
    {
        $cached = \Configuration::get(self::PUBLIC_KEY_CACHE_KEY . '_KEY_' . $kid);
        $cachedAt = (int) \Configuration::get(self::PUBLIC_KEY_CACHE_KEY . '_AT_' . $kid);

        if (is_string($cached) && $cached !== '' && $cachedAt > time() - 3600) {
            return $cached;
        }

        $client = new EbayApiClient(new TokenStorage());

        try {
            $data = $client->getPublicKey($kid);
        } catch (\Throwable $e) {
            return '';
        }

        $key = (string) ($data['key'] ?? '');
        if ($key === '') {
            return '';
        }

        $der = base64_decode($key, true);
        if ($der === false) {
            return '';
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";

        \Configuration::updateValue(self::PUBLIC_KEY_CACHE_KEY . '_KEY_' . $kid, $pem);
        \Configuration::updateValue(self::PUBLIC_KEY_CACHE_KEY . '_AT_' . $kid, (string) time());

        return $pem;
    }

    private function base64UrlDecode(string $value): string
    {
        return strtr($value, '-_', '+/');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractItemId(array $data): string
    {
        foreach (['itemId', 'item_id', 'legacyItemId', 'listingId'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return '';
    }

    /**
     * Pulls the current stock of a single listing from eBay into PrestaShop.
     */
    private function runTargetedStockSync(string $itemId): void
    {
        if ($itemId === '') {
            throw new \RuntimeException('The notification does not contain an item id; the cron will perform the sync.');
        }

        $stockSynchronizer = $this->buildStockSynchronizer();
        $result = $stockSynchronizer->syncItemFromEbay($itemId);

        $this->log(0, $itemId, 'webhook', $result['synced'] > 0 ? 'success' : 'error', 'Real-time stock sync: ' . ($result['error'] ?? 'ok'));
    }

    /**
     * Refreshes price and stock of a single listing (same path as an import).
     */
    private function runPriceRefresh(string $itemId): void
    {
        if ($itemId === '') {
            throw new \RuntimeException('The notification does not contain an item id; the cron will perform the sync.');
        }

        $apiClient = new EbayApiClient(new TokenStorage());
        $mapper = new EbayProductMapper();
        $importManager = new \LinkBay\Service\ImportManager(
            $apiClient,
            $mapper,
            new LinkBayProductRepository(),
            new LinkBayLogRepository(),
            new LinkBayCategoryMapRepository()
        );

        $summary = $importManager->manualImport([$itemId]);

        $this->log(
            0,
            $itemId,
            'webhook',
            ($summary['errors'] ?? $summary['skipped'] ?? 0) === 0 ? 'success' : 'error',
            'Real-time price refresh: ' . ($summary['updated'] ?? 0) . ' updated, ' . ($summary['skipped'] ?? 0) . ' skipped.'
        );
    }

    private function buildStockSynchronizer(): StockSynchronizer
    {
        $tokenStorage = new TokenStorage();

        return new StockSynchronizer(
            new EbayApiClient($tokenStorage),
            new EbayProductMapper(),
            new LinkBayProductRepository(),
            new LinkBayLogRepository()
        );
    }

    private function log(int $idProduct, string $ebayItemId, string $action, string $status, string $message): void
    {
        try {
            $log = new \LinkBay\Entity\LinkBayLog();
            $log->setIdProduct($idProduct)
                ->setEbayItemId($ebayItemId)
                ->setAction($action)
                ->setStatus($status)
                ->setMessage($message);

            (new LinkBayLogRepository())->add($log);
        } catch (\Throwable $e) {
            // Logging must never break the webhook answer.
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function answer(int $status, array $body): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
