# Review — Consulter la fiche d'un projet : son consommé face à l'estimé, sa frise complète et la timeline de tous ses tronçons

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/010-f-fiche-projet/`
> **Amont** : `plan.md` · `pitch.md`
> **Diff examiné** : working tree, 15 fichiers modifiés (+542 / −150 lignes) et 13 fichiers créés (~1 030 lignes : `ProjectRoadmap.php`, `TimelineEntry.php`, `TimelineMonth.php`, `RoadmapRunCutter.php`, 7 templates de `templates/roadmap/`, 2 classes de test), plus les documents de story ; puis les corrections des mineurs (`RoadmapRunCutter.php`, `Week.php`, `LotController.php`, `RoadmapController.php`, `index.html.twig` et leurs tests)

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 0
- **Mineurs restants** : 0 / 4
- **Statut** : **PRÊT À COMMITER**

Le diff suit le plan. Aucun impact modèle. La fiche réutilise la construction de la roadmap, donc sa frise ne peut pas diverger de la roadmap. Les 18 critères d'acceptation du pitch sont couverts par des tests. Le nombre de requêtes de la fiche est constant, et la roadmap garde son rendu (`around()` et le seuil des mois sont inchangés à 41 semaines). Les écarts avec le plan sont tous justifiés et tracés dans `metadata.json` : le service `RoadmapRunCutter`, imposé par la limite de complexité de PHPStan, et le partiel `_consumption_row.html.twig`. Les quatre mineurs ont été corrigés pendant la passe (395 tests PHPUnit, 42 scénarios E2E, lint propre). Prochaine étape : `/forge:report`, puis `/forge:sync` et `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

_(aucun)_

## Mineurs

- [x] **[ROBUSTESSE] Sur une feuille sans équipe, la référence de découpe change selon la partie** — `src/Service/RoadmapRunCutter.php:60` — `runs()` déduit « les personnes qui ont saisi » séparément pour la partie dans l'estimation et pour la partie au-delà. Or la règle 15 du pitch vise les personnes qui ont saisi sur la feuille. Un jour ouvré pour la seule personne qui n'a saisi qu'au-delà de l'estimation ne coupe donc pas la partie dans l'estimation. Corrigé : la liste est calculée une fois par feuille dans `cut()`, sur tous ses jours, et passée à `runs()` ; test `testLeafWithoutTeamIsCutOnTheWorkingDaysOfEveryoneWhoEnteredTimeOnIt` (fête nationale belge ouvrée pour la seule personne française, qui n'a saisi qu'au-delà de l'estimation).
- [x] **[A11Y] Lien interactif imbriqué dans un `<summary>`** — `templates/roadmap/index.html.twig:46` — le `<summary>` est exposé comme un bouton par les navigateurs, et un lien à l'intérieur est un contrôle imbriqué (règle axe `nested-interactive`). Le lien reste atteignable au clavier, et son `aria-label` est explicite, mais certains lecteurs d'écran l'annoncent mal. Corrigé : le lien est sorti du `<details>`, superposé à la colonne collante des titres (conteneur `sticky` dans une couche absolue). `data-test="roadmap-project"` passe sur un enveloppant ; il reste cliquable et en place à ×8, frise défilée.
- [x] **[TEST] La largeur minimale de la frise n'est vérifiée qu'en unitaire** — `tests/Controller/RoadmapProjectControllerTest.php:131` — `Roadmap::minTrackRem()` est testé, mais rien ne vérifie que le template injecte `--roadmap-min-track` dans la frise. Une régression du template ferait tasser un projet long sans qu'aucun test ne rougisse. Corrigé : `--roadmap-min-track: 52.0000rem` vérifié sur l'exemple, et `testFriezeOfAProjectLongerThanTheRoadmapWidensToLabelEveryMonth` vérifie 57 semaines, `72.2927rem` et un libellé pour chacun des 14 mois.
- [x] **[ARCHI] Aide `roadmapWeek()` dupliquée** — `src/Controller/RoadmapController.php:59` — même conversion tolérante d'une semaine ISO que `LotController::roadmapWeek()` (`src/Controller/LotController.php:139`). Corrigé : fabrique `Week::tryFromIso(): ?self`, testée dans `WeekTest` ; les deux aides privées sont retirées.

## Points positifs

- **Une seule construction pour deux frises** : `buildProject()` réutilise `projectRow()`, `leafRow()` et les tronçons de `RoadmapRunCutter`. Le test `testProjectRowOfItsPageIsTheOneOfTheRoadmapOnTheSameWindow` verrouille l'égalité avec la roadmap, ce qui protège la règle 9 du pitch.
- **`RoadmapWindow` généralisée sans régression** : les bornes stockées et la fabrique `spanning()` laissent `around()`, la navigation et le seuil des mois strictement inchangés. Le seuil se déduit maintenant de `minTrackRem()` au lieu d'un pourcentage figé, ce qui garde les libellés lisibles sur un projet long.
- **Cumuls qui ne compensent jamais** : `ProjectRollup` additionne le restant et le dépassement séparément. Le test sur 5 j de dépassement et 3 j de restant reprend mot pour mot l'exemple du pitch.
- **Tests au plus près du pitch** : l'exemple « API » / « Front · Login » sert à la fois au service, au contrôleur et à la timeline. Le test du nombre de requêtes compare deux projets de tailles différentes, après une requête d'échauffement.
- **E2E robuste au défilement** : le scénario de survol amène d'abord le tronçon à l'écran, et un commentaire explique pourquoi. Cela évite de confondre la fermeture voulue d'une infobulle au défilement (story 009) avec un défaut de la fiche.

## Hors review (à vérifier en environnement réel)

- Rendu d'un projet réel de plus de 41 semaines : défilement horizontal à ×1 et lisibilité des libellés de mois. Les fixtures n'en contiennent pas ; le HTML produit est vérifié en test fonctionnel, pas le rendu.
- Lecture de l'icône « Ouvrir la fiche » par un lecteur d'écran, maintenant qu'elle est hors du `<summary>`.
