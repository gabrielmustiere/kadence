# Plan technique — Consulter la fiche d'une personne : sa charge, ses affectations à venir et la timeline de tout son travail saisi

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/011-f-fiche-collaborateur/`
> **Amont** : `pitch.md`

## Approche retenue

La fiche d'une personne est une vue calculée à la lecture, comme la fiche d'un projet : rien n'est stocké. Un nouveau service, `PersonRoadmapBuilder`, porte sa construction. Il ne s'ajoute pas à `RoadmapBuilder`, qui est déjà à la limite de complexité cognitive de PHPStan (story 010). Comme la roadmap, il planifie **toutes** les feuilles avec `ScheduleLoader` et `Scheduler`, car la charge et la fin calculée d'une feuille dépendent des autres. Il retient ensuite les feuilles de la personne : celles où elle a saisi et celles dont elle est membre, triées par `LeafOrder` et regroupées par projet. Ses tronçons viennent de `RoadmapRunCutter::cutFor()`, qui lit en une requête tous ses jours saisis par feuille. Ils sont coupés sur ses propres jours ouvrés, et le partage entre l'estimation et le dépassement reste celui de la feuille (`ScheduleData::overrunDays`). Chaque feuille donne une `PersonLeafRow` : sa part, son début, sa fin calculée, ses tronçons placés sur la fenêtre (`RoadmapWindow::segmentedBar()`), sa partie à venir si elle en est membre, et ses signaux.

La fenêtre est une `RoadmapWindow::spanning()` bornée par ses tronçons et par les parties à venir des feuilles dont elle est membre. La timeline reprend `TimelineEntry::ofLeaves()` et `TimelineMonth::group()` de la fiche projet. Le bloc « À venir » filtre les lignes de ses affectations dont la feuille n'est pas terminée. La charge, `PersonLoad`, se déduit de `ScheduleResult::loadsOf()` :
- des plages de jours ouvrés consécutifs de même charge (`LoadSpan`), le week-end ne les coupant pas ;
- la charge du prochain jour ouvré ;
- « libre à partir du … », ou les feuilles dont la fin inconnue la rend inconnue.

Elle n'est calculée que si le contrôleur y est autorisé par `PersonVoter` (`PERSON_PLANNING` : lead, direction, la personne elle-même). Ainsi, la charge d'un collègue n'atteint jamais le HTML d'un membre de prod. La route `app_person` (`/personnes/{id}`) vit dans un `PersonController`. Aucun préfixe de `NavigationExtension` ne la reconnaît, donc aucune entrée du menu ne s'allume. La page embarque la coquille `_frieze.html.twig` avec ses propres lignes (`person/_row.html.twig`) et réutilise `_bar.html.twig` avec une infobulle propre à la fiche.

### Mécanismes mobilisés

- **`ScheduleLoader`, `Scheduler`, `ScheduleResult`** : la charge, les parties à venir et les fins calculées sont exactement celles de la planification (story 006). `loadsOf()` expose la charge d'une personne sans la recalculer.
- **`RoadmapRunCutter`** (story 010), étendu de `cutFor()` : un seul algorithme de découpe. La personne y est sa propre référence de jours ouvrés, comme l'équipe pour une feuille.
- **`RoadmapSignal::of(LeafSchedule)`**, ex-`RoadmapBuilder::signalsOf()` : les signaux d'une feuille sont les mêmes sur la roadmap, la fiche projet et la fiche personne (règle 12 du pitch).
- **`RoadmapWindow::spanning()`, `Roadmap::minTrackRem()`, `centerPosition()`, `months()`** : les bornes, le minimum de 4 semaines, le défilement et les libellés de mois de la fiche projet (règle 15). Une `Roadmap` sans ligne de projet porte la fenêtre et aujourd'hui.
- **`TimelineEntry`, `TimelineMonth`, `LeafOrder`** : l'ordre du journal et l'ordre des feuilles sont déjà définis ailleurs.
- **Voter** (`PersonVoter`, sur le modèle de `LotVoter` et de son `AccessDecisionManagerInterface`) : une règle d'accès par donnée sur une personne (la personne elle-même, lead, direction). Elle ne tient pas dans un `ROLE_*` ou dans `access_control`.
- **`{% embed %}` de `_frieze.html.twig`, `_bar.html.twig`, `_zoom.html.twig`, `_legend.html.twig` (paramétrée par `future_label`, `with_span` et `with_overload`), `_today.html.twig`, contrôleurs Stimulus `roadmap` (valeur `remember` à faux) et `roadmap-tooltip`** : même cadre, même zoom et mêmes infobulles que la fiche projet.
- **Résolveur d'entité par défaut sur `User`** et `isGranted()` dans le contrôleur : sans expression ni requête dédiée, la personne est chargée par son identifiant, son profil en lazy-load à nombre de requêtes constant.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| `RoadmapBuilder::buildPerson()` | `RoadmapBuilder` est à la limite de complexité cognitive de PHPStan (40) depuis la story 010, et la fiche personne ne partage ni ses lignes de projet ni ses récapitulatifs. |
| Réutiliser `RoadmapRow` et `_row.html.twig` | Il aurait fallu des drapeaux dans `_row`, `_tooltip` et `_recap` (pas d'équipe, pas de restant, une part) partagés par la roadmap et la fiche projet. Une ligne dédiée laisse ces partiels intacts. |
| Infobulles interactives pour rendre les noms cliquables | Un `role="tooltip"` ne doit pas contenir d'élément focusable, et l'infobulle se ferme dès qu'on quitte son déclencheur (story 009). Question ouverte du pitch tranchée en (b) : les noms ne sont des liens que dans la timeline de la fiche projet. |
| Masquer la charge dans le template seulement | La charge d'un collègue serait calculée pour tous, et une erreur de template suffirait à l'exposer. Le contrôleur ne la demande pas sans `PERSON_PLANNING`. |
| Ligne de charge en histogramme par jour ou en barres par semaine | Écartée au plan : un élément par jour sur plusieurs mois est illisible à ×1, et une moyenne hebdomadaire dilue une surcharge d'un jour. |
| Charge « d'aujourd'hui », à la lettre du pitch | Le planificateur ne charge jamais le jour courant : la partie à venir commence au plus tôt demain, et la charge d'aujourd'hui vaudrait toujours 0 %. Le pitch est annoté : charge du prochain jour ouvré. |
| Requête dédiée pour le temps par projet | Le total et le dernier jour saisi se déduisent des tronçons déjà chargés. |
| Route sous `/equipe` | `access_control` réserve `^/equipe` à la direction, alors que la fiche est ouverte à tous. |

## Modèle de données

Aucun impact modèle.

Seuls des modèles de vue non persistés sont créés (`PersonRoadmap`, `PersonProject`, `PersonLeafRow`, `PersonLoad`, `LoadSpan`) (voir Périmètre).

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Service/PersonRoadmapBuilder.php` | `build(User, bool $withOverloads, bool $withLoad): PersonRoadmap` : planifie toutes les feuilles, retient celles de la personne (saisies ou membre), découpe ses tronçons par `cutFor()`, borne la fenêtre, construit les lignes, « À venir », la timeline et, si demandé et si la personne est active, sa charge. |
| `src/Model/Person/PersonRoadmap.php` | La fiche : `roadmap` (une `Roadmap` sans ligne, qui porte la fenêtre et aujourd'hui), `projects` (`list<PersonProject>` dans l'ordre de `LeafOrder`), `upcoming` (`list<PersonLeafRow>` triée par début, les feuilles sans début à la fin), `timeline` (`list<TimelineMonth>`), `dated`, `load` (`?PersonLoad`). `enteredProjects()` rend les projets saisis du plus récent au plus ancien (règle 7). |
| `src/Model/Person/PersonProject.php` | Un projet de la personne : le `Project`, ses `PersonLeafRow`, le temps qu'elle y a saisi et son dernier jour saisi. |
| `src/Model/Person/PersonLeafRow.php` | Une feuille de la personne : `lot` (la feuille), `share` (null hors équipe), `start` et `end` (partie à venir et fin calculée de la feuille), `realized` et `overrun` (ses tronçons placés), `future` (la partie à venir si elle est membre), `signals`. `leafPath()` (« Lot · Sous-lot »), `isUpcoming(today)` : membre et feuille non terminée (fin inconnue ou après aujourd'hui). |
| `src/Model/Person/PersonLoad.php` | La charge : `spans` (`list<LoadSpan>`), `nextDay` et `nextDayPercent` (prochain jour ouvré après aujourd'hui), `freeFrom` (premier jour ouvré après son dernier jour chargé), `unknownEnds` (`list<PersonLeafRow>`, les lignes des feuilles dont elle est membre sans fin calculée, qui rendent `freeFrom` inconnue et sont nommées par `leafPath()`). Fabrique `of()` à partir des charges par jour, de `DailyCapacity`, d'aujourd'hui et de la fenêtre. |
| `src/Model/Person/LoadSpan.php` | Une plage de charge : premier et dernier jour, pourcentage, `RoadmapBar` placée, `isOverload()` au-delà de 100 %. |
| `src/Security/Voter/PersonVoter.php` | `PERSON_PLANNING` (rôle, tags, charge) : `ROLE_LEAD` ou la personne elle-même. `PERSON_MANAGER` : `ROLE_DIRECTION` ou la personne elle-même. Sujet : `User`. |
| `src/Controller/PersonController.php` | `show()` : route `app_person` `GET /personnes/{id}`, `User` par le résolveur d'entité par défaut, rend `person/show.html.twig` avec `build($person, isGranted('ROLE_LEAD'), isGranted(PERSON_PLANNING, $person))`. |
| `templates/person/show.html.twig` | En-tête (nom, « désactivée », profil selon le voter, temps par projet avec lien vers la fiche projet, charge ou « désactivée, aucune charge »), frise (zoom, légende par `_legend.html.twig`, `_frieze` embarquée : ligne de charge si `page.load`, puis projets et feuilles), « À venir », journal. Messages pour une fiche sans frise, sans affectation, sans saisie. |
| `templates/person/_row.html.twig` | Ligne d'une feuille : `leafPath` et signaux, barres `realized`, `overrun`, `future` (étiquetée de la part) via `_bar.html.twig` et `person/_tooltip.html.twig`. |
| `templates/person/_tooltip.html.twig` | Infobulle de la fiche : tronçon (feuille, période, saisi par la personne, jours avec saisie, au-delà de l'estimation), affectation (feuille, part, début, fin calculée), plage de charge (période, charge). |
| `templates/person/_upcoming.html.twig` | Bloc « À venir » : projet (lien) et feuille, début et fin calculée quand la feuille en a, part « de sa capacité » et signaux de la feuille ; message sans affectation. |
| `tests/Unit/Model/Person/PersonLoadTest.php` | Plages fusionnées par-dessus un week-end et coupées sur un jour sans charge, surcharge, prochain jour ouvré (week-end, jour férié), `freeFrom`, fin inconnue, aucune affectation. |
| `tests/Unit/Security/Voter/PersonVoterTest.php` | `PERSON_PLANNING` et `PERSON_MANAGER` pour la direction, un lead, un membre de prod sur sa fiche et sur celle d'un autre ; sujet non supporté. |
| `tests/Service/PersonRoadmapBuilderTest.php` | Exemple du pitch (Alice) : lignes regroupées par projet, seuls ses tronçons (ceux d'un collègue exclus), coupés sur ses jours ouvrés, tronçons au-delà de l'estimation, affectation à 50 % du 05/10 au 26/10, bornes du 07/09 au 01/11, « À venir » (feuille en dépassement et feuille sans début incluses), charge (50 % au 05/10, libre le 27/10, inconnue avec une feuille sans début), temps par projet, timeline, personne désactivée sans charge, personne sans rien (`dated` faux), charge absente sans `withLoad`. |
| `tests/Controller/PersonControllerTest.php` | Fiche d'Alice vue par la direction, un lead, Alice et un collègue de prod (rôle, tags, manager, charge, ligne de charge présents ou absents du HTML), « à replanifier » réservé aux leads, aucune action de gestion, liens vers la fiche projet, aucune entrée du menu active, personne désactivée, fiche vide, 404, nombre de requêtes constant. |
| `src/Model/Roadmap/LeafPath.php` | `of(Lot)` : « Lot · Sous-lot », ou le titre du lot ; partagé par `TimelineEntry` et `PersonLeafRow`. |
| `tests/e2e/person.spec.ts` | Nom dans la timeline d'une fiche projet → fiche de la personne ; infobulle d'un tronçon sur la frise de la fiche ; « Ma fiche » depuis le menu du compte. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Enum/Type/RoadmapSignal.php` | Ajouter `of(LeafSchedule): list<self>`, le corps de `RoadmapBuilder::signalsOf()`. |
| `src/Service/RoadmapBuilder.php` | `signalsOf()` remplacée par `RoadmapSignal::of()`. |
| `src/Model/Schedule/ScheduleResult.php` | Ajouter `loadsOf(int $userId): array<string, int>`, la charge de la personne par jour, dans l'ordre des jours. |
| `src/Repository/TimeEntryRepository.php` | Ajouter `sumQuartersByLotAndDayForUser(int $userId): array<int, array<string, int>>`, ses quarts par feuille et par jour, dans l'ordre des jours. |
| `src/Service/RoadmapRunCutter.php` | Ajouter `cutFor(ScheduleData, int $userId)` : ses tronçons par feuille, dans puis au-delà de l'estimation de la feuille, coupés sur ses seuls jours ouvrés ; `runs()` et `run()` partagés avec `cut()`, ainsi que `split()`, extraite, qui partage les jours entre l'estimation et le dépassement. |
| `src/Model/Roadmap/TimelineEntry.php` | `leafPath()` délègue à `LeafPath::of()`. |
| `templates/roadmap/_bar.html.twig` | Variable `tooltip_template`, `roadmap/_tooltip.html.twig` par défaut. |
| `templates/roadmap/_legend.html.twig` | Variables `future_label` (« Restant, calculé » par défaut), `with_span` (vrai par défaut) et `with_overload` (faux par défaut). |
| `templates/roadmap/_timeline.html.twig` | Variables `with_team` (vrai par défaut), `with_project` (faux par défaut : à vrai, le titre du projet, lien vers sa fiche, précède la feuille) et `empty_message` (message de la fiche projet par défaut). Les entrées de la fiche projet passent `links: true` à `_team.html.twig`. |
| `templates/roadmap/_team.html.twig` | Variable `links` (faux par défaut) : à vrai, le nom est un lien vers `app_person`, barré s'il est désactivé, `data-test="person-link"`. |
| `templates/base.html.twig` | Entrée « Ma fiche » (`tabler:file-description`, `data-test="nav-person"`) dans le menu du compte, vers `app_person` de l'utilisateur connecté. |
| `templates/team/index.html.twig` | Bouton-icône `tabler:file-description` vers `app_person` dans les actions de chaque ligne (`data-test="member-person"`). |
| `tests/Controller/RoadmapProjectControllerTest.php` | Les noms de la timeline, « hors équipe » compris, mènent à `app_person` ; aucun lien dans les infobulles de la frise. |
| `tests/Controller/TeamControllerTest.php` | Chaque ligne porte le lien vers la fiche de la personne. |
| `tests/Controller/NavigationTest.php` | « Ma fiche » mène à la fiche de l'utilisateur connecté (test dédié `testTheAccountMenuLeadsToMyPage()`) ; aucune entrée active sur `app_person`. |
| `docs/story/011-f-fiche-collaborateur/pitch.md` | Annotations du plan : règles 1, 8 et 16, critère de la charge, questions ouvertes « infobulles » et « téléphone » tranchées. |

## Hors scope

- **Infobulles de la roadmap et de la frise** : inchangées, sans lien (question ouverte du pitch tranchée en (b)).
- **Mise en cache de la planification** : `build()` planifie toutes les feuilles à chaque affichage, comme la roadmap et la fiche projet.
- **Équipe des tronçons de la personne** : `cutFor()` construit la `RoadmapRun` comme `cut()`, équipe comprise, mais la fiche ne l'affiche pas. Pas de variante de `run()` sans équipe.
- **Récapitulatif d'une feuille au survol de son titre** (`_recap.html.twig`) : absent de la fiche personne, qui ne montre pas la feuille entière.
- **Données de démonstration** : les fixtures contiennent déjà des personnes affectées et des temps saisis ; aucune donnée ajoutée.
- **Mise en service** (information des salariés, CSE) : question ouverte du pitch, hors du code.

## Impacts transverses

- **Cloisonnement des données** : pas de multi-organisation. Ce que chacun voit d'une personne est porté par `PersonVoter` (`PERSON_PLANNING`, `PERSON_MANAGER`). Côté principe 2, la contradiction est assumée au pitch.
- **Déclinaisons / thèmes** : non.
- **Traduction / i18n** : libellés en français dans les templates, comme le reste de l'application.
- **API / exposition externe** : non.
- **Droits d'accès** : la route vit sous `/personnes`, ouverte à `ROLE_USER` par l'`access_control` existant. Le rôle, les tags et la charge passent par `PERSON_PLANNING`, et le manager par `PERSON_MANAGER`. La charge n'est calculée qu'avec `PERSON_PLANNING`. « À replanifier » reste réservé aux leads et à la direction par `withOverloads`.
- **Emails / notifications** : non.
- **Migration de données** : aucune.
- **Comportement par défaut** : la roadmap et ses infobulles sont inchangées. Sur la fiche projet, les noms de la timeline deviennent des liens. La liste Équipe gagne une icône par ligne, et le menu du compte gagne « Ma fiche ».

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/Person/PersonLoad.php` | unit | Fusion des plages de même charge par-dessus un week-end, coupure sur un jour ouvré sans charge, surcharge au-delà de 100 %, charge du prochain jour ouvré (après un week-end, après un jour férié de son calendrier), `freeFrom` au premier jour ouvré après le dernier jour chargé, inconnue avec ses feuilles, aucune affectation. |
| `src/Security/Voter/PersonVoter.php` | unit | `PERSON_PLANNING` : vrai pour la direction, un lead, la personne elle-même ; faux pour un membre de prod sur un autre. `PERSON_MANAGER` : vrai pour la direction et la personne elle-même ; faux pour un lead. Abstention hors attributs et hors sujet `User`. |
| `src/Service/PersonRoadmapBuilder.php` | functional (noyau) | Exemple du pitch ; tronçons coupés sur ses jours ouvrés et non sur ceux de l'équipe ; tronçons au-delà de l'estimation de la feuille ; feuille saisie hors équipe sans partie à venir ; « À venir » trié, feuille en dépassement et feuille sans début incluses, feuille à estimation atteinte exclue ; personne désactivée sans charge ; personne sans saisie ni affectation (`dated` faux, listes vides) ; `withLoad` faux → `load` null. |
| `src/Service/RoadmapRunCutter.php` (`cutFor()`) | functional | Couvert par `PersonRoadmapBuilderTest`. `cut()` reste couvert par `RoadmapBuilderTest`, inchangé et vert. |
| `src/Enum/Type/RoadmapSignal.php` (`of()`) | functional | Couvert par `RoadmapBuilderTest` (signaux de la roadmap inchangés) et `PersonRoadmapBuilderTest`. |
| `src/Controller/PersonController.php` | functional | Visibilité par rôle sur le HTML (`data-test` du profil, de la charge et de la ligne de charge présents ou absents), lecture seule, temps par projet dans l'ordre, journal de l'exemple sous « Septembre 2026 », « À venir », liens vers la fiche projet, aucune entrée de menu active, personne désactivée, fiche vide, 404, nombre de requêtes indépendant du nombre de feuilles. |
| `templates/roadmap/_timeline.html.twig`, `_team.html.twig` | functional | Liens des noms dans la timeline de la fiche projet, aucun lien dans ses infobulles (`RoadmapProjectControllerTest`). |
| `templates/base.html.twig`, `templates/team/index.html.twig` | functional | « Ma fiche » (`NavigationTest`) et icône de la liste Équipe (`TeamControllerTest`). |
| Parcours fiche projet → fiche personne | E2E | Clic sur un nom de la timeline, infobulle d'un tronçon de la frise de la personne, « Ma fiche » depuis le menu du compte. |

**Hors scope tests** :

- Pas de test E2E de la visibilité par rôle : elle est portée par le voter et vérifiée sur le HTML en fonctionnel, qui est plus rapide et plus exhaustif.
- Pas de test E2E du zoom de la fiche : il réutilise `roadmap_controller.js` avec `remember` à faux, déjà couvert par la story 010.
- Pas de test du tactile, comme dans les stories 009 et 010.

## Ordre d'exécution

1. [x] **Socle partagé de la planification**
   - Objectif : `RoadmapSignal::of()` extraite de `RoadmapBuilder`, `ScheduleResult::loadsOf()`, `TimeEntryRepository::sumQuartersByLotAndDayForUser()`, `RoadmapRunCutter::cutFor()`.
   - Fichiers : `RoadmapSignal.php`, `RoadmapBuilder.php`, `ScheduleResult.php`, `TimeEntryRepository.php`, `RoadmapRunCutter.php`.
   - Vérification : `make phpunit-filter RoadmapBuilderTest` et `make phpunit-filter RoadmapProjectControllerTest` verts, sans modification des tests.
   - Commitable seule : oui.

2. [x] **Construction de la fiche et de la charge**
   - Objectif : `PersonRoadmapBuilder`, modèles `PersonRoadmap`, `PersonProject`, `PersonLeafRow`, `PersonLoad`, `LoadSpan`.
   - Fichiers : le service, les cinq modèles, `LeafPath.php`, `TimelineEntry.php`, `PersonLoadTest.php`, `PersonRoadmapBuilderTest.php`.
   - Vérification : `make phpunit-filter PersonLoadTest` et `make phpunit-filter PersonRoadmapBuilderTest` verts.
   - Commitable seule : oui.

3. [x] **Voter, route et page**
   - Objectif : `PersonVoter`, `PersonController`, `person/show.html.twig`, `_row`, `_tooltip`, `_upcoming`, variable `tooltip_template` de `_bar`, variables de `_timeline`.
   - Fichiers : `PersonVoter.php`, `PersonController.php`, templates de `templates/person/`, `_bar.html.twig`, `_timeline.html.twig`, `_legend.html.twig` (paramétrée en review), `PersonVoterTest.php`, `PersonControllerTest.php`.
   - Vérification : `make phpunit-filter PersonVoterTest`, `make phpunit-filter PersonControllerTest` et `make phpunit-filter RoadmapProjectControllerTest` verts ; fiche relue dans le navigateur sur une personne des fixtures, avec les comptes `admin@`, `lead@` et `prod@`.
   - Commitable seule : oui.

4. [x] **Entrées vers la fiche**
   - Objectif : noms en lien dans la timeline de la fiche projet, « Ma fiche » dans le menu du compte, icône dans la liste Équipe.
   - Fichiers : `_team.html.twig`, `_timeline.html.twig`, `base.html.twig`, `team/index.html.twig`, `RoadmapProjectControllerTest.php`, `TeamControllerTest.php`, `NavigationTest.php`.
   - Vérification : `make phpunit-filter RoadmapProjectControllerTest`, `make phpunit-filter TeamControllerTest` et `make phpunit-filter NavigationTest` verts.
   - Commitable seule : oui.

5. [x] **Parcours E2E**
   - Objectif : scénarios de `person.spec.ts`.
   - Fichiers : `tests/e2e/person.spec.ts`.
   - Vérification : `make playwright-file tests/e2e/person.spec.ts` vert (serveur lancé par `make serve`).
   - Commitable seule : oui.

6. [x] **QA finale**
   - Objectif : suite complète et qualité.
   - Fichiers : —
   - Vérification : `make lint`, `make phpunit`, `make playwright`.
   - Commitable seule : —

## Critères de sortie

- [x] `GET /personnes/{id}` répond 200 aux trois rôles, 404 pour une personne inconnue, et n'allume aucune entrée du menu.
- [x] Le HTML de la fiche d'un collègue, vue par un membre de prod, ne contient ni son rôle, ni ses tags, ni son manager, ni sa charge, ni sa ligne de charge ; celui de la fiche vue par un lead ne contient pas son manager.
- [x] `RoadmapBuilderTest` et `RoadmapProjectControllerTest` passent sans modification de leurs assertions existantes après l'extraction de `RoadmapSignal::of()` et le paramétrage de `_bar` et `_timeline`.
- [x] Le nombre de requêtes de la fiche ne dépend pas du nombre de feuilles de la personne.
- [x] `make phpunit` et `make playwright` verts, sans nouvelle régression.
- [x] `make lint` propre (PHP-CS-Fixer, PHPStan level 10).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Une donnée réservée (charge, tags, manager) fuite dans le HTML d'un membre de prod | moyenne | Charge non calculée sans `PERSON_PLANNING` ; profil conditionné par le voter dans le template ; critère de sortie et tests fonctionnels sur l'absence des `data-test` et des valeurs. |
| Le paramétrage de `_bar.html.twig` ou de `_timeline.html.twig` change la roadmap ou la fiche projet | faible | Valeurs par défaut identiques au rendu actuel ; tests existants verts sans modification de leurs assertions (critère de sortie). |
| « Libre à partir du … » reste inconnu tant qu'une ancienne feuille en dépassement garde la personne dans son équipe, la clôture n'existant pas encore | moyenne | Comportement voulu par le pitch (règle 8) ; la feuille en cause est nommée, et un lead peut retirer la personne de l'équipe. À reprendre avec `jalons-dates-annoncees`. |
| Planifier toutes les feuilles à chaque affichage de fiche | faible | Même coût que la roadmap et la fiche projet ; une seule requête ajoutée (`sumQuartersByLotAndDayForUser()`). |
| Les tronçons d'une personne coupés sur ses seuls jours ouvrés diffèrent de ceux de la feuille sur la roadmap | faible | Voulu par la règle 11 ; test dédié dans `PersonRoadmapBuilderTest`. |

## Questions ouvertes

- **Icône de « Ma fiche » et de la liste Équipe** : `tabler:id` ou `tabler:user-search` ? À trancher à l'implémentation, selon le rendu à côté des actions existantes. → tranché : `tabler:file-description`, l'icône « Ouvrir la fiche » de la roadmap, pour une même métaphore de fiche.
- **Libellé de la ligne de charge dans la colonne des titres** : « Charge » seul, ou « Charge (part des affectations) » ? À trancher au rendu. → tranché : « Charge » seul ; l'infobulle et la légende précisent la période et la surcharge.
