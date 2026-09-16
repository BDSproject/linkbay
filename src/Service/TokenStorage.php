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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Encrypts and decrypts OAuth tokens using the PrestaShop cookie key.
 *
 * Tokens are stored AES-256-CBC encrypted with an HMAC-SHA256 integrity tag so
 * a token can never be stored in plain text in the database.
 */
final class TokenStorage
{
    private const IV_LENGTH = 16;
    private const TAG_LENGTH = 32;

    /**
     * Encrypts a plain text value into a base64 encoded storage string.
     */
    public function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        $key = $this->deriveKey();
        $iv = random_bytes(self::IV_LENGTH);
        $cipherText = openssl_encrypt($plainText, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($cipherText === false) {
            return '';
        }

        $payload = $iv . $cipherText;
        $tag = hash_hmac('sha256', $payload, $key, true);

        return base64_encode($payload . $tag);
    }

    /**
     * Decrypts a storage string back into plain text.
     *
     * Returns an empty string when the input is invalid or the integrity check
     * fails, so callers never deal with corrupted tokens.
     */
    public function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }

        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < self::IV_LENGTH + self::TAG_LENGTH) {
            return '';
        }

        $key = $this->deriveKey();
        $tag = substr($raw, -self::TAG_LENGTH);
        $payload = substr($raw, 0, -self::TAG_LENGTH);
        $expectedTag = hash_hmac('sha256', $payload, $key, true);

        if (!hash_equals($expectedTag, $tag)) {
            return '';
        }

        $iv = substr($payload, 0, self::IV_LENGTH);
        $cipherText = substr($payload, self::IV_LENGTH);
        $plainText = openssl_decrypt($cipherText, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return $plainText === false ? '' : $plainText;
    }

    /**
     * Derives a stable 32 bytes key from the PrestaShop cookie key.
     */
    private function deriveKey(): string
    {
        return hash('sha256', (string) _COOKIE_KEY_, true);
    }
}
