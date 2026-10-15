# Plan technique — Calculer le restant d'une feuille à partir du temps saisi aujourd'hui, sans rien figer à la déclaration

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/020-f-avancement-calcule/`
> **Amont** : pas de pitch ; arbitrage direct avec le user, qui révise les règles 9 à 17 du pitch de la story 012

## Contexte

La story 012 figeait deux valeurs sur chaque déclaration d'avancement (`LotProgress`) : le temps saisi sur la feuille ce jour-là (`entered_quarters`) et le restant ancré (`remaining_quarters`). Le temps saisi après la déclaration consommait ce restant. Sur la démo, « Tests de charge » affichait ainsi 15 % déclaré le 09/10, 9 j saisis et un projeté de 18,5 j, parce que les 15 % portaient sur les 2,75 j saisis à cette date.

Le user pose un principe : **ne pas stocker de montants ni de totaux, tout calculer à la volée pour représenter la réalité**. `LotProgress` est la seule entité qui stocke des valeurs dérivées. Le user retient le modèle « pourcentage appliqué au temps saisi actuel ».

## Règles

1. Une déclaration ne porte que **le pourcentage, le jour et l'auteur**. L'avancement en vigueur est la dernière déclaration ; 0 % le retire.
2. **Restant** d'une feuille estimée avec un avancement en vigueur, arrondi au quart supérieur :
   - avec du temps saisi : `saisi aujourd'hui × (100 − %) / %` ;
   - sans temps saisi : `estimé × (100 − %) / 100` ;
   - à 100 % : 0.
3. Sans avancement, le restant reste `estimé − saisi`, borné à 0.
4. **Projeté** = saisi + restant. Une saisie faite après la déclaration est extrapolée au même pourcentage, et le projeté monte jusqu'à la déclaration suivante.
5. **Avancement à actualiser** : la dernière déclaration date de plus de 7 jours, la feuille n'est pas à 100 %, et du temps a été saisi après la déclaration (dernier jour saisi postérieur au jour déclaré). Le signal est un rappel du processus hebdomadaire : la feuille garde son restant et sa fin calculée, comme son lot et son projet.

Exemple : « Tests de charge », 18 j estimés, 9 j saisis, 15 % → restant 51 j, projeté 60 j (+42 j).

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Garder l'ancrage, mais recalculer le temps saisi à la date de déclaration depuis les saisies | Proposé et recommandé, écarté par le user : le projeté resterait figé sur l'état du jour déclaré au lieu de suivre le temps saisi aujourd'hui. |
| Supprimer le signal « avancement à actualiser » | Le restant ne s'épuise plus avant 100 %, mais la saisie hebdomadaire de l'avancement est obligatoire : le signal est redéfini comme rappel. |
| Requête dédiée « temps saisi après la déclaration » | Le dernier jour saisi, déjà chargé par `ScheduleLoader`, suffit pour savoir si du temps a été saisi depuis. |

## Modèle de données

| Table | Changement |
|---|---|
| `lot_progress` | Suppression de `entered_quarters` et `remaining_quarters` (`migrations/Version20261015115637.php`). Le `down()` les recrée, `entered_quarters` à 0 par défaut pour accepter des lignes existantes. |

## Fichiers modifiés

