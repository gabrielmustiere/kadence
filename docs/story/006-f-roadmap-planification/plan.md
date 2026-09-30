# Plan technique — Planifier chaque feuille avec une date de début et une équipe pour dessiner la chronologie des projets sur une roadmap

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/006-f-roadmap-planification/`
> **Amont** : `pitch.md`

## Approche retenue

La planification vit sur la feuille. `Lot` gagne une date de début nullable et une collection de membres, portée par une nouvelle entité d'association `LotMember` (feuille, personne, part de 25 à 100). Elle se saisit dans le formulaire existant du lot (création, premier sous-lot, modification), réservé aux leads et à la direction par une option du `LotType`, comme `with_owner` aujourd'hui. `ProjectManager` reste la porte d'entrée unique : il applique la planification, garantit l'invariant « responsable ⇒ membre » et transfère la planification lors des bascules lot ↔ sous-lot (règles 11 et 12 de la story 002).

La chronologie n'est **jamais stockée** : elle se calcule à la lecture par un moteur pur, `Scheduler`, sans accès base, qui reçoit des plans de feuilles (estimation, consommé, premier et dernier jour saisi, date de début, membres), une capacité journalière précalculée (`DailyCapacity`) et la date du jour. Il rend, par feuille, la partie réalisée, la partie future, le restant et les signaux, ainsi que la charge de chaque personne jour par jour et les feuilles en surcharge. Un chargeur, `ScheduleLoader`, réunit les données en un nombre constant de requêtes groupées (six, plus une quand des feuilles sont en dépassement), quel que soit le nombre de feuilles. Deux consommateurs utilisent le même moteur :

- la contrainte de classe `PlanningFitsCapacity` sur `LotInput`, qui compare la charge avant et après le geste de planification, toujours avec la nouvelle estimation, et sort sans rien charger quand la date de début et l'équipe sont inchangées (`LotInput::planningChanged()`) ;
- `RoadmapBuilder`, qui produit le modèle de vue de la page `/roadmap`, rendue côté serveur (grille CSS en colonnes-semaines, barres positionnées en pourcentage, projets repliés dans des `<details>` natifs).

Le calcul se fait en **arithmétique entière**, avec une unité de 1/6000 de quart : part (%) × maximum de la semaine (quarts) × 60 / nombre de jours non fériés. 60 est divisible par 1 à 5, donc la répartition uniforme d'un temps partiel sur les jours non fériés est exacte, sans dérive de flottants.

### Mécanismes mobilisés

- **Entité d'association Doctrine `LotMember`** avec `OneToMany` inverse sur `Lot` (`cascade: persist, remove`, `orphanRemoval`) : l'équipe porte une donnée propre (la part) et une unicité (feuille, personne) ; une entité est la forme standard, requêtable et contrainte en base.
- **DTO + contraintes de classe** (`LotInput`, `#[PlanningFitsCapacity]`, dans l'esprit de `#[EstimateCoversConsumed]`) : le refus de surcharge est une règle métier qui dépend de l'état de la base ; elle vit dans un validateur injecté, pas dans le contrôleur.
- **`Assert\Unique`** avec le normaliseur `LotMemberInput::identify()` sur la collection de membres, **`Assert\Choice`** sur la part : contraintes natives suffisantes pour l'unicité d'une personne et les parts autorisées (l'option `fields` de `Unique` ne s'applique qu'à des éléments tableaux, pas à des objets).
- **`CollectionType` avec prototype + contrôleur Stimulus `form-collection`** : ajout et retrait de lignes sans transformer le formulaire en Live Component. Pattern standard, environ 30 lignes de JS, compatible AssetMapper.
- **`DateType` en `single_text`** pour la date de début : champ date natif du navigateur, sans initialisation JS (Turbo).
- **`Psr\Clock\ClockInterface`** pour « aujourd'hui », et **`ClockSensitiveTrait`** dans les tests, comme `TimesheetManager` et `TimesheetBuilder`.
- **Option de formulaire `with_planning`** décidée dans `LotController` selon `isGranted('ROLE_LEAD')` et `isLeaf()` : même mécanisme que `with_owner`. Un responsable prod ne reçoit pas les champs, donc ne peut pas les soumettre (un champ inconnu est rejeté par le formulaire).
- **Rendu Twig serveur** de la frise : positions (`left`/`width` en %) calculées en PHP dans le modèle de vue, appliquées en `style` inline ; pas de valeur Tailwind arbitraire générée à l'exécution.
- **`<details>`/`<summary>` natifs** pour replier les projets : zéro JS, accessibles au clavier, remis à l'état replié à chaque visite Turbo (défaut voulu).
- **Composant `NavLink` + `nav_section()`** : nouvelle section `roadmap` (préfixe `app_roadmap`) dans la zone haute du menu.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Stocker la fin calculée (ou les jours planifiés) en base et la recalculer sur événements | Toute saisie, révision, changement de maximum, jour férié ou désactivation devrait déclencher un recalcul ; les sources d'invalidation sont nombreuses et le calcul à la volée coûte ~150 000 opérations entières pour 200 feuilles sur un an. |
| Page dédiée « Planifier » séparée du formulaire du lot | La désignation du responsable (formulaire du lot) est aussi un geste de planification soumis au contrôle de surcharge : deux formulaires à protéger et à tenir cohérents. |
| Formulaire du lot en Live Component (`LiveCollectionType`) | Réécriture des pages création/modification et du bouton « Enregistrer et ajouter un autre » pour un gain limité à l'ajout de lignes. |
| Liste fixe de toutes les personnes actives avec une part par ligne | 15 à 40 lignes dans chaque formulaire de lot, pour une équipe de 1 à 4 personnes. |
| Bibliothèque Gantt JS (frappe-gantt via importmap) | Dépendance à maintenir, rendu SVG hors design system Paper, tests E2E plus fragiles, pour un besoin (barres sur une frise en semaines) qui tient en CSS. |
| Calcul en flottants (jours décimaux) | Accumulation d'erreurs sur des répartitions en 0,8 j ; l'unité entière de 1/6000 de quart est exacte pour 1 à 5 jours ouvrés par semaine. |
| Capacité d'un temps partiel en jours pleins depuis le lundi | Le maximum hebdomadaire ne dit pas quel jour est chômé ; un vendredi artificiellement libre fausserait la charge jour par jour. |

## Modèle de données

### Nouvelle structure `LotMember`

`src/Entity/LotMember.php` (table `lot_member`) :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `lot` | `ManyToOne` (`Lot`), colonne `lot_id` | non | côté propriétaire ; supprimé avec la feuille (cascade ORM depuis `Lot::$members`, `orphanRemoval`) |
| `user` | `ManyToOne` (`User`), colonne `user_id` | non | index ; une personne n'est jamais supprimée (désactivation) |
| `share` | `smallint`, colonne `share` | non | `int<25, 100>`, valeurs `LotMember::SHARES = [25, 50, 75, 100]` |

Contrainte d'unicité `uniq_lot_member_lot_user (lot_id, user_id)`. Pas de cloisonnement (outil mono-organisation). Constructeur `new LotMember(Lot $lot, User $user, int $share)` qui s'ajoute à `$lot->getMembers()`. Pas de logique métier dans l'entité.

### Modification de `Lot`

`src/Entity/Lot.php` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `startDate` | `date_immutable`, colonne `start_date` | oui | uniquement sur une feuille, vidée par `clearLeafData` |
| `members` | `OneToMany` (`LotMember`), `mappedBy: 'lot'` | — | `cascade: ['persist', 'remove']`, `orphanRemoval: true`, `OrderBy(['id' => 'ASC'])` |

Accesseurs : `getStartDate()`/`setStartDate()`, `getMembers()`, `addMember()`/`removeMember()`.

### Migration

Deux migrations. La migration de schéma, générée par `symfony console make:migration` et non retouchée : création de `lot_member` (FK, index, unicité) et ajout de `lot.start_date`. La migration de données, générée vide par `symfony console doctrine:migrations:generate`, porte la reprise des responsables :

```sql
INSERT INTO lot_member (lot_id, user_id, share)
SELECT l.id, l.owner_id, 100 FROM lot l
WHERE l.owner_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM lot c WHERE c.parent_id = l.id)
```

Le `down()` de la migration de données ne fait rien (commentaire dans la migration) : celui de la migration de schéma supprime la table et la colonne, et la reprise perdue est sans conséquence puisque l'équipe est dérivable du responsable.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Entity/LotMember.php` | Membre d'une feuille avec sa part de capacité (25/50/75/100). |
| `migrations/Version20260930123737.php` | Table `lot_member` et colonne `lot.start_date`, générées. |
| `migrations/Version20260930123747.php` | Migration de données : reprise des responsables à 100 %. |
| `src/Dto/LotMemberInput.php` | Ligne d'équipe du formulaire : personne et part, contraintes `NotNull` et `Choice`, normaliseur d'unicité `identify()`. |
| `src/Form/LotMemberType.php` | Sous-formulaire d'une ligne : personne choisie parmi l'option `people` (actifs, plus un membre désactivé déjà présent) et part. |
| `assets/controllers/form_collection_controller.js` | Ajoute une ligne depuis le prototype et retire une ligne. |
| `src/Model/Schedule/PlannedMember.php` | Membre vu par le moteur : id de personne et part ; calendrier et état actif sont lus dans `DailyCapacity`. |
| `src/Model/Schedule/LeafPlan.php` | Entrée du moteur pour une feuille : id, estimation (quarts), consommé, premier et dernier jour saisi, date de début, membres. |
| `src/Model/Schedule/LeafSchedule.php` | Sortie par feuille : partie réalisée, partie future (début, fin ou null), restant, signaux (manques, démarrage en retard, estimation atteinte ou dépassée, équipe à revoir). |
| `src/Model/Schedule/ScheduleResult.php` | Chronologies par feuille, charge par personne et par jour, feuilles en surcharge avec leurs conflits. |
| `src/Model/Schedule/DailyCapacity.php` | Capacité en unités entières d'une personne un jour donné, depuis les maximums historisés et les jours fériés préchargés. |
| `src/Service/Scheduler.php` | Moteur pur : répartit le restant jour ouvré par jour ouvré, calcule charges et surcharges, horizon de 3 ans. |
| `src/Model/Schedule/ScheduleData.php` | Ce que rend le chargeur : feuilles, plans, personnes, capacité, date du jour, jours de dépassement. |
| `src/Service/ScheduleLoader.php` | Charge feuilles, consommés, personnes, maximums et jours fériés en requêtes constantes, construit `LeafPlan`, `DailyCapacity` et les jours de dépassement ; plage de jours fériés extensible à une date de début saisie. |
| `src/Validator/PlanningFitsCapacity.php` | Contrainte de classe sur `LotInput`. |
| `src/Validator/PlanningFitsCapacityValidator.php` | Refuse un geste de planification qui crée ou aggrave une surcharge ; message avec la personne, la feuille en conflit et le premier jour. |
| `src/Model/Roadmap/RoadmapWindow.php` | Fenêtre de 41 semaines ancrée sur une semaine (−4 / +36), pas de 4 semaines, positions en %. |
| `src/Model/Roadmap/RoadmapRow.php` | Ligne de la frise (projet, lot découpé ou feuille) : barre(s) positionnées, coupe aux bords, détails, signaux, enfants. |
| `src/Model/Roadmap/RoadmapBar.php` | Barre positionnée en % avec indicateurs de coupe. |
| `src/Model/Roadmap/Roadmap.php` | Modèle de page : fenêtre, date du jour, projets, repère « aujourd'hui », mois de l'en-tête. |
| `src/Enum/Type/RoadmapSignal.php` | Signaux de la roadmap, avec libellé et variante de badge. |
| `src/Service/RoadmapBuilder.php` | Assemble projets, lots et feuilles en lignes de frise à partir de `ScheduleResult` et de la fenêtre ; signaux de surcharge seulement si demandés. |
| `src/Controller/RoadmapController.php` | `GET /roadmap` (`app_roadmap`) et `GET /roadmap/{week}` (`app_roadmap_week`). |
| `templates/roadmap/index.html.twig` | Page Roadmap : navigation, en-tête des semaines, repère aujourd'hui, projets dans des `<details>`. |
| `templates/roadmap/_row.html.twig` | Libellé et détails sous le titre, barres, signaux, récursion lots → sous-lots. |
| `templates/roadmap/_bar.html.twig`, `_dates.html.twig`, `_today.html.twig` | Barre avec fondu sur un bord coupé, dates ou « fin inconnue », repère « aujourd'hui ». |
| `templates/lot/_member_row.html.twig` | Ligne d'équipe, partagée par les lignes existantes et le prototype. |
| `tests/Unit/Service/SchedulerTest.php` | Tous les exemples chiffrés du pitch, signaux, charge et surcharge, horizon. |
| `tests/Unit/Model/Schedule/DailyCapacityTest.php` | Répartition uniforme, jours fériés, maximum historisé, personne inactive. |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | Fenêtre par défaut, navigation par 4 semaines, positions et coupe aux bords. |
| `tests/Service/RoadmapBuilderTest.php` | Contre la base de test (`ScheduleLoader` est `final readonly`) : barres de cumul, feuilles non planifiées, planning partiel, dépassement, signaux de surcharge absents sans le drapeau. |
| `tests/Unit/Dto/LotInputTest.php` | `effectiveMembers()` ajoute le responsable à 100 % sans doublon ; pré-remplissages `fromLot()` et `forSubLotOf()`. |
| `tests/Service/ScheduleLoaderTest.php` | Contre la base de test : agrégats de saisie, maximums historisés, jours fériés, jour de dépassement, membres chargés. |
| `tests/Controller/RoadmapControllerTest.php` | Accès par rôle, barre d'une feuille planifiée, dépassement, badges des non planifiées, « à replanifier » visible du lead et absent du HTML pour la prod, navigation et semaine invalide, nombre de requêtes constant. |
| `tests/e2e/roadmap.spec.ts` | Un lead planifie une feuille avec ajout d'un membre, la voit sur la roadmap, déplie le projet, navigue ; revient au formulaire depuis la roadmap. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Entity/Lot.php` | Ajouter `startDate` et la collection `members` avec accesseurs. |
| `src/Dto/LotInput.php` | Ajouter `startDate`, `members` (liste de `LotMemberInput`, `Assert\Valid`, `Assert\Unique` avec normaliseur), la planification en vigueur (`currentStartDate`, parts), `effectiveMembers()` (le responsable absent revient avec sa part en vigueur, 100 % s'il rejoint l'équipe) et `planningChanged()` ; pré-remplir dans `fromLot()` et, en cas de reprise, `forSubLotOf()` ; ajouter `#[PlanningFitsCapacity]`. |
| `src/Form/LotType.php` | Option `with_planning` : champ `startDate` (`DateType`, `single_text`) et `members` (`CollectionType` de `LotMemberType`, `allow_add`, `allow_delete`, prototype à 100 %, `error_bubbling` désactivé) ; personnes actives chargées une fois pour le responsable et les lignes. |
| `templates/lot/_form.html.twig` | Bloc « Planification » (date de début, lignes d'équipe, bouton « Ajouter un membre ») branché sur le contrôleur `form-collection`. |
| `templates/lot/new.html.twig` | Le message de reprise du premier sous-lot mentionne aussi la date de début et l'équipe. |
| `templates/lot/edit.html.twig` | Lien « Annuler » vers la roadmap quand on vient de la roadmap. |
| `src/Controller/LotController.php` | Passer `with_planning` (lead et feuille) en création, premier sous-lot et modification ; paramètre de requête `roadmap` (semaine ISO validée par `Week::fromIso`) pour rediriger vers `app_roadmap_week` après enregistrement. |
| `src/Service/ProjectManager.php` | `applyLot()` applique la date de début et synchronise les membres depuis `effectiveMembers()` ; `clearLeafData()` vide aussi date et équipe ; `deleteLot()` fait remonter date et équipe du dernier sous-lot (nouveaux `LotMember` sur le lot). |
| `src/Repository/LotRepository.php` | `findLeavesForSchedule()` : toutes les feuilles avec projet, parent, responsable, membres et personnes en une requête. |
| `src/Repository/TimeEntryRepository.php` | `summarizeByLot()` : somme des quarts, premier et dernier jour saisi par feuille, en une requête groupée ; `sumQuartersByDayForLots()` pour le jour de dépassement. |
| `src/Repository/WeeklyMaxRepository.php` | `findAllQuartersByUser()` : tous les maximums, (lundi d'effet, quarts) par personne. |
| `src/Service/WeeklyMaxManager.php` | Extraire le plafond en helper statique `cap(int $quarters, int $holidayCount)`, réutilisé par `capFor()` et `DailyCapacity`. |
| `src/Service/HolidayManager.php` | Exposer `holidaysBetween(HolidayCalendar, from, to)` (l'actuel `between()` privé). |
| `src/Twig/NavigationExtension.php` | Section `roadmap` pour le préfixe `app_roadmap`. |
| `templates/base.html.twig` | Entrée `NavLink` « Roadmap » (`data-test="nav-roadmap"`) dans la zone du haut, après Ma semaine. |
| `fixtures/ProjectFixtures.php` | Chaque feuille avec responsable reçoit son responsable en membre à 100 % (invariant, les fixtures ne passent pas par la migration). |
| `fixtures/DemoCompanyFixtures.php` | Planifier les feuilles (dates de début, équipes par pôle, parts), dont un démarrage en retard, des estimations atteintes, des dépassements et une surcharge passive. |
| `tests/Support/CreatesProjects.php` | `createLot()` ajoute le responsable en membre ; helper `planLot(Lot, ?DateTimeImmutable, array $members)`. |
| `tests/Unit/Service/ProjectManagerTest.php` | Planification appliquée, responsable ajouté, bascules avec transfert de date et d'équipe. |
| `tests/Controller/LotControllerTest.php` | Champs de planification selon le rôle, refus de surcharge et message, révision d'estimation seule jamais refusée, désignation du responsable contrôlée, reprise par le premier sous-lot, retour vers la roadmap. |
| `tests/Controller/NavigationTest.php` | Entrée Roadmap pour les trois rôles, active sur `/roadmap`. |
| `tests/Unit/Twig/NavigationExtensionTest.php` | Section `roadmap`. |
| `assets/styles/app.css` | Classe `.roadmap-track` : quadrillage des semaines, nombre de colonnes transmis par `--roadmap-weeks`. |
| `tests/e2e/projects.spec.ts` | Purge des équipes avant les lots (SQLite n'applique pas les clés étrangères). |
| `templates/components/Timesheet.html.twig` | Bordures pointillées entre les colonnes de jours de la grille de saisie ; changement étranger à la roadmap, rattaché à la story. |

## Hors scope

- **Stockage ou cache des chronologies** : calcul à chaque requête ; un cache par requête ne sera ajouté que si une mesure le justifie.
- **Colonne « Équipe » ou date de début sur la page projet** : la planification se lit sur la roadmap et se modifie dans le formulaire du lot.
- **Raccourci Roadmap sur la page d'accueil** : non demandé par le pitch.
- **Date de désactivation d'une personne** : une personne inactive apporte 0 sur tout le futur, ce qui suffit puisque le passé ne dépend que des temps saisis.
- **Infobulles et zoom de la frise** : écartés au cadrage.
- **Refactor de `ProjectRollup`** vers le moteur de chronologie : les deux restent indépendants.

## Impacts transverses

- **Cloisonnement des données** : aucun filtrage multi-organisation. Principe 2 : `RoadmapBuilder` ne reçoit le drapeau des surcharges que si `isGranted('ROLE_LEAD')`, donc le signal « à replanifier » n'est jamais calculé dans le modèle de vue d'un membre de prod, ni présent dans son HTML. La partie réalisée provient de `summarizeByLot()`, agrégée par feuille, sans identité de saisie.
- **Déclinaisons / thèmes** : non.
- **Traduction / i18n** : libellés en français en dur dans les templates et formulaires, comme le reste du projet (interface française uniquement ; seul l'écran de connexion passe par `|trans`).
- **API / exposition externe** : non.
- **Droits d'accès** : `/roadmap` couvert par la règle existante `^/` → `ROLE_USER`. Planification réservée par `with_planning` décidé côté contrôleur (`ROLE_LEAD`, déjà la frontière de `LotVoter` pour les leads) ; pas de nouveau voter. Le paramètre de retour n'accepte qu'une semaine ISO valide et ne redirige que vers `app_roadmap_week`, donc pas de redirection ouverte.
- **Emails / notifications** : non.
- **Migration de données** : création de `lot_member`, ajout de `lot.start_date`, reprise des responsables des feuilles à 100 %.
- **Comportement par défaut** : sans date de début, une feuille n'a pas de barre et apparaît « sans début » ; le formulaire du lot d'un responsable prod est inchangé.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Service/Scheduler.php` | unit | 10 j depuis un lundi, 1 personne à 5 j/sem à 100 % → vendredi S+1 ; 2 personnes → vendredi S ; 50 % → vendredi S+3 ; jour férié d'un membre → fin repoussée ; maximum 16 quarts → fin repoussée ; 3 j saisis en S (aujourd'hui = vendredi S) → mardi S+2 ; début passé sans saisie → futur demain et « démarrage en retard » ; saisie avant le début → barre au premier jour saisi ; consommé = estimation → fin au dernier jour saisi, « estimation atteinte » ; consommé > estimation → pas de fin, « en dépassement » ; membre inactif → barre allongée, « équipe à revoir » ; équipe entièrement inactive ou restant non couvert en 3 ans → pas de fin, « équipe à revoir » ; feuille à estimer / sans début / sans équipe → manques, pas de barre ; charge jour par jour et feuilles en surcharge (> 100 %), jours fériés d'une personne hors charge. |
| `src/Model/Schedule/DailyCapacity.php` | unit | Répartition uniforme exacte (16 quarts sur 5 jours, 4 jours avec un férié), maximum historisé par semaine d'effet, défaut à 20 quarts, 0 un jour férié, 0 pour une personne inactive. |
| `src/Model/Roadmap/RoadmapWindow.php` | unit | Fenêtre par défaut (semaine courante −4 à +36), précédente et suivante par 4 semaines, conversion jour → %, coupe à gauche et à droite. |
| `src/Service/RoadmapBuilder.php` | functional | Ordre des projets et des lots, barre de cumul d'un lot découpé et d'un projet, feuille non planifiée sans barre avec ses manques, projet « planning partiel », projet à découper, signaux de surcharge présents avec le drapeau et absents sans. |
| `src/Dto/LotInput.php` | unit | `effectiveMembers()` ajoute le responsable à 100 % s'il manque, ne le duplique pas ; `forSubLotOf()` reprend date et équipe d'une feuille, rien d'un lot découpé. |
| `src/Service/ProjectManager.php` | unit | Création et modification avec date et équipe ; responsable ajouté ; retrait d'une ligne supprime le membre ; bascule vers le premier sous-lot transfère date et équipe ; suppression du dernier sous-lot les fait remonter. |
| `src/Service/ScheduleLoader.php` | functional | Agrégats (somme, premier et dernier jour) par feuille, maximums et jours fériés chargés, jour de dépassement, jours fériés au-delà de l'horizon ; nombre de requêtes constant vérifié par le profiler dans `RoadmapControllerTest`. |
| `src/Validator/PlanningFitsCapacityValidator.php` (via `LotController`) | functional | Ajout d'un membre, changement de part, date de début et désignation d'un responsable refusés en cas de surcharge avec personne, feuille en conflit et premier jour dans le message ; révision d'estimation seule jamais refusée ; geste qui réduit ou n'aggrave pas une surcharge existante accepté ; reprise par un premier sous-lot jamais refusée. |
| `src/Controller/LotController.php` | functional | Champs de planification présents pour lead et direction sur une feuille, absents pour un responsable prod (soumission forcée rejetée) et sur un lot découpé ; enregistrement persistant ; retour vers `app_roadmap_week` avec `?roadmap=2026-W40`, valeur invalide ignorée. |
| `src/Controller/RoadmapController.php` | functional | 200 pour les trois rôles ; barre et détails d'une feuille planifiée ; badges « à estimer », « sans début », « sans équipe » et « planning partiel » ; « à replanifier » pour lead et direction, absent du HTML pour la prod ; `/roadmap/2026-W40` décale la fenêtre ; semaine invalide → 404. |
| `src/Twig/NavigationExtension.php` | unit | `app_roadmap` et `app_roadmap_week` → `roadmap`. |
| `tests/e2e/roadmap.spec.ts` | E2E | Lead : ouvre un lot, ajoute un membre via « Ajouter un membre », choisit une part, pose une date, enregistre ; la barre apparaît sur la roadmap après dépliage du projet ; navigation « suivant » puis « Aujourd'hui » ; clic sur le titre d'une feuille, enregistrement, retour sur la même fenêtre. |

**Hors scope tests** :

- Pas de test de la reprise de la migration par PHPUnit : la base de test est reconstruite vide puis remplie par les fixtures. Vérification manuelle en dev (`symfony console doctrine:migrations:migrate` sur la base de démo existante, puis contrôle que chaque feuille avec responsable a un membre à 100 %).
- Pas de test visuel des positions en pixels : les positions en % sont testées dans `RoadmapWindowTest`, l'E2E vérifie la présence des barres.
- Pas de test de performance automatisé au-delà du nombre de requêtes constant vérifié dans `RoadmapControllerTest`.

## Ordre d'exécution

1. [x] **Modèle et migration**
   - Objectif : `LotMember`, `Lot::$startDate` et `Lot::$members` en place, reprise des responsables.
   - Fichiers : `src/Entity/LotMember.php`, `src/Entity/Lot.php`, migration de schéma générée et migration de données générée vide portant la reprise, `fixtures/ProjectFixtures.php`, `tests/Support/CreatesProjects.php`.
   - Vérification : `symfony console doctrine:schema:validate`, migration jouée sur la base de dev (reprise contrôlée), `make phpunit` vert.
   - Commitable seule : oui.

2. [x] **Moteur de chronologie pur**
   - Objectif : `Scheduler` et `DailyCapacity` calculent chronologies, signaux, charges et surcharges en unités entières.
   - Fichiers : `src/Model/Schedule/*`, `src/Service/Scheduler.php`, `src/Service/WeeklyMaxManager.php` (helper `cap()`), tests unitaires associés.
   - Vérification : `make phpunit-filter SchedulerTest` et `DailyCapacityTest` verts, tous les exemples du pitch couverts.
   - Commitable seule : oui.

3. [x] **Chargeur**
   - Objectif : `ScheduleLoader` construit plans et capacité en requêtes constantes.
   - Fichiers : `src/Service/ScheduleLoader.php`, `LotRepository`, `TimeEntryRepository`, `WeeklyMaxRepository`, `HolidayManager`, `tests/Service/ScheduleLoaderTest.php`.
   - Vérification : `make phpunit-filter ScheduleLoaderTest` vert, nombre de requêtes constant.
   - Commitable seule : oui.

4. [x] **Planification dans le formulaire du lot**
   - Objectif : un lead pose date de début et équipe ; responsable ⇒ membre ; bascules avec transfert.
   - Fichiers : `LotInput`, `LotMemberInput`, `LotType`, `LotMemberType`, `templates/lot/_form.html.twig`, `templates/lot/new.html.twig`, `assets/controllers/form_collection_controller.js`, `LotController`, `ProjectManager`, tests `LotInputTest`, `ProjectManagerTest`, `LotControllerTest`.
   - Vérification : `make phpunit` vert, formulaire manipulé à la main sur `https://kadence.wip`.
   - Commitable seule : oui.

5. [x] **Contrôle de surcharge**
   - Objectif : `PlanningFitsCapacity` refuse les gestes de planification qui créent ou aggravent une surcharge, jamais une révision d'estimation seule.
   - Fichiers : `src/Validator/PlanningFitsCapacity.php`, `src/Validator/PlanningFitsCapacityValidator.php`, `LotInput`, `LotControllerTest`.
   - Vérification : `make phpunit-filter LotControllerTest` vert.
   - Commitable seule : oui.

6. [x] **Page Roadmap**
   - Objectif : frise en semaines, projets repliés, détails sous le titre, signaux selon le rôle, navigation, retour depuis le formulaire, entrée de menu.
   - Fichiers : `RoadmapWindow`, `RoadmapRow`, `RoadmapBuilder`, `RoadmapController`, `templates/roadmap/*`, `templates/lot/edit.html.twig`, `LotController` (retour), `NavigationExtension`, `templates/base.html.twig`, tests associés.
   - Vérification : `make phpunit` vert, page relue à la main en lead et en prod.
   - Commitable seule : oui.

7. [x] **Fixtures de démonstration planifiées**
   - Objectif : la fausse entreprise montre une roadmap crédible, avec un démarrage en retard, des estimations atteintes, des dépassements et une surcharge passive.
   - Fichiers : `fixtures/DemoCompanyFixtures.php`.
   - Vérification : `make db-reset`, page `/roadmap` relue en `lead@example.com` et `prod@example.com`.
   - Commitable seule : oui.

8. [x] **E2E et QA finale**
   - Objectif : parcours de planification et de lecture de la roadmap couvert ; suite complète verte.
   - Fichiers : `tests/e2e/roadmap.spec.ts`, ajustements éventuels de `tests/e2e/navigation.spec.ts`.
   - Vérification : `make playwright-file tests/e2e/roadmap.spec.ts`, `make playwright`, `make phpunit`, `make lint`.
   - Commitable seule : oui.

## Critères de sortie

- [x] `symfony console doctrine:schema:validate` sans erreur ; la migration crée `lot_member` (unicité feuille × personne) et `lot.start_date`, et reprend les responsables à 100 %.
- [x] La chronologie n'est persistée nulle part ; `ScheduleLoader` émet un nombre de requêtes constant, quel que soit le nombre de feuilles.
- [x] Tous les exemples chiffrés des critères d'acceptation du pitch sont des cas de `SchedulerTest`.
- [x] Le signal « à replanifier » est absent du HTML de `/roadmap` pour `prod@example.com`.
- [x] Une révision d'estimation seule n'est jamais refusée par `PlanningFitsCapacity` (test dédié).
- [ ] `make phpunit` et `make playwright` verts, sans nouvelle régression.
- [x] `make lint` propre (PHP-CS-Fixer en dry-run, PHPStan level 10).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Temps de calcul de la roadmap et du validateur quand le nombre de feuilles grandit | faible | Requêtes groupées en nombre constant, arithmétique entière, horizon borné à 3 ans ; mesurer sur les fixtures de démo, et ajouter un cache par requête seulement si la page dépasse 200 ms. |
| Écart entre la charge vue par le validateur et celle de la roadmap | moyenne | Un seul moteur (`Scheduler`) et un seul chargeur pour les deux consommateurs ; « aujourd'hui » vient de la même horloge. |
| Invariant « responsable ⇒ membre » contourné par du code ou des fixtures qui posent `owner` directement | moyenne | Invariant appliqué dans `ProjectManager` ; fixtures et `CreatesProjects` alignés ; la reprise de migration couvre l'existant. |
| Rupture des tests existants du formulaire du lot (nouveaux champs, soumission) | moyenne | Champs de planification facultatifs ; `LotControllerTest` et `ProjectManagerTest` mis à jour à l'étape 4. |
| Fuite de charge individuelle vers la prod (principe 2) | faible | Drapeau de surcharge décidé côté contrôleur ; test fonctionnel sur l'absence du signal dans le HTML. |
| Ligne de prototype du `CollectionType` mal rendue avec le thème de formulaire Flowbite | faible | Rendu explicite de la ligne dans `_form.html.twig` (`form_widget` par champ), vérifié à la main et en E2E. |
| Frise illisible sur mobile (41 colonnes) | moyenne | Colonne des libellés fixe et défilement horizontal dans le conteneur de la frise, sans défilement horizontal de la page. |

## Questions ouvertes

- **Feuille en surcharge passive avec une personne désactivée** : une personne inactive n'apporte plus de capacité ; sa part compte-t-elle encore dans la charge ? → tranché : non, la charge ne porte que sur les personnes actives (une personne inactive ne peut plus être surchargée).
- **Restant non couvert dans les 3 ans avec une capacité non nulle** : → tranché : pas de fin calculée et signal « équipe à revoir », comme une équipe sans capacité (règle 16 du pitch précisée).
