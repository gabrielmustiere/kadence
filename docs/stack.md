# Stack technique — Kadence

> Dernière mise à jour : 2026-09-30 — cartographie factuelle de la stack. Chaque entrée est prouvée par un fichier du dépôt (source entre parenthèses) ou marquée _non renseigné_.

## Vue d'ensemble

Monolithe Symfony 8.1 en PHP 8.5, rendu serveur Twig + Symfony UX (Stimulus, Turbo, Live Component), sans bundler JS (AssetMapper). Base SQLite en dev et test. Amorcé depuis `gabrielmustiere/symfony-template` ; hébergement et base de production non encore décidés.

| Couche | Techno principale |
|---|---|
| Langage(s) | PHP 8.5 |
| Backend | Symfony 8.1, Doctrine ORM 3.7 |
| Frontend | Twig + Symfony UX (Stimulus, Turbo, Live Component), Tailwind CSS 4, Flowbite 4, AssetMapper |
| Données | SQLite (dev/test) ; production _non renseigné_ |
| Ops | _non renseigné_ (seul Mailpit en Docker pour le dev) |
| DevOps | pas de CI ; QA locale via Makefile |

## Langages & runtimes

- **PHP** `>=8.5` (contrainte), version épinglée `8.5` — source : `composer.json`, `.php-version`
- **Configuration PHP locale** : `date.timezone = Europe/Paris`, `memory_limit = 1024M` — source : `php.ini`
- **Extensions PHP requises** : `ext-calendar` (fêtes mobiles des jours fériés via `easter_days()`, ADR-0002), `ext-ctype`, `ext-iconv` — source : `composer.json`
- **Node.js** : utilisé uniquement pour l'outillage (Playwright) ; aucune version épinglée (`package.json`, pas de `.nvmrc`)

## Backend

- **Framework** : Symfony `8.1.*` (contrainte), `8.1.7` résolue (`composer.json`, `composer.lock`)
- **ORM / données** : Doctrine ORM `^3.7.2` (3.7.2 résolue), DoctrineBundle `^3.3.2` (3.3.2 résolue), Doctrine Migrations `^4.0.1` (`composer.json`, `composer.lock`)
- **Authentification** : `symfony/security-bundle` — login par formulaire e-mail / mot de passe avec CSRF, provider entité `App\Entity\User` chargé par `UserRepository` (e-mail insensible à la casse) ; rôles hiérarchisés `ROLE_DIRECTION` ⊃ `ROLE_LEAD` ⊃ `ROLE_PROD` ⊃ `ROLE_USER`, `/equipe` réservé à `ROLE_DIRECTION`, reste du site en `ROLE_USER` sauf `/login` ; `UserChecker` refusant les comptes désactivés ; limitation des tentatives par `login_throttling` via `symfony/rate-limiter` `8.1.*` (8.1.6 résolue) (`config/packages/security.yaml`, `src/Entity/User.php`, `src/Repository/UserRepository.php`, `src/Security/UserChecker.php`, `composer.json`, `composer.lock`)
- **Asynchrone** : Symfony Messenger — transport `async` sur Doctrine (`MESSENGER_TRANSPORT_DSN=doctrine://default`), 3 retries avec backoff ×2, transport `failed` ; e-mails et notifications routés en async ; `sync://` en test (`config/packages/messenger.yaml`, `.env`, `.env.test`)
- **E-mails / notifications** : `symfony/mailer`, `symfony/notifier` (`composer.json`, `config/packages/mailer.yaml`, `config/packages/notifier.yaml`)
- **Libs structurantes** : Form, Validator, Serializer, HttpClient, Intl, Translation, ExpressionLanguage, Twig 3.30 + `twig/extra-bundle` / `twig/html-extra` (`composer.json`, `composer.lock`)

## Frontend

- **Rendu** : Twig côté serveur + Symfony UX — `symfony/stimulus-bundle` `^3.5.1`, `symfony/ux-turbo` `^3.5.1`, `symfony/ux-live-component` `^3.5.1`, `symfony/ux-icons` `^3.5.1`, `symfony/ux-toolkit` `^3.5.1` (`composer.json`)
- **Bundler / build** : AssetMapper + importmap, sans bundler Node — Stimulus 3.2.2, Turbo 8.0.23 (`importmap.php`, `config/packages/asset_mapper.yaml`)
- **CSS** : Tailwind CSS 4 via `symfonycasts/tailwind-bundle` (binaire `v4.3.3`) (`config/packages/symfonycasts_tailwind.yaml`, `package.json`)
- **UI kit** : Flowbite 4.0.2 (+ `flowbite-datepicker` 2.0.0) via `tales-from-a-dev/flowbite-bundle` ; composants Twig à variants via `tales-from-a-dev/twig-tailwind-extra` (`html_cva`, `tailwind_merge`) (`importmap.php`, `composer.json`)
- **Design system** : « Paper » — tokens `@theme` dans `assets/styles/app.css`, documenté dans `DESIGN.md` ; choix tracé par l'ADR 0001 (`docs/adr/0001-stack-front-paper-flowbite-ux-toolkit.md`)
- **TypeScript** : non pour l'application (seule la config Playwright est en `.ts` : `playwright.config.ts`)

## Données & stockage

