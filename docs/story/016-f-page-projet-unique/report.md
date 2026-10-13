# Report — Lire et gérer un projet sur une seule page, chacun selon ses droits

> **But** : garder la trace de ce qui a dérivé entre l'intention et le livré, et de ce qui reste à faire.
> **Registre** : factuel
> **Story** : `docs/story/016-f-page-projet-unique/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Écarts** : 9. 8 sont réalignés dans `plan.md`, `pitch.md` ou `DESIGN.md`, 1 est laissé tel quel.
- **Critères** : 14 / 15 atteints et cochés dans `pitch.md`. Les 7 critères de sortie de `plan.md` sont cochés.
- **Non livré** : 1 critère non vérifiable tel quel (les chiffres d'Atlas).
- **Dette** : 4 items, dont 1 critique.

La livraison suit le plan. Les écarts viennent de trois sources : deux choix d'implémentation (redirection plus légère, recentrage de la frise après le morph), la convention `data-test` du plan appliquée jusqu'au bout, et deux correctifs de review (total projeté marqué partiel, libellé « Masquer » du journal). La dette porte surtout sur l'environnement de dev et un échec E2E préexistant.

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison | Réaligné dans |
|---|---|---|---|
| `RoadmapController::project()` garde son `MapEntity` (`findOneForDetail()`) | Le projet est résolu par son seul id ; 404 conservé | Charger tout le détail du projet est inutile pour une redirection (implem) | `plan.md` §Approche retenue, §Mécanismes mobilisés, §Périmètre, §Questions ouvertes |
| `refresh()` sur `turbo:morph` réapplique le zoom et recentre | `hold()` sur `turbo:before-render` retient le centre visible, `refresh()` réapplique le zoom et recentre sur ce point | Recentrer sur le même point de la frise qu'avant l'action (implem) | `plan.md` §Mécanismes mobilisés, §Périmètre |
| Seul `project-heading` renommé dans les tests | Renommés aussi : `project-description`, `lot-estimate-cell`, `lots-empty`, `project-total` (convention `project-page-*` / `consumption-*`) ; la cellule « Saisi » ne porte plus le dépassement, qui a sa colonne | Convention `data-test` fixée au plan (§Risques), appliquée à toute la page fusionnée (implem) | `plan.md` §Périmètre |
| Total à terme « estimation partielle » (règle 10), sans forme fixée au plan | Phrase « Estimation partielle : N lot(s) reste(nt) à estimer… » sous le chiffre de tête | Review **[PLAN] Le total projeté n'est pas marqué « estimation partielle »** | `plan.md` §Périmètre (`show.html.twig`) |
| Résumé « Afficher les mois précédents (N) » | « Afficher » ou « Masquer » selon l'état du `<details>` | Review **[UX] Le résumé du journal dit « Afficher » une fois déplié** | `pitch.md` §Règles métier (22), `plan.md` §Périmètre |
| Sous-tâches 2 et 3 menées l'une après l'autre | Menées ensemble | La page inclut le tableau (implem) | — (ordre sans conséquence de lecture) |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Critère « Sur Atlas, l'en-tête annonce 462,25 j projetés pour une estimation de 390 j… » | Non vérifiable tel quel : au 2026-10-13, les fixtures donnent à Atlas 423 j estimés et 493,5 j projetés (+70,5 j, +17 %), « au moins », 364 j saisis, 129,5 j de restant et 18,5 j de dépassement. Le calcul et le « au moins » sont couverts par `ProjectSummaryTest` et `RoadmapProjectControllerTest::testTheProjectedCostIsAMinimumWhileALeafOverrunsWithoutProgress` | Aucune ; le critère reste décoché |

### Ajouts non prévus

| Élément ajouté | Raison | Réaligné dans |
|---|---|---|
| `templates/project/_remaining.html.twig` | Restant puis dépassement, partagés par les lignes de lots et la ligne de cumul (implem) | `plan.md` §Périmètre |
| `tests/Controller/VocabularyTest.php` : `/roadmap/projets/{atlas}` retiré | L'ancienne adresse répond en 301, que le test ne compte pas comme un succès (implem) | `plan.md` §Périmètre |
| `tests/Controller/PersonControllerTest.php` : liens vers `/projets/{id}` | Conséquence directe de la bascule des liens (implem) | `plan.md` §Périmètre |

## Dette technique identifiée

1. **`public/assets/` compilé masque le JS modifié en dev** : `make build` et `make quality` lancent `asset-map:compile`, et le dossier, ignoré par git, est alors servi à la place des sources. Relancer `make build` après toute modification de JS, ou ne pas compiler en dev. **Critique** au premier test manuel ou E2E d'un contrôleur Stimulus modifié (source : « Hors review »).
2. **Échec E2E préexistant sur `main`** : `tests/e2e/roadmap.spec.ts:73` (« un lead planifie une feuille et la voit sur la roadmap », `roadmap-bar-future` introuvable), sans lien avec la story. À suivre séparément (source : « Hors review »).
3. - [ ] **[UX] Le journal déplié se replie après chaque action** — `templates/roadmap/_timeline.html.twig:16` — le rafraîchissement en morph retire l'attribut `open` que le serveur ne rend pas : un lead qui a déplié les mois précédents puis déclare un avancement les retrouve repliés. Action, si l'irritant se confirme : conserver `open` au morph en écoutant `turbo:before-morph-attribute`.
4. **Tableau à huit colonnes sur téléphone** : défilement horizontal de la `TableCard` vérifié à 390 px en headless, sans débordement de la page ; reste à regarder sur un vrai téléphone (source : « Hors review »).

## Leçons apprises

- **Un rafraîchissement en morph efface l'état que le navigateur garde dans le DOM** : le zoom d'un contrôleur Stimulus, l'attribut `open` d'un `<details>`. Le plan avait prévu le zoom, pas le journal. Toute page qui gagne une action en morph doit recenser cet état.
- **En Twig, `0 == null` est vrai** : comparer `loop.index0` à un paramètre optionnel sans tester `is not null` ouvre le repli dès le premier mois.
- **`public/assets/` compilé fausse les E2E en dev** : un scénario qui échoue sans explication sur un contrôleur modifié fait d'abord soupçonner les assets compilés.
