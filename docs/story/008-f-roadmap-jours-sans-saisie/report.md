# Report — Couper la partie réalisée d'une feuille sur les jours sans saisie pour montrer ses interruptions sur la roadmap

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/008-f-roadmap-jours-sans-saisie/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 95 %. Écarts structurants :
  - les jours saisis ne sont chargés que pour les feuilles planifiées, à la suite d'un mineur de review ;
  - le test du jour férié commun porte sur l'Ascension au lieu du 1er mai.
- **Critères** : 12 / 12 cochés. Celui du 11/11 est vérifié sur l'Ascension, qui obéit à la même règle.
- **Review** : 0 bloquant, 0 important, 2 mineurs, tous corrigés pendant la passe. Statut : PRÊT À COMMITER.
- **Périmètre livré** : 0 fichier créé, 15 fichiers modifiés (environ +408 / −47 lignes), hors documents de story et hors correctif des libellés de mois.

La partie saisie d'une feuille, bleue ou rouge, est découpée en tronçons sur chaque jour ouvré sans saisie. Une seule infobulle couvre toute la partie et donne le nombre de jours avec saisie, et la légende explique le vide. Tout ce que le plan prévoyait est livré. Le correctif des libellés de mois (`src/Model/Roadmap/Roadmap.php` et deux tests de `RoadmapWindowTest`) a été constaté pendant la recette de cette story, mais il corrige un défaut hérité de la story 006 : sur décision de l'utilisateur, il est hors story et partira dans un commit `fix(roadmap)` séparé.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| _(aucun)_ | Tous les changements se font dans des fichiers existants. | Oui |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Model/Schedule/DailyCapacity.php` | Ajout de `hasWorkingDayBetween()`, qui cherche un jour ouvré (`isWorkingDay()`) pour au moins une des personnes, strictement entre deux dates. | Oui |
| `src/Service/ScheduleLoader.php` | `capacity()` reçoit `$firstDay`, le plus ancien `firstEntryDay` quand il précède le lundi de la semaine en cours. | Oui |
| `src/Model/Roadmap/RoadmapBar.php` | Ajout de `public array $segments = []` (`list<RoadmapBar>` en pourcentage de la barre). | Oui |
| `src/Model/Roadmap/RoadmapWindow.php` | Ajout de `segmentedBar()` : barre du premier au dernier jour des tronçons, tronçons hors fenêtre ignorés, `null` sans tronçon visible. | Oui |
| `src/Model/Roadmap/RoadmapRow.php` | Ajout de `realizedDayCount` et `overrunDayCount`. | Oui |
| `src/Service/RoadmapBuilder.php` | Ajout de `enteredParts()` et de `runs()`, qui remplacent `realizedBars()` et le paramètre `$overrunDays` ; `enteredParts()` reçoit aussi le `ScheduleResult` pour ne traiter que les feuilles planifiées. | Écart volontaire (cf. §Écarts) |
| `templates/roadmap/_bar.html.twig` | Conteneur transparent (`bg-transparent` par `tailwind_merge`) qui porte le déclencheur, le fondu et le `data-test`, avec un enfant `roadmap-segment-{kind}` par tronçon. | Oui |
| `templates/roadmap/_tooltip.html.twig` | Ligne « Jours avec saisie » (`roadmap-days-entered`) dans les infobulles `realized` et `overrun`. | Oui |
| `templates/roadmap/index.html.twig` | Entrée de légende « Jour ouvré sans saisie » (`roadmap-legend-gap`). | Oui |
| `tests/Unit/Model/Schedule/DailyCapacityTest.php` | Deux tests sur `hasWorkingDayBetween()`. | Oui |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | Trois tests sur `segmentedBar()` et un helper `days()`. Les deux tests des libellés de mois sont hors story. | Oui |
| `tests/Service/ScheduleLoaderTest.php` | Test du 14/07 connu pour un calendrier France, avec une saisie en juillet. | Oui |
| `tests/Service/RoadmapBuilderTest.php` | Quatre tests de tronçons et un paramètre `anchor` ajouté à `build()`. | Écart volontaire (cf. §Écarts) |
| `tests/Controller/RoadmapControllerTest.php` | Test de la feuille interrompue (DOM, infobulle, légende), plus deux assertions sur « Jours avec saisie » dans le test du dépassement. | Oui |
| `tests/e2e/roadmap.spec.ts` | Feuille interrompue insérée en SQL (`ancien@example.com`), purge des `time_entry`, scénario de survol des deux tronçons, helper `iso()` partagé. | Oui |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| `enteredParts()` charge les jours saisis de toutes les feuilles qui ont des saisies. | Seules les feuilles dont `ScheduleResult::get()->isPlanned()` est vrai sont chargées et découpées. | Finding de review **[PERF] Jours saisis chargés et découpés aussi pour les feuilles non planifiées** : une feuille jamais dessinée (comme « Support » dans la démo) était traitée pour rien. |
| `RoadmapBuilderTest` vérifie qu'un jour férié commun à la France et à la Belgique ne coupe pas la barre, avec le 01/05. | Le test utilise l'Ascension (14/05/2026, un jeudi). | Le 1er mai 2026 tombe un vendredi, juste avant le week-end : il ne distinguerait pas un jour férié d'un week-end. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| Deux assertions sur « Jours avec saisie » dans `testOverrunLeafShowsByHowMuchAndThatItsEndIsUnknown` | Finding de review **[TEST] « Jours avec saisie » de l'infobulle de dépassement non vérifié au rendu**. |
| Cas « 14/07 avec une équipe française » dans `testAHolidayCutsTheTimeEnteredOnlyWhenOneMemberCouldWork` | Il complète le cas du 21/07 côté calendrier France, dans le même test. |
| Helper `iso()` dans `tests/e2e/roadmap.spec.ts`, réutilisé par `nextMonday()` | Le formatage de date sert désormais à `nextMonday()` et à `weekDays()`. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Model/Schedule/DailyCapacity.php` (`hasWorkingDayBetween`) | unit | unit, 2 tests (jours contigus, week-end, jour ordinaire, jour férié commun, jour férié d'un seul calendrier, personne désactivée, liste vide) | Fait |
| `src/Model/Roadmap/RoadmapWindow.php` (`segmentedBar`) | unit | unit, 3 tests (positions relatives, tronçon hors fenêtre et bord coupé, fenêtre entre deux tronçons, liste vide) | Fait |
| `src/Service/ScheduleLoader.php` | functional (base) | functional, 1 test (14/07 France et Belgique) | Fait |
| `src/Service/RoadmapBuilder.php` | functional (base) | functional, 4 tests (semaine de pause, mercredi et week-end, jours fériés selon les calendriers et membre désactivé, dépassement) | Fait — Ascension au lieu du 01/05 (cf. §Écarts), cas du 14/07 ajouté |
| `src/Controller/RoadmapController.php` (page `/roadmap`) | functional | functional, 1 test, plus 2 assertions dans le test du dépassement | Fait — couverture étendue (review) |
| `tests/e2e/roadmap.spec.ts` | E2E | E2E, 1 scénario (deux tronçons, même infobulle depuis le premier et le dernier) | Fait |
| Rendu au pixel des tronçons d'un jour | hors scope assumé | pas écrit | Conforme — géométrie couverte en unitaire, rendu vérifié à la recette visuelle |
| Performance de la requête jour par jour | hors scope assumé | pas écrit | Conforme — nombre de requêtes constant vérifié ; `/roadmap` à environ 80 ms côté serveur sur la démo, mesure faite à la recette |
| `Scheduler` et `PlanningFitsCapacityValidator` | non modifiés, tests existants en filet | inchangés ; `LotControllerTest` (34 tests) vert après l'étape 2 | Conforme |

Résultats consignés à l'implémentation : 352 tests PHPUnit et 37 scénarios Playwright verts. Après les corrections de la review : 58 tests de la roadmap et 5 scénarios E2E de `roadmap.spec.ts` verts. La suite complète n'a pas été relancée après la review : non mesuré.

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Une feuille de 10 j tenue par une personne au calendrier France, saisie du lundi 07/09 au vendredi 11/09 puis du lundi 21/09 au vendredi 25/09, sans saisie la semaine du 14/09, s'affiche en deux tronçons : du 07/09 au 11/09 et du 21/09 au 25/09.
- [x] Dans cet exemple, survoler l'un ou l'autre tronçon affiche la même infobulle : période du 07/09 au 25/09, 10 j saisis sur 10 j estimés, 10 jours avec saisie.
- [x] Une feuille saisie le lundi, le mardi, le jeudi et le vendredi d'une même semaine est coupée le mercredi.
- [x] Une feuille saisie le vendredi puis le lundi suivant reste d'un seul tenant sur le week-end.
- [x] Une feuille dont tous les membres suivent un calendrier où le 11/11 est férié n'est pas coupée le 11/11, même sans saisie ce jour-là. — Vérifié sur l'Ascension (14/05), jour férié commun aux deux calendriers et soumis à la même règle. Le 11/11 tombe après la date figée des tests (07/10/2026), mais il est bien férié en France et en Belgique dans Kadence.
- [x] Une feuille dont un membre suit le calendrier France, sans saisie le 21/07, est coupée le 21/07 ; la même feuille avec une équipe entièrement au calendrier Belgique ne l'est pas.
- [x] Un membre désactivé de l'équipe compte toujours pour dire si un jour était ouvré : une feuille dont il est le seul membre au calendrier France reste coupée sur un jour férié belge sans saisie.
- [x] Sur une feuille en dépassement, la partie saisie au-delà de l'estimation est elle aussi coupée sur ses jours sans saisie, et ses tronçons partagent une infobulle avec le dépassement et le nombre de jours avec saisie.
- [x] Un tronçon qui déborde de la fenêtre de la frise est coupé au bord avec l'indication qu'il continue, et l'infobulle donne la période complète de la partie.
- [x] La partie future, le vide entre partie réalisée et partie future, les dates de début et de fin calculées et les signaux d'une feuille sont identiques avant et après la livraison.
- [x] Les barres de cumul d'un lot découpé et d'un projet restent continues, même quand toutes leurs feuilles ont un jour sans saisie en commun.
- [x] Un membre de prod voit les mêmes tronçons et la même infobulle qu'un lead ou la direction.

## Dette technique identifiée

Issus de la review (mineurs non traités) :

_(aucun : les deux mineurs ont été corrigés pendant la passe)_

Au-delà de la review :

1. **Volume de la requête jour par jour** — `sumQuartersByDayForLots()` porte sur tout l'historique des feuilles planifiées. À mesurer en production quand l'historique dépassera un an ; au besoin, la borner à la fenêtre affichée (alternative écartée au plan).
2. **Couplage entre le seuil des libellés de mois et la largeur minimale de la frise** — `Roadmap::MIN_MONTH_WIDTH` (9 %) suppose `min-w-[72rem]` et une colonne de `20rem` dans `templates/roadmap/index.html.twig`. Ce point relève du correctif hors story, et il faudra le revoir si ces valeurs changent.
3. **Cause d'un trou inconnue** — un congé ou le jour libre d'un temps partiel coupe la barre comme un oubli de saisie, ce qui est hors scope assumé du pitch. La ligne `capacite-equipe` (absences) permettra d'en tenir compte.
4. **Seuil de trou fixe à un jour** — hors scope assumé du pitch, à réévaluer à l'usage si les barres des feuilles à temps partiel sont trop hachées.

## Leçons apprises

- **Infobulles Flowbite : un déclencheur par infobulle** — chaque `data-tooltip-target` crée une instance identifiée par l'id de l'infobulle, avec `override: true`, si bien qu'un second déclencheur détruit le premier. Pour partager une infobulle, il faut un conteneur déclencheur unique, et un E2E de survol pour le prouver.
- **Dates de test et calendrier** — un jour férié collé à un week-end (le 1er mai 2026, un vendredi) ne prouve rien sur une règle de jour ouvré. Il faut aussi choisir des dates passées par rapport à l'horloge figée des tests et ancrer la fenêtre de la frise sur elles (`build(anchor: …)`).
- **Positions en pourcentage et largeur minimale** — un seuil exprimé en pourcentage de la frise doit être calculé pour la piste la plus étroite (832 px) et la largeur réelle des libellés, sinon les libellés se chevauchent sur les petits écrans.
- **Mesurer la recette dans le navigateur** — passer les 104 fenêtres de 2026 et 2027 en revue par script a confirmé l'absence de chevauchement là où une capture n'en montrait qu'une seule.
