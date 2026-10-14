# Report — Lire sur la roadmap, sans rien survoler, quand chaque projet et chaque lot se termine

> **But** : garder la trace de ce qui a dérivé entre l'intention et le livré, et de ce qui reste à faire.
> **Registre** : factuel
> **Story** : `docs/story/018-f-roadmap-dates-lisibles/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Écarts** : 13 — 11 réalignés dans `plan.md`/`pitch.md`, 2 laissés tels quels.
- **Critères** : 14 / 14 atteints, cochés dans `pitch.md` ; 6 / 6 critères de sortie cochés dans `plan.md`.
- **Non livré** : 1 (audit AccessLint de l'étape 5 du plan).
- **Dette** : 3 items, dont 0 critique.

La ligne de date est livrée comme prévue. L'infobulle des lots en cause a pris un partiel à part. La recette à 390 px a ajouté le retour à la ligne des pastilles et un repère hors période collant. Restent l'audit AccessLint et deux régressions préexistantes révélées par les E2E.

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison | Réaligné dans |
|---|---|---|---|
| Un partiel `_end.html.twig` qui porte la ligne de date et son infobulle | `_end.html.twig` pour la ligne, `_end_tooltip.html.twig` pour l'infobulle, posée dans la piste | L'infobulle doit vivre hors de la cellule de titre collante, comme celles des pastilles (implémentation) | `plan.md` §Approche retenue, §Périmètre |
| `aria-describedby` sur la cellule de titre d'un lot découpé | Ligne de date en bouton focalisable, porteuse d'`aria-describedby` | Ouvrir la liste des lots en cause au clavier (implémentation) | `plan.md` §Mécanismes mobilisés ; `pitch.md` §Règles métier 10 |
| `_unknown_ends.html.twig` réutilisé tel quel | Paramètre `heading` : « Laissés hors de sa fin calculée, faute de planification : » quand la fin est connue | La phrase « Faute d'une fin calculée » est fausse pour un projet partiel dont la fin est connue (implémentation) | `plan.md` §Approche retenue, §Périmètre |
| Lots en cause et identifiant d'infobulle calculés dans les gabarits | `RoadmapRow::endCauses()` et `endTooltipId()`, lus par le `<summary>`, la ligne de date et l'infobulle | Review [STYLE] « Lots en cause et identifiant d'infobulle recalculés à trois endroits » | `plan.md` §Approche retenue, §Modèle de données, §Périmètre |
| `templates/roadmap/project.html.twig` | `templates/project/show.html.twig` | La story 016, livrée avant, a remplacé la fiche de la roadmap par la page projet unique ; le plan l'anticipait | `plan.md` §Périmètre |
| Test « après la période » sur `/roadmap/2026-W20` | Lot planifié au 2027-09-06, vu depuis `/roadmap` | La fenêtre de 2026-W20 court jusqu'en janvier 2027 et couvre octobre 2026 (implémentation) | `plan.md` §Stratégie de test |
| Questions ouvertes du plan et du pitch | 11rem ; retraits `ps-6`/`ps-8` sous `md` ; pastille « planification partielle » inchangée ; repère en texte | Options par défaut, confirmées à la recette (implémentation) | `plan.md` §Questions ouvertes ; `pitch.md` §Questions ouvertes |
| `RoadmapProjectControllerTest` : lignes de date de la frise seulement | `testAnUnknownEndNamesTheLeavesWithoutOneAndWhy` restreint à l'en-tête | La frise liste désormais elle aussi les lots en cause, le test comptait deux listes (implémentation) | `plan.md` §Périmètre |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Audit AccessLint de `/roadmap` en clair et en sombre (étape 5 de l'ordre d'exécution) | Non lancé à l'implémentation ; la recette visuelle à 390 px et 1440 px, en clair et en sombre, a été faite | Dette n°1 |

### Ajouts non prévus

| Élément ajouté | Raison | Réaligné dans |
|---|---|---|
| Pastilles qui passent à la ligne sous `md` (`_signal.html.twig`), aussi hors de la frise | À la recette, une pastille de dépassement débordait de la colonne de 11rem et masquait le repère hors période | `plan.md` §Approche retenue, §Périmètre ; `pitch.md` §Règles métier 15 et 19 |
| Repère hors période collant au bord de la piste visible | Rester lisible quand la frise défile (recette) | `plan.md` §Approche retenue, §Périmètre ; `pitch.md` §Règles métier 14 |
| Retrait de la ligne de date d'un projet réservé à `md` et au-delà | Ne pas resserrer davantage la colonne de 11rem (recette) | — |
| Scénarios E2E existants durcis : `bringIntoView()` avant `hover()`/`focus()`, `rendered()` dans le formulaire de lot | Lignes plus hautes : la page défile et le défilement ferme l'infobulle ; course préexistante du formulaire Live, qui perdait l'équipe | `plan.md` §Périmètre, §Stratégie de test |

## Dette technique identifiée

1. **Audit AccessLint non lancé** — `/roadmap`, en clair et en sombre — lancer `/accessibility-scan`, comme le demandent l'étape 5 du plan et `DESIGN.md` §Règles.
2. **Infobulle fermée par le défilement au clavier** — `templates/roadmap/_frieze.html.twig`, `assets/controllers/roadmap_tooltip_controller.js` — `scroll@window->roadmap-tooltip#hide` referme aussitôt l'infobulle d'un déclencheur atteint au clavier hors de l'écran ; régression préexistante, à traiter dans une story à part.
3. **Course du formulaire de lot** — `templates/components/LotForm.html.twig` — une action Live lancée pendant le rendu de la précédente est écrasée et l'équipe saisie se perd ; régression préexistante, le scénario E2E échouait une fois sur quatre avant la story.

## Leçons apprises

- **Une frise plus haute déplace les E2E d'infobulles** : `roadmap-tooltip` se ferme à tout défilement, et `hover()` comme `focus()` font défiler la page ; un scénario d'infobulle amène sa ligne à l'écran d'abord (`bringIntoView()`, déjà pratiqué dans `person.spec.ts` et `progress.spec.ts`).
- **Resserrer une colonne, c'est aussi prévoir ce qui y déborde** : les pastilles en `whitespace-nowrap` dépassaient la colonne de 11rem ; le plan n'avait anticipé que les titres et la ligne de date.
- **Une fenêtre de test se calcule** : la roadmap montre 4 semaines avant l'ancre et 36 après ; une semaine choisie sans ce calcul (2026-W20) couvrait la période que le test voulait éviter.
