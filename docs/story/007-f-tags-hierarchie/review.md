# Review — Décrire chaque personne par ses compétences, son équipe et son manager

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/007-f-tags-hierarchie/`
> **Amont** : `plan.md` · `pitch.md`
> **Diff examiné** : working tree, 24 fichiers modifiés (+852 / −26) et 31 fichiers nouveaux (~1 540 lignes), hors documents de story

## Synthèse

- **Bloquants restants** : 0 / 1
- **Importants restants** : 0 / 0
- **Mineurs restants** : 0 / 7
- **Statut** : **PRÊT À COMMITER**

Tous les findings sont corrigés pendant la passe (341 tests PHPUnit et 36 scénarios Playwright verts, lint propre). Prochaine étape : `/forge:report`, puis `/forge:sync` et `/forge:commit`.

## Bloquants

- [x] **[BUG] Le filtre de composition perd des personnes après un retour arrière** — `assets/controllers/tag_filter_controller.js:14` — Turbo met en cache la page dans son état filtré. Au retour arrière, `personTargetConnected()` mémorise la liste d'options déjà réduite, et vider le filtre ne rend plus tout le monde : reproduit sur « Régularisations », 23 options au départ, 7 après un filtre « Symfony », un aller-retour vers la roadmap et un filtre vidé. Le lead ne peut plus choisir les personnes masquées sans recharger la page. Corrigé : action `reset()` déclenchée par `turbo:before-cache@document` (`templates/lot/_form.html.twig`), qui vide les filtres et restaure toutes les options avant la mise en cache. Le scénario de retour arrière est ajouté à `tests/e2e/lot-team-filter.spec.ts` et passe.

## Importants

_(aucun)_

## Mineurs

- [x] **[PERF] `TagFilter` interroge deux fois la base** — `templates/components/TagFilter.html.twig:2` — `this.categories` est lu deux fois, et `getCategories()` relance `findAllByCategory()` à chaque lecture. Corrigé : lue une fois dans une variable Twig.
- [x] **[CONV] Migration sans description** — `migrations/Version20261001125003.php:17` — les migrations du projet décrivent ce qu'elles font dans `getDescription()`. Corrigé : description renseignée.
- [x] **[TEST] Aucun test ne fige le nombre de requêtes de la liste Équipe ni du formulaire de feuille** — `tests/Controller/TeamControllerTest.php` — les critères de sortie du plan (« une requête », « aucune requête par personne ») ont été vérifiés à la main pendant la review : 6 requêtes stables sur `/equipe` et 9 sur le formulaire de feuille, avec quatre personnes de plus. Corrigé : `TeamControllerTest::testTheListQueryCountDoesNotGrowWithThePeopleListed` et `LotControllerTest::testTheTeamChoicesQueryCountDoesNotGrowWithTheTaggedPeopleOffered`. Le second échoue bien sans la jointure des tags (56 puis 59 requêtes).
- [x] **[TEST] Le nom d'un test existant est devenu faux** — `tests/Controller/TeamControllerTest.php:70` — `testListShowsIdentityRoleAndStatusOnly` : la liste montre désormais aussi les tags et le manager. Corrigé : renommé `testListShowsIdentityRoleAndStatus`.
- [x] **[STYLE] Docblock qui paraphrase le nom** — `src/Enum/Type/TagCategory.php:32` — « Whether a person can carry several tags of this category. » répète `allowsMany()`. Corrigé : retiré.
- [x] **[STYLE] `data-test` calculé par une transformation de chaîne** — `src/Form/TeamMemberType.php:138` — `u($field)->snake()->replace('_', '-')` oblige à refaire la conversion de tête pour retrouver le sélecteur. Corrigé : `data-test` passé explicitement à `addNewTags()`.
- [x] **[ARCHI] `findActiveForOwnerChoice()` sert désormais trois listes** — `src/Repository/UserRepository.php:61` — responsable, membres de l'équipe et manager, avec une jointure des tags qui ne sert qu'aux membres. Corrigé : renommé `findActiveWithTags()`, avec un docblock qui nomme ses trois usages.

## Points positifs

- **Filtre sans troncature** : `findForTeamList()` filtre par `MEMBER OF` et garde les tags joints complets. Le test vérifie qu'une personne filtrée affiche encore ses trois tags.
- **Création à la volée transactionnelle** : `TagManager::resolve()` persiste sans `flush()`. Un formulaire invalide ne laisse aucun tag orphelin (règle 10 du pitch), et un test le prouve.
- **Suppression explicite** : sans clés étrangères SQLite actives, `TagManager::delete()` détache chaque porteur avant de supprimer le tag. La raison est écrite là où elle sert.
- **Invariant « manager actif » tenu au seul point de sortie** : `TeamManager::deactivate()` refuse d'abord, en nommant les personnes, puis nettoie les liens des personnes désactivées. Le cas est couvert en unitaire et en fonctionnel.
- **Session allégée** : `User::__serialize()` exclut les relations ajoutées, ce qui évite de sérialiser des proxies Doctrine dans le jeton.
- **Migration éprouvée** : générée, puis jouée en montée, en descente et de nouveau en montée sur la base peuplée, avant `schema:validate`.

## Hors review (à vérifier en environnement réel)

- Libellés des options de composition à largeur mobile (375 px) : seul le rendu desktop a été regardé.
- Le scénario `lot-team-filter.spec.ts` s'appuie sur les tags de la démo : il est à réajuster si `DemoCompanyFixtures` change.
