# Plan technique — Découper chaque projet en lots et sous-lots estimés, chacun confié à un responsable

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/002-f-projets-lots-sous-lots/`
> **Amont** : `pitch.md`

## Approche retenue

Deux nouvelles entités portent le référentiel. `Project` est une enveloppe qui n'a qu'un titre et une description. `Lot` est **auto-référencée** : un lot sans parent est un lot, un lot avec parent est un sous-lot. Chaque lot connaît aussi son projet, directement, y compris quand c'est un sous-lot. Une **feuille** est un lot sans enfant. Seule une feuille porte `estimateDays` (entier, en jours entiers) et `owner` (le responsable, une personne). Les stories suivantes (saisie, affectation, jalons) pointeront donc vers une seule entité, `Lot`. Les totaux d'un lot découpé et d'un projet ne sont jamais enregistrés : un service les calcule à la lecture, à partir de l'arbre chargé en une seule requête.

Toute la logique métier vit dans `ProjectManager`, sur le modèle de `TeamManager` : création, modification, suppression en cascade, profondeur limitée à deux niveaux, **bascule** (le premier sous-lot reprend l'estimation et le responsable du lot, qui les perd) et **remontée** (quand on supprime le dernier sous-lot, ses valeurs remontent sur le lot). Les contrôleurs restent minces et les formulaires se lient à des DTO (`ProjectInput`, `LotInput`) validés par attributs. L'unicité insensible à la casse est vérifiée par des contraintes de classe sur ces DTO, comme `UniqueTeamEmail`. Les droits combinent deux mécanismes : `ROLE_LEAD` pour tout ce qui touche à la structure (créer, supprimer, désigner un responsable), et un voter `LOT_EDIT` pour la modification d'une feuille (un lead, ou son responsable s'il est actif). Les écrans suivent le modèle de l'équipe : une liste, une page projet qui affiche l'arbre, des pages de formulaire dédiées avec un bouton « Enregistrer et ajouter un autre », et des modales de confirmation pour la suppression.

### Mécanismes mobilisés

- **Association Doctrine auto-référencée** (`Lot::$parent` ManyToOne / `Lot::$children` OneToMany) : deux niveaux sous le projet, sans bibliothèque d'arbre. La profondeur est bornée par le service.
- **`cascade: ['remove']` au niveau ORM** sur `Project::$lots` et `Lot::$children` : suppression en cascade par Doctrine. SQLite n'applique pas les clés étrangères dans la configuration actuelle (`config/packages/doctrine.yaml` n'active pas `foreign_keys`), un `ON DELETE CASCADE` en base ne ferait donc rien.
- **`#[ORM\OrderBy(['id' => 'ASC'])]`** sur les collections de lots : les lots et sous-lots s'affichent dans l'ordre de création.
- **Requêtes avec jointures chargées dans les repositories** (`p.lots`, `l.owner`, `l.children`) : le détail et la liste chargent l'arbre complet en une requête, sans N+1. La page de détail s'appuie sur `#[MapEntity(expr: 'repository.findOneForDetail(id)')]`.
- **Voter** (`LotVoter`, attribut `LOT_EDIT`) : décision au niveau de l'objet (« cette personne est-elle responsable de cette feuille ? »), qui ne s'exprime pas avec un simple rôle.
- **`#[IsGranted('ROLE_LEAD')]`** sur les actions de gestion. Pas de changement dans `access_control` : la règle `^/` → `ROLE_USER` couvre déjà la consultation de `/projets` et `/lots`.
- **Contraintes de classe sur mesure** (`UniqueProjectTitle`, `UniqueLotTitle`) : le repository renvoie les titres des voisins et la comparaison se fait en PHP avec `mb_strtolower`, parce que la fonction `LOWER()` de SQLite ne gère que l'ASCII (« Été » ≠ « été »).
- **Contraintes natives** : `NotBlank`, `Length`, `Positive` sur `estimateDays`. Le champ `IntegerType` refuse déjà les décimaux.
- **`ChoiceType` pour le responsable** : les choix viennent de `UserRepository::findActiveForOwnerChoice()`, plus le responsable actuel s'il est désactivé (libellé « désactivée »), pour qu'une simple modification du titre ne le perde pas. Placeholder « À désigner ». Refuser toute autre personne désactivée est assuré par la validation des choix du formulaire.
- **Options de formulaire** `with_owner` et `with_estimate` sur `LotType` : le même formulaire sert au lead (tous les champs), au responsable (sans champ responsable) et au lot découpé (ni estimation ni responsable).
- **Exception de domaine** (`LotDepthException`, sur le modèle de `LastActiveDirectorException`) : un sous-lot sous un sous-lot est refusé par le service. Le contrôleur répond 404 avant même d'afficher le formulaire, l'interface ne proposant jamais ce lien ; l'exception reste le garde-fou du service.
- **Clé de tri `u($title)->ascii()->lower()`** (symfony/string, déjà présent) : tri alphabétique français des projets, où « Évolution » se range à E. Pas besoin d'ajouter `ext-intl`.
- **Composants Twig Paper** (`Table`, `Badge`, `Button`, `Modal`, `Card`) et **POST + jeton CSRF** pour les suppressions, comme pour les actions de l'équipe.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Trois entités `Project`, `Lot`, `SubLot` | Estimation et responsable dupliqués, et la saisie, l'affectation et les jalons devraient pointer vers `Lot` **ou** `SubLot` (deux relations nullables partout). |
| Bibliothèque d'arbre générique (nested set, closure table) | La profondeur est fixe (deux niveaux sous le projet) : une dépendance et une complexité sans usage. |
| Estimation en demi-journées (entier) ou en jours décimaux | Le pitch est passé en jours entiers. Un décimal serait en plus renvoyé en chaîne par Doctrine, avec des conversions partout sous PHPStan niveau 10. |
| Totaux enregistrés sur le lot et le projet | Risque de divergence avec les feuilles, alors que le calcul à la lecture est trivial à l'échelle d'un référentiel interne. |
| Contrainte d'unicité en base | `LOWER()` de SQLite ne gère que l'ASCII, et un `parent_id` NULL rend l'unicité « dans le même parent » inexprimable pour les lots de premier niveau. |
| `ON DELETE CASCADE` en base | Les clés étrangères ne sont pas appliquées par SQLite dans la configuration actuelle. La cascade ORM est fiable, quel que soit le moteur. |
| Formulaires dans la page projet (Turbo Frames) | Plus de code et de scénarios E2E. Des pages dédiées avec « Enregistrer et ajouter un autre » tiennent l'objectif des 5 minutes. |
| Droit du responsable porté par un rôle | La décision dépend de l'objet (qui est responsable de *cette* feuille) : c'est le rôle d'un voter. |

