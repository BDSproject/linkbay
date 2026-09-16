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

namespace LinkBay\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Central registry of module configuration keys.
 *
 * Every value is stored through the native PrestaShop Configuration service so
 * that uninstall can reliably remove all of them.
 */
final class Config
{
    /** eBay OAuth application client id (shared keyset, same for every shop). */
    public const CLIENT_ID = 'LINKBAY_CLIENT_ID';

    /** eBay OAuth application client secret (shared keyset, same for every shop). */
    public const CLIENT_SECRET = 'LINKBAY_CLIENT_SECRET';

    /** OAuth redirect URI / RuName (the client's eBay code, entered in the back office). */
    public const REDIRECT_URI = 'LINKBAY_REDIRECT_URI';

    /** eBay environment: "production" or "sandbox". */
    public const ENVIRONMENT = 'LINKBAY_ENVIRONMENT';

    /** eBay seller username used to fetch listings through the Buy Browse API. */
    public const EBAY_USERNAME = 'LINKBAY_EBAY_USERNAME';

    /** PrestaShop tax rules group applied to imported products (0 = none). */
    public const TAX_RULE = 'LINKBAY_TAX_RULE';

    /** eBay site id used by the Trading API. */
    public const EBAY_SITE_ID = 'LINKBAY_EBAY_SITE_ID';

    /** Encrypted OAuth access token. */
    public const ACCESS_TOKEN = 'LINKBAY_ACCESS_TOKEN';

    /** Encrypted OAuth refresh token. */
    public const REFRESH_TOKEN = 'LINKBAY_REFRESH_TOKEN';

    /** Unix timestamp at which the access token expires. */
    public const TOKEN_EXPIRES_AT = 'LINKBAY_TOKEN_EXPIRES_AT';

    /** Default PrestaShop category id used when no category map matches. */
    public const DEFAULT_CATEGORY = 'LINKBAY_DEFAULT_CATEGORY';

    /** Recommended cron frequency expressed in minutes (informational). */
    public const SYNC_FREQUENCY = 'LINKBAY_SYNC_FREQUENCY';

    /** Whether automatic import is enabled. */
    public const AUTO_IMPORT = 'LINKBAY_AUTO_IMPORT';

    /** Secret token guarding the cron endpoint. */
    public const CRON_TOKEN = 'LINKBAY_CRON_TOKEN';

    /** Secret token expected on eBay webhook deliveries. */
    public const WEBHOOK_TOKEN = 'LINKBAY_WEBHOOK_TOKEN';

    /** Id of the AUTHORIZATION_REVOCATION notification subscription (empty = disabled). */
    public const WEBHOOK_AUTH_REVOCATION_SUB = 'LINKBAY_WEBHOOK_AUTH_REVOCATION_SUB';

    /** Id of the MARKETPLACE_ACCOUNT_DELETION notification subscription (empty = disabled). */
    public const WEBHOOK_ACCOUNT_DELETION_SUB = 'LINKBAY_WEBHOOK_ACCOUNT_DELETION_SUB';

    /** Stock buffer mode: "none", "percentage" or "fixed". */
    public const BUFFER_MODE = 'LINKBAY_BUFFER_MODE';

    /** Stock buffer value (percentage or fixed quantity, depending on the mode). */
    public const BUFFER_VALUE = 'LINKBAY_BUFFER_VALUE';

    /** Whether dynamic price rules also update the PrestaShop product price. */
    public const PRICE_RULE_APPLY_TO_PS = 'LINKBAY_PRICE_RULE_APPLY_TO_PS';

    /** Support contact email displayed in the back office. */
    public const SUPPORT_EMAIL = 'LINKBAY_SUPPORT_EMAIL';

    /**
     * Shared eBay application (single keyset for every client).
     *
     * eBay allows ONE keyset per developer account, so the Client ID and the
     * Client Secret are the same for every shop. Only the RuName (redirect URI)
     * differs from one client to another.
     */
    private const SHARED_CLIENT_ID = 'BEASSIST-beassist-PRD-a624625de-737aecff';
    private const SHARED_CLIENT_SECRET = 'PRD-62901719d24c-7ba3-46bd-81c2-d278';

    /** Default values applied during install. */
    public const DEFAULTS = [
        self::CLIENT_ID => self::SHARED_CLIENT_ID,
        self::CLIENT_SECRET => self::SHARED_CLIENT_SECRET,
        self::REDIRECT_URI => '',
        self::ENVIRONMENT => 'production',
        self::EBAY_SITE_ID => '0',
        self::TAX_RULE => '0',
        self::DEFAULT_CATEGORY => '0',
        self::SYNC_FREQUENCY => '15',
        self::AUTO_IMPORT => '1',
        self::BUFFER_MODE => 'none',
        self::BUFFER_VALUE => '0',
        self::PRICE_RULE_APPLY_TO_PS => '0',
        self::SUPPORT_EMAIL => 'contact@beassistnow.com',
    ];

    /** All keys the module writes; used for a clean uninstall. */
    public const ALL_KEYS = [
        self::CLIENT_ID,
        self::CLIENT_SECRET,
        self::REDIRECT_URI,
        self::ENVIRONMENT,
        self::EBAY_USERNAME,
        self::TAX_RULE,
        self::EBAY_SITE_ID,
        self::ACCESS_TOKEN,
        self::REFRESH_TOKEN,
        self::TOKEN_EXPIRES_AT,
        self::DEFAULT_CATEGORY,
        self::SYNC_FREQUENCY,
        self::AUTO_IMPORT,
        self::CRON_TOKEN,
        self::WEBHOOK_TOKEN,
        self::WEBHOOK_AUTH_REVOCATION_SUB,
        self::WEBHOOK_ACCOUNT_DELETION_SUB,
        self::BUFFER_MODE,
        self::BUFFER_VALUE,
        self::PRICE_RULE_APPLY_TO_PS,
        self::SUPPORT_EMAIL,
    ];

    private function __construct()
    {
    }
}
