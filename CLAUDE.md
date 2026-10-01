# CLAUDE.md

Ce fichier guide Claude Code (claude.ai/code) quand il travaille sur ce dépôt.

## Nature du projet

Kadence est l'outil interne de pilotage de production d'un éditeur logiciel (15 à 40 personnes) : saisie quotidienne des temps, estimé vs réalisé par projet et sous-projet, capacité de l'équipe, date de fin projetée et roadmap. Utilisateurs principaux : direction et leads ; l'équipe de prod saisit ses temps.

- Vision produit : `docs/vision.md`
- Périmètre fonctionnel et backlog : `docs/product-backlog.md`
- Décisions d'architecture : `docs/adr/`

## Stack technique

Monolithe Symfony, rendu serveur. Stack détaillée : `docs/stack.md`.

- PHP 8.5+ / Symfony 8.1, Doctrine ORM 3.7 + Migrations, SQLite (`var/data.db`, partagée par dev et test)
- Symfony Messenger sur transport Doctrine (même base SQLite)
- Front : Twig + Symfony UX (Stimulus, Turbo, Live Component, Icons, Toolkit), AssetMapper + importmap — **pas de bundler Node**
- UI : Tailwind CSS 4, Flowbite 4, design system « Paper » (`DESIGN.md`, tokens `@theme` dans `assets/styles/app.css`, ADR 0001)
- Tests : PHPUnit 13 (Unit + Functional) + Playwright (E2E) ; qualité : PHPStan level 10 + PHP-CS-Fixer
- AI : Symfony AI Mate en CLI (`symfony php vendor/bin/mate`, voir `AGENTS.md`) configuré dans `mate/` (extensions symfony + monolog) ; extensions maison dans `Mate\` (`mate/src/`) ; serveurs MCP Playwright et Chrome DevTools (`.mcp.json`)

## Architecture

```
src/
  Controller/          # contrôleurs HTTP (minces)
  Entity/              # entités Doctrine
  Repository/          # seul endroit où vivent les requêtes (QueryBuilder, DQL)
fixtures/              # fixtures Doctrine (namespace DataFixtures\)
migrations/            # migrations Doctrine
templates/
  components/          # composants Twig « Paper » (<twig:Button>, Alert, Modal, Table…)
assets/
  controllers/         # contrôleurs Stimulus
  styles/app.css       # Tailwind + tokens du design system
tests/                 # PHPUnit (Unit + Functional)
  e2e/                 # Playwright (testDir)
mate/                  # config et extensions AI Mate
docs/                  # vision, backlog, stack, ADR, stories forge
```

Flux applicatif :

```
Request → Controller → Service/Manager → Repository → Entity → Response
```

**Interdit** : QueryBuilder hors repository, logique métier dans controller/entity/repository, `new Service()`, entity qui injecte un service.

## Commandes

Toutes les commandes PHP passent par `symfony` CLI — jamais `php` directement. `make help` liste toutes les cibles.

```bash
make init                                 # Installation complète (deps + DB + fixtures)
docker compose up -d                      # Mailpit (UI : http://localhost:8027)
make serve                                # Serveur Symfony → https://kadence.wip
make db-reset                             # Reset DB complet (drop + migrate + fixtures)
make migration                            # Génère une migration (symfony console make:migration)
make phpunit                              # PHPUnit (Unit + Functional)
make phpunit-filter LoginTest             # Un test PHPUnit par nom
make playwright                           # Playwright (E2E, headless)
make playwright-file tests/e2e/login.spec.ts  # Un test E2E ciblé
make lint                                 # CS-Fixer (dry-run) + PHPStan — lecture seule
make quality                              # CS-Fixer (corrige) + PHPStan + build
```

## Conventions

- `declare(strict_types=1)` dans tous les fichiers PHP
- Fixtures dans `fixtures/` (PSR-4 : `DataFixtures\`) — **PAS** dans `src/DataFixtures/`
- Ne jamais modifier une migration commitée — en créer une nouvelle
- Toute modif de schéma = migration générée par `symfony console make:migration`
- Ne jamais modifier `vendor/`
- Pas de `dump()`, `var_dump()`, `dd()` dans le code commité
- PHPUnit 13 : `createStub()` sans attentes, `createMock()` avec `expects()`
- Playwright : sélecteurs `data-test="..."`, config dans `playwright.config.ts`
- Enums : backed string enums dans `src/Enum/Type/`
- Mailer : classes dédiées dans `src/Mailer/` avec `TemplatedEmail`
- UI : réutiliser les composants de `templates/components/` et les tokens de `DESIGN.md` avant d'écrire des classes Tailwind ad hoc

## Pièges fréquents

- **Playwright tape sur le vrai serveur** : `baseURL` = `https://kadence.wip` — `make serve` doit tourner avant `make playwright`.
- **Dev et test partagent `var/data.db`** — `make phpunit` recharge les fixtures avant et après les tests, car ceux-ci y écrivent sans rollback : toute donnée saisie à la main en dev est perdue.
- **Messenger est en `sync://` en test** (`.env.test`) — les messages async sont traités immédiatement dans les tests.

## Identifiants de test

Tous avec le mot de passe `password` :

- `admin@example.com` — direction (ROLE_DIRECTION)
- `lead@example.com` — lead (ROLE_LEAD)
- `prod@example.com` — prod (ROLE_PROD)
- `ancien@example.com` — prod désactivé (connexion refusée)

Premier compte direction hors fixtures : `symfony console app:create-director`.

## Skills disponibles

### Plugin `forge` — pipeline de développement

Marketplace `gabrielmustiere/forge`. Quatre phases : 0 poser le décor, 1 cadrer, 2 implémenter, 3 clôturer. `/forge:help` pour s'orienter, `/forge:status` à la reprise du projet. **Ne jamais passer à la phase suivante sans validation du user.**

