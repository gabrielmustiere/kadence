# Plan technique — Décrire chaque personne par ses compétences, son équipe et son manager

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/007-f-tags-hierarchie/`
> **Amont** : `pitch.md`

## Approche retenue

Un référentiel `Tag` (catégorie et libellé) est relié à `User` par une seule ManyToMany unidirectionnelle (`user_tag`), quelle que soit la catégorie. La règle « au plus un type d'équipe » n'est pas portée par le schéma : c'est la forme du DTO (`TeamMemberInput::$teamType`, un seul tag) et `User::replaceTags()` qui la tiennent. Le manager est une auto-référence ManyToOne nullable sur `User`. Toute règle métier vit dans deux services :
- **`TagManager`, nouveau :** il crée, renomme, supprime (en détachant d'abord les porteurs) et résout les libellés saisis à la volée.
- **`TeamManager`, étendu :** il pose les tags et le manager, et refuse la désactivation d'une personne qui manage encore des personnes actives.

Les contrôleurs restent minces. Les règles de saisie sont portées par des contraintes de classe sur les DTO, comme `UniqueTeamEmail` et `UniqueProjectTitle` aujourd'hui.

L'interface reste au plus près de l'existant, sans nouvelle dépendance :
- **Fiche personne :** par catégorie, des cases ou des radios pour les tags existants, plus un champ texte « Nouveaux… » séparés par des virgules. Les nouveaux tags sont créés dans le même `flush()` que la personne.
- **Liste Équipe :** elle se filtre côté serveur par un formulaire GET (un tag par catégorie au plus, combinés par ET, plus le manager direct).
- **Composition de l'équipe d'une feuille :** elle se filtre côté client. Un contrôleur Stimulus `tag-filter` reconstruit les options de chaque `<select>` de personne à partir de `data-tags`. Les trois listes du filtre sont rendues par un Twig Component `TagFilter`, ce qui évite de toucher aux trois actions de `LotController`.
- **Page Mon compte :** elle affiche ses propres tags et son manager à partir de `app.user`.

### Mécanismes mobilisés

- **ManyToMany unidirectionnelle `User::$tags` (`user_tag`)** : une seule relation pour les trois catégories, donc filtres (`MEMBER OF`), compteurs et détachement uniformes.
- **ManyToOne auto-référente `User::$manager` (`manager_id`, nullable)** : un manager au plus, sans table de liaison. Aucune reprise, puisque la colonne est nullable.
- **Backed string enum `App\Enum\Type\TagCategory`** : c'est la convention du projet (`Role`, `HolidayCalendar`). Il porte les libellés (`label()`, `pluralLabel()`) et la cardinalité (`allowsMany()`). Il s'appelle `TagCategory` et non `TagType` pour ne pas entrer en collision avec le formulaire `App\Form\TagType`.
- **DTO et contraintes de classe** :
  - `UniqueTagLabel` sur `TagInput` et `NoManagementCycle` sur `TeamMemberInput`, sur le modèle de `UniqueTeamEmail` ;
  - `TagLabelList` sur les champs « Nouveaux… », pour la longueur de chaque libellé ;
  - `Assert\IsTrue` sur un getter de `TeamMemberInput`, pour interdire de choisir un type d'équipe et d'en saisir un nouveau à la fois.
- **`TitleComparison`** : comparaison insensible à la casse faite en PHP, parce que `LOWER()` de SQLite ne traite que l'ASCII. On en extrait `normalize()` pour que `TagManager::resolve()` retrouve le tag correspondant, et pas seulement un booléen.
- **Exception de domaine convertie en flash** : `ManagerWithActiveReportsException` (`\DomainException`), levée par `TeamManager::deactivate()` et attrapée par `TeamController`, comme `LastActiveDirectorException`.
- **Méthodes de repository nommées** : jointure fetch des tags et du manager pour l'affichage. Le filtre passe par `MEMBER OF` ou un alias séparé, jamais par l'alias joint en fetch, ce qui tronquerait les collections chargées.
- **Formulaire GET sans nom ni CSRF** (`createNamed('')`, `csrf_protection: false`) pour la liste Équipe, avec une URL lisible et partageable (`?competence=&experience=&equipe=&manager=`).
- **`#[IsGranted('ROLE_DIRECTION')]`** sur `TagController`, comme `TeamController` et `HolidayController`. Il n'y a pas de voter : la règle est un contour de rôle.
- **`NavigationExtension::SECTIONS`** : préfixe `app_tag_` pour allumer l'entrée « Tags » du menu Administration.
- **Twig Component `TagFilter` (`#[AsTwigComponent]`)** : il charge les tags depuis `TagRepository` et rend les trois listes du filtre de composition.
- **Contrôleur Stimulus `tag_filter_controller.js`** : il reconstruit les options et réagit aux lignes ajoutées par `form-collection` via `personTargetConnected()`.
- **Composants Paper** : `Card`, `Table`, `Badge`, `Modal` et `Button`, réutilisés tels quels (`DESIGN.md`).

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| `symfony/ux-autocomplete` (Tom Select, option `create`) pour la saisie à la volée | Nouvelle dépendance, thème Paper à adapter et DataTransformer pour les valeurs créées. Des cases à cocher plus un champ texte suffisent pour 15 à 40 personnes. |
| Champ à puces maison (Stimulus et `datalist`) | Du JS d'accessibilité et de clavier à maintenir, pour un gain cosmétique. |
| ManyToMany pour compétences et expériences, plus ManyToOne `team_type_id` sur `user` | La cardinalité serait garantie en base, mais filtres, compteurs et suppression devraient traiter deux chemins. |
| Colonne `normalized_label` unique en base | Seconde source de vérité du libellé. Le projet compare déjà en PHP (`TitleComparison`). |
| `onDelete: CASCADE` sur `user_tag` | Les clés étrangères SQLite ne sont pas activées (pas de middleware `EnableForeignKeys`), donc rien ne serait nettoyé. `TagManager::delete()` détache explicitement. |
| Live Component pour le bloc équipe de `LotType` | Refonte lourde de la collection et de son état de saisie, pour un filtre purement local. |
| Masquer les `<option>` non retenues (`hidden`) | Safari ignore `hidden` et `display: none` sur `<option>`. On reconstruit la liste. |
| Filtre de la liste Équipe côté client | Testable seulement en Playwright, et l'URL ne garde pas le filtre. |
| Remplacer `UserRepository::findAllForTeamList()` | Elle est aussi utilisée par `SecurityController` (comptes de dev) et `ScheduleLoader` (roadmap). On ajoute une méthode à côté. |