| Fichier | Rôle |
|---|---|
| `src/Entity/LotProgress.php` | Constructeur `(lot, author, day, percent)`, `redeclare(author, percent)`, plus d'accesseurs de quarts. |
| `src/Model/Schedule/LeafProgress.php` | Ne garde que le pourcentage et le jour. `remaining(int $entered, int $estimate)` remplace `anchoredRemaining()` et `remainingAfter()`. `isToRefresh(?lastEntryDay, today)` porte la règle 5, avec `REFRESH_DAYS = 7`. |
| `src/Service/LotProgressManager.php` | Plus de calcul d'ancrage. Refus inchangés : pas de 5 %, 100 % sans temps, déclaration concurrente du jour. |
| `src/Service/Scheduler.php` | Restant via `remaining()`, et calcul du drapeau `progressToRefresh` posé sur la `LeafSchedule`. |
| `src/Model/Schedule/LeafSchedule.php` | Nouveau champ `progressToRefresh`, que lit `isProgressToRefresh()` ; la fin d'une feuille à actualiser reste calculée. |
| `src/Service/ProjectRollup.php` | Restant via `remaining()`. |
| `src/Model/LotSummary.php` | `isRemainingUnknown()` ne vaut plus que pour une feuille en dépassement sans avancement. |
| `src/Enum/Type/RoadmapSignal.php` | Sens et raison de « avancement à actualiser » réécrits ; le signal ne compte plus parmi ceux qui retiennent la fin (`withholdsEnd()`), ni sur la roadmap ni sur l'accueil. |
| `templates/roadmap/_recap.html.twig` | La mention « inconnue, avancement à actualiser » disparaît : la fin est désormais connue. |
| `templates/components/Ruler.html.twig` | La règle place le dépassement juste après le saisi, puis le restant au-delà. |
| `templates/project/show.html.twig` | L'écart projeté est titré « Écart projeté », suivi de « dont … déjà saisis au-delà des estimations » ; la légende de la règle dit « dont déjà dépassé » avant « Restant » ; la raison du « au moins » ne cite plus l'avancement épuisé. |
| `fixtures/DemoCompanyFixtures.php` | Déclarations réduites au pourcentage ; « Documentation » reste à actualiser par l'arrêt des déclarations il y a deux semaines. La démo est aussi refondue : lots de SCA et d'Onboarding renommés et découpés, estimations des feuilles pas encore démarrées (`ESTIMATES_AHEAD`), feuille en retard sur son estimation (`BEHIND_ESTIMATE`), favoris déduits des saisies des quatre dernières semaines. |

## Tests

| Test | Couverture |
|---|---|
| `tests/Unit/Model/Schedule/LeafProgressTest.php` | Extrapolation sur le saisi actuel (40 % de 10 j → 15 j, 15 % de 9 j → 51 j), hausse du restant avec le saisi suivant, estimé sans temps, dépassement, 100 %, arrondi, règle « à actualiser » (plus de 7 jours, saisie postérieure, jamais à 100 %), 0 %. |
| `tests/Unit/Service/SchedulerTest.php` | Restant extrapolé au même pourcentage après de nouvelles saisies ; feuille à actualiser qui garde sa fin ; déclaration récente ou sans saisie depuis non signalée. |
| `tests/Unit/Service/ProjectRollupTest.php`, `tests/Unit/Model/*SummaryTest.php`, `tests/Unit/Model/Roadmap/RoadmapRowTest.php` | Restant, projeté et écarts recalculés ; projeté minimum limité au dépassement sans avancement. |
| `tests/Service/LotProgressManagerTest.php`, `tests/Controller/LotProgressControllerTest.php` | Une déclaration enregistre le pourcentage, le jour et l'auteur. |
| `tests/Service/RoadmapBuilderTest.php`, `tests/Controller/RoadmapControllerTest.php` | Feuille à actualiser signalée avec une fin calculée, nouvelle déclaration qui lève le signal. |
| `tests/Unit/Enum/Type/RoadmapSignalTest.php` | Les signaux qui retiennent la fin, sans « avancement à actualiser ». |
| `tests/Controller/RoadmapProjectControllerTest.php` | Écart projeté et part déjà saisie au-delà des estimations sur la page projet. |
| `tests/Support/CreatesProgress.php`, `tests/Service/ScheduleLoaderTest.php`, `tests/Service/HomeAgendaBuilderTest.php` | Déclarations créées avec le seul pourcentage. |
| `tests/Controller/VocabularyTest.php`, `tests/e2e/lot-team-filter.spec.ts` | Feuilles de la démo refondue. |

## Vérification

`make phpunit` (729 verts), `make playwright` (56 verts), `make lint` propre, migration jouée en montée et en descente sur une base peuplée.

## Suites

- `/forge:sync` : reformuler les règles 9 à 17 du pitch de la story 012 et la ligne du backlog du 2026-10-02 (« restant … ancré à la déclaration »). → fait : règles 9 à 12 et 17 du pitch 012 réécrites ; C5.5, la règle transverse « estimation déclarative » et la note `jalons-dates-annoncees` éditées dans le backlog (l'entrée de changelog du 2026-10-02 reste un historique).
