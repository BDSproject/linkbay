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

use LinkBay\Service\EbayApiClient;
use LinkBay\Service\TokenStorage;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * eBay OAuth callback (token-less on purpose).
 *
 * eBay redirects here after the merchant authorizes the application. Unlike the
 * admin controller, this URL carries no PrestaShop admin token, so the redirect
 * URL registered in the eBay developer portal never expires — the admin token
 * changes on module reinstall, employee re-login or _COOKIE_KEY_ rotation.
 *
 * URL: /index.php?fc=module&module=linkbay&controller=oauth
 */
class LinkBayOauthModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    public function postProcess(): void
    {
        $code = Tools::getValue('code');
        $state = Tools::getValue('state');

        if (!is_string($code) || $code === '' || !is_string($state) || $state === '') {
            $this->output(false, $this->trans('Missing OAuth parameters.', [], 'Modules.Linkbay.Admin'));

            return;
        }

        if (!EbayApiClient::validateOauthState($state)) {
            $this->output(false, $this->trans('Invalid OAuth state. Please try connecting again.', [], 'Modules.Linkbay.Admin'));

            return;
        }

        $client = new EbayApiClient(new TokenStorage());

        try {
            $tokens = $client->exchangeAuthorizationCode($code);
            $client->storeTokens($tokens);
            $this->redirectToAdmin();
        } catch (\Throwable $e) {
            // Un code d'autorisation OAuth est à usage unique. Si le callback est
            // appelé deux fois (rechargement, double requête), le premier échange
            // réussit et le second échoue avec "invalid_grant" — alors qu'on est
            // déjà connecté. Dans ce cas on redirige au lieu d'afficher une erreur.
            if ($client->isAuthenticated()) {
                $this->redirectToAdmin();
            }

            $this->output(false, $e->getMessage());
        }
    }

    /**
     * Renders a minimal result page and stops execution (no Smarty template,
     * same approach as the cron front controller).
     */
    private function output(bool $success, string $message): void
    {
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="robots" content="noindex,nofollow"><title>LinkBay — eBay</title></head><body>';

        $style = $success ? 'font-family:sans-serif' : 'font-family:sans-serif;color:#c00';
        echo '<p style="' . $style . '">' . htmlspecialchars($message, ENT_QUOTES) . '</p>';
        echo '</body></html>';
        exit;
    }

    /**
     * Returns to the LinkBay back office page with a valid admin token.
     *
     * The admin session cookie ("psAdmin") holds the employee id; it is shared
     * with the front office because both cookies live on the same domain/path.
     */
    private function redirectToAdmin(): void
    {
        $adminCookie = new \Cookie('psAdmin');
        $idEmployee = (int) ($adminCookie->id_employee ?? 0);
        $employee = new \Employee($idEmployee);

        if ($idEmployee > 0 && \Validate::isLoadedObject($employee)) {
            $this->context->employee = $employee;
            \Tools::redirectAdmin($this->context->link->getAdminLink('AdminLinkBay'));
        }

        $this->output(true, $this->trans('eBay account connected successfully.', [], 'Modules.Linkbay.Admin'));
    }
}
