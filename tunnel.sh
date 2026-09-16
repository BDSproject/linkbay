#!/bin/bash
# LinkBay — tunnel éphémère auto-réparant.
#
# Démarre un quick tunnel Cloudflare et re-pointe automatiquement le domaine
# PrestaShop sur la nouvelle URL (l'URL change à chaque démarrage).
# Géré par launchd (KeepAlive) : en cas de chute, le script est relancé et
# la boutique suit la nouvelle URL.
set -u

TUNNEL_LOG=/tmp/cloudflared-linkbay.log
: > "$TUNNEL_LOG"

/opt/homebrew/bin/cloudflared tunnel --url http://localhost:8080 --no-autoupdate >> "$TUNNEL_LOG" 2>&1 &
CLOUDFLARED_PID=$!

# 1. Attendre que l'URL apparaisse dans le journal (max 60 s)
URL=""
for _ in $(seq 1 60); do
    URL=$(grep -oE "https://[a-z0-9-]+\.trycloudflare\.com" "$TUNNEL_LOG" | head -1)
    [ -n "$URL" ] && break
    sleep 1
done

if [ -z "$URL" ]; then
    echo "$(date '+%F %T') tunnel: aucune URL détectée" >> "$TUNNEL_LOG"
    wait "$CLOUDFLARED_PID"
    exit 1
fi

HOST="${URL#https://}"
echo "$(date '+%F %T') tunnel actif: $URL" >> "$TUNNEL_LOG"

# 2. Re-pointer le domaine PrestaShop sur le nouvel hôte (avec reprises :
#    la stack Docker peut être en cours de démarrage après un reboot)
for attempt in $(seq 1 30); do
    if /opt/homebrew/bin/docker exec -w /var/www/html linkbay-prestashop-1 php -r '
require "config/config.inc.php";
$d = $argv[1];
Configuration::updateValue("PS_SHOP_DOMAIN", $d);
Configuration::updateValue("PS_SHOP_DOMAIN_SSL", $d);
Db::getInstance()->execute("UPDATE " . _DB_PREFIX_ . "shop_url SET domain=\"" . pSQL($d) . "\", domain_ssl=\"" . pSQL($d) . "\" WHERE id_shop_url = 1");
Tools::clearAllCache();
echo "domain -> " . $d . "\n";
' "$HOST" >> "$TUNNEL_LOG" 2>&1; then
        echo "$(date '+%F %T') domaine PrestaShop -> $HOST" >> "$TUNNEL_LOG"
        break
    fi
    sleep 5
done

# 3. Rester au premier plan tant que le tunnel vit (launchd le relancera s'il meurt)
wait "$CLOUDFLARED_PID"
