# Plan technique — Lire sur la roadmap, sans rien survoler, quand chaque projet et chaque lot se termine

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/018-f-roadmap-dates-lisibles/`
> **Amont** : `pitch.md`

## Approche retenue

La story est un rendu, pas un calcul. Tout ce que la ligne de date affiche est déjà dans les `RoadmapRow` que construit `RoadmapBuilder::build()` (`src/Service/RoadmapBuilder.php:42-55`). Une feuille porte `start`, `end`, `lastDay` et ses `signals` (`leafRow()`, l. 189-244). Un lot découpé et un projet portent `start`, `end` (connue seulement si aucune feuille planifiée n'a une fin inconnue, `spanRow()` l. 161-180), `lastDay`, leurs `children` et, pour le projet, les signaux `Unsplit` ou `PartialPlanning` (`projectRow()` l. 124-139). Les raisons d'une fin manquante sont déjà portées par `RoadmapSignal::withholdsEnd()` (`src/Enum/Type/RoadmapSignal.php:103`), et les feuilles qui retiennent une fin par `RoadmapRow::unknownEndLeaves()` (`src/Model/Roadmap/RoadmapRow.php:123`), toutes deux livrées par les corrections rapides du 2026-10-12. Il manque deux lectures, sans requête : les feuilles non planifiées sous une ligne (`RoadmapRow::unplannedLeaves()`), et la position d'un jour par rapport à la fenêtre (`RoadmapWindow::isBefore()` / `isAfter()`).

Un partiel `templates/roadmap/_end.html.twig` écrit la ligne de date d'une ligne de frise (règles 2 à 9 du pitch) et, quand des lots sont en cause, son infobulle. Celle-ci réutilise `templates/roadmap/_unknown_ends.html.twig`, déjà affiché sur la page d'un projet et celle d'une personne. Le partiel est inclus dans la cellule de titre du `<summary>` d'un projet (`templates/roadmap/index.html.twig:41-48`) et dans celle de `templates/roadmap/_row.html.twig` (l. 8-23), qui sert aux lots découpés et aux feuilles, sur la roadmap comme sur la frise de la page d'un projet. Un second partiel, `_outside.html.twig`, pose le repère de bord (règle 14) dans la piste quand une ligne a des dates mais aucune barre dans la fenêtre. Enfin, la largeur de la colonne des titres, écrite en dur à `20rem` dans sept grilles, un calcul de largeur et une superposition, devient une variable CSS posée sur le cadre de la frise (`_frieze.html.twig:5`), plus étroite sous le point de rupture `md` (règles 15 et 16).

### Mécanismes mobilisés

- **Modèles calculés `RoadmapRow` et `RoadmapWindow`** : les deux nouvelles lectures sont des méthodes pures sur ces objets immuables, testables en unitaire, sur le modèle de `unknownEndLeaves()` et `RoadmapWindow::contains()`. Aucun service ni repository n'est touché, donc aucune requête nouvelle : la règle « pas de requête dans un gabarit » tient, puisque les gabarits ne lisent que des propriétés déjà chargées (`Lot::getTitle()`, `getParent()` par `RoadmapRow::leafPath()`, déjà lus par les récapitulatifs).
- **`RoadmapSignal::withholdsEnd()`** : filtre les raisons d'une explication. `ToReplan` y vaut `false`, ce qui garantit la règle 11 (jamais « à replanifier » dans une explication) sans condition de rôle dans les gabarits.
- **Contrôleur Stimulus `roadmap-tooltip`** (story 009) : l'infobulle des lots en cause est un `div role="tooltip" hidden` déclenché par `data-roadmap-tooltip` sur la ligne de date, comme les pastilles. Dans un `<summary>`, le déclencheur reste non focalisable (même contrainte que `_signal.html.twig` avec `focusable: false`, `index.html.twig:45-47`).
- **`aria-describedby`** : le `<summary>` d'un projet, et la cellule de titre d'un lot découpé, pointent vers l'infobulle. Un nœud masqué référencé par `aria-describedby` reste lu par les lecteurs d'écran : la liste est accessible sans survol, sans alourdir le nom accessible du `<summary>` (règle 17). La date elle-même est du texte dans le `<summary>` : elle fait partie de son nom accessible, à la suite du titre.
- **Variable CSS et propriété arbitraire Tailwind** : `[--roadmap-labels:11rem] md:[--roadmap-labels:20rem]` sur le cadre de la frise, et `grid-cols-[var(--roadmap-labels)_1fr]` partout où `grid-cols-[20rem_1fr]` est écrit. Le `calc()` de la largeur minimale (`_frieze.html.twig:6`) et la superposition de l'icône d'ouverture (`index.html.twig:64`, `w-80`) lisent la même variable. `roadmap_controller.js` mesure la piste à l'exécution (`#trackGeometry()`, l. 88-94) : le zoom et le recentrage suivent sans changement.
- **`<time datetime>`** : la date d'une ligne est balisée pour les technologies d'assistance, au format `Y-m-d`.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Écrire la date au bout de la barre, dans la piste | Coupée ou absente quand la barre déborde ou tombe hors de la fenêtre, recouvre les tronçons voisins au fort zoom, invisible sur téléphone sans défilement. La colonne des titres est collante et toujours visible. |
| Ajouter une colonne « Fin » dédiée entre les titres et la frise | Élargit encore la partie fixe, ce qui aggrave le problème du téléphone ; la ligne sous le titre ne coûte que de la hauteur. |
| Calculer le texte de la ligne de date en PHP (méthode `endLabel()` sur `RoadmapRow`) | Les libellés et l'accord du pluriel sont de la présentation ; le modèle expose les faits (`end`, `unknownEndLeaves()`, `unplannedLeaves()`), le gabarit les met en mots, comme `_recap.html.twig` le fait pour la fin d'une feuille. |
| Lister les lots en cause en clair, sous la ligne du projet | Une ligne de projet pourrait compter une dizaine de lots en cause : la colonne des titres deviendrait illisible, projets repliés. Le compte en clair plus la liste à la demande tiennent la règle 10 ; le détail lot par lot se lit aussi en dépliant. |
| Une liste `sr-only` dans le `<summary>` | Son texte entrerait dans le nom accessible du bouton de dépliage, lu à chaque passage du focus. `aria-describedby` la rend disponible comme description. |
| Retirer les pastilles « planning partiel » et « à découper », redondantes avec la ligne de date | Touche aux signaux de la story 006 et aux tests qui les vérifient ; question renvoyée au pitch (§Questions ouvertes). |

