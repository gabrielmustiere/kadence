# Plan technique — Consulter la fiche d'un projet : son consommé face à l'estimé, sa frise complète et la timeline de tous ses tronçons

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/010-f-fiche-projet/`
> **Amont** : `pitch.md`

## Approche retenue

La fiche est une vue de la roadmap restreinte à un seul projet. Rien n'est stocké : tout se calcule à la lecture, dans les modèles de vue existants. `RoadmapBuilder` gagne `buildProject(Project, bool $withOverloads)`. Comme `build()`, il planifie **toutes** les feuilles, car la capacité est partagée entre projets et la fin d'un projet dépend des autres. Il calcule ensuite les **bornes** du projet : les jours atteints par ses feuilles planifiées (début → dernier jour), réunis avec les jours saisis sur toutes ses feuilles. Il crée une `RoadmapWindow` à ces bornes, puis construit l'unique `RoadmapRow` du projet avec les méthodes privées que la roadmap utilise déjà. La frise de la fiche est donc, par construction, celle de la roadmap. Le découpage des jours saisis en tronçons (`RoadmapRun`), dans et au-delà de l'estimation, sort de `RoadmapBuilder` dans un service `RoadmapRunCutter`, partagé par `build()` et `buildProject()`. Sur la fiche, il est appelé pour **toutes** les feuilles saisies du projet, planifiées ou non. Les tronçons des feuilles planifiées deviennent aussi les barres, comme sur la roadmap. La timeline les aplatit en entrées `TimelineEntry` (feuille, tronçon, au-delà de l'estimation ou non ; fabrique `ofLeaves()`), rangées par mois (`TimelineMonth`). Le résultat est un `ProjectRoadmap` : une `Roadmap` à un seul projet, sa `RoadmapRow` en propriété `project`, la timeline, et un drapeau `dated` faux quand le projet n'a aucune date.

`RoadmapWindow` devient une plage de jours : elle garde ses bornes au lieu de les déduire de `WEEK_COUNT`. `around(Week)` ne change pas pour la roadmap. `spanning(from, to)` couvre les semaines entières des bornes, sur 4 semaines au moins. Pour que la frise d'un projet long reste lisible, `Roadmap` expose une largeur minimale de piste, `minTrackRem()`. Elle vaut 52rem jusqu'à 41 semaines, puis grandit avec le nombre de semaines, pour garder au minimum la densité de la roadmap à ×1. Le template l'injecte dans la formule de largeur existante, et la frise défile au-delà. Le seuil des libellés de mois, `MIN_MONTH_WIDTH`, s'en déduit au lieu d'être figé à 9 %, sans changement pour la roadmap. Le tableau du consommé face à l'estimé vient de `ProjectRollup`, qui cumule désormais le saisi, le restant et le dépassement, ces deux derniers séparément, à partir de `TimeEntryRepository::sumQuartersByLot()`. La route `app_roadmap_project` (`/roadmap/projets/{id}`) vit dans `RoadmapController`, ce qui allume « Roadmap » dans le menu. Elle reçoit la semaine d'origine en `?roadmap=` pour le lien de retour, comme `LotController::edit`, et la lit par `Week::tryFromIso()`, partagée avec `LotController`. Sur la roadmap, le lien-icône vers la fiche est superposé à la colonne collante des titres, hors du `<details>` de chaque projet.

### Mécanismes mobilisés

- **Réutilisation des méthodes privées de `RoadmapBuilder`** (`projectRow`, `leafRow`, `splitLotRow`), **de `RoadmapRunCutter`** et **de `RoadmapTeamLine::forLeaf()`** : la frise de la fiche ne peut pas diverger de la roadmap (règle 9 du pitch). Seuls la fenêtre et le périmètre des feuilles changent. Sortir le découpage en tronçons dans un service tient `RoadmapBuilder` sous la limite de complexité cognitive de PHPStan (40).
- **Fabrique nommée sur `RoadmapWindow`** (`spanning()` à côté de `around()`) : un seul calcul de barres (`bar()`, `segmentedBar()`, `position()`) pour les deux frises.
- **Variables CSS sur la frise** (`--roadmap-weeks`, `--roadmap-zoom`, `--roadmap-min-track`) : les lignes de semaines, le zoom et la largeur minimale suivent la fenêtre sans CSS propre à la fiche.
- **`{% embed %}` Twig** pour la coquille de la frise (cadre qui défile, contrôleur d'infobulles, en-tête des mois). La roadmap et la fiche ne remplissent que le bloc des lignes, l'une avec des `<details>` pliables, l'autre avec une ligne de projet fixe.
- **Couche superposée avec un conteneur `sticky`** pour le lien-icône de la roadmap : un navigateur expose un `<summary>` comme un bouton, qui ne peut pas contenir de lien. Le lien reste dans la colonne des titres, y compris quand la frise zoomée défile.
- **Valeur Stimulus `remember`** sur `roadmap_controller.js` : la fiche réutilise le zoom (paliers, point fixe) sans `sessionStorage`, ouverte à ×1 et centrée sur aujourd'hui borné à la frise.
- **`#[MapEntity(expr: 'repository.findOneForDetail(id)')]`** et **`#[MapQueryParameter]`**, comme `ProjectController::show` et `LotController::edit`.
- **`roadmap-tooltip` et les partiels `_bar`, `_tooltip`, `_recap`, `_team`, `_today`**, réutilisés tels quels. `_team.html.twig` sert aussi aux entrées de la timeline.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Une interface de frise implémentée par `RoadmapWindow` et une nouvelle `ProjectWindow` | Le calcul des barres serait dupliqué ou déplacé dans une classe abstraite, pour deux fabriques qui ne diffèrent que par leurs bornes. |
| Un `ProjectOverviewBuilder` et un contrôleur sous `/projets/{id}/fiche` | Les routes `app_project_` allument « Projets », l'entrée d'administration. Il aurait fallu une exception dans `NavigationExtension`, et un service de plus qui ne fait qu'assembler `RoadmapBuilder` et `ProjectRollup`. |
| Frise toujours ajustée à la largeur de l'écran | Écartée au plan par l'utilisateur. Sur un projet de 2 ans, un mois ferait environ 30 px pour un libellé de 63 px, et les libellés se chevaucheraient. |
| Pas des libellés de mois adaptatif (un mois sur 2, 3, 6 ou 12) | Inutile dès que la frise garde la densité de la roadmap et défile. |
| Tronçons des seules feuilles planifiées dans la timeline (pitch à la lettre) | Le temps saisi sur une feuille non planifiée aurait manqué au journal, alors que le tableau le compte. |
| Lien-icône imbriqué dans le `<summary>` de chaque projet | Écarté en review (A11Y) : contrôle interactif imbriqué dans un élément exposé comme bouton. |
| Garder le découpage en tronçons dans `RoadmapBuilder` | La classe dépassait la limite de complexité cognitive de PHPStan (61, puis 44, pour 40). |
| Construire la timeline en relisant les segments des barres | Les segments ne gardent que les tronçons dans la fenêtre et n'existent pas pour une feuille non planifiée. La timeline part des `RoadmapRun`, avant leur placement. |

