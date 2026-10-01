# Plan technique — Couper la partie réalisée d'une feuille sur les jours sans saisie pour montrer ses interruptions sur la roadmap

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/008-f-roadmap-jours-sans-saisie/`
> **Amont** : `pitch.md`

## Approche retenue

Aucune donnée n'est stockée : les tronçons se calculent à la lecture, comme toute la chronologie depuis la story 006. Le moteur `Scheduler` et le validateur de planification ne changent pas. Le travail se concentre sur le modèle de vue de la roadmap. `RoadmapBuilder` charge en une requête groupée les jours saisis de chaque feuille planifiée qui a des saisies (celles dont `ScheduleResult::isPlanned()` est vrai), via `TimeEntryRepository::sumQuartersByDayForLots()`, déjà utilisée pour les dépassements. Pour chaque feuille, il sépare ces jours en deux groupes : jusqu'au dernier jour dans l'estimation (partie bleue), puis à partir du jour de dépassement (partie rouge), en s'appuyant sur `ScheduleData::overrunDays`. Chaque groupe est ensuite découpé en tronçons. Un nouveau tronçon commence dès qu'un jour ouvré pour au moins un membre de l'équipe actuelle sépare deux jours saisis consécutifs. La question « ce jour était-il ouvré ? » est posée à `DailyCapacity`, qui connaît déjà le calendrier de chacun, désactivés compris. Seul changement côté chargement : `ScheduleLoader` étend vers le passé la plage des jours fériés de la capacité, jusqu'au plus ancien premier jour saisi.

La géométrie reste dans `RoadmapWindow`. `segmentedBar()` rend la barre de la partie entière, du premier au dernier jour saisi, et y attache ses tronçons, chacun étant une `RoadmapBar` placée en pourcentage de la barre qui la contient. Au rendu, la barre d'une partie devient un conteneur transparent qui porte le déclencheur d'infobulle, le fondu de bord et le `data-test` actuel, et les tronçons sont ses enfants. Une partie n'a ainsi qu'un seul déclencheur et une seule infobulle. C'est imposé par Flowbite : chaque `data-tooltip-target` crée une instance identifiée par l'id de l'infobulle, avec `override: true`, si bien que plusieurs déclencheurs visant la même infobulle s'annuleraient et que seul le dernier fonctionnerait. L'infobulle gagne la ligne « Jours avec saisie », et la légende de la page gagne une entrée « Jour ouvré sans saisie ».

### Mécanismes mobilisés

- **Requête groupée existante `sumQuartersByDayForLots()`** : elle rend déjà les jours saisis par feuille, dans l'ordre des dates. On l'appelle une fois pour toutes les feuilles planifiées qui ont des saisies, ce qui ajoute une requête constante à `/roadmap`, sans requête dans une boucle et sans nouvelle méthode de dépôt.
- **`DailyCapacity::isWorkingDay()`** : elle encode déjà « du lundi au vendredi, hors jours fériés du calendrier de la personne », ajustements compris, sans regarder si la personne est active. La nouvelle méthode `hasWorkingDayBetween()` la réutilise pour un groupe de personnes.
- **Modèle de vue calculé en PHP, positions en `style` inline** : c'est le pattern de la frise depuis la story 006 (`RoadmapWindow::bar()`, `RoadmapBar::style()`). Les tronçons suivent le même chemin, sans classe Tailwind arbitraire générée à l'exécution.
- **Infobulle Flowbite à déclencheur unique** : elle reste en place, avec un conteneur comme déclencheur. Le survol d'un tronçon (descendant du conteneur) déclenche `mouseenter` sur le conteneur.
- **`ClockSensitiveTrait`** dans les tests contre la base, comme les tests existants de la roadmap.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Un seul élément dont le fond est un dégradé CSS à arrêts nets | Coins intérieurs carrés, et tronçons impossibles à compter dans le DOM : les tests passeraient par un attribut au lieu d'éléments observables. |
| Une infobulle par tronçon | Un tronçon d'un jour fait environ 3 px, ce qui le rend difficile à survoler ; le pitch retient l'infobulle commune. |
| Plusieurs déclencheurs Flowbite visant la même infobulle | Chaque instance détruit la précédente (`override: true`) : seul le dernier tronçon afficherait l'infobulle. |
| Contrôleur Stimulus d'infobulle maison | Du nouveau code JS à maintenir et à tester, pour limiter le survol aux seuls tronçons, un bénéfice mince. |
| Charger les jours saisis dans `ScheduleLoader` | Le validateur de planification, qui appelle aussi `load()`, paierait une requête et un volume de données dont il n'a pas l'usage. |
| Calculer les jours de dépassement dans le builder à partir des mêmes données, et retirer la requête conditionnelle du chargeur | Plus économe d'une lecture, mais refactore du code livré par la story 006 (chargeur, `ScheduleData`, `ScheduleLoaderTest`) hors du besoin. |
| Borner la requête jour par jour à la fenêtre affichée | Il faudrait alors savoir si le premier tronçon visible continue avant la fenêtre (dernier jour saisi avant, jours ouvrés entre les deux), donc une requête de plus et une logique de bord fragile. On l'écarte tant que le volume ne le justifie pas (voir Risques). |

## Modèle de données

Aucun impact modèle.

Seuls des modèles de vue non persistés évoluent : `RoadmapBar` gagne ses tronçons, `RoadmapRow` gagne deux compteurs (voir Périmètre).

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| _(aucun)_ | Tous les changements se font dans des fichiers existants. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Model/Schedule/DailyCapacity.php` | Ajouter `hasWorkingDayBetween(list<int> $userIds, \DateTimeImmutable $after, \DateTimeImmutable $before): bool`, qui dit si un jour strictement compris entre les deux dates est ouvré (`isWorkingDay()`) pour au moins une des personnes. |
| `src/Service/ScheduleLoader.php` | Dans `capacity()`, faire partir la plage des jours fériés du plus ancien `LeafPlan::firstEntryDay` quand il précède le lundi de la semaine en cours. |
| `src/Model/Roadmap/RoadmapBar.php` | Ajouter `public array $segments = []` (`list<RoadmapBar>`, placés en pourcentage de la barre, avec leurs propres `cutStart`/`cutEnd`) ; une barre sans tronçon reste rendue comme aujourd'hui. |
| `src/Model/Roadmap/RoadmapWindow.php` | Ajouter `segmentedBar(list<array{\DateTimeImmutable, \DateTimeImmutable}> $runs): ?RoadmapBar`, qui rend la barre du premier au dernier jour des tronçons, avec ses `segments` relatifs ; un tronçon hors de la fenêtre est ignoré, et le résultat est `null` sans tronçon visible. |
| `src/Model/Roadmap/RoadmapRow.php` | Ajouter `realizedDayCount` et `overrunDayCount` (`int`, 0 par défaut) : les jours avec saisie de chaque partie. |
| `src/Service/RoadmapBuilder.php` | Ajouter `enteredParts()`, calculé une fois dans `build()` comme `teams()` : il charge les jours saisis, les sépare selon `overrunDays`, les découpe en tronçons (`runs()`, privée statique, sur les membres de `LeafPlan::$members` et `DailyCapacity::hasWorkingDayBetween()`) et rend par feuille les deux barres segmentées et leurs compteurs. Ce résultat remplace le paramètre `$overrunDays` de `projectRow()`, `splitLotRow()` et `leafRow()`, et la méthode `realizedBars()`. `enteredParts()` reçoit aussi le `ScheduleResult` pour ne traiter que les feuilles planifiées. |
| `templates/roadmap/_bar.html.twig` | Quand `bar.segments` n'est pas vide, rendre un conteneur transparent (positionnement, déclencheur, fondu de bord, `data-test` existant) et un enfant par tronçon, portant les classes visuelles, `data-test="roadmap-segment-{kind}"` et des coins arrondis sauf sur un bord coupé. |
| `templates/roadmap/_tooltip.html.twig` | Ajouter, pour les parties `realized` et `overrun`, la ligne « Jours avec saisie » (`data-test="roadmap-days-entered"`). |
| `templates/roadmap/index.html.twig` | Ajouter à la légende l'entrée « Jour ouvré sans saisie » (deux blocs `bg-brand` séparés par un vide, `data-test="roadmap-legend-gap"`). |
| `tests/Unit/Model/Schedule/DailyCapacityTest.php` | Cas de `hasWorkingDayBetween()`. |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | Cas de `segmentedBar()`. |
| `tests/Service/ScheduleLoaderTest.php` | Jour férié antérieur à la semaine en cours connu quand une saisie le précède. |
| `tests/Service/RoadmapBuilderTest.php` | Scénarios de tronçons du pitch. |
| `tests/Controller/RoadmapControllerTest.php` | Tronçons dans le DOM, compteur de l'infobulle, légende. |
| `tests/e2e/roadmap.spec.ts` | Feuille interrompue : même infobulle au survol du premier et du dernier tronçon. |

