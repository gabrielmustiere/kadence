# Plan technique — Mettre ses feuilles en favori pour les retrouver chaque semaine en tête de sa saisie

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/013-f-feuilles-favorites/`
> **Amont** : `pitch.md`

## Approche retenue

Un favori est une ligne `FavoriteLot (user, lot)`, unique par couple et sans date. Il ne porte que le lien entre une personne et une feuille. `FavoriteManager` l'ajoute ou le retire par deux opérations idempotentes, `add()` et `remove()`, plutôt qu'une bascule : deux onglets ou un double clic ne peuvent donc pas s'annuler l'un l'autre. `FavoriteLotRepository` porte toutes les requêtes. `findLotsOf(User)` lit en une requête les feuilles favorites, avec leur lot parent et leur projet, pour que le chemin « Projet › Lot › Sous-lot » s'affiche sans chargement paresseux. `moveToLot()` et `deleteForLots()` suivent le modèle de `LotProgressRepository`.

La grille reste construite par `TimesheetBuilder::build()`. Il charge les favoris de la personne connectée et les ajoute aux feuilles saisies sur la semaine affichée ou la précédente, ainsi qu'aux lignes ajoutées. Chaque feuille n'y figure qu'une fois, indexée par son id. Il trie ensuite deux groupes avec `LeafOrder::compare` : les favoris d'abord, les autres lignes ensuite (règles 7, 8 et 10 du pitch). `TimesheetRow` reçoit un drapeau `favorite`, qui pilote l'étoile. Le Live Component `Timesheet` expose deux actions, `addFavorite` et `removeFavorite`, qui passent par `leaf()` existant. Une feuille découpée y est donc refusée par un 404, avant même d'atteindre le service. `removeFavorite` ajoute la feuille à `addedLotIds` : la ligne reste visible jusqu'au prochain montage, ce qui permet de se raviser, puis disparaît au rechargement si elle n'a pas de temps (règle 11). `ProjectManager` fait suivre les favoris aux mêmes endroits que les avancements : vers le premier sous-lot dans `addSubLot()` (règle 14), vers le lot redevenu feuille dans `deleteLot()` (règle 15), et il les supprime dans `deleteLot()` et `deleteProject()` (règle 16). Tout se passe dans les transactions existantes.

### Mécanismes mobilisés

- **Entité Doctrine + repository dédié** (`FavoriteLot`, `FavoriteLotRepository`) : même forme que `TimeEntry` et `LotProgress`, avec des colonnes `JoinColumn` en snake_case, une contrainte d'unicité nommée et aucune cascade ORM. Toutes les lectures et écritures en masse passent par des méthodes nommées. Le déplacement par `UPDATE` en DQL ne serait pas possible sur une table de jointure plusieurs-à-plusieurs.
- **Invariant dans le constructeur de l'entité** : `new FavoriteLot()` lève une `\LogicException` sur un lot découpé, comme `Lot::__construct()` le fait pour la profondeur. La règle 1 du pitch tient alors quel que soit l'appelant.
- **Service métier `FavoriteManager`** : l'écriture ne vit ni dans le composant ni dans le repository (`CLAUDE.md`). `add()` vérifie l'existence par `isFavorite()` avant d'insérer, sans intercepter la violation d'unicité : après une exception au `flush()`, l'EntityManager est fermé et le réaffichage du composant échouerait. La contrainte d'unicité suffit à garantir l'intégrité.
- **Live Component `Timesheet`** (story 003) : deux `#[LiveAction]` sur le modèle de `addLot`. Le composant lit toujours l'utilisateur dans `Security`, jamais dans une prop. Le cloisonnement des favoris ne dépend donc d'aucune donnée envoyée par le navigateur.
- **`LeafOrder::compare`** : le tri du référentiel est réutilisé tel quel pour chacun des deux groupes, sans nouveau comparateur.
- **Transactions existantes de `ProjectManager`** : `addSubLot()` et `deleteLot()` déplacent déjà temps et avancements par des appels explicites aux repositories dans `wrapInTransaction()`. Les favoris s'y ajoutent, au même endroit.
- **Partial Twig** pour l'étoile : un seul gabarit, rendu sur chaque ligne de la grille et dans chaque résultat de la recherche. Il utilise les icônes `tabler:star` et `tabler:star-filled` (ux-icons, récupérées à la demande, comme les autres icônes Tabler du projet).

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Relation plusieurs-à-plusieurs `User` ↔ `Lot` (table de jointure, comme `user_tag`) | Le transfert vers le premier sous-lot exigerait du SQL brut, puisque le DQL ne met pas à jour une table de jointure. `User` grossirait d'une collection que seule la saisie lit. |
| Une seule action `toggleFavorite` | Une bascule envoyée deux fois, par deux onglets ou un double clic, annule le geste. Deux actions idempotentes donnent le même résultat quel que soit le nombre d'envois. |
| Ligne retirée des favoris qui disparaît aussitôt | Le réaffichage du composant effacerait la ligne sous le curseur, et un clic par erreur obligerait à la rechercher. Tranché au plan : elle rejoint `addedLotIds` jusqu'au rechargement. |
| `ON DELETE CASCADE` sur `favorite_lot.lot_id` | Ce serait le premier usage dans le projet, et le nettoyage deviendrait invisible depuis `ProjectManager`, où vivent déjà les suppressions explicites des avancements. Tranché au plan. |
| Intercepter `UniqueConstraintViolationException` dans `add()` | L'EntityManager fermé casserait le réaffichage du composant dans la même requête. Le contrôle préalable couvre tous les cas sauf une course de quelques millisecondes entre deux onglets, sans risque pour les données. |