## Modèle de données

Aucun impact modèle.

Seuls des modèles de vue non persistés sont créés (`ProjectRoadmap`, `TimelineEntry`, `TimelineMonth`) ou modifiés (`RoadmapWindow`, `Roadmap`, `LotSummary`, `ProjectSummary`) (voir Périmètre).

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Model/Roadmap/ProjectRoadmap.php` | La fiche d'un projet : la `Roadmap` à un seul projet, sa `RoadmapRow` en propriété `project`, la timeline (`list<TimelineMonth>`) et `dated` (le projet a au moins une date). |
| `src/Model/Roadmap/TimelineEntry.php` | Une entrée de la timeline : la feuille (`Lot`), son `RoadmapRun`, `beyondEstimate` ; `leafPath()` rend « Lot · Sous-lot » ou « Lot » ; `ofLeaves()` liste les tronçons dans l'ordre des feuilles, dans puis au-delà de l'estimation. |
| `src/Model/Roadmap/TimelineMonth.php` | Un mois de la timeline : libellé (« Septembre 2026 ») et ses entrées. `group(list<TimelineEntry>)` trie par dernier jour décroissant, puis premier jour décroissant, puis ordre des feuilles, et range chaque entrée sous le mois de son dernier jour. |
| `src/Service/RoadmapRunCutter.php` | `cut()` : découpe les jours saisis de chaque feuille en tronçons, dans et au-delà de l'estimation ; la référence de découpe est l'équipe de la feuille ou, sans équipe, toutes les personnes qui y ont saisi. |
| `templates/roadmap/project.html.twig` | La fiche : lien « Roadmap » vers la semaine d'origine, titre, description, synthèse, puis tableau, frise et timeline. Un projet non découpé n'affiche que « pas encore découpé », un projet sans date un message à la place de la frise. |
| `templates/roadmap/_frieze.html.twig` | Coquille de la frise, embarquée par la roadmap et la fiche : cadre qui défile (`roadmap-tooltip`, `--roadmap-weeks`, `--roadmap-zoom`, `--roadmap-min-track`), en-tête des mois et bloc `rows`. |
| `templates/roadmap/_zoom.html.twig` | Boutons « − », « + », « 100 % » et palier affiché, extraits de `index.html.twig`. |
| `templates/roadmap/_legend.html.twig` | Légende de la frise et période affichée, extraites de `index.html.twig`. |
| `templates/roadmap/_consumption.html.twig` | Tableau du consommé face à l'estimé : une ligne par lot et sous-lot dans l'ordre du découpage, puis la ligne du projet. Colonnes estimé (« partiel », « à estimer »), saisi, restant ou dépassement (les deux sur un cumul qui a les deux). |
| `templates/roadmap/_consumption_row.html.twig` | Une ligne du tableau (lot, sous-lot ou projet) : estimé, « à estimer », saisi, restant et dépassement. |
| `templates/roadmap/_timeline.html.twig` | Timeline verticale : un intertitre par mois, puis une entrée par tronçon (feuille, période, saisi, jours avec saisie, mention au-delà de l'estimation, équipe par `_team.html.twig`), ou un message sans saisie. |
| `tests/Unit/Model/Roadmap/TimelineMonthTest.php` | Ordre des entrées, mois du dernier jour pour un tronçon à cheval, libellés en français, `leafPath()`. |
| `tests/Controller/RoadmapProjectControllerTest.php` | Fiche sur l'exemple du pitch : accès de tous les rôles, absence de liens de gestion, tableau, synthèse, timeline, largeur minimale de la frise et projet de 57 semaines, lien-icône de la roadmap, retour à la semaine d'origine, semaine invalide ignorée, 404, projet non découpé ou sans date, menu, nombre de requêtes constant. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Model/Roadmap/RoadmapWindow.php` | Bornes stockées (`firstDay()`, `lastDay()`), `dayCount()` déduit des bornes, `weekCount()`. Ajouter `spanning(from, to)` : du lundi de la semaine de `from` au dimanche de la semaine de `to`, sur 4 semaines au moins, ancrée sur la semaine de `from`. `around()`, `previous()`, `next()` et le calcul des barres sont inchangés. |
| `src/Model/Roadmap/Roadmap.php` | Ajouter `minTrackRem()`, soit `max(52, 52 × semaines ÷ 41)`. Le seuil d'un mois coupé au bord gauche se déduit de la largeur du libellé rapportée à `minTrackRem()` : toujours 9 % sur 41 semaines. Ajouter `centerPosition()`, la position d'aujourd'hui bornée à [0, 100]. |
| `src/Service/RoadmapBuilder.php` | Le découpage des tronçons (`enteredParts()` en partie, `runs()`, `run()`) part dans `RoadmapRunCutter`, injecté ; `enteredParts()` ne fait plus que placer les tronçons des feuilles planifiées ; `team()` devient `RoadmapTeamLine::forLeaf()`. Extraire de `leafRow()` le début et le dernier jour atteint d'une feuille (`reach()`), réutilisé pour les bornes. Ajouter `buildProject()`, `leavesOf()` et `bounds()` : la découpe est limitée aux feuilles saisies du projet, et la timeline est construite. |
| `src/Model/LotSummary.php` | Ajouter `enteredQuarters`, `remainingQuarters` et `overrunQuarters`. |
| `src/Model/ProjectSummary.php` | Ajouter les mêmes cumuls pour le projet. |
| `src/Service/ProjectRollup.php` | Une feuille rend son saisi, son restant (estimé − saisi, au moins 0) et son dépassement (saisi − estimé, au moins 0). Une feuille à estimer n'a ni restant ni dépassement. Lots découpés et projet additionnent chacun des trois séparément. |
| `src/Controller/RoadmapController.php` | Ajouter `project()` : route `app_roadmap_project` `GET /roadmap/projets/{id}`, projet par `findOneForDetail`, `?roadmap=` lu par `Week::tryFromIso()` et ignoré s'il est invalide. Rendre `roadmap/project.html.twig` avec `buildProject($project, isGranted('ROLE_LEAD'))`, `ProjectRollup::summarize($project, sumQuartersByLot($project))` et la semaine de retour. |
| `templates/roadmap/index.html.twig` | Embarquer `_frieze.html.twig`, `_zoom.html.twig` et `_legend.html.twig` sans changer le rendu. Envelopper chaque projet d'un `div` (`data-test="roadmap-project"`) qui porte le `<details>` et, superposé à la colonne collante des titres hors du `<summary>`, un lien-icône vers `app_roadmap_project`, avec `?roadmap=` la semaine affichée, un `aria-label` « Ouvrir la fiche de … » et `data-test="roadmap-project-open"`. |
| `templates/roadmap/_row.html.twig` | Variable `links`, vraie par défaut : à faux, le titre d'une feuille est le `<span tabindex="0">` qui porte le récapitulatif, jamais le lien de modification. |
| `src/Model/Roadmap/RoadmapTeamLine.php` | Fabrique `forLeaf()`, l'ancienne `RoadmapBuilder::team()`. |
| `src/Model/Week.php` | Fabrique `tryFromIso(): ?self`. |
| `src/Controller/LotController.php` | `roadmapWeek()` retirée au profit de `Week::tryFromIso()`. |
| `assets/controllers/roadmap_controller.js` | Valeur `remember` (booléen, vrai par défaut). À faux, le palier part de ×1, n'est ni lu ni écrit en `sessionStorage`, et la frise est centrée sur `center` dès la connexion, même à ×1. |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | `spanning()` : alignement sur les semaines, 4 semaines au moins, `weekCount()`, barres placées sur une fenêtre de projet. `minTrackRem()` et seuil des mois inchangés à 41 semaines, libellé de chaque mois sur une fenêtre de 2 ans, `centerPosition()` bornée. |
| `tests/Unit/Service/ProjectRollupTest.php` | Saisi, restant et dépassement d'une feuille ; cumuls séparés d'un lot et du projet (5 j de dépassement et 3 j de restant) ; feuille à estimer sans restant. |
| `tests/Service/RoadmapBuilderTest.php` | `buildProject()` : bornes (début, fin calculée, jours saisis, 4 semaines au moins), une seule ligne de projet, timeline sur l'exemple du pitch, tronçons au-delà de l'estimation marqués, feuille non planifiée saisie présente dans la timeline sans barre, feuille sans équipe coupée sur les jours de toutes les personnes qui y ont saisi, projet sans date (`dated` faux). |
| `tests/Unit/Model/WeekTest.php` | `tryFromIso()` : semaines invalides et semaine existante. |
| `tests/e2e/roadmap.spec.ts` | Cliquer sur l'icône ouvre la fiche sans déplier le projet ; la fiche s'ouvre à ×1 après un zoom sur la roadmap ; l'infobulle d'un tronçon s'affiche sur la frise de la fiche ; la timeline liste les tronçons de la feuille interrompue. |

