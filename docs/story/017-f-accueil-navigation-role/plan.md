# Plan technique — Voir à l'accueil ce qui attend chacun, et trouver dans le menu le quotidien, le pilotage et l'administration

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/017-f-accueil-navigation-role/`
> **Amont** : `pitch.md`

## Approche retenue

La story réorganise la navigation sans nouvelle table et ajoute un seul calcul, celui de l'accueil. Le menu de `templates/base.html.twig` (lignes 105 à 143) passe de deux à trois zones. Le haut, sans titre, garde Accueil, Ma semaine et Congés. Une zone « Pilotage » (`data-test="nav-steering"`), rendue pour tous, suit le haut avec Roadmap, Projets et Personnes. La zone « Administration » (`data-test="nav-admin"`, `mt-auto shrink-0` comme aujourd'hui) n'est rendue que pour `ROLE_DIRECTION`, avec Tags, Jours fériés et Droits à congés. `NavigationExtension::SECTIONS` (lignes 12 à 21) gagne deux sections et en renomme une :
- `people` (`app_person`, `app_team_`) ;
- `entitlements` (`app_time_off_entitlements`), placée **avant** `time_off`, puisque la première correspondance de préfixe gagne ;
- `dashboard` devient `home`.

La page Personnes réunit l'annuaire et l'ancienne page Équipe. La liste `GET /personnes` (`app_person_index`) rejoint `PersonController`, à côté de la fiche `/personnes/{id}`. Elle rend le composant `TeamList` existant, qui s'ouvre à toute personne connectée. Colonnes, filtres et actions s'y affichent par rôle, selon les niveaux de `PersonVoter` :
- les leads voient le rôle et les tags ;
- la direction voit en plus l'e-mail, le manager et les personnes désactivées.

Les filtres que le rôle ne peut pas voir sont ignorés côté serveur, même présents dans l'URL. Les deux `LiveAction` de gestion vérifient `ROLE_DIRECTION` à chaque appel. `TeamController` garde son `#[IsGranted('ROLE_DIRECTION')]` de classe, mais change de préfixe : `/equipe` devient `/personnes` (`/personnes/nouveau`, `/personnes/{id}/modifier`…). Son action `index` disparaît. Trois routes de redirection permanentes, déclarées dans `config/routes.yaml` via le `RedirectController` du framework, mènent les anciennes adresses GET à leur nouvelle place. La page des droits à congés garde son adresse `/conges/compteurs` ; seuls sa section de menu, son titre et l'absence d'onglets changent.

L'accueil est calculé par un service `HomeAgendaBuilder`, qui réutilise les calculs existants au lieu d'en écrire de nouveaux :
- **signaux des lots et des projets** : les lignes de `RoadmapBuilder::build()`, sur la fenêtre de la semaine courante. Les signaux d'un lot ne dépendent pas de la fenêtre ; la fin d'un projet non plus (`RoadmapRow::isEndUnknown()`, `unknownEndLeaves()`) ;
- **journées incomplètes** : la règle de Ma semaine, extraite de `TimesheetBuilder::compose()` (lignes 84 à 91 et 109 à 120) dans une méthode commune ;
- **demandes** : les requêtes existantes de `TimeOffRequestRepository`.

Une feuille déclarée terminée (`Completed`) est écartée de « Mes lots » et des projets à surveiller : `Scheduler` laisse `Overrun` levé sur un lot terminé au-delà de son estimation, alors que plus rien n'y est à faire. Une demande validée d'office, faute de valideur (`decidedBy` nul), ne remonte pas dans « Mes congés » : personne d'autre n'a décidé.

Le résultat est un modèle calculé `HomeAgenda`, que `templates/page/index.html.twig` rend section par section. Un projet à surveiller y est un `WatchedProject` : sa ligne de roadmap et les pastilles retenues sur ses feuilles, qu'une ligne projet ne porte pas. Ces pastilles sont les signaux de surveillance de ses feuilles, plus ceux qui retiennent la fin d'une feuille à fin inconnue ; sa fin calculée, ou « Fin inconnue », s'affiche en regard. Le partiel `_signal_tooltips.html.twig` accepte une variable `signals` pour ces pastilles. Enfin, la déconnexion mène à `/login` (`logout.target`) : aucune page cible n'est plus mémorisée, et la connexion retombe sur `default_target_path: app_timesheet`. Un lien profond suivi avant connexion garde, lui, son comportement.

