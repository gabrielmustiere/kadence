# Plan technique — Détailler chaque tronçon et chaque feuille dans les infobulles de la roadmap, et zoomer la frise pour les survoler

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/009-f-roadmap-infobulles-zoom/`
> **Amont** : `pitch.md`

## Approche retenue

Rien n'est stocké : tronçons, détail par personne et récapitulatif se calculent à la lecture, dans le modèle de vue de la roadmap. `RoadmapBuilder` lit en une requête groupée le temps saisi par feuille, jour et personne (`sumQuartersByDayAndUserForLots()`), pour les feuilles planifiées. Il découpe les tronçons comme dans la story 008 et attache à chacun un `RoadmapRun` : premier et dernier jour, temps saisi, jours avec saisie, équipe. L'équipe d'un tronçon réutilise `team()` (membres avec leur part, puis personnes hors équipe). Le jour de franchissement de l'estimation appartient en entier à la partie au-delà de l'estimation (règle 2 du pitch). Le découpage du temps de chacun entre « dans l'estimation » et « au-delà » (`beyondEstimate()`, `findQuartersInOrderForLots()`, `realizedTeam`/`overrunTeam`) n'a plus d'usage et disparaît. Le récapitulatif d'une feuille vient de champs ajoutés à `RoadmapRow` : période saisie, jours avec saisie et équipe en totaux par personne (`sumQuartersByLotAndUser()`). Ces champs sont renseignés pour toute feuille, planifiée ou non. Les jours avec saisie d'une feuille viennent d'un `COUNT(DISTINCT e.day)` ajouté à `summarizeByLot()`, porté par `LeafPlan`.

Côté page, les infobulles de la roadmap quittent Flowbite pour un contrôleur Stimulus délégué, `roadmap-tooltip`, posé une fois sur la frise. Il écoute le survol et le focus par délégation. Tout élément qui porte `data-roadmap-tooltip="<id>"` (tronçon, partie restante, barre de cumul, titre de feuille) affiche l'infobulle de cet id. Les contenus restent rendus par Twig, masqués par `hidden`. Le contrôleur n'en montre qu'une à la fois, en fermant la précédente avant d'ouvrir la suivante, et la place avec Popper, déjà présent dans l'importmap, sans sortir du cadre de la frise. Il la ferme au défilement, sur `Escape`, avant la mise en cache de la page par Turbo (`turbo:before-cache`) et à la déconnexion ; le moindre mouvement du pointeur la rouvre après un défilement. Le zoom relève du contrôleur `roadmap` existant, remonté sur un conteneur qui englobe la navigation. Il pose une variable CSS `--roadmap-zoom` dont dépend la largeur minimale de la frise. Comme barres, tronçons, repère « aujourd'hui », mois et lignes de semaines sont placés en pourcentage, ils suivent le zoom sans calcul serveur. Le palier vit en `sessionStorage`, et le contrôleur recentre par `scrollLeft`.

### Mécanismes mobilisés

- **Contrôleur Stimulus à délégation d'événements** (`pointerover`/`pointermove`/`pointerout`, `focusin`/`focusout`) : un seul écouteur pour des centaines de déclencheurs, rien à initialiser par élément ni à relancer à chaque rendu Turbo. « Une seule infobulle à la fois » est garanti par construction, sans dépendre d'une transition.
- **`@popperjs/core`** (déjà dans `importmap.php`, dépendance de Flowbite), en `strategy: 'fixed'` avec `flip` et `preventOverflow` borné au cadre de la frise : placement fiable dans la frise qui défile horizontalement et dans la colonne des titres collante, sans passer sous la barre latérale de l'application. Une seule instance vit à la fois.
- **Contenus d'infobulle rendus par Twig** : la mise en forme (jours au quart, dates, personnes désactivées barrées, « hors équipe ») reste dans les gabarits, et les tests fonctionnels lisent le HTML comme aujourd'hui.
- **Variable CSS + `sessionStorage`**, comme les projets dépliés (`roadmap_controller.js`) : le zoom est un état d'affichage propre à l'onglet, sans paramètre serveur.
- **Requêtes groupées dans `TimeEntryRepository`** : une requête par feuille, jour et personne, à la place de la requête par jour, pour un nombre constant de requêtes sur `/roadmap`.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Une infobulle Flowbite par tronçon | Des centaines d'instances recréées par `initFlowbite()` à chaque rendu Turbo, et deux infobulles visibles un instant pendant la transition de sortie, ce que le pitch exclut. |
| Données des tronçons en JSON et infobulle construite en JS | La mise en forme (jours, dates, équipe, personnes désactivées) serait dupliquée hors de Twig. |
| Garder le découpage par personne entre « dans l'estimation » et « au-delà » dans le récapitulatif | Le pitch demande le total saisi de chacun. Le dépassement se lit au niveau de la feuille, et ce découpage coûtait une requête. |
| Palier de zoom dans un cookie lu par le serveur | Le zoom deviendrait un paramètre du contrôleur PHP, partagé entre onglets. Le `sessionStorage` appliqué à la connexion du contrôleur ne produit pas de saut visible avec Turbo. |
| Zone de survol élargie autour de chaque tronçon | Écartée au cadrage au profit du zoom, qui rend un tronçon d'un jour large d'environ 23 px à ×8. |
| Largeur minimale fixe multipliée par le palier (`72rem × zoom`) | Sur un grand écran, la frise est déjà plus large que 72rem : ×2 ne doublerait pas l'affichage réel. La formule `20rem + max(52rem, 100% − 20rem) × zoom` part de la largeur réellement affichée. |

## Modèle de données

Aucun impact modèle.

Seuls des modèles de vue non persistés évoluent : `RoadmapRun` et `RoadmapSegment` sont créés, et `RoadmapBar`, `RoadmapRow` et `LeafPlan` sont modifiés (voir Périmètre).

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Model/Roadmap/RoadmapRun.php` | Un tronçon de jours saisis : premier et dernier jour, temps saisi, jours avec saisie, équipe (`list<RoadmapTeamLine>`). |
| `src/Model/Roadmap/RoadmapSegment.php` | Un tronçon placé dans sa barre : géométrie relative (`RoadmapBar`) et son `RoadmapRun`. |
| `assets/controllers/roadmap_tooltip_controller.js` | Infobulles de la frise par délégation : une seule visible, placée par Popper dans le cadre de la frise, fermée au défilement, sur `Escape`, avant la mise en cache Turbo et à la déconnexion, rouverte au mouvement du pointeur. |
| `templates/roadmap/_team.html.twig` | Liste de l'équipe d'une infobulle, avec ou sans le temps saisi, partagée par les infobulles et le récapitulatif. |
| `templates/roadmap/_recap.html.twig` | Récapitulatif d'une feuille : début effectif, fin calculée ou « inconnue », estimé, saisi, restant ou dépassement, période saisie, jours avec saisie, équipe en totaux, et ce qui manque pour une feuille non planifiée. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Repository/TimeEntryRepository.php` | `summarizeByLot()` rend aussi `COUNT(DISTINCT e.day)`. Ajouter `sumQuartersByDayAndUserForLots(list<int>)`, qui rend le temps par feuille, jour (Y-m-d, dans l'ordre) et personne. Retirer `findQuartersInOrderForLots()`. |
| `src/Model/Schedule/LeafPlan.php` | Ajouter `public int $enteredDayCount = 0`, conservé par `withPlanning()`. |
| `src/Service/ScheduleLoader.php` | `planOf()` renseigne `enteredDayCount` depuis le résumé. |
| `src/Model/Roadmap/RoadmapBar.php` | `$segments` devient `list<RoadmapSegment>`. |
| `src/Model/Roadmap/RoadmapWindow.php` | `segmentedBar()` prend `list<RoadmapRun>` et rend des `RoadmapSegment`. |
| `src/Model/Roadmap/RoadmapRow.php` | Retirer `realizedTeam`, `overrunTeam`, `realizedDayCount`, `overrunDayCount` et `realizedQuarters()`, devenue orpheline. Ajouter `enteredFrom`, `enteredTo`, `enteredDayCount`, `team` (totaux par personne) et `enteredQuarters()` pour le récapitulatif. |
| `src/Service/RoadmapBuilder.php` | `enteredParts()` lit `sumQuartersByDayAndUserForLots()` et construit des `RoadmapRun` (temps, jours, équipe par `team()`). `teams()` ne rend que les totaux par personne, et `beyondEstimate()` est retirée. `leafRow()` renseigne les champs du récapitulatif, y compris pour une feuille non planifiée. |
| `templates/roadmap/_bar.html.twig` | Sans tronçons, la barre (partie restante, cumul) porte `data-roadmap-tooltip`. Avec tronçons, le conteneur ne déclenche rien, et chaque tronçon porte `data-roadmap-tooltip` et inclut sa propre infobulle. `data-tooltip-target` disparaît. |
| `templates/roadmap/_tooltip.html.twig` | Masquage par `hidden` au lieu des classes Flowbite. Les types `realized` et `overrun` lisent le `RoadmapRun` du tronçon (période, saisi, jours avec saisie, équipe). `future` et `span` sont inchangés. |
| `templates/roadmap/_row.html.twig` | Le titre d'une feuille (lien ou `<span tabindex="0">`) porte `data-roadmap-tooltip` et `aria-describedby` vers son récapitulatif, et inclut `_recap.html.twig`. |
| `templates/roadmap/_today.html.twig` | `data-test="roadmap-today-line"` sur le repère « aujourd'hui », pour l'E2E d'ouverture d'une frise zoomée. |
| `templates/roadmap/index.html.twig` | Conteneur `data-controller="roadmap roadmap-tooltip"` englobant la navigation et la frise. Boutons « − », « + » et « 100 % » avec le palier affiché. `--roadmap-zoom` et largeur minimale `calc(20rem + max(52rem, 100% - 20rem) * var(--roadmap-zoom, 1))` à la place de `min-w-[72rem]`. Position d'aujourd'hui, ou de la semaine de référence, transmise au contrôleur. |
| `assets/controllers/roadmap_controller.js` | Ajouter le zoom : paliers 1, 2, 4 et 8, actions `zoomIn`, `zoomOut` et `zoomReset`, état des boutons, `sessionStorage`, recentrage sur la date au centre, ouverture centrée sur aujourd'hui quand le palier est au-delà de ×1. |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | Adapter les tests de `segmentedBar()` aux `RoadmapRun`. |
| `tests/Service/ScheduleLoaderTest.php` | `enteredDayCount` dans le plan. |
| `tests/Service/RoadmapBuilderTest.php` | Détail des tronçons et récapitulatif ; le test du découpage par personne dans et au-delà de l'estimation est remplacé. |
| `tests/Controller/RoadmapControllerTest.php` | Infobulles des tronçons, récapitulatif, boutons de zoom ; adapter les tests des infobulles par partie. |
| `tests/e2e/roadmap.spec.ts` | Feuille interrompue avec un tronçon d'un jour : une seule infobulle à la fois, récapitulatif au survol et au clavier, fermeture avant la mise en cache Turbo, zoom. |