## Modèle de données

### Nouvelle structure `FavoriteLot`

`src/Entity/FavoriteLot.php` (table `favorite_lot`) :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `user` | ManyToOne (`User`), colonne `user_id` | non | aucune cascade ; les personnes ne sont jamais supprimées, seulement désactivées (favoris conservés) |
| `lot` | ManyToOne (`Lot`), colonne `lot_id` | non | une feuille à la création (`\LogicException` sinon) ; aucune cascade ORM : transfert et suppression explicites par `FavoriteLotRepository`, appelés depuis `ProjectManager` |

- Unicité `uniq_favorite_lot_user_lot` sur (`user_id`, `lot_id`). Elle sert aussi d'index à `findLotsOf()` et `isFavorite()`. Doctrine génère l'index de la clé étrangère `lot_id`, qui sert `moveToLot()` et `deleteForLots()`.
- L'entité n'a que des accesseurs, sans mutateur. Un favori se crée ou se supprime, il ne se modifie jamais en PHP : seul `moveToLot()` change son lot, par une requête.
- Pas de cloisonnement par organisation : l'application en a une seule. Le cloisonnement par personne se fait au filtre `user` de chaque lecture.
- Migration générée par `make migration`. Elle crée seulement la table et ne reprend aucune donnée, puisque personne n'a encore de favori.

### Modification d'un modèle calculé (non persisté)

