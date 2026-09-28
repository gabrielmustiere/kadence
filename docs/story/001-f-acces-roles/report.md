# Report — Inscrire l'équipe avec un rôle pour que chacun ne voie que ce qui le concerne

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/001-f-acces-roles/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 95 % — les 43 fichiers prévus sont livrés et l'approche est celle du plan ; écarts structurants : `down()` de migration réversible seulement sur table vide, limitation des tentatives de connexion ajoutée (nouvelle dépendance `symfony/rate-limiter`), raccourci de connexion en dev ajouté à la demande.
- **Critères** : 13 / 14 cochés (le critère « moins d'une minute » n'est pas mesuré).
- **Review** : 0 bloquant ; 1 important et 3 mineurs, tous corrigés pendant la passe — statut **PRÊT À COMMITER**.
- **Périmètre livré** : 35 fichiers créés (~1 900 lignes, hors docs de story), 14 fichiers modifiés (+337 / −56).

La direction gère l'équipe (inscription avec mot de passe provisoire affiché une fois, modification, désactivation/réactivation, réinitialisation), les rôles sont hiérarchisés, les comptes désactivés sont refusés et leurs sessions coupées, et le changement de mot de passe provisoire est imposé. Les suites de tests consignées à la review sont vertes (53 tests PHPUnit, 7 E2E). Reste : chronométrer l'inscription en recette et reporter la nouvelle dépendance dans `docs/stack.md`.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Enum/Type/Role.php` | Enum des trois rôles, correspondance vers les rôles de sécurité et libellés. | Oui |
| `src/Security/UserChecker.php` | Refuse l'authentification d'un compte désactivé (`DisabledException`). | Oui |
| `src/EventSubscriber/AccountGuardSubscriber.php` | Coupe la session d'un compte désactivé ; redirige vers le changement tant que le mot de passe est provisoire. | Oui |
| `src/Service/TeamManager.php` | Inscription, modification, désactivation, réactivation, réinitialisation, changement de mot de passe, garde-fou dernier directeur. | Oui |
| `src/Service/TemporaryPasswordGenerator.php` | Génère un mot de passe provisoire de 12 caractères sans caractères ambigus. | Oui |
| `src/Exception/LastActiveDirectorException.php` | Exception métier levée quand une opération retirerait le dernier directeur actif. | Oui |
| `src/Dto/TeamMemberInput.php` | Données du formulaire d'inscription/édition et leurs contraintes. | Oui |
| `src/Dto/ChangePasswordInput.php` | Données du changement de mot de passe et leurs contraintes par groupe. | Oui |
| `src/Validator/UniqueTeamEmail.php` | Contrainte de classe : e-mail non utilisé par une autre personne. | Oui |
| `src/Validator/UniqueTeamEmailValidator.php` | Validateur associé, message de réactivation pour un compte désactivé. | Oui |
| `src/Validator/NotCurrentPassword.php` | Contrainte : le nouveau mot de passe diffère de l'actuel. | Oui |
| `src/Validator/NotCurrentPasswordValidator.php` | Validateur associé (utilisateur courant + password hasher). | Oui |
| `src/Form/TeamMemberType.php` | Formulaire lié à `TeamMemberInput`, rôle en `EnumType`, `prod` présélectionné. | Oui |
| `src/Form/ChangePasswordType.php` | Formulaire lié à `ChangePasswordInput`, mot de passe actuel en changement volontaire seulement, confirmation par `RepeatedType`. | Oui |
| `src/Controller/TeamController.php` | Écrans et actions Équipe ; mot de passe provisoire stocké en session par personne. | Oui |
| `src/Controller/AccountController.php` | « Mon compte » : changement volontaire ou forcé, reconnexion par `Security::login()`. | Oui |
| `src/Command/CreateDirectorCommand.php` | `app:create-director` (commande invocable, arguments demandés interactivement). | Oui |
| `templates/team/index.html.twig` | Liste de l'équipe, actions par ligne, modales de confirmation. | Oui |
| `templates/team/_form.html.twig` | Formulaire partagé inscription/édition. | Oui |
| `templates/team/new.html.twig` | Page d'inscription. | Oui |
| `templates/team/edit.html.twig` | Page d'édition. | Oui |
| `templates/team/temporary_password.html.twig` | Affichage unique du mot de passe provisoire (`Cache-Control: no-store`). | Oui |
| `templates/account/password.html.twig` | Changement de mot de passe, gabarit de connexion en variante forcée. | Oui |
| `migrations/Version20260928203535.php` | Ajout de `first_name`, `last_name`, `role`, `active`, `must_change_password` ; suppression de `roles` ; description et docblock d'irréversibilité. | Écart volontaire (cf. §Écarts) |
| `tests/Unit/Service/TeamManagerTest.php` | 9 cas : inscription, garde-fou (désactivation, rétrogradation), réactivation, réinitialisation, changement. | Oui |
| `tests/Unit/Service/TemporaryPasswordGeneratorTest.php` | Longueur, alphabet, unicité. | Oui |
| `tests/Unit/Enum/Type/RoleTest.php` | Correspondance enum → rôle de sécurité. | Oui |
| `tests/Controller/TeamControllerTest.php` | Accès refusé lead/prod, navigation, liste, inscription, doublons, édition, désactivation/réactivation, garde-fou, réinitialisation, CSRF, stockage par personne. | Oui |
| `tests/Controller/AccountControllerTest.php` | Changement forcé et volontaire, robustesse, maintien de la session. | Oui |
| `tests/Security/AccountGuardTest.php` | Coupure de session d'un compte désactivé, confinement d'un compte à mot de passe provisoire. | Oui |
| `tests/Command/CreateDirectorCommandTest.php` | Création d'un directeur, e-mail déjà utilisé refusé. | Oui |
| `tests/e2e/team.spec.ts` | Parcours inscription → première connexion → changement forcé ; prod sans accès ; purge des comptes `e2e-*` en fin d'exécution. | Oui |
| `tests/Support/CreatesUsers.php` | Trait de test : création de comptes isolés à e-mail unique, rechargement. | Non (ajout — cf. §Écarts) |
| `assets/controllers/dev_login_controller.js` | Contrôleur Stimulus du raccourci de connexion en dev. | Non (ajout — cf. §Écarts) |
| `tests/e2e/dev-login.spec.ts` | Raccourci de connexion en dev : préremplissage, sélection d'un compte, connexion. | Non (ajout — cf. §Écarts) |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Entity/User.php` | Ajout de `firstName`, `lastName`, `role` (enum), `active`, `mustChangePassword` ; suppression de `roles` ; `getRoles()` dérivé de l'enum ; e-mail en minuscules. | Oui |
| `src/Repository/UserRepository.php` | `UserLoaderInterface` (chargement insensible à la casse), `findOneByEmail()`, `findAllForTeamList()`, `countActiveDirectors()`. | Oui |
| `config/packages/security.yaml` | `role_hierarchy`, `user_checker`, `access_control ^/equipe`, provider sans `property` ; en plus : `login_throttling`. | Écart volontaire (cf. §Écarts) |
| `config/packages/translation.yaml` | `default_locale: fr`. | Oui |
| `fixtures/AppFixtures.php` | Comptes `admin@` (direction), `lead@`, `prod@`, `ancien@` (désactivé). | Oui |
| `templates/base.html.twig` | Entrée « Équipe » réservée à la direction ; menu utilisateur : prénom/nom, rôle, « Mon compte ». | Oui |
| `templates/security/login.html.twig` | Retrait de « Mot de passe oublié ? » et « Se souvenir de moi » ; en plus : raccourci de connexion en dev (sélecteur de compte, champs préremplis) à la place du rappel des identifiants de test. | Écart volontaire (cf. §Écarts) |
| `tests/ApplicationAvailabilityFunctionalTest.php` | Ajout de `/equipe`, `/equipe/nouveau`, `/mon-compte/mot-de-passe`. | Oui |
| `tests/Controller/SecurityControllerTest.php` | Compte désactivé (message générique), connexion insensible à la casse ; en plus : absence du raccourci hors dev, blocage après échecs répétés, remise à zéro du limiteur. | Écart volontaire (cf. §Écarts) |
| `CLAUDE.md` | Section « Identifiants de test » : quatre comptes, commande `app:create-director`. | Oui |
| `src/Controller/SecurityController.php` | Liste des comptes passée au gabarit de connexion en environnement `dev` uniquement. | Non (ajout — cf. §Écarts) |
| `composer.json` | Ajout de `symfony/rate-limiter` (8.1.*). | Non (ajout — cf. §Écarts) |
| `composer.lock` | Verrouillage de `symfony/rate-limiter`. | Non (ajout — cf. §Écarts) |
| `config/reference.php` | Référence de configuration régénérée par Composer après l'ajout du rate limiter. | Non (ajout — cf. §Écarts) |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| `role_hierarchy` à l'étape 2 (socle sécurité) | Posée à l'étape 1 | Sans `ROLE_USER` dans `getRoles()`, l'`access_control ^/` aurait tout bloqué : l'étape 1 n'aurait pas été commitable seule. |
| Redirection du changement forcé à l'étape 2 | Ajoutée au subscriber à l'étape 5 | La route cible `app_account_password` n'existe qu'à l'étape 5 (anticipé dans le plan). |
| `TeamMemberInput` et `UniqueTeamEmail` à l'étape 6 | Créés aux étapes 3 et 4 | Le service (étape 3) prend le DTO en entrée ; la commande (étape 4) doit refuser un e-mail déjà utilisé. |
| Critère de sortie : « la migration générée est réversible (`down()` recrée `roles`) » | `down()` réversible uniquement sur une table vide, documenté dans la migration | Le `down()` généré recrée `roles` en `NOT NULL` sans défaut ; le SQL généré n'est pas modifié à la main (convention). Finding **[MIGRATION]** de la review, corrigé par documentation. Aucune donnée de production. |
| Requêtes de `UserRepository` (forme non précisée au plan) | `findBy()` / `count()` du repository de base | PHPStan niveau 10 ne type pas `getResult()` sans `@var` forcé, interdit par la configuration ; les méthodes de base sont typées. |
| Isolation des tests non traitée au plan | Chaque test qui modifie des comptes crée les siens (`tests/Support/CreatesUsers.php`) ; le test de la commande supprime le directeur créé | La base de test n'a pas de rollback transactionnel ; ajouter une dépendance (`dama/doctrine-test-bundle`) n'était pas prévu. Le directeur supprimé garde le garde-fou « dernier directeur » testable. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| Raccourci de connexion en dev : champs préremplis (`admin@example.com` / `password`) et sélecteur de compte qui remplit e-mail et mot de passe | Demande explicite de l'utilisateur pendant la review (confort de développement) ; liste calculée uniquement si `kernel.environment` vaut `dev`. |
| `login_throttling` et dépendance `symfony/rate-limiter` | Finding **[SECU] Aucune limitation des tentatives de connexion** de la review, corrigé. |
| Mot de passe provisoire stocké en session par personne | Finding **[ROBUSTESSE] Le mot de passe provisoire peut rester en session ou se perdre** de la review, corrigé. |
| Purge des comptes `e2e-*` après le parcours E2E (`dbal:run-sql`) | Finding **[TEST] Les E2E laissaient un compte `e2e-*` dans la base de dev** de la review, corrigé. |
| Confirmation du nouveau mot de passe (`RepeatedType`) | Non précisée au plan ; évite qu'une faute de frappe verrouille la personne à sa première connexion. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Service/TeamManager.php` | unit | unit, 9 cas | Fait |
| `src/Service/TemporaryPasswordGenerator.php` | unit | unit, 2 cas | Fait |
| `src/Enum/Type/Role.php` | unit | unit, 3 cas (data provider) | Fait |
| `src/Validator/UniqueTeamEmailValidator.php` | functional (via `TeamControllerTest`) | functional (via `TeamControllerTest` et `CreateDirectorCommandTest`) | Fait — couverture étendue |
| `src/Controller/TeamController.php` | functional | functional, 14 cas | Fait — couverture étendue (stockage par personne, navigation) |
| `src/Controller/AccountController.php` | functional | functional, 6 cas | Fait |
| `src/EventSubscriber/AccountGuardSubscriber.php` | functional | functional, 2 cas | Fait |
| `src/Security/UserChecker.php` | functional (via `SecurityControllerTest`) | functional (message comparé à celui d'identifiants invalides) | Fait |
| `src/Command/CreateDirectorCommand.php` | functional | functional (`CommandTester`), 2 cas | Fait |
| `tests/e2e/team.spec.ts` | E2E | E2E, 2 parcours | Fait |
| `login_throttling` | non prévu | functional (`testLoginIsThrottledAfterRepeatedFailures`) | Fait (ajout) |
| Raccourci de connexion en dev | non prévu | functional (absence hors dev) + E2E (`dev-login.spec.ts`) | Fait (ajout) |
| Connexion avec le nouvel e-mail après modification par la direction | non prévu | **non couvert directement** (e-mail modifié vérifié ; chargement de l'utilisateur par e-mail testé ailleurs) | Manque mineur — risque faible |
| Concurrence sur le garde-fou « dernier directeur » | hors scope assumé | pas écrit | Conforme — improbable à 2–3 directeurs |
| E2E de la désactivation et de la réinitialisation | hors scope assumé | pas écrit | Conforme — couverts en fonctionnel |
| Test de charge de la liste d'équipe | hors scope assumé | pas écrit | Conforme — quelques dizaines de lignes |

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] La direction inscrit une personne en renseignant prénom, nom, e-mail et rôle, et voit s'afficher une seule fois un mot de passe provisoire généré par l'outil.
- [ ] La direction inscrit une personne en moins d'une minute. — non mesuré : à chronométrer en recette avec la direction (cf. §Dette technique).
- [x] L'inscription est refusée si l'e-mail est déjà utilisé par une personne active ou désactivée (y compris avec une casse différente), avec un message orientant vers la réactivation dans le second cas.
- [x] À sa première connexion avec le mot de passe provisoire, la personne est obligée de choisir un nouveau mot de passe, différent du provisoire, avant d'accéder à quoi que ce soit.
- [x] La direction consulte la liste de l'équipe avec, pour chaque personne, prénom, nom, e-mail, rôle et statut — et aucune donnée d'activité.
- [x] La direction modifie le prénom, le nom, l'e-mail ou le rôle d'une personne ; la personne se connecte ensuite avec son nouvel e-mail le cas échéant.
- [x] Une personne désactivée ne peut plus se connecter, et le message affiché est identique à celui d'identifiants invalides.
- [x] Une personne désactivée alors qu'elle est connectée est déconnectée dès sa prochaine action.
- [x] Une personne réactivée se reconnecte avec son mot de passe inchangé et retrouve son rôle.
- [x] La direction attribue un nouveau mot de passe provisoire à une personne ; l'ancien mot de passe ne fonctionne plus et la personne doit en choisir un nouveau à sa connexion suivante.
- [x] Toute personne connectée change son mot de passe depuis « Mon compte » après avoir confirmé son mot de passe actuel.
- [x] Il est impossible de désactiver ou de changer le rôle du dernier membre actif de la direction, y compris le sien.
- [x] Un lead ou un membre de prod ne voit pas l'entrée « Équipe » dans la navigation et est refusé s'il accède directement à une page de gestion d'équipe.
- [x] Le premier compte direction se crée sans passer par une page de l'application, et aucune page ne permet de s'inscrire soi-même.

## Dette technique identifiée

Issus de la review (mineurs non traités) : aucun — les trois mineurs ont été corrigés pendant la passe.

Au-delà de la review :

1. **Chronométrage de l'inscription** — critère « moins d'une minute » à mesurer en recette avec la direction ; non automatisable.
2. **`docs/stack.md` à jour** — y reporter `symfony/rate-limiter` et `login_throttling` (`/forge:sync`).
3. **Test direct de la connexion après changement d'e-mail** — ajouter un cas fonctionnel « la direction modifie l'e-mail, la personne se connecte avec le nouveau ».
4. **Icônes UX Icons récupérées en ligne** (préexistant) — les verrouiller localement (`ux:icons:lock`) ou vérifier leur disponibilité sur l'hébergement cible. **Critique** au premier déploiement si l'hébergement n'a pas d'accès sortant.
5. **Cadrage social et données personnelles** (pitch, vision) — information des salariés et consultation du CSE avant d'ouvrir l'outil à l'équipe. **Critique** avant le lancement, hors code.

## Leçons apprises

- **Vérifier que la base de départ est verte avant la première modification** : les dépendances n'étaient pas installées et le CSS Tailwind pas compilé ; 6 tests existants échouaient pour cette seule raison. `make init` ne compile pas Tailwind : lancer `symfony console tailwind:build` avant `make phpunit` sur un poste neuf.
- **Changer le hash du mot de passe de l'utilisateur courant le déconnecte** à la requête suivante : tout écran qui modifie son propre mot de passe doit reconnecter par `Security::login()`, et un test doit le vérifier.
- **Un cache en mémoire est vidé entre les requêtes de test** (`kernel.reset`) : il ne peut pas servir à tester un rate limiter. Vider le cache du limiteur au début des tests de connexion garde le blocage testable et les exécutions répétées stables.
- **Sans rollback transactionnel, les tests qui modifient des comptes doivent créer les leurs** ; un seul directeur ajouté en base suffit à rendre le garde-fou « dernier directeur » intestable.
- **Les migrations SQLite qui ajoutent des colonnes `NOT NULL` recréent la table** : sur une base non vide, `up()` comme `down()` échouent sans valeur par défaut. À prévoir dès le plan quand des données existeront en production.