## Hors scope

- **Dédoublonner la lecture des jours de dépassement** : la requête conditionnelle de `ScheduleLoader` est conservée (voir Alternatives écartées).
- **Survol limité aux seuls tronçons** : survoler un trou situé à l'intérieur d'une partie affiche aussi son infobulle, ce qui est assumé.
- **Données de démonstration** : `DemoCompanyFixtures` produit déjà des jours sans saisie (semaines de congés tirées au hasard, jours oubliés, choix aléatoire de la feuille du jour). Aucune feuille dédiée n'est ajoutée.
- **Largeur minimale d'un tronçon ou d'un trou** : non, largeur réelle (environ 2,9 px par jour à la largeur minimale de la frise).
- **Barres de cumul et partie future** : non touchées (`spanRow()`, `future`).

## Impacts transverses

- **Cloisonnement des données** : aucun filtrage multi-organisation. Côté principe 2, le modèle de vue ne transporte que des jours agrégés par feuille, jamais l'auteur d'une saisie. `teams()` n'est pas modifiée.
- **Déclinaisons / thèmes** : non. Les tronçons réutilisent les tokens existants (`bg-brand`, `bg-danger`).
- **Traduction / i18n** : libellés en français en dur dans les gabarits, comme le reste de la roadmap (« Jours avec saisie », « Jour ouvré sans saisie »).
- **API / exposition externe** : non.
- **Droits d'accès** : inchangés. `/roadmap` reste ouverte à `ROLE_USER`, et les tronçons ne dépendent pas de `$withOverloads`.
- **Emails / notifications** : non.
- **Migration de données** : aucune.
- **Comportement par défaut** : toutes les barres de feuille sont segmentées, sans option à activer.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/Schedule/DailyCapacity.php` (`hasWorkingDayBetween`) | unit | Deux jours contigus : faux. Week-end seul entre vendredi et lundi : faux. Jour férié commun à toutes les personnes : faux. Jour férié d'un seul calendrier avec une personne de l'autre calendrier : vrai. Personne désactivée au calendrier ouvré : vrai. Jour ordinaire entre deux saisies : vrai. |
| `src/Model/Roadmap/RoadmapWindow.php` (`segmentedBar`) | unit | Deux tronçons : barre du premier au dernier jour, segments à 0 % et en fin, largeurs relatives exactes. Un tronçon hors de la fenêtre est ignoré. Un tronçon à cheval sur le bord gauche est `cutStart`, et la barre aussi. Liste vide ou entièrement hors fenêtre : `null`. |
| `src/Service/ScheduleLoader.php` | functional (base) | Avec une saisie en juillet et l'horloge en octobre, `isWorkingDay()` est faux le 14/07 pour un calendrier France et vrai pour un calendrier Belgique. |
| `src/Service/RoadmapBuilder.php` | functional (base) | Sur des dates passées, avec une fenêtre ancrée sur la période des saisies : semaine sans saisie, d'où deux segments et `realizedDayCount` de 10 ; mercredi sans saisie, d'où une coupure ; vendredi puis lundi, d'où un segment ; Ascension, le 14/05 (férié France et Belgique ; le 1er mai 2026 tombe un vendredi, collé au week-end), sans coupure ; 21/07 avec un membre France, coupure, et équipe toute Belgique, aucune ; 14/07 avec une équipe France, aucune ; membre France désactivé avec un membre Belgique, coupure le 21/07 ; feuille en dépassement avec ses segments rouges et `overrunDayCount`. |
| `src/Controller/RoadmapController.php` (page `/roadmap`) | functional | La feuille interrompue a un seul `roadmap-bar-realized` contenant deux `roadmap-segment-realized`, et `roadmap-days-entered` vaut le bon nombre. Sur une feuille en dépassement, `roadmap-days-entered` est vérifié dans les infobulles `realized` et `overrun`. La légende contient `roadmap-legend-gap`. Les tests existants (une barre réalisée, période de l'infobulle, nombre de requêtes constant) restent verts. |
| `tests/e2e/roadmap.spec.ts` | E2E | Feuille insérée en SQL, saisies sur deux semaines passées séparées d'une semaine : deux tronçons visibles ; le survol du premier puis du dernier tronçon affiche la même infobulle avec le nombre de jours avec saisie. |

**Hors scope tests** :

- Pas de test de rendu au pixel des tronçons d'un jour : la géométrie est couverte par `RoadmapWindowTest`, et le rendu est vérifié à la recette visuelle.
- Pas de test de performance automatisé de la requête jour par jour : le nombre de requêtes constant est vérifié, et le volume est mesuré à la recette sur les données de démonstration.
- `Scheduler` et `PlanningFitsCapacityValidator` ne sont pas modifiés : leurs tests existants servent de filet.

## Ordre d'exécution

1. [x] **Jour ouvré entre deux dates pour un groupe**
   - Objectif : `DailyCapacity::hasWorkingDayBetween()` disponible et couverte.
   - Fichiers : `src/Model/Schedule/DailyCapacity.php`, `tests/Unit/Model/Schedule/DailyCapacityTest.php`.
   - Vérification : `make phpunit-filter DailyCapacityTest`.
   - Commitable seule : oui.

2. [x] **Jours fériés du passé dans la capacité**
   - Objectif : la capacité connaît les jours fériés depuis le plus ancien premier jour saisi.
   - Fichiers : `src/Service/ScheduleLoader.php`, `tests/Service/ScheduleLoaderTest.php`.
   - Vérification : `make phpunit-filter ScheduleLoaderTest` ; `make phpunit-filter PlanningFitsCapacity` reste vert.
   - Commitable seule : oui.

3. [x] **Barre segmentée sur la fenêtre**
   - Objectif : `RoadmapWindow::segmentedBar()` et `RoadmapBar::$segments`.
   - Fichiers : `src/Model/Roadmap/RoadmapBar.php`, `src/Model/Roadmap/RoadmapWindow.php`, `tests/Unit/Model/Roadmap/RoadmapWindowTest.php`.
   - Vérification : `make phpunit-filter RoadmapWindowTest`.
   - Commitable seule : oui.

4. [x] **Tronçons dans le modèle de vue**
   - Objectif : `RoadmapBuilder::enteredParts()` et `runs()` ; `RoadmapRow` porte les barres segmentées et les compteurs ; `realizedBars()` et le paramètre `$overrunDays` sont retirés.
   - Fichiers : `src/Service/RoadmapBuilder.php`, `src/Model/Roadmap/RoadmapRow.php`, `tests/Service/RoadmapBuilderTest.php`.
   - Vérification : `make phpunit-filter RoadmapBuilderTest` ; `make phpunit-filter RoadmapControllerTest` reste vert, nombre de requêtes compris.
   - Commitable seule : oui (le gabarit actuel rend encore la barre entière).

5. [x] **Rendu des tronçons, infobulle et légende**
   - Objectif : conteneur déclencheur et tronçons enfants, ligne « Jours avec saisie », entrée de légende.
   - Fichiers : `templates/roadmap/_bar.html.twig`, `templates/roadmap/_tooltip.html.twig`, `templates/roadmap/index.html.twig`, `tests/Controller/RoadmapControllerTest.php`.
   - Vérification : `make phpunit-filter RoadmapControllerTest`.
   - Commitable seule : oui.

6. [x] **Scénario E2E de la feuille interrompue**
   - Objectif : prouver dans le navigateur que l'infobulle commune s'affiche depuis n'importe quel tronçon.
   - Fichiers : `tests/e2e/roadmap.spec.ts`. Insertion SQL d'une feuille planifiée et de ses saisies, purge des `time_entry` avant celle des lots. Prendre une personne sans usage dans les autres specs (par exemple `ancien@example.com`) pour ne pas perturber les feuilles de temps testées ailleurs.
   - Vérification : `make serve` lancé, puis `make playwright-file tests/e2e/roadmap.spec.ts`.
   - Commitable seule : oui.

7. [x] **QA complète et recette visuelle**
   - Objectif : suite verte, lint propre, rendu vérifié sur les données de démonstration.
   - Fichiers : aucun nouveau, sauf correctifs issus de la QA.
   - Vérification : `make lint`, `make phpunit`, `make playwright` ; `make db-reset` puis `/roadmap` : tronçons, infobulle depuis plusieurs tronçons, légende, temps de réponse comparable à avant.
   - Commitable seule : oui.

## Critères de sortie

- [x] La partie réalisée et la partie au-delà de l'estimation d'une feuille sont rendues en tronçons enfants d'un seul conteneur déclencheur ; les barres future et de cumul sont rendues comme avant.
- [x] Le nombre de requêtes de `/roadmap` reste indépendant du nombre de feuilles (`testQueryCountDoesNotGrowWithTheNumberOfLeaves` vert) ; aucune requête dans une boucle.
- [x] `Scheduler`, `PlanningFitsCapacityValidator` et leurs tests ne sont pas modifiés.
- [x] `make phpunit` vert, sans nouvelle régression.
- [x] `make playwright` vert, serveur lancé.
- [x] `make lint` propre (PHP-CS-Fixer en dry-run, PHPStan level 10).
- [x] Recette visuelle sur les données de démonstration faite : tronçons lisibles, infobulle commune au survol de plusieurs tronçons, entrée de légende.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Volume de la requête jour par jour, qui porte sur tout l'historique (une ligne par feuille et par jour saisi) | moyenne à terme | Mesurer à la recette sur les données de démonstration (26 semaines). Si elle devient lente, la borner à la fenêtre et déduire le bord coupé de `LeafPlan::firstEntryDay` (alternative écartée pour l'instant). |
| Fragmentation dans le DOM : une feuille saisie un jour sur deux produit environ 100 tronçons sur 41 semaines | faible | Pas d'infobulle dupliquée (une par partie), et des tronçons en `aria-hidden`. Le poids de la page est vérifié à la recette. |
| L'infobulle commune ne s'affiche pas depuis un tronçon (`mouseenter` sur un enfant, Popper ancré au conteneur) | faible | Scénario E2E qui survole le premier puis le dernier tronçon. |
| Un jour férié du passé est ignoré parce que la capacité ne le connaît pas, d'où une coupure à tort | faible | Plage des jours fériés étendue au plus ancien premier jour saisi, test dans `ScheduleLoaderTest` et cas de l'Ascension dans `RoadmapBuilderTest`. |
| Régression des barres existantes (période de l'infobulle, rouge à partir du jour de dépassement) | faible | Tests existants de `RoadmapControllerTest` et `RoadmapBuilderTest` conservés tels quels, ce qui garde `data-test="roadmap-bar-realized"` unique par partie. |

## Questions ouvertes

_(aucune)_
