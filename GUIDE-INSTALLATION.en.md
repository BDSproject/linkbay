# LinkBay — Installation & User Guide

Thank you for choosing **LinkBay**, the eBay connector for PrestaShop.
This guide walks you through installation, configuration and stock/price synchronization.

---

## 1. Overview

LinkBay **imports your eBay listings** into PrestaShop and **synchronizes stock** in both directions, continuously via cron. It also handles **variations**, **taxes**, **multi-marketplace**, a **stock buffer** and **dynamic pricing**.

> **No eBay developer account required**: the eBay app is shared and provided by Beassisted. You only paste your **eBay code (RuName)** provided with your license.

---

## 2. Requirements

| Item | Requirement |
|---|---|
| PrestaShop | 1.7.8 or higher |
| PHP | 7.4 or higher |
| eBay | An active **seller** account |
| eBay code (RuName) | Provided with your license (e.g. `BEASSISTED-BEASSIST-beassi-xxxxxxx`) |

---

## 3. Installation

1. In the back office: **Modules → Module Manager**.
2. Click **"Add a new module"** and upload `linkbay.zip`.
3. Click **"Install"**. The module creates its own tables and its **LinkBay** menu (no PrestaShop table is modified).

---

## 4. Connect to eBay

1. Go to **LinkBay → Configuration**.
2. In the **"eBay API settings"** card, paste your **eBay code (RuName)** — the value provided with your license (e.g. `BEASSISTED-BEASSIST-beassi-xxxxxxx`). This is the only value to enter: the eBay app is shared and already pre-configured by Beassisted.
3. Click **"Save settings"**, then **"Connect to eBay"**.
4. Log in to your eBay **seller** account and **authorize** the application.

> 💬 Need help? **contact@beassistnow.com**

---

## 5. Recommended settings

In **LinkBay → Configuration**:

- **eBay username**: your seller username (e.g. `bridgefurb`).
- **Marketplaces**: tick the eBay site(s) where you sell (France, Germany, UK…).
- **Tax rule**: choose the PrestaShop rule applied **to imported products**. eBay includes VAT; the module converts to tax-excluded price.
  - ⚠️ If you use a **third-party VAT module**, select **"No tax rule"** to avoid any conflict.
- **Default category**: the destination PrestaShop category.
- **Stock buffer**: keep a safety margin (e.g. send 80% of the real stock).
- **Dynamic prices**: create your rules (e.g. "if stock > 50, lower by 5%; if stock < 5, raise by 10%").

---

## 6. Usage

### Automatic import
The **cron** imports your active listings and syncs stock. Frequency is configurable (default 15 min).

### Manual import
**LinkBay → Import**: browse the eBay categories via the **dropdown**, tick the listings, then **"Import selected"**.

### Dashboard
**LinkBay → Dashboard**: a checklist of issues to fix, with **"Repair automatically"** (e.g. obsolete combinations, import failures, prices). One click.

---

## 7. Compatibility with your other modules

- LinkBay manages **only products mapped to eBay**. It never modifies products you manage elsewhere.
- It never changes the shop's **global tax settings**.
- The **"Compatibility & conflicts"** card lists active modules that also react to product changes.
- **VAT**: if a third-party module handles VAT, choose **"No tax rule"** in LinkBay — it lets that module work (no conflict).

---

## 8. Troubleshooting

| Issue | Solution |
|---|---|
| "invalid_grant" after returning from eBay | Connection already succeeded (duplicate callback call). Reload the back office. |
| Listings not found | Check your **eBay username** and the **marketplaces** selected. |
| Prices unchanged | Make sure a price rule matches the current stock. |
| Connection lost | Click **"Connect to eBay"** again in the configuration. |

---

## 9. Support

📧 **contact@beassistnow.com** — Beassisted is at your disposal for setup and any question.

---

*LinkBay — Precision-built PrestaShop modules · © Beassisted*