### Mécanismes mobilisés

- **Fonction Twig pure `nav_section()`** (story 005) : on enrichit la table de préfixes au lieu d'ajouter des blocs Twig par page ; une route bien préfixée allume d'office sa section.
- **Composant Twig anonyme `NavLink`** (props `count`, `countLabel` déjà livrées) : les nouvelles entrées le réutilisent tel quel.
- **Live Component `TeamList`** (`#[AsLiveComponent]`, `LiveProp(url: true)`) : il porte déjà filtres, désactivation et réactivation en place. On lève son verrou `#[PostMount]`/`#[PostHydrate]` (lignes 60 à 66), et on déplace le contrôle d'accès dans les actions et dans le calcul du filtre.
- **Voters existants** : `PersonVoter` (`PERSON_PLANNING`, `PERSON_MANAGER`) pour la fiche, et des rôles hiérarchiques (`ROLE_LEAD`, `ROLE_DIRECTION`) pour la liste, dont les colonnes valent pour toutes les lignes. `TimeOffVoter::INBOX` sert pour la section « À valider ». Aucun nouveau voter.
- **`#[IsGranted]` de classe sur `TeamController`** : il protège seul les routes de gestion. La ligne `^/equipe` de `access_control` (`security.yaml` ligne 47) disparaît, puisque plus aucune route de gestion ne vit sous ce préfixe.
- **`Symfony\Bundle\FrameworkBundle\Controller\RedirectController::redirectAction`** déclaré en YAML (`route`, `permanent: true`, `keepQueryParams: true`) : les attributs de la route d'origine (`id`) passent à la route cible, sans contrôleur maison.
- **`form_login` + `logout.target`** : la déconnexion n'enregistre plus `/` comme cible. `always_use_default_target_path` est écarté, car il casserait le lien « Décider de la demande » des e-mails (`templates/emails/time_off/requested.html.twig`).
- **Réutilisation des calculs** : `RoadmapBuilder::build()` (ligne 42), `RoadmapSignal`, `TimesheetBuilder`, `TimeOffRequestRepository::findOfUser()` (ligne 104) et `findPendingDecidableBy()` (ligne 123). Aucune requête DQL nouvelle hors repository.
- **Composants du design system** : `PageHeader`, `Card`, `EmptyState`, `Badge`, et les partiels `roadmap/_signal.html.twig` et `_signal_tooltips.html.twig`, sous un `data-controller="roadmap-tooltip"` comme dans l'en-tête de la fiche projet.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Un annuaire en lecture pour tous à côté de la page Équipe de la direction | Deux listes des mêmes personnes, deux vocabulaires, et la direction devrait choisir entre les deux. Une liste unique aux colonnes et actions conditionnées couvre les trois rôles. |
| Garder les adresses `/equipe/…` pour la gestion | L'adresse contredirait le menu (« Personnes ») et la fiche (`/personnes/{id}`). Les redirections couvrent les marque-pages. |
| Renommer les noms internes (`TeamController`, `TeamList`, `app_team_*`, `templates/team/`) | Aucun effet visible pour un coût élevé en tests ; la story 015 garde aussi le vocabulaire du code. Seuls les libellés et les adresses changent. |
| Un calcul de signaux dédié à l'accueil | Une seconde source de vérité qui divergerait de la roadmap. `RoadmapBuilder::build()` livre déjà signaux et fins inconnues, avec un nombre de requêtes constant (testé). |
| Journées incomplètes par deux appels à `TimesheetBuilder::build()` | Ils chargeraient les favoris, les lignes et les entrées de deux semaines de trop. Extraire le calcul des jours garde une seule règle, sans lignes. |
| `always_use_default_target_path: true` | Après connexion, un manager qui suit « Décider de la demande » atterrirait sur Ma semaine au lieu de la page À valider. |
| Déplacer `/conges/compteurs` vers une nouvelle adresse | Le changement attendu est la place dans le menu, pas l'adresse ; un renommage ajouterait une redirection sans gain. |
| Sections de l'accueil chargées à la demande (Turbo Frame, Live Component paresseux) | Prématuré : la page reste rendue serveur en une passe, et le budget de requêtes est testé. À reconsidérer si la mesure le justifie (§Questions ouvertes). |

