# Report — Découper chaque projet en lots et sous-lots estimés, chacun confié à un responsable

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/002-f-projets-lots-sous-lots/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 95 % — les 7 étapes du plan sont livrées avec l'approche prévue ; écarts structurants : sous-lot de sous-lot refusé par une 404 plutôt qu'un message flash, pré-remplissage du premier sous-lot porté par `forSubLotOf()` au lieu de `inheritedFrom()`, formulaires Équipe et « Mon compte » passés en pleine largeur hors plan, à la demande.
- **Critères** : 13 / 14 cochés (le chronométrage « trois lots en moins de cinq minutes » n'est pas mesuré).
- **Review** : 0 bloquant ; 1 important et 4 mineurs, tous corrigés pendant la passe — statut **PRÊT À COMMITER**.
- **Périmètre livré** : 41 fichiers créés (~2 800 lignes, hors docs de story), 8 fichiers modifiés (+53 / −27, hors docs).

Les leads et la direction créent des projets découpés en lots et sous-lots, estimés en jours entiers et confiés à un responsable par feuille, avec bascule vers le premier sous-lot et remontée à la suppression du dernier ; le responsable d'une feuille, même en prod, la modifie ; tout le monde consulte les projets, avec totaux, statut partiel et signaux. Les suites consignées à la review sont vertes (100 tests PHPUnit, 9 E2E). Restent : l'estimation initiale, la révision bornée et le blocage des suppressions, qui attendent la story de saisie, et le chronométrage en recette.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Entity/Project.php` | Entité projet (titre, description, tous ses lots, sous-lots compris). | Oui |
| `src/Entity/Lot.php` | Entité lot / sous-lot auto-référencée ; le constructeur rattache le lot à son projet et à son parent ; prédicats `isLeaf()` et `isSubLot()`. | Oui |
| `src/Repository/ProjectRepository.php` | `findAllForList(?User)` (arbre en une requête, filtre « mes responsabilités », tri qui ignore les accents), `findOneForDetail(int)`, `findTitlesExcept(?int)`. | Oui |
| `src/Repository/LotRepository.php` | `findSiblingTitles(Project, ?Lot, ?int)`. | Oui |
| `migrations/Version20260929190554.php` | Création des tables `project` et `lot` avec index sur `project_id`, `parent_id`, `owner_id` ; `down()` supprime les deux tables. | Oui |
| `src/Service/ProjectManager.php` | Création, modification et suppression des projets et lots ; bascule, remontée, profondeur limitée, invariant « seule une feuille porte estimation et responsable ». | Oui |
| `src/Service/ProjectRollup.php` | Construit l'arbre de lecture : totaux, compteurs « à estimer », « à désigner », « à redésigner », nombre de sous-lots. | Oui |
| `src/Model/ProjectSummary.php` | Vue en lecture seule d'un projet (lots, total, compteurs, `isPartial()`, `isUnsplit()`). | Oui |
| `src/Model/LotSummary.php` | Vue en lecture seule d'un lot (sous-lots, total, compteurs, `isPartial()`). | Oui |
| `src/Exception/LotDepthException.php` | Refus d'un sous-lot sous un sous-lot. | Oui |
| `src/Security/Voter/LotVoter.php` | `LOT_EDIT` : un lead, ou le responsable actif d'une feuille (comparaison par identifiant). | Oui |
| `src/Dto/ProjectInput.php` | Données du formulaire projet + `fromProject()` ; contrainte `UniqueProjectTitle`. | Oui |
| `src/Dto/LotInput.php` | Données du formulaire lot + `forLotOf()`, `forSubLotOf()` (pré-remplissage du premier sous-lot) et `fromLot()` ; contrainte `UniqueLotTitle`. | Écart volontaire (cf. §Écarts) |
| `src/Validator/UniqueProjectTitle.php` / `UniqueProjectTitleValidator.php` | Titre de projet unique, sans tenir compte de la casse. | Oui |
| `src/Validator/UniqueLotTitle.php` / `UniqueLotTitleValidator.php` | Titre de lot unique parmi ses voisins, avec un message distinct pour les lots et les sous-lots. | Oui |
| `src/Validator/TitleComparison.php` | Comparaison de titres insensible à la casse en PHP, partagée par les deux validateurs. | Non (ajout — cf. §Écarts) |
| `src/Form/ProjectType.php` | Formulaire projet (titre, description). | Oui |
| `src/Form/LotType.php` | Formulaire lot avec les options `with_owner`, `with_estimate`, `current_owner` ; choix des responsables actifs, plus le responsable actuel s'il est désactivé. | Oui |
| `src/Controller/ProjectController.php` | `/projets` : liste (+ filtre), détail, création, modification, suppression avec jeton CSRF. | Oui |
| `src/Controller/LotController.php` | Ajout d'un lot et d'un sous-lot (avec « Enregistrer et ajouter un autre »), modification selon le voter, suppression avec jeton CSRF. | Oui |
| `templates/project/index.html.twig` | Liste des projets : total, partiel, signaux, « à découper », filtre « Mes responsabilités » avec `aria-current`. | Oui |
| `templates/project/show.html.twig` | Page projet : en-tête, total et signaux, actions du projet, bouton « Ajouter un lot », arbre des lots. | Oui |
| `templates/project/_lot_row.html.twig` | Ligne d'un lot ou sous-lot, badges de feuille, badge « vous », actions et modale de suppression. | Non (ajout — cf. §Écarts) |
| `templates/project/_estimate.html.twig` / `_signals.html.twig` | Total avec badge « partiel » ; badges « à estimer », « à désigner », « à redésigner ». | Non (ajout — cf. §Écarts) |
| `templates/project/new.html.twig` / `edit.html.twig` / `_form.html.twig` | Formulaires projet (pleine largeur). | Oui |
| `templates/lot/new.html.twig` / `edit.html.twig` / `_form.html.twig` | Formulaires lot ; message de reprise au premier sous-lot ; bouton « Enregistrer et ajouter un autre » à la création. | Oui |
| `fixtures/ProjectFixtures.php` | Trois projets de démonstration couvrant lot découpé, « à estimer », « à désigner », « à redésigner », feuille de `prod@example.com`, projet sans lot. | Oui |
| `tests/Unit/Service/ProjectManagerTest.php` | 9 cas : création, bascule, sous-lots suivants non pré-remplis, profondeur, remontée, suppression en cascade, invariant du lot découpé. | Oui |
| `tests/Unit/Service/ProjectRollupTest.php` | 5 cas : totaux, partiel, compteurs, estimation d'un lot découpé ignorée, projet sans lot. | Oui |
| `tests/Unit/Security/Voter/LotVoterTest.php` | 6 cas de la matrice de décision. | Oui |
| `tests/Controller/ProjectControllerTest.php` | 13 cas : droits, navigation, création, unicité à la création et au renommage, tri, signaux, filtre, arbre, nombre de requêtes, suppression en cascade, CSRF. | Oui |
| `tests/Controller/LotControllerTest.php` | 11 cas (13 exécutions) : enchaînement, estimation invalide, bascule, profondeur, lot découpé sans champs, responsable, droits prod, choix des responsables, unicité, renommage refusé, remontée. | Oui |
| `tests/Support/CreatesProjects.php` | Aides de test pour créer projets et lots. | Oui |
| `tests/e2e/projects.spec.ts` | Parcours lead (projet de trois lots) et parcours responsable (modifier sa feuille, et seulement la sienne). | Oui |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Repository/UserRepository.php` | Ajout de `findActiveForOwnerChoice()`. | Oui |
| `templates/base.html.twig` | Entrée « Projets » (`nav-projects`) visible par tous. | Oui |
| `templates/page/index.html.twig` | Raccourci « Projets » (`home-projects`). | Oui |
| `fixtures/AppFixtures.php` | Références `user-director`, `user-lead`, `user-prod`, `user-former` pour `ProjectFixtures`. | Oui |
| `tests/ApplicationAvailabilityFunctionalTest.php` | Ajout de `/projets` et `/projets/nouveau`. | Oui |
| `docs/story/002-f-projets-lots-sous-lots/pitch.md` | Règle 7 et critère d'acceptation en jours entiers ; questions ouvertes tranchées. | Oui |
| `templates/team/new.html.twig`, `templates/team/edit.html.twig` | Formulaires Équipe en pleine largeur. | Non (ajout — cf. §Écarts) |
| `templates/account/password.html.twig` | « Mon compte » en pleine largeur ; le changement de mot de passe obligatoire reste un écran d'accès centré. | Non (ajout — cf. §Écarts) |

## Écarts avec le plan

`plan.md` a été réaligné pendant la review sur les points ci-dessous ; ils sont tracés ici par rapport au plan validé à la planification.

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| `LotInput::fromLot()` et `inheritedFrom(Lot $parent)` | `forLotOf(Project)`, `forSubLotOf(Lot $parent)` et `fromLot()` | `forSubLotOf()` sert à tous les sous-lots et ne pré-remplit que le premier ; `inheritedFrom` aurait été trompeur pour les suivants. |
| Sous-lot sous un sous-lot : `LotDepthException` transformée en message flash par le contrôleur | Le contrôleur répond 404 avant d'afficher le formulaire ; l'exception reste le garde-fou du service | L'interface ne propose jamais ce lien ; un formulaire affiché puis refusé à la soumission n'avait pas de sens. |
| Nombre de requêtes de la page projet vérifié au profiler (via Mate) à l'étape 5 | Vérifié par un test fonctionnel automatique (`testProjectPageQueryCountDoesNotGrowWithItsLots`) | Le contrôle reste actif à chaque passage de la suite plutôt que ponctuel. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `src/Validator/TitleComparison.php` | Factorisation de la comparaison insensible à la casse, identique dans les deux validateurs d'unicité. |
| `templates/project/_lot_row.html.twig`, `_estimate.html.twig`, `_signals.html.twig` | Une macro `_self` ne peut pas être appelée depuis le contenu d'un composant `<twig:Table:Body>` (compilé comme template embarqué) ; total et signaux sont partagés par la liste et la page projet. |
| Formulaires Équipe et « Mon compte » en pleine largeur | Demande explicite après l'implémentation, appliquée à tous les formulaires de l'application affichés avec la barre latérale ; livrée dans cette story (finding de review [PLAN], corrigé par la traçabilité). |
| `aria-current` sur les filtres de la liste des projets | Finding de review [A11Y] : le filtre actif n'était signalé que par la couleur. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Service/ProjectManager.php` | unit | unit, 9 cas | Fait |
| `src/Service/ProjectRollup.php` | unit | unit, 5 cas | Fait |
| `src/Security/Voter/LotVoter.php` | unit | unit, 6 cas | Fait |
| `src/Controller/ProjectController.php` | functional | functional, 13 cas (dont nombre de requêtes et renommage refusé) | Fait — couverture étendue |
| `src/Controller/LotController.php` | functional | functional, 11 cas (dont lot découpé sans champs et renommage refusé) | Fait — couverture étendue |
| `src/Validator/Unique*TitleValidator.php` | functional (via les contrôleurs) | functional (via les contrôleurs), unicité limitée aux voisins comprise | Fait |
| `tests/e2e/projects.spec.ts` | E2E | E2E, 2 scénarios | Fait |
| DTO et formulaires | hors scope assumé | pas de test unitaire dédié | Conforme — contraintes vérifiées par les tests de contrôleur |
| Chronométrage « trois lots en moins de cinq minutes » | hors scope assumé | non mesuré | Conforme — recette manuelle prévue |
| Charge de la liste des projets | hors scope assumé | pas écrit | Conforme — quelques dizaines de projets attendus |

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Un lead crée un projet avec un titre et, s'il le souhaite, une description.
- [x] Un lead ajoute des lots à un projet et des sous-lots à un lot ; rien ne peut être ajouté sous un sous-lot.
- [x] Un lead déclare l'estimation d'une feuille en jours entiers ; une valeur nulle, négative ou décimale est refusée.
- [x] Un projet et un lot découpé en sous-lots n'offrent ni estimation ni responsable à renseigner, et affichent la somme des estimations de leurs feuilles.
- [x] Une feuille sans estimation apparaît « à estimer », et le total du lot et du projet qui la contiennent est signalé comme partiel.
- [x] Une feuille sans responsable apparaît « à désigner » ; une feuille dont le responsable a été désactivé apparaît « à redésigner ».
- [x] Seules des personnes actives, tous rôles confondus, sont proposées comme responsable.
- [x] Quand un lead ajoute le premier sous-lot à un lot estimé, ce sous-lot reprend l'estimation et le responsable du lot, et le total du lot est inchangé.
- [x] Quand un lead supprime le dernier sous-lot d'un lot, l'estimation et le responsable de ce sous-lot remontent sur le lot.
- [x] La création ou le renommage d'un projet, d'un lot ou d'un sous-lot avec un titre déjà utilisé au même niveau est refusé, quelle que soit la casse.
- [x] Un lead ou la direction modifie ou supprime n'importe quel projet, lot ou sous-lot, y compris ceux dont il n'est pas responsable.
- [x] Un membre de prod responsable d'une feuille modifie son titre, sa description et son estimation ; il ne peut ni créer, ni supprimer, ni changer le responsable, ni modifier une autre feuille, un lot découpé ou un projet.
- [x] Un membre de prod consulte tous les projets, lots et sous-lots avec leurs estimations et leurs responsables, sans aucune action de modification hors de ses propres feuilles.
- [ ] Un lead crée un projet découpé en trois lots, estimés et dotés d'un responsable, en moins de cinq minutes. — non mesuré : chronométrage prévu à la recette ; le scénario E2E prouve que le parcours se fait sans détour.

## Dette technique identifiée

Issus de la review (mineurs non traités) :

_(aucun — les 4 mineurs ont été corrigés pendant la passe)_

Au-delà de la review :

1. **Estimation initiale, révision bornée et blocage des suppressions (règles 17 et 18 du pitch)** — à livrer avec `saisie-quotidienne` : champ d'estimation initiale figé au premier temps saisi, révision jamais sous le consommé, suppression refusée dès qu'un temps existe.
2. **Bascule d'un lot qui a déjà des temps saisis** — question ouverte du pitch, à trancher avec `saisie-quotidienne`.
3. **Chronométrage du parcours lead et rendu mobile de l'arbre** — à vérifier en recette (review, §Hors review).
4. **Clés étrangères non appliquées par SQLite** — la suppression passe par la cascade ORM ; toute suppression SQL directe laisse des lots orphelins. À reprendre avec le choix de la base de production (dette déjà listée dans `docs/stack.md`).
5. **Pas de contrainte d'unicité des titres en base** — deux créations simultanées du même titre restent possibles ; accepté pour un outil interne.
6. **Liste des projets non paginée** — à reconsidérer avec `archivage-projets` (V2).

## Leçons apprises

- **Macros Twig et composants** : le contenu d'un composant (`<twig:Table:Body>`) est compilé comme un template embarqué où `_self` ne désigne plus la page ; pour une ligne réutilisée dans un tableau de composants, passer par un template inclus.
- **Compter les requêtes en test fonctionnel** : tant que le client n'a pas fait de requête, le noyau n'a pas redémarré et le profiler compte aussi les `INSERT` de préparation du test ; faire une requête d'amorçage avant de mesurer.
- **Typer un résultat de QueryBuilder sous PHPStan 10** : sans chargeur d'ObjectManager configuré, `getResult()` renvoie `mixed`, et PHP-CS-Fixer transforme un `/** @var */` posé sur un `return` en simple commentaire ; passer par une variable nommée.
- **SQLite en dev et test** : `LOWER()` ne gère que l'ASCII et les clés étrangères ne sont pas appliquées ; comparer les titres en PHP et confier les cascades à l'ORM.
- **`make:entity` est interactif** : dans une session non interactive, écrire les entités à la main et générer la migration avec `make migration`, qui reste la seule source du SQL.
