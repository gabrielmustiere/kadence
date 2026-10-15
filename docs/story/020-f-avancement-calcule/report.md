# Report — Calculer le restant d'une feuille à partir du temps saisi aujourd'hui, sans rien figer à la déclaration

> **But** : garder la trace de ce qui a dérivé entre l'intention et le livré, et de ce qui reste à faire.
> **Registre** : factuel
> **Story** : `docs/story/020-f-avancement-calcule/`
> **Amont** : `plan.md` (pas de pitch : la story révise les règles 9 à 17 du pitch de la story 012)

## Synthèse

- **Écarts** : 5, tous réalignés dans `plan.md`.
- **Critères** : sans objet. Le plan n'a pas de critères de sortie à cocher et la story n'a pas de pitch ; sa §Vérification est consignée dans l'entrée `Implémentation` de `metadata.json`.
- **Non livré** : aucun.
- **Dette** : 0 item.

Le calcul livré est celui du plan. Le diff le dépasse sur trois points : la démo, la règle de la page projet et le libellé de l'écart projeté. L'utilisateur les a confirmés comme voulus dans cette story lors de la review, sans en donner le motif. S'y ajoute un correctif de review : « avancement à actualiser » ne retient plus la fin.

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison | Réaligné dans |
|---|---|---|---|
| Fixtures : déclarations réduites au pourcentage | Démo refondue en plus : lots de SCA et d'Onboarding renommés et découpés, `ESTIMATES_AHEAD`, `BEHIND_ESTIMATE`, favoris déduits des quatre dernières semaines | Voulu dans la story, arbitré à la review ([PLAN] le diff dépasse le périmètre du plan sans trace) ; motif non tracé | `plan.md` §Fichiers modifiés |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison | Réaligné dans |
|---|---|---|
| `RoadmapSignal::withholdsEnd()` sans « avancement à actualiser » | Review [BUG] : le signal était encore donné comme cause d'une fin inconnue sur la roadmap et l'accueil | `plan.md` §Fichiers modifiés et §Tests |
| Règle de la page projet : dépassement placé avant le restant (`Ruler.html.twig`) | Voulu dans la story, arbitré à la review [PLAN] ; motif non tracé | `plan.md` §Fichiers modifiés |
| Page projet : « Écart projeté », « dont … déjà saisis au-delà des estimations », légende « dont déjà dépassé » | Voulu dans la story, arbitré à la review [PLAN] ; motif non tracé | `plan.md` §Fichiers modifiés |
| Tests adaptés hors de la table du plan (`CreatesProgress`, `ScheduleLoaderTest`, `HomeAgendaBuilderTest`, `RoadmapProjectControllerTest`, `VocabularyTest`, `lot-team-filter.spec.ts`) | Nouvelle signature de `LotProgress` et feuilles de la démo refondue ; non tracé ailleurs | `plan.md` §Tests |

## Dette technique identifiée

_(aucune)_

## Leçons apprises

- **Redéfinir un signal de roadmap engage toutes ses méthodes** : le plan a réécrit le sens et la raison de « avancement à actualiser », mais pas `withholdsEnd()`. Or trois écrans en dépendent : la fin d'une ligne, l'infobulle des fins inconnues et les projets à surveiller de l'accueil. La review l'a rattrapé.
- **Une story sans pitch n'a pas de critère pour ancrer un changement visible** : la refonte de la démo et les libellés de la page projet sont arrivés sans trace dans le plan ni dans le changelog. Seule la review les a fait apparaître.
