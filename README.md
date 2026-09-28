# Kadence

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.5-blue.svg)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-8.1-black.svg)](https://symfony.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Outil interne de pilotage de production : saisie quotidienne des temps, estimé vs réalisé par projet et sous-projet, capacité de l'équipe, date de fin projetée et roadmap. Vision produit : [`docs/vision.md`](docs/vision.md) · backlog : [`docs/product-backlog.md`](docs/product-backlog.md).

Amorcé depuis [`gabrielmustiere/symfony-template`](https://github.com/gabrielmustiere/symfony-template).

## Stack

- **Framework** : Symfony 8.1 / PHP 8.5+
- **Serveur local** : Symfony CLI (proxy HTTPS `*.wip`)
- **Base de données** : SQLite (fichier local, zéro infra)
- **Design system** : "Paper" (voir [`DESIGN.md`](DESIGN.md)) — Tailwind CSS 4.3, Flowbite 4 (drawer, toasts, datepicker), Symfony UX Toolkit (`<twig:Button>`, `tailwind_merge`)
- **Assets** : Tailwind CSS 4 + Symfony UX (Stimulus, Icons, Live Component, Turbo)
- **E-mails** : Mailpit pour la capture en développement
- **Auth** : Authentification par formulaire (email/password)
- **Tests** : PHPUnit 13 (Unit + Functional) + Playwright 1.63 (E2E)
- **Qualité** : PHPStan (level 10) + PHP-CS-Fixer
- **Async** : Symfony Messenger (transport Doctrine)
- **AI** : Symfony AI Mate (CLI) + serveurs MCP intégrés (Playwright, Chrome DevTools)

## Prérequis

- [PHP 8.5+](https://www.php.net/)
- [Composer](https://getcomposer.org/)
- [Symfony CLI](https://symfony.com/download)
- [Docker](https://www.docker.com/) (uniquement pour Mailpit)
- [Node.js 22+](https://nodejs.org/) (pour Playwright et Tailwind)

## Démarrer

1. Installer les dépendances, la base et les fixtures :
   ```bash
   make init
   ```
2. Lancer Mailpit puis le serveur :
   ```bash
   docker compose up -d   # Mailpit (capture des e-mails)
   make serve             # serveur Symfony
   ```
   L'application est accessible sur https://kadence.wip.

## Workflow Makefile

Toutes les opérations courantes passent par `make`. Lancez `make help` pour la liste complète.

| Commande            | Description                                                |
|---------------------|------------------------------------------------------------|
| `make init`         | Installation complète (deps + DB + fixtures)               |
| `make install`      | Réinstalle les dépendances PHP/JS/Playwright               |
| `make serve`        | Lance le serveur Symfony (`serve-d` en arrière-plan)       |
| `make stop`         | Arrête le serveur Symfony                                  |
| `make db-reset`     | Recrée la base from scratch (drop + migrate + fixtures)    |
| `make migration`    | Génère une migration depuis le diff d'entités              |
| `make phpunit`      | Lance les tests PHPUnit (Unit + Functional)                |
| `make playwright`   | Lance les tests E2E Playwright                             |
| `make php-cs-fix`   | Corrige le code style (PHP-CS-Fixer)                       |
| `make phpstan`      | Analyse statique PHPStan level 10                          |
| `make lint`         | CS-Fixer (dry-run) + PHPStan                               |
| `make quality`      | CS-Fixer + PHPStan + build (mode dev)                      |

## Accès aux services

- **Application** : https://kadence.wip
- **Mailpit (UI web)** : http://localhost:8027
- **Base SQLite (dev)** : `var/data.db` — accessible via `sqlite3 var/data.db`
- **Base SQLite (test)** : `var/data_test.db`

## Identifiants de test

- `admin@example.com` / `password` (ROLE_USER)

## Design system & composants

Le projet embarque le design system **"Paper"** — tokens (couleurs, typographies Roboto/Montserrat/PT Mono, radius, spacing) documentés dans [`DESIGN.md`](DESIGN.md), variables `@theme` exposées dans `assets/styles/app.css`.

Stack front retenue (voir [`docs/adr/0001-stack-front-paper-flowbite-ux-toolkit.md`](docs/adr/0001-stack-front-paper-flowbite-ux-toolkit.md)) :

- **Tailwind CSS 4** comme moteur utilitaire.
- **Flowbite 4** pour les composants interactifs (drawer sidebar, toasts flash, datepicker).
- **Symfony UX Toolkit** + `tales-from-a-dev/twig-tailwind-extra` pour composer des composants Twig avec variants (`html_cva`) et fusion intelligente (`tailwind_merge`).

Exemple : le composant `<twig:Button>` (`templates/components/Button.html.twig`) expose des variants `brand`, `secondary`, `outline`, `ghost`…, des tailles et formes, et fusionne proprement les classes utilitaires.

## Assistance IA (Claude Code)

**Symfony AI Mate** expose le profiler Symfony, les logs Monolog et les services du container via une CLI (plus de serveur MCP depuis la 0.13). Les agents la découvrent via `AGENTS.md` et `mate/AGENT_INSTRUCTIONS.md`, générés par `mate discover` :

```bash
symfony php vendor/bin/mate tools:list                              # Liste des outils
symfony php vendor/bin/mate tools:call symfony-profiler-list --limit=1
```

Le fichier `.mcp.json` configure deux serveurs MCP :

| Serveur             | Description                                   |
|---------------------|-----------------------------------------------|
| **playwright**      | Automatisation navigateur pour tests et debug |
| **chrome-devtools** | Interaction avec Chrome via DevTools Protocol |

## Licence

Distribué sous licence MIT. Voir [LICENSE](LICENSE).
