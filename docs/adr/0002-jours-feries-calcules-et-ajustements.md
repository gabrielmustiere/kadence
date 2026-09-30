# ADR-0002 — Jours fériés : jours légaux calculés à la volée, écarts stockés comme ajustements

- **Statut** : accepted
- **Date** : 2026-09-30
- **Déciders** : @gabrielmustiere
- **Story liée** : `docs/story/004-f-jours-feries/`

## Contexte

La story 004 verrouille la saisie des temps les jours fériés. Chaque personne suit le calendrier français (11 jours légaux) ou belge (10 jours légaux). Trois des jours fériés sont mobiles, car ils dépendent de Pâques. La grille se navigue vers n'importe quelle semaine, passée ou future : les jours fériés doivent donc être justes pour n'importe quelle année.

La loi ne suffit pas. En Belgique, un jour férié qui tombe un week-end doit être remplacé par un jour que fixe l'employeur (en 2026, le 15 août et le 1er novembre). En France, le lundi de Pentecôte est souvent travaillé au titre de la journée de solidarité. La direction doit donc pouvoir ajouter ou retirer un jour, date par date et calendrier par calendrier.

Ce modèle ne sert pas qu'à la saisie. Le rappel de saisie (`rappel-saisie`, qui mesure le « % de jours ouvrés saisis ») et la capacité de l'équipe (`capacite-equipe`) liront les mêmes jours fériés. L'hébergement de production n'est pas encore choisi (`docs/stack.md`).

## Decision drivers

1. **Justesse sans maintenance annuelle** : les jours légaux, fêtes mobiles comprises, sont justes pour n'importe quelle année sans action humaine.
2. **Écarts propres à l'entreprise, séparés de la loi** : un jour de remplacement ou un jour férié travaillé se déclare par date et par calendrier, et reste distinguable des jours légaux.
3. **Source unique réutilisable** : la saisie, puis le rappel de saisie et la capacité lisent les jours fériés au même endroit.
4. **Dépendances minimales, aucun service externe** : la vision exclut les intégrations au départ.

## Options considérées

### Option A — Calcul à la volée par un service maison, et table d'ajustements (retenue)

Un service pur (`LegalHolidays`) calcule les jours légaux d'une année pour un calendrier : les dates fixes, plus Pâques via la fonction native `easter_days()` (extension `ext-calendar`). Une table `holiday_adjustment` ne stocke que les écarts : un ajout avec son libellé, ou un retrait, unique par couple (calendrier, jour). Un service unique (`HolidayManager`) les combine : jours légaux, plus les ajouts, moins les retraits.

- **Driver 1** : oui. Toute année se calcule, sans génération ni saisie préalable.
- **Driver 2** : oui. Les ajustements vivent dans leur propre table, et la page de la direction distingue « légal », « ajouté » et « retiré ».
- **Driver 3** : oui. `HolidayManager` est la seule porte d'entrée, qu'appellent la saisie et, demain, le rappel de saisie et la capacité.
- **Driver 4** : partiel. Il n'y a aucune bibliothèque ni aucun service externe, mais on dépend d'une extension PHP.
- **Coût / trade-off** : environ 40 lignes de calcul à tester. Une évolution de la loi impose un changement de code.

### Option B — Bibliothèque `azuyalabs/yasumi`, et table d'ajustements

Yasumi fournit les jours fériés de la France et de la Belgique. La table d'ajustements reste identique à l'option A.

