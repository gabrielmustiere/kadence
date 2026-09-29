# Report — Saisir ses temps de la semaine en quarts de journée, d'un clic par case

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/003-f-saisie-quotidienne/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 97 % des fichiers prévus sont livrés tels que prévus (60 / 62). Trois écarts sont structurants. La révision bornée ne contrôle qu'un changement d'estimation. Des fixtures de démonstration, chargées seulement en dev, remplacent celles prévues. `TimesheetBuilder` est testé contre la base.
- **Critères** : 26 / 26 cochés.
- **Review** : 1 bloquant, 1 important et 6 mineurs, tous corrigés. Statut : PRÊT À COMMITER.
- **Périmètre livré** : 37 fichiers créés (2 980 lignes) et 35 modifiés (+784 / −65 lignes), hors `docs/`.

La grille de la semaine est livrée, en Live Component, avec les crans au quart de journée, les plafonds par jour et par semaine, les signaux de jour complet et d'oubli, et la vue d'un jour sur téléphone. Le maximum hebdomadaire est historisé, et les garde-fous sur le référentiel sont en place. Il reste à réaligner la règle 25 du pitch (la borne ne s'applique qu'à une révision) et le backlog (passage au quart de journée, maximum hebdomadaire avancé).

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Entity/TimeEntry.php` | Une case saisie : personne, feuille, jour, quarts (1 à 4). Unicité (personne, feuille, jour), index (personne, jour). | Oui |
| `src/Entity/WeeklyMax.php` | Une valeur datée du maximum hebdomadaire d'une personne : lundi d'effet, quarts (1 à 20). | Oui |
| `src/Repository/TimeEntryRepository.php` | `findForUserBetween()` avec jointures chargées, `sumQuartersByLot()`, `sumQuartersForLotId()`, `existsForLots()`, `existsForProject()`, `moveToLot()` (UPDATE DQL). | Oui |
| `src/Repository/WeeklyMaxRepository.php` | `findInEffectAt()`, `findOneAt()`, `findForUser()` (du plus ancien au plus récent). | Oui |
| `src/Model/Week.php` | Semaine ISO : `containing()`, `fromIso()`, `iso()`, `previous()`, `next()`, `friday()`, `days()`, `contains()`, `equals()`. | Oui |
| `src/Model/Timesheet/WeekGrid.php` | Modèle de lecture de la grille : semaine, jours, lignes, total et maximum de la semaine. | Oui |
| `src/Model/Timesheet/TimesheetDay.php` | Un jour : date, total, aujourd'hui, oubli, complet (sans champ « futur », cf. §Écarts). | Écart volontaire (cf. §Écarts) |
| `src/Model/Timesheet/TimesheetRow.php` | Une ligne : feuille et cases. Le libellé est rendu par un partial. | Écart volontaire (cf. §Écarts) |
| `src/Model/Timesheet/TimesheetCell.php` | Une case : quarts saisis, plus grand cran sélectionnable, verrouillée (futur). | Oui |
| `src/Service/TimesheetManager.php` | `record()` : quarts entre 0 et 4, feuille, jour ouvré, pas de futur. Plafonds du jour et de la semaine contrôlés seulement à la hausse. Création, mise à jour ou suppression de la saisie, gel de l'estimation initiale. | Oui |
| `src/Service/TimesheetBuilder.php` | Construit `WeekGrid` : lignes de la semaine affichée et de la précédente, lignes ajoutées, tri, crans sélectionnables, verrous, totaux, oubli. | Oui |
| `src/Service/WeeklyMaxManager.php` | `quartersFor(User, Week)` (20 par défaut), `change(User, quarters, Week)`, `delete()`. | Écart volontaire (cf. §Écarts) |
| `src/Service/LeafFinder.php` | `search()` filtré en PHP, sans accents ni casse, tous les termes requis, 10 résultats au plus ; `isSearchable()`. | Oui |
| `src/Exception/TimeEntryRefusedException.php` | Refus d'une saisie, avec son message : quarts invalides, lot découpé, week-end, futur, jour complet, semaine complète. | Oui |
| `src/Exception/LotHasTimeEntriesException.php` | Refus de supprimer un projet ou un lot qui porte des temps. | Oui |
| `src/Validator/EstimateCoversConsumed.php` | Contrainte de classe sur `LotInput`. | Oui |
| `src/Validator/EstimateCoversConsumedValidator.php` | Une révision ne descend pas sous le consommé arrondi au jour supérieur, et le retrait d'une estimation est refusé. Rien n'est contrôlé si l'estimation est inchangée. | Écart volontaire (cf. §Écarts) |
| `src/Twig/Components/Timesheet.php` | Live Component : props `week`, `addedLotIds`, `query` et `focusedDay` ; actions `record`, `addLot` et `showDay`. Il expose la grille calculée, les résultats de recherche et le message de refus. | Oui |
| `src/Twig/DaysExtension.php` | Filtres `days` (« 3,75 j »), `quarter_fraction` (« ¾ ») et `weekday` (« Lun »). | Oui |
| `src/Controller/TimesheetController.php` | `GET /saisie` et `GET /saisie/{week}`, avec une 404 pour une semaine invalide. | Oui |
| `templates/timesheet/index.html.twig` | Page : titre, libellé de la semaine, navigation entre semaines, montage du composant. | Oui |
| `templates/components/Timesheet.html.twig` | Grille : total face au maximum, onglets des jours sur mobile, colonnes (vert, aujourd'hui, oubli), lignes, « Ajouter une ligne », message de refus. | Oui |
| `templates/timesheet/_quarter_bar.html.twig` | Barre de 4 crans (boutons, `aria-pressed`, `aria-label` avec le chemin complet, verrous, cran actif à 0). | Oui |
| `migrations/Version20260929201824.php` | Migration générée : tables `time_entry` et `weekly_max`, colonne `lot.initial_estimate_days`. | Oui |
| `fixtures/DemoCompanyFixtures.php` | Fausse entreprise, en dev seulement : 14 personnes dont des temps partiels, 4 projets découpés, 26 semaines de saisie jusqu'à hier. | Écart volontaire (cf. §Écarts) |
| `tests/Support/CreatesTimeEntries.php` | Trait de test : créer une saisie ou un maximum. | Oui |
| `tests/Unit/Model/WeekTest.php` | Lundi, aller-retour ISO, changement d'année, jours ouvrés, appartenance, semaines invalides. | Oui |
| `tests/Service/TimesheetBuilderTest.php` | Lignes et tri, crans limités par le jour et par la semaine, jamais sous la valeur actuelle, verrous du futur, oubli. | Écart volontaire (cf. §Écarts) |
| `tests/Unit/Service/LeafFinderTest.php` | Accents et casse repliés sur les trois niveaux, requête trop courte, limite et tri. | Oui |
| `tests/Service/TimesheetManagerTest.php` | Création, modification, remise à 0 ; plafonds du jour et de la semaine ; diminution au-dessus d'un maximum abaissé ; cas refusés ; estimation initiale figée. | Oui |
| `tests/Service/WeeklyMaxManagerTest.php` | 5 j par défaut, valeur en vigueur selon la semaine, écriture seulement si la valeur change, remplacement à même date, suppression. | Oui |
| `tests/Twig/Components/TimesheetTest.php` | Rendu et signaux, `record` et re-clic, crans limités, refus avec message, lot découpé depuis l'affichage, ligne ajoutée, recherche, `showDay`, temps d'autrui, jour hors de la semaine. | Oui |
| `tests/Controller/TimesheetControllerTest.php` | Arrivée sur `/saisie`, navigation, 404, accès, raccourci du tableau de bord, budget de requêtes. | Oui |
| `tests/e2e/timesheet.spec.ts` | 9 scénarios : lignes proposées, journée en deux clics, survol et re-clic, crans verrouillés, ligne ajoutée, navigation, mobile, suppression bloquée, maximum hebdomadaire. | Oui |
| `src/Model/Quarters.php` | Conversion des quarts : jours (« 3,75 j »), fraction, arrondi au jour supérieur. | Non (ajout — cf. §Écarts) |
| `src/Model/Timesheet/LeafOrder.php` | Ordre des feuilles : projet (titre replié), puis lot, puis sous-lot. | Non (ajout — cf. §Écarts) |
| `templates/timesheet/_leaf_path.html.twig` | Libellé « Projet › Lot › Sous-lot » d'une feuille. | Non (ajout — cf. §Écarts) |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Entity/Lot.php` | Ajout de `initialEstimateDays` (colonne `initial_estimate_days`, nullable), avec son getter et son setter. | Oui |
| `src/Repository/LotRepository.php` | Ajout de `findLeavesWithAncestors()` et `findLeavesByIds()` sur un QueryBuilder privé commun. | Oui |
| `src/Service/ProjectManager.php` | Injection de `TimeEntryRepository`. Suppressions refusées s'il y a du temps. Bascule vers le premier sous-lot en transaction, avec les temps et l'estimation initiale. Remontée de l'estimation initiale. Gel à la première estimation d'une feuille qui a du temps. | Oui |
| `src/Service/ProjectRollup.php` | `summarize(Project, $quartersByLot)` calcule `hasTime` par feuille, lot découpé et projet. | Oui |
| `src/Model/LotSummary.php`, `src/Model/ProjectSummary.php` | Ajout de `hasTime`. | Oui |
| `src/Controller/ProjectController.php` | `show()` passe le consommé par lot au rollup. `delete()` intercepte `LotHasTimeEntriesException` et affiche un flash d'erreur. | Oui |
| `src/Controller/LotController.php` | `delete()` intercepte `LotHasTimeEntriesException`. `edit()` passe le consommé et `minimum_estimate_days`. | Oui |
| `src/Dto/LotInput.php` | Contrainte `#[EstimateCoversConsumed]` et champ `currentEstimateDays`. | Écart volontaire (cf. §Écarts) |
| `src/Dto/TeamMemberInput.php` | `weeklyMaxDays` et `weeklyMaxFrom` avec leurs contraintes. `forNewMember(Week)`, et `fromUser()` reçoit le maximum en vigueur et la semaine en cours. | Oui |
| `src/Form/TeamMemberType.php` | `weeklyMaxDays` (`NumberType` html5, pas de 0,25) et `weeklyMaxFrom` (`DateType` `single_text`). | Oui |
| `src/Service/TeamManager.php` | `apply()` passe le maximum à `WeeklyMaxManager::change()`, converti en quarts, pour la semaine de la date d'effet. | Oui |
| `src/Controller/TeamController.php` | Pré-remplissage du maximum en vigueur, historique passé au template. Route `POST /equipe/{id}/maximum/{weeklyMaxId}/supprimer` (CSRF, entrée appartenant à la personne). | Oui |
| `templates/team/_form.html.twig` | Les deux champs du maximum, côte à côte. | Oui |
| `templates/team/edit.html.twig` | Historique du maximum : « 5 j par défaut », puis chaque valeur avec un bouton de suppression. | Oui |
| `templates/project/_lot_row.html.twig` | Affichage « initiale X j ». La modale de suppression devient « lot-delete-blocked », sans bouton de confirmation, si `hasTime`. | Oui |
| `templates/project/show.html.twig` | Même adaptation pour la modale de suppression du projet (`project-delete-blocked`). | Oui |
| `templates/lot/edit.html.twig` | Estimation initiale, consommé et minimum d'une révision, affichés quand la feuille a du temps. | Oui |
| `templates/base.html.twig` | Entrée « Ma semaine » dans le menu (`nav-timesheet`). | Oui |
| `templates/page/index.html.twig` | Raccourci « Ma semaine » en tête du tableau de bord (`home-timesheet`). | Oui |
| `config/packages/security.yaml` | `default_target_path: app_timesheet`. | Oui |
| `src/Controller/AccountController.php` | Redirection vers `app_timesheet` après le changement de mot de passe. | Oui |
| `assets/styles/app.css` | `@layer components` pour `.quarter-bar` et `.quarter` : remplissage au survol, vidage des crans suivants, aperçu « effacer », hachures des crans verrouillés, focus visible. | Oui |
| `tests/Unit/Service/ProjectManagerTest.php` | Stub du repository. Bascule avec temps (avec ou sans estimation initiale), bascule sans temps, remontée de l'estimation initiale, suppressions refusées. | Oui |
| `tests/Unit/Service/ProjectRollupTest.php` | `hasTime` d'une feuille, du lot découpé et du projet. | Oui |
| `tests/Controller/LotControllerTest.php` | Révision bornée, feuille en dépassement modifiable sans révision, première estimation devenue initiale, premier sous-lot (reprise des temps et estimation sous le consommé refusée), suppression refusée. | Oui |
| `tests/Controller/ProjectControllerTest.php` | Suppression refusée d'un projet qui porte des temps. | Oui |
| `tests/Controller/TeamControllerTest.php` | Maximum fixé à partir d'une semaine, valeurs invalides, suppression d'une entrée, entrée d'une autre personne. | Oui |
| `src/Command/CreateDirectorCommand.php` | Horloge injectée, `TeamMemberInput::forNewMember()`. | Non (ajout — cf. §Écarts) |
| `fixtures/ProjectFixtures.php` | Référence `SUPPORT` sur le lot « Support ». | Non (ajout — cf. §Écarts) |
| `phpstan.dist.neon` | `fixtures/` ajouté aux chemins analysés et aux exemptions de complexité cognitive. | Non (ajout — cf. §Écarts) |
| `tests/Controller/AccountControllerTest.php` | Redirection attendue vers `/saisie`. | Non (ajout — cf. §Écarts) |
| `tests/Controller/SecurityControllerTest.php` | Redirection attendue vers `/saisie`, et connexion avec un utilisateur créé. | Non (ajout — cf. §Écarts) |
| `tests/Unit/Service/TeamManagerTest.php` | Nouvelle dépendance `WeeklyMaxManager`, inscription d'une personne à temps partiel. | Non (ajout — cf. §Écarts) |
| `tests/e2e/team.spec.ts` | Titre attendu « Ma semaine » après connexion. | Non (ajout — cf. §Écarts) |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| `EstimateCoversConsumed` impose une estimation au moins égale au consommé arrondi au jour supérieur, et non vide dès qu'il y a du consommé. | Seule une révision est contrôlée. Aucune vérification si l'estimation est inchangée (`estimateDays === currentEstimateDays`). Le retrait reste refusé. | Review, **[BUG] Une feuille dont le consommé dépasse l'estimation ne peut plus être modifiée sans relever son estimation**. Arbitrage : un dépassement est normal et fréquent (règle 16). La règle 25 du pitch est à préciser en conséquence. |
| `fixtures/TimeEntryFixtures.php` : deux semaines et demie de temps pour Paula et Louis, et un maximum de 4,5 j pour Louis. | `fixtures/DemoCompanyFixtures.php`, en dev seulement : 14 personnes, 4 projets découpés, 26 semaines de saisie, temps partiels (80 %, 90 %, un passage en cours de route, une arrivée récente). Aucune saisie sur la semaine en cours pour les comptes de test. | Demande explicite au lancement de l'implémentation : une fausse entreprise avec 4 projets, lots et sous-lots, collaborateurs, et 6 mois de saisie. Ces fixtures sont limitées au dev pour garder les tests déterministes et laisser la semaine en cours vide pour les E2E. |
| Fixtures de démonstration validées par leur chargement dans `make phpunit` (hors scope tests). | `fixtures/` est analysé par PHPStan niveau 10, et la complexité cognitive n'y est pas appliquée, comme pour Entity, FormType et Repository. | Review, **[TEST] Aucune vérification automatique des fixtures de démonstration**. L'exemption de complexité est un choix délibéré : il s'agit de code de données. |
| `tests/Unit/Service/TimesheetBuilderTest.php` (unitaire). | `tests/Service/TimesheetBuilderTest.php` (fonctionnel, contre la base). | Le tri des lignes repose sur des ids persistés. |
| Budget de requêtes vérifié dans `TimesheetTest`. | Vérifié dans `TimesheetControllerTest` : 6 requêtes au plus, et autant pour une petite que pour une grande grille. | Le compte passe par le profiler d'une requête HTTP complète (`DoctrineDataCollector`). |
| L'E2E vérifie l'état des crans (`data-filled`, `disabled`), pas les pixels. | Le survol est vérifié en comparant entre elles les couleurs calculées des crans (`expect.poll`), sans valeur exacte. | Le survol est en CSS pur : aucun attribut ne change quand on survole un cran. |
| LiveProp `day` (jour affiché sur mobile). | LiveProp `focusedDay`. | Un nom distinct de l'argument `day` de la LiveAction `record()`. |
| `Week::current()` avec `ClockInterface`. | `Week::containing($clock->now())` chez les appelants. | `Week` reste un objet valeur sans dépendance, et l'horloge est injectée dans les services et les contrôleurs. |
| `WeeklyMaxManager::change(User, quarters, lundi)`. | `change(User, quarters, Week)`. | Le lundi d'effet est porté par `Week`. |
| `TimesheetRow` porte le libellé « Projet › Lot › Sous-lot ». | Le libellé est rendu par `templates/timesheet/_leaf_path.html.twig`. | Il est partagé par la grille et les résultats de recherche. |
| `TimesheetDay` : date, total, complet, aujourd'hui, futur, oubli. | Plus de champ « futur ». Le verrouillage est porté par `TimesheetCell::$locked`. | Review, **[STYLE] Code mort dans le modèle de grille**. |
| `aria-label` d'un cran : « ¾ j, mardi 29/09, Kadence › Saisie ». | « ¾ j — Mar 29/09, Projet › Lot › Sous-lot », et « Effacer — … » sur le cran actif. | Jour abrégé partagé avec les en-têtes (filtre `weekday`). Le chemin complet a été ajouté par la review, **[A11Y] Le libellé d'un cran ne nomme que la feuille, pas son chemin**. |
| Contrôle visuel du rendu à l'étape 6. | Contrôle visuel fait à l'étape 8, sur les données de démonstration (ordinateur et 390 px). | Toutes les étapes ont été enchaînées à la demande, et les données de démonstration donnaient une grille réaliste. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `src/Model/Quarters.php` | Une seule conversion des quarts, partagée par l'extension Twig, les messages de refus, le validateur et `LotController`. `daysRoundedUp()` a été ajoutée par la review, **[ARCHI] Le minimum imposé par le consommé est recalculé dans le template**. |
| `src/Model/Timesheet/LeafOrder.php` | Un seul ordre des feuilles pour la grille et la recherche. |
| Filtre Twig `weekday` et partial `_leaf_path.html.twig` | Libellés de jour et chemin de feuille réutilisés par la grille, les onglets mobiles, les crans et la recherche. |
| `LotInput::currentEstimateDays` | Distinguer le retrait d'une estimation d'une feuille qui reste « à estimer ». Depuis la review, il sert aussi à ne contrôler qu'une révision. |
| `LeafFinder::isSearchable()` | Review, **[ARCHI] La longueur minimale de recherche est mesurée à deux endroits, avec deux règles différentes**. |
| `TeamMemberInput::forNewMember()` et adaptation de `CreateDirectorCommand` | La commande échouait à la validation, faute de semaine d'effet du maximum. |
| Référence `ProjectFixtures::SUPPORT` | Les fixtures de démonstration versent une partie des temps sur le lot « Support ». |
| Mise à jour de `AccountControllerTest`, `SecurityControllerTest` et `team.spec.ts` | L'arrivée après connexion est désormais `/saisie`. Le test de connexion utilise un utilisateur créé, pour échapper à la limitation des tentatives de connexion. |
| Mise à jour de `TeamManagerTest` | Nouvelle dépendance `WeeklyMaxManager`, et inscription d'une personne à temps partiel. |
| Scénarios E2E « suppression bloquée » et « maximum hebdomadaire » | Couverture de bout en bout des modales adaptées et de la fiche Équipe. |

