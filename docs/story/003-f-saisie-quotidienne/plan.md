# Plan technique — Saisir ses temps de la semaine en quarts de journée, d'un clic par case

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/003-f-saisie-quotidienne/`
> **Amont** : `pitch.md`

## Approche retenue

Chaque case saisie devient une ligne `TimeEntry` (personne, feuille, jour, nombre de **quarts** entre 1 et 4). Une case à 0 n'est pas enregistrée : la ligne est supprimée. Le maximum hebdomadaire est un historique `WeeklyMax` (personne, lundi d'effet, quarts). Le maximum d'une semaine est la dernière entrée dont le lundi d'effet est antérieur ou égal au lundi de la semaine. Sans entrée, il vaut 20 quarts (5 j), si bien qu'il n'y a rien à reprendre pour les comptes existants. `Lot` gagne `initialEstimateDays`. Toute la logique métier vit dans des services :

- **`TimesheetManager`** écrit une case : feuille, jour ouvré, pas de futur, plafonds jour et semaine, gel de l'estimation initiale.
- **`TimesheetBuilder`** construit le modèle de lecture de la grille (`WeekGrid`) : lignes, crans sélectionnables et verrouillés, totaux, jour complet, oubli.
- **`WeeklyMaxManager`** résout et modifie le maximum.
- **`LeafFinder`** cherche une feuille.
- **`ProjectManager`** reçoit les garde-fous sur le référentiel : suppression bloquée, temps déplacés à la bascule, estimation initiale figée à la première estimation après saisie.

La grille est un **Live Component** `Timesheet`, rendu dans la page `/saisie/{semaine}`. Le serveur est la seule source de vérité : un clic sur un cran appelle la LiveAction `record()`, qui délègue à `TimesheetManager`, puis le composant se re-rend par morphing avec des verrous, des totaux et des signaux recalculés une seule fois, en PHP. Le survol façon notation par étoiles est **en CSS pur**, sans JS maison. Les lignes ajoutées par la recherche vivent dans une LiveProp non persistée, ce qui les fait disparaître au rechargement (règle 7 du pitch). La navigation entre semaines se fait par de simples liens, et l'URL porte la semaine ISO (`/saisie/2026-W40`). Toutes les données de la grille sont filtrées par la personne connectée, lue dans la sécurité, jamais dans une prop ou dans la requête. Le maximum hebdomadaire se règle dans le formulaire existant de la fiche Équipe, avec deux champs (valeur et semaine d'effet). L'historique s'affiche en lecture seule sous le formulaire, avec une suppression par entrée.

### Mécanismes mobilisés

- **`#[AsLiveComponent]`** (`App\Twig\Components\Timesheet`) : l'état de l'écran est porté côté serveur. C'est la première utilisation de Live Component dans le projet, mais le bundle et ses routes (`/_components`) sont déjà installés. Les LiveProps sont scalaires :
  - `week`, une chaîne ISO non modifiable depuis le client ;
  - `addedLotIds`, une `list<int>` modifiée seulement par action ;
  - `query`, modifiable avec un debounce ;
  - `focusedDay`, l'index du jour affiché sur mobile.

  Les LiveActions sont `record`, `addLot` et `showDay`. Le composant expose une propriété calculée `grid`, construite une seule fois par rendu.
