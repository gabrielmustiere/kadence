# Report — Détailler chaque tronçon et chaque feuille dans les infobulles de la roadmap, et zoomer la frise pour les survoler

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/009-f-roadmap-infobulles-zoom/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 95 %. Écarts structurants :
  - les infobulles sont bornées au cadre de la frise et réaffichées au mouvement du pointeur, deux défauts trouvés à la recette ;
  - la fermeture avant la mise en cache Turbo passe par `turbo:before-cache` au lieu de `disconnect()` (mineur de review) ;
  - un partiel `_team.html.twig` factorise la liste de l'équipe des infobulles et du récapitulatif.
- **Critères** : 15 / 15 cochés, dont un vérifié à la recette seulement (date au centre au zoom).
- **Review** : 0 bloquant, 0 important, 3 mineurs, tous corrigés pendant la passe. Un point d'abord classé important reposait sur un mauvais diagnostic et a été reclassé en mineur. Statut : PRÊT À COMMITER.
- **Périmètre livré** : 5 fichiers créés (~166 lignes), 18 fichiers modifiés (+456 / −236), hors documents de story.

Chaque tronçon a son infobulle, le titre d'une feuille affiche son récapitulatif, une seule infobulle s'affiche à la fois grâce à un contrôleur Stimulus délégué qui remplace Flowbite sur la roadmap, et la frise se zoome de ×1 à ×8. Tout ce que le plan prévoyait est livré. Le découpage du temps de chacun entre « dans l'estimation » et « au-delà » a disparu avec son code et une requête. La dette porte sur le poids du HTML brut, qui suppose la compression HTTP en production.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Model/Roadmap/RoadmapRun.php` | Un tronçon de jours saisis : premier et dernier jour, temps saisi, jours avec saisie, équipe. | Oui |
| `src/Model/Roadmap/RoadmapSegment.php` | Un tronçon placé dans sa barre : géométrie relative et son `RoadmapRun`. | Oui |
| `assets/controllers/roadmap_tooltip_controller.js` | Infobulles de la frise par délégation (`pointerover`, `pointermove`, `pointerout`, `focusin`, `focusout`) : une seule visible, placée par Popper en `fixed`, bornée au cadre de la frise, fermée au défilement, sur `Escape`, avant la mise en cache Turbo et à la déconnexion. | Écart volontaire (cf. §Écarts) |
| `templates/roadmap/_recap.html.twig` | Récapitulatif d'une feuille : début, fin calculée, « inconnue » ou « non calculée », estimé ou « à estimer », saisi, dépassement ou restant, période saisie ou « aucune saisie », jours avec saisie, équipe ou « sans équipe ». | Oui |
| `templates/roadmap/_team.html.twig` | Liste de l'équipe d'une infobulle, avec ou sans le temps saisi, partagée par les infobulles et le récapitulatif. | Non (ajout — cf. §Écarts) |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Repository/TimeEntryRepository.php` | `summarizeByLot()` rend `COUNT(DISTINCT e.day)` ; `sumQuartersByDayAndUserForLots()` ajoutée ; `findQuartersInOrderForLots()` retirée. | Oui |
| `src/Model/Schedule/LeafPlan.php` | `enteredDayCount` (0 par défaut), conservé par `withPlanning()`. | Oui |
| `src/Service/ScheduleLoader.php` | `planOf()` renseigne `enteredDayCount`. | Oui |
| `src/Model/Roadmap/RoadmapBar.php` | `$segments` devient `list<RoadmapSegment>`. | Oui |
| `src/Model/Roadmap/RoadmapWindow.php` | `segmentedBar()` prend des `RoadmapRun` et rend des `RoadmapSegment`. | Oui |
| `src/Model/Roadmap/RoadmapRow.php` | `realizedTeam`, `overrunTeam`, `realizedDayCount`, `overrunDayCount` et `realizedQuarters()` retirés ; `enteredFrom`, `enteredTo`, `enteredDayCount`, `team` et `enteredQuarters()` ajoutés. | Écart volontaire (cf. §Écarts) |
| `src/Service/RoadmapBuilder.php` | `enteredParts()` construit des `RoadmapRun` à partir des saisies par jour et par personne ; `recaps()` remplace `teams()` (totaux par personne et jours avec saisie) ; `beyondEstimate()` retirée ; champs du récapitulatif renseignés pour toute feuille. | Oui |
| `templates/roadmap/_bar.html.twig` | `data-roadmap-tooltip` sur la barre entière ou sur chaque tronçon ; infobulles rendues hors du conteneur ; `data-tooltip-target` retiré. | Oui |
| `templates/roadmap/_tooltip.html.twig` | Masquage par `hidden` ; infobulle de tronçon lue sur son `RoadmapRun` ; équipe via `_team.html.twig`. | Oui |
| `templates/roadmap/_row.html.twig` | Titre de feuille déclencheur du récapitulatif (`tabindex="0"` sur le `<span>`, `aria-describedby` sur le lien et le `<span>`) ; inclusion de `_recap.html.twig` dans la piste. | Écart volontaire (cf. §Écarts) |
| `templates/roadmap/index.html.twig` | Conteneur `roadmap` englobant navigation et frise, avec la position d'ouverture ; groupe de boutons de zoom ; `roadmap-tooltip` et ses actions sur la frise ; `--roadmap-zoom` et largeur minimale calculée. | Oui |
| `templates/roadmap/_today.html.twig` | `data-test="roadmap-today-line"` sur le repère « aujourd'hui ». | Non (ajout — cf. §Écarts) |
| `assets/controllers/roadmap_controller.js` | Zoom : paliers 1, 2, 4 et 8, actions, état des boutons, `sessionStorage`, recentrage, ouverture centrée. | Oui |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | Tests de `segmentedBar()` sur des `RoadmapRun`. | Oui |
| `tests/Service/ScheduleLoaderTest.php` | Jours avec saisie comptés une fois par jour, conservés par `withPlanning()`. | Oui |
| `tests/Service/RoadmapBuilderTest.php` | Détail des tronçons, jour de franchissement, récapitulatif d'une feuille planifiée et d'une feuille non planifiée. | Oui |
| `tests/Controller/RoadmapControllerTest.php` | Infobulles des tronçons, récapitulatif, récapitulatifs des feuilles non planifiées, boutons de zoom et position d'ouverture, absence de `data-tooltip-target`. | Oui |
| `tests/e2e/roadmap.spec.ts` | Feuille interrompue avec un tronçon d'un jour ; une seule infobulle à la fois ; récapitulatif au clavier ; fermeture avant la mise en cache Turbo ; zoom. | Oui |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| Popper avec `preventOverflow` (limite par défaut, la fenêtre). | `preventOverflow` borné au cadre de la frise (`boundary: this.element`). | Recette : le récapitulatif, centré au-dessus du titre, passait sous la barre latérale de l'application. |
| Infobulle affichée au survol (`pointerover`). | `pointermove` déclenche aussi l'affichage. | Recette : une infobulle fermée par un défilement ne revenait qu'en sortant puis en revenant sur le tronçon. |
| Fermeture dans `disconnect()` contre une infobulle restée dans le cache Turbo. | Fermeture sur `turbo:before-cache@document`, en plus de `disconnect()`. | Finding de review **[ROBUSTESSE] La fermeture des infobulles avant la mise en cache Turbo reposait sur `disconnect()`** : Turbo copie la page un tour de boucle après `turbo:before-cache`, `disconnect()` peut intervenir après. Défaut non reproduit en pratique. |
| `RoadmapRow` réorganisé (champs du récapitulatif). | `realizedQuarters()` retirée en plus, et `enteredQuarters()` ajoutée pour le saisi du récapitulatif. | Finding de review **[ARCHI] `RoadmapRow::realizedQuarters()` n'a plus d'usage** ; le saisi total se lit sur l'équipe en totaux. |
| Titre de feuille déclencheur du récapitulatif au survol et au focus. | Titre relié au récapitulatif par `aria-describedby`. | Finding de review **[A11Y] Le récap ouvert au clavier n'est pas annoncé**. |
| E2E : tabulation jusqu'au titre. | E2E : focus placé par `focus()` sur le titre. | Même événement `focusin` déclenché, sans dépendre de l'ordre de tabulation de toute la page. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| Partiel `templates/roadmap/_team.html.twig` | La liste de l'équipe sert à l'infobulle de tronçon, à celle de la partie restante et au récapitulatif ; un partiel évite de la dupliquer. |
| `data-test="roadmap-today-line"` dans `templates/roadmap/_today.html.twig` | Nécessaire à l'E2E qui vérifie l'ouverture d'une frise zoomée sur aujourd'hui (sélecteurs `data-test` imposés par `CLAUDE.md`). |
| Scénario E2E « une infobulle ouverte se ferme avant que Turbo ne mette la page en cache » | Prouve le branchement `turbo:before-cache` de façon déterministe : il échoue sans ce branchement et passe avec, là où un retour arrière réel ne reproduisait pas le défaut. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Model/Roadmap/RoadmapWindow.php` (`segmentedBar`) | unit | unit, 3 tests adaptés aux `RoadmapRun` (le run est conservé par son segment) | Fait |
| `src/Service/ScheduleLoader.php` | functional (base) | functional, 1 test (jour compté une fois, `withPlanning()`) | Fait |
| `src/Service/RoadmapBuilder.php` | functional (base) | functional, 2 tests ajoutés (détail des tronçons et jour de franchissement ; récapitulatif planifié et non planifié), tests de la story 008 adaptés | Fait |
| `src/Controller/RoadmapController.php` (page `/roadmap`) | functional | functional, tests adaptés et étendus (infobulles par tronçon, récapitulatif, feuilles non planifiées, zoom, position d'ouverture, absence de Flowbite) | Fait — couverture étendue |
| `tests/e2e/roadmap.spec.ts` | E2E | E2E, 4 scénarios (une infobulle à la fois et récapitulatif, clavier, fermeture avant mise en cache, zoom) | Fait — couverture étendue (review) |
| Placement au pixel des infobulles | hors scope assumé | pas écrit | Conforme — vérifié à la recette (récap borné au cadre de la frise) |
| Recentrage au pixel près | hors scope assumé | pas écrit | Conforme — décalage nul mesuré à la recette à chaque palier |
| Écran tactile | hors scope assumé | pas écrit | Conforme — question ouverte du pitch tranchée (émulation du survol) |
| `Scheduler` et `PlanningFitsCapacityValidator` | non modifiés, tests existants en filet | inchangés ; `SchedulerTest` et `LotControllerTest` verts après l'étape 1 | Conforme |

Résultats consignés : 356 tests PHPUnit et 39 scénarios Playwright verts à l'implémentation ; 44 tests de la roadmap et 8 scénarios E2E de `roadmap.spec.ts` verts après les corrections de la review, lint propre. La suite complète n'a pas été relancée après la review : non mesuré.

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Sur une feuille de 10 j tenue par une personne au calendrier France à 100 %, saisie d'une journée pleine du lundi 07/09 au vendredi 11/09 puis du lundi 21/09 au vendredi 25/09, survoler le premier tronçon affiche : période du 07/09 au 11/09, 5 j saisis, 5 jours avec saisie, et la personne avec 5 j et 100 %.
- [x] Dans cet exemple, survoler le second tronçon affiche la période du 21/09 au 25/09 et 5 j saisis.
- [x] Dans cet exemple, survoler le titre de la feuille affiche le récapitulatif : début le 07/09, fin le 25/09, 10 j estimés, 10 j saisis, 0 j restant, période saisie du 07/09 au 25/09, 10 jours avec saisie, et la personne avec 10 j et 100 %. — Vérifié champ par champ (début, fin, estimé, saisi, restant, période, jours, équipe) sur une feuille de `RoadmapControllerTest` ; l'exemple lui-même vérifie le saisi, la période et les jours.
- [x] Sur un tronçon où une personne hors équipe a saisi, cette personne apparaît après les membres, marquée « hors équipe », avec son temps sur le tronçon.
- [x] Sur une feuille en dépassement, chaque tronçon au-delà de l'estimation a sa propre infobulle ; la somme des temps de tous les tronçons de la feuille égale le total saisi, et le récapitulatif donne le dépassement.
- [x] La partie restante d'une feuille garde son infobulle actuelle.
- [x] Une feuille sans début affiche un récapitulatif qui indique « sans début » ; une feuille à estimer, « à estimer ».
- [x] Passer d'un tronçon à l'autre, ou d'un tronçon au titre, n'affiche jamais deux infobulles à la fois ; survoler un trou n'en affiche aucune.
- [x] Le récapitulatif s'affiche aussi quand le titre de la feuille reçoit le focus au clavier. — Vérifié en E2E en plaçant le focus sur le titre (`focus()`), plutôt qu'en tabulant jusqu'à lui.
- [x] « + » fait passer la frise de ×1 à ×2, ×4 puis ×8, où il est désactivé ; « − » est désactivé à ×1 ; « 100 % » ramène à ×1.
- [x] À ×8, un tronçon d'un jour mesure au moins 20 px de large et affiche son infobulle au survol.
- [x] En zoomant, la date au centre de l'écran reste la même ; la colonne des titres reste visible pendant le défilement horizontal. — Vérifié à la recette : décalage nul de la date au centre à chaque palier ; l'E2E vérifie la visibilité du repère « aujourd'hui » (hors scope de tests du plan).
- [x] Le palier de zoom est conservé après une navigation de 4 semaines et au retour sur la page pendant la session ; une frise zoomée s'ouvre centrée sur aujourd'hui.
- [x] Les barres de cumul des lots découpés et des projets, et leurs infobulles, sont inchangées.
- [x] Un membre de prod voit les mêmes infobulles et dispose du même zoom qu'un lead ou la direction.

## Dette technique identifiée

Issus de la review (mineurs non traités) :

_(aucun : les trois mineurs ont été corrigés pendant la passe)_

Au-delà de la review :

1. **Poids du HTML brut de `/roadmap`** — 333 Ko sur la fenêtre par défaut (191 Ko mesurés à la recette de la story 008, avant celle-ci) et 513 Ko sur une fenêtre dense, dont l'essentiel tient aux infobulles de tronçon et aux récapitulatifs ; 26 à 32 Ko une fois compressé. Vérifier que la compression HTTP est active dans l'hébergement de production, encore à décider. Si le poids gêne, rendre l'équipe des tronçons à la demande (piste du plan).
2. **Volume de la requête par feuille, jour et personne** — plus large que la requête par jour qu'elle remplace ; même piste de repli que la story 008 (borner à la fenêtre) quand l'historique dépassera un an.
3. **Seuil des libellés de mois au zoom** — `Roadmap::MIN_MONTH_WIDTH` reste calculé pour ×1 (question ouverte du pitch tranchée) ; un mois coupé n'est pas libellé aux paliers supérieurs même quand la place existe.

## Leçons apprises

- **Valider un défaut avant de le classer** — le récapitulatif visible après un retour arrière venait d'un survol légitime (pointeur resté sur le lien). Éloigner la souris, puis rejouer sans le correctif, aurait évité de classer important un défaut non reproduit.
- **Un test de régression doit échouer sans le correctif** — deux premières versions du scénario E2E de retour arrière passaient sans le correctif ; seule la version qui déclenche directement `turbo:before-cache` prouvait le branchement.
- **Infobulles en position fixe** — une infobulle ne doit pas vivre dans un élément transformé (`-translate-y-1/2`) ni dans un contexte d'empilement collant (`sticky z-10`) : elle prend ce parent comme repère ou passe sous les lignes suivantes. D'où les infobulles rendues hors des barres et le récapitulatif placé dans la piste plutôt que dans la colonne des titres.
- **Survol et défilement** — fermer une infobulle au défilement impose de pouvoir la rouvrir sans sortir de l'élément : `pointermove` en plus de `pointerover`.
- **Zoom par largeur relative** — multiplier la largeur réellement affichée (`max(52rem, 100% − 20rem) × palier`) plutôt qu'une largeur minimale fixe garantit qu'un palier ×2 double l'affichage sur tous les écrans.
