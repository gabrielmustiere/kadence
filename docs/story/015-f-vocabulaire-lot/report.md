# Report — Parler la même langue sur tous les écrans : « lot » à la place de « feuille », un seul mot et une seule unité par notion

> **But** : garder la trace de ce qui a dérivé entre l'intention et le livré, et de ce qui reste à faire.
> **Registre** : factuel
> **Story** : `docs/story/015-f-vocabulaire-lot/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Écarts** : 9 — 9 réalignés dans `plan.md`, 0 laissé tel quel.
- **Critères** : 14 / 14 atteints, cochés dans `pitch.md` ; 6 / 7 critères de sortie cochés dans `plan.md`.
- **Non livré** : 1 — le critère de sortie « Scénarios Playwright touchés verts », à cause de deux échecs de `roadmap.spec.ts` qui existaient déjà sur `main`.
- **Dette** : 2 items, aucun critique.

Le lexique et le format d'écart sont livrés comme prévu. Ce qui a dérivé est technique : `RoadmapRow::overrunPercent()` est supprimée au lieu d'être conservée, un `data-test` est fusionné, et quatre retouches de libellés ou de tests manquaient à l'inventaire du plan. Restent une vérification visuelle et deux scénarios E2E de la roadmap, cassés avant la story.

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison | Réaligné dans |
|---|---|---|---|
| `RoadmapRow::overrunPercent()` délègue à `Quarters::percentOf()`, contrat inchangé | Méthode supprimée ; ses cas d'arrondi passent dans `QuartersTest`, ses assertions sont retirées de `RoadmapRowTest` et `RoadmapBuilderTest` | Plus aucun gabarit ne l'appelait une fois la pastille et le récapitulatif passés par `gap` ; `CLAUDE.md` demande de supprimer ce que ses changements rendent orphelin (implem) | `plan.md` §Approche retenue, §Mécanismes mobilisés, §Périmètre, §Stratégie de test, §Ordre d'exécution, §Hors scope |
| Aucun attribut `data-test` ne change | `roadmap-overrun-percent` fondu dans `roadmap-overrun` (« 1 j (50 %) ») | Le filtre `gap` rend jours et part d'un seul tenant ; aucun test E2E ne visait cet attribut (implem) | `plan.md` §Approche retenue, §Périmètre, §Hors scope |
| `VocabularyTest` : `/tags` et `/lots/{id}/modifier` parcourus pour les deux rôles | Parcourus en direction seulement | `/tags` répond 403 à la prod (constaté à l'implémentation) ; pour `/lots/{id}/modifier`, raison non tracée | `plan.md` §Stratégie de test |
| En-tête de la fiche : dépassement via `gap` | « 0 j » sans part quand il n'y a pas de dépassement, `gap` sinon | Éviter « 0 j (0 %) » quand il n'y a pas d'écart (implem) | `plan.md` §Périmètre |
| `_consumption_row` et `_progress` : `gap|gap(…)` | Variable renommée `projectedGap` | Review **[STYLE] La variable `gap` porte le nom du filtre qu'on lui applique** | `plan.md` §Périmètre |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Critère de sortie « Scénarios Playwright touchés verts » | `roadmap.spec.ts` l. 73 et 106 échouent, aussi sur `main` sans le diff (vérifié à l'implémentation) ; les 9 autres scénarios de ce fichier et les 43 du reste de la suite passent | Dette n° 2 |

### Ajouts non prévus

| Élément ajouté | Raison | Réaligné dans |
|---|---|---|
| Pastille `Completed` « terminée à 100 % » → « terminé à 100 % », et `RoadmapControllerTest` l. 185 | Accord au masculin de la règle 3 du pitch ; libellé absent de l'inventaire du plan (implem) | `plan.md` §Périmètre |
| `RoadmapProjectControllerTest` l. 124 (projeté « 12 j +4 j (+50 %) ») et l. 102 (en-tête « 5 j (50 %) ») | Assertions sur l'ancien format, absentes de l'inventaire du plan (implem) | `plan.md` §Périmètre |
| Accueil : « Suivez la charge, l'avancement et la fin calculée de chaque projet. » | Review **[PLAN] L'accueil parle encore des « dates de livraison de l'équipe »** | `plan.md` §Périmètre |
| `ProjectControllerTest` (dépassement de la liste des lots) et `PersonControllerTest` (pastille sans écart chiffré) | Review **[TEST] Deux formats de l'écart ne sont assertés nulle part** | `plan.md` §Périmètre, §Stratégie de test |

## Dette technique identifiée

1. **Contrôle visuel de la pastille de dépassement** — `templates/roadmap/_signal.html.twig:10` — Vérifier « en dépassement de 3,25 j (13 %) » à 1440 px et à 390 px. Point « Hors review » de la review ; mitigation du plan, non faite à l'implémentation.
2. **`roadmap.spec.ts` l. 73 et 106 en échec** — `tests/e2e/roadmap.spec.ts:73` — La barre « restant » du lot planifié n'est jamais trouvée, et aucune infobulle de tronçon ne s'affiche. L'échec est le même sur `main`, sans rapport avec le lexique. À diagnostiquer dans une story à part.

## Leçons apprises

- **Inventorier les tests par le rendu, pas seulement par le libellé** : l'inventaire du plan cherchait les mots remplacés. Il a manqué les assertions sur l'ancien format chiffré (« 12 j (+4 j) ») et un accord féminin (« terminée »). Pour un changement de format, il faut aussi chercher les fragments rendus par les gabarits touchés.
- **Un garde-fou par liste de termes bannis ne voit pas les synonymes** : `VocabularyTest` n'a pas attrapé « dates de livraison de l'équipe », qui contredit pourtant les règles 1 et 10. Il protège contre les retours en arrière, pas contre un troisième nom resté en place ; la relecture reste nécessaire.
