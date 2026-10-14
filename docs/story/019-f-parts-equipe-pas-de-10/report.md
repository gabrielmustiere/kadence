# Report — Affecter un collaborateur à une feuille par tranches de 10 % de sa capacité

> **But** : garder la trace de ce qui a dérivé entre l'intention et le livré, et de ce qui reste à faire.
> **Registre** : factuel
> **Story** : `docs/story/019-f-parts-equipe-pas-de-10/`
> **Amont** : `pitch.md` · `plan.md`

## Synthèse

- **Écarts** : 2 — 2 réalignés dans `plan.md`, 0 laissé tel quel.
- **Critères** : 6 / 6 atteints, cochés dans `pitch.md`.
- **Non livré** : aucun.
- **Dette** : 0 item.

Le message de refus d'une part est partagé par une constante plutôt que recopié, et cinq fichiers de tests existants qui posaient des parts de 25 ou 75 % hors validation, absents du recensement du plan, ont été réalignés sur les dizaines.

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison | Réaligné dans |
|---|---|---|---|
| Même message écrit dans `Assert\Choice` et dans l'`invalid_message` du champ | Message porté par la constante `LotMemberInput::INVALID_SHARE`, lue par la contrainte, le formulaire et le test de refus | Éviter le doublon DTO / formulaire (implémentation) | `plan.md` §Mécanismes mobilisés, §Périmètre |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison | Réaligné dans |
|---|---|---|
| Parts 25/75 passées à 20/70 dans `LotInputTest`, `ProjectManagerTest`, `RoadmapBuilderTest`, `SchedulerTest` et dans trois autres tests de `LotControllerTest` ; part invalide de `LotInputTest` passée de 30, devenu valide, à 25 | Ces tests posaient les parts sans passer par le formulaire (`planLot()`, constructeurs, `LotMemberInput::of()`) ; le filtre sur `LotMember::SHARES` les écartait et six tests échouaient. Le recensement du plan ne visait que les parts postées | `plan.md` §Périmètre, §Ordre d'exécution, §Risques et mitigations |

## Dette technique identifiée

_(aucune)_

## Leçons apprises

- **Restreindre une liste de valeurs, c'est aussi chercher ses littéraux hors formulaire** : quand le domaine d'une valeur se resserre, les tests qui la posent directement (helpers de fixtures, constructeurs, fabriques de DTO) échappent à un recensement fait sur les seuls envois de formulaire ; chercher les littéraux de l'ancien domaine dans tout `tests/`, pas seulement dans les payloads.
