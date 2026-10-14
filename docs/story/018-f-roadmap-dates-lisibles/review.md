# Review — Lire sur la roadmap, sans rien survoler, quand chaque projet et chaque lot se termine

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/018-f-roadmap-dates-lisibles/`
> **Amont** : `plan.md` + `pitch.md`
> **Diff examiné** : working tree, 15 fichiers modifiés + 3 nouveaux, ~320 lignes

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 0
- **Mineurs restants** : 0 / 2
- **Statut** : **PRÊT À COMMITER**

`/forge:sync`, puis `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

_(aucun)_

## Mineurs

- [x] **[STYLE] Lots en cause et identifiant d'infobulle recalculés à trois endroits** — `templates/roadmap/index.html.twig:40`, `templates/roadmap/_end.html.twig:7-12`, `templates/roadmap/_end_tooltip.html.twig:5-7` — la condition `unknownEndLeaves`/`unplannedLeaves` et l'identifiant `roadmap-end-<lot|project>-<id>` sont reconstruits par le `<summary>`, la ligne de date et l'infobulle ; si l'une diverge, `aria-describedby` pointe vers une infobulle absente. Corrigé : `RoadmapRow::endCauses()` et `endTooltipId()`, lus par les trois gabarits ; test unitaire de l'ordre des lots en cause.
- [x] **[DOC] Contrat de `--roadmap-labels` non écrit** — `templates/roadmap/_frieze.html.twig:1-4` — le cadre pose la largeur de la colonne des titres que doivent lire toutes les grilles du bloc `rows` (roadmap, page projet, page personne) ; le docblock ne le dit pas, un futur gabarit qui embarque la frise reprendrait `20rem` en dur. Corrigé : le docblock pose le contrat.

## Points positifs

- **Aucun calcul nouveau** : `RoadmapBuilder`, `Scheduler` et les repositories sont intacts ; deux lectures pures (`unplannedLeaves()`, `isBefore()`/`isAfter()`) suffisent, testées en unitaire. Nombre de requêtes mesuré sur les fixtures avant et après : identique (`/roadmap` 16, pages projet 18 et 17).
- **Règle 11 tenue sans condition de rôle** : seules les raisons `withholdsEnd()` entrent dans une explication, et le test des lignes de date tourne pour la direction, le lead et la prod.
- **Accessibilité pensée pour le `<summary>`** : la date entre dans le nom accessible, la liste passe par `aria-describedby`, la ligne d'un lot découpé est un bouton pour l'ouvrir au clavier.
- **Recette à 390 px suivie d'effets** : pastilles qui passent à la ligne et repère hors période collant au bord visible, corrigés avant la review plutôt que laissés en dette.
- **E2E durcis avec leur cause écrite** : `bringIntoView()` et `rendered()` disent pourquoi ils existent, sur le modèle du commentaire déjà présent dans `person.spec.ts` et `progress.spec.ts`.

## Hors review (à vérifier en environnement réel)

- Audit AccessLint de `/roadmap` en clair et en sombre, prévu par l'étape 5 du plan et non lancé (`/accessibility-scan`).
- Régression préexistante, à suivre séparément : `roadmap-tooltip` ferme l'infobulle à tout défilement, si bien qu'un déclencheur atteint au clavier hors de l'écran (la page défile pour le montrer) referme aussitôt son infobulle.
- Régression préexistante, à suivre séparément : dans le formulaire de lot, une action Live lancée pendant le rendu de la précédente est écrasée par lui et l'équipe saisie se perd (scénario E2E en échec une fois sur quatre avant la story).