## Modèle de données

Aucun impact sur la base : pas d'entité, pas de migration.

### Nouveau modèle calculé (non persisté) `HomeAgenda`

`src/Model/Home/HomeAgenda.php` (`final readonly`) :

| Champ | Type | Contenu |
|---|---|---|
| `forgottenDays` | `list<TimesheetDay>` | Journées incomplètes de la semaine courante et de la précédente, de la plus ancienne à la plus récente. |
| `toDecide` | `list<TimeOffRequest>` | Demandes que la personne peut décider ; vide sans le droit `TIME_OFF_INBOX`. |
| `myLeaves` | `list<RoadmapRow>` | Feuilles non terminées dont la personne est responsable et qui portent `ToEstimate`, `Overrun` ou `ProgressToRefresh`, dans l'ordre des projets puis du découpage. |
| `watchedProjects` | `list<WatchedProject>` | Projets dont la fin est inconnue ou dont une feuille non terminée porte `Overrun`, `ToReplan`, `TeamToReview`, `LateStart` ou `ProgressToRefresh`, par titre ; vide sous `ROLE_LEAD`. |
| `myRequests` | `list<TimeOffRequest>` | Demandes en attente de la personne, puis ses demandes validées, refusées ou annulées par un autre depuis moins de 7 jours, chaque groupe par date de début ; une demande validée d'office n'y figure pas. |

`src/Model/Home/WatchedProject.php` (`final readonly`) : `row` (`RoadmapRow`, la ligne projet) et `signals` (`list<RoadmapSignal>`, dans l'ordre des cas de l'enum).

Méthode `isEmpty(): bool`. Deux constantes de `HomeAgendaBuilder` portent les ensembles de signaux (`OWNER_SIGNALS`, `WATCH_SIGNALS`) ; une troisième porte la fenêtre de 7 jours (`RECENT_DAYS`).

### Modification d'un DTO