## Hors scope

- **Page d'administration du projet** (`/projets/{id}`) : inchangée, sans lien vers la fiche (hors scope du pitch).
- **Centrage de la roadmap à ×1** : la roadmap garde son comportement (centrage au-delà de ×1 seulement). Seule la fiche se centre dès ×1.
- **Mise en cache de la planification** : `buildProject()` planifie toutes les feuilles à chaque affichage, comme la roadmap. Pas de cache tant que ce coût n'est pas mesuré comme un irritant.
- **Requête globale `sumQuartersByLotAndUser()`** : gardée telle quelle, une seule requête groupée, filtrée en PHP sur les feuilles du projet.
- **Données de démonstration** : les fixtures produisent déjà des projets planifiés et saisis ; aucune donnée ajoutée.

## Impacts transverses

- **Cloisonnement des données** : sans objet (pas de multi-organisation). La timeline expose le temps saisi par personne et par tronçon, comme l'infobulle de la story 009 (tension avec le principe 2 assumée au pitch).
- **Déclinaisons / thèmes** : non.
- **Traduction / i18n** : libellés en français dans les templates, comme le reste de l'application. Les noms complets des mois de la timeline viennent d'une constante de `TimelineMonth`, sur le modèle de `Roadmap::MONTHS`, sans dépendance à l'extension Intl de Twig (non installée).
- **API / exposition externe** : non.
- **Droits d'accès** : aucun contrôle nouveau. La route vit sous `/roadmap`, ouverte à `ROLE_USER` par l'`access_control` existant. Le signal « à replanifier » reste réservé aux leads et à la direction par `withOverloads`. Les liens de modification sont supprimés par `links: false`, et non filtrés par le voter.
- **Emails / notifications** : non.
- **Migration de données** : aucune.
- **Comportement par défaut** : la roadmap a le même rendu, plus l'icône de chaque projet. `around()` et le seuil des mois sur 41 semaines sont inchangés, et les tests existants le vérifient.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/Roadmap/RoadmapWindow.php` | unit | `spanning()` aligne sur les semaines et couvre 4 semaines au moins ; `around()` inchangé ; barres et tronçons placés sur une fenêtre de projet. |
| `src/Model/Roadmap/Roadmap.php` | unit | `minTrackRem()` vaut 52 jusqu'à 41 semaines puis grandit ; les mois de la roadmap sont inchangés ; chaque mois d'une fenêtre de 2 ans a son libellé ; `centerPosition()` bornée. |
| `src/Model/Roadmap/TimelineMonth.php` | unit | Ordre décroissant, départage par premier jour puis ordre des feuilles, mois du dernier jour, libellés, `leafPath()`. |
| `src/Service/ProjectRollup.php` | unit | Saisi, restant, dépassement par feuille ; cumuls séparés ; feuille à estimer. |
| `src/Service/RoadmapBuilder.php` (`buildProject()`) | functional (noyau) | Bornes, ligne du projet identique à celle de `build()`, timeline de l'exemple du pitch, tronçons au-delà de l'estimation, feuille non planifiée et feuille sans équipe, projet sans date. |
| `src/Controller/RoadmapController.php` (`project()`) | functional | Accès des trois rôles, lecture seule (aucun `lot-edit` ni lien de feuille), tableau et synthèse de l'exemple, timeline dans l'ordre sous « Septembre 2026 », retour vers `?roadmap=`, semaine invalide → retour à la roadmap courante, 404, non découpé, sans date, « Roadmap » active dans le menu, nombre de requêtes indépendant du nombre de feuilles, `--roadmap-min-track` à 52rem sur l'exemple et à 72,2927rem avec un libellé pour chacun des 14 mois sur un projet de 57 semaines. |
| `templates/roadmap/index.html.twig` | functional | Lien-icône de chaque projet vers sa fiche avec la semaine affichée (dans `RoadmapProjectControllerTest`). |
| `src/Model/Week.php` | unit | `tryFromIso()` : semaines invalides, semaine existante. |
| Parcours roadmap → fiche | E2E | L'icône ouvre la fiche sans déplier le projet ; zoom à ×1 à l'ouverture malgré un zoom sur la roadmap ; infobulle d'un tronçon sur la frise de la fiche ; timeline de la feuille interrompue. |

