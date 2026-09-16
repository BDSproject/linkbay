# LinkBay — Installations- und Benutzerhandbuch

Vielen Dank, dass Sie sich für **LinkBay** entschieden haben, den eBay-Anbieter für PrestaShop.
Dieses Handbuch begleitet Sie Schritt für Schritt von der Installation bis zur Synchronisierung von Bestand und Preisen.

---

## 1. Übersicht

LinkBay **importiert Ihre eBay-Angebote** in PrestaShop und **synchronisiert den Bestand** in beide Richtungen, kontinuierlich über den Cron. Es verwaltet auch **Varianten**, **Steuern**, **Multi-Marktplatz**, einen **Sicherheitspuffer** und **dynamische Preise**.

> **Kein eBay-Entwicklerkonto erforderlich**: Die eBay-App wird von Beassisted gemeinsam bereitgestellt. Sie müssen nur Ihren **eBay-Code (RuName)** aus Ihrer Lizenz einfügen.

---

## 2. Voraussetzungen

| Element | Voraussetzung |
|---|---|
| PrestaShop | 1.7.8 oder höher |
| PHP | 7.4 oder höher |
| eBay | Ein aktives **Verkäufer**-Konto |
| eBay-Code (RuName) | In Ihrer Lizenz enthalten (z. B. `BEASSISTED-BEASSIST-beassi-xxxxxxx`) |

---

## 3. Installation

1. Im Backoffice: **Module → Modulverwaltung**.
2. Klicken Sie auf **„Modul hinzufügen"** und laden Sie `linkbay.zip` hoch.
3. Klicken Sie auf **„Installieren"**. Das Modul erstellt seine eigenen Tabellen und sein Menü **LinkBay** (keine PrestaShop-Tabelle wird geändert).

---

## 4. Mit eBay verbinden

1. Gehen Sie zu **LinkBay → Konfiguration**.
2. Fügen Sie in der Karte **„eBay-API-Einstellungen"** Ihren **eBay-Code (RuName)** ein — den in Ihrer Lizenz enthaltenen Wert (z. B. `BEASSISTED-BEASSIST-beassi-xxxxxxx`). Dies ist der einzige einzugebende Wert: Die eBay-App ist gemeinsam und bereits von Beassisted vorkonfiguriert.
3. Klicken Sie auf **„Einstellungen speichern"** und dann auf **„Mit eBay verbinden"**.
4. Melden Sie sich bei Ihrem eBay-**Verkäufer**-Konto an und **autorisieren** Sie die Anwendung.

> 💬 Hilfe? **contact@beassistnow.com**

---

## 5. Empfohlene Einstellungen

In **LinkBay → Konfiguration**:

- **eBay-Benutzername**: Ihr Verkäufername (z. B. `bridgefurb`).
- **Marktplätze**: Wählen Sie die eBay-Website(en) aus, auf denen Sie verkaufen (Frankreich, Deutschland, Großbritannien…).
- **Steuerregel**: Wählen Sie die PrestaShop-Regel, die **auf importierte Produkte** angewendet wird. eBay enthält die MwSt.; das Modul rechnet in den Nettopreis um.
  - ⚠️ Wenn Sie ein **Drittanbieter-MwSt-Modul** verwenden, wählen Sie **„Keine Steuerregel"**, um Konflikte zu vermeiden.
- **Standardkategorie**: die Ziel-PrestaShop-Kategorie.
- **Bestandspuffer**: Halten Sie eine Sicherheitsmarge (z. B. 80 % des echten Bestands senden).
- **Dynamische Preise**: Erstellen Sie Ihre Regeln (z. B. „wenn Bestand > 50, Preis um 5 % senken; wenn Bestand < 5, um 10 % erhöhen").

---

## 6. Verwendung

### Automatischer Import
Der **Cron** importiert Ihre aktiven Angebote und synchronisiert den Bestand. Die Häufigkeit ist einstellbar (Standard 15 Min.).

### Manueller Import
**LinkBay → Import**: Die eBay-Kategorien über das **Dropdown** durchsuchen, Angebote ankreuzen, dann **„Auswahl importieren"**.

### Dashboard
**LinkBay → Dashboard**: Eine Checkliste der zu behebenden Probleme, mit **„Automatisch reparieren"** (z. B. veraltete Kombinationen, Importfehler, Preise). Ein Klick.

---

## 7. Kompatibilität mit Ihren anderen Modulen

- LinkBay verwaltet **nur die eBay zugeordneten Produkte**. Es ändert nie Produkte, die Sie anderswo verwalten.
- Es ändert nie die **globalen Steuereinstellungen** des Shops.
- Die Karte **„Kompatibilität & Konflikte"** listet aktive Module auf, die ebenfalls auf Produktänderungen reagieren.
- **MwSt.**: Wenn ein Drittanbieter-Modul die MwSt. verwaltet, wählen Sie **„Keine Steuerregel"** in LinkBay — es lässt dieses Modul arbeiten (kein Konflikt).

---

## 8. Fehlerbehebung

| Problem | Lösung |
|---|---|
| „invalid_grant" nach Rückkehr von eBay | Die Verbindung war bereits erfolgreich (doppelter Callback-Aufruf). Backoffice neu laden. |
| Angebote nicht gefunden | Prüfen Sie Ihren **eBay-Benutzernamen** und die gewählten **Marktplätze**. |
| Preise unverändert | Stellen Sie sicher, dass eine Preisregel zum aktuellen Bestand passt. |
| Verbindung verloren | Klicken Sie erneut auf **„Mit eBay verbinden"** in der Konfiguration. |

---

## 9. Support

📧 **contact@beassistnow.com** — Beassisted steht Ihnen für Einrichtung und Fragen zur Verfügung.

---

*LinkBay — Präzise PrestaShop-Module · © Beassisted*
