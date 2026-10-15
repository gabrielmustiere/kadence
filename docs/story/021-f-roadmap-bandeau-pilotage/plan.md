# Plan technique — Lire en tête de la roadmap combien de lots demandent une action, et n'afficher qu'eux d'un clic

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/021-f-roadmap-bandeau-pilotage/`
> **Amont** : `pitch.md`

## Approche retenue

Tout se calcule **après** `RoadmapBuilder::build()`, qui ne change pas : l'accueil (`HomeAgendaBuilder`) en dépend, et les signaux de chaque feuille y sont déjà posés, `ToReplan` compris pour un lead. Un modèle pur, `RoadmapBoard`, lit la `Roadmap` construite : il parcourt les feuilles non terminées (ni `Completed` ni `EstimateReached`) et compte, pour chacun des huit signaux d'action, les feuilles qui le portent et leurs projets distincts. Ces signaux sont rangés en trois familles ordonnées. Le filtrage est une méthode de la `Roadmap` qui rend une roadmap élaguée sur un signal. Chaque ligne parente est clonée avec ses seuls enfants retenus (`clone with` de PHP 8.5), si bien que sa barre, sa fin et ses signaux restent ceux de l'ensemble. Les projets sans feuille retenue disparaissent. Ni requête ni ordonnancement en plus.

Un petit objet de valeur, `RoadmapView` (`Week` + `?RoadmapSignal`), porte l'état « semaine + filtre » d'un bout à l'autre. Il se construit depuis des chaînes de requête ou de formulaire : la semaine passe par `Week::tryFromIso`, le signal par `RoadmapSignal::tryFrom`, limité aux huit signaux du bandeau et **abandonné quand le lecteur n'est pas lead**. Il rend ses paramètres de route. Le `RoadmapController` s'en sert pour la page, et il remplace le « back » `Week` actuel de `ProjectController::show`, `LotController::edit` et `LotController::backWeek`. Les templates propagent `signal` partout où ils propagent déjà `roadmap` : liens de navigation, lien d'édition d'une feuille, flèche vers la page projet, lien et champs cachés de retour de la page projet. Le bandeau est un partiel Twig fait de liens `<a href>` ; sur la semaine courante, une tuile et « Tout afficher » mènent à `/roadmap?signal=…` plutôt qu'à la semaine, pour qu'un lien partagé reste sur aujourd'hui. Le filtre fonctionne sans JS, et le zoom, gardé en `sessionStorage` par `roadmap_controller.js`, survit seul à la navigation.

**Complément au pitch** (tranché au plan) : le retour vers la roadmap depuis l'édition d'une feuille, ou depuis la page projet ouverte à partir d'une frise filtrée, garde le filtre, en plus des semaines et du zoom (règle 11). `/forge:sync` le reportera dans le pitch.

### Mécanismes mobilisés

- **Modèle de lecture pur (`src/Model/Roadmap/`)** : les compteurs et l'élagage sont des fonctions de la `Roadmap` déjà construite. On peut les tester en unitaire sans base, comme `RoadmapRow` et `RoadmapWindow` aujourd'hui.
- **`clone` avec propriétés (PHP 8.5)** : `RoadmapRow` est un readonly à vingt arguments. Cloner en remplaçant `children`, depuis la portée de la classe, évite de le reconstruire champ par champ et garantit que barre, fin et signaux du parent ne bougent pas (règle 10 du pitch).
- **Méthodes d'enum (`RoadmapSignal`)** : `action(): ?string` rejoint `label()`, `meaning()`, `reason()` et `variant()`. Un `match` exhaustif renvoie `null` pour un signal sans action. C'est là que le projet range déjà les textes d'un signal.
- **`#[MapQueryParameter]` en chaîne + `tryFrom`** : on garde le motif de `?roadmap=` (`Week::tryFromIso`). Un `#[MapQueryParameter] ?RoadmapSignal` natif répondrait 404 sur une valeur inconnue, alors qu'un lien périmé ou fantaisiste doit simplement montrer la roadmap (règle 14).
- **`isGranted('ROLE_LEAD')`** : le contrôleur le passe déjà au builder. Il décide aussi de l'affichage du bandeau et du sort du filtre, sans nouveau voter.
- **`<details open>` hors mémoire Stimulus** : en mode filtré, les projets sont rendus ouverts, sans `data-roadmap-target="project"` ni action `remember`. L'état déplié gardé en `sessionStorage` n'est donc ni appliqué ni écrasé.
- **Composants du design system** : `<twig:Badge>` avec la variante du signal (`variant()`) dans chaque tuile, pour reprendre la couleur des pastilles de la frise. Les tokens de `DESIGN.md` servent pour le cadre (`rounded-card`, `border-default`), et `<twig:EmptyState>` pour « Rien à traiter pour « … » ».

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Passer un `?signal` à `RoadmapBuilder::build()` pour n'émettre que les lignes filtrées | Alourdit un builder déjà dense et partagé avec l'accueil, et ses tests passent par la base. |
| Deux paramètres `roadmap` et `signal` lus séparément dans chaque contrôleur | La validation (signal connu, d'action, lecteur lead) serait dupliquée à quatre endroits. |
| Filtrer côté client (Stimulus masque les lignes) | Le lien ne serait ni partageable ni conservé à la navigation, et les compteurs devraient être calculés de toute façon côté serveur. |
| `#[MapQueryParameter] ?RoadmapSignal` natif | Répond 404 sur une valeur inconnue, alors que le pitch veut une roadmap normale. |

## Modèle de données

Aucun impact modèle.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Model/Roadmap/RoadmapBoard.php` | Le bandeau : les familles ordonnées (Dérives, Planification, Avancement) et leurs compteurs non nuls, lus sur une `Roadmap`. Il expose aussi la liste des huit signaux d'action. |
| `src/Model/Roadmap/RoadmapCounter.php` | Un compteur : le signal, le nombre de feuilles, le nombre de projets distincts. |
| `src/Model/Roadmap/RoadmapView.php` | Semaine + signal facultatif lus depuis des chaînes. Signal abandonné s'il est inconnu, sans action ou si le lecteur n'est pas lead. Paramètres de route rendus. |
| `templates/roadmap/_board.html.twig` | Le bandeau : familles, tuiles-liens (chiffre, badge du signal, « sur N projets », action), tuile active marquée, « Tout afficher », « Rien à traiter ». |
| `tests/Unit/Model/Roadmap/RoadmapBoardTest.php` | Comptage par signal, exclusion des feuilles terminées, feuille à plusieurs signaux, projets distincts, ordre des familles, compteurs nuls absents. |
| `tests/Unit/Model/Roadmap/RoadmapViewTest.php` | Semaine invalide, signal inconnu, signal sans action, lecteur non lead, paramètres de route avec ou sans signal. |
| `tests/e2e/roadmap-board.spec.ts` | Clic sur une tuile, frise filtrée et dépliée, navigation qui garde le filtre, « Tout afficher », retour depuis l'édition d'une feuille, téléphone sans défilement horizontal. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Enum/Type/RoadmapSignal.php` | Ajouter `action(): ?string` (les huit libellés du pitch, `null` pour les autres). |
| `src/Model/Roadmap/RoadmapRow.php` | Ajouter `isFinished()` (porte `Completed` ou `EstimateReached`), `leavesAwaiting(RoadmapSignal)` (les feuilles qui portent le signal sans être terminées, prédicat partagé par le bandeau et l'élagage) et `only(RoadmapSignal): ?self` : la feuille si elle est retenue, sinon le parent cloné avec ses seuls enfants retenus, `null` s'il n'en reste aucun. |
| `src/Model/Roadmap/Roadmap.php` | Ajouter `only(RoadmapSignal): self`, la même roadmap restreinte aux projets que `RoadmapRow::only()` garde. |
| `src/Controller/RoadmapController.php` | `current()` et `week()` lisent `?signal`, bâtissent la `RoadmapView`, calculent le `RoadmapBoard` pour un lead, élaguent la roadmap si un signal est retenu, et passent `view` et `board` au template. |
| `src/Controller/ProjectController.php` | `show()` lit `?signal` à côté de `?roadmap` et passe une `RoadmapView` comme `back` à la place de la `Week`. |
| `src/Controller/LotController.php` | `edit()` construit le chemin de retour depuis une `RoadmapView` (semaine + signal). `backWeek()` devient `backParams()`, qui lit une `RoadmapView` dans le payload (`roadmap`, `signal`) et rend les paramètres que `delete()` et `progress()` reportent dans leur redirection. |
| `templates/roadmap/index.html.twig` | Poser une fois la route de la roadmap sans filtre (semaine courante ou non), qu'utilisent `_board` et l'état vide. Inclure `_board` pour un lead. Porter `signal` dans les liens « ± 4 semaines » et « Aujourd'hui » et dans la flèche vers la page projet. En mode filtré : `<details open>` sans cible ni action Stimulus, et état vide « Rien à traiter pour « … » » avec « Tout afficher ». |
| `templates/roadmap/_row.html.twig` | Porter `signal` dans le lien d'édition d'une feuille. |
| `templates/project/show.html.twig` | Le lien de retour vers la roadmap prend les paramètres de route de `back` (semaine + signal). |
| `templates/project/_lot_row.html.twig` | Ajouter le champ caché `signal` à côté de `roadmap`. |
| `templates/project/_progress.html.twig` | Ajouter le champ caché `signal` à côté de `roadmap`. |
| `templates/project/_lots_table.html.twig` | Mettre à jour le commentaire d'en-tête : `back` est désormais une `RoadmapView`. |
| `tests/Unit/Model/Roadmap/RoadmapRowTest.php` | Cas de `isFinished()` et `only()` : feuille retenue ou non, lot découpé élagué, barre, fin et signaux du parent inchangés, parent vide écarté. |
| `tests/Controller/RoadmapControllerTest.php` | Bandeau pour lead et direction, absent pour la prod. `?signal` filtre et déplie, signal ignoré pour la prod ou s'il est invalide, lien périmé, liens de navigation et d'édition qui portent le filtre, nombre de requêtes inchangé avec le bandeau et le filtre. |
| `tests/Controller/LotControllerTest.php` | Le retour de l'édition d'une feuille garde `signal`. Le lien de retour de la page projet garde `signal`. |
| `tests/Controller/LotProgressControllerTest.php` | La déclaration d'avancement redirige vers la page projet en gardant `roadmap` et `signal`. |

## Hors scope

- **Retour depuis l'édition d'une feuille ouverte depuis la page projet** : il mène aujourd'hui à la page projet sans la semaine (`LotController::edit`, `backPath` sans `roadmap`). Ce comportement existant n'est pas corrigé ici.
- **`HomeAgendaBuilder` et le bloc « Projets à surveiller »** : intouchés, et `RoadmapBuilder::build()` garde son contrat.
- **Composant Twig générique de tuile** : un seul usage, donc un partiel suffit, sans nouveau composant dans `templates/components/`.
- **Page d'une personne** (`PersonRoadmapBuilder`) : non concernée.

## Impacts transverses

- **Cloisonnement des données** : non. Le bandeau compte des feuilles déjà visibles d'un lead. `ToReplan` n'est posé que si le contrôleur passe le rôle lead, et le bandeau n'est rendu que pour ce rôle.
- **Déclinaisons / thèmes** : mode clair et sombre via les tokens existants (`.dark`). Aucune classe de couleur en dur hors tokens.
- **Traduction / i18n** : libellés en français en dur, comme le reste de l'application (les textes des signaux vivent dans l'enum). Pas de `|trans`.
- **API / exposition externe** : non.
- **Droits d'accès** : `isGranted('ROLE_LEAD')` existant, sans nouveau voter. Le signal est abandonné dans `RoadmapView` pour la prod, sur la roadmap comme dans les liens de retour.
- **Emails / notifications** : non.
- **Migration de données** : aucune.
- **Comportement par défaut** : sans `?signal`, la roadmap est rendue comme aujourd'hui ; seul le bandeau s'ajoute pour un lead. Pour la prod, la page est identique à l'octet près hors données.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/Roadmap/RoadmapBoard.php` | unit | Huit signaux comptés en feuilles et en projets distincts. Feuille terminée (`Completed`, `EstimateReached`) jamais comptée, même avec `Overrun`. Une feuille à deux signaux compte dans les deux. Compteurs à zéro et familles vides absents. Ordre Dérives, Planification, Avancement, puis ordre du pitch dans chaque famille. Bandeau vide quand rien n'est à traiter. |
| `src/Model/Roadmap/RoadmapRow.php` | unit | `only()` garde une feuille qui porte le signal sans être terminée et écarte les autres. Un lot découpé ne garde que ses sous-lots retenus, ou disparaît. Le parent garde `span`, `end` et `signals` d'origine. `isFinished()` vaut pour les deux signaux de fin. |
| `src/Model/Roadmap/Roadmap.php` | unit | `only()` retire les projets sans feuille retenue et garde fenêtre et date du jour. Couvert dans `RoadmapBoardTest` ou `RoadmapRowTest`, au choix de l'implémentation. |
| `src/Model/Roadmap/RoadmapView.php` | unit | Semaine invalide (`null`). Signal inconnu, sans action (`completed`, `unsplit`) ou lecteur non lead : vue sans signal. Paramètres de route `{week}` ou `{week, signal}`. |
| `src/Enum/Type/RoadmapSignal.php` | unit | `action()` non nul exactement pour les huit signaux du bandeau, vérifié dans `RoadmapBoardTest`. |
| `src/Controller/RoadmapController.php` | functional | Lead et direction voient `roadmap-board`, la prod ne le voit pas. Les compteurs sont identiques sur deux périodes. Le lien périmé se joue au 03/01/2000 sur « démarrage en retard », qu'aucune feuille ne peut porter avant toute date de début : la base partagée finit par porter les huit signaux. `?signal=to_estimate` ne laisse que les feuilles concernées, `<details open>` sans cible Stimulus. Prod + `?signal` donne la roadmap complète. Signal inconnu donne la roadmap complète. Lien périmé affiche le message et « Tout afficher ». Les liens précédent, suivant, aujourd'hui, édition de feuille et page projet portent `signal`. Le test du nombre de requêtes reste vert avec un signal. |
| `src/Controller/LotController.php` | functional | Édition ouverte avec `?roadmap=…&signal=…` : le lien Retour et la redirection après enregistrement mènent à `/roadmap/<semaine>?signal=…`. La page projet ouverte avec ces paramètres a un lien Retour qui les garde. |
| `src/Controller/LotController.php` (`progress`) | functional | La déclaration postée avec `roadmap` et `signal` redirige vers la page projet en les gardant. |
| `tests/e2e/roadmap-board.spec.ts` | E2E | En lead : une tuile mène à la frise filtrée, projet déplié, tuile marquée. « 4 semaines » garde le filtre. « Tout afficher » rend la frise complète. Éditer une feuille depuis la frise filtrée et enregistrer ramène à la frise filtrée. À 375 px, le bandeau passe à la ligne sans défilement horizontal de la page. |

**Hors scope tests** :

- Valeurs exactes des compteurs en E2E : la base est partagée avec les fixtures et les autres specs. L'E2E vérifie la présence de sa propre feuille dans la frise filtrée et l'absence d'un projet sans signal, pas un total. Les comptes exacts sont couverts en unitaire.
- Le zoom en mode filtré : il vit en `sessionStorage`, indépendant du filtre, et son maintien entre pages est déjà couvert par `roadmap.spec.ts`.
- Mode sombre : aucun test visuel. Les tokens existants s'appliquent.

## Ordre d'exécution

1. [x] **Libellés d'action et lecture du bandeau**
   - Objectif : `RoadmapSignal::action()`, `RoadmapCounter` et `RoadmapBoard` calculés sur une `Roadmap`, avec feuilles terminées exclues et familles ordonnées.
   - Fichiers : `RoadmapSignal.php`, `RoadmapCounter.php`, `RoadmapBoard.php`, `RoadmapBoardTest.php`.
   - Vérification : `make phpunit-filter RoadmapBoardTest`.
   - Commitable seule : oui.

2. [x] **Élagage de la roadmap sur un signal**
   - Objectif : `RoadmapRow::isFinished()` et `only()`, puis `Roadmap::only()`, avec parents intacts et projets vides écartés.
   - Fichiers : `RoadmapRow.php`, `Roadmap.php`, `RoadmapRowTest.php`.
   - Vérification : `make phpunit-filter RoadmapRowTest`.
   - Commitable seule : oui.

3. [x] **`RoadmapView`**
   - Objectif : semaine + signal lus et validés en un point, paramètres de route rendus.
   - Fichiers : `RoadmapView.php`, `RoadmapViewTest.php`.
   - Vérification : `make phpunit-filter RoadmapViewTest`.
   - Commitable seule : oui.

4. [x] **Roadmap : bandeau et frise filtrée**
   - Objectif : le contrôleur lit `?signal` et rend `board` et `view`. Le partiel `_board` est en place. Les liens de navigation, d'édition et de page projet portent le filtre. Le mode filtré ouvre les projets hors mémoire Stimulus et gère l'état vide.
   - Fichiers : `RoadmapController.php`, `_board.html.twig`, `index.html.twig`, `_row.html.twig`, `RoadmapControllerTest.php`.
   - Vérification : `make phpunit-filter RoadmapControllerTest`, puis contrôle visuel sur https://kadence.wip/roadmap en lead et en prod, clair et sombre, desktop et 375 px.
   - Commitable seule : oui.

5. [x] **Retours vers la frise filtrée**
   - Objectif : `ProjectController::show`, `LotController::edit`, `delete` et `progress` transportent la `RoadmapView`. Les templates de la page projet reportent `signal`.
   - Fichiers : `ProjectController.php`, `LotController.php`, `project/show.html.twig`, `_lot_row.html.twig`, `_progress.html.twig`, `_lots_table.html.twig`, `LotControllerTest.php`, `LotProgressControllerTest.php`.
   - Vérification : `make phpunit-filter LotControllerTest` et `make phpunit-filter LotProgressControllerTest`.
   - Commitable seule : oui.

6. [x] **E2E et QA finale**
   - Objectif : parcours Playwright du bandeau. Suites complètes vertes, lint propre.
   - Fichiers : `tests/e2e/roadmap-board.spec.ts`.
   - Vérification : `make playwright-file tests/e2e/roadmap-board.spec.ts` (`make serve` lancé), `make phpunit`, `make playwright`, `make lint`.
   - Commitable seule : oui.

## Critères de sortie

- [x] `RoadmapBuilder::build()` et `HomeAgendaBuilder` inchangés (aucun diff).
- [x] Le bandeau et le filtre n'ajoutent aucune requête SQL : `testQueryCountDoesNotGrowWithTheNumberOfLeaves` reste vert, et un cas avec `?signal` est ajouté.
- [x] Aucune valeur de `?signal` ne provoque d'erreur HTTP : un signal inconnu, sans action ou fourni par la prod donne la roadmap complète (200).
- [x] Tous les liens qui transportaient `roadmap=<semaine>` vers ou depuis la roadmap transportent aussi `signal` quand il est retenu.
- [x] `make phpunit` vert, sans nouvelle régression.
- [x] `make playwright` vert, dont `tests/e2e/roadmap-board.spec.ts`.
- [x] `make lint` propre (PHP-CS-Fixer + PHPStan level 10).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| `clone` avec propriétés sur une propriété `readonly` refusé hors de la portée de la classe | faible | Cloner dans `RoadmapRow::only()` même (portée de classe). Repli : constructeur avec arguments nommés. |
| Compteurs et frise filtrée divergents (une feuille comptée mais absente du filtre, ou l'inverse) | moyenne | Un seul prédicat « porte le signal et n'est pas terminée », partagé par `RoadmapBoard` et `RoadmapRow::only()`, testé des deux côtés. |
| L'état déplié en `sessionStorage` écrase l'ouverture forcée, ou est écrasé par elle | moyenne | En mode filtré, rendre `<details open>` sans `data-roadmap-target` ni action `remember`. Vérifié en E2E par un aller-retour entre la frise filtrée et la frise complète. |
| Un lien de retour oublié perd le filtre (formulaire POST de la page projet) | moyenne | Recensement fait au plan (`_lot_row`, `_progress`, `show`, `LotController::edit/delete/progress`). Critère de sortie dédié et tests fonctionnels sur chacun. |
| Bandeau trop haut sur téléphone avec huit tuiles | faible | Tuiles compactes en `flex-wrap` ; seules les tuiles non nulles s'affichent. Vérifié à 375 px en E2E. |

## Questions ouvertes

_(aucune)_
