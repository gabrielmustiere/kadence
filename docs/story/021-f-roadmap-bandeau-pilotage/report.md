# Report — Lire en tête de la roadmap combien de lots demandent une action, et n'afficher qu'eux d'un clic

> **But** : garder la trace de ce qui a dérivé entre l'intention et le livré, et de ce qui reste à faire.
> **Registre** : factuel
> **Story** : `docs/story/021-f-roadmap-bandeau-pilotage/`
> **Amont** : `pitch.md` · `plan.md`

## Synthèse

- **Écarts** : 7, tous réalignés dans `plan.md` ou `pitch.md`.
- **Critères** : 11 / 11 atteints, cochés dans `pitch.md` ; 7 / 7 critères de sortie cochés dans `plan.md`.
- **Non livré** : aucun.
- **Dette** : 1 item, non critique.

Le livré suit le plan. Les écarts sont des précisions d'implémentation ou des correctifs de review. Une seule décision de comportement s'y ajoute : sur la semaine courante, un lien filtré reste sur aujourd'hui. Il reste une réserve d'ergonomie sur téléphone.

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison | Réaligné dans |
|---|---|---|---|
| Pitch, règle 11 : le filtre survit aux semaines, au retour à aujourd'hui et au zoom | Il survit aussi au retour depuis l'édition d'un lot et depuis la page projet, actions comprises | Tranché au plan, pour boucler « traiter un lot, revenir, il a disparu de la liste » | `pitch.md` §Règles métier (11) |
| Tuiles et « Tout afficher » pointent vers la semaine affichée | Sur la semaine courante, ils pointent vers `/roadmap?signal=…` | Implem : un lien partagé reste sur aujourd'hui au lieu de figer la semaine | `plan.md` §Approche retenue |
| `LotController::backWeek()` lit une `RoadmapView` | `backParams()` rend directement les paramètres de redirection (semaine + signal) | Implem : `delete()` et `progress()` reportent ces paramètres tels quels | `plan.md` §Fichiers à modifier |
| Le test d'`action()` va dans le test d'enum s'il existe | `action()` est vérifié dans `RoadmapBoardTest`, face à la liste des signaux du bandeau | Implem : c'est la cohérence entre l'enum et le bandeau qui compte | `plan.md` §Stratégie de test |
| Le lien périmé se teste sur un signal sans lot | Il se joue au 03/01/2000 sur « démarrage en retard » | Implem : en suite complète, la base partagée finit par porter les huit signaux ; un premier essai sur « équipe à revoir » a échoué | `plan.md` §Stratégie de test |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison | Réaligné dans |
|---|---|---|
| `RoadmapRow::leavesAwaiting()` | Prédicat unique partagé par le bandeau et l'élagage, comme le demandait la mitigation « compteurs et frise divergents » du plan | `plan.md` §Fichiers à modifier |
| Route de la roadmap sans filtre posée une fois dans `index.html.twig` | Review [ARCHI] lien « Tout afficher » calculé deux fois | `plan.md` §Fichiers à modifier |

## Dette technique identifiée

1. **[UX] Le bandeau est haut sur téléphone** — `templates/roadmap/_board.html.twig:22` — Avec sept ou huit tuiles empilées une par ligne, il occupe environ 600 px avant la frise (relevé à l'implémentation). La règle 15 est tenue, sans défilement horizontal, mais deux tuiles par ligne sous 640 px restent à envisager.

## Leçons apprises

- **Des assets précompilés masquent la CSS de dev** : un dossier `public/assets/` laissé par un `asset-map:compile` fait servir à AssetMapper des fichiers figés. Les classes Tailwind nouvelles n'atteignent alors ni le navigateur ni Playwright, qui tape sur le même serveur. Le dossier a été supprimé ici. À vérifier avant tout contrôle visuel d'une story UI.
- **Une base partagée rend non déterministe un test qui attend « rien »** : les tests écrivent sans rollback, donc un état vide ne se garantit pas par les données. Il se garantit par l'horloge (`mockTime`), en se plaçant à une date où le signal ne peut pas exister.