## Modèle de données

### Nouvelle structure `Project`

`src/Entity/Project.php` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `title` | string(150), colonne `title` | non | unicité insensible à la casse vérifiée par `UniqueProjectTitle` (pas en base) |
| `description` | text, colonne `description` | oui | |
| `lots` | OneToMany (`Lot`, `mappedBy: project`) | — | tous les lots du projet, sous-lots compris ; `cascade: ['remove']` ; tri `id ASC` |

### Nouvelle structure `Lot`

`src/Entity/Lot.php` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `project` | ManyToOne (`Project`, `inversedBy: lots`), colonne `project_id` | non | renseigné aussi pour un sous-lot (identique à celui du parent, garanti par le service) ; indexé |
| `parent` | ManyToOne (`Lot`, `inversedBy: children`), colonne `parent_id` | oui | vide = lot, renseigné = sous-lot ; un parent n'a jamais lui-même de parent (garanti par le service) ; indexé |
| `children` | OneToMany (`Lot`, `mappedBy: parent`) | — | `cascade: ['remove']` ; tri `id ASC` |
| `title` | string(150), colonne `title` | non | unicité insensible à la casse parmi les voisins (même projet et même parent), vérifiée par `UniqueLotTitle` |
| `description` | text, colonne `description` | oui | |
| `estimateDays` | entier, colonne `estimate_days` | oui | > 0 ; vide sur une feuille = « à estimer » ; toujours vide sur un lot découpé (garanti par le service) |
| `owner` | ManyToOne (`User`), colonne `owner_id` | oui | vide sur une feuille = « à désigner » ; toujours vide sur un lot découpé ; indexé |