- **Base de données** : SQLite — `var/data.db` (dev), `var/data_test.db` (test) (`.env`, `.env.test`) ; **production : _non renseigné_**
- **File / queue** : table Doctrine utilisée par Messenger (même base) (`.env`, `config/packages/messenger.yaml`)
- **Cache / sessions** : configuration Symfony par défaut (`config/packages/cache.yaml`) ; pas de service externe

## Ops / Infrastructure

- **Conteneurisation** : Docker Compose pour le **dev uniquement** — service `mailpit` (`axllent/mailpit:latest`, SMTP `127.0.0.1:1027`, UI `127.0.0.1:8027`) (`compose.yaml`) ; pas de `Dockerfile` de production
- **Serveur local** : Symfony CLI, proxy HTTPS `kadence.wip`, workers `messenger:consume async` + `tailwind:build --watch` + docker compose (`.symfony.local.yaml`)
- **Hébergement de production** : _non renseigné_ (non décidé)
- **CDN / reverse proxy** : _non renseigné_
- **Gestion des secrets** : _non renseigné_ — en dev, `APP_SECRET` dans `.env.dev` (régénéré à l'amorçage), vide dans `.env`
- **Environnements** : `dev` et `test` prouvés (`.env`, `.env.dev`, `.env.test`) ; staging / prod _non renseigné_

## DevOps / CI-CD

- **Pipeline CI** : aucune — absence assumée pour l'instant ; QA exécutée en local via `make lint` / `make phpunit` / `make playwright`
- **Tests** : PHPUnit `^13.3.5` (13.3.5 résolue) — unitaires + fonctionnels (`phpunit.dist.xml`, `tests/`) ; Playwright `^1.63.0` — E2E sur `https://kadence.wip` (`playwright.config.ts`, `e2e/`) ; fixtures Doctrine (`fixtures/`)
- **Analyse statique / style** : PHPStan `^2.2.16` niveau 10 + extensions strict-rules, doctrine, symfony, phpunit + `tomasvotruba/cognitive-complexity` (`phpstan.dist.neon`, `composer.json`) ; PHP-CS-Fixer (`.php-cs-fixer.dist.php`) ; PHPInsights (`phpinsights.php`)
- **Hooks git / automatisation deps** : aucun détecté
- **Déploiement** : _non renseigné_

## Monitoring / observabilité

- **Erreurs** : _non renseigné_
- **Métriques / traces** : _non renseigné_
- **Logs** : Monolog — en prod, `fingers_crossed` (seuil `error`, 404/405 exclus) vers `php://stderr` au format JSON, dépréciations séparées (`config/packages/monolog.yaml`) ; centralisation _non renseigné_

## Outillage de développement local

- **Commandes QA / build** : `make init` (deps + DB + fixtures), `make serve`, `make phpunit`, `make playwright`, `make lint` (CS-Fixer dry-run + PHPStan), `make quality` (CS-Fixer + PHPStan + build), `make migration` / `make migrate`, `make db-reset` (`Makefile`)
- **Services de dev** : `docker compose up -d` (Mailpit) (`compose.yaml`)
- **Assistance IA** : Symfony AI Mate `^0.14` (0.14.0 résolue, `require-dev`, dossier `mate/`) ; serveurs MCP Playwright et Chrome DevTools (`.mcp.json`) ; skills agents dans `.agents/skills/` et `.claude/skills/`

## Contraintes & dette de stack connues

- **PHP ≥ 8.5 et Symfony 8.1** : versions très récentes, imposées par le template ; l'hébergement de production devra fournir PHP 8.5.
- **SQLite + Messenger sur Doctrine** : la file de messages partage la base SQLite ; si SQLite est retenu en production, cela implique un seul serveur applicatif et une stratégie de sauvegarde du fichier. Choix à trancher (`/tech-plan` ou `/adr`) avec l'hébergement.
- **SQLite : clés étrangères non appliquées, `LOWER()` limité à l'ASCII** : aucune activation des clés étrangères dans la configuration Doctrine, donc les contraintes déclarées par les migrations ne sont pas vérifiées et les suppressions en cascade passent par l'ORM (`cascade: ['remove']`) ; les comparaisons insensibles à la casse se font en PHP (`config/packages/doctrine.yaml`, `src/Entity/Lot.php`, `src/Validator/TitleComparison.php`). À reprendre avec le choix de la base de production.
- **Mailpit en `:latest`** : image non épinglée (dev uniquement).
- **Pas de CI** : la QA repose sur la discipline locale.
- **`ext-calendar` requise en production** : les jours fériés en dépendent ; l'hébergement devra la fournir, sans quoi `composer install` échoue (`composer.json`, `docs/adr/0002-jours-feries-calcules-et-ajustements.md`).

## Changelog

- 2026-09-28 — Création — amorcé depuis gabrielmustiere/symfony-template
- 2026-09-28 — Éditer — Backend (authentification) — sync post-livraison de la story 001-f-acces-roles
- 2026-09-29 — Enrichir — Contraintes & dette (SQLite : clés étrangères, LOWER) — sync post-livraison de la story 002-f-projets-lots-sous-lots
- 2026-09-30 — Enrichir — Langages & runtimes (extensions PHP requises), Contraintes & dette (`ext-calendar` en production) — sync post-livraison de la story 004-f-jours-feries
