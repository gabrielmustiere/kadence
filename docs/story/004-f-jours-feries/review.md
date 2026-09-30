# Review — Verrouiller la saisie des jours fériés selon le calendrier français ou belge de chacun

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/004-f-jours-feries/`
> **Amont** : `plan.md` · `pitch.md`
> **Diff examiné** : working tree, 24 fichiers modifiés (+304 / −34) et 20 fichiers de code nouveaux (~1 330 lignes), hors documents de story

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 0
- **Mineurs restants** : 0 / 4
- **Statut** : **PRÊT À COMMITER**

Le diff couvre les 15 critères d'acceptation du pitch et suit le plan. Les écarts sont justifiés et tracés dans `metadata.json`. Les quatre mineurs sont corrigés pendant la passe. Contrôles repassés après correction : PHP-CS-Fixer, PHPStan niveau 10, `lint:twig` et `make phpunit` (199 tests) sont verts, ainsi que `holidays.spec.ts` (6 E2E). Prochaine étape : `/forge:report`, puis `/forge:sync` et `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

_(aucun)_

## Mineurs

- [x] **[ROBUSTESSE] Une année hors 1000–9999 fait planter la page « Jours fériés »** — `src/Controller/HolidayController.php:31` — La contrainte de route `\d{4}` accepte `0000` : `easter_days(0)` lève alors une `ValueError`, d'où une erreur 500. Le lien « année précédente » depuis `/jours-feries/1000` et le lien « année suivante » depuis `/jours-feries/9999` produisent aussi une erreur 500, car 999 et 10000 ne respectent pas la contrainte. L'URL n'est accessible qu'à la direction et ces années sont irréalistes. Corrigé : contrainte `[1-9]\d{3}` (`/jours-feries/0000` renvoie 404), liens « année précédente » et « année suivante » masqués aux bornes 1000 et 9999, test `testYearsOutsideFourDigitsAreNotFound` ajouté.
- [x] **[STYLE] `TimesheetDay::isHoliday()` n'est jamais appelée** — `src/Model/Timesheet/TimesheetDay.php:24` — Le gabarit teste la propriété `column.holiday`, et aucun code ni aucun test n'appelle la méthode. Corrigé : méthode retirée.
- [x] **[TEST] Un test vérifie le helper de test, pas l'application** — `tests/Service/HolidayManagerTest.php:110` — `testAnyRoleCarriesTheFrenchCalendarByDefault` crée l'utilisateur avec `createUser()`, qui fixe lui-même `HolidayCalendar::France`. Le test ne prouve donc pas la valeur par défaut de `User`, que `TeamControllerTest` couvre déjà à l'inscription. Corrigé : test retiré.
- [x] **[STYLE] Docblock de `DemoCompanyFixtures` mal recoupé** — `fixtures/DemoCompanyFixtures.php:28-31` — L'ajout sur les personnes belges laisse une ligne courte au milieu du paragraphe (« holiday. Test accounts get no entry in the current week, so that the »). Corrigé : paragraphe recoupé à 120 colonnes.

## Points positifs

- **La loi n'est jamais stockée** : `LegalHolidays` est un calcul pur, testé exactement pour 2026 et sur les fêtes mobiles de 2027 et 2028. La base ne porte que les décisions de l'entreprise, ce qui les rend auditables (ADR-0002).
- **Un seul point d'entrée** : saisie, validateur, page de la direction et fixtures passent tous par `HolidayManager`. Le plafond de la semaine se calcule à un seul endroit (`WeeklyMaxManager::capFor()`). `quartersFor()` ne change pas, et la fiche Équipe continue d'afficher le maximum réglé.
- **Verrou vérifié côté serveur** : `TimesheetManager` refuse toute écriture sur un jour férié, même une baisse ou une remise à zéro, avant les plafonds. Un onglet ouvert avant l'ajout d'un jour férié ne peut donc pas le contourner, conformément à la règle 14 du pitch.
- **Invariant porté par l'entité** : les constructeurs `HolidayAdjustment::added()` et `removed()` empêchent un ajout sans libellé, et la contrainte unique (calendrier, jour) interdit un ajout et un retrait le même jour.
- **Tests rejouables** : `PurgesHolidayAdjustments` nettoie les ajustements après chaque test, et la neutralisation E2E est marquée puis purgée avant et après le run. Aucune ligne parasite ne reste en base après une exécution.
- **Semaine à cheval sur deux années** : le cas de 2026-W53, avec le 1er janvier 2027, est géré et testé.

## Hors review (à vérifier en environnement réel)

- **`ext-calendar` en production** : à vérifier au choix de l'hébergeur (suite obligatoire de l'ADR-0002). Sans l'extension, `composer install` échoue explicitement.
- **Base de dev antérieure à la migration** : des temps saisis sur des jours fériés y restent invisibles mais comptés dans les totaux, tant qu'on ne relance pas `make db-reset`. Il n'existe aucune donnée de production.
- **E2E de saisie** : ils dépendent de l'absence d'ajout volontaire sur le calendrier France dans la semaine réelle et la précédente, car un ajout existant n'est pas neutralisé (`INSERT OR IGNORE`).