`src/Model/Timesheet/TimesheetRow.php` : ajout de `public bool $favorite`, troisième paramètre du constructeur, que seul `TimesheetBuilder` renseigne.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Entity/FavoriteLot.php` | Favori d'une personne sur une feuille, unique par couple ; refuse un lot découpé. |
| `src/Repository/FavoriteLotRepository.php` | `findLotsOf(User)` (feuilles avec parent et projet, une requête), `isFavorite(User, Lot)`, `removeFor(User, Lot)` (DELETE DQL), `moveToLot(Lot, Lot)`, `deleteForLots(list<Lot>)`. |
| `src/Service/FavoriteManager.php` | `add(User, Lot)` sans doublon, `remove(User, Lot)` sans effet si absent. |
| `migrations/Version<horodatage>.php` | Création de `favorite_lot` (générée par `make migration`, relue). |
| `templates/timesheet/_favorite_star.html.twig` | Bouton étoile (`data-test="favorite-toggle"`, `data-favorite`, libellé « Mettre en favori : <chemin> » / « Retirer des favoris : <chemin> » et infobulle `title`), action `addFavorite` ou `removeFavorite`, fermeture optionnelle de la fenêtre de recherche. |
| `tests/Support/CreatesFavorites.php` | Trait de test `createFavorite(User, Lot)`, sur le modèle de `CreatesProgress`. |
| `tests/Service/FavoriteManagerTest.php` | Ajout idempotent, retrait idempotent, favoris cloisonnés par personne (contre la base). |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Model/Timesheet/TimesheetRow.php` | Ajouter le drapeau `favorite`. |
| `src/Service/TimesheetBuilder.php` | Injecter `FavoriteLotRepository` ; ajouter les favoris de la personne aux feuilles saisies et ajoutées (sans refaire `findLeavesByIds()` pour une feuille déjà connue) ; trier favoris puis autres lignes avec `LeafOrder::compare` ; passer le drapeau à chaque `TimesheetRow`. |
| `src/Twig/Components/Timesheet.php` | Injecter `FavoriteManager` ; ajouter `#[LiveAction] addFavorite(int $lot)` (via `leaf()`, vide `query` comme `addLot`) et `#[LiveAction] removeFavorite(int $lot)` (via `leaf()`, ajoute l'id à `addedLotIds` s'il n'y est pas). |
| `templates/components/Timesheet.html.twig` | Étoile dans une cellule de tête dédiée de chaque ligne, sous un en-tête de colonne `sr-only` « Favori », pour que l'en-tête de ligne ne porte que le chemin (visible aussi sur téléphone, cette cellule n'étant jamais masquée) ; colonne « Lot » en `min-w-46` pour que la grille tienne toujours sur téléphone ; `colspan` de l'état vide à 7. Dans la recherche, chaque `<li>` devient deux boutons côte à côte, la ligne (inchangée) puis l'étoile, qui ferme aussi la fenêtre. Couleur de l'étoile pleine tirée des tokens de `DESIGN.md`. |
| `src/Service/ProjectManager.php` | Injecter `FavoriteLotRepository` ; `addSubLot()` : `moveToLot(parent, subLot)` si `takesOver` ; `deleteLot()` : `moveToLot(lot, takenBackBy)` si le parent redevient feuille, sinon `deleteForLots([lot, ...children])` ; `deleteProject()` : `deleteForLots(project.lots)`. Toujours dans la transaction existante, avant le `remove()`. |
| `fixtures/DemoCompanyFixtures.php` | Quelques favoris pour Paula (`prod@example.com`) et Louis (`lead@example.com`), dont une feuille sans temps sur les deux dernières semaines. |
| `fixtures/ProjectFixtures.php` | Référence `TIMESHEET` sur le lot « Saisie des temps » du projet Kadence, feuille sans temps que Paula met en favori. |
| `tests/Service/TimesheetBuilderTest.php` | Cas des favoris (voir §Stratégie de test). |
| `tests/Twig/Components/TimesheetTest.php` | Actions `addFavorite` et `removeFavorite`, rendu de l'étoile. |
| `tests/Service/ProjectManagerTest.php` | Transfert au découpage et au retour à une feuille, suppressions non bloquées par un favori (contre la base). |
| `tests/Unit/Service/ProjectManagerTest.php` | Ajouter `FavoriteLotRepository` en dépendance factice (`createStub`) aux constructions existantes. |
| `tests/Controller/TimesheetControllerTest.php` | Budget de requêtes : la personne aux nombreuses lignes reçoit aussi des favoris (avec et sans temps) ; égalité maintenue, plafond maintenu à 6. |
| `tests/e2e/timesheet.spec.ts` | Nouveau scénario des favoris ; `purge()` supprime les `favorite_lot` des lots « Saisie E2E » avant les lots. |
| `tests/e2e/holidays.spec.ts` | Le scénario « le jour ajouté est verrouillé et nommé dans la grille » cible la ligne ajoutée au lieu de compter les lignes : les favoris de Paula s'affichent sur toutes les semaines. |