| Phase / track        | Skills                                                                                  |
|----------------------|-----------------------------------------------------------------------------------------|
| 0 — Décor            | `/forge:vision`, `/forge:product-backlog`, `/forge:stack`, `/forge:claude-md`, `/forge:rules` |
| Track feature        | `/forge:feature-interview` (optionnel) → `/forge:feature-pitch` → `/forge:feature-plan` → `/forge:feature-implem` |
| Track refacto        | `/forge:refactor-plan` → `/forge:refactor-implem`                                       |
| Track technique      | `/forge:tech-plan` → `/forge:tech-implem`                                               |
| 3 — Clôture          | `/forge:review` → `/forge:report` → `/forge:sync` → `/forge:commit`, puis `/forge:release` |
| Transverses          | `/forge:adr`, `/forge:estimate`, `/forge:test-scenario`, `/forge:doc-feature`, `/forge:status` |

Les artifacts de story vivent dans `docs/story/NNN-<f|r|t>-<slug>/`.

### Plugin `symfony` — recettes framework

À invoquer quand on touche au domaine concerné (controllers, doctrine, forms, events, messenger, validation, etc.).

| Domaine        | Skills                                                                                          |
|----------------|-------------------------------------------------------------------------------------------------|
| HTTP           | `/symfony:routing-define`, `/symfony:controller-action`                                         |
| Doctrine       | `/symfony:doctrine-entity`, `/symfony:doctrine-migration`, `/symfony:doctrine-query`            |
| Forms          | `/symfony:form-type`, `/symfony:form-render`, `/symfony:form-handle`, `/symfony:form-advanced`  |
| Events         | `/symfony:event-dispatch`, `/symfony:event-listen`, `/symfony:event-subscribe`                  |
| Services / DI  | `/symfony:service-define`, `/symfony:service-wire`, `/symfony:service-tags`                     |
| Validation     | `/symfony:validation-constraints`, `/symfony:validation-groups`, `/symfony:validation-use`      |
| HTTP Client    | `/symfony:http-client-request`, `/symfony:http-client-async`, `/symfony:http-client-response`, `/symfony:http-client-test` |
| Messenger      | `/symfony:messenger-async`                                                                      |
| Serializer / Mapper | `/symfony:serializer-use`, `/symfony:object-mapper`                                        |

Préférer ces skills aux conventions ad hoc — elles encodent les patterns retenus pour ce projet.

## Principes de travail

**Tradeoff :** ces principes privilégient la prudence sur la vitesse. Pour une tâche triviale, garde ton jugement.

### 1. Réfléchir avant de coder

Avant d'écrire la moindre ligne, **énonce tes hypothèses explicitement**. Si la demande est ambiguë, **demande** plutôt que de trancher en silence : présente les interprétations possibles et laisse l'utilisateur arbitrer. Une approche plus simple existe ? Dis-le. Quelque chose bloque ? Nomme-le et remonte-le immédiatement. Une question posée en amont coûte moins cher qu'une correction après coup.

### 2. Simplicité d'abord

Écris le **code minimal qui résout le problème posé** — rien de spéculatif. Pas de fonctionnalité au-delà de ce qui est demandé, pas d'abstraction pour un usage unique, pas de « configurabilité » non demandée, pas de gestion d'erreur pour un scénario impossible. Question à se poser : « Un dev sénior trouverait-il cela sur-conçu ? » Si oui, resserre.

### 3. Modifications chirurgicales

Quand tu modifies du code existant, **ne touche qu'à ce que la demande exige**. N'« améliore » pas le code adjacent, ni les commentaires, ni le formatage ; ne refactore pas ce qui n'est pas cassé. Préserve le style alentour. Code mort sans rapport repéré ? Signale-le, ne le supprime pas. Ne supprime que les imports, variables ou fonctions que **tes** changements ont rendus orphelins. Test : chaque ligne modifiée doit se rattacher directement à la demande.

### 4. Le code d'abord, le commentaire en dernier recours

Un commentaire ne se justifie que si l'information **ne peut pas vivre dans le code** : une contrainte externe (bug d'une lib, contrat d'API imposé), la raison d'un choix non évident, un invariant que le typage n'exprime pas. Ce qu'un nom de méthode, une signature typée ou un test dit déjà ne se commente pas — le redire crée une seconde source de vérité, et c'est elle qui mentira dans six mois. Pas de commentaire de section, pas de paraphrase de la ligne suivante, pas de docblock qui répète les types (PHPStan level 10 les porte déjà). Besoin d'expliquer *ce que* fait un bloc : renomme ou extrais plutôt que de commenter.

### 5. Exécution pilotée par l'objectif

Transforme une demande floue en **critères de succès vérifiables** avant d'implémenter, puis **boucle jusqu'à validation** :

- « Ajouter une validation » → écrire les tests PHPUnit des entrées invalides, puis les faire passer
- « Corriger le bug » → écrire un test qui le reproduit, puis le faire passer
- « Refactor X » → tests verts avant et après, `make lint` propre
- « Nouvel écran » → scénario Playwright (`data-test`) qui passe via `make playwright-file`

---

**Ces directives fonctionnent si :** moins de modifications inutiles dans les diffs, moins de réécritures dues à la sur-conception, et les questions de clarification arrivent **avant** l'implémentation plutôt qu'après les erreurs.

*Principes adaptés de [multica-ai/andrej-karpathy-skills](https://github.com/multica-ai/andrej-karpathy-skills) (MIT).*

<!-- BEGIN AI_MATE_AGENTS_IMPORT -->
@AGENTS.md
<!-- END AI_MATE_AGENTS_IMPORT -->
