# LinkBay — Guide d'installation et d'utilisation

Merci d'avoir choisi **LinkBay**, le connecteur eBay pour PrestaShop.
Ce guide vous accompagne pas à pas, de l'installation à la synchronisation de vos stocks et prix.

---

## 1. Présentation

LinkBay **importe vos annonces eBay** dans PrestaShop et **synchronise vos stocks** dans les deux sens, en continu via le cron. Il gère aussi les **déclinaisons**, les **taxes**, le **multi-marketplace**, un **stock tampon** et des **prix dynamiques**.

> **Aucun compte développeur eBay n'est requis** : l'application eBay est partagée et fournie par Beassisted. Vous n'avez qu'à coller votre **code eBay (RuName)** fourni avec votre licence.

---

## 2. Prérequis

| Élément | Prérequis |
|---|---|
| PrestaShop | 1.7.8 ou supérieur |
| PHP | 7.4 ou supérieur |
| eBay | Un compte **vendeur** actif |
| Code eBay (RuName) | Fourni avec votre licence (ex. `BEASSISTED-BEASSIST-beassi-xxxxxxx`) |

---

## 3. Installation

1. Dans le back-office PrestaShop : **Modules → Module Manager**.
2. Cliquez **« Ajouter un module »** puis déposez le fichier `linkbay.zip`.
3. Cliquez **« Installer »**. Le module crée ses propres tables et son menu **LinkBay** (aucune table PrestaShop modifiée).

---

## 4. Connexion à eBay

1. Allez dans **LinkBay → Configuration**.
2. Dans la carte **« Réglages API eBay »**, collez votre **code eBay (RuName)** — la valeur fournie avec votre licence (ex. `BEASSISTED-BEASSIST-beassi-xxxxxxx`). C'est la seule valeur à saisir : l'application eBay est partagée et déjà préconfigurée par Beassisted.
3. Cliquez **« Enregistrer les paramètres »**, puis **« Connect to eBay »**.
4. Connectez-vous à votre compte **vendeur eBay** et **autorisez** l'application.

> 💬 Besoin d'aide ? **contact@beassistnow.com**

---

## 5. Réglages recommandés

Dans **LinkBay → Configuration** :

- **Nom d'utilisateur eBay** : votre identifiant vendeur (ex. `bridgefurb`).
- **Marketplaces** : cochez le ou les sites eBay où vous vendez (France, Allemagne, UK…).
- **Règle de taxe** : choisissez la règle PrestaShop à appliquer **aux produits importés**. eBay inclut la TVA ; le module convertit en prix HT.
  - ⚠️ Si vous utilisez un **module de TVA tiers**, sélectionnez **« Aucune règle de taxe »** pour éviter tout conflit.
- **Catégorie par défaut** : la catégorie PrestaShop de destination.
- **Stock tampon** : gardez une marge de sécurité (ex. envoyer 80 % du stock réel).
- **Prix dynamiques** : créez vos règles (ex. « si stock > 50, baisse de 5 % ; si stock < 5, hausse de 10 % »).

---

## 6. Utilisation

### Import automatique
Le **cron** importe vos annonces actives et synchronise les stocks. La fréquence est configurable (défaut : 15 min).

### Import manuel
**LinkBay → Import** : parcourez les catégories eBay via la **liste déroulante**, cochez les annonces, puis **« Importer la sélection »**.

### Tableau de bord
**LinkBay → Dashboard** : une checklist des problèmes à corriger, avec **« Réparer automatiquement »** (ex. combinaisons obsolètes, échecs d'import, prix). Un clic suffit.

---

## 7. Compatibilité avec vos autres modules

- LinkBay ne gère **que les produits mappés vers eBay**. Il ne modifie pas les produits que vous gérez ailleurs.
- Il ne change **jamais** les réglages de TVA globaux de la boutique.
- La carte **« Compatibilité & conflits »** de la configuration liste les modules actifs qui réagissent aussi aux modifications de produit.
- **TVA** : si un module tiers gère la TVA, choisissez **« Aucune règle de taxe »** dans LinkBay → il laisse ce module faire (aucun conflit).

---

## 8. Dépannage

| Problème | Solution |
|---|---|
| « invalid_grant » au retour d'eBay | Connexion déjà réussie (double appel du callback). Rechargez le back-office. |
| Annonces non trouvées | Vérifiez le **nom d'utilisateur eBay** et vos **marketplaces** cochées. |
| Prix inchangés | Assurez-vous qu'une règle de prix correspond bien au stock actuel. |
| Connexion perdue | Cliquez à nouveau **« Connect to eBay »** dans la configuration. |

---

## 9. Support

📧 **contact@beassistnow.com** — Beassisted se tient à votre disposition pour la configuration et toute question.

---

*LinkBay — Precision-built PrestaShop modules · © Beassisted*