- **Driver 1** : oui. La bibliothèque calcule toute année et suit les évolutions de la loi au fil de ses versions.
- **Driver 2** : oui, avec la même table d'ajustements.
- **Driver 3** : oui, derrière le même service unique.
- **Driver 4** : non. On ajoute une dépendance pour 21 dates. Elle renvoie des jours à filtrer (Pâques et Pentecôte le dimanche, sous-régions comme l'Alsace-Moselle) et des libellés à réaligner sur ceux du pitch.
- **Coût / trade-off** : moins de calcul à écrire, mais une couche de filtrage et une dépendance à suivre.

### Option C — Table matérialisée des jours fériés par année

Une commande génère en base les jours fériés d'une année pour chaque calendrier. La direction les modifie ensuite directement.

- **Driver 1** : non. Il faut lancer la commande chaque année, sinon l'année suivante n'a aucun jour férié. Naviguer vers une année non générée donne une grille fausse.
- **Driver 2** : partiel. Une fois édités, les jours légaux et les décisions de l'entreprise se confondent en base.
- **Driver 3** : oui, via une table unique.
- **Driver 4** : oui, sans dépendance.
- **Coût / trade-off** : une tâche annuelle à ne pas oublier, et une donnée dérivée de la loi qui peut diverger d'elle.

### Option D — Liste déclarée entièrement par la direction chaque année

Rien n'est calculé : la direction saisit la liste de chaque calendrier.

- **Driver 1** : non. Il faut une saisie annuelle pour deux calendriers, et un oubli se traduit par des jours fériés saisissables.
- **Driver 2** : oui, puisque tout est une décision de l'entreprise, mais la loi n'y est plus distinguable.
- **Driver 3** : oui.
- **Driver 4** : oui.
- **Coût / trade-off** : cette option a été écartée au cadrage produit (pitch de la story 004, calendrier « calculé + ajustable »).

## Décision

**Option A retenue.**

Seules les options A et B satisfont à la fois la justesse sans maintenance (driver 1) et la séparation de la loi et des écarts (driver 2). C tombe sur le driver 1 (génération annuelle), et D sur les drivers 1 et 2. Entre A et B, le driver 4 tranche. Pour deux pays et 21 dates, dont trois dérivées de Pâques, la dépendance de B coûte plus qu'elle ne rapporte : il faudrait filtrer des jours et réaligner des libellés, pour un calcul que `easter_days()` réduit à trois additions.

La dépendance à `ext-calendar` est acceptée pour deux raisons : elle est déclarée dans `composer.json`, donc visible dès l'installation, et son remplacement est local. Un calcul de Pâques maison (algorithme de Meeus, une dizaine de lignes) se substitue à `easter_days()` dans `LegalHolidays` seul, sans migration ni changement de modèle.

## Conséquences

**Positives**

- Aucune donnée de jours légaux en base. Le schéma ne porte que les décisions de l'entreprise, ce qui les rend auditables sur la page « Jours fériés ».
- Toute semaine, passée ou future, affiche des jours fériés justes sans préparation.
- Les features `rappel-saisie` et `capacite-equipe` n'ont pas de modèle à concevoir : elles consomment `HolidayManager`.
- La contrainte unique (calendrier, jour) interdit d'emblée un ajout et un retrait contradictoires le même jour.

**Négatives / coûts assumés**

- Dépendance à l'extension `ext-calendar`, que l'hébergement de production devra fournir.
- Les listes et libellés légaux sont codés en dur dans `LegalHolidays`. Un changement de loi exige un changement de code et un déploiement. D'ici là, la direction peut compenser par un ajustement.
- Une évolution de la loi s'appliquerait aussi aux années passées, puisque le calcul n'est pas historisé. C'est acceptable tant que la liste légale reste stable.
- Un ajustement vaut pour une seule date : un jour férié travaillé chaque année (journée de solidarité) se retire chaque année.

**Suites obligatoires**

- [ ] Déclarer `"ext-calendar": "*"` dans `composer.json` (étape 1 du plan de la story 004).
- [ ] Vérifier la disponibilité de `ext-calendar` au moment du choix de l'hébergement de production (`docs/stack.md`, hébergement non renseigné).

## Links

- Artifact source : `docs/story/004-f-jours-feries/plan.md`
- Cadrage fonctionnel : `docs/story/004-f-jours-feries/pitch.md`
- ADR superseded : aucun
- ADR liés : aucun
- Références externes :
  - `easter_days()` : https://www.php.net/manual/fr/function.easter-days.php
  - France : Code du travail, article L3133-1 (fêtes légales) et articles L3133-7 et suivants (journée de solidarité)
  - Belgique : loi du 4 janvier 1974 relative aux jours fériés et arrêté royal du 18 avril 1974 (jours de remplacement)