**Hors scope tests** :

- Pas de test E2E du défilement horizontal d'un projet de 2 ans : la largeur minimale et les libellés de mois sont couverts en unitaire et dans le HTML de la fiche (test fonctionnel), et la formule de largeur est celle de la roadmap, déjà éprouvée.
- Pas de test de non-régression visuelle de la roadmap après l'extraction des partiels : les tests fonctionnels et E2E existants de la roadmap traversent tous les éléments extraits (`data-test` conservés).
- Pas de test du tactile, comme dans la story 009.

## Ordre d'exécution

1. [x] **Fenêtre bornée et largeur minimale de la frise**
   - Objectif : `RoadmapWindow` en plage de jours avec `spanning()` et `weekCount()` ; `Roadmap::minTrackRem()`, seuil des mois déduit, `centerPosition()`.
   - Fichiers : `RoadmapWindow.php`, `Roadmap.php`, `RoadmapWindowTest.php`.
   - Vérification : `make phpunit-filter RoadmapWindowTest` vert, tests de la roadmap inchangés et verts.
   - Commitable seule : oui.

2. [x] **Consommé face à l'estimé dans le cumul des projets**
   - Objectif : saisi, restant et dépassement par feuille, lot et projet, restant et dépassement séparés.
   - Fichiers : `LotSummary.php`, `ProjectSummary.php`, `ProjectRollup.php`, `ProjectRollupTest.php`.
   - Vérification : `make phpunit-filter ProjectRollupTest` vert ; page d'administration des projets inchangée.
   - Commitable seule : oui.

