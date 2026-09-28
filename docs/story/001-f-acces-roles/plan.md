# Plan technique — Inscrire l'équipe avec un rôle pour que chacun ne voie que ce qui le concerne

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/001-f-acces-roles/`
> **Amont** : `pitch.md`

## Approche retenue

L'entité `User` existante devient le « membre de l'équipe » : elle gagne une identité (prénom, nom), un rôle unique porté par un backed enum, un statut actif et un marqueur de mot de passe provisoire ; la colonne JSON `roles` disparaît au profit de `getRoles()` dérivé de l'enum. Les droits reposent sur la hiérarchie de rôles native du composant Security (`ROLE_DIRECTION` ⊃ `ROLE_LEAD` ⊃ `ROLE_PROD`), un `access_control` sur `^/equipe` et `is_granted()` dans la navigation. Deux points d'accroche sécurité complètent le socle : un `UserChecker` refuse la connexion d'un compte désactivé (message générique grâce à `expose_security_errors: none`, valeur par défaut), et un subscriber `kernel.request` placé après le firewall déconnecte immédiatement une session dont le compte a été désactivé et confine une personne à mot de passe provisoire sur la page de changement.

Toute la logique métier (inscription, modification, désactivation/réactivation, réinitialisation, changement de mot de passe, garde-fou « dernier membre actif de la direction ») vit dans un service `TeamManager`, appelé par des contrôleurs minces et par la commande console d'installation `app:create-director`. Les formulaires se lient à des DTO validés par attributs (`TeamMemberInput`, `ChangePasswordInput`), jamais à l'entité. Le mot de passe provisoire est généré par un service dédié, stocké brièvement en session (indexé par personne) et affiché une seule fois sur une page qui le retire de la session à la lecture (les toasts flash se ferment en 5 s et Turbo impose une redirection après POST). Les tentatives de connexion sont limitées par le `login_throttling` du firewall.

### Mécanismes mobilisés

- **`role_hierarchy` (security.yaml)** : cumul des droits direction ⊃ lead ⊃ prod sans stocker plusieurs rôles ; `getRoles()` ne renvoie que le rôle de l'enum.
- **`access_control` + `#[IsGranted('ROLE_DIRECTION')]`** : garde-fou grossier sur `^/equipe` doublé d'un attribut sur le contrôleur — pas de voter, la règle est un simple contour de rôle, sans décision fine par objet.
- **`UserCheckerInterface::checkPreAuth()`** : lève `DisabledException` pour un compte inactif ; le firewall la masque en « identifiants invalides » (règle 11 du pitch) sans code supplémentaire.
- **`UserLoaderInterface` sur `UserRepository`** : `loadUserByIdentifier()` met l'identifiant en minuscules avant la recherche — SQLite compare les chaînes en respectant la casse.
- **Event subscriber `kernel.request` (priorité inférieure au firewall)** : coupure immédiate d'un compte désactivé via `Security::logout(false)` et redirection forcée vers le changement de mot de passe tant que `mustChangePassword` est vrai.
- **`Security::login()`** : reconnexion programmatique après un changement de mot de passe — sans elle, le hash modifié fait détecter un « utilisateur changé » et déconnecte la personne à la requête suivante.
- **Contraintes de validation natives** : `NotBlank`, `Email`, `Length(min: 12)`, `PasswordStrength`, `UserPassword` (groupe du changement volontaire).
- **Contraintes sur mesure** : `UniqueTeamEmail` (contrainte de classe sur `TeamMemberInput`, via `UserRepository`, en excluant la personne éditée et en distinguant le cas « compte désactivé ») et `NotCurrentPassword` (le nouveau mot de passe diffère de l'actuel, via le password hasher).
- **`ByteString::fromRandom()` (symfony/string)** : génération du mot de passe provisoire sur un alphabet sans caractères ambigus.
- **Commande console (`#[AsCommand]`)** : création du premier compte direction à l'installation, réutilisant `TeamManager`.
- **Composants Twig Paper** (`Table`, `Badge`, `Button`, `Modal`, `Alert`, `Card`) : écrans Équipe et Mon compte, confirmations de désactivation/réinitialisation en modale ; actions d'état en POST avec jeton CSRF.
- **`login_throttling` (firewall, symfony/rate-limiter)** : blocage temporaire après échecs répétés (défaut : 5 par minute et par couple identifiant/IP) — ajouté sur finding de review.
- **Contrôleur Stimulus `dev-login`** : raccourci de connexion en dev (sélecteur de compte qui remplit e-mail et mot de passe), la liste des comptes n'étant calculée que si `kernel.environment` vaut `dev` — ajouté à la demande pendant la review.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Conserver la colonne JSON `roles` avec un tableau à un élément | « Un seul rôle par personne » ne serait qu'une convention ; l'enum le garantit par le typage et suit la convention `src/Enum/Type/`. |
| Voter dédié à la gestion d'équipe | Aucune décision par objet : un contour `ROLE_DIRECTION` suffit ; un voter ajouterait une classe sans règle à porter. |
| Lier les formulaires directement à l'entité `User` | Setters typés `non-empty-string` incompatibles avec un champ vidé, risque de mass-assignment sur `active`/`password`, et le garde-fou « dernier directeur » devrait comparer l'état avant/après binding. |
| Afficher le mot de passe provisoire dans un flash | Les toasts se ferment en 5 s : la direction risquerait de le perdre avant de l'avoir noté. |
| Rendre le mot de passe directement dans la réponse au POST | Turbo n'affiche pas une réponse 200 à une soumission de formulaire : il faut une redirection. |
| Laisser la session d'un compte désactivé vivre jusqu'à expiration | Coût de la coupure immédiate marginal (même subscriber que le changement forcé) ; décision reportée dans le pitch (règle 9). |
| `NotCompromisedPassword` (Have I Been Pwned) | Appel réseau externe, en tension avec l'anti-objectif « pas d'intégration », à désactiver en test. |

## Modèle de données

### Nouvel enum `Role`

`src/Enum/Type/Role.php` : backed string enum `direction` | `lead` | `prod`, avec une méthode `securityRole(): string` renvoyant `ROLE_DIRECTION` | `ROLE_LEAD` | `ROLE_PROD` et un libellé français pour l'affichage.

### Modification de `User`

`src/Entity/User.php` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | inchangé |
| `email` | string(180) | non | unique (`UNIQ_IDENTIFIER_EMAIL`, inchangé) ; normalisé en minuscules dans le setter |
| `firstName` | string(100), colonne `first_name` | non | |
| `lastName` | string(100), colonne `last_name` | non | |
| `role` | string enum `Role`, colonne `role` | non | remplace `roles` (JSON), supprimée |
| `active` | booléen, colonne `active` | non | défaut `true` |
| `mustChangePassword` | booléen, colonne `must_change_password` | non | défaut `false` |
| `password` | string(255) | non | inchangé (hash) |

- `getRoles()` renvoie `[$this->role->securityRole()]` ; la hiérarchie apporte les rôles inférieurs et `ROLE_USER`.
- `__serialize()` existant conservé (hash du mot de passe réduit en CRC32C dans la session).
- Pas de cloisonnement multi-organisation (outil mono-entreprise). Pas d'horodatage : la liste d'équipe ne doit exposer aucune donnée d'activité (pitch, règle 15).
- La migration est générée par `make migration`. Aucune donnée de production n'existe ; en dev, les nouvelles colonnes `NOT NULL` sans défaut exigent `make db-reset`. Son `down()` n'est réversible que sur une table `user` vide (colonne `roles` recréée en `NOT NULL` sans défaut), ce que documente la migration.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Enum/Type/Role.php` | Enum des trois rôles, correspondance vers les rôles de sécurité et libellés. |
| `src/Security/UserChecker.php` | Refuse l'authentification d'un compte désactivé (`DisabledException`). |
| `src/EventSubscriber/AccountGuardSubscriber.php` | Coupe la session d'un compte désactivé ; redirige vers le changement tant que le mot de passe est provisoire. |
| `src/Service/TeamManager.php` | Inscription, modification, désactivation, réactivation, réinitialisation, changement de mot de passe, garde-fou dernier directeur. |
| `src/Service/TemporaryPasswordGenerator.php` | Génère un mot de passe provisoire aléatoire sans caractères ambigus. |
| `src/Exception/LastActiveDirectorException.php` | Exception métier levée quand une opération retirerait le dernier directeur actif. |
| `src/Dto/TeamMemberInput.php` | Données du formulaire d'inscription/édition (prénom, nom, e-mail, rôle) et leurs contraintes. |
| `src/Dto/ChangePasswordInput.php` | Données du changement de mot de passe (actuel, nouveau) et leurs contraintes par groupe. |
| `src/Validator/UniqueTeamEmail.php` | Contrainte de classe : e-mail non utilisé par une autre personne (active ou désactivée). |
| `src/Validator/UniqueTeamEmailValidator.php` | Validateur associé, message dédié orientant vers la réactivation pour un compte désactivé. |
| `src/Validator/NotCurrentPassword.php` | Contrainte : le nouveau mot de passe diffère de l'actuel. |
| `src/Validator/NotCurrentPasswordValidator.php` | Validateur associé (utilisateur courant + password hasher). |
| `src/Form/TeamMemberType.php` | Formulaire lié à `TeamMemberInput` (rôle en `EnumType`, `prod` présélectionné). |
| `src/Form/ChangePasswordType.php` | Formulaire lié à `ChangePasswordInput` ; champ « mot de passe actuel » seulement en changement volontaire. |
| `src/Controller/TeamController.php` | Écrans et actions Équipe : liste, création, édition, désactivation, réactivation, réinitialisation, affichage unique du mot de passe provisoire. |
| `src/Controller/AccountController.php` | « Mon compte » : changement de mot de passe volontaire ou forcé. |
| `src/Command/CreateDirectorCommand.php` | `app:create-director` : crée un compte direction et affiche son mot de passe provisoire. |
| `templates/team/index.html.twig` | Liste de l'équipe (identité, rôle, statut), actions par ligne, modales de confirmation. |
| `templates/team/_form.html.twig` | Formulaire partagé inscription/édition. |
| `templates/team/new.html.twig` | Page d'inscription. |
| `templates/team/edit.html.twig` | Page d'édition. |
| `templates/team/temporary_password.html.twig` | Affichage unique du mot de passe provisoire. |
| `templates/account/password.html.twig` | Changement de mot de passe (variante forcée à la première connexion). |
| `migrations/VersionYYYYMMDDHHMMSS.php` | Ajout de `first_name`, `last_name`, `role`, `active`, `must_change_password` ; suppression de `roles`. Générée. |
| `tests/Unit/Service/TeamManagerTest.php` | Garde-fou dernier directeur (désactivation, changement de rôle), réinitialisation, réactivation, changement de mot de passe. |
| `tests/Unit/Service/TemporaryPasswordGeneratorTest.php` | Longueur, alphabet, deux appels distincts. |
| `tests/Unit/Enum/Type/RoleTest.php` | Correspondance enum → rôle de sécurité. |
| `tests/Controller/TeamControllerTest.php` | Accès refusé lead/prod, inscription, doublon d'e-mail (casse, compte désactivé), édition, désactivation/réactivation, réinitialisation, affichage unique. |
| `tests/Controller/AccountControllerTest.php` | Changement forcé (redirection, différent du provisoire), changement volontaire (mot de passe actuel requis), session conservée après changement. |
| `tests/Security/AccountGuardTest.php` | Coupure de la session d'un compte désactivé, confinement d'un compte à mot de passe provisoire. |
| `tests/Command/CreateDirectorCommandTest.php` | Création d'un compte direction via `CommandTester`, mot de passe affiché, marqueur provisoire levé. |
| `tests/e2e/team.spec.ts` | Parcours : la direction inscrit une personne, celle-ci se connecte, change son mot de passe provisoire, arrive au tableau de bord ; un compte prod ne voit pas « Équipe » ; purge des comptes `e2e-*` en fin d'exécution. |
| `tests/Support/CreatesUsers.php` | Trait de test : comptes isolés à e-mail unique et rechargement (la base de test n'a pas de rollback transactionnel). |
| `assets/controllers/dev_login_controller.js` | Contrôleur Stimulus du raccourci de connexion en dev. |
| `tests/e2e/dev-login.spec.ts` | Raccourci de connexion en dev : préremplissage, sélection d'un compte, connexion. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Entity/User.php` | Ajouter `firstName`, `lastName`, `role` (enum), `active`, `mustChangePassword` ; supprimer `roles` ; dériver `getRoles()` de l'enum ; e-mail en minuscules. |
| `src/Repository/UserRepository.php` | Implémenter `UserLoaderInterface` (chargement insensible à la casse) ; ajouter `findOneByEmail()`, `findAllForTeamList()` (actifs d'abord, tri nom/prénom), `countActiveDirectors()`. |
| `config/packages/security.yaml` | Ajouter `role_hierarchy`, `user_checker`, `access_control ^/equipe → ROLE_DIRECTION`, `login_throttling` ; provider entité sans `property` (loader du repository). |
| `config/packages/translation.yaml` | `default_locale: fr`. |
| `fixtures/AppFixtures.php` | Comptes `admin@` (direction), `lead@`, `prod@`, `ancien@` (désactivé), tous `password`, sans mot de passe provisoire. |
| `templates/base.html.twig` | Entrée « Équipe » dans la barre latérale si `is_granted('ROLE_DIRECTION')` ; menu utilisateur : prénom/nom et rôle, lien unique « Mon compte » à la place de « Mon profil » / « Paramètres ». |
| `templates/security/login.html.twig` | Retirer « Mot de passe oublié ? » et la case « Se souvenir de moi » (inerte) ; en `dev` uniquement : sélecteur de compte et champs préremplis à la place du rappel des identifiants de test. |
| `tests/ApplicationAvailabilityFunctionalTest.php` | Ajouter `/equipe`, `/equipe/nouveau` et `/mon-compte/mot-de-passe` aux URL testées. |
| `tests/Controller/SecurityControllerTest.php` | Ajouter : connexion d'un compte désactivé refusée avec le message générique ; connexion insensible à la casse de l'e-mail ; absence du raccourci hors dev ; blocage après échecs répétés (cache du limiteur vidé en début de test). |
| `CLAUDE.md` | Section « Identifiants de test » : quatre comptes et leurs rôles. |
| `src/Controller/SecurityController.php` | Passer la liste des comptes au gabarit de connexion en environnement `dev` uniquement. |
| `composer.json` | Ajout de `symfony/rate-limiter` (8.1.*). |
| `composer.lock` | Verrouillage de `symfony/rate-limiter`. |
| `config/reference.php` | Référence de configuration régénérée par Composer. |

## Hors scope

- **« Se souvenir de moi »** : la case inerte est retirée, pas activée ; la session native (fin à la fermeture du navigateur) suffit à la saisie quotidienne tant qu'aucun irritant n'est remonté.
- **Refonte de la page de connexion et du tableau de bord d'exemple** (textes « Paper », statistiques factices) : hors du sujet des rôles, à traiter avec la première feature qui remplace le tableau de bord.
- **Recherche de la barre supérieure et notifications** (éléments d'exemple du template) : non touchées.
- **Pagination de la liste d'équipe** : 15 à 40 personnes, une seule requête suffit.
- **Journal des actions de la direction** (qui a désactivé qui) : non demandé par le pitch.

## Impacts transverses

- **Cloisonnement des données** : aucun filtrage multi-organisation. La liste d'équipe n'est servie qu'à `ROLE_DIRECTION` et n'expose aucune donnée d'activité (pas de colonne d'horodatage ajoutée).
- **Déclinaisons / thèmes** : non. Navigation conditionnée par `is_granted('ROLE_DIRECTION')`.
- **Traduction / i18n** : interface en français uniquement ; `default_locale` passe à `fr` pour les messages natifs (erreur d'authentification, validations). Les nouveaux libellés suivent le style des templates existants (texte français dans Twig).
- **API / exposition externe** : non.
- **Droits d'accès** : `role_hierarchy` + `access_control` + `#[IsGranted]` ; `UserChecker` pour les comptes désactivés ; subscriber pour la coupure de session et le changement forcé ; `login_throttling` contre les tentatives répétées.
- **Emails / notifications** : non — le mot de passe provisoire est affiché à la direction.
- **Migration de données** : création de colonnes et suppression de `roles` ; aucune reprise (pas de production), `make db-reset` en dev.
- **Comportement par défaut** : toute personne créée par la direction ou par la commande a un mot de passe provisoire à remplacer ; les comptes de fixtures n'en ont pas.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Service/TeamManager.php` | unit | Inscription (hash, marqueur provisoire, mot de passe renvoyé) ; refus de désactiver ou rétrograder le dernier directeur actif, y compris soi-même ; désactivation autorisée s'il reste un autre directeur ; réactivation sans changement de mot de passe ; réinitialisation (nouveau hash, marqueur levé). |
| `src/Service/TemporaryPasswordGenerator.php` | unit | Longueur attendue, caractères uniquement dans l'alphabet sans ambiguïté, deux générations différentes. |
| `src/Enum/Type/Role.php` | unit | Chaque cas renvoie le bon rôle de sécurité. |
| `src/Validator/UniqueTeamEmailValidator.php` | functional (via `TeamControllerTest`) | Doublon exact, doublon de casse, doublon d'un compte désactivé (message de réactivation), édition de la personne sans changer son e-mail. |
| `src/Controller/TeamController.php` | functional | 403 pour lead et prod sur toutes les routes `/equipe` ; inscription → redirection vers l'affichage unique → second affichage indisponible ; édition ; désactivation/réactivation ; réinitialisation ; refus flashé sur le dernier directeur ; CSRF invalide refusé ; mot de passe en attente conservé quand on ouvre la page d'une autre personne. |
| `src/Controller/AccountController.php` | functional | Changement forcé : nouveau mot de passe identique au provisoire refusé, trop court ou trop faible refusé, succès → tableau de bord accessible sans reconnexion ; changement volontaire : mot de passe actuel erroné refusé, succès → toujours connecté. |
| `src/EventSubscriber/AccountGuardSubscriber.php` | functional | Session d'un compte désactivé en cours de navigation → redirection vers la connexion ; compte à mot de passe provisoire → toute URL redirige vers `/mon-compte/mot-de-passe`, la déconnexion reste possible. |
| `src/Security/UserChecker.php` | functional (via `SecurityControllerTest`) | Compte désactivé : connexion refusée avec le même message que des identifiants invalides. |
| `src/Command/CreateDirectorCommand.php` | functional | Création avec arguments, rôle direction, mot de passe provisoire affiché ; e-mail déjà utilisé refusé. |
| `tests/e2e/team.spec.ts` | E2E | Parcours complet inscription → première connexion → changement forcé → tableau de bord ; absence de l'entrée « Équipe » pour un compte prod. |
| `login_throttling` (`config/packages/security.yaml`) | functional (via `SecurityControllerTest`) | Message de blocage après échecs répétés ; cache du limiteur vidé au début des tests d'échec pour des exécutions répétées stables. |
| Raccourci de connexion en dev | functional + E2E | Absence du sélecteur et champs vides hors dev ; en dev, préremplissage, sélection d'un compte et connexion (`tests/e2e/dev-login.spec.ts`). |

**Hors scope tests** :

- Pas de test de charge sur la liste d'équipe : volume borné à quelques dizaines de lignes.
- Pas de test de concurrence sur le garde-fou « dernier directeur » (deux directeurs se rétrogradant simultanément) : improbable à cette échelle, risque consigné.
- Pas de test unitaire des contrôleurs : couverts en fonctionnel, conformément à la convention du projet.
- Pas de test E2E de la désactivation ni de la réinitialisation : couverts en fonctionnel, l'E2E se limite au parcours de première connexion qui traverse toute la chaîne.

## Ordre d'exécution

1. [x] **Enum, entité, migration et fixtures**
   - Objectif : `User` porte identité, rôle unique, statut et marqueur provisoire ; schéma et fixtures à jour.
   - Fichiers : `src/Enum/Type/Role.php`, `src/Entity/User.php`, migration générée, `fixtures/AppFixtures.php`, `tests/Unit/Enum/Type/RoleTest.php`, `CLAUDE.md`, `config/packages/security.yaml` (`role_hierarchy`, avancée ici pour que l'`access_control ^/` continue de laisser passer `ROLE_USER`).
   - Vérification : `make migration` puis `make db-reset` ; `make db-validate` ; `make phpunit` vert (les tests existants s'appuient sur `admin@example.com`, devenu direction).
   - Commitable seule : oui.

2. [x] **Socle sécurité**
   - Objectif : chargement insensible à la casse, refus des comptes désactivés, coupure de session (le changement forcé est ajouté au subscriber à l'étape 5, avec sa route cible).
   - Fichiers : `config/packages/security.yaml`, `config/packages/translation.yaml`, `src/Repository/UserRepository.php`, `src/Security/UserChecker.php`, `src/EventSubscriber/AccountGuardSubscriber.php`, `tests/Support/CreatesUsers.php`, `tests/Security/AccountGuardTest.php`, `tests/Controller/SecurityControllerTest.php`.
   - Vérification : `make phpunit-filter SecurityControllerTest` et `make phpunit-filter AccountGuardTest` verts.
   - Commitable seule : oui.

3. [x] **Service métier et générateur**
   - Objectif : `TeamManager` et `TemporaryPasswordGenerator` couverts par leurs tests unitaires.
   - Fichiers : `src/Service/TeamManager.php`, `src/Service/TemporaryPasswordGenerator.php`, `src/Exception/LastActiveDirectorException.php`, `src/Dto/TeamMemberInput.php` (entrée du service), `src/Repository/UserRepository.php` (`findAllForTeamList()`, `countActiveDirectors()`), tests unitaires associés.
   - Vérification : `make phpunit-filter TeamManagerTest` et `make phpunit-filter TemporaryPasswordGeneratorTest` verts ; `make lint` propre.
   - Commitable seule : oui.

4. [x] **Commande d'installation**
   - Objectif : `app:create-director` crée un compte direction avec mot de passe provisoire.
   - Fichiers : `src/Command/CreateDirectorCommand.php`, `src/Validator/UniqueTeamEmail*.php` (la commande doit refuser un e-mail déjà utilisé), `tests/Command/CreateDirectorCommandTest.php`.
   - Vérification : `make phpunit-filter CreateDirectorCommandTest` vert ; `symfony console app:create-director` en dev.
   - Commitable seule : oui.

5. [x] **Mon compte : changement de mot de passe**
   - Objectif : changement volontaire et forcé, reconnexion programmatique, contraintes de robustesse.
   - Fichiers : `src/Dto/ChangePasswordInput.php`, `src/Validator/NotCurrentPassword*.php`, `src/Form/ChangePasswordType.php`, `src/Controller/AccountController.php`, `templates/account/password.html.twig`, `src/EventSubscriber/AccountGuardSubscriber.php` (redirection forcée), `tests/Controller/AccountControllerTest.php`, `tests/Security/AccountGuardTest.php`.
   - Vérification : `make phpunit-filter AccountControllerTest` vert.
   - Commitable seule : oui.

6. [x] **Écrans Équipe**
   - Objectif : liste, inscription, édition, désactivation/réactivation, réinitialisation, affichage unique du mot de passe provisoire.
   - Fichiers : `src/Form/TeamMemberType.php`, `src/Controller/TeamController.php`, `templates/team/*`, `tests/Controller/TeamControllerTest.php`, `tests/ApplicationAvailabilityFunctionalTest.php`.
   - Vérification : `make phpunit-filter TeamControllerTest` vert ; parcours manuel sur `https://kadence.wip/equipe`.
   - Commitable seule : oui.

7. [x] **Navigation, page de connexion et E2E**
   - Objectif : entrée « Équipe » selon le rôle, menu « Mon compte », nettoyage de la page de connexion, parcours E2E.
   - Fichiers : `templates/base.html.twig`, `templates/security/login.html.twig`, `tests/e2e/team.spec.ts`.
   - Vérification : `make serve` puis `make playwright` vert (dont `login.spec.ts` existant) ; `make lint` et `make phpunit` verts.
   - Commitable seule : oui.

8. [x] **Retours de review**
   - Objectif : documenter l'irréversibilité de la migration, limiter les tentatives de connexion, indexer le mot de passe provisoire par personne, purger les comptes E2E, raccourci de connexion en dev (demande utilisateur).
   - Fichiers : migration, `composer.json`, `composer.lock`, `config/reference.php`, `config/packages/security.yaml`, `src/Controller/TeamController.php`, `src/Controller/SecurityController.php`, `templates/security/login.html.twig`, `assets/controllers/dev_login_controller.js`, `tests/Controller/SecurityControllerTest.php`, `tests/Controller/TeamControllerTest.php`, `tests/e2e/team.spec.ts`, `tests/e2e/dev-login.spec.ts`.
   - Vérification : `make lint`, `make phpunit` et `make playwright` verts.
   - Commitable seule : oui.

## Critères de sortie

- [x] `make db-validate` : mapping et schéma synchronisés ; le `down()` de la migration générée recrée `roles`, réversible uniquement sur une table `user` vide (documenté dans la migration).
- [x] Aucune route `/equipe*` n'est accessible à `ROLE_LEAD` ni `ROLE_PROD` (tests fonctionnels 403).
- [x] Un compte désactivé ne peut ni se connecter ni poursuivre une session ouverte (tests fonctionnels).
- [x] Aucun QueryBuilder ni `findBy` hors de `src/Repository/` ; aucune logique métier dans les contrôleurs, l'entité ou le repository.
- [x] Le mot de passe provisoire n'est jamais persisté en clair ni journalisé ; il quitte la session dès son affichage.
- [x] `make phpunit` vert, sans nouvelle régression.
- [x] `make lint` propre (PHP-CS-Fixer + PHPStan niveau 10).
- [x] `make playwright` vert.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Déconnexion involontaire après son propre changement de mot de passe (hash modifié → utilisateur jugé « changé ») | élevée | `Security::login()` après changement ; test fonctionnel « toujours connecté après changement ». |
| Boucle de redirection du subscriber (page de changement, déconnexion, profiler) | moyenne | Liste blanche explicite de routes (`app_account_password`, `app_logout`) ; ne traiter que la requête principale ; test fonctionnel dédié. |
| Migration SQLite en échec sur une base de dev non vide (colonnes `NOT NULL` sans défaut) | élevée en dev, nulle en prod | Pas de données de production ; consigne `make db-reset` dans l'étape 1. |
| Mot de passe provisoire exposé (session, logs, profiler) | faible | Stockage en session indexé par personne, retiré à l'affichage unique ; session invalidée à la déconnexion ; jamais en flash ni en log. |
| Garde-fou « dernier directeur » contourné par deux opérations concurrentes | faible | Échelle de 2–3 directeurs ; recours par `app:create-director` ; risque accepté. |
| `PasswordStrength` refuse des mots de passe longs mais jugés faibles, message peu clair | moyenne | Message personnalisé en français expliquant l'attente (longueur, variété) ; seuil par défaut. |
| Tests E2E qui polluent la base de dev (`https://kadence.wip`) à chaque exécution | moyenne | E-mail unique horodaté dans `team.spec.ts` ; purge des comptes `e2e-*` en `test.afterAll` (`dbal:run-sql`). |
| Tests existants cassés par la suppression de `roles` / `setRoles()` | moyenne | Étape 1 relance toute la suite ; seuls `AppFixtures` et les tests qui appellent `loginUser()` sont concernés. |

## Questions ouvertes

- **Longueur du mot de passe provisoire** : 12 ou 16 caractères ? → tranché : 12 caractères, alphabet sans caractères ambigus.
- **Seuil de `PasswordStrength`** : défaut (`STRENGTH_MEDIUM`) ou `STRENGTH_WEAK` si trop de refus en recette ? → tranché : seuil par défaut (`STRENGTH_MEDIUM`), avec un message en français.
- **Chemin des routes** : `/equipe` et `/mon-compte/mot-de-passe` retenus ; → tranché : `app_team_index`, `app_team_new`, `app_team_edit`, `app_team_deactivate`, `app_team_reactivate`, `app_team_reset_password`, `app_team_temporary_password`, `app_account_password`.
