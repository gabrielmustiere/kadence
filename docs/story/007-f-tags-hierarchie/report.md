# Report — Décrire chaque personne par ses compétences, son équipe et son manager

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/007-f-tags-hierarchie/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 90 %. Les 49 fichiers prévus sont livrés et les 8 étapes faites. Trois écarts structurent la livraison :
  - `findActiveForOwnerChoice()` est renommé `findActiveWithTags()` (finding de review) ;
  - le tri des tags se fait en PHP sans tenir compte des accents, au lieu d'un `OrderBy` Doctrine ;
  - le filtre de composition est vidé avant la mise en cache Turbo (bloquant de review).
- **Critères** : 22 / 22 critères d'acceptation cochés. Tous les critères de sortie du plan sont tenus.
- **Review** : 1 bloquant et 7 mineurs, tous corrigés pendant la passe. Statut **PRÊT À COMMITER**.
- **Périmètre livré** : 28 fichiers créés (~1 560 lignes) et 23 modifiés (+901 / −19), hors documents de story. 341 tests PHPUnit et 36 scénarios Playwright verts, d'après la review.

Le référentiel de tags, la fiche personne avec création à la volée, le manager informatif, le refus de désactivation, les filtres de la liste Équipe et de la composition, et la carte « Mon profil » sont livrés comme prévu. Restent deux vérifications non faites : le rendu à 375 px des libellés d'options, et un test du refus d'un libellé trop long saisi à la volée.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Enum/Type/TagCategory.php` | Les trois catégories de tag, leurs libellés (`label()`, `pluralLabel()`) et leur cardinalité (`allowsMany()`). | Oui |
| `src/Entity/Tag.php` | Tag du référentiel : catégorie figée, libellé renommable, et `compare()` pour l'ordre alphabétique sans accents. | Oui |
| `src/Repository/TagRepository.php` | `findAllByCategory()`, `findByCategory()`, `findLabelsExcept(category, ?id)` et `countHoldersByTag()`. | Écart volontaire (cf. §Écarts) |
| `migrations/Version20261001125003.php` | Générée : tables `tag` et `user_tag`, colonne `user.manager_id`, description renseignée. | Oui |
| `src/Dto/TagInput.php` | Saisie d'un tag (catégorie, libellé, id pour le renommage), avec `#[UniqueTagLabel]`. | Oui |
| `src/Dto/TeamListFilter.php` | Filtre de la liste Équipe : un tag au plus par catégorie et un manager. | Oui |
| `src/Form/TagType.php` | Formulaire d'ajout (catégorie et libellé) et de renommage (libellé seul, option `with_category`). | Oui |
| `src/Form/TeamFilterType.php` | Formulaire GET du filtre de la liste Équipe : trois listes de tags et une liste de managers. | Oui |
| `src/Validator/UniqueTagLabel.php` | Contrainte : libellé unique dans sa catégorie, sans tenir compte de la casse. | Oui |
| `src/Validator/UniqueTagLabelValidator.php` | Compare via `TitleComparison` aux libellés de la catégorie, hors tag renommé. | Oui |
| `src/Validator/TagLabelList.php` | Contrainte sur les champs « Nouvelles compétences » et « Nouvelles expériences » : 60 caractères au plus par libellé. | Oui |
| `src/Validator/TagLabelListValidator.php` | Découpe via `TagManager::split()` et signale le premier libellé trop long. | Oui |
| `src/Validator/NoManagementCycle.php` | Contrainte de classe sur `TeamMemberInput` : pas soi-même, pas de cycle. | Oui |
| `src/Validator/NoManagementCycleValidator.php` | Remonte la chaîne des managers du candidat jusqu'à la personne éditée ou une personne déjà vue. | Écart volontaire (cf. §Écarts) |
| `src/Exception/ManagerWithActiveReportsException.php` | Refus de désactivation, dont le message nomme les personnes actives rattachées. | Oui |
| `src/Service/TagManager.php` | `create()`, `rename()`, `delete()` (détache les porteurs puis supprime), `split()` et `resolve()`. | Oui |
| `src/Controller/TagController.php` | `/tags` (`app_tag_index`), `/tags/{id}/renommer` (`app_tag_edit`) et `/tags/{id}/supprimer` (`app_tag_delete`), réservés à la direction. | Oui |
| `templates/tag/index.html.twig` | Formulaire d'ajout, puis une carte par catégorie (libellé, nombre de porteurs, Renommer, Supprimer avec une modale qui annonce le nombre). | Oui |
| `templates/tag/edit.html.twig` | Page de renommage d'un tag. | Oui |
| `src/Twig/Components/TagFilter.php` | Twig Component : fournit les tags groupés par catégorie au filtre de composition. | Oui |
| `templates/components/TagFilter.html.twig` | Les trois listes du filtre, lues une seule fois. | Oui |
| `assets/controllers/tag_filter_controller.js` | Reconstruit les options de chaque select de personne selon les tags choisis, s'applique aux lignes ajoutées, et se vide avant la mise en cache Turbo (`reset()`). | Oui |
| `tests/Support/CreatesTags.php` | Trait de test : `createTag()` et `giveTags()`. | Oui |
| `tests/Unit/Service/TagManagerTest.php` | `split()`, `create()`, `resolve()` (réutilisation sans tenir compte de la casse, création unique, saisie vide) et `delete()`. | Oui |
| `tests/Unit/Validator/NoManagementCycleValidatorTest.php` | Soi-même, cycle direct et indirect, chaîne valide, pas de manager ou personne nouvelle, boucle déjà en base. | Oui |
| `tests/Controller/TagControllerTest.php` | Accès refusé au lead et à la prod, ajout par catégorie, doublon, renommage, suppression avec compteur et détachement, CSRF invalide. | Oui |
| `tests/e2e/tags.spec.ts` | Parcours direction (ajout, renommage, suppression par la modale) et accès refusé au lead. | Oui |
| `tests/e2e/lot-team-filter.spec.ts` | Filtre de composition (options restreintes, valeur conservée, ligne ajoutée filtrée, filtre vidé) et retour arrière. | Oui |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Entity/User.php` | ManyToMany `tags` (`user_tag`), ManyToOne `manager`, `replaceTags()`, `removeTag()`, `tagsOf()`, `teamType()`, `getManager()` et `setManager()`. Tri par `Tag::compare()` plutôt qu'un `OrderBy` Doctrine. `__serialize()` exclut les deux relations. | Écart volontaire (cf. §Écarts) |
| `src/Repository/UserRepository.php` | `findForTeamList(TeamListFilter)`, `findManagers()`, `findReportsOf()` et `findHoldersOf()` ajoutés. `findActiveForOwnerChoice()` renommé `findActiveWithTags()`, avec jointure des tags. `findAllForTeamList()` intact. | Écart volontaire (cf. §Écarts) |
| `src/Validator/TitleComparison.php` | Extraction de `normalize()`, réutilisée par `isTaken()` et `TagManager`. | Oui |
| `src/Dto/TeamMemberInput.php` | Tags par catégorie, champs « Nouveaux… », manager, `#[NoManagementCycle]` et `isTeamTypeUnambiguous()`. `newTeamType` est un libellé unique (`Assert\Length`). Alimentation dans `fromUser()`. | Écart volontaire (cf. §Écarts) |
| `src/Form/TeamMemberType.php` | Cases et radios par catégorie, trois champs texte avec `data-test` explicites, select Manager (personnes actives sauf la personne éditée). | Oui |
| `src/Service/TeamManager.php` | `apply()` résout les nouveaux libellés et pose tags et manager. `deactivate()` refuse s'il reste des personnes actives rattachées, sinon retire le manager des personnes désactivées. | Oui |
| `src/Controller/TeamController.php` | `index()` construit `TeamFilterType` et appelle `findForTeamList()`. `deactivate()` attrape `ManagerWithActiveReportsException`. | Oui |
| `src/Form/LotMemberType.php` | Libellé « Prénom Nom — Type d'équipe · compétences · expériences », `data-tags` et `data-tag-filter-target="person"`. | Oui |
| `src/Form/LotType.php` | Appel renommé en `findActiveWithTags()`. | Non (ajout — cf. §Écarts) |
| `src/Twig/NavigationExtension.php` | Section `'tags' => ['app_tag_']`. | Oui |
| `templates/base.html.twig` | Entrée « Tags » du menu Administration, réservée à la direction. | Oui |
| `templates/page/index.html.twig` | Raccourci « Tags » pour la direction. Description de « Mon compte » mise à jour. | Non (ajout — cf. §Écarts) |
| `templates/team/_form.html.twig` | Fieldset « Profil » : un bloc par catégorie (existants puis nouveaux) et le manager. | Oui |
| `templates/team/index.html.twig` | Barre de filtre GET, colonnes Équipe, Compétences et expériences, Manager, état vide filtré, nom et manager sans retour à la ligne. | Oui |
| `templates/lot/_form.html.twig` | `data-controller="tag-filter"`, `<twig:TagFilter/>` et action `turbo:before-cache@document->tag-filter#reset`. | Oui |
| `templates/account/password.html.twig` | Carte « Mon profil » en lecture seule hors mode forcé. | Oui |
| `fixtures/DemoCompanyFixtures.php` | 21 tags de démonstration répartis sur les personnes (comptes de test sans tag), hiérarchie Hélène, puis les leads, puis leurs équipes. | Oui |
| `tests/Unit/Service/TeamManagerTest.php` | `TagManager` réel sur stubs. Refus nommant les personnes, nettoyage des personnes désactivées, tags et manager posés. | Oui |
| `tests/Unit/Twig/NavigationExtensionTest.php` | `app_tag_index` et `app_tag_edit` allument `tags`. | Oui |
| `tests/Controller/TeamControllerTest.php` | Neuf cas ajoutés (tags à la volée, formulaire invalide, type d'équipe ambigu, choix et cycle du manager, désactivation, liste et filtres, nombre de requêtes). Un test existant renommé. | Oui |
| `tests/Controller/LotControllerTest.php` | Tags dans les options pour un lead, rien pour un responsable non lead, nombre de requêtes stable. | Oui |
| `tests/Controller/AccountControllerTest.php` | Carte « Mon profil » (ses tags et son manager, pas ceux d'un autre) et son absence en mode forcé. | Oui |
| `tests/Controller/NavigationTest.php` | Entrée « Tags » et raccourci `home-tags` pour la direction seule, page courante sur `/tags`. | Oui |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| `TagRepository::findAllOrdered()` | `TagRepository::findAllByCategory()`, qui renvoie les tags groupés par catégorie. | L'écran Tags et le composant `TagFilter` consomment tous deux les tags par catégorie. |
| `#[ORM\OrderBy(['label' => 'ASC'])]` sur `User::$tags` | Pas de tri Doctrine. `Tag::compare()` trie en PHP sans tenir compte des accents, dans `User::tagsOf()` et `TagRepository`. | Même ordre que les listes de projets (`ProjectRepository`, accents ignorés), alors que l'ordre SQLite place « É » après « z ». |
| Fetch-join des tags dans `UserRepository::findActiveForOwnerChoice()` | Méthode renommée `findActiveWithTags()`, ce qui modifie aussi `src/Form/LotType.php`. | Finding de review **[ARCHI] `findActiveForOwnerChoice()` sert désormais trois listes** (responsable, membres, manager). |
| Garde de `NoManagementCycleValidator` : 40 pas au plus | La remontée s'arrête sur une personne déjà vue. | Tolère une boucle déjà présente en base sans plafond arbitraire. Couvert par `testAnExistingLoopAboveTheManagerDoesNotHangTheValidation`. |
| `#[TagLabelList]` sur les trois champs « Nouveaux… » | `newTeamType` est un libellé unique, validé par `Assert\Length(max: 60)`, et non découpé aux virgules. | Une personne porte au plus un type d'équipe : découper la saisie n'aurait pas de sens. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Vérification visuelle à 375 px des libellés d'options de composition (mitigation d'un risque du plan) | Seul le rendu desktop a été regardé, à l'implémentation comme à la review. | Vérifier avant déploiement (cf. §Dette technique). |
| Couverture fonctionnelle de `TagLabelListValidator` (« couverts par les tests fonctionnels » selon le plan) | Aucun test fonctionnel ne saisit de libellé de plus de 60 caractères dans les champs « Nouveaux… ». | Ajouter le cas à `TeamControllerTest` (cf. §Dette technique). |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `User::__serialize()` exclut `tags` et `manager` | Ne pas sérialiser de collections ni de proxies Doctrine dans le jeton de session. L'utilisateur est rechargé depuis la base à chaque requête. |
| Raccourci « Tags » et description de « Mon compte » sur l'accueil (`templates/page/index.html.twig`) | L'accueil reprend en raccourcis les entrées du menu Administration, et « Mon compte » montre désormais le profil. |
| `UserRepository::findManagers()` | La liste « Manager » du filtre ne propose que les personnes qui managent quelqu'un, plutôt que tout l'effectif. |
| `reset()` du contrôleur `tag-filter` sur `turbo:before-cache`, et scénario E2E de retour arrière | Finding de review **[BUG] Le filtre de composition perd des personnes après un retour arrière** (23 options réduites à 7). |
| Tests de nombre de requêtes (`TeamControllerTest`, `LotControllerTest`) | Finding de review **[TEST] Aucun test ne fige le nombre de requêtes de la liste Équipe ni du formulaire de feuille**. |
| Cases de test au-delà de la stratégie : CSRF invalide sur la suppression, accès refusé au lead en E2E, mode forcé sans profil, boucle déjà en base | Bords constatés en écrivant les tests. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Service/TagManager.php` | unit | unit, 5 cas | Fait |
| `src/Validator/NoManagementCycleValidator.php` | unit | unit, 6 cas, dont une boucle déjà en base | Fait — couverture étendue |
| `src/Service/TeamManager.php` | unit | unit, 3 cas ajoutés | Fait |
| `src/Twig/NavigationExtension.php` | unit | unit, 2 routes ajoutées | Fait |
| `src/Controller/TagController.php` | functional | functional, 6 cas, dont un CSRF invalide | Fait — couverture étendue |
| `src/Controller/TeamController.php` | functional | functional, 9 cas ajoutés, dont le nombre de requêtes | Fait — couverture étendue |
| `src/Form/LotMemberType.php` | functional | functional, 3 cas, dont le nombre de requêtes (échoue sans la jointure des tags : 56 puis 59 requêtes) | Fait — couverture étendue |
| `templates/account/password.html.twig` | functional | functional, 2 cas | Fait |
| `templates/base.html.twig` | functional | functional (`NavigationTest`, entrée et raccourci) | Fait |
| `assets/controllers/tag_filter_controller.js` | E2E | E2E, 2 scénarios, dont le retour arrière | Fait — couverture étendue |
| `src/Controller/TagController.php` | E2E | E2E, 2 scénarios | Fait |
| `src/Validator/UniqueTagLabelValidator.php` | hors scope assumé (couvert en fonctionnel) | couvert par `TagControllerTest` | Conforme — couvert en fonctionnel, comme `UniqueTeamEmailValidator` |
| `src/Validator/TagLabelListValidator.php` | hors scope assumé (couvert en fonctionnel) | **non couvert** | Manque mineur — risque faible : un libellé trop long saisi à la volée passerait sans régression détectée |
| Fiche personne et liste Équipe | pas d'E2E (hors scope assumé) | pas écrit | Conforme — pas de JS, couverts en fonctionnel |
| Migration | `make db-reset` et suite complète | montée, descente et montée sur la base peuplée, puis `schema:validate` | Conforme |

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] La direction accède à un écran Tags depuis le menu Administration ; un lead et un membre de la prod n'y ont pas accès.
- [x] La direction crée un tag dans chacun des trois types : compétence technique, expérience fonctionnelle, type d'équipe.
- [x] Créer un tag dont le libellé existe déjà dans le même type, à la casse ou aux espaces près, est refusé avec un message explicite.
- [x] Un même libellé peut être créé dans deux types différents.
- [x] Renommer un tag met à jour son libellé sur toutes les personnes qui le portent.
- [x] Supprimer un tag porté par des personnes demande une confirmation qui annonce leur nombre, puis le retire de leurs profils.
- [x] À l'inscription comme à la modification d'une personne, la direction lui attribue plusieurs compétences techniques, plusieurs expériences fonctionnelles et au plus un type d'équipe.
- [x] Dans le formulaire d'une personne, saisir un libellé absent du référentiel crée le tag, qui apparaît ensuite dans l'écran Tags.
- [x] Dans le formulaire d'une personne, saisir un libellé existant à la casse près réutilise le tag existant, sans créer de doublon.
- [x] Une personne s'enregistre sans aucun tag ni manager.
- [x] La direction désigne le manager d'une personne parmi les personnes actives.
- [x] Désigner comme manager la personne elle-même, une personne désactivée ou une personne qu'elle manage directement ou indirectement est refusé.
- [x] La liste Équipe affiche les tags et le manager de chaque personne.
- [x] La liste Équipe se filtre par tag et par manager direct.
- [x] Désactiver une personne désignée comme manager par au moins une personne active est refusé, et le message nomme ces personnes.
- [x] Désactiver une personne dont seules des personnes désactivées dépendent retire ce lien de leurs profils.
- [x] En composant l'équipe d'une feuille, un lead voit les tags de chaque personne proposée.
- [x] En composant l'équipe d'une feuille, un lead restreint les personnes proposées à celles qui portent les tags choisis, sans que les membres déjà présents soient retirés.
- [x] Un lead ne voit nulle part le manager d'une autre personne.
- [x] Un membre de la prod ne voit nulle part les tags ni le manager d'une autre personne.
- [x] Chaque personne voit, en lecture seule sur sa page Mon compte, ses tags et son manager.
- [x] Après la mise en production, les personnes existantes apparaissent sans tag ni manager, et aucun écran existant ne change de comportement.

## Dette technique identifiée

Issus de la review (mineurs non traités) : aucun. Les 7 mineurs ont été corrigés pendant la passe.

Au-delà de la review :

1. **Rendu à 375 px non vérifié** — `src/Form/LotMemberType.php` — vérifier à largeur mobile le libellé complet des options de composition (nom et tags dans un `<select>` natif). Trancher alors la troncature « +N » laissée en option dans le plan.
2. **Refus d'un libellé trop long non testé** — `tests/Controller/TeamControllerTest.php` — ajouter un cas qui saisit dans « Nouvelles compétences techniques » un libellé de plus de 60 caractères et attend le message de `TagLabelList`.
3. **Feature absente du backlog produit** — `docs/product-backlog.md` — aucune ligne ni capacité ne couvre les tags et la hiérarchie (question ouverte du pitch). À poser dans « Équipe & accès » avec `/forge:product-backlog`.
4. **Suppression de tags hors `TagManager`** — clés étrangères SQLite non activées (hors scope du plan) — tout futur code qui supprimerait un tag sans passer par `TagManager::delete()` laisserait des lignes orphelines dans `user_tag`.
5. **Scénario E2E adossé à la démo** — `tests/e2e/lot-team-filter.spec.ts` — il s'appuie sur les tags et l'équipe de « Régularisations » dans `DemoCompanyFixtures`, et est à réajuster si la démo change.

## Leçons apprises

- **Turbo met en cache le DOM tel que le JS l'a laissé** : un contrôleur Stimulus qui réécrit des options ou masque des éléments doit restaurer l'état complet sur `turbo:before-cache`. Le retour arrière mérite un scénario E2E dès qu'un filtre vit côté client.
- **Avec Turbo, attendre l'URL avant de remplir** : après un clic sur un lien, `page.fill()` peut viser le formulaire de la page précédente encore affichée. `expect(page).toHaveURL()` avant de saisir.
- **Le premier appel d'un `WebTestCase` partage l'entity manager du test** : un comptage de requêtes pris sur ce premier appel est faussé dès que le test a créé des entités. Prendre la référence au deuxième appel.
- **Filtrer sur un alias joint en fetch tronque la collection** : avec Doctrine, un filtre sur une relation affichée passe par `MEMBER OF` ou un alias distinct, et un test vérifie qu'une ligne filtrée garde tous ses éléments.
- **Base partagée entre dev et test, démo chargée en test** : libellés uniques (`uniqid`) dans les tests, aucun comptage global, et des comptes de test laissés sans tag pour garder des scénarios prévisibles.