3. [x] **Construction de la fiche et de sa timeline**
   - Objectif : découpage des tronçons sorti dans `RoadmapRunCutter` (référence sans équipe : les personnes qui ont saisi), `RoadmapTeamLine::forLeaf()`, `reach()` extraite, `buildProject()`, `ProjectRoadmap`, `TimelineEntry`, `TimelineMonth`.
   - Fichiers : `RoadmapBuilder.php`, `RoadmapRunCutter.php`, `RoadmapTeamLine.php`, les trois modèles créés, `RoadmapBuilderTest.php`, `TimelineMonthTest.php`.
   - Vérification : `make phpunit-filter RoadmapBuilderTest` et `make phpunit-filter TimelineMonthTest` verts ; tests de `build()` inchangés et verts.
   - Commitable seule : oui.

4. [x] **Route, fiche et icône sur la roadmap**
   - Objectif : `RoadmapController::project()`, `Week::tryFromIso()`, partiels extraits de `index.html.twig` (`_frieze`, `_zoom`, `_legend`), `links` dans `_row`, `project.html.twig`, `_consumption`, `_consumption_row`, `_timeline`, lien-icône superposé à la colonne des titres.
   - Fichiers : `RoadmapController.php`, `LotController.php`, `Week.php`, templates de `templates/roadmap/`, `RoadmapProjectControllerTest.php`, `WeekTest.php`.
   - Vérification : `make phpunit-filter RoadmapProjectControllerTest` et `make phpunit-filter RoadmapControllerTest` verts ; fiche relue dans le navigateur sur un projet des fixtures.
   - Commitable seule : oui.