`src/Dto/TeamListFilter.php` : ajout de `public bool $activeOnly = false`. `UserRepository::findForTeamList()` (ligne 86) ajoute `u.active = true` quand il est vrai.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Service/HomeAgendaBuilder.php` | `build(User $user, bool $canDecide, bool $direction, bool $withProjects): HomeAgenda` ; un appel de `RoadmapBuilder::build(RoadmapWindow::around(Week courante), $withProjects)`, deux appels de `TimesheetBuilder::daysOf()`, `findOfUser()` et, si `$canDecide`, `findPendingDecidableBy()`. |
| `src/Model/Home/HomeAgenda.php` | Modèle calculé de l'accueil (voir §Modèle de données). |
| `src/Model/Home/WatchedProject.php` | Un projet à surveiller et ses pastilles (voir §Modèle de données). |
| `templates/person/index.html.twig` | Page Personnes : `PageHeader` « Personnes » (description par rôle), bouton « Inscrire une personne » pour la direction, `<twig:TeamList/>`. |
| `templates/page/_section.html.twig` | Carte d'une section de l'accueil : titre, liste d'éléments, `data-test="home-section-<clé>"`. |
| `tests/Service/HomeAgendaBuilderTest.php` | Règles de sélection de l'accueil, contre la base (voir §Stratégie de test). |
| `tests/Controller/HomeControllerTest.php` | Sections par rôle, état vide, cloisonnement, liens, nombre de requêtes constant. |
| `tests/e2e/home.spec.ts` | Une personne ouvre une journée incomplète depuis l'accueil et arrive sur la bonne semaine ; la personne, sans aucun temps, est créée puis purgée en SQL, car les fixtures tirent au hasard les journées oubliées. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `templates/base.html.twig` | Trois zones : haut (Accueil `nav-home`, Ma semaine, Congés), « Pilotage » (`nav-steering`, titre associé par `aria-labelledby` : Roadmap, Projets, Personnes `nav-people`), « Administration » pour la direction seule (Tags, Jours fériés, Droits à congés `nav-entitlements`). L'entrée Équipe (`nav-team`) disparaît. |
| `src/Twig/NavigationExtension.php` | `SECTIONS` : `home` (`app_page`), `entitlements` avant `time_off`, `people` (`app_person`, `app_team_`) ; `team` disparaît. |
| `src/Controller/PersonController.php` | Route `GET /personnes` `app_person_index`, qui rend `person/index.html.twig`. |
| `src/Controller/TeamController.php` | Préfixe `#[Route('/personnes')]` ; suppression d'`index()` ; redirections de fin d'action vers `app_person_index` (après modification, mot de passe déjà affiché). |
| `src/Twig/Components/TeamList.php` | Suppression du verrou de montage ; `deactivate()` et `reactivate()` appellent `denyAccessUnlessGranted('ROLE_DIRECTION')` ; `filter()` ignore les tags sous `ROLE_LEAD` et le manager sous `ROLE_DIRECTION`, et pose `activeOnly` hors direction ; `isFiltered()` se décide sur ce filtre effectif ; `getManagers()` renvoie `[]` hors direction. |
| `templates/components/TeamList.html.twig` | Nom qui mène à la fiche (`app_person`, `member-person`), à la place du bouton « Fiche » ; `data-member` sur chaque ligne, `data-email` pour la direction seule ; colonnes Rôle, Pôle, Compétences et expériences et filtres de tags sous `is_granted('ROLE_LEAD')` ; e-mail, Manager, filtre manager, badge « Désactivée » et actions sous `is_granted('ROLE_DIRECTION')`. |
| `src/Dto/TeamListFilter.php` | Drapeau `activeOnly`. |
| `src/Repository/UserRepository.php` | `findForTeamList()` filtre les actifs quand `activeOnly`. |
| `templates/team/new.html.twig`, `edit.html.twig`, `temporary_password.html.twig`, `_form.html.twig` | Lien retour et annulation vers `app_person_index`, libellé « Personnes ». |
| `templates/team/index.html.twig` | Supprimé : la liste vit dans `templates/person/index.html.twig`. |
| `templates/tag/index.html.twig`, `src/Validator/UniqueTeamEmail.php` | « page Équipe » / « liste de l'équipe » → « page Personnes ». |
| `templates/roadmap/_signal_tooltips.html.twig` | Variable `signals` facultative, à la place des signaux de la ligne. |
| `config/routes.yaml` | Trois redirections permanentes : `/equipe` → `app_person_index`, `/equipe/nouveau` → `app_team_new`, `/equipe/{id}/modifier` → `app_team_edit` (`keepQueryParams` pour les filtres). |
| `config/packages/security.yaml` | `logout.target: app_login` ; suppression de la règle `^/equipe`. |
| `src/Controller/TimeOffController.php` | `entitlements()` rend son gabarit sans passer par `page()` (ligne 74) : plus d'onglets, donc plus de compte à valider à calculer. |
| `templates/time_off/entitlements.html.twig` | Titre « Droits à congés », description inchangée, sans `_tabs.html.twig` ; titre de page « Droits à congés · Kadence ». |
| `templates/time_off/_tabs.html.twig` | Retrait de l'onglet « Compteurs » (ligne 10). |
| `src/Service/TimesheetBuilder.php` | Extraction du calcul des jours de `compose()` (lignes 84 à 91 et 109 à 120) dans une méthode privée partagée ; nouvelle méthode publique `daysOf(User, Week): list<TimesheetDay>`, qui ne lit que les temps de la semaine, les jours fériés, les congés et le maximum. |
| `src/Controller/PageController.php` | Injecte `HomeAgendaBuilder` ; passe `isGranted(TimeOffVoter::INBOX)`, `isGranted('ROLE_DIRECTION')` et `isGranted('ROLE_LEAD')`. |
| `templates/page/index.html.twig` | Réécrit : `PageHeader` « Accueil » avec salutation, puis les cinq sections dans l'ordre du pitch, ou un `EmptyState` avec un lien vers Ma semaine. La macro `shortcut` et les blocs « Suivre » et « Administration » disparaissent. |
| `tests/Unit/Twig/NavigationExtensionTest.php` | Fournisseur réaligné : `home`, `people` (liste, fiche, inscription, modification, mot de passe), `entitlements` (les deux routes), `time_off` inchangé pour les autres pages. |
| `tests/Controller/NavigationTest.php` | Zones par rôle ; entrée active sur `/personnes`, `/personnes/{id}`, `/personnes/nouveau`, `/conges/compteurs` ; aucune sur Mon compte ; les tests de l'ancien accueil sont remplacés par `HomeControllerTest`. |
| `tests/Controller/TeamControllerTest.php` | Adresses `/equipe…` → `/personnes…` ; tests des trois redirections ; refus des routes de gestion pour un lead (403). |
| `tests/Twig/Components/TeamListTest.php` | `testOnlyTheDirectionManagesTheTeam` remplacé : un lead et un membre de prod montent la liste, n'y voient pas les personnes désactivées, reçoivent un 403 sur `deactivate` ; un filtre de tag passé par un membre de prod et un filtre manager passé par un lead ne filtrent rien. |
| `tests/Controller/PersonControllerTest.php` | Liste par rôle : contenus visibles et absents (rôle, tags, e-mail, manager, désactivées, actions) ; aucune charge ni temps saisi. |
| `tests/Controller/TimeOffControllerTest.php` | `testTheTabsFollowWhatThePersonMayDo` : plus d'onglet `entitlements` ; la page des droits garde son contenu pour la direction et reste interdite aux autres. |
| `tests/Controller/SecurityControllerTest.php` | `testLogout` : la déconnexion mène à `/login`, puis la connexion mène à `/saisie` ; un lien profond (`/conges/a-valider`) suivi déconnecté est rendu après connexion. |
| `tests/Service/TimesheetBuilderTest.php` | `daysOf()` donne les mêmes jours incomplets que `build()` (jour férié, congé, maximum atteint, jour futur). |
| `tests/Controller/TimesheetControllerTest.php` | `testTheDashboardOffersTheTimesheet` supprimé : sans son assertion sur `home-timesheet`, il ne vérifiait plus rien ; le plafond de requêtes de `/saisie` reste à 8. |
| `tests/Controller/VocabularyTest.php` | `/personnes` parmi les pages de la prod. |
| `tests/Controller/ProjectControllerTest.php` | Ligne 55 : l'assertion sur `home-projects` est retirée. |
| `tests/Controller/TagControllerTest.php` | Ligne 90 : `/equipe` → `/personnes`. |
| `tests/ApplicationAvailabilityFunctionalTest.php` | Lignes 34 et 35 : `/personnes`, `/personnes/nouveau`. |
| `tests/e2e/navigation.spec.ts` | Géométrie de « Administration » pour la direction, inchangée ; pour la prod, pas de zone « Administration » et « Pilotage » visible dans le menu déroulant ; entrée active sur Personnes et Droits à congés. |
| `tests/e2e/team.spec.ts` | Navigation par `nav-people` ; adresses `/personnes` ; un membre de prod voit la liste sans action de gestion (au lieu d'un 403). |
| `tests/e2e/modal.spec.ts`, `tests/e2e/timesheet.spec.ts` | `goto('/equipe')` → `goto('/personnes')`. |
| `tests/e2e/login.spec.ts`, `tests/e2e/logout-prefetch.spec.ts` | Ils lisaient « Tableau de bord » dans le menu ; ils vérifient l'arrivée sur Ma semaine. |

## Hors scope

- **Renommage des classes, routes et gabarits internes** « team » : non embarqué (voir §Alternatives écartées).
- **Optimisation de `RoadmapBuilder::build()` pour l'accueil** (sans tronçons ni absences) : non embarquée tant que la mesure ne le demande pas.
- **Réalignement de la documentation** : relève de `/forge:sync` après livraison. À réaligner :
  - `DESIGN.md` : menu en trois zones, accueil, liste Personnes ;
  - `docs/product-backlog.md` : C1.6 et règles transverses « Profil » et « Lecture individuelle sans comparaison », pour la liste des personnes, et note `rappel-saisie` sur l'accueil ;
  - le changelog de la story 005, dont des décisions sont renversées.
- **Liens vers la page d'un projet** : la cible définitive vient de la story 016 (voir §Risques et mitigations).

## Impacts transverses

- **Cloisonnement des données** :
  - `HomeAgendaBuilder` filtre sur l'utilisateur de la session : `findOfUser()`, la règle des jours appliquée à ses temps, et ses feuilles comparées à `Lot::getOwner()` ;
  - `toDecide` passe par `findPendingDecidableBy()`, qui exclut déjà les demandes de la personne elle-même ;
  - les projets à surveiller et `ToReplan` ne sont calculés que sous `ROLE_LEAD`, comme sur la roadmap (`RoadmapController` ligne 60) ;
  - côté liste, colonnes et filtres sont décidés côté serveur et ne dépendent pas des props envoyées par le navigateur.
- **Déclinaisons / thèmes** : non. Les tokens du design system « Cadence » sont utilisés, en clair comme en sombre.
- **Traduction / i18n** : non. Libellés en français dans les gabarits : « Accueil », « Pilotage », « Personnes », « Droits à congés », intitulés des sections.
- **API / exposition externe** : non.
- **Droits d'accès** :
  - `GET /personnes` ouvert à `ROLE_USER` par la règle `^/` ;
  - gestion des personnes par `#[IsGranted('ROLE_DIRECTION')]` sur `TeamController`, et dans les actions de `TeamList` ;
  - `/conges/compteurs` reste derrière `access_control` et `denyAccessUnlessGranted` ;
  - aucun voter nouveau.
- **Emails / notifications** : non. Les liens profonds des e-mails de congés restent servis après connexion.
- **Migration de données** : aucune.
- **Comportement par défaut** : le nouveau menu et le nouvel accueil s'appliquent à tous ; `/equipe`, `/equipe/nouveau` et `/equipe/{id}/modifier` redirigent en 301.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `tests/Unit/Twig/NavigationExtensionTest.php` | unit | Chaque route vers sa section ; `app_time_off_entitlements(_period)` vers `entitlements` et non `time_off` ; `app_person`, `app_person_index` et `app_team_*` vers `people` ; `app_account_password` vers `null`. |
| `tests/Service/HomeAgendaBuilderTest.php` | functional (base) | Une feuille en dépassement dont la personne est responsable est dans `myLeaves`, celle d'un autre non ; une feuille `WithoutStart` n'y est pas ; une feuille déclarée terminée au-delà de son estimation n'est ni dans `myLeaves` ni dans `watchedProjects` ; une demande validée d'office n'est pas dans `myRequests` ; un projet à fin inconnue est dans `watchedProjects` avec `withProjects`, et absent sans ; une demande refusée il y a 6 jours est dans `myRequests`, une refusée il y a 8 jours non ; une demande annulée par la personne elle-même n'y est pas ; `toDecide` est vide sans `canDecide` ; `isEmpty()` est vrai pour une personne sans rien. |
| `tests/Service/TimesheetBuilderTest.php` | functional (base) | `daysOf()` et `build()` marquent les mêmes jours incomplets : jour férié, demi-journée de congé, maximum hebdomadaire atteint, jour futur. |
| `tests/Controller/HomeControllerTest.php` | functional (HTTP) | Prod : « Ma saisie » et « Mes congés », sans « À valider » ni « Projets à surveiller ». Manager : « À valider ». Lead : « Projets à surveiller ». Liens vers la semaine, la page À valider et la page du projet. État vide explicite. Aucune donnée d'une autre personne (journées, demandes). Nombre de requêtes égal avec peu ou beaucoup de projets, feuilles et demandes. |
| `tests/Controller/NavigationTest.php` | functional (HTTP) | Contenu et ordre des trois zones pour la direction, un lead et la prod ; « Administration » absente hors direction ; entrée active sur les pages listées au pitch. |
| `tests/Controller/PersonControllerTest.php` | functional (HTTP) | La liste par rôle : nom pour tous ; rôle et tags pour lead et direction ; e-mail, manager, désactivées et actions pour la direction seule ; pas de charge ni de temps saisi. |
| `tests/Twig/Components/TeamListTest.php` | functional (composant) | Montage par tous les rôles ; 403 sur `deactivate`/`reactivate` hors direction ; filtres interdits ignorés ; désactivées cachées hors direction ; filtres conservés après désactivation (existant). |
| `tests/Controller/TeamControllerTest.php` | functional (HTTP) | Parcours existants sous `/personnes` ; redirections 301 des trois anciennes adresses, filtres compris ; 403 pour un lead sur `/personnes/nouveau`. |
| `tests/Controller/TimeOffControllerTest.php` | functional (HTTP) | Onglets sans « Compteurs » ; page des droits inchangée pour la direction, 403 pour un lead. |
| `tests/Controller/SecurityControllerTest.php` | functional (HTTP) | Déconnexion vers `/login`, connexion vers `/saisie` ; lien profond conservé. |
| `tests/e2e/navigation.spec.ts` | E2E | Géométrie et découpage du menu sur ordinateur, écran bas et téléphone, par rôle. |
| `tests/e2e/team.spec.ts` | E2E | Inscription, filtres, désactivation et réactivation depuis Personnes ; liste en lecture pour la prod. |
| `tests/e2e/home.spec.ts` | E2E | Depuis l'accueil, un clic sur une journée incomplète ouvre Ma semaine à la bonne semaine. |

**Hors scope tests** :
- Pas de test E2E par section de l'accueil : le rendu et le cloisonnement sont couverts au niveau HTTP, le calcul au niveau service.
- Pas de nouveau test des signaux eux-mêmes : `RoadmapBuilderTest` et `RoadmapSignal` les couvrent déjà ; l'accueil ne fait que les filtrer.
- Pas de test du `RedirectController` du framework au-delà des trois redirections déclarées.

## Ordre d'exécution

1. [x] **Menu en trois zones**
   - Objectif : haut, « Pilotage » (Roadmap, Projets) pour tous, « Administration » pour la direction seule (Équipe provisoirement, Tags, Jours fériés) ; sections de `nav_section()` réalignées.
   - Fichiers : `templates/base.html.twig`, `src/Twig/NavigationExtension.php`, `tests/Unit/Twig/NavigationExtensionTest.php`, `tests/Controller/NavigationTest.php`, `tests/e2e/navigation.spec.ts`.
   - Vérification : `make phpunit-filter 'NavigationTest|NavigationExtensionTest'`, `make playwright-file tests/e2e/navigation.spec.ts`.
   - Commitable seule : oui.

2. [x] **Page Personnes**
   - Objectif : liste ouverte à tous avec colonnes, filtres et actions par rôle ; gestion sous `/personnes` ; redirections ; entrée « Personnes » dans « Pilotage » à la place d'« Équipe ».
   - Fichiers : `PersonController`, `TeamController`, `TeamList` (classe et gabarit), `TeamListFilter`, `UserRepository`, `templates/person/index.html.twig`, `templates/team/*`, `config/routes.yaml`, `security.yaml` (règle `^/equipe`), tests `TeamControllerTest`, `TeamListTest`, `PersonControllerTest`, `TagControllerTest`, `ApplicationAvailabilityFunctionalTest`, e2e `team.spec.ts`, `modal.spec.ts`, `timesheet.spec.ts`.
   - Vérification : `make phpunit-filter 'TeamControllerTest|TeamListTest|PersonControllerTest|TagControllerTest|ApplicationAvailability'`, `make playwright-file tests/e2e/team.spec.ts`.
   - Commitable seule : oui.

3. [x] **Droits à congés dans « Administration »**
   - Objectif : entrée de menu, titre de page, onglet retiré de Congés.
   - Fichiers : `TimeOffController`, `templates/time_off/entitlements.html.twig`, `_tabs.html.twig`, `base.html.twig`, `NavigationExtension`, `TimeOffControllerTest`, `NavigationTest`.
   - Vérification : `make phpunit-filter 'TimeOffControllerTest|NavigationTest'`, `make playwright-file tests/e2e/time-off.spec.ts`.
   - Commitable seule : oui.

4. [x] **Accueil « À faire »**
   - Objectif : extraction de la règle des jours dans `TimesheetBuilder`, `HomeAgendaBuilder` et `HomeAgenda`, nouvel accueil et libellé « Accueil » dans le menu.
   - Fichiers : `TimesheetBuilder`, `HomeAgendaBuilder`, `HomeAgenda`, `PageController`, `templates/page/index.html.twig`, `templates/page/_section.html.twig`, tests `TimesheetBuilderTest`, `HomeAgendaBuilderTest`, `HomeControllerTest`, `TimesheetControllerTest`, `ProjectControllerTest`, e2e `home.spec.ts`.
   - Vérification : `make phpunit-filter 'TimesheetBuilderTest|HomeAgendaBuilderTest|HomeControllerTest|TimesheetControllerTest|ProjectControllerTest'` (plafond de `/saisie` toujours à 8), `make playwright-file tests/e2e/home.spec.ts`.
   - Commitable seule : oui.

5. [x] **Arrivée sur Ma semaine**
   - Objectif : `logout.target: app_login`, lien profond conservé.
   - Fichiers : `config/packages/security.yaml`, `tests/Controller/SecurityControllerTest.php`.
   - Vérification : `make phpunit-filter SecurityControllerTest`, `make playwright-file tests/e2e/logout-prefetch.spec.ts`.
   - Commitable seule : oui.

6. [x] **QA finale**
   - Objectif : suite complète verte, analyse statique et style.
   - Fichiers : aucun nouveau.
   - Vérification : `make phpunit`, `make lint`, `make playwright` (serveur lancé).
   - Commitable seule : non (clôture).

## Critères de sortie

- [x] `nav_section()` renvoie `entitlements` pour les deux routes des droits à congés, et `people` pour la liste, la fiche et les routes de gestion d'une personne.
- [x] Aucune route sous `/equipe` hors des trois redirections ; `access_control` ne mentionne plus `^/equipe`.
- [x] `TeamList` refuse `deactivate` et `reactivate` hors direction, et ignore les filtres que le rôle ne voit pas.
- [x] Le nombre de requêtes de l'accueil est identique avec peu ou beaucoup de projets, feuilles et demandes.
- [x] Le plafond de requêtes de `/saisie` reste à 8 (`TimesheetControllerTest`).
- [x] Suite de tests du projet verte (`make phpunit`, `make playwright`), sans nouvelle régression.
- [x] `make lint` propre (PHP-CS-Fixer, PHPStan niveau 10).
- [x] Les liens des e-mails de congés ouvrent leur page après connexion.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| L'accueil devient lent : il ordonnance tout le planning à chaque affichage, pour chaque rôle | moyenne | Réutiliser `RoadmapBuilder::build()`, dont le nombre de requêtes est testé constant. Mesurer avec le profiler sur les fixtures. Si besoin, ajouter une variante sans tronçons ni absences (§Questions ouvertes). |
| Fuite de tags, d'e-mails ou de personnes désactivées vers la prod via les props d'URL du Live Component | moyenne | Le filtre et les colonnes sont décidés côté serveur à partir des rôles, jamais des props. Tests de composant avec des props forgées. |
| Une sous-page oubliée n'allume plus la bonne entrée (préfixe `app_time_off_` qui masque `entitlements`) | moyenne | Ordre explicite de `SECTIONS`, testé unitairement route par route. |
| Divergence entre les journées incomplètes de l'accueil et celles de Ma semaine | faible | Une seule méthode calcule les jours pour `build()` et `daysOf()` ; test d'égalité. |
| Dépendance aux stories 015 (« lot », « Pôle ») et 016 (cible des liens projet) | moyenne | Si 016 n'est pas livrée, les liens « Mes lots » et « Projets à surveiller » visent `app_roadmap_project` (lisible par tous), puis basculent sur `app_project_show`. Si 015 ne l'est pas, les libellés existants sont repris et alignés par 015. |
| Géométrie du menu à trois zones sur un écran bas (recouvrement) | faible | `mt-auto` seulement sur « Administration », `shrink-0` partout ; scénarios E2E existants étendus. |
| Tests E2E dépendants de `/equipe` ou de l'ancien accueil oubliés | moyenne | `grep -rn "/equipe\|home-\|nav-team\|nav-dashboard" tests` avant la clôture de chaque tranche. |

## Questions ouvertes

- **Coût de l'accueil** : faut-il une variante de `RoadmapBuilder` qui saute le découpage en tronçons et les absences, inutiles à l'accueil ? Options : (a) réutiliser `build()` tel quel et mesurer (proposé) ; (b) ajouter tout de suite `buildSignals()`, au prix d'une seconde entrée publique à tenir. **Tranché : (a).** Sur les fixtures, l'accueil d'un lead se calcule en 26 ms, contre 24 ms pour la roadmap seule.