## Modèle de données

### Nouvelle structure `Tag`

`src/Entity/Tag.php`, table `tag` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `category` | string(20), `enumType: TagCategory` | non | `competence` · `experience` · `equipe` ; jamais modifiée après création (pas de setter) |
| `label` | string(60) | non | sans espaces en début ni en fin ; `uniq_tag_category_label` (`category`, `label`) en filet de base, unicité insensible à la casse tenue par `UniqueTagLabel` |

Pas de relation inverse vers `User`. Le nombre de porteurs se calcule par `TagRepository::countHoldersByTag()` (une requête groupée), et le détachement passe par `UserRepository::findHoldersOf()`.

### Nouvel enum `TagCategory`

`src/Enum/Type/TagCategory.php` :

| Cas | Valeur | `label()` | `pluralLabel()` | `allowsMany()` |
|---|---|---|---|---|
| `TechnicalSkill` | `competence` | Compétence technique | Compétences techniques | oui |
| `FunctionalExperience` | `experience` | Expérience fonctionnelle | Expériences fonctionnelles | oui |
| `TeamType` | `equipe` | Type d'équipe | Types d'équipe | non |

### Modification de `User`

`src/Entity/User.php` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `tags` | ManyToMany unidirectionnelle (`Tag`), `JoinTable(name: 'user_tag')`, colonnes `user_id` et `tag_id`, `OrderBy(['label' => 'ASC'])` | non (collection vide par défaut) | aucune cascade. `replaceTags(iterable<Tag>)` refuse plus d'un tag de catégorie `TeamType` (`\LogicException`). S'y ajoutent `removeTag(Tag)`, `tagsOf(TagCategory): list<Tag>` et `teamType(): ?Tag` |
| `manager` | ManyToOne (`User`), `JoinColumn(name: 'manager_id', nullable: true)` | oui | index posé par Doctrine sur la clé étrangère. Invariants (actif, pas soi-même, pas de cycle) tenus par la validation et `TeamManager`, pas par l'entité |

