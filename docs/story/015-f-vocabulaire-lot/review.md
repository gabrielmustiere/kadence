# Review — Parler la même langue sur tous les écrans : « lot » à la place de « feuille », un seul mot et une seule unité par notion

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/015-f-vocabulaire-lot/`
> **Amont** : `plan.md` + `pitch.md`
> **Diff examiné** : working tree, 42 fichiers modifiés + 4 nouveaux, ~300 lignes

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 0
- **Mineurs restants** : 0 / 3
- **Statut** : **PRÊT À COMMITER**

Les trois mineurs sont corrigés pendant la passe ; reste la vérification visuelle de la section « Hors review ». Prochaine étape : `/forge:sync`, puis `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

_(aucun)_

## Mineurs

- [x] **[PLAN] L'accueil parle encore des « dates de livraison de l'équipe »** — `templates/page/index.html.twig:9` — Deux écarts au lexique, que le plan n'avait pas relevés et que le garde-fou ne cherche pas : la fin calculée y porte un troisième nom (règle 1), et « l'équipe » y désigne l'ensemble des personnes sur un écran connecté (règle 10). Proposition : « Suivez la charge, l'avancement et la fin calculée de chaque projet. » La story 017 reprend l'accueil, mais elle s'appuie sur ce lexique au lieu de le corriger. Corrigé avec la phrase proposée.
- [x] **[TEST] Deux formats de l'écart ne sont assertés nulle part** — `templates/project/_lot_row.html.twig:46`, `templates/roadmap/_signal.html.twig:10` — Aucun test ne vérifie « 3,25 j (13 %) de dépassement » dans la liste des lots de la page d'administration (critère d'acceptation du pitch), ni que la pastille de la fiche personne reste « en dépassement », sans écart chiffré (règle 7). Corrigé : `ProjectControllerTest::testProjectPageWritesTheOverrunInDaysThenShareOfTheEstimate` et `PersonControllerTest::testTheOverrunSignalOfAPersonDoesNotTellTheGap`.
- [x] **[STYLE] La variable `gap` porte le nom du filtre qu'on lui applique** — `templates/roadmap/_consumption_row.html.twig:62`, `templates/roadmap/_progress.html.twig:11` — `{{ gap|gap(…) }}` se lit mal. Corrigé : variable renommée en `projectedGap`.

## Points positifs

- **Un seul point de formatage** : `Quarters::percentOf()` et le filtre `gap` portent l'arrondi « jamais 0 % » et le signe moins typographique. Les sept écarts chiffrés passent par eux, ce qui empêche deux arrondis de diverger.
- **Garde-fou vérifié** : `VocabularyTest` lit le HTML décodé, infobulles cachées comprises, pour deux rôles. Réintroduire un ancien libellé le fait bien échouer.
- **Code orphelin supprimé** : `RoadmapRow::overrunPercent()` est supprimée plutôt que gardée sans appelant, et ses cas d'arrondi sont testés dans `QuartersTest`.
- **Aucun identifiant technique touché** : les `data-test`, routes, valeurs enregistrées et noms de classes restent inchangés, sauf `roadmap-overrun-percent`, fusionné et tracé dans les écarts.

## Hors review (à vérifier en environnement réel)

- Contrôle visuel de la pastille « en dépassement de 3,25 j (13 %) », plus longue qu'avant, à 1440 px et à 390 px (mitigation du plan, non faite à l'implémentation).
- `tests/e2e/roadmap.spec.ts` l. 73 et 106 échouent, aussi sur `main` sans le diff : régression antérieure à la story, à suivre séparément.