## Tests

Résultats consignés en review : 167 tests PHPUnit, 18/18 E2E, `make lint` propre. Rien n'a été relancé pour ce report.

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Model/Week.php` | unit | unit, 6 tests (dont un data provider de semaines invalides) | Fait |
| `src/Service/TimesheetBuilder.php` | unit | functional (base), 4 tests | Fait — type changé (cf. §Écarts) |
| `src/Service/LeafFinder.php` | unit | unit, 3 tests | Fait |
| `src/Service/TimesheetManager.php` | functional (base) | functional, 6 tests (cas refusés en data provider) | Fait |
| `src/Service/WeeklyMaxManager.php` | functional (base) | functional, 4 tests | Fait |
| `src/Service/ProjectManager.php` | unit | unit, 6 nouveaux tests. Le gel de l'estimation à la première estimation est couvert par `LotControllerTest`. | Fait |
| `src/Service/ProjectRollup.php` | unit | unit, 1 nouveau test | Fait |
| `src/Validator/EstimateCoversConsumedValidator.php` | functional (via `LotController`) | functional, 4 tests (dont la feuille en dépassement modifiable sans révision, ajouté en review) | Fait — couverture étendue |
| `src/Twig/Components/Timesheet.php` | functional | functional, 10 tests (dont le lot découpé depuis l'affichage, ajouté en review). Le budget de requêtes est vérifié dans le contrôleur. | Fait — couverture étendue |
| `src/Controller/TimesheetController.php` | functional | functional, 6 tests (dont le budget de requêtes) | Fait |
| `src/Controller/TeamController.php` | functional | functional, 4 nouveaux tests ; l'accès réservé à la direction est couvert par les tests existants | Fait |
| `src/Controller/LotController.php`, `src/Controller/ProjectController.php` | functional | functional, 6 + 1 nouveaux tests | Fait |
| `src/Service/TeamManager.php` | non prévu | unit, 1 nouveau test (maximum à temps partiel) | Fait — ajout |
| `tests/e2e/timesheet.spec.ts` | E2E | E2E, 9 scénarios | Fait — couverture étendue |
| Rendu visuel du survol (couleurs exactes) | hors scope assumé | couleurs comparées entre elles, sans valeur exacte | Conforme (hors scope assumé) |
| Course de deux enregistrements simultanés | hors scope assumé | pas écrit ; l'onglet périmé est couvert au niveau du composant | Conforme (hors scope assumé) |
| Chronométrage « moins d'une minute » | hors scope assumé (nombre de clics) | deux clics dans l'E2E, plus un garde-fou de 60 s | Conforme (hors scope assumé) |
| Fixtures de démonstration | hors scope assumé (chargées par `make phpunit`) | non chargées en test, analysées par PHPStan | Conforme — vérification par PHPStan (cf. §Écarts) |
| Saisie sur un jour d'une semaine passée | non prévu explicitement | **non couvert** directement | Manque mineur — risque faible : `TimesheetManager` n'a pas de limite d'ancienneté, et le builder ne verrouille que les jours futurs |
| Saisie au-delà de l'estimation d'une feuille | non prévu explicitement | **non couvert** directement (« à estimer » est couvert) | Manque mineur — risque faible : `TimesheetManager` ne lit pas l'estimation |

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Après connexion, toute personne active arrive sur la grille de sa semaine en cours, du lundi au vendredi, avec la colonne du jour mise en évidence.
- [x] La grille propose d'office les feuilles sur lesquelles la personne a saisi la semaine affichée ou la précédente, intitulées « Projet › Lot › Sous-lot », et aucune autre.
- [x] « Ajouter une ligne » retrouve une feuille par le titre de son projet, de son lot ou de son sous-lot ; un lot découpé et un projet sans lot n'y sont pas proposés.
- [x] Une ligne ajoutée sans aucun temps n'est plus affichée après rechargement de la semaine.
- [x] Au survol du n-ième cran d'une case, les n premiers crans se remplissent.
- [x] Un clic fixe la case à ¼, ½, ¾ ou 1 j sans bouton de validation, et la valeur est conservée après rechargement.
- [x] Un clic sur le cran actif d'une case la remet à 0 ; un clic sur un autre cran la fixe directement à la nouvelle valeur.
- [x] Quand ¾ j sont déjà saisis sur un jour, une autre case de ce jour n'offre que son premier cran, les trois autres étant grisés et inactifs.
- [x] Quand la semaine a atteint le maximum hebdomadaire de la personne, aucun cran supplémentaire n'est actif sur la semaine.
- [x] Sur une semaine qui dépasse un maximum abaissé depuis, une case peut encore être diminuée ou remise à 0.
- [x] Un enregistrement qui ferait dépasser 1 j sur un jour ou le maximum de la semaine, par exemple depuis un second onglet, est refusé avec un message, et la case reprend sa valeur précédente.
- [x] Les jours futurs sont affichés verrouillés ; un jour d'une semaine passée reste saisissable et corrigeable.
- [x] Les boutons « semaine précédente », « semaine suivante » et « cette semaine » changent la semaine affichée.
- [x] Chaque colonne affiche son total, et l'en-tête affiche le total de la semaine face au maximum (« 3 j / 4,5 j »).
- [x] Une colonne dont le total atteint 1 j prend un fond vert très clair.
- [x] Un jour passé sous 1 j est signalé tant que la semaine n'a pas atteint son maximum, et le signal disparaît dès que le maximum est atteint.
- [x] Sur un écran de téléphone, la grille présente un seul jour à la fois avec un sélecteur des jours ; les crans, plafonds et totaux s'y appliquent à l'identique.
- [x] La direction fixe le maximum hebdomadaire d'une personne au quart de journée, entre ¼ et 5 j, à partir d'une semaine choisie ; une personne sans réglage a 5 j ; ni un lead ni un membre de prod ne peut le modifier.
- [x] Un changement de maximum ne s'applique qu'à partir de la semaine choisie : les semaines antérieures gardent l'ancienne valeur, et supprimer une valeur de l'historique rétablit la précédente.
- [x] Aucun rôle ne peut consulter ni modifier les temps d'une autre personne.
- [x] On peut saisir sur une feuille « à estimer », et au-delà de l'estimation d'une feuille.
- [x] Après le premier temps saisi sur une feuille, son estimation initiale est consultable sur la feuille.
- [x] Une révision d'estimation sous le consommé arrondi au jour supérieur, ou le retrait de l'estimation, est refusé une fois des temps saisis.
- [x] La suppression d'un projet, d'un lot ou d'un sous-lot qui porte des temps, directement ou par ses descendants, est refusée avec un message.
- [x] Quand un lot qui porte des temps reçoit son premier sous-lot, ce sous-lot reprend les temps, l'estimation, l'estimation initiale et le responsable du lot, et les totaux sont inchangés.
- [x] Une journée répartie sur deux lignes déjà proposées se saisit en deux clics, en moins d'une minute depuis la connexion.

Deux critères reposent en partie sur le code plutôt que sur un test dédié : le jour d'une semaine passée, et la saisie au-delà de l'estimation (cf. §Tests).

## Dette technique identifiée

Issus de la review (mineurs non traités) :

_(aucun — les 6 mineurs ont été corrigés pendant la review)_

Au-delà de la review :

1. **Réalignement documentaire** — La règle 25 du pitch et le mécanisme `EstimateCoversConsumed` du plan doivent préciser que la borne ne s'applique qu'à une révision (`/forge:sync`). Dans `docs/product-backlog.md`, la convention « saisie en demi-journées » et C3.1 passent au quart de journée, et une part de C4.1 (le maximum hebdomadaire historisé) est livrée ici (`/product-backlog`).
2. **Plafonds vérifiés à l'écriture, sans verrou en base** — Deux clics simultanés de la même personne peuvent encore dépasser un plafond. C'est un risque résiduel accepté par le plan (§Risques et mitigations).
3. **Recherche filtrée en PHP sur toutes les feuilles** — `LeafFinder` charge toutes les feuilles à chaque recherche. C'est à revoir si le référentiel dépasse quelques centaines de feuilles.
4. **E2E dépendants de la base de dev et de la date réelle** — Ils purgent leurs données en SQL direct, parce que SQLite n'applique pas les clés étrangères. Ils sont à relancer après chaque `make db-reset`.
5. **Latence au clic non mesurée** — C'est une question ouverte du plan. À mesurer en recette avant d'envisager des composants enfants par ligne ou une mise à jour optimiste.
6. **Consommé antérieur au lancement** — C'est une question ouverte du pitch, qui relève de `consomme-vs-estime`.

## Leçons apprises

- **Une contrainte de validation doit dire si elle porte sur l'état ou sur le changement.** « Impose une estimation au moins égale au consommé » a été codé comme un invariant d'état. Or la règle 16 rend cet état légitime, et plus aucune feuille en dépassement n'était modifiable. Formuler ces règles sous la forme « une modification de X ne peut pas… ».
- **Des données réalistes révèlent ce que des données minimales cachent.** Les fixtures de démonstration contenaient d'emblée des feuilles en dépassement, et le bug de la révision bornée s'y voyait immédiatement. Les fixtures des tests, minimales, ne le déclenchaient pas.
- **Laisser le service de domaine porter les refus.** Un filtre en amont dans le Live Component (`findLeavesByIds` suivi d'une 404) court-circuitait le message prévu par `TimesheetManager`. Réserver la 404 à ce qui n'existe pas.
- **Un survol en CSS pur ne change aucun attribut.** L'E2E doit lire le style calculé et attendre la fin de la transition (`expect.poll`), et non un attribut `data-*`.
- **Vérifier que les outils de QA couvrent chaque nouveau dossier touché.** Des fixtures chargées seulement en dev et absentes des chemins PHPStan n'avaient aucun filet.
- **Mesurer un budget de requêtes par comparaison.** Comparer une petite et une grande grille détecte un N+1 sans figer un chiffre fragile.