La migration crée `tag` et `user_tag` et ajoute `user.manager_id`. Sous SQLite, Doctrine reconstruit la table `user` par copie. Aucune reprise : les personnes existantes démarrent sans tag ni manager.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Enum/Type/TagCategory.php` | Les trois catégories de tag, leurs libellés et leur cardinalité. |
| `src/Entity/Tag.php` | Tag du référentiel : catégorie figée et libellé renommable. |
| `src/Repository/TagRepository.php` | `findAllOrdered()`, `findByCategory()`, `findLabelsExcept(category, ?id)` et `countHoldersByTag()`. |
| `migrations/VersionAAAAMMJJhhmmss.php` | Générée par `make migration` : tables `tag` et `user_tag`, colonne `user.manager_id`. |
| `src/Dto/TagInput.php` | Saisie d'un tag (catégorie, libellé et id pour le renommage), avec `#[UniqueTagLabel]`. |
| `src/Dto/TeamListFilter.php` | Filtre de la liste Équipe : un tag au plus par catégorie et un manager. |
| `src/Form/TagType.php` | Formulaire d'ajout (catégorie et libellé) et de renommage (libellé seul, option `with_category`). |
| `src/Form/TeamFilterType.php` | Formulaire GET du filtre de la liste Équipe : trois listes de tags et une liste de managers. |
| `src/Validator/UniqueTagLabel.php` | Contrainte : libellé unique dans sa catégorie, sans tenir compte de la casse. |
| `src/Validator/UniqueTagLabelValidator.php` | Compare via `TitleComparison` aux libellés de la catégorie, hors tag renommé. |
| `src/Validator/TagLabelList.php` | Contrainte sur un champ « Nouveaux… » : chaque libellé fait 60 caractères au plus. |
| `src/Validator/TagLabelListValidator.php` | Découpe via `TagManager::split()` et signale le premier libellé trop long. |
| `src/Validator/NoManagementCycle.php` | Contrainte de classe sur `TeamMemberInput` : pas soi-même, pas de cycle. |
| `src/Validator/NoManagementCycleValidator.php` | Remonte la chaîne des managers du candidat et lève une violation sur `manager` s'il retombe sur la personne éditée. |
| `src/Exception/ManagerWithActiveReportsException.php` | Refus de désactivation. Le message nomme les personnes actives rattachées. |
| `src/Service/TagManager.php` | `create()`, `rename()`, `delete()` (détache les porteurs puis supprime), `split()` et `resolve(category, string): list<Tag>`. |
| `src/Controller/TagController.php` | `/tags` (`app_tag_index`, GET et POST), `/tags/{id}/renommer` (`app_tag_edit`) et `/tags/{id}/supprimer` (`app_tag_delete`, POST avec CSRF), réservés à la direction. |
| `templates/tag/index.html.twig` | Formulaire d'ajout, puis une carte par catégorie (libellé, nombre de porteurs, Renommer, Supprimer avec une modale qui annonce le nombre). |
| `templates/tag/edit.html.twig` | Page de renommage d'un tag. |
| `src/Twig/Components/TagFilter.php` | Twig Component : fournit les tags groupés par catégorie au filtre de composition. |
| `templates/components/TagFilter.html.twig` | Les trois listes du filtre (`data-tag-filter-target="filter"`, `data-action="tag-filter#apply"`). |
| `assets/controllers/tag_filter_controller.js` | Mémorise les options de chaque select de personne, les reconstruit selon les tags choisis (placeholder, valeur déjà choisie et personnes qui portent tous les tags) et s'applique aux lignes ajoutées. |
| `tests/Support/CreatesTags.php` | Trait de test : créer un tag d'une catégorie, l'attribuer à une personne. |
| `tests/Unit/Service/TagManagerTest.php` | `split()`, `resolve()` (réutilisation insensible à la casse, création, dédoublonnage) et `delete()` (détachement). |
| `tests/Unit/Validator/NoManagementCycleValidatorTest.php` | Soi-même, cycle direct, cycle indirect, chaîne valide, pas de manager. |
| `tests/Controller/TagControllerTest.php` | Accès, ajout, doublon, renommage, suppression avec compteur et détachement. |
| `tests/e2e/tags.spec.ts` | Parcours direction : ajouter, renommer et supprimer un tag (modale de confirmation). |
| `tests/e2e/lot-team-filter.spec.ts` | Filtre de composition : options restreintes, valeur déjà choisie conservée, ligne ajoutée filtrée. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Entity/User.php` | Ajouter la ManyToMany `tags` et la ManyToOne `manager`, avec `replaceTags()`, `removeTag()`, `tagsOf()`, `teamType()`, `getManager()` et `setManager()`. |
| `src/Repository/UserRepository.php` | Ajouter `findForTeamList(TeamListFilter)` (fetch-join des tags et du manager, filtres `MEMBER OF` et `manager = :manager`), `findReportsOf(User)` et `findHoldersOf(Tag)`. Ajouter le fetch-join des tags à `findActiveForOwnerChoice()`. Ne pas toucher `findAllForTeamList()`. |
| `src/Validator/TitleComparison.php` | Extraire `public static function normalize(string): string` (`mb_strtolower(trim())`), réutilisée par `isTaken()` et `TagManager`. |
| `src/Dto/TeamMemberInput.php` | Ajouter `technicalSkills`, `functionalExperiences` (listes de `Tag`), `teamType`, les trois champs `new*` (`#[TagLabelList]`) et `manager`. Ajouter `#[NoManagementCycle]` et `isTeamTypeUnambiguous()` (`#[Assert\IsTrue]`). Alimenter ces champs dans `fromUser()`. |
| `src/Form/TeamMemberType.php` | Injecter `TagRepository` et `UserRepository`. Ajouter les cases à cocher par catégorie, les radios du type d'équipe (« Aucun » en placeholder), les trois champs texte « Nouveaux… » et un select Manager (personnes actives sauf la personne éditée, « Aucun »). |
| `src/Service/TeamManager.php` | Injecter `TagManager` (`UserRepository` l'est déjà). Dans `apply()`, résoudre les `new*`, puis appeler `replaceTags()` et `setManager()`. `deactivate()` lève `ManagerWithActiveReportsException` s'il reste des personnes actives rattachées, puis retire le manager des personnes désactivées qui le désignaient. |
| `src/Controller/TeamController.php` | Dans `index()`, construire `TeamFilterType` (GET) et appeler `findForTeamList()`. Dans `deactivate()`, attraper `ManagerWithActiveReportsException`. |
| `src/Form/LotMemberType.php` | `choice_label` devient « Prénom Nom — Type d'équipe · compétences · expériences », `choice_attr` reçoit `data-tags` (ids), et `attr` reçoit `data-tag-filter-target="person"`. |
| `src/Twig/NavigationExtension.php` | Ajouter la section `'tags' => ['app_tag_']`. |
| `templates/base.html.twig` | Ajouter l'entrée « Tags » (`tabler:tags`, `data-test="nav-tags"`) au menu Administration, réservée à `ROLE_DIRECTION`. |
| `templates/team/_form.html.twig` | Ajouter un fieldset « Profil » : un bloc par catégorie (existants, puis nouveaux) et le manager. |
| `templates/team/index.html.twig` | Ajouter la barre de filtre GET et les colonnes « Équipe », « Compétences et expériences » (badges) et « Manager ». |
| `templates/lot/_form.html.twig` | Poser `data-controller="tag-filter"` sur le fieldset de planification et `<twig:TagFilter/>` au-dessus de l'équipe. |
| `templates/account/password.html.twig` | Hors mode forcé, ajouter une carte « Mon profil » en lecture seule (tags par catégorie et manager de `app.user`). |
| `fixtures/DemoCompanyFixtures.php` | Ajouter un référentiel de démonstration (compétences, expériences, types d'équipe), des tags par personne et une hiérarchie (Hélène au sommet, les leads sous elle, la prod sous son lead). |
| `tests/Unit/Service/TeamManagerTest.php` | Câbler un vrai `TagManager` sur des stubs. Couvrir les tags et le manager posés, le refus de désactivation et le nettoyage des personnes désactivées rattachées. |
| `tests/Unit/Twig/NavigationExtensionTest.php` | Le préfixe `app_tag_` allume `tags`. |
| `tests/Controller/TeamControllerTest.php` | Attribution, création à la volée, réutilisation insensible à la casse, conflit sur le type d'équipe, refus du manager (soi-même, cycle, personne désactivée), refus de désactivation, nettoyage, affichage et filtres de la liste. |
| `tests/Controller/LotControllerTest.php` | Un lead voit les tags dans les options de l'équipe. Le responsable non lead ne voit pas la composition, donc aucun tag. |
| `tests/Controller/AccountControllerTest.php` | La carte « Mon profil » montre ses tags et son manager. |
| `tests/Controller/NavigationTest.php` | L'entrée « Tags » est visible pour la direction, absente pour un lead ou un membre de la prod. |

## Hors scope

- **Activation globale des clés étrangères SQLite** (middleware `EnableForeignKeys`) : elle change le comportement de tout le schéma. Ce serait une story technique à part, si elle est voulue.
- **Refonte de `findAllForTeamList()`** : ses usages par `ScheduleLoader` et `SecurityController` ne sont pas concernés.
- **Pagination de la liste Équipe et de l'écran Tags** : 15 à 40 personnes et quelques dizaines de tags.
- **Passage des libellés par `|trans`** : l'interface est en français uniquement, et les libellés existants sont écrits en dur. On garde la convention en place.
- **Partage du filtre entre la liste Équipe et la composition** : deux mécanismes distincts (serveur et client), assumés.

## Impacts transverses

- **Cloisonnement des données** :
  - les tags et le manager sont lus par la direction (`TeamController`, `TagController` sous `ROLE_DIRECTION`) ;
  - les leads ne voient les tags que dans les options de `LotMemberType`, rendues seulement quand `with_planning` est vrai, c'est-à-dire pour `ROLE_LEAD` ;
  - chaque personne ne voit les siens que sur la page Mon compte, via `app.user` ;
  - le manager n'est jamais rendu ailleurs.
- **Déclinaisons / thèmes** : non. Composants Paper existants, clair et sombre via les tokens.
- **Traduction / i18n** : non. Libellés en français, en dur, comme l'existant. Les catégories sont libellées par `TagCategory::label()` et `pluralLabel()`.
- **API / exposition externe** : non.
- **Droits d'accès** : `#[IsGranted('ROLE_DIRECTION')]` sur `TagController`. Le reste s'appuie sur les contrôles existants : `TeamController` réservé à la direction, `with_planning` réservé aux leads, `AccountController` réservé à l'utilisateur connecté. Pas de voter.
- **Emails / notifications** : non.
- **Migration de données** : création de structure seulement (`tag`, `user_tag`, `user.manager_id` nullable). Sans reprise. `down()` est réversible.
- **Comportement par défaut** : sans tag ni manager, la liste Équipe affiche des cellules vides et la composition des options au libellé inchangé (nom seul). La désactivation se comporte comme avant.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Service/TagManager.php` | unit | `split()` : virgules, espaces, vides et doublons à la casse près. `resolve()` : réutilise un tag existant sans tenir compte de la casse, persiste les nouveaux, n'en crée qu'un pour deux saisies équivalentes. `delete()` : retire le tag de chaque porteur avant `remove()`. |
| `src/Validator/NoManagementCycleValidator.php` | unit | Violation si le manager est la personne elle-même, ou si elle le manage directement ou indirectement. Aucune violation pour une chaîne valide ou sans manager. |
| `src/Service/TeamManager.php` | unit | `apply()` pose tags et manager. `deactivate()` lève `ManagerWithActiveReportsException` (qui nomme les personnes) s'il reste des personnes actives rattachées, sinon désactive et retire le manager des personnes désactivées rattachées. Le refus « dernier directeur » est inchangé. |
| `src/Twig/NavigationExtension.php` | unit | `app_tag_index` et `app_tag_edit` allument `tags`. |
| `src/Controller/TagController.php` | functional | Lead et prod en 403. Ajout dans chaque catégorie. Doublon refusé à la casse et aux espaces près. Même libellé accepté dans une autre catégorie. Renommage propagé et doublon refusé. La modale annonce le nombre de porteurs, et la suppression détache. |
| `src/Controller/TeamController.php` | functional | Attribution multiple, type d'équipe unique. « Nouveaux… » crée le tag, « symfony » reprend « Symfony ». Un formulaire invalide ne crée aucun tag. Type existant et nouveau ensemble : violation. Manager soi-même absent des choix. Manager en cycle : violation. Personne désactivée : choix invalide. Désactivation refusée avec les noms, puis nettoyage des personnes désactivées. La liste affiche tags et manager et se filtre (ET entre catégories, manager direct). |
| `src/Form/LotMemberType.php` | functional | Via `LotControllerTest` : options libellées avec les tags et `data-tags` pour un lead. Le responsable non lead n'a pas de composition. |
| `templates/account/password.html.twig` | functional | Via `AccountControllerTest` : la carte « Mon profil » liste ses tags et son manager, pas ceux d'un autre. Elle est absente en mode forcé. |
| `templates/base.html.twig` | functional | Via `NavigationTest` : entrée « Tags » pour la direction seule. |
| `assets/controllers/tag_filter_controller.js` | E2E | Choisir un tag restreint les options. Combiner deux catégories applique un ET. La valeur déjà choisie d'une ligne reste. Une ligne ajoutée après le filtre est filtrée. Vider le filtre restaure tout. |
| `src/Controller/TagController.php` | E2E | Parcours direction : ajouter, renommer, supprimer par la modale (`tests/e2e/tags.spec.ts`). |

**Hors scope tests** :
- Pas de test unitaire de `UniqueTagLabelValidator` ni de `TagLabelListValidator` : ils sont couverts par les tests fonctionnels de `TagController` et `TeamController`, comme `UniqueTeamEmailValidator` aujourd'hui.
- Pas d'E2E de la fiche personne ni de la liste Équipe : il n'y a pas de JS, et PHPUnit fonctionnel couvre le rendu et la soumission.
- Pas de test de la migration au-delà de `make db-reset` et du jeu de tests complet sur la base partagée.

## Ordre d'exécution

1. [ ] **Modèle et migration**
   - Objectif : `TagCategory`, `Tag`, `TagRepository`, relations `User::$tags` et `User::$manager`, migration générée et relue.
   - Fichiers : `src/Enum/Type/TagCategory.php`, `src/Entity/Tag.php`, `src/Repository/TagRepository.php`, `src/Entity/User.php`, `migrations/VersionAAAAMMJJhhmmss.php`.
   - Vérification : `symfony console doctrine:schema:validate`, `make db-reset`, puis `symfony console doctrine:migrations:migrate prev` et retour pour vérifier `down()`. `make lint` propre.
   - Commitable seule : oui.

2. [ ] **Référentiel Tags (administration)**
   - Objectif : écran `/tags` pour ajouter, renommer et supprimer avec compteur. Entrée de menu.
   - Fichiers : `src/Dto/TagInput.php`, `src/Form/TagType.php`, `src/Validator/UniqueTagLabel*.php`, `src/Validator/TitleComparison.php`, `src/Service/TagManager.php` (`create`, `rename`, `delete`, `split`), `src/Controller/TagController.php`, `templates/tag/*.html.twig`, `src/Twig/NavigationExtension.php`, `templates/base.html.twig`, `src/Repository/UserRepository.php` (`findHoldersOf`), `tests/Support/CreatesTags.php`, `tests/Unit/Service/TagManagerTest.php`, `tests/Controller/TagControllerTest.php`, `tests/Controller/NavigationTest.php`, `tests/Unit/Twig/NavigationExtensionTest.php`.
   - Vérification : `make phpunit-filter TagControllerTest`, `make phpunit-filter TagManagerTest`, `make phpunit-filter NavigationTest`.
   - Commitable seule : oui.

3. [ ] **Fiche personne : tags et création à la volée**
   - Objectif : attribuer les tags par catégorie, en créer à la volée à l'enregistrement, signaler le conflit sur le type d'équipe.
   - Fichiers : `src/Dto/TeamMemberInput.php`, `src/Form/TeamMemberType.php`, `src/Validator/TagLabelList*.php`, `src/Service/TagManager.php` (`resolve`), `src/Service/TeamManager.php` (`apply`), `templates/team/_form.html.twig`, `tests/Unit/Service/TeamManagerTest.php`, `tests/Controller/TeamControllerTest.php`.
   - Vérification : `make phpunit-filter TeamControllerTest`, `make phpunit-filter TeamManagerTest`.
   - Commitable seule : oui.

4. [ ] **Manager et refus de désactivation**
   - Objectif : choisir le manager (actif, pas soi-même, sans cycle). Refuser la désactivation d'une personne qui a des personnes actives rattachées et nettoyer les personnes désactivées rattachées.
   - Fichiers : `src/Validator/NoManagementCycle*.php`, `src/Exception/ManagerWithActiveReportsException.php`, `src/Dto/TeamMemberInput.php`, `src/Form/TeamMemberType.php`, `src/Service/TeamManager.php` (`deactivate`), `src/Repository/UserRepository.php` (`findReportsOf`), `src/Controller/TeamController.php`, `templates/team/_form.html.twig`, `tests/Unit/Validator/NoManagementCycleValidatorTest.php`, `tests/Unit/Service/TeamManagerTest.php`, `tests/Controller/TeamControllerTest.php`.
   - Vérification : `make phpunit-filter NoManagementCycleValidatorTest`, `make phpunit-filter TeamControllerTest`.
   - Commitable seule : oui.

5. [ ] **Liste Équipe : colonnes et filtre GET**
   - Objectif : afficher type d'équipe, compétences, expériences et manager, et filtrer par tag (ET entre catégories) et par manager direct, sans N+1.
   - Fichiers : `src/Dto/TeamListFilter.php`, `src/Form/TeamFilterType.php`, `src/Repository/UserRepository.php` (`findForTeamList`), `src/Controller/TeamController.php`, `templates/team/index.html.twig`, `tests/Controller/TeamControllerTest.php`.
   - Vérification : `make phpunit-filter TeamControllerTest`. Dans le profiler (via `mate`), une seule requête de chargement des personnes sur `/equipe`.
   - Commitable seule : oui.

6. [ ] **Composition d'équipe : tags et filtre client**
   - Objectif : libellés d'options avec tags, filtre Stimulus à trois listes appliqué aux lignes existantes et ajoutées.
   - Fichiers : `src/Form/LotMemberType.php`, `src/Repository/UserRepository.php` (fetch-join dans `findActiveForOwnerChoice`), `src/Twig/Components/TagFilter.php`, `templates/components/TagFilter.html.twig`, `assets/controllers/tag_filter_controller.js`, `templates/lot/_form.html.twig`, `tests/Controller/LotControllerTest.php`, `tests/e2e/lot-team-filter.spec.ts`.
   - Vérification : `make phpunit-filter LotControllerTest`, `make playwright-file tests/e2e/lot-team-filter.spec.ts` (avec `make serve` lancé).
   - Commitable seule : oui.

7. [ ] **Mon compte : mon profil**
   - Objectif : carte en lecture seule avec ses tags et son manager, hors mode forcé.
   - Fichiers : `templates/account/password.html.twig`, `tests/Controller/AccountControllerTest.php`.
   - Vérification : `make phpunit-filter AccountControllerTest`.
   - Commitable seule : oui.

8. [ ] **Fixtures de démonstration et QA finale**
   - Objectif : un référentiel et une hiérarchie de démonstration réalistes, tous les tests verts, lint propre.
   - Fichiers : `fixtures/DemoCompanyFixtures.php`, `tests/e2e/tags.spec.ts`.
   - Vérification : `make db-reset`, `make phpunit`, `make playwright`, `make lint`.
   - Commitable seule : oui.

## Critères de sortie

- [ ] `symfony console doctrine:schema:validate` est vert, et la migration monte et descend sur une base peuplée.
- [ ] La liste Équipe charge personnes, tags et managers en une requête, quel que soit le filtre (vérifié au profiler).
- [ ] Les options de composition d'équipe n'entraînent aucune requête par personne (fetch-join dans `findActiveForOwnerChoice()`).
- [ ] Supprimer un tag ne laisse aucune ligne orpheline dans `user_tag`.
- [ ] Un formulaire de personne invalide ne crée aucun tag en base.
- [ ] `make phpunit` est vert, sans nouvelle régression.
- [ ] `make playwright` est vert, `tags.spec.ts` et `lot-team-filter.spec.ts` compris.
- [ ] `make lint` est propre (PHP-CS-Fixer et PHPStan level 10).
- [ ] Sans tag ni manager, les écrans existants (Équipe, composition, désactivation) se comportent comme avant.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Filtrer sur l'alias joint en fetch tronque les tags affichés (on ne verrait que le tag filtré). | moyenne | Filtre par `:tag MEMBER OF u.tags` ou par un alias de jointure distinct. Test fonctionnel : une personne filtrée affiche tous ses tags. |
| Reconstruction de la table `user` par Doctrine sous SQLite (ajout de `manager_id`) sur une base peuplée. | moyenne | Relire la migration générée. La jouer sur la base de dev peuplée, puis `down()` et `up()` à nouveau avant de commiter. |
| N+1 sur les tags dans les options de composition (jusqu'à 40 personnes). | moyenne | Fetch-join des tags dans `findActiveForOwnerChoice()`. Les membres désactivés, peu nombreux, restent en chargement paresseux. |
| Lignes orphelines dans `user_tag`, faute de clés étrangères SQLite actives. | faible | `TagManager::delete()` détache explicitement chaque porteur avant `remove()`. Couvert en unit et en fonctionnel. |
| Le filtre client désynchronisé avec les lignes ajoutées ou la valeur déjà choisie. | moyenne | `personTargetConnected()` mémorise et filtre chaque nouvelle ligne. La valeur sélectionnée est toujours conservée. Scénario E2E dédié. |
| Libellé d'option trop long (nom et tags) dans un `<select>` natif, surtout à largeur mobile. | moyenne | Ordre fixe (type d'équipe, compétences, expériences) et séparateurs courts. Vérification visuelle à 375 px. Troncature éventuelle à trancher en implémentation. |
| Collision avec la clôture non commitée de la story 006 dans le working tree (`DemoCompanyFixtures`, tests roadmap). | élevée | Commiter la story 006 avant de lancer `/forge:feature-implem`. |
| Cycle introduit par deux éditions concurrentes de la direction. | faible | Accepté. Une ou deux personnes de direction, et la remontée de chaîne s'arrête à 40 pas au plus (garde-fou contre une boucle infinie). |

## Questions ouvertes

- **Troncature du libellé des options de composition** : options : (a) libellé complet ; (b) au plus trois compétences, puis « +N ». À trancher à l'implémentation, après vérification à largeur mobile.
- **`TagManager::split()` statique ou service** : le validateur `TagLabelListValidator` en a besoin. Options : (a) méthode statique de `TagManager` ; (b) petite classe `App\Model\TagLabels` dédiée. (a) par défaut, à revoir si la review y voit un couplage gênant.