Aucun cloisonnement (outil mono-organisation), pas d'horodatage ni de traduction. L'estimation initiale conservée après la première saisie (règle 17 du pitch) n'est **pas** créée ici : elle arrivera avec la story de saisie, la seule à pouvoir la figer.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Entity/Project.php` | Entité projet (titre, description, lots). |
| `src/Entity/Lot.php` | Entité lot / sous-lot auto-référencée, avec `isLeaf()` et `isSubLot()` comme prédicats de structure. |
| `src/Repository/ProjectRepository.php` | `findAllForList(?User $owner)` (arbre chargé en une requête, filtre « mes responsabilités », tri alphabétique), `findOneForDetail(int $id)`, `findTitlesExcept(?int $id)`. |
| `src/Repository/LotRepository.php` | `findSiblingTitles(Project, ?Lot $parent, ?int $exceptId)`. |
| `migrations/VersionYYYYMMDDHHMMSS.php` | Création des tables `project` et `lot` (générée par `make migration`, `down()` supprime les deux tables). |
| `src/Service/ProjectManager.php` | Création, modification et suppression des projets et lots ; bascule, remontée, profondeur limitée, invariant « seule une feuille porte estimation et responsable ». |
| `src/Service/ProjectRollup.php` | Construit l'arbre de lecture d'un projet : totaux, statut partiel, compteurs « à estimer », « à désigner », « à redésigner », signal « à découper ». |
| `src/Model/ProjectSummary.php` | Vue en lecture seule d'un projet (projet, lots de premier niveau, total, partiel, compteurs, à découper). |
| `src/Model/LotSummary.php` | Vue en lecture seule d'un lot (lot, sous-lots, total, partiel, statut de feuille). |
| `src/Exception/LotDepthException.php` | Refus d'un sous-lot sous un sous-lot. |
| `src/Security/Voter/LotVoter.php` | `LOT_EDIT` : un lead, ou le responsable actif d'une feuille. |
| `src/Dto/ProjectInput.php` | Données du formulaire projet + `fromProject()`. |
| `src/Dto/LotInput.php` | Données du formulaire lot + `forLotOf(Project)`, `forSubLotOf(Lot $parent)` (pré-remplissage du premier sous-lot) et `fromLot()`. |
| `src/Validator/UniqueProjectTitle.php` / `UniqueProjectTitleValidator.php` | Titre de projet unique, sans tenir compte de la casse. |
| `src/Validator/UniqueLotTitle.php` / `UniqueLotTitleValidator.php` | Titre de lot unique parmi ses voisins, sans tenir compte de la casse. |
| `src/Validator/TitleComparison.php` | Comparaison de titres insensible à la casse en PHP, partagée par les deux validateurs d'unicité. |
| `src/Form/ProjectType.php` | Formulaire projet (titre, description). |
| `src/Form/LotType.php` | Formulaire lot avec les options `with_owner`, `with_estimate` et `current_owner`. |
| `src/Controller/ProjectController.php` | `/projets` : liste (+ filtre), détail, création, modification, suppression. |
| `src/Controller/LotController.php` | Ajout d'un lot, ajout d'un sous-lot (pré-rempli si bascule), modification (voter), suppression. |
| `templates/project/index.html.twig` | Liste des projets : total, partiel, badges de signaux, filtre « Mes responsabilités ». |
| `templates/project/show.html.twig` | Arbre du projet : lots, sous-lots, estimations, responsables, badges, actions selon les droits, feuilles de la personne connectée mises en évidence. |
| `templates/project/_lot_row.html.twig` | Ligne d'un lot ou sous-lot dans l'arbre, avec ses actions (template inclus : une macro ne peut pas être appelée depuis le contenu d'un composant `<twig:Table:Body>`). |
| `templates/project/_estimate.html.twig` / `_signals.html.twig` | Total avec badge « partiel », et badges « à estimer », « à désigner », « à redésigner », partagés par la liste et la page projet. |
| `templates/project/new.html.twig` / `edit.html.twig` / `_form.html.twig` | Formulaires projet. |
| `templates/lot/new.html.twig` / `edit.html.twig` / `_form.html.twig` | Formulaires lot, avec le bouton « Enregistrer et ajouter un autre » à la création. |
| `fixtures/ProjectFixtures.php` | Projets de démonstration : un lot découpé, des feuilles « à estimer », « à désigner » et « à redésigner », une feuille dont `prod@example.com` est responsable, un projet sans lot. |
| `tests/Unit/Service/ProjectManagerTest.php` | Bascule, remontée, profondeur, invariant sur les lots découpés. |
| `tests/Unit/Service/ProjectRollupTest.php` | Totaux, partiel, compteurs, à découper, à redésigner. |
| `tests/Unit/Security/Voter/LotVoterTest.php` | Matrice de décision du voter. |
| `tests/Controller/ProjectControllerTest.php` | Droits, création, unicité, suppression en cascade, filtre. |
| `tests/Controller/LotControllerTest.php` | Droits (lead, responsable, autre), validation de l'estimation, bascule pré-remplie, profondeur, responsable désactivé. |
| `tests/Support/CreatesProjects.php` | Aides de test pour créer projets et lots. |
| `tests/e2e/projects.spec.ts` | Parcours lead (projet de trois lots) et parcours responsable (modifier sa feuille). |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Repository/UserRepository.php` | Ajouter `findActiveForOwnerChoice()` (personnes actives, triées par nom puis prénom). |
| `templates/base.html.twig` | Ajouter l'entrée « Projets » (`data-test="nav-projects"`) dans la navigation, visible par tous. |
| `templates/page/index.html.twig` | Ajouter le raccourci « Projets » (`home-projects`) sur le tableau de bord. |
| `fixtures/AppFixtures.php` | Enregistrer des références vers les personnes pour `ProjectFixtures`. |
| `tests/ApplicationAvailabilityFunctionalTest.php` | Ajouter `/projets` et `/projets/nouveau`. |
| `docs/story/002-f-projets-lots-sous-lots/pitch.md` | Règle 7 et critère d'acceptation en jours entiers ; questions ouvertes tranchées. |
| `templates/team/new.html.twig`, `templates/team/edit.html.twig`, `templates/account/password.html.twig` | Ajout hors plan, demandé après l'implémentation : formulaires en pleine largeur (sauf le changement de mot de passe obligatoire, qui reste un écran d'accès centré). |

## Hors scope

- **Estimation initiale et révision bornée après saisie (règle 17), blocage des suppressions dès qu'un temps est saisi (règle 18)** : il n'existe pas encore de temps saisis ; la story `saisie-quotidienne` ajoutera le champ et les garde-fous.
- **Bascule d'un lot qui a déjà des temps saisis** : question laissée à `saisie-quotidienne`.
- **Contrainte d'unicité ou cascade en base** : écartées (voir §Alternatives écartées).
- **Activation des clés étrangères SQLite** : changement transverse de la configuration de la base, hors de cette story.
- **Pagination de la liste des projets** : quelques dizaines de projets au plus ; à reconsidérer avec l'archivage (V2).

## Impacts transverses

- **Cloisonnement des données** : aucun. Outil mono-organisation, tous les projets sont visibles par toute personne connectée.
- **Déclinaisons / thèmes** : non.
- **Traduction / i18n** : libellés en français écrits directement dans les templates, comme les écrans existants (interface en français uniquement, convention du backlog).
- **API / exposition externe** : non.
- **Droits d'accès** : nouveau voter `LotVoter` (`LOT_EDIT`) et `#[IsGranted('ROLE_LEAD')]` sur les actions de gestion. `access_control` inchangé.
- **Emails / notifications** : non (pas de notification de désignation, question du pitch tranchée).
- **Migration de données** : création de deux tables, aucune reprise de l'existant.
- **Comportement par défaut** : nouvelle entrée « Projets » dans la navigation et sur le tableau de bord pour tout le monde. Les boutons de création, modification et suppression ne s'affichent que si la personne a le droit correspondant.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Service/ProjectManager.php` | unit | Création d'un projet et d'un lot. Premier sous-lot : il reçoit les valeurs saisies et le lot perd estimation et responsable. Deuxième sous-lot : le lot reste inchangé. Un sous-lot sous un sous-lot lève `LotDepthException`. Supprimer le dernier sous-lot fait remonter estimation et responsable sur le lot ; supprimer un sous-lot parmi d'autres ne fait rien remonter. Modifier un lot découpé ne lui donne jamais d'estimation ni de responsable. |
| `src/Service/ProjectRollup.php` | unit | Total d'un lot découpé et d'un projet, statut partiel dès qu'une feuille est « à estimer », compteurs « à estimer », « à désigner », « à redésigner » (responsable désactivé), projet sans lot « à découper ». |
| `src/Security/Voter/LotVoter.php` | unit | Lead et direction : accordé. Responsable actif d'une feuille : accordé. Responsable désactivé : refusé. Autre membre de prod : refusé. Lot découpé pour un non-lead : refusé. |
| `src/Controller/ProjectController.php` | functional | Prod consulte la liste et le détail, mais création, modification et suppression lui renvoient 403. Le lead crée un projet. Titre en double refusé à la création comme au renommage, y compris avec une casse ou une capitale accentuée différente (« Été » / « été »). Nombre de requêtes de la page projet identique pour un petit et un grand projet. La suppression emporte lots et sous-lots. Le filtre « Mes responsabilités » ne garde que les projets concernés. Badge « à découper » sur un projet vide. Tri alphabétique. |
| `src/Controller/LotController.php` | functional | Le lead ajoute un lot, et « Enregistrer et ajouter un autre » renvoie sur un formulaire vierge. Estimation 0, négative ou décimale refusée. Le formulaire du premier sous-lot est pré-rempli ; celui d'un sous-lot de sous-lot répond 404. Le formulaire d'un lot découpé ne propose ni estimation ni responsable. Renommer un lot avec le titre d'un voisin est refusé. Le responsable prod modifie sa feuille (pas de champ responsable) et reçoit 403 sur la feuille d'un autre et sur un lot découpé. La liste des responsables ne contient pas de personne désactivée, sauf le responsable actuel, qui reste sélectionné après une modification du titre. |
| `src/Validator/Unique*TitleValidator.php` | functional | Couverts par les tests de contrôleur (unicité parmi les voisins, pas au-delà : deux sous-lots de même titre dans deux lots différents sont acceptés). |
| `tests/e2e/projects.spec.ts` | E2E | Le lead crée un projet, enchaîne trois lots avec « Enregistrer et ajouter un autre », estime et désigne, et voit le total. Le responsable prod modifie l'estimation de sa feuille. |

**Hors scope tests** :

- Pas de test unitaire dédié aux DTO et formulaires : leurs contraintes sont vérifiées par les tests de contrôleur.
- Pas de test du chronométrage « trois lots en moins de cinq minutes » : il se valide manuellement à la recette. Le parcours E2E prouve qu'il se fait sans détour.
- Pas de test de charge sur la liste : volume attendu de quelques dizaines de projets.

## Ordre d'exécution

1. [x] **Modèle de données et migration**
   - Objectif : entités `Project` et `Lot`, repositories (méthodes de lecture), migration générée et relue.
   - Fichiers : `src/Entity/Project.php`, `src/Entity/Lot.php`, `src/Repository/ProjectRepository.php`, `src/Repository/LotRepository.php`, `migrations/Version*.php`.
   - Vérification : `symfony console doctrine:schema:validate` ; `make migrate` puis retour arrière d'une version sans erreur ; `make lint`.
   - Commitable seule : oui.

2. [x] **Service métier et calcul des totaux**
   - Objectif : `ProjectManager` (bascule, remontée, profondeur, invariant) et `ProjectRollup` avec ses vues en lecture seule.
   - Fichiers : `src/Service/ProjectManager.php`, `src/Service/ProjectRollup.php`, `src/Model/ProjectSummary.php`, `src/Model/LotSummary.php`, `src/Exception/LotDepthException.php`, `src/Dto/ProjectInput.php`, `src/Dto/LotInput.php`, tests unitaires associés.
   - Vérification : `make phpunit-filter ProjectManagerTest`, `make phpunit-filter ProjectRollupTest`.
   - Commitable seule : oui.

3. [x] **Voter**
   - Objectif : `LOT_EDIT` pour un lead ou le responsable actif d'une feuille.
   - Fichiers : `src/Security/Voter/LotVoter.php`, `tests/Unit/Security/Voter/LotVoterTest.php`.
   - Vérification : `make phpunit-filter LotVoterTest`.
   - Commitable seule : oui.

4. [x] **Validation et formulaires**
   - Objectif : contraintes d'unicité, `ProjectType`, `LotType` (options `with_owner`, `with_estimate`, `current_owner`), choix des responsables.
   - Fichiers : `src/Validator/UniqueProjectTitle*.php`, `src/Validator/UniqueLotTitle*.php`, `src/Validator/TitleComparison.php`, `src/Form/ProjectType.php`, `src/Form/LotType.php`, `src/Repository/UserRepository.php`.
   - Vérification : `make lint` (testés via les contrôleurs aux étapes 5 et 6).
   - Commitable seule : oui.

5. [x] **Écrans projets, navigation et fixtures**
   - Objectif : liste (tri, filtre, badges), détail en arbre, création, modification, suppression en cascade ; entrée de navigation et raccourci d'accueil ; projets de démonstration.
   - Fichiers : `src/Controller/ProjectController.php`, `templates/project/*`, `templates/base.html.twig`, `templates/page/index.html.twig`, `fixtures/ProjectFixtures.php`, `fixtures/AppFixtures.php`, `tests/Support/CreatesProjects.php`, `tests/Controller/ProjectControllerTest.php`, `tests/ApplicationAvailabilityFunctionalTest.php`.
   - Vérification : `make db-reset` ; `make phpunit-filter ProjectControllerTest`, dont `testProjectPageQueryCountDoesNotGrowWithItsLots` qui compare au profiler le nombre de requêtes d'un petit et d'un grand projet.
   - Commitable seule : oui.

6. [x] **Écrans lots**
   - Objectif : ajout (avec « Enregistrer et ajouter un autre »), ajout d'un sous-lot avec formulaire pré-rempli à la bascule, modification selon le voter, suppression avec remontée.
   - Fichiers : `src/Controller/LotController.php`, `templates/lot/*`, `templates/project/show.html.twig`, `tests/Controller/LotControllerTest.php`.
   - Vérification : `make phpunit-filter LotControllerTest`.
   - Commitable seule : oui.

7. [x] **E2E et QA finale**
   - Objectif : parcours lead et responsable de bout en bout, suite complète au vert.
   - Fichiers : `tests/e2e/projects.spec.ts`.
   - Vérification : `make serve` puis `make playwright-file tests/e2e/projects.spec.ts` ; `make lint` ; `make phpunit` ; `make playwright`.
   - Commitable seule : oui.

## Critères de sortie

- [x] `symfony console doctrine:schema:validate` sans erreur, et la migration s'applique puis s'annule sans erreur.
- [x] La page détail d'un projet exécute un nombre de requêtes SQL constant, quel que soit le nombre de lots et sous-lots (aucun N+1, vérifié au profiler par `testProjectPageQueryCountDoesNotGrowWithItsLots`).
- [x] Aucun QueryBuilder ni DQL hors de `src/Repository/`, aucune logique métier dans les contrôleurs ni les entités (prédicats de structure `isLeaf()` / `isSubLot()` exceptés).
- [x] Chaque critère d'acceptation du pitch est couvert par au moins un test PHPUnit ou Playwright, sauf le chronométrage (recette manuelle).
- [x] `make phpunit` vert, sans régression sur les tests existants.
- [x] `make lint` propre (PHP-CS-Fixer et PHPStan niveau 10).
- [x] `make playwright` vert.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| N+1 au chargement de l'arbre (collections `children` non hydratées) | moyenne | Jointures chargées sur `p.lots`, `l.owner` et `l.children` dans les méthodes de repository ; test fonctionnel qui compare le nombre de requêtes d'un petit et d'un grand projet (requête d'amorçage d'abord, sinon le profiler compte aussi les `INSERT` de préparation). |
| Lots orphelins, les clés étrangères n'étant pas appliquées par SQLite | moyenne | Suppression toujours par l'ORM (cascade `remove`). La purge SQL des tests E2E supprime les lots avant les projets. |
| Responsable désactivé effacé en silence quand on modifie le titre d'une feuille | moyenne | Le responsable actuel reste dans les choix même désactivé ; test fonctionnel dédié. |
| Invariant « seule une feuille porte estimation et responsable » contourné par des appels directs aux setters (fixtures, tests) | faible | `ProjectManager` est le seul point d'écriture applicatif ; le calcul des totaux ignore l'estimation d'un lot découpé ; les fixtures passent par des feuilles cohérentes. |
| Deux leads créent au même instant deux projets de même titre (pas de contrainte en base) | faible | Accepté : outil interne, peu de leads. Le validateur couvre le cas nominal. |
| Tri alphabétique incorrect pour les titres accentués | faible | Clé de tri `u($title)->ascii()->lower()` ; test fonctionnel avec un titre qui commence par « É ». |

## Questions ouvertes

- **Rendu de l'arbre sur mobile** : tableau imbriqué avec défilement horizontal, ou cartes empilées ? À trancher à l'implémentation avec les composants Paper (`DESIGN.md`). → tranché : tableau imbriqué dans un conteneur à défilement horizontal, comme la liste de l'équipe ; rendu sur téléphone à vérifier en recette.
- **Longueurs maximales** : titre à 150 caractères, description libre (texte). À confirmer à l'implémentation si un besoin contraire apparaît dans les données du tableur. → tranché : 150 caractères pour le titre, texte libre pour la description.