- **Morphing du Live Component** : le re-rendu préserve le focus et le survol, et seule la grille change.
- **Survol en CSS** : dans `.quarter-bar:hover`, les crans actifs se remplissent, et `.quarter:hover ~ .quarter` vide ceux qui suivent le cran survolé. Chaque cran est un `<button>` avec `aria-label` (« ¾ j — Mar 29/09, Kadence › Saisie », « Effacer — … » sur le cran actif) et `aria-pressed`. Un cran verrouillé est `disabled`. Le cran actif porte la valeur 0, ce qui fait qu'un re-clic efface la case.
- **`Symfony\Component\Clock\ClockInterface`** injecté (`TimesheetManager`, `TimesheetBuilder`, le composant et les contrôleurs, qui obtiennent la semaine en cours par `Week::containing($clock->now())`) : « aujourd'hui » est testable avec `ClockSensitiveTrait::mockTime()`. Le service `clock` du framework délègue à l'horloge globale que le trait remplace.
- **Objet valeur `Week`**, sans dépendance à l'horloge : lundi de la semaine ISO, `fromIso('2026-W40')`, `containing($date)`, `previous()`, `next()`, `days()` (du lundi au vendredi), `iso()`.
- **Requêtes avec jointures chargées dans les repositories** : les saisies de la semaine affichée et de la précédente sont chargées en une requête, avec feuille, lot parent et projet. La page projet charge le consommé par feuille en une requête groupée, sans N+1.
- **DQL `UPDATE`** (`TimeEntryRepository::moveToLot()`) : à la bascule, les temps passent du lot au premier sous-lot en une instruction, dans une transaction (`wrapInTransaction`) avec la création du sous-lot.
- **Contrainte de classe sur mesure** `EstimateCoversConsumed` sur `LotInput`, sur le modèle de `UniqueLotTitle`. Elle ne contrôle qu'une révision : quand l'estimation change (`LotInput::currentEstimateDays` porte la valeur d'avant), elle doit rester au moins égale au consommé arrondi au jour supérieur, et elle ne peut plus être retirée dès qu'il y a du consommé. Une feuille dont le consommé dépasse l'estimation reste modifiable sans relever son estimation. Le consommé est celui de la feuille, ou celui du lot parent pour le premier sous-lot d'une feuille.
- **Exceptions de domaine**, sur le modèle de `LotDepthException` :
  - `TimeEntryRefusedException` porte le message affiché dans la grille ;
  - `LotHasTimeEntriesException` est interceptée par les contrôleurs, qui affichent un flash d'erreur.
- **Contraintes natives** sur `TeamMemberInput` : `NotNull`, `Range(0.25, 5)` et `DivisibleBy(0.25)` sur `weeklyMaxDays`, `NotNull` sur `weeklyMaxFrom`, normalisé au lundi.
- **Extension Twig** `DaysExtension` : le filtre `days` convertit des quarts en « 3,75 j » (virgule décimale), le filtre `quarter_fraction` donne « ¼ », « ½ », « ¾ » ou « 1 », et le filtre `weekday` abrège le jour (« Lun »). Les conversions vivent dans `App\Model\Quarters`, partagé avec les messages de refus et le validateur. La logique de format reste hors des templates.
- **Clé de tri `u($title)->ascii()->lower()`**, qui existe déjà dans `ProjectRepository` : elle sert au tri des lignes (`LeafOrder`, partagé par la grille et la recherche) et au repli des accents dans la recherche. « ecrans » trouve ainsi « Écrans », ce que le `LIKE` de SQLite ne ferait pas.
- **Composants Twig Paper et tokens existants** : `success-soft` pour le jour complet, `warning` pour l'oubli, `brand` pour les crans remplis, `neutral-tertiary` pour les crans vides et `disabled` pour les crans verrouillés. Aucun nouveau token n'est nécessaire.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Stimulus + endpoint JSON avec mise à jour optimiste | Les plafonds et les verrous seraient codés deux fois (JS et PHP), avec un retour en arrière à gérer. Cela fait beaucoup plus de JS à écrire et à tester pour gagner environ 100 ms. |
| Turbo Frame + un formulaire par case | Deux allers-retours par clic (POST puis GET après redirection), des lignes ajoutées à faire passer dans l'URL et une recherche dans un frame séparé. |
| Temps en jours décimaux | Le décimal est rendu en chaîne par Doctrine, ce qui impose des conversions partout sous PHPStan niveau 10 et crée un risque d'arrondi dans les sommes. L'entier en quarts est exact. |
| Maximum hebdomadaire en simple champ de `User` | Le choix produit est un historique avec date d'effet : les semaines passées gardent leur valeur. |
| Valeur de départ du maximum stockée par personne | Inutile : l'absence d'entrée vaut 5 j, ce qui évite toute colonne et toute reprise des comptes existants. |
| Recherche par `LIKE` en base | SQLite ne replie que la casse ASCII. Le volume de feuilles (quelques centaines au plus) permet de filtrer en PHP. |
| Lignes ajoutées persistées (épinglage) | Contraire à la règle 7 du pitch : les lignes suivent les temps saisis. |
| `/` devient la grille de saisie | Choix produit : le tableau de bord reste, et seule la connexion (et le changement de mot de passe obligatoire) mène à `/saisie`. |
| Refus de suppression uniquement côté serveur | La personne confirmerait une action vouée à l'échec. La modale sait déjà, grâce à la requête groupée, qu'il y a du temps. |
| Verrou pessimiste pour les plafonds (`BEGIN IMMEDIATE`) | Une course réelle supposerait deux clics simultanés de la même personne à quelques millisecondes d'écart. La vérification au moment de l'écriture couvre le cas réaliste de l'onglet périmé. |

## Modèle de données

### Nouvelle structure `TimeEntry`

`src/Entity/TimeEntry.php`, table `time_entry` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `user` | ManyToOne (`User`), colonne `user_id` | non | pas de relation inverse ; aucune cascade |
| `lot` | ManyToOne (`Lot`), colonne `lot_id` | non | toujours une feuille (vérifié par `TimesheetManager`) ; pas de relation inverse ; aucune cascade, la suppression d'un lot qui porte des temps étant refusée |
| `day` | `date_immutable`, colonne `day` | non | du lundi au vendredi, jamais après aujourd'hui (vérifié par le service) |
| `quarters` | `smallint`, colonne `quarters` | non | entre 1 et 4 (`int<1, 4>` pour PHPStan) ; une case à 0 correspond à une ligne supprimée |

- Unicité `uniq_time_entry_user_lot_day` sur (`user_id`, `lot_id`, `day`).
- Index `idx_time_entry_user_day` sur (`user_id`, `day`) pour les requêtes par semaine. L'index sur `lot_id` est celui créé pour la clé étrangère, et il sert aux sommes par feuille.
- Pas de relation inverse sur `Lot` ni sur `User` : les temps ne sont jamais parcourus depuis l'entité, seulement par des requêtes nommées du repository.

### Nouvelle structure `WeeklyMax`

`src/Entity/WeeklyMax.php`, table `weekly_max` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `user` | ManyToOne (`User`), colonne `user_id` | non | pas de relation inverse |
| `effectiveFrom` | `date_immutable`, colonne `effective_from` | non | toujours un lundi (normalisé par `WeeklyMaxManager`) |
| `quarters` | `smallint`, colonne `quarters` | non | entre 1 et 20 (`int<1, 20>`) |

- Unicité `uniq_weekly_max_user_from` sur (`user_id`, `effective_from`) : une nouvelle valeur pour la même semaine remplace l'ancienne.
- Il n'existe pas de ligne « valeur de départ ». Sans entrée, le maximum est `WeeklyMaxManager::DEFAULT_QUARTERS` (20).

### Modification de `Lot`

`src/Entity/Lot.php` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `initialEstimateDays` | entier, colonne `initial_estimate_days` | oui | `positive-int` ; figé la première fois que la feuille a à la fois du temps et une estimation, puis jamais modifié par l'édition ; il suit la feuille à la bascule et à la remontée |

Une seule migration générée par `make migration` : elle crée `time_entry` et `weekly_max` et ajoute une colonne nullable à `lot`. Il n'y a aucune reprise de données.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Entity/TimeEntry.php` | Une case saisie : personne, feuille, jour, quarts. |
| `src/Entity/WeeklyMax.php` | Une valeur datée du maximum hebdomadaire d'une personne. |
| `src/Repository/TimeEntryRepository.php` | `findForUserBetween()` (avec jointures chargées sur la feuille, le parent et le projet), `sumQuartersByLot(Project)`, `sumQuartersForLotId()`, `existsForLots()`, `existsForProject()`, `moveToLot()`. |
| `src/Repository/WeeklyMaxRepository.php` | `findInEffectAt(User, lundi)`, `findOneAt(User, lundi)`, `findForUser(User)` (historique trié). |
| `src/Model/Week.php` | Objet valeur de la semaine ISO (lundi, jours ouvrés, semaines voisines, format `2026-W40`). |
| `src/Model/Quarters.php` | Conversions des quarts : jours (« 3,75 j »), fraction (« ¾ »), arrondi au jour supérieur. |
| `src/Model/Timesheet/WeekGrid.php` | Modèle de lecture de la grille : semaine, jours, lignes, total et maximum de la semaine. |
| `src/Model/Timesheet/TimesheetDay.php` | Un jour : date, total, complet, aujourd'hui, oubli (le verrouillage du futur est porté par les cases). |
| `src/Model/Timesheet/TimesheetRow.php` | Une ligne : feuille et cases ; le libellé « Projet › Lot › Sous-lot » est rendu par `_leaf_path.html.twig`. |
| `src/Model/Timesheet/TimesheetCell.php` | Une case : quarts saisis, plus grand cran sélectionnable, verrouillée (futur). |
| `src/Model/Timesheet/LeafOrder.php` | Ordre des feuilles, partagé par la grille et la recherche : projet (titre replié), puis lot, puis sous-lot. |
| `src/Service/TimesheetManager.php` | `record(User, Lot, jour, quarts)`. Il vérifie que le lot est une feuille, que le jour est ouvré et pas dans le futur, et que les quarts sont entre 0 et 4. Il vérifie les plafonds du jour et de la semaine, sachant qu'une diminution est toujours acceptée. Il crée, met à jour ou supprime la saisie, et fige l'estimation initiale. |
| `src/Service/TimesheetBuilder.php` | Construit `WeekGrid` : lignes proposées (semaine affichée et précédente) plus lignes ajoutées, tri, crans sélectionnables, totaux, signaux. |
| `src/Service/WeeklyMaxManager.php` | `quartersFor(User, Week)`, `change(User, quarters, Week)` (écrit seulement si la valeur diffère de celle en vigueur, remplace à même date), `delete(WeeklyMax)`. |
| `src/Service/LeafFinder.php` | `search(query, exclus, limite 10)` : filtre PHP, sans accents ni casse, sur « projet lot sous-lot » à partir d'au moins 2 caractères ; `isSearchable(query)` porte seule cette longueur minimale. |
| `src/Exception/TimeEntryRefusedException.php` | Refus d'une saisie, avec son message métier (plafond du jour, plafond de la semaine, jour futur…). |
| `src/Exception/LotHasTimeEntriesException.php` | Refus de supprimer un projet ou un lot qui porte des temps. |
| `src/Validator/EstimateCoversConsumed.php` | Contrainte de classe sur `LotInput`. |
| `src/Validator/EstimateCoversConsumedValidator.php` | Contrôle une révision : estimation au moins égale au consommé arrondi au jour supérieur, et non retirée dès qu'il y a du consommé ; rien à contrôler si l'estimation est inchangée ; ignorée pour un lot découpé. |
| `src/Twig/Components/Timesheet.php` | Live Component de la grille (props, actions, `grid` calculée, résultats de recherche, message de refus). |
| `src/Twig/DaysExtension.php` | Filtres `days` (« 3,75 j »), `quarter_fraction` (« ¾ ») et `weekday` (« Lun »). |
| `src/Controller/TimesheetController.php` | `GET /saisie` (semaine en cours) et `GET /saisie/{week}` (`\d{4}-W\d{2}`, 404 si invalide) ; liens semaine précédente, suivante et en cours. |
| `templates/timesheet/index.html.twig` | Page : titre, navigation entre semaines, montage du composant. |
| `templates/components/Timesheet.html.twig` | Grille : en-tête avec total sur maximum, colonnes du jour (vert, aujourd'hui, oubli), lignes, « Ajouter une ligne », onglets des jours sur mobile, message de refus. |
| `templates/timesheet/_quarter_bar.html.twig` | Barre de 4 crans d'une case (boutons, états rempli, verrouillé et actif). |
| `templates/timesheet/_leaf_path.html.twig` | Libellé « Projet › Lot › Sous-lot » d'une feuille, partagé par la grille et les résultats de recherche. |
| `migrations/Version20260929201824.php` | Généré : tables `time_entry` et `weekly_max`, colonne `lot.initial_estimate_days`. |
| `fixtures/DemoCompanyFixtures.php` | Fausse entreprise, chargée en dev seulement : 14 personnes dont des temps partiels (maximum historisé), 4 projets découpés en lots et sous-lots, 26 semaines de saisie jusqu'à hier, relatives à la date du jour ; estimations et estimations initiales dérivées du consommé ; aucune saisie sur la semaine en cours pour les comptes de test. |
| `tests/Support/CreatesTimeEntries.php` | Trait de test : créer une saisie ou un maximum. |
| `tests/Unit/Model/WeekTest.php` | Analyse ISO, lundi, semaines voisines, jours ouvrés, passage d'une année à l'autre (`2026-W53`/`2027-W01`). |
| `tests/Service/TimesheetBuilderTest.php` | Avec la base, le tri reposant sur des ids persistés : lignes proposées et tri, crans sélectionnables selon les plafonds, diminution toujours possible, jours futurs verrouillés, jour complet, oubli tant que le maximum n'est pas atteint, maximum abaissé sous le total. |
| `tests/Unit/Service/LeafFinderTest.php` | Accents et casse repliés, recherche sur les trois niveaux, feuilles seulement, exclusion des lignes déjà présentes, limite, requête trop courte. |
| `tests/Service/TimesheetManagerTest.php` | Avec la base : création, modification, remise à 0, plafonds du jour et de la semaine refusés, diminution acceptée au-dessus d'un maximum abaissé, futur, week-end, lot découpé refusés, estimation initiale figée. |
| `tests/Service/WeeklyMaxManagerTest.php` | 5 j sans entrée, valeur en vigueur par semaine, remplacement à même date, pas d'écriture si la valeur est inchangée, suppression qui rend la valeur précédente. |
| `tests/Twig/Components/TimesheetTest.php` | Rendu des lignes et des totaux, `record` puis re-rendu, refus avec message (plafond, lot découpé depuis l'affichage), ajout d'une ligne, recherche, jour sur mobile, jamais les temps d'autrui, jour hors de la semaine refusé. |
| `tests/Controller/TimesheetControllerTest.php` | Arrivée sur `/saisie` après connexion, semaine en paramètre, 404 pour une semaine invalide, navigation entre semaines, entrée « Ma semaine » dans le menu, raccourci du tableau de bord, budget de requêtes. |
| `tests/e2e/timesheet.spec.ts` | Survol qui remplit les crans, clic enregistré après rechargement, re-clic qui efface, crans verrouillés par le plafond, colonne verte, un jour à la fois sur mobile, journée en deux clics, suppression d'un lot qui porte des temps bloquée, maximum hebdomadaire fixé puis supprimé. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Entity/Lot.php` | Ajouter `initialEstimateDays` (colonne `initial_estimate_days`, nullable), avec son getter et son setter. |
| `src/Repository/LotRepository.php` | Ajouter `findLeavesWithAncestors()` (feuilles avec parent et projet, en une requête) et `findLeavesByIds(list<int>)`. |
| `src/Service/ProjectManager.php` | Injecter `TimeEntryRepository`. `deleteProject()` et `deleteLot()` lèvent `LotHasTimeEntriesException` s'il y a du temps. `addSubLot()` déplace les temps et l'estimation initiale vers le premier sous-lot, en transaction. `deleteLot()` fait remonter l'estimation initiale avec le reste. `applyLot()` fige l'estimation initiale à la première estimation d'une feuille qui a du temps. |
| `src/Service/ProjectRollup.php` | `summarize(Project, array<int, int> $quartersByLot = [])` calcule `hasTime` par lot (un lot découpé en a si l'un de ses enfants en a) et pour le projet. |
| `src/Model/LotSummary.php`, `src/Model/ProjectSummary.php` | Ajouter `hasTime`. |
| `src/Controller/ProjectController.php` | `show()` passe `TimeEntryRepository::sumQuartersByLot()` au rollup. `delete()` intercepte `LotHasTimeEntriesException` et affiche un flash d'erreur. |
| `src/Controller/LotController.php` | `delete()` intercepte `LotHasTimeEntriesException`. `edit()` passe le consommé de la feuille et le minimum d'une révision (`Quarters::daysRoundedUp()`) au template. |
| `src/Dto/LotInput.php` | Ajouter la contrainte de classe `#[EstimateCoversConsumed]` et `currentEstimateDays`, l'estimation d'avant la modification (ou celle du lot que reprend le premier sous-lot). |
| `src/Dto/TeamMemberInput.php` | Ajouter `weeklyMaxDays` (`?float`) et `weeklyMaxFrom` (`?\DateTimeImmutable`) avec leurs contraintes. `fromUser()` reçoit la valeur en vigueur et le lundi en cours, `forNewMember(Week)` pré-remplit la semaine d'effet d'une inscription. |
| `src/Form/TeamMemberType.php` | Ajouter `weeklyMaxDays` (`NumberType`, html5, `step` 0.25, min 0.25, max 5) et `weeklyMaxFrom` (`DateType`, `single_text`, `datetime_immutable`, aide « la semaine contenant cette date »). |
| `src/Service/TeamManager.php` | `register()` et `update()` appliquent le maximum via `WeeklyMaxManager::change()` (conversion des jours en quarts, normalisation au lundi). |
| `src/Controller/TeamController.php` | Pré-remplir le maximum en vigueur et le lundi en cours. Passer l'historique au template. Nouvelle route `POST /equipe/{id}/maximum/{weeklyMax}/supprimer` (CSRF, entrée appartenant à la personne). |
| `templates/team/_form.html.twig` | Ajouter les deux champs du maximum. |
| `templates/team/edit.html.twig` | Ajouter sous le formulaire l'historique du maximum en lecture seule, avec un bouton de suppression par entrée et « 5 j par défaut » en première ligne implicite. |
| `templates/project/_lot_row.html.twig` | Afficher « initiale X j » sous l'estimation quand elle est posée. La modale de suppression indique « Des temps sont saisis : suppression impossible », sans bouton de confirmation, quand `hasTime` est vrai. |
| `templates/project/show.html.twig` | Même adaptation pour la modale de suppression du projet. |
| `templates/lot/edit.html.twig` | Afficher l'estimation initiale et le minimum imposé par le consommé quand la feuille a du temps. |
| `templates/base.html.twig` | Entrée « Ma semaine » en tête du menu latéral (`data-test="nav-timesheet"`). |
| `templates/page/index.html.twig` | Raccourci « Ma semaine » en tête du tableau de bord (`data-test="home-timesheet"`). |
| `config/packages/security.yaml` | `default_target_path: app_timesheet`. |
| `src/Controller/AccountController.php` | Après le changement de mot de passe, rediriger vers `app_timesheet`. |
| `assets/styles/app.css` | Règles `@layer components` de la barre de crans : remplissage au survol, vidage des crans qui suivent, état verrouillé hachuré, focus visible. |
| `tests/Unit/Service/ProjectManagerTest.php` | Nouvelle dépendance (stub du repository), suppression refusée, bascule qui déplace les temps et l'estimation initiale, estimation initiale figée à la modification. |
| `tests/Unit/Service/ProjectRollupTest.php` | `hasTime` d'une feuille, d'un lot découpé et d'un projet. |
| `tests/Controller/LotControllerTest.php` | Révision sous le consommé refusée, retrait de l'estimation refusé, suppression refusée, premier sous-lot qui reprend les temps, estimation initiale affichée. |
| `tests/Controller/ProjectControllerTest.php` | Suppression d'un projet qui porte des temps refusée, modale adaptée, raccourci « Ma semaine ». |
| `tests/Controller/TeamControllerTest.php` | Maximum fixé à partir d'une semaine, valeur invalide refusée (0, 5,5, 4,3), suppression d'une entrée, accès réservé à la direction. |
| `src/Command/CreateDirectorCommand.php` | Horloge injectée, `TeamMemberInput::forNewMember()` : la semaine d'effet du maximum est requise. |
| `fixtures/ProjectFixtures.php` | Référence `SUPPORT` sur le lot « Support », utilisé par les fixtures de démonstration. |
| `phpstan.dist.neon` | `fixtures/` ajouté aux chemins analysés et aux exemptions de complexité cognitive. |
| `tests/Controller/AccountControllerTest.php`, `tests/Controller/SecurityControllerTest.php`, `tests/e2e/team.spec.ts` | Arrivée attendue sur `/saisie` (« Ma semaine ») après connexion et après changement de mot de passe. |
| `tests/Unit/Service/TeamManagerTest.php` | Nouvelle dépendance `WeeklyMaxManager`, inscription d'une personne à temps partiel. |

## Hors scope

- **Mise à jour optimiste côté client** : chaque clic attend le re-rendu du serveur. On y reviendra seulement si la latence gêne en recette (cf. §Questions ouvertes).
- **Verrou pessimiste des plafonds en base** : la vérification au moment de l'écriture suffit (cf. §Risques et mitigations).
- **Affichage du consommé** dans la grille ou l'arbre du projet : c'est `consomme-vs-estime`. La requête groupée `sumQuartersByLot()` est posée ici, mais seul `hasTime` s'en sert.
- **Refonte du tableau de bord** : seul un raccourci y est ajouté.
- **Navigation au clavier par flèches dans une barre** : les crans sont des boutons atteignables avec Tab, ce qui suffit.
- **Catalogue de traduction** : les libellés restent en français en dur, comme dans le reste de l'interface.
- **Correction du défaut « clés étrangères non appliquées » de SQLite** (dette déjà listée dans `docs/stack.md`) : les garde-fous du service suffisent à éviter les temps orphelins.

## Impacts transverses

- **Cloisonnement des données** : chaque lecture et chaque écriture de temps est filtrée par la personne connectée (`Security::getUser()` dans le composant, passée aux services). Le composant n'a aucune prop « personne ». `record()` ne reçoit que la feuille, le jour et les quarts, et le jour doit appartenir à la semaine du composant. `findForUserBetween()` exige une personne. Un test vérifie qu'on ne voit jamais les temps d'autrui.
- **Déclinaisons / thèmes** : sous le point de rupture `md`, un seul jour est affiché, piloté par la LiveProp `focusedDay`, et les autres colonnes sont masquées. Le mode sombre passe par les tokens existants.
- **Traduction / i18n** : non. Libellés en français, formats « 3,75 j » et « ¾ » via `DaysExtension`.
- **API / exposition externe** : non. L'endpoint `/_components/Timesheet` du Live Component est interne et couvert par la règle `^/` → `ROLE_USER`.
- **Droits d'accès** : la saisie est ouverte à `ROLE_USER`, limitée à soi par construction. Le maximum hebdomadaire passe par `/equipe`, déjà réservé à `ROLE_DIRECTION`. La route de suppression d'une entrée vérifie que l'entrée appartient à la personne de l'URL. Le voter `LOT_EDIT` ne change pas, et la révision bornée s'applique à quiconque modifie la feuille.
- **Emails / notifications** : non.
- **Migration de données** : création de deux tables et d'une colonne nullable. Aucune reprise : sans entrée, le maximum vaut 5 j et l'estimation initiale reste vide tant qu'aucun temps n'est saisi.
- **Comportement par défaut** : la connexion mène à `/saisie`. `/` reste le tableau de bord, avec un raccourci en plus. Une personne sans temps voit une grille vide avec « Ajouter une ligne ».

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/Week.php` | unit | Analyse et format ISO, lundi, jours ouvrés, semaines voisines, changement d'année ; 404 en amont pour une semaine invalide. |
| `src/Service/TimesheetBuilder.php` | functional (base) | Lignes de la semaine affichée et de la précédente plus les lignes ajoutées (feuilles seulement), tri projet puis ordre de création. Plus grand cran sélectionnable : minimum de `4 − reste du jour` et de `maximum − reste de la semaine`, jamais sous la valeur actuelle. Jours futurs verrouillés, jour complet à 4 quarts. Oubli seulement pour les jours passés, tant que la semaine est sous son maximum. Maximum abaissé sous le total. |
| `src/Service/LeafFinder.php` | unit | Repli des accents et de la casse, correspondance sur le projet, le lot et le sous-lot, exclusion des lots découpés et des lignes présentes, 10 résultats au plus, rien sous 2 caractères. |
| `src/Service/TimesheetManager.php` | functional (base) | Création, modification, remise à 0 qui supprime la ligne. Refus : plafond du jour, plafond de la semaine, futur, week-end, lot découpé, quarts hors 0 à 4. Diminution acceptée au-dessus du maximum. Estimation initiale figée au premier temps, et pas si la feuille est « à estimer ». |
| `src/Service/WeeklyMaxManager.php` | functional (base) | 5 j par défaut, valeur en vigueur selon la semaine, remplacement à même date, valeur inchangée non écrite, suppression. |
| `src/Service/ProjectManager.php` | unit | Suppression refusée d'un projet ou d'un lot qui porte des temps. Bascule qui appelle `moveToLot()` et transfère l'estimation initiale. Remontée de l'estimation initiale. Estimation initiale figée à la première estimation après saisie. |
| `src/Service/ProjectRollup.php` | unit | `hasTime` par feuille, par lot découpé et par projet. |
| `src/Validator/EstimateCoversConsumedValidator.php` | functional (via `LotController`) | Estimation sous le consommé arrondi au jour supérieur refusée, estimation vidée refusée, feuille en dépassement modifiable sans révision, lot découpé ignoré, premier sous-lot soumis au consommé du lot. |
| `src/Twig/Components/Timesheet.php` | functional (`InteractsWithLiveComponents` + `mockTime()`) | Rendu (lignes, totaux, vert, oubli, verrous), `record` puis re-rendu, refus avec message et case inchangée, ajout d'une ligne qui disparaît au remontage, recherche, `showDay`. Clic sur un lot découpé depuis l'affichage : message de refus. Temps d'autrui jamais rendus. |
| `src/Controller/TimesheetController.php` | functional | Arrivée sur `/saisie` après connexion, semaine en paramètre, 404 pour une semaine invalide, liens entre semaines, entrée de menu. Rendu d'une semaine en 6 requêtes au plus, autant pour une petite que pour une grande grille. |
| `src/Controller/TeamController.php` | functional | Maximum fixé à partir d'une semaine et historique affiché, valeurs invalides refusées, suppression d'une entrée (CSRF, appartenance), accès réservé à la direction. |
| `src/Controller/LotController.php`, `src/Controller/ProjectController.php` | functional | Révision bornée, suppressions refusées avec flash, modales adaptées, bascule avec temps, estimation initiale affichée. |
| `tests/e2e/timesheet.spec.ts` | E2E | Survol qui remplit les n premiers crans, clic enregistré puis rechargé, re-clic qui efface, crans verrouillés par le plafond du jour, colonne verte à 1 j, navigation entre semaines, vue d'un jour sur mobile (390 px), journée en deux clics sur deux lignes proposées, suppression bloquée d'un lot qui porte des temps, maximum hebdomadaire fixé puis supprimé. Les dates sont toujours prises sur le lundi de la semaine en cours, toujours saisissable, et les temps E2E sont purgés dans `afterAll`. |

**Hors scope tests** :

- **Rendu visuel du survol en CSS** (couleurs exactes) : l'E2E compare entre elles les couleurs calculées des crans pendant le survol, sans valeur exacte (en CSS pur, aucun attribut ne change au survol), et vérifie `disabled` pour les verrous.
- **Course de deux enregistrements simultanés** : non reproductible de façon fiable sous SQLite. Le scénario de l'onglet périmé est couvert au niveau du composant.
- **Chronométrage « moins d'une minute »** : il est remplacé par le nombre de clics dans l'E2E. Le temps réel se mesure en recette.
- **Fixtures de démonstration** : pas de test dédié. Chargées en dev seulement, elles sont analysées par PHPStan (`fixtures/` dans les chemins, sans la limite de complexité cognitive, comme Entity, FormType et Repository).

## Ordre d'exécution

1. [x] **Modèle et migration**
   - Objectif : `TimeEntry`, `WeeklyMax` et `Lot.initialEstimateDays` mappés, avec leurs repositories et leurs requêtes nommées.
   - Fichiers : `src/Entity/TimeEntry.php`, `src/Entity/WeeklyMax.php`, `src/Entity/Lot.php`, `src/Repository/TimeEntryRepository.php`, `src/Repository/WeeklyMaxRepository.php`, `src/Repository/LotRepository.php`, migration générée.
   - Vérification : `make migration` relue (colonnes en snake_case, index, `down()` réversible), `make db-reset`, `make db-validate`, `make lint`.
   - Commitable seule : oui.

2. [x] **Semaine et maximum hebdomadaire**
   - Objectif : `Week` et `WeeklyMaxManager`, maximum réglable dans la fiche Équipe, avec son historique et sa suppression.
   - Fichiers : `src/Model/Week.php`, `src/Service/WeeklyMaxManager.php`, `src/Dto/TeamMemberInput.php`, `src/Form/TeamMemberType.php`, `src/Service/TeamManager.php`, `src/Controller/TeamController.php`, `templates/team/_form.html.twig`, `templates/team/edit.html.twig`, tests `WeekTest`, `WeeklyMaxManagerTest`, `TeamControllerTest`.
   - Vérification : `make phpunit-filter WeekTest`, `make phpunit-filter WeeklyMaxManagerTest`, `make phpunit-filter TeamControllerTest`.
   - Commitable seule : oui.

3. [x] **Écriture d'une case**
   - Objectif : `TimesheetManager::record()` avec toutes ses règles et le gel de l'estimation initiale.
   - Fichiers : `src/Service/TimesheetManager.php`, `src/Exception/TimeEntryRefusedException.php`, `tests/Support/CreatesTimeEntries.php`, `tests/Service/TimesheetManagerTest.php`.
   - Vérification : `make phpunit-filter TimesheetManagerTest`.
   - Commitable seule : oui.

4. [x] **Modèle de lecture de la grille et recherche**
   - Objectif : `TimesheetBuilder` produit `WeekGrid`, et `LeafFinder` retrouve les feuilles.
   - Fichiers : `src/Model/Timesheet/*`, `src/Service/TimesheetBuilder.php`, `src/Service/LeafFinder.php`, `src/Twig/DaysExtension.php`, tests `TimesheetBuilderTest` et `LeafFinderTest`.
   - Vérification : `make phpunit-filter TimesheetBuilderTest`, `make phpunit-filter LeafFinderTest`.
   - Commitable seule : oui.

5. [x] **Écran de saisie**
   - Objectif : Live Component `Timesheet`, page `/saisie/{week}`, navigation, menu, raccourci, redirection après connexion et après changement de mot de passe.
   - Fichiers : `src/Twig/Components/Timesheet.php`, `src/Controller/TimesheetController.php`, `templates/timesheet/index.html.twig`, `templates/components/Timesheet.html.twig`, `templates/timesheet/_quarter_bar.html.twig`, `templates/base.html.twig`, `templates/page/index.html.twig`, `config/packages/security.yaml`, `src/Controller/AccountController.php`, tests `TimesheetTest` et `TimesheetControllerTest`.
   - Vérification : `make phpunit-filter TimesheetTest`, `make phpunit-filter TimesheetControllerTest`, puis parcours manuel sur `https://kadence.wip/saisie`.
   - Commitable seule : oui.

6. [x] **Rendu graphique**
   - Objectif : survol façon étoiles en CSS, crans verrouillés, colonne verte, jour courant, oubli, vue d'un jour sur mobile, accessibilité des crans.
   - Fichiers : `assets/styles/app.css`, `templates/components/Timesheet.html.twig`, `templates/timesheet/_quarter_bar.html.twig`.
   - Vérification : parcours manuel sur ordinateur et à 390 px (Chrome DevTools ou Playwright MCP), `make quality`.
   - Commitable seule : oui.

7. [x] **Garde-fous sur le référentiel**
   - Objectif : suppression bloquée avec modales adaptées, bascule avec temps, estimation initiale figée et affichée, révision bornée.
   - Fichiers : `src/Service/ProjectManager.php`, `src/Service/ProjectRollup.php`, `src/Model/LotSummary.php`, `src/Model/ProjectSummary.php`, `src/Exception/LotHasTimeEntriesException.php`, `src/Validator/EstimateCoversConsumed*.php`, `src/Dto/LotInput.php`, `src/Controller/ProjectController.php`, `src/Controller/LotController.php`, `templates/project/_lot_row.html.twig`, `templates/project/show.html.twig`, `templates/lot/edit.html.twig`, tests `ProjectManagerTest`, `ProjectRollupTest`, `LotControllerTest`, `ProjectControllerTest`.
   - Vérification : `make phpunit-filter ProjectManagerTest`, `make phpunit-filter LotControllerTest`, `make phpunit-filter ProjectControllerTest`.
   - Commitable seule : oui.

8. [x] **Démonstration, E2E et QA finale**
   - Objectif : fixtures de démonstration, scénario Playwright, suite complète verte.
   - Fichiers : `fixtures/DemoCompanyFixtures.php`, `tests/e2e/timesheet.spec.ts`.
   - Vérification : `make db-reset`, `make quality`, `make phpunit`, `make serve` puis `make playwright`.
   - Commitable seule : oui.

## Critères de sortie

- [ ] Migration générée par `make migration`, relue (colonnes en snake_case, unicités et index présents, `down()` réversible) ; `make db-validate` sans erreur.
- [ ] Aucune requête hors de `src/Repository/`, aucune logique métier dans les contrôleurs, entités, repositories ou templates.
- [ ] Le rendu de la grille d'une semaine tient en 6 requêtes au plus, quel que soit le nombre de lignes (vérifié par un test).
- [ ] Aucune lecture ni écriture de temps sans filtre sur la personne connectée (vérifié par un test).
- [ ] Chaque critère d'acceptation du pitch est couvert par au moins un test fonctionnel ou E2E. « Moins d'une minute » est vérifié par le nombre de clics.
- [ ] `make phpunit` vert, sans régression.
- [ ] `make playwright` vert, `make serve` en marche.
- [ ] `make lint` propre (PHP-CS-Fixer et PHPStan niveau 10).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Première utilisation d'un Live Component : hydratation des props, morphing en conflit avec le survol ou le focus | moyenne | Props scalaires uniquement (`string`, `int`, `list<int>`). Tests `InteractsWithLiveComponents` dès l'étape 5. Vérification manuelle du survol après un clic avant l'étape 6. |
| Latence perçue à chaque clic (aller-retour et re-rendu de toute la grille) | moyenne | Budget de 6 requêtes vérifié par un test, état `data-loading` sur la barre cliquée. Si la gêne est avérée en recette, découper en composants enfants par ligne (cf. §Questions ouvertes). |
| Deux clics simultanés dépassent un plafond (vérification puis écriture non atomiques) | faible | SQLite sérialise les écritures et l'unicité (personne, feuille, jour) interdit les doublons. Le cas réaliste de l'onglet périmé est refusé, car la vérification relit la base. Risque résiduel accepté. |
| Maximum abaissé sous un total déjà saisi : crans négatifs ou diminution impossible | moyenne | Règle explicite, la diminution est toujours acceptée. Le plus grand cran sélectionnable n'est jamais sous la valeur actuelle. Cas testés dans `TimesheetBuilderTest` et `TimesheetManagerTest`. |
| Frontière de jour ou de fuseau fausse (« aujourd'hui », verrous du futur) | faible | `ClockInterface` partout, fuseau Europe/Paris (`php.ini`), `day` stocké en date sans heure, tests avec `mockTime()`. |
| E2E dépendant de la date réelle (serveur non simulé) | moyenne | Saisie toujours sur le lundi de la semaine en cours, toujours dans le passé ou aujourd'hui. Aucun test du verrouillage du futur en E2E, il est couvert en fonctionnel. |
| `UPDATE` DQL de la bascule qui contourne l'unité de travail | faible | Exécuté dans la même transaction que la création du sous-lot. Aucun `TimeEntry` n'est chargé dans cette requête. Le nouveau sous-lot n'a pas de temps, donc pas de conflit d'unicité. |
| Temps orphelins si un lot est supprimé en SQL direct (SQLite n'applique pas les clés étrangères) | faible | Suppression uniquement via `ProjectManager`, qui refuse. Les purges E2E suppriment `time_entry` avant `lot`. |
| PHPStan niveau 10 sur les props et arguments du Live Component | faible | Types stricts sur les `#[LiveArg]` (`int`, `string`), normalisation `list<int>` à l'écriture de `addedLotIds`. |

## Questions ouvertes

- **Latence au clic en recette** : si le re-rendu complet de la grille dépasse environ 200 ms perçues, options : (a) composants enfants par ligne, re-rendus seuls, avec émission d'un événement pour les totaux, (b) mise à jour optimiste du cran en Stimulus avant la réponse. À trancher sur mesure pendant l'implémentation, (a) de préférence.
- **Consommé antérieur au lancement** (question ouverte du pitch) : relève de `consomme-vs-estime`, sans impact sur ce plan.
