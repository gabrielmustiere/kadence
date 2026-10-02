# Report — Déclarer l'avancement d'une feuille pour recaler sa fin calculée sur le rythme réellement observé

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/012-f-avancement-feuilles/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 93 % — les sept étapes sont livrées selon l'approche prévue. Écarts structurants : une feuille déclarée à 100 % ne retrouve jamais de restant (règle 16 du pitch préférée à la règle 11) ; un avancement n'est en vigueur que sur une feuille estimée (BUG de la review) ; la validation du pas de 5 % vit dans le service et non dans le contrôleur.
- **Critères** : 20 / 20 cochés.
- **Review** : 0 bloquant ; 1 important et 4 mineurs, tous corrigés pendant la passe ; statut PRÊT À COMMITER.
- **Périmètre livré** : 15 fichiers créés (~1 110 lignes, tests compris), 32 fichiers modifiés (+868 / −110 lignes), hors documents de story et hors `assets/styles/app.css` (modifié avant la story).

Un lead, la direction ou le responsable d'une feuille estimée déclare son avancement (0 à 100 % par pas de 5 %) depuis la page de gestion du projet. Le restant extrapolé, ancré à la déclaration, alimente la fin calculée de la roadmap, de la fiche projet et de la fiche personne. La fiche projet montre l'avancement, le coût projeté face à l'estimation, l'avancement cumulé et l'historique des déclarations. Restent hors code le réalignement de la vision (H2), du backlog et de la règle 7 de la story 010.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Entity/LotProgress.php` | Une déclaration : feuille, auteur, jour, pourcentage, temps saisi et restant ancrés (`null` à 0 %) ; `PERCENTS` (0 à 100 par pas de 5) ; `redeclare()` pour le remplacement du jour. | Oui |
| `src/Repository/LotProgressRepository.php` | `findCurrentByLot()` (sous-requête corrélée sur le dernier jour), `findForProjectByLot(Project)` (auteurs joints, du plus récent au plus ancien), `findOneByLotAndDay()`, `moveToLot()`, `deleteForLots()`. | Écart volontaire (cf. §Écarts) |
| `src/Model/Schedule/LeafProgress.php` | L'avancement en vigueur : `fromDeclaration()` (`null` à 0 %), `anchoredRemaining()` (arrondi au quart supérieur), `remainingAfter()` (jamais de restant après 100 %), `isComplete()`, `pointsOf()` et `weightedPercent()` pour le cumul. | Écart volontaire (cf. §Écarts) |
| `src/Service/LotProgressManager.php` | `declare(Lot, int, User)` : feuille estimée exigée, pas de 5 %, refus de 100 % sans temps, ancrage, remplacement du jour, déclaration concurrente rattrapée en refus. | Écart volontaire (cf. §Écarts) |
| `src/Exception/LotProgressRefusedException.php` | `invalidPercent()`, `completeWithoutTime()`, `declaredMeanwhile()`. | Écart volontaire (cf. §Écarts) |
| `migrations/Version20261002124240.php` | Table `lot_progress`, unicité `uniq_lot_progress_lot_day`, clés étrangères `lot_id` et `author_id` (générée ; description renseignée). | Oui |
| `templates/project/_progress.html.twig` | Cellule « Avancement » : formulaire (select prérempli, « Enregistrer », « déclaré le … ») pour qui a `LOT_PROGRESS`, sinon « 40 % » « au 02/10 » ou « — ». | Oui |
| `templates/roadmap/_progress_history.html.twig` | Section « Avancements déclarés » : un bloc par feuille déclarée dans l'ordre du découpage (« Lot · Sous-lot »), déclarations avec jour, pourcentage et auteur ; message sans déclaration. | Oui |
| `tests/Support/CreatesProgress.php` | Pose une déclaration à un jour donné, ancrée sur le temps saisi et l'estimation de la feuille. | Oui |
| `tests/Unit/Model/Schedule/LeafProgressTest.php` | Dix cas : 15 j, 7 j sans temps, 3 j en dépassement, 100 %, arrondi, 12 j après 3 j saisis, temps retiré, rien ne revient après 100 %, 0 %, déclaration en vigueur. | Oui |
| `tests/Service/LotProgressManagerTest.php` | Huit cas : ancrage sur les temps en base, estimation sans temps, remplacement du même jour, autre jour conservé, 0 %, 100 % sans temps refusé, pas de 5 %, lot découpé. | Oui |
| `tests/Controller/LotProgressControllerTest.php` | Sept cas : déclaration par un lead depuis la page de gestion, direction, responsable prod (sa feuille / lecture et 403 sur une autre), lot découpé et feuille à estimer (403), jeton invalide, pas de 5 %, 100 % sans temps. | Oui |
| `tests/e2e/progress.spec.ts` | Un lead déclare 30 % ; la page de gestion, la fiche projet (restant, avancement, projeté, historique) et l'infobulle de la frise reflètent la déclaration. | Oui |
| `templates/roadmap/_progress.html.twig` | Lignes « Avancement » et « Projeté » d'une feuille, partagées par l'infobulle de la partie restante et le récapitulatif. | Non (ajout — cf. §Écarts) |
| `tests/Unit/Service/LotProgressManagerTest.php` | Déclaration concurrente du jour refusée avec message (violation d'unicité au `flush()`). | Non (ajout — cf. §Écarts) |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Model/Schedule/LeafPlan.php` | `?LeafProgress $progress`, conservé par `withPlanning()`. | Oui |
| `src/Model/Schedule/LeafSchedule.php` | `remainingQuarters` jamais négatif, `overrunQuarters`, `progress`, `estimateExhausted` → `exhausted` ; `isOverrun()`, `isEstimateReached()`, `isCompleted()`, `isProgressToRefresh()`, `end()`. | Oui |
| `src/Service/Scheduler.php` | `remainingOf()` : restant issu de `remainingAfter()` ou `estimé − consommé`, borné à 0 ; `overrunQuarters` ; épuisement à restant nul. | Oui |
| `src/Service/ScheduleLoader.php` | `findCurrentByLot()` chargé une fois ; avancement posé sur le plan des seules feuilles estimées. | Écart volontaire (cf. §Écarts) |
| `src/Enum/Type/RoadmapSignal.php` | `Completed` (« terminée à 100 % », gris) et `ProgressToRefresh` (« avancement à actualiser », warning). | Oui |
| `src/Model/Roadmap/RoadmapRow.php` | `overrunQuarters` en propriété (méthode retirée), `progress`, `projectedQuarters()`, `projectedGapQuarters()`. | Écart volontaire (cf. §Écarts) |
| `src/Service/RoadmapBuilder.php` | `leafRow()` transmet `overrunQuarters` et `progress`. | Oui |
| `src/Model/LotSummary.php` | `progressPoints`, `progress`, `projectedQuarters`, `progressPercent()`, `projectedGapQuarters()`. | Écart volontaire (cf. §Écarts) |
| `src/Model/ProjectSummary.php` | `progressPoints`, `progressPercent()`. | Oui |
| `src/Service/ProjectRollup.php` | Historique reçu par feuille ; `summarizeLeaf()` et `leafSummary()` : restant issu de l'avancement, coût projeté, points d'avancement ; feuille « à estimer » sans avancement. | Écart volontaire (cf. §Écarts) |
| `src/Security/Voter/LotVoter.php` | `PROGRESS` (`LOT_PROGRESS`) : feuille estimée, puis lead/direction ou responsable actif. | Oui |
| `src/Service/ProjectManager.php` | `addSubLot()` déplace les déclarations dans la transaction des temps ; `deleteLot()` (avec `detach()` extrait) les remonte ou les supprime en transaction ; `deleteProject()` les supprime en transaction. | Écart volontaire (cf. §Écarts) |
| `src/Controller/LotController.php` | Action `progress` (`POST /lots/{id}/avancement`, `app_lot_progress`) : `LOT_PROGRESS`, CSRF `lot-progress-{id}`, refus du manager en flash, retour à la page de gestion. | Écart volontaire (cf. §Écarts) |
| `src/Controller/ProjectController.php` | `show()` passe l'historique du projet à `ProjectRollup`. | Oui |
| `src/Controller/RoadmapController.php` | `project()` passe l'historique à `ProjectRollup` et au template (`declarations`). | Oui |
| `templates/project/show.html.twig` | Colonne « Avancement ». | Oui |
| `templates/project/_lot_row.html.twig` | Cellule « Avancement » : `_progress.html.twig` pour une feuille, « — » pour un lot découpé. | Oui |
| `templates/roadmap/_consumption.html.twig` | Colonnes « Avancement » et « Projeté ». | Oui |
| `templates/roadmap/_consumption_row.html.twig` | Avancement en vigueur et sa date, ou cumul et « partiel » ; projeté et écart via `projectedGapQuarters()`. | Oui |
| `templates/roadmap/project.html.twig` | Section « Avancements déclarés ». | Oui |
| `templates/roadmap/_tooltip.html.twig` | Partie restante d'une feuille avec avancement : restant puis `_progress.html.twig`. | Oui |
| `templates/roadmap/_recap.html.twig` | « inconnue, avancement à actualiser » ; dépassement et restant côte à côte ; `_progress.html.twig`. | Oui |
| `fixtures/DemoCompanyFixtures.php` | `declareProgress()` : Bulletins (trois déclarations, fin reculée), Documentation (à actualiser), première feuille finie en dépassement d'au moins un jour (90 %), première feuille finie à l'estimation exacte (100 %). | Oui |
| `tests/Unit/Service/SchedulerTest.php` | Six cas d'avancement ; cas de dépassement existant adapté (`remainingQuarters` 0 et `overrunQuarters` 2 au lieu de −2). | Écart volontaire (cf. §Écarts) |
| `tests/Unit/Service/ProjectRollupTest.php` | Restant issu de l'avancement, écart projeté, 0 %, feuille repassée à estimer, cumul à 40 % et partiel, temps au-delà de l'estimation compté à 100 %. | Oui |
| `tests/Unit/Service/ProjectManagerTest.php` | Troisième argument du constructeur ; transfert au premier sous-lot, aucun transfert ensuite, remontée, suppression d'un lot découpé et d'un projet. | Oui |
| `tests/Unit/Security/Voter/LotVoterTest.php` | Quatre cas `LOT_PROGRESS`. | Oui |
| `tests/Unit/Model/Roadmap/RoadmapRowTest.php` | Jeu de données en `overrunQuarters` ; `projectedQuarters()` et `projectedGapQuarters()`. | Écart volontaire (cf. §Écarts) |
| `tests/Service/ScheduleLoaderTest.php` | Dernière déclaration en vigueur, 0 % sans effet, feuille repassée à estimer sans avancement. | Oui |
| `tests/Service/RoadmapBuilderTest.php` | Propriété `overrunQuarters` ; dépassement avec fin, terminée à 100 %, à actualiser et fin du projet inconnue ; surcharge passive « à replanifier » ; signal levé par une nouvelle déclaration ; bascule au premier sous-lot par `ProjectManager` réel. | Écart volontaire (cf. §Écarts) |
| `tests/Controller/ProjectControllerTest.php` | Non modifié : la colonne « Avancement » est testée dans `LotProgressControllerTest`. | Écart volontaire (cf. §Écarts) |
| `tests/Controller/RoadmapProjectControllerTest.php` | Restant, avancement, projeté, cumuls (25 %, 67 %), historique, état vide, lecture seule. | Oui |
| `tests/Controller/RoadmapControllerTest.php` | Infobulle et récapitulatif d'une feuille avec avancement ; « terminée à 100 % » et « avancement à actualiser » vus par un membre de prod. | Oui |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| Le contrôleur refuse une valeur hors `PERCENTS` par une erreur 400. | `LotProgressManager::declare()` refuse la valeur (`LotProgressRefusedException::invalidPercent()`), rendue en flash. | La règle métier vit dans le service, qui la garantit quel que soit l'appelant. |
| `LotProgressRepository::deleteForProject()`. | Pas de méthode dédiée : `deleteProject()` passe les lots du projet à `deleteForLots()`. `deleteLot()` et `deleteProject()` écrivent en transaction. | Une seule méthode de suppression suffit ; la transaction évite de perdre des déclarations si la suppression du lot ou du projet échoue. |
| `deleteLot()` et `summarizeLeaf()` modifiés en place. | `ProjectManager::detach()` extrait de `deleteLot()` ; `ProjectRollup::leafSummary()` extrait de `summarizeLeaf()`. | Limite de complexité cognitive de PHPStan (9) dépassée. |
| Cumul pondéré et écart projeté calculés par `ProjectRollup` et les templates. | `LeafProgress::pointsOf()` et `weightedPercent()` ; `LotSummary::projectedGapQuarters()` et `RoadmapRow::projectedGapQuarters()`. | Garder la formule en un seul endroit ; finding [ARCHI] de la review « L'écart entre le coût projeté et l'estimation se calcule dans deux templates, avec un `* 4` en dur ». |
| `remainingAfter()` : le temps retiré depuis la déclaration rend du restant (règle 11 du pitch). | Une feuille déclarée à 100 % ne retrouve jamais de restant. | Contradiction entre les règles 11 et 16 du pitch, repérée à la vérification visuelle de la démo (« Cadrage » affichait 0,25 j de restant) ; la règle 16 l'emporte. |
| La dernière déclaration est en vigueur sur toute feuille. | En vigueur sur une feuille estimée seulement (`ScheduleLoader::planOf()`, `ProjectRollup::summarizeLeaf()`) ; les déclarations restent dans l'historique. | Finding [BUG] de la review « Une déclaration reste en vigueur sur une feuille repassée « à estimer » » (règle 1 du pitch). |
| Pas de traitement des déclarations simultanées. | Une violation d'unicité au `flush()` devient `LotProgressRefusedException::declaredMeanwhile()`. | Finding [ROBUSTESSE] de la review « Deux déclarations simultanées le même jour sur la même feuille finissent en erreur 500 ». |
| Les tests existants passent sans modification de leurs attentes (critère de sortie). | `SchedulerTest` (dépassement), `RoadmapRowTest` (jeu de données) et `RoadmapBuilderTest` (méthode devenue propriété) adaptés à `overrunQuarters`. | Le restant n'est plus négatif par conception ; les trois tests vérifient le même dépassement par sa nouvelle valeur explicite. |
| La colonne « Avancement » testée dans `ProjectControllerTest`. | Testée dans `LotProgressControllerTest`, avec l'action de déclaration. | Regrouper l'affichage selon le rôle et le geste qu'il permet. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `templates/roadmap/_progress.html.twig` | Les mêmes lignes « Avancement » et « Projeté » servent à l'infobulle de la partie restante et au récapitulatif d'une feuille. |
| `tests/Unit/Service/LotProgressManagerTest.php` | Couvre le refus d'une déclaration concurrente, impossible à provoquer en test fonctionnel. |
| Trois tests de `RoadmapBuilderTest` (surcharge passive, signal levé, bascule) | Finding [TEST] de la review « Trois critères d'acceptation ne sont pas couverts directement ». |
| Critère d'acceptation 4 du pitch reformulé | Finding [DOC] de la review : le tableau de la fiche projet affiche « 25 j (+5 j) » dans la colonne Projeté, l'estimation étant dans sa propre colonne. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Model/Schedule/LeafProgress.php` | unit | unit, 10 cas | Fait — couverture étendue (100 % après correction de temps) |
| `src/Service/Scheduler.php` | unit | unit, 6 cas ajoutés | Fait |
| `src/Service/ProjectRollup.php` | unit | unit, 5 cas ajoutés | Fait — couverture étendue (feuille repassée à estimer, temps au-delà compté à 100 %) |
| `src/Security/Voter/LotVoter.php` | unit | unit, 4 cas ajoutés | Fait |
| `src/Service/ProjectManager.php` | unit | unit, 5 cas ajoutés | Fait |
| `src/Service/LotProgressManager.php` | functional | functional, 8 cas + unit, 1 cas | Fait — couverture étendue (déclaration concurrente) |
| `src/Service/ScheduleLoader.php` | functional | functional, 2 cas ajoutés | Fait |
| `src/Service/RoadmapBuilder.php` | functional | functional, 4 cas ajoutés | Fait — couverture étendue (surcharge passive, signal levé, bascule) |
| `LotController::progress()` | functional | functional, 7 cas | Fait |
| `templates/project/*` | functional (`ProjectControllerTest`) | functional (`LotProgressControllerTest`) | Fait — écart volontaire de fichier |
| `templates/roadmap/project.html.twig` | functional | functional, 1 cas | Fait |
| `templates/roadmap/_tooltip.html.twig`, `_recap.html.twig` | functional | functional, 1 cas | Fait |
| Parcours complet | E2E | E2E, 1 scénario | Fait |
| Fiche personne | hors scope assumé | pas de test dédié | Conforme — fins et signaux hérités de `LeafSchedule` ; `PersonControllerTest` et `PersonRoadmapBuilderTest` verts |
| Refus et droits en E2E | hors scope assumé | pas écrit | Conforme — couverts en fonctionnel |
| Migration | hors scope assumé | `doctrine:schema:validate` | Conforme |

Résultats consignés à l'implémentation et à la review : 487 tests PHPUnit verts, `make lint` propre ; 45 tests E2E verts à l'implémentation, puis `progress.spec.ts` et `roadmap.spec.ts` verts après les corrections de review. La suite E2E complète n'a pas été rejouée après ces corrections : non mesuré.

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Sur la page de gestion d'un projet, un lead déclare l'avancement d'une feuille estimée en choisissant un pourcentage de 0 à 100 par pas de 5 %.
- [x] Un lot découpé, un projet et une feuille « à estimer » n'offrent aucun avancement à déclarer.
- [x] La direction déclare l'avancement de toute feuille ; un membre de prod responsable d'une feuille déclare celui de sa feuille, et ne peut pas déclarer celui d'une feuille dont il n'est pas responsable.
- [x] Une feuille de 20 j estimés, avec 10 j saisis et une personne à 5 j/semaine à 100 % dans son équipe, déclarée à 40 % : son restant passe de 10 j à 15 j, sa fin calculée recule de 5 jours ouvrés, et la fiche projet affiche un coût projeté de 25 j (+5 j) face aux 20 j estimés. _(Formulation précisée à la review.)_
- [x] Dans cet exemple, 3 j saisis après la déclaration ramènent le restant à 12 j.
- [x] Une feuille de 10 j estimés sans aucun temps saisi, déclarée à 30 %, a un restant de 7 j.
- [x] Déclarer 0 % ramène le restant à l'estimation moins le temps saisi.
- [x] Réviser l'estimation d'une feuille qui a un avancement ne déplace pas sa fin calculée.
- [x] Une feuille de 10 j estimés avec 12 j saisis, en « fin inconnue », déclarée à 80 % : elle retrouve une fin calculée sur un restant de 3 j, reste signalée « en dépassement de 2 j », et la fin de son lot et de son projet n'est plus inconnue de son fait.
- [x] Une feuille déclarée à 100 % n'a plus de partie future, se termine au dernier jour saisi et est signalée « terminée à 100 % » ; la saisie y reste possible.
- [x] Déclarer 100 % sur une feuille sans aucun temps saisi est refusé avec un message.
- [x] Sur la fiche projet, une feuille déclarée à 40 % avec 10 j saisis pour 20 j estimés affiche 15 j de restant, et le restant de son lot et de son projet en tient compte.
- [x] Quand le temps saisi depuis la déclaration épuise le restant ancré, la feuille est signalée « avancement à actualiser » et sa fin, comme celle de son lot et de son projet, est inconnue ; une nouvelle déclaration lève le signal.
- [x] Une déclaration qui repousse une fin au point de surcharger une personne est acceptée, et les feuilles concernées sont signalées « à replanifier » pour les leads et la direction.
- [x] La fiche projet affiche, pour chaque feuille, son avancement en vigueur avec sa date, son coût projeté et l'historique de ses déclarations (date, pourcentage, auteur), de la plus récente à la plus ancienne ; deux déclarations le même jour n'y laissent que la dernière.
- [x] La fiche projet reste en lecture seule : elle n'offre aucun moyen de déclarer un avancement.
- [x] Un lot dont un sous-lot de 20 j est déclaré à 50 % et un autre de 20 j, sans avancement, a 6 j saisis affiche un avancement cumulé de 40 %.
- [x] Sur la roadmap, l'infobulle de la partie restante d'une feuille qui a un avancement montre l'avancement, sa date et le coût projeté face à l'estimation.
- [x] Quand un lot qui a un avancement reçoit son premier sous-lot, ce sous-lot reprend l'avancement et la barre est inchangée.
- [x] La saisie quotidienne ne demande ni n'affiche aucun avancement.

Le critère 9 (fin du lot et du projet) découle de la règle de cumul de la story 006 : aucun test ne l'isole, la fin du projet de `RoadmapBuilderTest` restant inconnue du fait d'une autre feuille. Les critères 10 (saisie possible à 100 %) et 20 (saisie inchangée) tiennent à l'absence de modification de la saisie quotidienne.

## Dette technique identifiée

Issus de la review : aucun mineur non traité.

Au-delà de la review :

1. **Vision et backlog non réalignés** — `docs/vision.md` (hypothèse H2 sous tension : le repli « reste à faire déclaré » est posé en coexistence), `docs/product-backlog.md` (story cadrée hors backlog, sans ligne) et la règle 7 de la story 010 (restant issu de l'avancement sur la fiche projet) sont à reprendre par `/forge:sync`.
2. **Boutons d'action sur trois lignes** — `templates/project/_lot_row.html.twig` — Avec la colonne « Avancement », les trois boutons des lots de premier niveau de la page de gestion passent sur trois lignes. Resserrer la colonne des actions (icônes ou menu) quand la page sera retouchée.
3. **Fin d'un lot et d'un projet sans test isolé** — critère 9 — Le cas d'une feuille en dépassement qui, par son avancement, rend une fin à son projet n'a pas de test dédié.

## Leçons apprises

- **Un champ à double sens se paie au premier nouveau cas** : le restant négatif qui valait « dépassement » a dû être séparé avant tout le reste. Une donnée calculée qui code deux informations est à éclater dès qu'une troisième source de calcul apparaît.
- **La vérification visuelle sur la démo trouve ce que les tests à jeu de données propre ne trouvent pas** : la contradiction entre les règles 11 et 16 est apparue sur « Cadrage », dont les fixtures arrondissent la dernière saisie après coup.
- **Une requête `UPDATE` ou `DELETE` en DQL ne met pas à jour les entités déjà chargées** : un test qui enchaîne une bascule et une relecture dans le même `EntityManager` doit le vider, comme le ferait une nouvelle requête HTTP.
- **Lancer PHPUnit sans recharger les fixtures fait gonfler la base partagée** : le test de comptage de requêtes de la roadmap échoue alors par épuisement mémoire du profileur. Passer par `make phpunit` ou recharger les fixtures avant une série de lancements ciblés.
- **Un état cible d'une règle métier (« à estimer ») doit être relu à chaque nouvelle donnée rattachée à la feuille** : retirer une estimation laissait une déclaration en vigueur, cas qu'aucune règle du pitch ne nommait.
