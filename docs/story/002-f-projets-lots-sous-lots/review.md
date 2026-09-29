# Review — Découper chaque projet en lots et sous-lots estimés, chacun confié à un responsable

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/002-f-projets-lots-sous-lots/`
> **Amont** : `plan.md` · `pitch.md`
> **Diff examiné** : working tree sur `main`, 10 fichiers modifiés + 44 nouveaux (~3 150 lignes nouvelles, docs de story comprises)

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 1
- **Mineurs restants** : 0 / 4
- **Statut** : **PRÊT À COMMITER**

Tous les findings sont corrigés (100 tests PHPUnit et 9 E2E verts, lint propre) : `/forge:report` pour documenter la livraison, puis `/forge:sync` et `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

- [x] **[TEST] Deux critères d'acceptation du pitch ne sont couverts par aucun test** — `tests/Controller/LotControllerTest.php`, `tests/Controller/ProjectControllerTest.php:81` — le critère de sortie du plan exige un test par critère d'acceptation. Manquent : (1) « un lot découpé n'offre ni estimation ni responsable à renseigner » (aucun test n'ouvre le formulaire de modification d'un lot découpé en tant que lead pour vérifier l'absence des champs `lot-estimate` et `lot-owner`) ; (2) « le **renommage** avec un titre déjà utilisé au même niveau est refusé » (seule la création est testée, pour les projets comme pour les lots). Corrigé : `testSplitLotOffersNeitherEstimateNorOwner`, `testRenamingALotToASiblingTitleIsRefused` et `testRenamingAProjectToAnExistingTitleIsRefused` ajoutés.

## Mineurs

- [x] **[A11Y] Le filtre actif de la liste des projets n'est signalé que par la couleur** — `templates/project/index.html.twig:20` — corrigé : `aria-current` vaut `page` sur le filtre actif et `false` sur l'autre.
- [x] **[STYLE] Attributs `class` vides ou avec espace final dans « Mon compte »** — `templates/account/password.html.twig:6` — les ternaires produisent `class=""` et `class="w-full "` hors mode forcé ; corrigé : l'attribut n'est posé qu'en mode forcé, et la carte reçoit `w-full` ou `w-full max-w-lg`.
- [x] **[PLAN] Passage en pleine largeur des formulaires Équipe et « Mon compte », hors du périmètre de la story** — `templates/team/new.html.twig`, `templates/team/edit.html.twig`, `templates/account/password.html.twig` — demandé explicitement après l'implémentation ; corrigé : tracé dans le §Périmètre du plan comme ajout hors plan demandé, à reprendre dans `report.md`.
- [x] **[DOC] Le §Périmètre du plan ne reflète pas le livré** — `docs/story/002-f-projets-lots-sous-lots/plan.md` — manquent `src/Validator/TitleComparison.php` et les templates `project/_lot_row`, `_signals`, `_estimate` ; `LotInput::inheritedFrom()` est devenu `forLotOf()` / `forSubLotOf()` ; le sous-lot de sous-lot renvoie 404 au lieu d'un flash. Corrigé : §Mécanismes mobilisés, §Périmètre et étape 4 du plan réalignés sur le livré.

## Points positifs

- **Invariant « seule une feuille porte estimation et responsable » tenu en un seul point** : `ProjectManager::applyLot()` efface estimation et responsable dès que le lot n'est pas une feuille, et `ProjectRollup` ignore de toute façon l'estimation d'un lot découpé — un test unitaire vérifie les deux.
- **Pas de N+1, et c'est prouvé** : l'arbre est chargé en une requête (jointures chargées sur `lots`, `children`, `owner`) et `testProjectPageQueryCountDoesNotGrowWithItsLots` compare le nombre de requêtes d'un petit et d'un grand projet via le profiler.
- **Droits au bon niveau** : `ROLE_LEAD` pour la structure, voter `LOT_EDIT` pour la décision par objet (comparaison par identifiant, responsable désactivé exclu), formulaire sans champ responsable pour un non-lead — l'ajout d'un champ `owner` forgé rend le formulaire invalide.
- **Responsable désactivé préservé sans pouvoir être redésigné** : les choix du formulaire contiennent les personnes actives plus le responsable actuel, ce qui rend la validation des choix suffisante, sans contrainte supplémentaire.
- **Contrainte SQLite prise en compte** : comparaison des titres en PHP (`LOWER()` ignore les accents), cascade de suppression par l'ORM (clés étrangères non appliquées), purge E2E qui supprime les lots avant les projets.
- **Mutations toujours protégées** : jeton CSRF sur les deux suppressions, DTO pour tous les formulaires, aucun `|raw`.

## Hors review (à vérifier en environnement réel)

- Chronométrage du critère « un projet de trois lots en moins de cinq minutes » : à faire à la recette, avec un lead.
- Rendu mobile de l'arbre des lots (tableau à défilement horizontal) : non vérifié sur un vrai téléphone.
