# Plan technique — Déclarer l'avancement d'une feuille pour recaler sa fin calculée sur le rythme réellement observé

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/012-f-avancement-feuilles/`
> **Amont** : `pitch.md`

## Approche retenue

Une déclaration d'avancement est une ligne d'historique, `LotProgress`, rattachée à une feuille. Elle porte le pourcentage, le jour, l'auteur et deux valeurs figées au moment où elle est faite : le temps saisi sur la feuille et le restant ancré. Rien n'est stocké sur `Lot`. L'avancement en vigueur est la dernière déclaration, et une déclaration à 0 % le neutralise. `LotProgressManager::declare()` calcule l'ancrage (règles 9, 10 et 13 du pitch). Il remplace une déclaration du même jour et refuse 100 % tant qu'aucun temps n'est saisi, une valeur hors du pas de 5 % et une déclaration concurrente du jour (violation d'unicité au `flush()`). Il est appelé par une action POST de `LotController`, protégée par un nouvel attribut `LotVoter::PROGRESS`. Un formulaire HTML inline sur chaque feuille de la page de gestion du projet y poste.

La formule vit dans un modèle pur, `App\Model\Schedule\LeafProgress`. `anchoredRemaining()` calcule l'ancrage à la déclaration, et `remainingAfter(int $consumed)` le restant courant, c'est-à-dire le restant ancré diminué du temps saisi depuis. Le `Scheduler` et `ProjectRollup` s'en servent tous les deux, ce qui donne une seule formule pour la fin calculée, la roadmap, la fiche projet et la fiche personne. `ScheduleLoader` charge les avancements en vigueur en une requête et les pose sur chaque `LeafPlan`. Le `Scheduler` remplace `estimé − consommé` par `remainingAfter()` quand un avancement est en vigueur. Un avancement n'est en vigueur que sur une feuille estimée, et une feuille déclarée à 100 % ne retrouve jamais de restant. Le reste du calcul de la story 006 est inchangé.

Le changement le plus délicat est un découplage. Aujourd'hui, `LeafSchedule::remainingQuarters` négatif **signifie** dépassement (`isOverrun()`, `RoadmapRow::overrunQuarters()`, `_recap.html.twig`). Avec un avancement, une feuille peut avoir 3 j de restant **et** 2 j de dépassement. `LeafSchedule` et `RoadmapRow` reçoivent donc un `overrunQuarters` explicite (`max(0, consommé − estimé)`), et `remainingQuarters` devient le restant à faire, jamais négatif. La fin, les signaux « estimation atteinte », « en dépassement », « terminée à 100 % » et « avancement à actualiser » se déduisent de ces deux valeurs et de l'avancement en vigueur.

### Mécanismes mobilisés

- **Entité Doctrine + repository dédié** (`LotProgress`, `LotProgressRepository`) : l'historique est une donnée persistante propre. Toutes les lectures passent par des méthodes nommées : `findCurrentByLot()` pour la planification, `findForProjectByLot()` pour la fiche et la page de gestion, `findOneByLotAndDay()` pour le remplacement du jour. Le transfert et la suppression (`moveToLot()`, `deleteForLots()`, appelés en transaction) suivent le modèle de `TimeEntryRepository::moveToLot()`.
- **Modèle pur `LeafProgress`** à côté de `LeafPlan`, `LeafSchedule` et `DailyCapacity` : la règle métier reste hors de l'entité, comme l'impose `CLAUDE.md`. Elle se teste unitairement sur les exemples chiffrés du pitch.
- **`ScheduleLoader` → `LeafPlan` → `Scheduler` → `LeafSchedule`** (story 006) : la chaîne existante reçoit l'avancement, sans nouveau service de calcul. `LeafPlan::withPlanning()` conserve l'avancement. `PlanningFitsCapacityValidator` mesure donc la charge avant et après un geste de planification avec le bon restant, sans modification.
- **`RoadmapSignal::of(LeafSchedule)`** : les deux nouveaux signaux apparaissent d'eux-mêmes sur la roadmap, la fiche projet et la fiche personne, qui partagent cette fabrique (story 011).
- **Voter** (`LotVoter`, nouvel attribut `LOT_PROGRESS`) : il combine l'état (feuille estimée) et l'identité (lead/direction, ou responsable actif). C'est la même règle que `LOT_EDIT` restreinte aux feuilles estimées. Elle pilote l'affichage du formulaire et l'action.
- **POST avec `isCsrfTokenValid()` et `getPayload()`** : c'est la convention du projet pour un geste ponctuel sur un élément d'une liste (`LotController::delete()`, `HolidayController`, `TeamController`). Un formulaire Symfony nommé par ligne ne servirait à rien pour un seul champ borné.
- **Exception métier dédiée** (`LotProgressRefusedException`, sur le modèle de `TimeEntryRefusedException`) : elle porte les messages des refus (valeur hors du pas de 5 %, 100 % sans temps saisi, déclaration concurrente du jour), rendus en flash.
- **`ClockInterface`** : le jour de la déclaration et la comparaison du « même jour » suivent l'horloge du projet, ce qui les rend testables.
- **`ProjectRollup`** : il reçoit l'historique de la page, comme il reçoit déjà le temps saisi par lot. Il en déduit le restant, le coût projeté et l'avancement cumulé, sans requête supplémentaire.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Avancement en vigueur dénormalisé sur `Lot` (4 colonnes) en plus de l'historique | Deux sources de vérité pour la même donnée, à synchroniser à chaque déclaration et à chaque bascule. Une requête dédiée lit l'avancement en vigueur de toutes les feuilles en une fois. |
| Collection `Lot::$progresses` avec cascade ORM | `addSubLot()` et `deleteLot()` déplacent les déclarations par une requête `UPDATE`. La collection en mémoire du lot quitté resterait périmée, et une cascade `remove` supprimerait des déclarations déjà déplacées. Des appels explicites au repository, comme pour les temps saisis, évitent ce piège. |
| Recalcul du restant sur le consommé courant (pourcentage seul stocké) | Écarté au pitch : chaque jour saisi ferait reculer la fin tant que l'avancement n'est pas mis à jour. L'ancrage impose de figer le temps saisi et le restant au moment de la déclaration. |
| Laisser `ProjectRollup` sur `estimé − saisi` et n'appliquer l'avancement qu'à la fin calculée | La fiche projet afficherait 10 j de restant face à une fin calculée sur 15 j. Tranché au plan : le restant issu de l'avancement s'affiche partout (pitch annoté, règle 22). |
| Modale ou Live Component pour déclarer | Un clic de plus par feuille, ou un aperçu en direct qu'aucune autre page de gestion n'a. Le formulaire inline sans JS suffit à faire le tour d'un projet en revue. |
| Historique mêlé à la timeline de la fiche projet | La timeline de la story 010 est une liste de tronçons, et y mêler des déclarations brouillerait les deux. Une section dédiée est tranchée au plan. |

## Modèle de données

### Nouvelle structure `LotProgress`

`src/Entity/LotProgress.php` (table `lot_progress`) :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `lot` | ManyToOne (`Lot`), colonne `lot_id` | non | aucune cascade ORM, suppression et transfert explicites par `LotProgressRepository` |
| `author` | ManyToOne (`User`), colonne `author_id` | non | l'auteur reste, même désactivé |
| `declaredOn` | `date_immutable`, colonne `declared_on` | non | jour de la déclaration (`ClockInterface`), à minuit |
| `percent` | `smallint`, colonne `percent` | non | `int<0, 100>`, dans `LotProgress::PERCENTS` (0, 5 … 100) |
| `enteredQuarters` | `integer`, colonne `entered_quarters` | non | temps saisi sur la feuille au moment de la déclaration, en quarts |
| `remainingQuarters` | `integer`, colonne `remaining_quarters` | oui | restant ancré en quarts, arrondi au quart supérieur ; `null` à 0 % (pas d'avancement) |

- Unicité `uniq_lot_progress_lot_day` sur (`lot_id`, `declared_on`). Une seconde déclaration du jour met à jour la ligne existante (règle 7), et l'unicité sert aussi d'index pour les lectures par feuille.
- L'entité n'a que des accesseurs, et ses mutateurs ne servent qu'au remplacement du jour. `PERCENTS` vit sur l'entité, comme `LotMember::SHARES`.
- Pas de cloisonnement : l'application est mono-organisation.
- Migration générée par `symfony console make:migration`. Aucune reprise de données, puisqu'aucune feuille n'a d'avancement au lancement.

### Modification de modèles calculés (non persistés)

- `LeafPlan` : `?LeafProgress $progress = null`, conservé par `withPlanning()`.
- `LeafSchedule` : `remainingQuarters` (restant à faire, jamais négatif, `null` si « à estimer »), `overrunQuarters` (`max(0, consommé − estimé)`), `?LeafProgress $progress`. `estimateExhausted` devient `exhausted` (restant épuisé, quelle que soit sa source).
- `RoadmapRow` : `overrunQuarters` devient une propriété fournie par le builder (et non plus déduite du restant), plus `?LeafProgress $progress`, `projectedQuarters()` et `projectedGapQuarters()`.
- `LotSummary` et `ProjectSummary` : `progressPoints` (somme de l'estimation × l'avancement, au sens de la règle 23) et `progressPercent()`. `LotSummary` y ajoute, pour une feuille, `?LotProgress $progress` (dernière déclaration, 0 % compris, pour préremplir le select ; aucune sur une feuille « à estimer »), `?int $projectedQuarters` et `projectedGapQuarters()`. La pondération vit dans `LeafProgress::pointsOf()` et `weightedPercent()`.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Entity/LotProgress.php` | Une déclaration d'avancement d'une feuille : pourcentage, jour, auteur, temps saisi et restant ancrés ; constante `PERCENTS`. |
| `src/Repository/LotProgressRepository.php` | `findCurrentByLot()` (dernière déclaration de chaque feuille), `findForProjectByLot(Project)` (historique groupé par feuille, du plus récent au plus ancien, auteurs joints), `findOneByLotAndDay()`, `moveToLot()`, `deleteForLots()`. |
| `src/Model/Schedule/LeafProgress.php` | L'avancement en vigueur d'une feuille et sa formule : `fromDeclaration(LotProgress): ?self` (`null` à 0 %), `anchoredRemaining(percent, entered, estimate)`, `remainingAfter(consumed)` (jamais de restant après 100 %), `isComplete()`, `pointsOf()` et `weightedPercent()` pour le cumul. |
| `src/Service/LotProgressManager.php` | `declare(Lot, int $percent, User $author)` : contrôle de la feuille et de la valeur, refus de 100 % sans temps, ancrage, remplacement du jour, flush, déclaration concurrente rattrapée en refus. |
| `src/Exception/LotProgressRefusedException.php` | Refus métier avec message affichable : `invalidPercent()`, `completeWithoutTime()`, `declaredMeanwhile()`. |
| `migrations/Version<horodatage>.php` | Création de la table `lot_progress`, de son unicité et de ses clés étrangères (générée). |
| `templates/project/_progress.html.twig` | Cellule « Avancement » d'une feuille sur la page de gestion : formulaire (select + « Enregistrer ») pour qui a `LOT_PROGRESS`, lecture seule sinon (« 40 % · 02/10 » ou « — »). |
| `templates/roadmap/_progress.html.twig` | Lignes « Avancement » et « Projeté » d'une feuille, partagées par l'infobulle de la partie restante et le récapitulatif. |
| `templates/roadmap/_progress_history.html.twig` | Section « Avancements déclarés » de la fiche projet : un bloc par feuille déclarée (lot · sous-lot), déclarations avec date, pourcentage et auteur. |
| `tests/Support/CreatesProgress.php` | Trait de test qui pose une déclaration à un jour donné, sans passer par l'horloge. |
| `tests/Unit/Model/Schedule/LeafProgressTest.php` | Ancrage et restant courant sur les exemples du pitch : 15 j, puis 12 j, 7 j sans temps, 3 j en dépassement, 0 à 100 %, arrondi au quart supérieur, épuisement, rien ne revient après 100 %. |
| `tests/Unit/Service/LotProgressManagerTest.php` | Déclaration concurrente du jour refusée avec message (violation d'unicité au `flush()`). |
| `tests/Service/LotProgressManagerTest.php` | Ancrage sur les temps réels, remplacement du même jour, 0 % sans restant, refus de 100 % sans temps, refus d'un lot découpé ou d'une feuille à estimer. |
| `tests/Controller/LotProgressControllerTest.php` | Droits (lead, direction, responsable prod, prod non responsable, lot découpé, feuille à estimer), colonne « Avancement » selon le rôle et la responsabilité, CSRF, valeur hors pas, flash de succès et de refus, redirection. |
| `tests/e2e/progress.spec.ts` | Un lead déclare 40 % sur la page de gestion ; la fiche projet montre le restant, le projeté et l'historique ; l'infobulle de la roadmap montre l'avancement. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Model/Schedule/LeafPlan.php` | Ajouter `?LeafProgress $progress`, conservé par `withPlanning()`. |
| `src/Model/Schedule/LeafSchedule.php` | `remainingQuarters` jamais négatif, ajout de `overrunQuarters` et `progress`, `estimateExhausted` → `exhausted` ; `isOverrun()` sur `overrunQuarters` ; `isEstimateReached()` sans avancement ; nouveaux `isCompleted()` et `isProgressToRefresh()` ; `end()` : dernier jour saisi si estimation atteinte ou terminée à 100 %, sinon inconnue une fois épuisé. |
| `src/Service/Scheduler.php` | Restant issu de `LeafProgress::remainingAfter()` quand un avancement est en vigueur, sinon `estimé − consommé`, borné à 0 ; calcul de `overrunQuarters` ; épuisement à restant nul. |
| `src/Service/ScheduleLoader.php` | Charger `findCurrentByLot()` et poser `LeafProgress::fromDeclaration()` sur le `LeafPlan` de chaque feuille estimée. |
| `src/Enum/Type/RoadmapSignal.php` | Cas `Completed` (« terminée à 100 % », gris) et `ProgressToRefresh` (« avancement à actualiser », warning) dans `of()`, `label()` et `variant()`. |
| `src/Model/Roadmap/RoadmapRow.php` | `overrunQuarters` en propriété, ajout de `progress`, de `projectedQuarters()` (saisi + restant, si un avancement est en vigueur) et de `projectedGapQuarters()` ; `overrunPercent()` sur la propriété. |
| `src/Service/RoadmapBuilder.php` | `leafRow()` transmet `overrunQuarters` et `progress` du `LeafSchedule`. |
| `src/Model/LotSummary.php` | Ajouter `progress`, `progressPoints`, `projectedQuarters`, `progressPercent()` et `projectedGapQuarters()`. |
| `src/Model/ProjectSummary.php` | Ajouter `progressPoints` et `progressPercent()`. |
| `src/Service/ProjectRollup.php` | Recevoir l'historique par feuille ; `summarizeLeaf()` et `leafSummary()` : restant d'une feuille issu de l'avancement en vigueur ; coût projeté ; points d'avancement (déclaré, ou saisi / estimé plafonné à 100 %, feuille à estimer exclue et sans avancement) sommés par lot et par projet. |
| `src/Security/Voter/LotVoter.php` | Attribut `PROGRESS` (`LOT_PROGRESS`) : feuille estimée, puis lead/direction ou responsable actif. |
| `src/Service/ProjectManager.php` | `addSubLot()` transfère les déclarations au sous-lot dans la transaction des temps ; `deleteLot()` (avec `detach()` extrait) les remonte sur le lot redevenu feuille, ou les supprime, en transaction ; `deleteProject()` les supprime par `deleteForLots()` avant de supprimer le projet, en transaction. |
| `src/Controller/LotController.php` | Action `progress` (`POST /lots/{id}/avancement`, `app_lot_progress`) : `LOT_PROGRESS`, CSRF `lot-progress-{id}`, appel du manager (qui refuse une valeur hors `PERCENTS`), refus en flash, retour à la page de gestion du projet. |
| `src/Controller/ProjectController.php` | `show()` passe l'historique du projet à `ProjectRollup`. |
| `src/Controller/RoadmapController.php` | `project()` passe l'historique à `ProjectRollup` et au template. |
| `templates/project/show.html.twig` | Colonne « Avancement » dans l'en-tête du tableau des lots. |
| `templates/project/_lot_row.html.twig` | Cellule « Avancement » : `_progress.html.twig` pour une feuille, « — » pour un lot découpé. |
| `templates/roadmap/_consumption.html.twig` | Colonnes « Avancement » et « Projeté ». |
| `templates/roadmap/_consumption_row.html.twig` | Avancement en vigueur avec sa date (feuille) ou cumul marqué « partiel » (lot découpé, projet) ; projeté avec écart (« 25 j (+5 j) », via `projectedGapQuarters()`) pour une feuille avec avancement. |
| `templates/roadmap/project.html.twig` | Section « Avancements déclarés » (`_progress_history.html.twig`) sous le tableau du consommé face à l'estimé. |
| `templates/roadmap/_tooltip.html.twig` | Partie restante : avancement, date de déclaration et projeté face à l'estimé (`_progress.html.twig`). |
| `templates/roadmap/_recap.html.twig` | « inconnue, avancement à actualiser » ; dépassement **et** restant quand les deux coexistent ; avancement en vigueur (`_progress.html.twig`). |
| `fixtures/DemoCompanyFixtures.php` | Déclarations de démonstration : extrapolation qui recule une fin, dépassement qui retrouve une fin, terminée à 100 %, avancement à actualiser, historique de plusieurs déclarations. |
| `tests/Unit/Service/SchedulerTest.php` | Cas d'avancement : fin reculée de 5 jours ouvrés, restant décrémenté, dépassement avec fin, terminée à 100 %, à actualiser, estimation révisée sans effet ; cas de dépassement existant lu sur `overrunQuarters`. |
| `tests/Unit/Service/ProjectRollupTest.php` | Restant issu de l'avancement, cumul à 40 %, cumul partiel, projeté et écart, 0 %, feuille repassée à estimer, temps au-delà de l'estimation compté à 100 %. |
| `tests/Unit/Service/ProjectManagerTest.php` | Transfert et suppression des déclarations aux bascules et suppressions. |
| `tests/Unit/Security/Voter/LotVoterTest.php` | Règle `LOT_PROGRESS`. |
| `tests/Unit/Model/Roadmap/RoadmapRowTest.php` | `overrunPercent()`, `projectedQuarters()` et `projectedGapQuarters()` sur les nouvelles propriétés. |
| `tests/Service/ScheduleLoaderTest.php` | Avancement en vigueur posé sur le plan ; déclaration à 0 % sans effet ; feuille repassée à estimer sans avancement. |
| `tests/Service/RoadmapBuilderTest.php` | Signaux et fin d'une feuille avec avancement, propagation de la fin inconnue au lot et au projet, surcharge passive « à replanifier », signal levé par une nouvelle déclaration, bascule au premier sous-lot. |
| `tests/Controller/RoadmapProjectControllerTest.php` | Colonnes Avancement et Projeté, restant issu de l'avancement, cumul, section d'historique, aucun formulaire. |
| `tests/Controller/RoadmapControllerTest.php` | Infobulle de la partie restante, signaux visibles de tous. |

## Hors scope

- **Fiche personne** : aucune modification attendue de `PersonRoadmapBuilder` ni de ses templates. Ils lisent `LeafSchedule::end()` et `RoadmapSignal::of()`, et reçoivent donc d'eux-mêmes les nouvelles fins et les nouveaux signaux. Seule vérification prévue : les tests de la fiche personne restent verts.
- **`PlanningFitsCapacityValidator`** : pas de modification, puisque `withPlanning()` conserve l'avancement.
- **Avancement cumulé sur la page de gestion et sur la liste des projets** : seule la fiche projet l'affiche. `ProjectController::index()` appelle `summarize()` sans historique.
- **Optimisation du chargement de la planification** : `findCurrentByLot()` ajoute une requête à `ScheduleLoader::load()`. Pas de cache.
- **Refonte de `RoadmapBuilder`** : ses lignes ne reçoivent que deux champs de plus. Il est à la limite de complexité cognitive, et rien d'autre n'y est ajouté.

## Impacts transverses

- **Cloisonnement des données** : sans objet, l'application est mono-organisation. La déclaration porte sur une feuille, et l'auteur est affiché comme un responsable (principe 2).
- **Déclinaisons / thèmes** : non. Le formulaire et la section reprennent les composants « Paper » (`twig:Table`, `twig:Button`, `twig:Select` ou un `select` stylé comme dans les formulaires existants, `twig:Badge`).
- **Traduction / i18n** : libellés en français en dur, comme dans le reste des templates (interface en français uniquement).
- **API / exposition externe** : non.
- **Droits d'accès** : nouvel attribut `LotVoter::PROGRESS`. L'action vérifie l'accès avant toute lecture de la requête. La fiche projet et la roadmap restent ouvertes à toute personne connectée, en lecture seule.
- **Emails / notifications** : non.
- **Migration de données** : création de la table `lot_progress`, sans reprise.
- **Comportement par défaut** : une feuille sans avancement, ou à 0 %, se calcule exactement comme avant. Le restant et le dépassement de la fiche projet sont inchangés pour elle.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/Schedule/LeafProgress.php` | unit | Ancrage avec temps (40 % de 10 j → 15 j) et sans temps (30 % de 10 j → 7 j) ; dépassement (80 % de 12 j → 3 j) ; 100 % → 0 ; arrondi au quart supérieur ; `remainingAfter()` après 3 j → 12 j, après épuisement → ≤ 0, jamais de restant rendu après 100 % ; `fromDeclaration()` → `null` à 0 %. |
| `src/Service/Scheduler.php` | unit | Une personne à 5 j/semaine : fin reculée de 5 jours ouvrés à 40 % ; restant décrémenté par le saisi postérieur ; dépassement avec avancement → fin calculée et `isOverrun()` ; 100 % → fin au dernier jour saisi et `isCompleted()` ; épuisé → fin inconnue et `isProgressToRefresh()` ; estimation révisée sans effet ; sans avancement, tous les cas de la story 006 inchangés. |
| `src/Service/ProjectRollup.php` | unit | Restant d'une feuille issu de l'avancement ; dépassement inchangé ; cumul de 20 j à 50 % et 20 j avec 6 j saisis → 40 % ; feuille à estimer exclue et cumul partiel ; projeté 25 j pour 20 j. |
| `src/Security/Voter/LotVoter.php` | unit | `LOT_PROGRESS` : lead et direction sur toute feuille estimée ; responsable actif sur la sienne ; refus pour un responsable désactivé, un non-responsable, un lot découpé, une feuille à estimer. |
| `src/Service/ProjectManager.php` | unit | `addSubLot()` déplace les déclarations ; `deleteLot()` les remonte au lot redevenu feuille ou les supprime ; `deleteProject()` les supprime. |
| `src/Service/LotProgressManager.php` | functional | Ancrage calculé sur les temps en base ; remplacement du même jour (une seule ligne, valeurs mises à jour) ; 0 % → restant `null` ; refus de 100 % sans temps (exception, rien d'écrit) ; refus d'une valeur hors pas. |
| `src/Service/LotProgressManager.php` | unit | Déclaration concurrente du jour (violation d'unicité au `flush()`) refusée avec message. |
| `src/Service/ScheduleLoader.php` | functional | Le plan d'une feuille porte sa dernière déclaration ; une déclaration à 0 % ne pose pas d'avancement. |
| `src/Service/RoadmapBuilder.php` | functional | Signaux « terminée à 100 % » et « avancement à actualiser » ; fin inconnue propagée au lot et au projet ; dépassement avec fin ; surcharge passive « à replanifier » causée par une déclaration ; signal levé par une nouvelle déclaration ; barre inchangée à la bascule au premier sous-lot. |
| `LotController::progress()` | functional | Parcours lead → 302 et flash ; responsable prod sur sa feuille → succès ; non-responsable → 403 ; lot découpé et feuille à estimer → 403 ; CSRF invalide → refus ; valeur 37 → flash de refus, rien d'écrit ; 100 % sans temps → flash de refus, rien d'écrit. |
| `templates/project/*` | functional | Dans `LotProgressControllerTest` : formulaire présent selon `LOT_PROGRESS`, select prérempli par l'avancement en vigueur, lecture seule sinon. |
| `templates/roadmap/project.html.twig` | functional | Colonnes Avancement et Projeté, restant issu de l'avancement, cumul « partiel », section d'historique (dernière déclaration du jour seule), aucun formulaire. |
| `templates/roadmap/_tooltip.html.twig`, `_recap.html.twig` | functional | Avancement, date, projeté ; « inconnue, avancement à actualiser » ; dépassement et restant côte à côte. |
| Parcours complet | E2E | Déclaration à 40 % depuis la page de gestion, lecture sur la fiche projet et dans l'infobulle de la roadmap. |

**Hors scope tests** :

- Pas de test fonctionnel dédié à la fiche personne : elle hérite des fins et des signaux par `LeafSchedule`. Ses tests existants doivent rester verts.
- Pas de test E2E des refus et des droits : ils sont couverts en fonctionnel. L'E2E ne sert qu'à vérifier le parcours de bout en bout.
- Pas de test de la migration au-delà de `doctrine:schema:validate` : c'est une création de table sans reprise.

## Ordre d'exécution

1. [x] **Socle : déclaration et formule**
   - Objectif : la table `lot_progress` existe et la formule du pitch est prouvée.
   - Fichiers : `LotProgress`, `LotProgressRepository`, migration générée, `LeafProgress`, `LeafProgressTest`, `CreatesProgress`.
   - Vérification : `make migration` puis migration appliquée, `symfony console doctrine:schema:validate`, `make phpunit-filter LeafProgressTest`.
   - Commitable seule : oui.

2. [x] **Écriture : manager, droits et bascules**
   - Objectif : une déclaration s'enregistre avec son ancrage, le même jour se remplace, 100 % sans temps est refusé, et les déclarations suivent la feuille aux bascules et suppressions.
   - Fichiers : `LotProgressManager`, `LotProgressRefusedException`, `LotVoter`, `ProjectManager`, `LotProgressManagerTest`, `LotVoterTest`, `ProjectManagerTest`.
   - Vérification : `make phpunit-filter LotProgressManagerTest`, `LotVoterTest`, `ProjectManagerTest`.
   - Commitable seule : oui.

3. [x] **Calcul : restant, dépassement découplé et signaux**
   - Objectif : la fin calculée suit l'avancement, et le dépassement ne dépend plus du signe du restant.
   - Fichiers : `LeafPlan`, `LeafSchedule`, `Scheduler`, `ScheduleLoader`, `RoadmapSignal`, `RoadmapRow`, `RoadmapBuilder`, `SchedulerTest`, `ScheduleLoaderTest`, `RoadmapBuilderTest`, `RoadmapRowTest`.
   - Vérification : `make phpunit` complet. Les tests de dépassement, de roadmap et de fiche personne existants restent verts sans changer leurs attentes, et `make lint` est propre (renommage `exhausted` suivi par PHPStan).
   - Commitable seule : oui.

4. [x] **Cumuls : restant, projeté et avancement cumulé**
   - Objectif : `ProjectRollup` donne le restant issu de l'avancement, le coût projeté et l'avancement cumulé pondéré.
   - Fichiers : `LotSummary`, `ProjectSummary`, `ProjectRollup`, `ProjectRollupTest`, `ProjectController`, `RoadmapController`.
   - Vérification : `make phpunit-filter ProjectRollupTest`, puis `RoadmapProjectControllerTest` vert.
   - Commitable seule : oui.

5. [x] **Page de gestion : déclarer**
   - Objectif : la colonne « Avancement » et son formulaire, et l'action `app_lot_progress`.
   - Fichiers : `LotController`, `templates/project/show.html.twig`, `_lot_row.html.twig`, `_progress.html.twig`, `LotProgressControllerTest`, `ProjectControllerTest`.
   - Vérification : `make phpunit-filter LotProgressControllerTest`, `ProjectControllerTest`, et une vérification visuelle sur https://kadence.wip.
   - Commitable seule : oui.

6. [x] **Fiche projet et roadmap : lire**
   - Objectif : les colonnes Avancement et Projeté, la section « Avancements déclarés », l'infobulle de la partie restante et le récapitulatif.
   - Fichiers : `_consumption.html.twig`, `_consumption_row.html.twig`, `project.html.twig`, `_progress_history.html.twig`, `_tooltip.html.twig`, `_recap.html.twig`, `RoadmapProjectControllerTest`, `RoadmapControllerTest`.
   - Vérification : `make phpunit-filter RoadmapProjectControllerTest`, `RoadmapControllerTest`, et une vérification visuelle.
   - Commitable seule : oui.

7. [x] **Démo, E2E et QA finale**
   - Objectif : les données de démonstration couvrent les cas du pitch, et le parcours est vérifié de bout en bout.
   - Fichiers : `DemoCompanyFixtures`, `tests/e2e/progress.spec.ts`.
   - Vérification : `make db-reset`, `make lint`, `make phpunit`, `make serve` puis `make playwright`.
   - Commitable seule : oui.

## Critères de sortie

- [x] La table `lot_progress` est créée par une migration générée, et `doctrine:schema:validate` est vert.
- [x] Une feuille sans avancement ou à 0 % a exactement la même fin, les mêmes signaux, le même restant et le même dépassement qu'avant. Les tests existants de la roadmap, de la fiche projet et de la fiche personne passent sans modification de leurs attentes. _(Trois tests unitaires ou fonctionnels lisent désormais le dépassement sur `overrunQuarters` au lieu d'un restant négatif : `SchedulerTest`, `RoadmapRowTest`, `RoadmapBuilderTest`.)_
- [x] Le restant d'une feuille avec avancement vient d'une seule formule (`LeafProgress`), utilisée par le `Scheduler` et par `ProjectRollup`.
- [x] Aucun template ni modèle ne déduit plus le dépassement d'un restant négatif.
- [x] `ScheduleLoader::load()` reste à nombre de requêtes constant (une requête de plus pour les avancements).
- [x] L'action de déclaration est refusée (403) à qui n'a pas `LOT_PROGRESS`, avant toute écriture.
- [x] `make lint` propre (PHP-CS-Fixer et PHPStan level 10).
- [x] `make phpunit` et `make playwright` verts, sans nouvelle régression. _(Suite E2E complète verte à l'implémentation ; après la review, `progress.spec.ts` et `roadmap.spec.ts` rejoués.)_

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Un usage implicite de « restant négatif = dépassement » survit au découplage (template, modèle de la fiche personne) | moyenne | Recherche de `remainingQuarters` sur `src/` et `templates/` à l'étape 3. `overrunQuarters()` devient une propriété (même nom côté Twig). Les tests de dépassement existants servent de filet sans changer leurs attentes. |
| Fin inconnue plus fréquente (« avancement à actualiser ») qui se propage aux lots, aux projets et au « libre à partir du … » | moyenne | C'est voulu par la règle 17 du pitch. Les données de démonstration montrent le cas, et `RoadmapBuilderTest` couvre la propagation. |
| Déclarations orphelines si un chemin de suppression de lot ou de projet les oublie | faible | Tous les chemins passent par `ProjectManager` (`deleteLot()`, `deleteProject()`, `addSubLot()`), couverts par `ProjectManagerTest`. La clé étrangère non nulle fait échouer une suppression oubliée plutôt que de laisser un orphelin silencieux. |
| Les données de démonstration modifient des fins attendues par des tests qui s'appuient sur elles (`ProjectControllerTest`, `PersonControllerTest`, `PersonRoadmapBuilderTest`) | moyenne | Poser les déclarations de démonstration sur des feuilles que ces tests n'utilisent pas, et lancer `make phpunit` complet à l'étape 7. |
| Arrondi : restant extrapolé non entier en quarts | faible | Arrondi au quart supérieur dans `anchoredRemaining()` (règle 13), testé unitairement. |
| Jour de la déclaration différent du jour de saisie à cause du fuseau | faible | `ClockInterface` et `setTime(0, 0)`, comme `ScheduleLoader`. Le fuseau est Europe/Paris, comme le reste de l'application. |

## Questions ouvertes

- **Valeur préremplie du select pour une feuille sans déclaration** : (a) 0 %, qui revient à « sans avancement » ; (b) une option vide « — » qu'on ne peut pas soumettre. Penchant : (a), plus simple, puisque déclarer 0 % ne change rien au calcul. → tranché : (a).
- **Avancement cumulé quand toutes les feuilles d'un lot sont à estimer** : `progressPercent()` vaut `null` (estimation nulle). Afficher « — ». À confirmer à l'implémentation. → tranché : « — ».
