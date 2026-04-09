# Billetterie Les Mathu'Loire

Application web de billetterie en ligne pour la **Cie Les Mathu'Loire**, association théâtrale amateur basée à Saint-Mathurin-sur-Loire (49).

L'application gère l'ensemble du cycle de vie des réservations : vente en ligne avec paiement, placement en salle, impression de billets, gestion administrative et reporting.

---

## Sommaire

- [Stack technique](#stack-technique)
- [Fonctionnalités](#fonctionnalités)
- [Architecture](#architecture)
- [Installation](#installation)
- [Configuration](#configuration)
- [Commandes utiles](#commandes-utiles)
- [Déploiement en production](#déploiement-en-production)
- [Comptes de test](#comptes-de-test)

---

## Stack technique

| Couche | Technologie |
|---|---|
| **Back-end** | Symfony 8 (PHP 8.4) |
| **Base de données** | MySQL 8 |
| **ORM** | Doctrine 3 |
| **Front-end** | Twig (rendu serveur) |
| **CSS** | Tailwind CSS 4 |
| **JS interactif** | Stimulus (composants légers) + React (plan de salle) |
| **Build assets** | Webpack Encore |
| **Paiement** | HelloAsso Checkout API (gratuit pour les associations) |
| **PDF** | Dompdf (billets, relevés de séance, plans de salle) |
| **Graphiques** | Chart.js (dashboard admin) |
| **Emails** | Symfony Mailer (envoi synchrone, compatible mutualisé OVH) |

**Conventions du projet** :
- Pas de JS dans les vues (tout passe par des controllers Stimulus)
- Pas d'API REST/GraphQL — rendu serveur classique
- Templates organisés par composants

---

## Fonctionnalités

### Côté spectateur

- **Page d'accueil** avec hero, présentation de la compagnie, valeurs, chiffres clés
- **Liste des spectacles** avec affiches, descriptions et nombre de dates disponibles
- **Page détail d'un spectacle** : synopsis, auteur, metteur en scène, durée, prochaines dates
- **Billetterie** : choix de la représentation, du nombre de places, saisie des coordonnées
- **Indicateur de jauge** en temps réel (libre / X places restantes / complet)
- **Récapitulatif** avant paiement
- **Paiement sécurisé** via HelloAsso
- **Email de confirmation** avec numéro de réservation
- **Page de suivi** de réservation accessible via lien dans l'email
- **Annulation autonome** (jusqu'à 48h avant le spectacle, avec remboursement automatique)
- **Conformité RGPD** : consentement, mentions légales, politique de confidentialité, droit à l'oubli

### Côté admin / billettiste

#### Dashboard
- Statistiques de saison (spectateurs, réservations, recettes)
- 4 graphiques Chart.js animés (remplissage, recettes, répartition, capacité)
- Synthèse par séance avec taux de remplissage coloré
- Filtrage par saison

#### Gestion des réservations
- Liste filtrable (par représentation, statut, recherche libre)
- Pagination
- Recherche multi-champs (nom, prénom, email, téléphone, n° de résa)
- Création manuelle (réservations par téléphone, invitations)
- Édition complète (changement de date, places, statut, commentaires)
- Renvoi de l'email de confirmation
- Annulation avec remboursement automatique HelloAsso
- Impression billets format thermique 80mm (PDF)
- Export CSV
- Liste séparée des réservations annulées

#### Gestion des spectacles
- CRUD complet (titre, description, synopsis, auteur, mise en scène, durée)
- Upload d'affiche (JPG, PNG, WebP)
- Suppression en cascade (avec confirmation explicite)

#### Gestion des représentations
- CRUD avec validation stricte (date future, jauge, tarifs)
- Annulation
- Duplication (pré-remplit le formulaire avec date +7 jours)
- Génération du relevé de séance PDF
- Filtrage par saison

#### Plan de salle interactif (React)
- Reproduction fidèle du plan réel du théâtre (rangées A-R, 2 blocs, ~175 sièges)
- Sélection d'une réservation puis clic sur un siège pour l'assigner
- Réassignation entre réservations avec confirmation
- Échange entre 2 sièges (clic droit → "Échanger avec...")
- Blocage / déblocage de sièges par représentation
- Marquage de sièges cassés (réversible)
- Légende et indicateurs visuels
- Lien direct depuis l'édition d'une réservation
- Responsive (scroll horizontal sur mobile)

#### Gestion des utilisateurs (admin only)
- CRUD avec hash des mots de passe
- Rôles : Admin / Billettiste
- Protection contre l'auto-suppression

#### Journal d'audit (admin only)
- Historique de toutes les actions admin (login, CRUD réservations/représentations/spectacles/utilisateurs)
- Filtrable par utilisateur, type d'action, plage de dates
- Traçage des tentatives de connexion échouées (IP, email tenté)

#### Notifications
- Badge en temps réel sur le menu "Réservations" (nouvelles résas depuis la dernière visite)
- Rappels J-2 automatiques par email aux spectateurs (commande cron)

#### Authentification
- Login natif Symfony (form_login + CSRF)
- Mot de passe oublié (token expirant 1h, email sécurisé)
- Hiérarchie des rôles

### Automatisations

- **Rapport journalier** envoyé par email aux admins/billettistes (cron)
- **Plans de salle** joints au rapport (1 PDF par séance à venir)
- **Rappel J-2** par email aux spectateurs 2 jours avant leur représentation (cron)
- **Anonymisation RGPD** des réservations de plus de 12 mois (cron)

---

## Architecture

### Structure du projet

```
src/
├── Command/                    # Commandes console (cron)
│   ├── AnonymizeOldReservationsCommand.php
│   ├── SendDailyReportCommand.php
│   └── SendRemindersCommand.php
│
├── Controller/                 # Controllers HTTP
│   ├── Admin/                  # Back-office
│   │   ├── DashboardController.php
│   │   ├── ReservationController.php
│   │   ├── ShowController.php
│   │   ├── RepresentationController.php
│   │   ├── SeatPlanController.php
│   │   ├── UserController.php
│   │   └── AuditController.php
│   ├── HomeController.php
│   ├── ShowController.php
│   ├── ReservationController.php
│   ├── PasswordResetController.php
│   ├── SecurityController.php
│   ├── LegalController.php
│   └── WebhookController.php
│
├── DataFixtures/               # Fixtures par entité
│   ├── UserFixtures.php
│   ├── ShowFixtures.php
│   ├── SeatFixtures.php
│   ├── RepresentationFixtures.php
│   ├── ReservationFixtures.php
│   ├── SeatAssignmentFixtures.php
│   ├── PaymentFixtures.php
│   └── AuditLogFixtures.php
│
├── Entity/                     # 8 entités Doctrine
│   ├── User.php
│   ├── Show.php
│   ├── Representation.php
│   ├── Reservation.php
│   ├── Seat.php
│   ├── SeatAssignment.php
│   ├── Payment.php
│   └── AuditLog.php
│
├── EventListener/              # Listeners Symfony
│   └── AuditLoginListener.php       # Audit login/logout
├── Form/                       # Form types
├── Repository/                 # Repositories Doctrine
└── Service/                    # Services métier
    ├── ReservationService.php
    ├── ReservationMailer.php
    ├── PasswordResetMailer.php
    ├── HelloAssoPaymentHandler.php
    ├── DashboardService.php
    ├── ReportService.php
    ├── AuditLogger.php
    ├── TicketPdfGenerator.php           # Billet A4
    ├── TicketThermalPdfGenerator.php    # Billet 80mm
    ├── SessionReportPdfGenerator.php
    └── SeatPlanPdfGenerator.php

templates/
├── base.html.twig
├── layouts/
│   ├── public.html.twig
│   └── admin.html.twig
├── components/                 # Composants réutilisables
│   ├── public/
│   └── admin/
├── public/                     # Pages publiques
├── admin/                      # Pages back-office
├── security/                   # Login, mot de passe oublié
├── email/                      # Templates emails
├── pdf/                        # Templates PDF
└── form/theme.html.twig        # Form theme global

assets/
├── app.js
├── seatplan/                   # App React du plan de salle
│   ├── index.jsx
│   └── components/
├── controllers/                # Stimulus controllers
└── styles/app.css              # Tailwind
```

### Modèle de données

8 entités principales :

```
User ─┐
      └─> Reservation <─── Representation ───> Show
                  │                │
                  ├─> Payment      └─> SeatAssignment ───> Seat
                  │
                  └─> SeatAssignment
```

- **User** : compte admin ou billettiste
- **Show** : pièce de théâtre (titre, synopsis, affiche, auteur)
- **Representation** : séance (date, statut, jauge, tarifs)
- **Reservation** : réservation d'un spectateur (infos embarquées, pas de compte spectateur)
- **Seat** : siège physique de la salle (rangée + numéro, isActive)
- **SeatAssignment** : placement d'un siège pour une réservation/représentation
- **Payment** : paiement via HelloAsso (méthode, montant, type, transactionId)
- **AuditLog** : journal d'audit (user, action, cible, résumé, IP, date)

### Suppressions en cascade

```
Show → Representations → Reservations → Payments
                                     └─> SeatAssignments
                      └─> SeatAssignments
```

---

## Installation

### Prérequis

- PHP 8.4+
- Composer
- Node.js 22+ et npm
- MySQL 8+
- Extension PHP `intl` (pour les dates en français)

### Étapes

```bash
# 1. Cloner le projet
git clone <url-du-repo> mathuloire
cd mathuloire/mathuloire_symfony

# 2. Installer les dépendances
composer install
npm install

# 3. Configurer l'environnement
cp .env .env.local
# Éditer .env.local avec vos paramètres (BDD, mailer, HelloAsso...)

# 4. Créer la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Charger les fixtures (dev uniquement)
php bin/console doctrine:fixtures:load

# 6. Compiler les assets
npm run build       # production
# ou
npm run watch       # développement

# 7. Lancer le serveur de dev
symfony server:start
# ou
php -S localhost:8000 -t public
```

---

## Configuration

### Variables d'environnement (.env.local)

```bash
# Application
APP_ENV=dev
APP_SECRET=<secret aléatoire>
APP_BASE_URL=http://localhost:8000   # URL absolue pour HelloAsso

# Base de données
DATABASE_URL="mysql://user:pass@127.0.0.1:3306/mathuloire?serverVersion=8.0&charset=utf8mb4"

# Mailer
MAILER_DSN=smtp://user:pass@smtp.example.com:587?encryption=tls
MAILER_FROM=contact@les-mathuloire.com

# HelloAsso (sandbox ou production)
HELLOASSO_CLIENT_ID=<votre clientId>
HELLOASSO_CLIENT_SECRET=<votre clientSecret>
HELLOASSO_ORGANIZATION_SLUG=<votre slug d'asso>
HELLOASSO_IS_SANDBOX=true
```

### Webhook HelloAsso

Dans le dashboard HelloAsso, configurer l'URL de notification :
```
https://<votre-domaine>/webhook/helloasso
```

---

## Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Créer une migration depuis les changements d'entités
php bin/console make:migration

# Appliquer les migrations
php bin/console doctrine:migrations:migrate

# Recharger les fixtures (PURGE LA BDD)
php bin/console doctrine:fixtures:load

# Envoyer manuellement le rapport journalier
php bin/console app:send-daily-report

# Envoyer les rappels J-2
php bin/console app:send-reminders

# Anonymiser les anciennes réservations (RGPD)
php bin/console app:anonymize-reservations

# Build assets
npm run build      # production
npm run watch      # développement avec watcher

# Lancer les tests
vendor/bin/phpunit                    # tous les tests
vendor/bin/phpunit --testsuite Unit   # tests unitaires uniquement
vendor/bin/phpunit --testsuite Functional  # tests fonctionnels uniquement
vendor/bin/phpunit --testdox          # affichage lisible

# Analyse statique PHP (level 7)
vendor/bin/phpstan analyse

# Lint PHP (syntaxe)
vendor/bin/parallel-lint src/

# Lint JavaScript
npx eslint assets/
```

---

## Tests

31 tests couvrant les règles métier critiques :

| Suite | Tests | Ce qu'ils protègent |
|---|---|---|
| ReservationService | 7 | Calcul des prix, annulation + libération des sièges, création depuis draft |
| HelloAssoPaymentHandler | 7 | Parsing webhook, enregistrement paiement, idempotence |
| Security | 6 | Admin inaccessible sans login, pages publiques accessibles |
| SelfCancel | 2 | Bouton annulation visible/caché selon règle 48h, token invalide rejeté |
| Webhook | 5 | Payloads invalides rejetés, événements non-paiement ignorés |
| ReservationCapacity | 1 | Jauge pleine → réservation refusée |
| Commandes | 2 | Anonymisation RGPD et rappels J-2 s'exécutent sans erreur |

Philosophie : chaque test protège contre un risque réel (perte d'argent, sur-réservation, faille de sécurité, incohérence de données). Pas de tests décoratifs.

---

## Déploiement en production

Voir le fichier [`note.txt`](./note.txt) pour les instructions complètes.

### Étapes principales

1. **Variables d'environnement** : créer un `.env.local` avec les valeurs de prod
2. **Base de données** : `php bin/console doctrine:migrations:migrate`
3. **Build assets** : `npm run build`
4. **Cache** : `php bin/console cache:clear --env=prod`
5. **Crons** :
   ```cron
   # Rapport journalier à 8h
   0 8 * * * cd /path/to/project && php bin/console app:send-daily-report

   # Rappels J-2 à 9h
   0 9 * * * cd /path/to/project && php bin/console app:send-reminders

   # Anonymisation RGPD le 1er du mois à 3h
   0 3 1 * * cd /path/to/project && php bin/console app:anonymize-reservations
   ```
6. **HelloAsso** : passer `HELLOASSO_IS_SANDBOX=false` et configurer le webhook
7. **APP_BASE_URL** : mettre le vrai domaine de prod

### Spécificités OVH mutualisé

- Les emails sont envoyés en **synchrone** (pas besoin de worker Messenger ni supervisord)
- Les crons se configurent depuis l'espace client OVH

---

## Comptes de test

Après chargement des fixtures :

| Rôle | Email | Mot de passe |
|---|---|---|
| Admin | `l.zerri@gmail.com` | `password` |
| Billettiste | `billettiste@les-mathuloire.com` | `billett123` |

---

## Licence

Application développée pour l'association **Les Mathu'Loire**. Tous droits réservés.