## Hors scope

- **Message de `deleteLot()` / `deleteProject()` sur violation de clé étrangère** : toute violation y devient « porte des temps ». Le message n'est pas retouché ici ; il ne se déclenche pas, puisque les favoris sont supprimés avant.
- **Correction de `docs/stack.md`** (qui disait encore que SQLite n'applique pas les clés étrangères) : relève de `/forge:sync`, qui l'a réalignée.
- **Refonte de la recherche `LeafFinder`** : inchangée. Elle exclut déjà les feuilles affichées, donc tout favori, et son étoile est toujours vide.
- **Optimisation du budget de requêtes** au-delà de la requête des favoris : non embarquée.

## Impacts transverses

- **Cloisonnement des données** : chaque lecture et écriture passe par l'utilisateur de `Security` (`Timesheet::user()`), jamais par une prop. `findLotsOf()`, `isFavorite()` et `removeFor()` filtrent sur la personne. Aucun autre écran ne lit les favoris.
- **Déclinaisons / thèmes** : non. L'étoile utilise les tokens du design system « Cadence », en mode clair comme en mode sombre.
- **Traduction / i18n** : non. Les libellés sont en français dans le gabarit, comme le reste de la grille (application en français uniquement).
- **API / exposition externe** : non.
- **Droits d'accès** : inchangés. `/saisie` est réservé aux personnes connectées. Toute feuille est saisissable, et donc épinglable, comme l'ajout d'une ligne. Aucun voter.
- **Emails / notifications** : non.
- **Migration de données** : création de la table `favorite_lot` seulement ; aucune reprise.
- **Comportement par défaut** : sans favori, la grille est identique à aujourd'hui, avec une étoile vide sur chaque ligne et dans chaque résultat de recherche.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `tests/Service/FavoriteManagerTest.php` | functional (base) | `add()` deux fois laisse un seul favori ; `remove()` d'un favori absent ne fait rien ; les favoris d'une personne n'apparaissent pas dans `findLotsOf()` d'une autre ; `new FavoriteLot()` sur un lot découpé lève une `\LogicException`. |
| `tests/Service/TimesheetBuilderTest.php` | functional (base) | Un favori sans temps est une ligne sur une semaine passée, la semaine en cours et une semaine future ; les favoris passent avant les autres lignes, chaque groupe trié par `LeafOrder` ; un favori qui porte des temps n'apparaît qu'une fois, avec `favorite` vrai ; une feuille ajoutée qui est aussi favorite n'apparaît qu'une fois ; les favoris d'une autre personne sont absents. |
| `tests/Twig/Components/TimesheetTest.php` | functional (composant) | `addFavorite` épingle et rend l'étoile pleine, la ligne en tête ; `addFavorite` depuis la recherche ajoute la ligne et vide la requête ; une ligne ajoutée puis étoilée reste après un nouveau montage ; `removeFavorite` sur une feuille sans temps la garde affichée jusqu'à un nouveau montage, puis elle disparaît ; `removeFavorite` sur une feuille avec temps la laisse parmi les autres lignes ; une feuille découpée est refusée en 404 ; une grille sans temps mais avec un favori n'affiche pas l'état vide. |
| `tests/Service/ProjectManagerTest.php` | functional (base) | Le premier sous-lot d'un lot favori reprend le favori ; la suppression du dernier sous-lot rend le favori au lot ; la suppression d'un lot (avec ses sous-lots) et celle d'un projet portant des favoris réussissent et suppriment les favoris, en deux tests (un `DELETE` DQL laisse les entités gérées périmées entre deux `flush()`). Sans le nettoyage, la clé étrangère lèverait `LotHasTimeEntriesException`. |
| `tests/Unit/Service/ProjectManagerTest.php` | unit | Inchangé dans ses assertions ; nouvelle dépendance factice seulement. |
| `tests/Controller/TimesheetControllerTest.php` | functional (HTTP) | Le nombre de requêtes de `/saisie` reste le même avec une ligne ou avec de nombreuses lignes dont des favoris. |
| `tests/e2e/timesheet.spec.ts` | E2E | Épingler « Gamma » depuis l'étoile de la recherche : la ligne apparaît en tête, étoile pleine ; elle reste après rechargement et trois semaines plus tôt ; retirer l'étoile depuis la grille, recharger : la ligne a disparu. |
| `tests/e2e/holidays.spec.ts` | E2E | Inchangé dans son intention : les cases du jour férié de la ligne ajoutée, ciblée par son `data-lot`. |

**Hors scope tests** :

- La course entre deux onglets qui ajoutent le même favori à la même milliseconde : la contrainte d'unicité empêche le doublon, au prix d'une erreur affichée. Non reproductible de façon fiable en test.
- L'étoile sur téléphone en E2E : c'est le même balisage dans une cellule jamais masquée, et le scénario mobile existant traverse déjà la grille.
- La règle 17 du pitch (clôture, archivage) : ces états n'existent pas encore.

## Ordre d'exécution

1. [x] **Modèle, migration et repository**
   - Objectif : table `favorite_lot` et requêtes nommées.
   - Fichiers : `src/Entity/FavoriteLot.php`, `src/Repository/FavoriteLotRepository.php`, `migrations/Version<horodatage>.php`, `tests/Support/CreatesFavorites.php`.
   - Vérification : `make migration` puis migration relue ; `symfony console doctrine:schema:validate` ; `make lint`.
   - Commitable seule : oui.

2. [x] **Service `FavoriteManager`**
   - Objectif : ajout et retrait idempotents.
   - Fichiers : `src/Service/FavoriteManager.php`, `tests/Service/FavoriteManagerTest.php`.
   - Vérification : `make phpunit-filter FavoriteManagerTest`.
   - Commitable seule : oui.

3. [x] **Grille : favoris en tête**
   - Objectif : `TimesheetBuilder` intègre et trie les favoris, `TimesheetRow` porte le drapeau.
   - Fichiers : `src/Model/Timesheet/TimesheetRow.php`, `src/Service/TimesheetBuilder.php`, `tests/Service/TimesheetBuilderTest.php`, `tests/Controller/TimesheetControllerTest.php`.
   - Vérification : `make phpunit-filter TimesheetBuilderTest` ; `make phpunit-filter TimesheetControllerTest` (budget mesuré, plafond ajusté si nécessaire).
   - Commitable seule : oui.

4. [x] **Référentiel : les favoris suivent la structure**
   - Objectif : transfert au découpage et au retour à une feuille, suppression avec la feuille, le lot ou le projet.
   - Fichiers : `src/Service/ProjectManager.php`, `tests/Service/ProjectManagerTest.php`, `tests/Unit/Service/ProjectManagerTest.php`.
   - Vérification : `make phpunit-filter ProjectManagerTest`.
   - Commitable seule : oui.

5. [x] **Composant et gabarit : l'étoile**
   - Objectif : actions `addFavorite` et `removeFavorite`, étoile sur les lignes et dans la recherche.
   - Fichiers : `src/Twig/Components/Timesheet.php`, `templates/components/Timesheet.html.twig`, `templates/timesheet/_favorite_star.html.twig`, `tests/Twig/Components/TimesheetTest.php`.
   - Vérification : `make phpunit-filter TimesheetTest` ; contrôle visuel sur `https://kadence.wip/saisie` (ordinateur et téléphone, clair et sombre).
   - Commitable seule : oui.

6. [x] **Données de démonstration**
   - Objectif : favoris de Paula et Louis, dont une feuille sans temps récent.
   - Fichiers : `fixtures/DemoCompanyFixtures.php`, `fixtures/ProjectFixtures.php`.
   - Vérification : `make db-reset` puis connexion `prod@example.com` : favoris en tête de la grille.
   - Commitable seule : oui.

7. [x] **E2E et QA finale**
   - Objectif : parcours des favoris de bout en bout, purge sans violation de clé étrangère.
   - Fichiers : `tests/e2e/timesheet.spec.ts`, `tests/e2e/holidays.spec.ts`.
   - Vérification : `make serve` puis `make playwright-file tests/e2e/timesheet.spec.ts` et `make playwright` (suite complète) ; `make lint` ; `make phpunit`.
   - Commitable seule : oui.

## Critères de sortie

- [x] La table `favorite_lot` existe avec l'unicité (`user_id`, `lot_id`), créée par une migration générée ; `doctrine:schema:validate` est vert.
- [x] Aucune requête sur les favoris hors de `FavoriteLotRepository` ; aucune écriture de favori hors de `FavoriteManager` et `ProjectManager`.
- [x] Le nombre de requêtes de `/saisie` ne dépend ni du nombre de lignes ni du nombre de favoris (`TimesheetControllerTest`).
- [x] Découper un lot favori, supprimer le dernier sous-lot, supprimer un lot ou un projet portant des favoris ne lève aucune violation de clé étrangère.
- [x] `make phpunit` vert, sans nouvelle régression.
- [x] `make playwright-file tests/e2e/timesheet.spec.ts` vert, et la purge laisse la base sans favori « Saisie E2E ».
- [x] `make lint` propre (PHP-CS-Fixer et PHPStan niveau 10).
- [x] Sans favori, la grille rend les mêmes lignes, dans le même ordre, qu'avant la story (tests existants de `TimesheetBuilderTest` et `TimesheetTest` inchangés et verts).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Un chemin de suppression oublie les favoris : la clé étrangère lève une violation, que `deleteLot()` et `deleteProject()` traduisent à tort en « porte des temps » | moyenne | Tests contre la base des trois chemins (lot seul, lot avec sous-lots, projet) dans `tests/Service/ProjectManagerTest.php`. |
| Le budget de requêtes de `/saisie` (≤ 6) est dépassé par la requête des favoris | élevée | Une seule requête avec jointures sur le parent et le projet ; plafond relevé à 7 si la mesure l'exige, l'égalité petite et grande grille restant l'invariant. Non survenu : la page passe à 6 requêtes, plafond maintenu. |
| La purge E2E supprime les lots avant leurs favoris, ce qui viole la clé étrangère (`dbal:run-sql` passe par la connexion qui active les clés étrangères) | élevée | `purge()` supprime `favorite_lot` des lots « Saisie E2E » en premier. |
| Les favoris de démonstration de Paula ajoutent des lignes dans les scénarios E2E existants | faible | Lignes sans temps, sur des projets de démonstration ; suite E2E complète rejouée à l'étape 7. Survenu : `holidays.spec.ts` comptait exactement une ligne sur une semaine de 2030 ; le scénario cible désormais la ligne ajoutée. |
| Un lead découpe une feuille pendant qu'une grille est ouverte : l'étoile de la feuille périmée renvoie un 404 | faible | Même comportement que `record` ou `addLot` sur une feuille découpée ; le rechargement montre le sous-lot, qui porte le favori. |
| Deux onglets ajoutent le même favori simultanément : violation d'unicité et erreur affichée | faible | Contrôle préalable par `isFavorite()` ; aucune donnée incohérente possible ; accepté (§Hors scope tests). |

## Questions ouvertes

- **Plafond du budget de requêtes de `/saisie`** : rester à 6 si la requête des favoris s'absorbe, sinon passer à 7. À trancher à l'étape 3, sur mesure. → tranché : le plafond reste à 6 ; la page passe de 5 à 6 requêtes avec la requête des favoris, identique avec ou sans favoris.
- **`docs/stack.md` périmé sur les clés étrangères SQLite** : il les dit non appliquées, alors que `config/services.yaml` active `EnableForeignKeys`. À réaligner par `/forge:sync`. → tranché : réaligné par `/forge:sync` (`docs/stack.md` §Contraintes & dette).
