# LinkBay

LinkBay connects PrestaShop (1.7.8+ / 8.0+ / 9.0+) to eBay. It imports your active eBay
listings into PrestaShop and keeps stock synchronized in both directions through
a cron endpoint.

> **Compatibility**: PHP **7.4** or higher, PrestaShop **1.7.8** and above.

## Features

- OAuth2 connection to an eBay account (with automatic token refresh).
- Automatic import of every active eBay listing.
- Manual import by browsing eBay categories and selecting specific items.
- Bidirectional stock synchronization (eBay ↔ PrestaShop) via cron.
- Multi-variation support (attributes + combinations with per-combination SKU).
- Category mapping (eBay category → PrestaShop category).
- **Visual dashboard** — a checklist of the problems to fix, each with a
  one-click "repair automatically" action.
- **Competitive price rules (dynamic pricing)** — adjust the eBay price
  automatically according to your stock (e.g. "stock > 50 → −5%", "stock < 5
  → +10%").
- Action logs and error tracking.

## Requirements

- PHP 7.4 or higher.
- PrestaShop 1.7.8+ / 8.0+ / 9.0+.
- Guzzle 7 — declared as a Composer dependency and bundled in the module's
  `vendor/` directory (PrestaShop 8 already ships Guzzle, PrestaShop 9 does not).

## Installation

1. Copy the `linkbay` folder into the `modules/` directory of your shop.
2. In the back office go to **Modules → Module Manager**, find *LinkBay* and
   click **Install**.
3. The module creates its own tables (`linkbay_product_link`, `linkbay_log`,
   `linkbay_category_map`) and registers its hooks and menu entries.

> No core PrestaShop table is modified. Uninstalling the module removes its
> tables and every configuration key.

## Connecting to eBay

> **No eBay developer account required.** The eBay application is provided by
> Beassisted as a single shared keyset: the *Client ID* and *Client Secret* are
> the same for every shop (and are already pre-configured), so eBay API rate
> limits are never a concern for you. What is unique to your shop is your
> **eBay code (RuName)**.

1. In the back office go to **LinkBay → Configuration**.
2. In the **eBay API settings** card, paste your **eBay code (RuName)** — the
   value provided with your license (e.g. `BEASSISTED-BEASSIST-beassi-xxxxxxx`).
   This is the only value you enter; nothing else is needed to link this shop
   to its dedicated eBay connection.
3. Click **Save settings**, then **Connect to eBay** and authorize your account
   on eBay. The access and refresh tokens are stored encrypted
   (AES-256-CBC + HMAC, keyed with the shop's `_COOKIE_KEY_`).
4. Fill in your **eBay username** (used to fetch your active listings), select
   the **eBay marketplaces** you sell on, then choose a **Default PrestaShop
   category** and the **cron frequency**.
5. Toggle **Enable automatic import** as needed.
6. Choose the **Tax rule for imported products**. eBay prices include taxes;
   when a rule is selected, the module stores the tax-excluded price and
   assigns the rule, so the shop displays the same tax-included price as eBay
   (variation prices included). Leave "No tax rule" to import prices as-is.
   International taxes (customs, IOSS, destination VAT) are collected by eBay
   itself — the module does not duplicate them.

The **Logs** section shows import and synchronization activity.

## Category mapping

In the **Category mapping** section you can map an eBay category ID to a
PrestaShop category. During import each listing is placed in:

1. the category from the mapping table (when present), otherwise
2. the configured default category, otherwise
3. the shop home category.

## Manual import

Open the **Import** menu entry to browse the eBay categories through a
**dropdown**: pick a category to drill down (a "← Back" entry goes up one
level) and tick the listings you want to import, then press **Import
selected**. The "All categories" entry lists every active listing.

The page stays light on purpose:

- The eBay category tree is cached locally (7 days), so navigation costs no
  API round-trip after the first visit.
- Listings are only fetched when a category is actually selected; selecting a
  category uses a single targeted search instead of enumerating every root
  category.

## Dashboard (problems to fix)

The **Dashboard** menu entry shows a checklist of only the issues that need
your attention — never raw logs. Each item carries a count and a
**Repair automatically** button:

- **The module is not connected to eBay / credentials incomplete / username
  missing** → opens the configuration.
- **Some listings failed to import** → re-runs the import for exactly those
  listings.
- **Stock synchronization errors** → re-runs the synchronization in both
  directions.
- **Some product images could not be downloaded** → re-runs the import of the
  affected listings.
- **Some combinations no longer exist on eBay** → removes the obsolete
  PrestaShop combinations (eBay → PrestaShop cleanup). When the eBay listing
  has *gained* variations, the next import creates them automatically.
- **Products deactivated because their listing disappeared** → reactivates
  them (e.g. if the listing ended by mistake).

After a successful repair, the stale error entries are cleared so the
checklist returns to "Everything is synchronized". What still fails writes a
fresh error and stays visible.

> eBay offers no seller-side API to create variations on an existing classic
> listing, so a variation removed on eBay is cleaned up in PrestaShop rather
> than re-created on eBay.

## Cron / stock synchronization

The cron endpoint is:

```
https://your-shop.com/index.php?fc=module&module=linkbay&controller=cron&token=SECRET
```

The exact URL (with your secret token) is shown on the configuration page.
Schedule it on your server, e.g. every 15 minutes:

```cron
*/15 * * * * /usr/bin/curl -s "https://your-shop.com/index.php?fc=module&module=linkbay&controller=cron&token=SECRET&type=all" > /dev/null 2>&1
```

The `type` parameter accepts:

- `stock` — run stock synchronization only,
- `import` — run automatic import only (respects the *Enable automatic import* setting),
- `price` — run the dynamic price rules only,
- `all` — run everything (default).

Synchronization is bidirectional:

- **eBay → PrestaShop**: current eBay quantities are fetched and applied to the
  mapped products/combinations.
- **PrestaShop → eBay**: the current PrestaShop stock is compared against the
  last synchronized value stored in the mapping table. Only real changes are
  pushed back (via `ReviseInventoryStatus`), which prevents push/pull loops.

Stock changes made in the back office are detected through the
`actionObjectProductUpdateAfter`, `actionObjectProductDeleteAfter` and
`actionObjectCombinationUpdateAfter` hooks and are pushed on the next cron run.

## Dynamic pricing rules

The configuration page contains a **Competitive price rules** card. Each rule
combines a stock condition with a price adjustment:

- **If stock is above X** — e.g. *above 50*, adjust the eBay price by **−5%**
  to sell the surplus off.
- **If stock is below Y** — e.g. *below 5*, adjust by **+10%** to maximize the
  margin on scarcity.
- Rules are evaluated by **priority** (lowest first); the first matching rule
  wins. Between the two bounds, no rule applies and the base eBay price is
  kept.

The adjustment is always computed from the **base price** — the eBay price at
the last import — so percentages never compound on themselves. A per-product
table shows the current stock, base price, matched rule, target price and its
status. Rules are evaluated on every cron run (`type=price`, or the full
`all`), and can also be triggered manually with **Apply the rules now**.

Two honest limitations, by design:

- **Classic listings** (without a SKU) cannot be updated by API — eBay offers
  no seller-side endpoint to change their price. The module computes the
  target and marks the product *Classic listing (eBay forbids API price
  changes)*. Enable **"Also apply the computed price to the PrestaShop
  product"** so the PrestaShop price at least follows your strategy.
- **Inventory-managed listings** (with a SKU) get the target pushed to eBay
  through the Sell Inventory `bulk_update_price_quantity` API.

## eBay API approach

For consistency the module uses eBay's **REST APIs**:

- **Buy Browse API** (`/buy/browse/v1`) for listing retrieval: the seller's
  active listings are found through a seller-filtered search over the root
  categories of every **selected marketplace** (multi-select in the module
  settings, e.g. eBay FR + DE + US), and full details (title, description,
  images, price, quantity estimate, category) come from `GET /item/{item_id}`.
  Listings are keyed by the classic eBay **item id** and tagged with their
  marketplace. Fill in your **eBay username** in the module settings.
  **Multi-variation listings** (grades, colors, sizes…) are supported:
  variation children are fetched through the item group endpoint and imported
  as PrestaShop combinations, each with its own price, quantity and varying
  aspects.
- **Sell Inventory API** (`/sell/inventory/v1`) for stock pushes back to eBay
  (`bulk_update_price_quantity`). Only listings managed through eBay inventory
  (with a SKU) can be updated; classic listings are imported read-only.
- **Commerce Taxonomy API** (`/commerce/taxonomy/v1`) for category browsing.

OAuth scopes requested: the base scope (`https://api.ebay.com/oauth/api_scope`)
and `https://api.ebay.com/oauth/api_scope/sell.inventory` — both must be
enabled for the application keyset in the eBay developer portal. The deprecated
Trading API (now returning `410 Gone`) is no longer used.

## Stock buffer

A **stock buffer** keeps a safety margin in PrestaShop: the module sends **less
stock to eBay than you really have** (e.g. 10 real, 8 sent), so you can still
honor orders if a product is damaged or missing. Configure it in the back
office:

- a **default buffer** for all products: none, a **percentage of the stock to
  send** (e.g. 80%) or a **fixed quantity kept in reserve** (e.g. 2),
- a **per-product override** (percentage of the stock to send; empty = default).

The buffer applies when stock is pushed from PrestaShop to eBay (inventory
managed listings). The eBay → PrestaShop pull always applies the real eBay
quantities.

## Real-time webhooks

The module exposes a webhook endpoint (`/module/linkbay/webhook`) that answers
eBay's destination challenge (SHA-256 of challenge code + verification token +
endpoint) and verifies delivery signatures (ECDSA, public key fetched from
eBay by key id). The back office can enable the **eBay account alerts**:
`AUTHORIZATION_REVOCATION` (the module disconnects itself when the user revokes
the app) and `MARKETPLACE_ACCOUNT_DELETION` (the module removes its data on
account deletion requests).

> eBay does **not** offer seller-side webhooks for item quantity or price
> changes (the item topics are buyer-side, reserved to eBay Partner Network
> partners). Stock and price synchronization therefore run through the
> scheduled cron — this is the same situation for every eBay seller.

## Database tables

| Table | Purpose |
| --- | --- |
| `linkbay_product_link` | Maps an eBay ItemID to a PrestaShop product/combination and stores the last synchronized quantity. |
| `linkbay_log` | Action logs. |
| `linkbay_category_map` | Maps an eBay category ID to a PrestaShop category ID. |

All tables use the PrestaShop prefix and MySQL engine placeholders and are
removed on uninstall.

## Docker development environment

A `docker-compose.yml` runs a full PrestaShop 8.2 + MariaDB stack and bind-mounts
this module straight into the shop for live development.

```bash
colima start            # macOS only, once: start the Docker runtime
docker-compose up -d    # start PrestaShop + MariaDB
```

- Shop: <http://localhost:8080>
- Admin: <http://localhost:8080/admin> — `admin@beassisted.fr` / `admin123456`
- The module is mounted at `modules/linkbay`; edits appear immediately (clear the
  cache after structural changes: `docker exec linkbay-prestashop-1 rm -rf var/cache/prod`).

Install the module from **Modules → Module Manager → LinkBay → Install**, or:

```bash
docker exec -w /var/www/html linkbay-prestashop-1 php bin/console prestashop:module install linkbay
```

### Testing the eBay OAuth callback through a tunnel

eBay cannot call back to `localhost`, so expose the shop with a Cloudflare quick
tunnel:

```bash
cloudflared tunnel --url http://localhost:8080
```

This prints a `https://<random>.trycloudflare.com` URL. Point the shop domain at
it (the OAuth callback must be served on the same host), then register the
callback URL in the eBay developer portal:

```bash
docker exec -w /var/www/html linkbay-prestashop-1 php -r '
require "config/config.inc.php";
$d = $argv[1];
Configuration::updateValue("PS_SHOP_DOMAIN", $d);
Configuration::updateValue("PS_SHOP_DOMAIN_SSL", $d);
Db::getInstance()->execute("UPDATE " . _DB_PREFIX_ . "shop_url SET domain=\"" . pSQL($d) . "\", domain_ssl=\"" . pSQL($d) . "\" WHERE id_shop_url = 1");
Tools::clearAllCache();
echo "domain -> $d\n";
' 'https://<random>.trycloudflare.com'
```

- eBay callback URL to register (RuName target, or sandbox redirect URL):
  `https://<random>.trycloudflare.com/index.php?fc=module&module=linkbay&controller=oauth`
- Revert to local development by re-running the command with `localhost:8080`.

> The quick-tunnel hostname changes on every restart. For a stable URL, set up a
> **named tunnel** (`cloudflared tunnel login`, then `cloudflared tunnel create
> linkbay`) and configure it once.

### Local cron (macOS / launchd)

The cron can run locally without any tunnel, straight inside the Docker
container (see `cron-cli.php`):

```bash
docker exec -w /var/www/html linkbay-prestashop-1 php modules/linkbay/cron-cli.php all
```

A launchd agent (`~/Library/LaunchAgents/com.beassisted.linkbay-cron.plist`)
runs this every 15 minutes and logs to `/tmp/linkbay-cron.log`. To run a sync
immediately:

```bash
launchctl kickstart gui/$(id -u)/com.beassisted.linkbay-cron
```

## Development

```bash
composer install
_PS_ROOT_DIR_=/path/to/prestashop vendor/bin/phpstan analyse -c phpstan.neon --no-progress
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
```

PHPStan runs at level 8 and uses the official
[`prestashop/php-dev-tools`](https://github.com/PrestaShop/php-dev-tools) bootstrap:
point `_PS_ROOT_DIR_` to a PrestaShop installation so core classes can be
resolved. A few `ignoreErrors` patterns cover known nullable DocBlock
inaccuracies of PrestaShop core (`Context::$link`, `Context::$cookie`, ...).

## Translations

Translation files for English, French, German, Spanish and Italian are provided
in `translations/`. Every user-facing string is handled through
`$this->trans('...', [], 'Modules.Linkbay.Admin')`.

## License

Academic Free License 3.0 (AFL-3.0). See `LICENSE.txt`.
