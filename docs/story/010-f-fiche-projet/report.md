# Report — Consulter la fiche d'un projet : son consommé face à l'estimé, sa frise complète et la timeline de tous ses tronçons

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/010-f-fiche-projet/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 90 % — les six étapes sont livrées selon l'approche prévue. Deux écarts structurants : le découpage en tronçons est sorti de `RoadmapBuilder` dans un service `RoadmapRunCutter` (limite de complexité de PHPStan), et le lien « Ouvrir la fiche » est superposé au `<summary>` au lieu d'y être imbriqué (mineur A11Y de la review).
- **Critères** : 18 / 18 cochés.
- **Review** : 0 bloquant, 0 important, 4 mineurs, tous corrigés pendant la passe ; statut PRÊT À COMMITER.
- **Périmètre livré** : 13 fichiers créés (~880 lignes, hors documents de story), 18 fichiers modifiés (+603 / −180 lignes).

La fiche d'un projet est livrée sous `/roadmap/projets/{id}`, ouverte à tous en lecture seule depuis une icône de la roadmap. Elle comprend une synthèse, le consommé face à l'estimé par lot et par projet (restant et dépassement jamais compensés), une frise bornée aux semaines du projet qui défile au-delà de 41 semaines, et la timeline de tous les tronçons. Les écarts sont des choix d'organisation du code. Ils ne changent aucun comportement prévu au pitch.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Model/Roadmap/ProjectRoadmap.php` | La fiche d'un projet : la `Roadmap` à un seul projet, sa `RoadmapRow` en propriété `project`, la timeline (`list<TimelineMonth>`) et `dated`. | Écart volontaire (cf. §Écarts) |
| `src/Model/Roadmap/TimelineEntry.php` | Une entrée de la timeline : feuille, `RoadmapRun`, `beyondEstimate` ; `leafPath()` ; fabrique `ofLeaves()` qui liste les tronçons dans l'ordre des feuilles, dans puis au-delà de l'estimation. | Écart volontaire (cf. §Écarts) |
| `src/Model/Roadmap/TimelineMonth.php` | Un mois de la timeline : libellé (« Septembre 2026 ») et entrées ; `group()` trie par dernier puis premier jour décroissants (tri stable pour l'ordre des feuilles) et range sous le mois du dernier jour. | Oui |
| `templates/roadmap/project.html.twig` | La fiche : retour à la semaine d'origine, titre, description, synthèse, tableau, frise (ou message sans date), timeline ; « pas encore découpé » pour un projet sans lot. | Oui |
| `templates/roadmap/_frieze.html.twig` | Coquille de la frise embarquée par la roadmap et la fiche : cadre qui défile, `roadmap-tooltip`, `--roadmap-weeks`, `--roadmap-zoom`, `--roadmap-min-track`, en-tête des mois, bloc `rows`. | Oui |
| `templates/roadmap/_zoom.html.twig` | Boutons « − », « + », « 100 % » et palier, extraits de `index.html.twig`. | Oui |
| `templates/roadmap/_legend.html.twig` | Légende de la frise et période affichée, extraites de `index.html.twig`. | Oui |
| `templates/roadmap/_consumption.html.twig` | Tableau du consommé face à l'estimé : lots et sous-lots dans l'ordre du découpage, ligne « Projet » en pied (`<tfoot>`). | Oui |
| `templates/roadmap/_timeline.html.twig` | Timeline verticale : intertitre par mois, entrée par tronçon (feuille, période, saisi, jours avec saisie, badge « au-delà de l'estimation », équipe par `_team.html.twig`), ou message sans saisie. | Oui |
| `tests/Unit/Model/Roadmap/TimelineMonthTest.php` | Ordre décroissant, départage par premier jour, ordre des feuilles conservé, mois du dernier jour, aucun mois sans entrée, `leafPath()`. | Oui |
| `tests/Controller/RoadmapProjectControllerTest.php` | Trois rôles en lecture seule, menu, synthèse et tableau de l'exemple, cumul séparé, feuille à estimer, frise (période, centre, semaines, largeur minimale), projet de 57 semaines, timeline, dépassement, retour à la semaine d'origine et semaine invalide, icône de la roadmap, sans date, non découpé, 404, nombre de requêtes. | Oui |
| `src/Service/RoadmapRunCutter.php` | Découpe les jours saisis de chaque feuille en tronçons, dans et au-delà de l'estimation ; la référence de découpe est l'équipe de la feuille ou, sans équipe, toutes les personnes qui y ont saisi. | Non (ajout — cf. §Écarts) |
| `templates/roadmap/_consumption_row.html.twig` | Une ligne du tableau (lot, sous-lot ou projet) : estimé, « à estimer », saisi, restant et dépassement. | Non (ajout — cf. §Écarts) |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Model/Roadmap/RoadmapWindow.php` | Bornes stockées, `dayCount()` déduit des bornes, `weekCount()`, fabrique `spanning()` (semaines entières, 4 au moins, ancrée sur la première) ; `around()`, navigation et calcul des barres inchangés. | Oui |
| `src/Model/Roadmap/Roadmap.php` | `minTrackRem()` (52rem jusqu'à 41 semaines, puis proportionnel), seuil des mois coupés déduit de `minTrackRem()` (9 % inchangé sur 41 semaines), `centerPosition()`. | Oui |
| `src/Service/RoadmapBuilder.php` | `buildProject()`, `leavesOf()`, `bounds()`, `reach()` extraite de `leafRow()`, `enteredParts()` sur des tronçons déjà découpés ; le découpage (`enteredRuns()`, `runs()`, `run()`) et `team()` sortent de la classe. | Écart volontaire (cf. §Écarts) |
| `src/Model/LotSummary.php` | `enteredQuarters`, `remainingQuarters`, `overrunQuarters`. | Oui |
| `src/Model/ProjectSummary.php` | Mêmes cumuls pour le projet. | Oui |
| `src/Service/ProjectRollup.php` | Saisi, restant et dépassement par feuille ; cumuls séparés pour les lots découpés et le projet ; feuille à estimer sans restant ni dépassement. | Oui |
| `src/Controller/RoadmapController.php` | Action `project()` : `app_roadmap_project` `GET /roadmap/projets/{id}`, `findOneForDetail`, `buildProject()`, `ProjectRollup::summarize()`, semaine de retour par `Week::tryFromIso()`. | Oui |
| `templates/roadmap/index.html.twig` | Embarque `_frieze`, `_zoom` et `_legend` ; chaque projet est enveloppé d'un `div` (`data-test="roadmap-project"`) qui porte le `<details>` et, superposé à la colonne collante, le lien-icône vers la fiche. | Écart volontaire (cf. §Écarts) |
| `templates/roadmap/_row.html.twig` | Variable `links`, vraie par défaut ; à faux, le titre d'une feuille n'est jamais un lien de modification. | Oui |
| `assets/controllers/roadmap_controller.js` | Valeur `remember` : à faux, palier ×1 ni lu ni écrit en `sessionStorage`, centrage sur `center` dès la connexion. | Oui |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | `spanning()` (semaines entières, 4 au moins, barres), `weekCount()`, `minTrackRem()`, libellé de chacun des 24 mois sur 2 ans, `centerPosition()`. | Oui |
| `tests/Unit/Service/ProjectRollupTest.php` | Saisi, restant, dépassement par feuille ; cumuls séparés (5 j de dépassement, 3 j de restant) ; feuille à estimer. | Oui |
| `tests/Service/RoadmapBuilderTest.php` | `buildProject()` : bornes, ligne identique à `build()`, timeline de l'exemple, dépassement à cheval sur deux mois, feuilles non planifiées, feuille sans équipe, projet sans date. | Oui |
| `tests/Controller/RoadmapControllerTest.php` | Non modifié : le lien-icône est vérifié dans `RoadmapProjectControllerTest`. | Écart volontaire (cf. §Écarts) |
| `tests/e2e/roadmap.spec.ts` | Deux scénarios : l'icône ouvre la fiche sans déplier le projet et la fiche ramène à la même fenêtre ; la fiche s'ouvre à ×1 sans toucher au zoom de la roadmap, infobulle d'un tronçon, timeline de la feuille interrompue. | Oui |
| `src/Model/Roadmap/RoadmapTeamLine.php` | Fabrique `forLeaf()`, l'ancienne `RoadmapBuilder::team()`. | Non (ajout — cf. §Écarts) |
| `src/Model/Week.php` | Fabrique `tryFromIso(): ?self`. | Non (ajout — cf. §Écarts) |
| `src/Controller/LotController.php` | `roadmapWeek()` retirée au profit de `Week::tryFromIso()`. | Non (ajout — cf. §Écarts) |
| `tests/Unit/Model/WeekTest.php` | `tryFromIso()` : semaines invalides et semaine existante. | Non (ajout — cf. §Écarts) |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| `enteredRuns()` extraite dans `RoadmapBuilder`, réutilisée par `build()` et `buildProject()` ; `runs()` et `team()` restent dans `RoadmapBuilder`. | Découpage dans un service `RoadmapRunCutter::cut()`, injecté dans `RoadmapBuilder` ; `team()` devient `RoadmapTeamLine::forLeaf()` ; les entrées de la timeline sont construites par `TimelineEntry::ofLeaves()`. | PHPStan refusait la complexité cognitive de `RoadmapBuilder` (61, puis 44, pour une limite de 40). |
| `ProjectRoadmap::project()`, méthode qui rend la `RoadmapRow` du projet. | Propriété `project`, passée au constructeur à côté de la `Roadmap`. | Un accès par index à `roadmap.projects[0]` n'était pas sûr pour PHPStan (niveau 10). |
| Lien-icône ajouté dans le `<summary>` de chaque projet. | Lien hors du `<details>`, superposé à la colonne collante des titres, à droite ; `data-test="roadmap-project"` porté par un enveloppant. | Review : **[A11Y] Lien interactif imbriqué dans un `<summary>`**. Un navigateur expose le `<summary>` comme un bouton. |
| Lien-icône vérifié dans `tests/Controller/RoadmapControllerTest.php`. | Vérifié dans `RoadmapProjectControllerTest::testComesBackToTheWeekOfTheRoadmapItWasOpenedFrom`, avec le retour depuis la fiche. | Le lien et le retour forment un seul parcours. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `templates/roadmap/_consumption_row.html.twig` | Une même ligne sert aux lots, aux sous-lots et au projet, dont les résumés diffèrent (`LotSummary`, `ProjectSummary`). |
| `Week::tryFromIso()`, retrait de `LotController::roadmapWeek()`, tests dans `WeekTest` | Review : **[ARCHI] Aide `roadmapWeek()` dupliquée**. |
| Référence de découpe calculée une fois par feuille dans `RoadmapRunCutter::cut()`, test `testLeafWithoutTeamIsCutOnTheWorkingDaysOfEveryoneWhoEnteredTimeOnIt` | Review : **[ROBUSTESSE] Sur une feuille sans équipe, la référence de découpe change selon la partie**. |
| Vérification de `--roadmap-min-track` et test `testFriezeOfAProjectLongerThanTheRoadmapWidensToLabelEveryMonth` | Review : **[TEST] La largeur minimale de la frise n'est vérifiée qu'en unitaire**. |
| Scénario E2E qui amène le tronçon au centre de l'écran avant de le survoler | Un défilement provoqué par le survol lui-même referme l'infobulle qu'il vient d'ouvrir (fermeture au défilement de la story 009). |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Model/Roadmap/RoadmapWindow.php` | unit | unit, 3 cas ajoutés (`spanning()`, 4 semaines, barres) | Fait |
| `src/Model/Roadmap/Roadmap.php` | unit | unit, 3 cas ajoutés (`minTrackRem()`, 24 mois sur 2 ans, `centerPosition()`) ; tests existants des mois inchangés et verts | Fait |
| `src/Model/Roadmap/TimelineMonth.php` | unit | unit, 4 cas | Fait |
| `src/Service/ProjectRollup.php` | unit | unit, 3 cas ajoutés | Fait |
| `src/Service/RoadmapBuilder.php` (`buildProject()`) | functional (noyau) | functional, 6 cas (dont la feuille sans équipe ajoutée en review) | Fait — couverture étendue |
| `src/Controller/RoadmapController.php` (`project()`) | functional | functional, 13 méthodes (15 cas avec les trois rôles) | Fait — couverture étendue (projet de 57 semaines, `--roadmap-min-track`) |
| `templates/roadmap/index.html.twig` | functional | functional, dans `RoadmapProjectControllerTest` | Fait |
| `src/Model/Week.php` (`tryFromIso()`) | non prévu | unit, 2 méthodes (5 cas) | Fait — ajout de review |
| Parcours roadmap → fiche | E2E | E2E, 2 scénarios | Fait |
| Défilement horizontal d'un projet de 2 ans | hors scope assumé | pas d'E2E ; HTML vérifié en fonctionnel (57 semaines, 14 libellés) | Conforme — la formule de largeur est celle de la roadmap |
| Non-régression visuelle de la roadmap après extraction des partiels | hors scope assumé | pas écrit ; tests fonctionnels et E2E existants de la roadmap verts | Conforme — `data-test` conservés |
| Tactile | hors scope assumé | pas écrit | Conforme — comme la story 009 |

Résultats consignés par l'implémentation et la review : 395 tests PHPUnit et 42 scénarios E2E verts, `make lint` propre.

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Sur la roadmap, chaque projet porte une icône « Ouvrir la fiche » qui mène à sa fiche ; cliquer ailleurs sur la ligne du projet le déplie ou le replie, comme avant.
- [x] Le lien « Roadmap » de la fiche ramène à la semaine de la roadmap d'où l'on venait, et l'entrée « Roadmap » du menu est mise en évidence sur la fiche.
- [x] Un membre de prod, un lead et la direction voient la même fiche ; aucune action de gestion n'y figure et les titres des feuilles ne sont pas des liens.
- [x] Dans l'exemple, le tableau affiche pour « API » 10 j estimés, 10 j saisis et 0 j de restant ; pour « Login » 8 j estimés, 3 j saisis et 5 j de restant ; pour « Front » le même cumul que « Login » ; pour le projet 18 j estimés, 13 j saisis et 5 j de restant.
- [x] Un lot dont un sous-lot dépasse son estimation de 5 j et un autre a 3 j de restant affiche 3 j de restant et 5 j de dépassement, et le projet les cumule de la même façon.
- [x] Une feuille à estimer affiche « à estimer » dans le tableau, avec son temps saisi, et l'estimé de son lot et du projet est marqué « partiel ».
- [x] La synthèse affiche l'estimé, le saisi, le restant, le dépassement s'il y en a un, le début, la fin calculée (ou « inconnue ») et les signaux du projet.
- [x] À ×1, la frise couvre tout le projet, du premier au dernier jour connu ; un projet de quelques mois tient dans la largeur, un projet plus long que la fenêtre de la roadmap défile horizontalement avec un libellé lisible pour chaque mois ; ses barres, tronçons, infobulles et récapitulatifs sont ceux de la roadmap.
- [x] Un projet dont toutes les dates tiennent dans une semaine s'affiche sur une frise de 4 semaines.
- [x] La fiche s'ouvre à ×1 même après un zoom sur la roadmap ou sur une autre fiche ; « + » fait passer la frise à ×2, ×4 puis ×8, en gardant la date au centre de l'écran.
- [x] Le repère « aujourd'hui » n'apparaît que si aujourd'hui tombe entre les bornes de la frise.
- [x] Dans l'exemple, la timeline présente, sous l'intertitre « Septembre 2026 » et dans cet ordre : « API » du 21/09 au 25/09 avec 5 j saisis et 5 jours avec saisie ; « Front · Login » du 14/09 au 16/09 avec 3 j saisis ; « API » du 07/09 au 11/09 avec 5 j saisis.
- [x] Chaque entrée de la timeline donne l'équipe du tronçon : chaque membre avec sa part et son temps saisi sur le tronçon, puis les personnes hors équipe, marquées « hors équipe ». Une personne désactivée y apparaît barrée.
- [x] Un tronçon à cheval sur août et septembre se range sous l'intertitre de septembre.
- [x] Sur une feuille en dépassement, les tronçons au-delà de l'estimation figurent dans la timeline, marqués comme tels.
- [x] Le temps saisi sur une feuille non planifiée apparaît en tronçons dans la timeline, alors que la feuille n'a pas de barre dans la frise.
- [x] Un projet sans aucune saisie affiche une timeline vide avec un message ; un projet découpé sans aucun début ni saisie n'a pas de frise, et un message l'indique.
- [x] Un projet non découpé affiche « pas encore découpé », sans tableau, frise ni timeline.

## Dette technique identifiée

Issus de la review (mineurs non traités) :

- Aucun : les quatre mineurs ont été corrigés pendant la passe.

Au-delà de la review :

- Aucune dette consignée.

## Leçons apprises

- **Une nouvelle vue sur un service existant bute vite sur la limite de complexité de PHPStan** : `RoadmapBuilder` était déjà proche de 40. Un plan qui ajoute une méthode publique à un constructeur de vue doit prévoir dès le départ où sortir le calcul réutilisé (ici le découpage en tronçons).
- **Une fenêtre en plage de jours plutôt qu'en nombre de semaines fixe** a suffi pour réutiliser toute la frise. Les barres, les tronçons, le repère « aujourd'hui » et les lignes de semaines étaient déjà en pourcentage de la fenêtre. Seuls le seuil des libellés de mois et la largeur minimale dépendaient de la taille.
- **Une infobulle fermée au défilement complique les tests de survol sur une page longue** : `hover()` fait défiler la page, et l'événement `scroll` arrive après l'ouverture. Sur toute page où la frise n'est pas en haut, amener d'abord l'élément à l'écran avant de le survoler.
- **Un `<summary>` ne peut pas contenir de lien proprement** : pour ajouter une action à une ligne pliable, la superposer hors du `<details>` dans une couche `sticky` plutôt que de l'y imbriquer.
- **Mesurer le nombre de requêtes après une requête d'échauffement** : dans un `WebTestCase`, la première requête profilée inclut les écritures de la mise en place du test, faites sur le même noyau.
