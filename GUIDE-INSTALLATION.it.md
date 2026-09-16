# LinkBay — Guida all'installazione e all'uso

Grazie per aver scelto **LinkBay**, il connettore eBay per PrestaShop.
Questa guida vi accompagna passo dopo passo, dall'installazione alla sincronizzazione di scorte e prezzi.

---

## 1. Presentazione

LinkBay **importa i tuoi annunci eBay** in PrestaShop e **sincronizza le scorte** in entrambe le direzioni, in continuo tramite il cron. Gestisce anche **varianti**, **tasse**, **multi-mercato**, una **scorta tampone** e **prezzi dinamici**.

> **Nessun account sviluppatore eBay richiesto**: l'app eBay è condivisa e fornita da Beassisted. Devi solo incollare il tuo **codice eBay (RuName)** fornito con la licenza.

---

## 2. Requisiti

| Elemento | Requisito |
|---|---|
| PrestaShop | 1.7.8 o superiore |
| PHP | 7.4 o superiore |
| eBay | Un account **venditore** attivo |
| Codice eBay (RuName) | Incluso con la licenza (es. `BEASSISTED-BEASSIST-beassi-xxxxxxx`) |

---

## 3. Installazione

1. Nel back office: **Moduli → Gestione moduli**.
2. Clicca su **«Aggiungi un modulo»** e carica `linkbay.zip`.
3. Clicca su **«Installa»**. Il modulo crea le proprie tabelle e il menu **LinkBay** (nessuna tabella di PrestaShop viene modificata).

---

## 4. Connessione a eBay

1. Vai su **LinkBay → Configurazione**.
2. Nella card **«Impostazioni API eBay»**, incolla il tuo **codice eBay (RuName)** — il valore fornito con la licenza (es. `BEASSISTED-BEASSIST-beassi-xxxxxxx`). È l'unico valore da inserire: l'app eBay è condivisa e già preconfigurata da Beassisted.
3. Clicca **«Salva impostazioni»**, poi **«Connetti a eBay»**.
4. Accedi al tuo account **venditore** eBay e **autorizza** l'applicazione.

> 💬 Serve aiuto? **contact@beassistnow.com**

---

## 5. Impostazioni consigliate

In **LinkBay → Configurazione**:

- **Nome utente eBay**: il tuo venditore (es. `bridgefurb`).
- **Marketplace**: spunta il/i sito/i eBay dove vendi (Francia, Germania, Regno Unito…).
- **Regola fiscale**: scegli la regola PrestaShop applicata **ai prodotti importati**. eBay include l'IVA; il modulo converte al prezzo senza imposte.
  - ⚠️ Se usi un **modulo IVA di terze parti**, seleziona **«Nessuna regola fiscale»** per evitare conflitti.
- **Categoria predefinita**: la categoria PrestaShop di destinazione.
- **Scorta tampone**: mantieni un margine di sicurezza (es. invia l'80% delle scorte reali).
- **Prezzi dinamici**: crea le tue regole (es. «se scorte > 50, riduci del 5%; se scorte < 5, aumenta del 10%»).

---

## 6. Utilizzo

### Importazione automatica
Il **cron** importa i tuoi annunci attivi e sincronizza le scorte. La frequenza è configurabile (default 15 min).

### Importazione manuale
**LinkBay → Importa**: sfoglia le categorie eBay tramite il **menu a discesa**, spunta gli annunci, poi **«Importa la selezione»**.

### Dashboard
**LinkBay → Dashboard**: una checklist dei problemi da correggere, con **«Ripara automaticamente»** (es. varianti obsolete, errori di importazione, prezzi). Un clic.

---

## 7. Compatibilità con gli altri moduli

- LinkBay gestisce **solo i prodotti mappati verso eBay**. Non modifica mai i prodotti che gestisci altrove.
- Non cambia mai le **impostazioni fiscali globali** del negozio.
- La card **«Compatibilità e conflitti»** elenca i moduli attivi che reagiscono anche alle modifiche dei prodotti.
- **IVA**: se un modulo di terze parti gestisce l'IVA, scegli **«Nessuna regola fiscale»** in LinkBay — lascia lavorare quel modulo (nessun conflitto).

---

## 8. Risoluzione dei problemi

| Problema | Soluzione |
|---|---|
| «invalid_grant» al ritorno da eBay | La connessione è già riuscita (doppia chiamata del callback). Ricarica il back office. |
| Annunci non trovati | Controlla il tuo **nome utente eBay** e i **marketplace** selezionati. |
| Prezzi invariati | Assicurati che una regola prezzi corrisponda alle scorte attuali. |
| Connessione persa | Clicca di nuovo **«Connetti a eBay»** nella configurazione. |

---

## 9. Supporto

📧 **contact@beassistnow.com** — Beassisted è a tua disposizione per la configurazione e ogni domanda.

---

*LinkBay — Moduli PrestaShop di precisione · © Beassisted*
