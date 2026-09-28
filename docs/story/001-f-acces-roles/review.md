# Review — Inscrire l'équipe avec un rôle pour que chacun ne voie que ce qui le concerne

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/001-f-acces-roles/`
> **Amont** : `plan.md` · `pitch.md`
> **Diff examiné** : working tree vs `main` — 11 fichiers modifiés (+232 / −54) et ~40 fichiers nouveaux (~2 250 lignes, docs de story comprises), dont l'ajout hors plan du raccourci de connexion en dev demandé pendant la review.

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 1
- **Mineurs restants** : 0 / 3
- **Statut** : **PRÊT À COMMITER**

Tous les findings ont été corrigés pendant la passe (53 tests PHPUnit et 7 E2E verts, lint propre) : `/forge:report`, puis `/forge:sync` et `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

- [x] **[MIGRATION] Le `down()` échoue dès que la table `user` contient une ligne, sans que la migration le dise** — `migrations/Version20260928203535.php:31` — le `down()` recrée `roles` en `NOT NULL` sans valeur par défaut (vérifié : `SQLSTATE[23000] NOT NULL constraint failed: user.roles` sur la base de dev peuplée), et `getDescription()` renvoyait `''`. Corrigé : description renseignée et docblock sur `down()` indiquant qu'il n'est réversible que sur une table vide (`make db-reset` en dev) ; SQL généré inchangé.

## Mineurs

- [x] **[SECU] Aucune limitation des tentatives de connexion** — `config/packages/security.yaml:27` — préexistant, mais la feature crée désormais de vrais comptes. Corrigé : `symfony/rate-limiter` ajouté et `login_throttling` activé (défaut : 5 échecs par minute et par couple identifiant/IP) ; `testLoginIsThrottledAfterRepeatedFailures` vérifie le message français, et les tests d'échec de connexion vident le cache du limiteur pour rester stables d'une exécution à l'autre.
- [x] **[ROBUSTESSE] Le mot de passe provisoire peut rester en session ou se perdre** — `src/Controller/TeamController.php:129-170` — ouvrir la page d'affichage d'une autre personne le retirait sans l'afficher. Corrigé : stockage indexé par personne, seule l'entrée affichée est retirée (`testOpeningAnotherMemberTemporaryPasswordPageKeepsPendingOne`). Un mot de passe jamais affiché reste en session au plus jusqu'à la déconnexion, qui invalide la session.
- [x] **[TEST] Les E2E laissaient un compte `e2e-*` dans la base de dev à chaque exécution** — `tests/e2e/team.spec.ts:6` — corrigé : `test.afterAll` purge les comptes `e2e-*@example.com` via `dbal:run-sql` (l'application n'a pas de suppression de compte) ; vérifié à 0 compte restant après exécution.

## Points positifs

- **Droits portés par le framework, pas par du code maison** : `role_hierarchy`, `access_control` + `#[IsGranted]`, `UserChecker` — et le masquage « compte désactivé → identifiants invalides » est obtenu nativement, vérifié par un test qui compare les deux messages.
- **Piège de la déconnexion après changement de mot de passe anticipé au plan et verrouillé par un test** (`Security::login()` + `testVoluntaryChangeKeepsUserLoggedIn`).
- **Garde-fou « dernier directeur » dans le service, testé des deux côtés** : unitaire (`TeamManagerTest`) et fonctionnel (auto-désactivation et auto-rétrogradation refusées, état inchangé).
- **Formulaires sur DTO** : ni `active`, ni `password`, ni `mustChangePassword` ne sont exposés au binding ; l'entité ne passe jamais par un état invalide.
- **Tests isolés sans dépendance ajoutée** : chaque test qui mute crée ses propres comptes (`tests/Support/CreatesUsers.php`), et le seul test qui crée un directeur le supprime, ce qui garde le garde-fou testable.
- **Raccourci de connexion dev strictement cloisonné** : la liste des comptes n'est calculée que si `kernel.environment` vaut `dev` ; un test fonctionnel vérifie son absence (sélecteur et champs vides) hors dev, un E2E son fonctionnement en dev.

## Hors review (à vérifier en environnement réel)

- Critère d'acceptation « inscrire une personne en moins d'une minute » : à chronométrer en recette avec la direction (non automatisable).
- Icônes UX Icons récupérées en ligne à la demande (préexistant) : vérifier leur disponibilité sur l'hébergement cible, ou les verrouiller localement (`ux:icons:lock`).
- Ajouts hors plan à consigner dans le `report.md` : raccourci de connexion en dev (`src/Controller/SecurityController.php`, `templates/security/login.html.twig`, `assets/controllers/dev_login_controller.js`, `tests/e2e/dev-login.spec.ts`) ; nouvelle dépendance `symfony/rate-limiter` (à reporter dans `docs/stack.md` par `/forge:sync`).
