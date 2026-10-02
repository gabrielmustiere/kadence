# Review — Consulter la fiche d'une personne : sa charge, ses affectations à venir et la timeline de tout son travail saisi

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/011-f-fiche-collaborateur/`
> **Amont** : `plan.md` · `pitch.md`
> **Diff examiné** : working tree, 15 fichiers modifiés (+188 / −45) et 18 fichiers nouveaux (~1 570 lignes, tests compris)

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 0
- **Mineurs restants** : 0 / 3
- **Statut** : **PRÊT À COMMITER**

`/forge:report` pour documenter la livraison, puis `/forge:sync` et `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

_(aucun)_

## Mineurs

- [x] **[ARCHI] Légende de la frise dupliquée dans la fiche d'une personne** — `templates/person/show.html.twig:97` — cinq des sept entrées de `roadmap/_legend.html.twig` étaient recopiées. Corrigé : `_legend` prend `future_label`, `with_span` et `with_overload` (valeurs par défaut identiques au rendu de la roadmap et de la fiche projet), et la fiche personne l'inclut.
- [x] **[TEST] Assertion E2E trompeuse sur la charge d'une personne désactivée** — `tests/e2e/person.spec.ts:68` — `person-load` était absent parce que le lecteur est un membre de prod, pas parce qu'Arthur Petit est désactivé. Corrigé : assertion retirée ; les droits sur la charge et le cas désactivé sont vérifiés en fonctionnel (`PersonControllerTest`).
- [x] **[TEST] Mise en évidence de la surcharge non vérifiée** — `tests/Controller/PersonControllerTest.php:129` — le critère d'acceptation « la ligne de charge met ces jours en évidence » n'était couvert que par la valeur `data-percent`. Corrigé : assertion sur la classe `bg-danger-soft` de la plage à 200 %.

## Points positifs

- **Donnée réservée jamais calculée** : le contrôleur ne demande la charge que si `PERSON_PLANNING` est accordé, et `PersonControllerTest` vérifie rôle par rôle l'absence du rôle, des tags, de la charge, de la ligne de charge et du nom du manager dans le HTML.
- **Aucune divergence avec la roadmap** : `RoadmapSignal::of()`, `RoadmapRunCutter::split()` et `LeafPath` sont des extractions sans changement de comportement ; `RoadmapBuilderTest` et `RoadmapProjectControllerTest` passent sans modification de leurs assertions.
- **Un seul algorithme de découpe** : `cutFor()` réutilise `runs()` et `run()` en prenant la personne comme seule référence de jours ouvrés, ce qui donne des tronçons cohérents avec la roadmap sans en dupliquer la logique.
- **Coût constant** : une seule requête ajoutée (`sumQuartersByLotAndDayForUser()`, couverte par l'index `idx_time_entry_user_day`), totaux par projet déduits des tronçons, nombre de requêtes indépendant du nombre de feuilles (test dédié).
- **Exemple du pitch rejoué de bout en bout** : `PersonRoadmapBuilderTest` et `PersonControllerTest` reprennent l'exemple d'Alice (bornes du 07/09 au 01/11, 50 % au 05/10, libre le 27/10, journal sous « Septembre 2026 »).

## Hors review (à vérifier en environnement réel)

- **« Libre à partir du … » souvent inconnue** : sur les données de démonstration, une personne membre d'une ancienne feuille en dépassement voit sa date « inconnue » (ex. deux feuilles « en dépassement » pour une personne des fixtures). Voulu par le pitch (règle 8) et listé dans les risques du plan ; à observer en usage réel tant que la clôture d'une feuille (`jalons-dates-annoncees`) n'existe pas.
- **Mise en service** : information de l'équipe et, selon l'effectif, consultation du CSE (question ouverte du pitch), et réalignement de la vision par `/vision`, avant d'ouvrir la fiche à tous.