## Modèle de données

Aucun impact modèle.

Deux méthodes s'ajoutent à des modèles calculés, non persistés :

- `src/Model/Roadmap/RoadmapRow.php` : `unplannedLeaves(): list<RoadmapRow>`, les feuilles sous la ligne (ou la ligne elle-même si c'est une feuille) qui n'ont pas de début (`null === $start`), dans l'ordre des `children`, sur le modèle récursif de `unknownEndLeaves()`.
- `src/Model/Roadmap/RoadmapWindow.php` : `isBefore(\DateTimeImmutable $day): bool` (le jour précède `firstDay`) et `isAfter(\DateTimeImmutable $day): bool` (le jour suit `lastDay`), comparés au format `Y-m-d` comme `contains()` (l. 73-78).

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `templates/roadmap/_end.html.twig` | Ligne de date d'une ligne de frise (`data-test="roadmap-end"`, `data-state` = `known`, `unknown`, `unplanned` ou `unsplit`) : date en `<time>`, raison d'une feuille par ses signaux filtrés sur `withholdsEnd`, compte des lots en cause d'un projet ou d'un lot découpé ; infobulle `role="tooltip"` (`roadmap-end-<lot|project>-<id>`) qui inclut `_unknown_ends.html.twig` sur `unknownEndLeaves()` puis `unplannedLeaves()`. |
| `templates/roadmap/_outside.html.twig` | Repère de bord d'une ligne qui a des dates mais aucune barre dans la fenêtre : « ‹ avant cette période » au bord gauche si `window.isBefore(lastDay)`, « après cette période › » au bord droit si `window.isAfter(start)` (`data-test="roadmap-outside"`, `data-side`). |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Model/Roadmap/RoadmapRow.php` | Ajouter `unplannedLeaves()`. |
| `src/Model/Roadmap/RoadmapWindow.php` | Ajouter `isBefore()` et `isAfter()`. |
| `templates/roadmap/_frieze.html.twig` | Poser `[--roadmap-labels:11rem] md:[--roadmap-labels:20rem]` sur le cadre ; `calc()` de la largeur minimale et grille de l'en-tête sur `var(--roadmap-labels)`. |
| `templates/roadmap/index.html.twig` | `<summary>` : grille sur la variable, `aria-describedby` vers l'infobulle de fin quand des lots sont en cause, inclusion de `_end.html.twig` sous le titre et les pastilles, de `_outside.html.twig` dans la piste quand `project.span` est nul et `project.start` connu ; superposition de l'icône d'ouverture en `w-[var(--roadmap-labels)]`. |
| `templates/roadmap/_row.html.twig` | Grille sur la variable ; retraits `ps-9` / `ps-14` réduits sous `md` ; inclusion de `_end.html.twig` sous le titre (feuille et lot découpé), `aria-describedby` de la cellule de titre d'un lot découpé ; inclusion de `_outside.html.twig` quand la ligne n'a aucune barre dans la fenêtre. |
| `templates/roadmap/project.html.twig` | Grille de la ligne du projet en tête de frise sur la variable ; cette ligne ne reçoit pas de ligne de date (règle 18). Si la story 016 est livrée avant, la même modification s'applique au gabarit de la page projet unique qui reprend cette frise. |
| `templates/person/show.html.twig` | Grilles de la ligne de charge et des lignes de projet sur la variable. |
| `templates/person/_row.html.twig` | Grille sur la variable ; retrait `ps-9` réduit sous `md`. |
| `tests/Unit/Model/Roadmap/RoadmapRowTest.php` | Cas de `unplannedLeaves()` et `unknownEndLeaves()`. |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | Cas de `isBefore()` et `isAfter()` aux bornes. |
| `tests/Controller/RoadmapControllerTest.php` | Lignes de date des projets et des lots, explications, repères hors période, accessibilité, rôles (voir §Stratégie de test). |
| `tests/Controller/RoadmapProjectControllerTest.php` | Lignes de date des lots de la frise ; en-tête inchangé. |
| `tests/e2e/roadmap.spec.ts` | Scénario téléphone et zoom (voir §Stratégie de test). |

## Hors scope

- **Calcul de la fin, signaux, fenêtre de 41 semaines, navigation par 4 semaines** : inchangés (`Scheduler`, `RoadmapBuilder`, `RoadmapSignal::of()`).
- **Signal `PartialPlanning` d'un projet sans aucune feuille planifiée** : inchangé, en attendant l'arbitrage du pitch (§Questions ouvertes).
- **Libellé « non calculée » du récapitulatif d'une feuille non planifiée** (`_recap.html.twig:18`) : non retouché ; la story 015 tranche le vocabulaire.
- **Frise de la page d'une personne** : seule sa largeur de colonne change ; pas de ligne de date (`person/_row.html.twig`).
- **Réalignement de `DESIGN.md`** (ligne de date de la roadmap, colonne des titres variable selon la largeur d'écran, repère hors période) : relève de `/forge:sync`.
- **Poids du HTML de la roadmap** (dette de la story 009) : une infobulle de plus par projet ou lot découpé qui a des lots en cause, pas par feuille ; pas d'optimisation embarquée.

## Impacts transverses

- **Cloisonnement des données** : non concerné. Aucune donnée de personne n'est ajoutée ; `withholdsEnd()` exclut `ToReplan`, seul signal réservé aux leads (`RoadmapController` passe `isGranted('ROLE_LEAD')` à `build()`), donc aucune explication ne varie selon le rôle.
- **Déclinaisons / thèmes** : non. Tokens du design system « Cadence » (`text-body`, `text-heading`, `text-xs`, `tabular-nums`), en clair comme en sombre.
- **Traduction / i18n** : libellés en français dans les gabarits, accord du pluriel en Twig ; l'application est en français uniquement.
- **API / exposition externe** : non.
- **Droits d'accès** : inchangés. Aucun voter ; la page reste ouverte à toute personne connectée.
- **Emails / notifications** : non.
- **Migration de données** : aucune.
- **Comportement par défaut** : la ligne de date s'affiche pour tous dès la mise en service.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `tests/Unit/Model/Roadmap/RoadmapRowTest.php` | unit | `unplannedLeaves()` : une feuille sans début se rend elle-même, une feuille planifiée rien ; un lot découpé et un projet rendent leurs feuilles sans début dans l'ordre, sous-lots compris. `unknownEndLeaves()` : seules les feuilles planifiées sans fin, jamais une feuille sans début. |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | unit | `isBefore()` vrai la veille du premier jour, faux le premier jour ; `isAfter()` vrai le lendemain du dernier jour, faux le dernier jour. |
| `tests/Controller/RoadmapControllerTest.php` | functional (HTTP) | Projet planifié : `roadmap-end` du `<summary>` en `known` avec la date de la feuille la plus tardive, identique à `roadmap-recap-end` de cette feuille. Feuille en dépassement : la feuille dit « Fin inconnue : en dépassement », son projet « Fin inconnue : 1 lot en cause », l'infobulle liste la feuille, et le `<summary>` pointe vers elle par `aria-describedby`. Projet partiel : « hors 2 lots non planifiés » et l'infobulle liste ce qui leur manque. Projet sans feuille planifiée : « Non planifié : 3 lots ». Projet sans lot (« Évolution du portail ») : « Pas encore découpé en lots ». Feuille surchargée vue par un lead : « à replanifier » absent de toute `roadmap-end` et de toute infobulle de fin. Projet terminé avant la fenêtre (`/roadmap/2027-W30`) : date en clair et `roadmap-outside` à `data-side="before"` ; projet qui commence après (`/roadmap/2026-W20`) : `data-side="after"`. Mêmes lignes pour la prod. |
| `tests/Controller/RoadmapControllerTest.php` | functional (HTTP) | `testQueryCountDoesNotGrowWithTheNumberOfLeaves` inchangé et vert : aucune requête par ligne. |
| `tests/Controller/RoadmapProjectControllerTest.php` | functional (HTTP) | Chaque feuille de la frise de la page d'un projet porte sa `roadmap-end` ; `project-page-end` et `unknown-ends` de l'en-tête sont inchangés ; la ligne du projet en tête de frise n'a pas de `roadmap-end`. |
| `tests/e2e/roadmap.spec.ts` | E2E | Écran de 390 × 844 : la ligne de date du projet E2E est entièrement dans la fenêtre du navigateur sans défilement horizontal de la frise ; la frise défile toujours. À ×8, la ligne de date reste visible et identique. Les scénarios existants du zoom, de la navigation et des infobulles restent verts. |

**Hors scope tests** :

- Pas de test du rendu visuel des retraits et de la hauteur des lignes : vérifiés à la recette sur la roadmap des données de démonstration (Nova, Kadence, Atlas), en clair et en sombre.
- Pas de test fonctionnel dédié à la page d'une personne : seule la largeur de colonne change, couverte par `tests/e2e/person.spec.ts` existant et la recette.
- La lecture effective par un lecteur d'écran n'est pas automatisée : le test vérifie le texte du `<summary>` et le lien `aria-describedby` ; l'audit AccessLint en clair et en sombre (`DESIGN.md`, §Règles) complète.

## Ordre d'exécution

1. [ ] **Lectures du modèle**
   - Objectif : `RoadmapRow::unplannedLeaves()`, `RoadmapWindow::isBefore()` et `isAfter()`.
   - Fichiers : `src/Model/Roadmap/RoadmapRow.php`, `src/Model/Roadmap/RoadmapWindow.php`, leurs tests unitaires.
   - Vérification : `make phpunit-filter 'RoadmapRowTest|RoadmapWindowTest'`.
   - Commitable seule : oui.

2. [ ] **Ligne de date et explication**
   - Objectif : `_end.html.twig` inclus dans le `<summary>` d'un projet et dans `_row.html.twig` ; infobulle des lots en cause et `aria-describedby`.
   - Fichiers : `templates/roadmap/_end.html.twig`, `templates/roadmap/index.html.twig`, `templates/roadmap/_row.html.twig`, `tests/Controller/RoadmapControllerTest.php`, `tests/Controller/RoadmapProjectControllerTest.php`.
   - Vérification : `make phpunit-filter 'RoadmapControllerTest|RoadmapProjectControllerTest'` ; nombre de requêtes de `/roadmap` relevé avant et après sur les fixtures (profiler), identique.
   - Commitable seule : oui.

3. [ ] **Repère hors période**
   - Objectif : `_outside.html.twig` dans la piste d'un projet et d'une ligne de `_row.html.twig` sans barre dans la fenêtre.
   - Fichiers : `templates/roadmap/_outside.html.twig`, `templates/roadmap/index.html.twig`, `templates/roadmap/_row.html.twig`, `tests/Controller/RoadmapControllerTest.php`.
   - Vérification : `make phpunit-filter RoadmapControllerTest`.
   - Commitable seule : oui.

4. [ ] **Colonne des titres selon la largeur d'écran**
   - Objectif : variable `--roadmap-labels` sur le cadre de la frise, lue par les sept grilles, le `calc()` et la superposition de l'icône ; retraits réduits sous `md`.
   - Fichiers : `templates/roadmap/_frieze.html.twig`, `templates/roadmap/index.html.twig`, `templates/roadmap/_row.html.twig`, `templates/roadmap/project.html.twig`, `templates/person/show.html.twig`, `templates/person/_row.html.twig`, `tests/e2e/roadmap.spec.ts`.
   - Vérification : `make build` génère les classes `[--roadmap-labels:…]` ; `make playwright-file tests/e2e/roadmap.spec.ts` et `tests/e2e/person.spec.ts` ; recette à 390 px et à 1440 px sur la roadmap, la page d'un projet et celle d'une personne.
   - Commitable seule : oui.

5. [ ] **QA finale**
   - Objectif : qualité et non-régression.
   - Fichiers : aucun.
   - Vérification : `make lint`, `make phpunit`, `make playwright` ; audit AccessLint de `/roadmap` en clair et en sombre.
   - Commitable seule : non.

## Critères de sortie

- [ ] `RoadmapBuilder`, `Scheduler`, `ScheduleLoader` et les repositories ne sont pas modifiés.
- [ ] Le nombre de requêtes de `/roadmap`, de la page d'un projet et de celle d'une personne est identique avant et après la story, sur les fixtures.
- [ ] Aucune occurrence de `grid-cols-[20rem_1fr]` ni de `20rem` en dur ne subsiste dans les gabarits de frise ; la largeur vient de `--roadmap-labels`.
- [ ] Aucun gabarit ne teste le rôle pour composer une explication de fin ; seule `withholdsEnd()` filtre les raisons.
- [ ] Suite de tests du projet verte (`make phpunit`, `make playwright`), sans nouvelle régression.
- [ ] `make lint` propre (PHP-CS-Fixer, PHPStan niveau 10).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Colonne des titres encombrée : titre long, deux pastilles et ligne de date font de hautes lignes, surtout à 11rem | moyenne | Ligne de date en `text-xs` sur une ligne qui passe à la ligne au besoin ; compte des lots en cause plutôt que leur liste ; recette sur Nova (« planning partiel » + date) et sur les sous-lots à 390 px. |
| Régression de la frise de la page d'un projet ou d'une personne, qui partagent `_frieze.html.twig` et `_row.html.twig` | moyenne | Une seule variable posée sur le cadre commun ; tests fonctionnels des deux pages, `person.spec.ts`, recette visuelle des trois frises. |
| Zoom et recentrage décalés par une colonne de largeur variable | faible | `#trackGeometry()` mesure la piste à l'exécution ; scénarios E2E du zoom existants et nouveau scénario à ×8 sur téléphone. |
| Nom accessible du `<summary>` trop long | faible | Seule la ligne de date entre dans le nom ; la liste des lots en cause passe par `aria-describedby`. |
| Date de la ligne différente de celle du récapitulatif ou de l'infobulle | faible | Même source (`row.end`) ; un test compare `roadmap-end` et `roadmap-recap-end`. |
| Classe à propriété arbitraire non générée par le build Tailwind | faible | Classe écrite en entier dans le gabarit (pas de concaténation) ; vérification après `make build`. |
| Repère hors période qui encombre un projet déplié dont plusieurs feuilles sont hors fenêtre | faible | Repère seulement sur une ligne sans aucune barre dans la fenêtre, en texte discret au bord. |

## Questions ouvertes

- **Largeur de la colonne des titres sur téléphone** : 11rem laisse environ 180 px de frise visibles sur un écran de 390 px. Options : (a) 11rem ; (b) 12rem, plus confortable pour les titres longs. À régler à la recette, (a) par défaut.
- **Sous-lots sur téléphone** : le retrait de deux niveaux (`ps-14`) mange la colonne resserrée. Options : (a) retraits réduits sous `md` (prévu) ; (b) retrait unique pour les deux niveaux sous `md`. À trancher à la recette.
