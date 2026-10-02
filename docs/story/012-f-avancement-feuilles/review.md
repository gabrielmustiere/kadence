# Review — Déclarer l'avancement d'une feuille pour recaler sa fin calculée sur le rythme réellement observé

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/012-f-avancement-feuilles/`
> **Amont** : `plan.md` + `pitch.md`
> **Diff examiné** : working tree, 32 fichiers modifiés + 15 nouveaux (hors `assets/styles/app.css`, modifié avant la story), ~760 lignes ajoutées

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 1
- **Mineurs restants** : 0 / 4
- **Statut** : **PRÊT À COMMITER**

Tous les findings ont été corrigés pendant la passe (487 tests PHPUnit, `progress.spec.ts` et `roadmap.spec.ts` verts, lint propre). Prochaine étape : `/forge:report`, puis `/forge:sync` et `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

- [x] **[BUG] Une déclaration reste en vigueur sur une feuille repassée « à estimer »** — `src/Service/ScheduleLoader.php:139`, `src/Service/ProjectRollup.php:60` — L'estimation d'une feuille sans temps saisi peut être retirée après une déclaration (`EstimateCoversConsumedValidator` ne la protège que s'il y a du temps). La dernière déclaration reste alors portée par `LeafPlan`, `LeafSchedule`, `RoadmapRow` et `LotSummary`. Le récapitulatif de la roadmap affiche « 0 j pour  j estimés » (`roadmap/_progress.html.twig`), et la fiche projet comme la page de gestion montrent « 30 % » sur une feuille « à estimer », contre la règle 1 du pitch. Corrigé : `ScheduleLoader::planOf()` et `ProjectRollup::summarizeLeaf()` ne mettent un avancement en vigueur que sur une feuille estimée, les déclarations restant dans l'historique ; tests `ScheduleLoaderTest::testALeafBackToEstimateHasNoProgressInForce()` et `ProjectRollupTest::testALeafBackToEstimateKeepsItsDeclarationsButHasNoProgress()`.

## Mineurs

- [x] **[ARCHI] L'écart entre le coût projeté et l'estimation se calcule dans deux templates, avec un `* 4` en dur** — `templates/roadmap/_consumption_row.html.twig:53`, `templates/roadmap/_progress.html.twig:5` — Corrigé : `LotSummary::projectedGapQuarters()` et `RoadmapRow::projectedGapQuarters()`, avec `Quarters::PER_DAY`, appelées par les deux templates et couvertes par `ProjectRollupTest` et `RoadmapRowTest`.
- [x] **[ROBUSTESSE] Deux déclarations simultanées le même jour sur la même feuille finissent en erreur 500** — `src/Service/LotProgressManager.php:51` — La lecture puis l'insertion ne sont pas atomiques, et l'unicité (`lot_id`, `declared_on`) lève une exception non rattrapée. Corrigé : la violation d'unicité au `flush()` devient `LotProgressRefusedException::declaredMeanwhile()` (« Un avancement vient d'être déclaré sur « … » : rechargez la page avant de le modifier. »), rendu en flash ; test `tests/Unit/Service/LotProgressManagerTest.php`.
- [x] **[TEST] Trois critères d'acceptation ne sont pas couverts directement** — `tests/Service/RoadmapBuilderTest.php` — Ajouter : une déclaration qui repousse une fin jusqu'à surcharger une personne signale « à replanifier » (critère 12) ; une nouvelle déclaration lève « avancement à actualiser » (critère 11) ; la barre est inchangée quand le premier sous-lot reprend l'avancement (critère 17, aujourd'hui couvert seulement par l'appel à `moveToLot()` dans `ProjectManagerTest`). Corrigé : `RoadmapBuilderTest::testAProgressThatPushesAnEndIntoAnotherLeafOfThePersonIsToReplan()`, `testANewDeclarationLiftsTheProgressToRefresh()` et `testFirstSubLotTakesOverTheProgressAndTheBarOfItsLot()` (ce dernier passe par `ProjectManager::addSubLot()` réel).
- [x] **[DOC] Le critère d'acceptation 4 cite un libellé que la fiche projet n'affiche pas tel quel** — `docs/story/012-f-avancement-feuilles/pitch.md` — Le pitch attend « projeté 25 j pour 20 j estimés (+5 j) » sur la fiche projet. Le tableau affiche « 25 j (+5 j) » dans la colonne Projeté, l'estimation étant dans sa propre colonne ; seule l'infobulle de la roadmap porte la phrase complète. Corrigé : critère reformulé dans le pitch (« la fiche projet affiche un coût projeté de 25 j (+5 j) face aux 20 j estimés »), annoté « Formulation précisée à la review ».

## Points positifs

- **Formule unique** : `LeafProgress::anchoredRemaining()` et `remainingAfter()` sont les seuls endroits qui calculent le restant d'une feuille avec avancement. `Scheduler` et `ProjectRollup` les appellent, et le test unitaire reprend tous les exemples chiffrés du pitch.
- **Découplage du dépassement** : `overrunQuarters` est explicite dans `LeafSchedule` et `RoadmapRow`. Plus aucun template ni modèle ne déduit le dépassement d'un restant négatif, et les tests existants de dépassement passent avec la même intention.
- **Droits au bon endroit** : `LotVoter::PROGRESS` combine l'état (feuille estimée) et l'identité. Le contrôleur le vérifie avant de lire la requête et avant le CSRF, et le template s'en sert pour afficher ou non le formulaire.
- **Transfert des déclarations sans piège ORM** : `moveToLot()` et `deleteForLots()` sont appelés explicitement par `ProjectManager`, dans une transaction. Aucune cascade ne risque de supprimer une déclaration déjà déplacée.
- **Nombre de requêtes constant** : une seule requête de plus dans `ScheduleLoader` (`findCurrentByLot()`) et sur les deux pages de projet (`findForProjectByLot()`, auteurs joints). Les tests de comptage de requêtes existants passent.
- **Vérification visuelle utile** : elle a révélé qu'une correction de temps faisait réapparaître du restant sur une feuille terminée à 100 %. La règle 16 du pitch l'emporte désormais sur la règle 11, et un test le prouve.

## Hors review (à vérifier en environnement réel)

- Les données de dev ont été réinitialisées par les rechargements de fixtures : la démo contient désormais les avancements de Bulletins, Documentation, Saisie des variables et Cadrage.
- `docs/vision.md` (hypothèse H2), `docs/product-backlog.md` (ligne absente) et la règle 7 de la story 010 restent à réaligner par `/forge:sync`.