## Hors scope

- **Flowbite ailleurs que sur la roadmap** : `initFlowbite()` reste en place pour le reste de l'application.
- **Comportement dédié au tactile** : on s'appuie sur l'émulation du survol par le navigateur (question ouverte du pitch tranchée), sans code ni test spécifiques.
- **Seuil des libellés de mois selon le zoom** : `Roadmap::MIN_MONTH_WIDTH` reste calculé pour ×1 (question ouverte du pitch tranchée).
- **Raccourcis clavier de zoom, zoom à la molette ou au pincement** : hors scope du pitch.
- **Données de démonstration** : les fixtures produisent déjà des tronçons et des personnes hors équipe ; aucune donnée ajoutée.

## Impacts transverses

- **Cloisonnement des données** : aucun filtrage multi-organisation. Côté principe 2, le temps par personne et par tronçon est rendu pour tous les rôles, conformément au pitch (tension assumée). `$withOverloads` reste le seul contenu filtré par rôle.
- **Déclinaisons / thèmes** : non. Infobulles et boutons réutilisent les tokens Paper et le composant `Button`.
- **Traduction / i18n** : libellés en français en dur dans les gabarits, comme le reste de la roadmap (récapitulatif, `aria-label` des boutons de zoom).
- **API / exposition externe** : non.
- **Droits d'accès** : inchangés. `/roadmap` reste ouverte à `ROLE_USER`.
- **Emails / notifications** : non.
- **Migration de données** : aucune.
- **Comportement par défaut** : sans zoom mémorisé, la frise s'ouvre à ×1 avec la largeur d'aujourd'hui (`max(52rem, 100% − 20rem)` égale la largeur actuelle de la frise).

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/Roadmap/RoadmapWindow.php` (`segmentedBar`) | unit | Tronçons placés en pourcentage de leur barre, avec leur `RoadmapRun` intact. Tronçon hors fenêtre ignoré, bord coupé, fenêtre entre deux tronçons rendant `null`. |
| `src/Service/ScheduleLoader.php` | functional (base) | `enteredDayCount` égale le nombre de jours distincts saisis, plusieurs personnes le même jour comptant pour un. |
| `src/Service/RoadmapBuilder.php` | functional (base) | Chaque tronçon a sa période, son temps, ses jours et son équipe : membres avec part et temps, puis personne hors équipe. Le jour de franchissement est entier dans le tronçon au-delà de l'estimation, et la somme des tronçons égale le total saisi. Le récapitulatif d'une feuille planifiée (période saisie, jours, totaux par personne) et d'une feuille sans début avec saisies est renseigné. |
| `src/Controller/RoadmapController.php` (page `/roadmap`) | functional | Deux tronçons, chacun avec son infobulle (période, « 5 j », équipe). Récapitulatif rendu sur le titre (« 10 j saisis », période, jours). Récapitulatif « sans début » d'une feuille non planifiée. Infobulle de la partie restante inchangée. Boutons de zoom présents. Plus aucun `data-tooltip-target` dans la frise. Nombre de requêtes constant. |
| `tests/e2e/roadmap.spec.ts` | E2E | Survol du premier, puis du dernier tronçon, puis du titre : une seule infobulle visible à chaque fois, avec le bon contenu. Survol d'un trou : aucune. Focus placé sur le titre : récapitulatif. Infobulle ouverte fermée à l'événement `turbo:before-cache`. « − » désactivé à ×1, « + » jusqu'à ×8 puis désactivé, tronçon d'un jour d'au moins 20 px, « 100 % ». Palier conservé après « 4 semaines » et retour. Frise zoomée rouverte avec le repère « aujourd'hui » visible. |

**Hors scope tests** :

- Pas de test de placement au pixel des infobulles : Popper est une bibliothèque éprouvée, et le placement est vérifié à la recette visuelle.
- Pas de test du recentrage au pixel près : l'E2E vérifie que le repère « aujourd'hui » reste visible ; l'exactitude de la date au centre est vérifiée à la recette.
- Pas de test sur écran tactile (question ouverte du pitch tranchée).
- `Scheduler` et `PlanningFitsCapacityValidator` ne sont pas modifiés : leurs tests existants servent de filet (`LeafPlan` gagne un champ par défaut).

## Ordre d'exécution

1. [x] **Données des tronçons et du récapitulatif**
   - Objectif : jours distincts dans le résumé, temps par feuille, jour et personne, `LeafPlan::enteredDayCount`.
   - Fichiers : `src/Repository/TimeEntryRepository.php`, `src/Model/Schedule/LeafPlan.php`, `src/Service/ScheduleLoader.php`, `tests/Service/ScheduleLoaderTest.php`.
   - Vérification : `make phpunit-filter "ScheduleLoaderTest|SchedulerTest"`.
   - Commitable seule : oui (`findQuartersInOrderForLots()` n'est retirée qu'à l'étape 2).

2. [x] **Modèle de vue des tronçons et du récapitulatif**
   - Objectif : `RoadmapRun`, `RoadmapSegment`, `segmentedBar()` sur des runs, `RoadmapRow` réorganisé, `RoadmapBuilder` (détail des tronçons, totaux, champs du récapitulatif). Retrait de `beyondEstimate()` et de `findQuartersInOrderForLots()`.
   - Fichiers : `src/Model/Roadmap/RoadmapRun.php`, `src/Model/Roadmap/RoadmapSegment.php`, `src/Model/Roadmap/RoadmapBar.php`, `src/Model/Roadmap/RoadmapWindow.php`, `src/Model/Roadmap/RoadmapRow.php`, `src/Service/RoadmapBuilder.php`, `src/Repository/TimeEntryRepository.php`, `tests/Unit/Model/Roadmap/RoadmapWindowTest.php`, `tests/Service/RoadmapBuilderTest.php`.
   - Vérification : `make phpunit-filter "RoadmapWindowTest|RoadmapBuilderTest"` ; PHPStan.
   - Commitable seule : non (les gabarits lisent encore `realizedTeam` ; ils suivent à l'étape 3).

3. [x] **Infobulles par délégation et récapitulatif**
   - Objectif : contrôleur `roadmap-tooltip`, déclencheurs `data-roadmap-tooltip`, infobulles des tronçons, récapitulatif sur le titre, fin de Flowbite sur la roadmap.
   - Fichiers : `assets/controllers/roadmap_tooltip_controller.js`, `templates/roadmap/_bar.html.twig`, `templates/roadmap/_tooltip.html.twig`, `templates/roadmap/_recap.html.twig`, `templates/roadmap/_row.html.twig`, `templates/roadmap/index.html.twig`, `tests/Controller/RoadmapControllerTest.php`.
   - Vérification : `make phpunit-filter RoadmapControllerTest` ; `make playwright-file tests/e2e/roadmap.spec.ts` pour les scénarios existants (survol de la partie restante).
   - Commitable seule : oui.

4. [x] **Zoom de la frise**
   - Objectif : boutons, paliers, variable CSS, largeur minimale, mémorisation, recentrage, ouverture centrée.
   - Fichiers : `assets/controllers/roadmap_controller.js`, `templates/roadmap/index.html.twig`, `tests/Controller/RoadmapControllerTest.php`.
   - Vérification : `make phpunit-filter RoadmapControllerTest` ; contrôle manuel dans le navigateur à ×1, ×2, ×4 et ×8.
   - Commitable seule : oui.

5. [x] **Scénarios E2E**
   - Objectif : prouver « une seule infobulle à la fois », le récapitulatif au clavier et le zoom dans le navigateur.
   - Fichiers : `tests/e2e/roadmap.spec.ts`. La feuille interrompue gagne un tronçon d'un jour ; les attentes de la story 008 sont adaptées aux infobulles par tronçon.
   - Vérification : `make serve` lancé, puis `make playwright-file tests/e2e/roadmap.spec.ts`.
   - Commitable seule : oui.

6. [x] **QA complète et recette visuelle**
   - Objectif : suite verte, lint propre, rendu et poids de la page vérifiés sur les données de démonstration.
   - Fichiers : aucun nouveau, sauf correctifs issus de la QA.
   - Vérification : `make lint`, `make phpunit`, `make playwright`. Sur `/roadmap`, survol de tronçons, récapitulatif, zoom aux quatre paliers, poids du HTML comparé à avant.
   - Commitable seule : oui.

## Critères de sortie

- [x] Plus aucun `data-tooltip-target` dans la frise : toutes ses infobulles passent par `roadmap-tooltip`, et une seule est visible à la fois.
- [x] `beyondEstimate()`, `findQuartersInOrderForLots()`, `realizedTeam` et `overrunTeam` ont disparu, sans autre usage restant.
- [x] Le nombre de requêtes de `/roadmap` reste indépendant du nombre de feuilles (`testQueryCountDoesNotGrowWithTheNumberOfLeaves` vert).
- [x] `Scheduler`, `PlanningFitsCapacityValidator` et leurs tests ne sont pas modifiés.
- [x] `make phpunit` vert, sans nouvelle régression.
- [x] `make playwright` vert, serveur lancé.
- [x] `make lint` propre (PHP-CS-Fixer en dry-run, PHPStan level 10).
- [x] Recette visuelle sur les données de démonstration faite : infobulles de tronçon et récapitulatif placés correctement aux quatre paliers, recentrage, poids du HTML mesuré.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Poids du HTML : une infobulle par tronçon, avec son équipe, ajoute environ 50 à 120 Ko sur une fenêtre dense | moyenne | Mesure à la recette sur la fenêtre de juillet des données de démonstration (75 tronçons). Si le poids gêne, rendre l'équipe des tronçons à la demande (Turbo Frame) dans une story suivante. |
| Requête par feuille, jour et personne plus volumineuse que la requête par jour | moyenne à terme | Limitée aux feuilles planifiées ; même piste de repli que la story 008 (borner à la fenêtre) si l'historique dépasse un an. |
| Infobulle mal placée ou coupée dans la frise qui défile ou la colonne collante | faible | Popper en `strategy: 'fixed'` avec `flip` et `preventOverflow` borné au cadre de la frise, fermeture au défilement et réouverture au mouvement du pointeur. Recette visuelle aux quatre paliers. |
| Infobulle restée ouverte dans l'aperçu Turbo en cache | faible | Fermeture sur `turbo:before-cache` : Turbo copie la page un tour de boucle après l'événement, `disconnect()` seul pourrait arriver trop tard. Scénario E2E déterministe. |
| Recentrage imprécis au zoom (colonne collante de 20rem à exclure du calcul) | moyenne | Calcul sur les largeurs mesurées de la frise ; E2E sur la visibilité du repère « aujourd'hui » ; recette sur la date au centre. |
| Un arrêt de tabulation par feuille (récapitulatif au clavier) | faible | Assumé par le pitch ; les titres-liens des leads étaient déjà focalisables. |
| Régression des infobulles existantes (partie restante, barres de cumul) en quittant Flowbite | faible | Scénario E2E existant de survol de la partie restante conservé ; tests fonctionnels de leurs contenus inchangés. |

## Questions ouvertes

_(aucune)_