5. [x] **Zoom de la fiche et parcours E2E**
   - Objectif : valeur `remember` dans `roadmap_controller.js`, ouverture à ×1 centrée sur aujourd'hui borné ; scénarios E2E.
   - Fichiers : `roadmap_controller.js`, `project.html.twig`, `roadmap.spec.ts`.
   - Vérification : `make playwright-file tests/e2e/roadmap.spec.ts` vert (serveur lancé par `make serve`).
   - Commitable seule : oui.

6. [x] **QA finale**
   - Objectif : suite complète et qualité.
   - Fichiers : —
   - Vérification : `make lint`, `make phpunit`, `make playwright`.
   - Commitable seule : —

## Critères de sortie

- [x] `GET /roadmap/projets/{id}` répond 200 aux trois rôles, 404 pour un projet inconnu, et allume « Roadmap » dans le menu.
- [x] La ligne de projet de la fiche porte les mêmes début, fin, signaux et barres que la même ligne de `build()` placée sur la même fenêtre.
- [x] Sur 41 semaines, `RoadmapWindow::around()` et `Roadmap::months()` rendent exactement le même résultat qu'avant la story.
- [x] Le nombre de requêtes de la fiche ne dépend pas du nombre de feuilles du projet.
- [x] `make phpunit` et `make playwright` verts, sans nouvelle régression.
- [x] `make lint` propre (PHP-CS-Fixer, PHPStan level 10).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Le découpage sorti dans `RoadmapRunCutter`, l'extraction de `reach()`, ou les bornes stockées dans `RoadmapWindow`, changent le rendu de la roadmap | moyenne | Étapes 1 et 3 faites tests existants verts avant d'ajouter du neuf ; critère de sortie sur l'égalité `around()` / `months()` à 41 semaines. |
| Le lien-icône bascule le `<details>` du projet, ou Turbo garde le projet déplié en cache | faible | Lien superposé hors du `<details>` ; scénario E2E qui clique l'icône et vérifie que le projet n'est pas déplié au retour sur la roadmap. |
| Planifier toutes les feuilles à chaque affichage de fiche | faible | Même coût que `/roadmap`, déjà mesuré à la story 009. Les requêtes par jour et par personne sont limitées aux feuilles du projet. |
| Frise d'un projet long très large (2 ans ≈ 132rem à ×1, 8 fois plus à ×8) | faible | Barres positionnées en pourcentage, sans élément par jour. Le poids HTML dépend du nombre de tronçons, pas de la largeur. |
| Une feuille sans équipe coupée sur les jours des personnes qui ont saisi peut paraître interrompue sur un jour non travaillé de l'une d'elles | faible | Règle annotée au pitch (règle 15), test dédié dans `RoadmapBuilderTest`. |

## Questions ouvertes

- **Icône du lien vers la fiche** : `tabler:file-description` ou `tabler:arrow-up-right` ? À trancher à l'implémentation, selon le rendu à côté des badges de signal. → tranché : `tabler:file-description`.
- **Position de la ligne du projet dans le tableau** : en tête ou en pied ? Le plan retient le pied (ligne de total), à confirmer au rendu. → tranché : en pied, dans le `<tfoot>` du tableau.
