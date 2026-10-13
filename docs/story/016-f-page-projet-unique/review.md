# Review — Lire et gérer un projet sur une seule page, chacun selon ses droits

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/016-f-page-projet-unique/`
> **Amont** : `plan.md` + `pitch.md`
> **Diff examiné** : working tree, 26 fichiers modifiés ou supprimés + 4 nouveaux, ~500 lignes ajoutées et ~370 retirées

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 1
- **Mineurs restants** : 1 / 2
- **Statut** : **PRÊT À COMMITER**

`/forge:sync`, puis `/forge:commit` ; le mineur restant passe en dette du `report.md`.

## Bloquants

_(aucun)_

## Importants

- [x] **[PLAN] Le total projeté n'est pas marqué « estimation partielle »** — `templates/project/show.html.twig:92` — la règle 10 du pitch veut que le total à terme le soit aussi, quand des lots restent à estimer ; seule l'estimation portait le badge. Corrigé : phrase `project-page-projected-partial` (« Estimation partielle : N lot(s) reste(nt) à estimer, et leur restant n'est pas compté dans le total projeté. ») sous le chiffre de tête, assertée dans `RoadmapProjectControllerTest`, ainsi que son absence quand tout est estimé.

## Mineurs

- [x] **[UX] Le résumé du journal dit « Afficher » une fois déplié** — `templates/roadmap/_timeline.html.twig:19` — corrigé : « Afficher » ou « Masquer » selon l'état du `<details>`, en CSS (`group-open:`), testé.
- [ ] **[UX] Le journal déplié se replie après chaque action** — `templates/roadmap/_timeline.html.twig:16` — le rafraîchissement en morph retire l'attribut `open` que le serveur ne rend pas : un lead qui a déplié les mois précédents puis déclare un avancement les retrouve repliés. Action, si l'irritant se confirme : conserver `open` au morph en écoutant `turbo:before-morph-attribute`.

## Points positifs

- **Calcul hors du gabarit** : `projectedQuarters()`, `isProjectedAtLeast()` et `isRemainingUnknown()` sont des méthodes de modèle testées en unitaire, alignées sur les signaux « en dépassement » et « avancement à actualiser » de la frise.
- **Droits déplacés sans être réécrits** : les `is_granted` et `LotVoter` d'origine sont repris tels quels, et quatre profils sont testés (prod, responsable prod, lead, direction).
- **Semaine d'origine validée** : `Week::tryFromIso()` filtre `roadmap` en lecture, dans le champ caché comme dans la 301. Il n'y a donc ni redirection ouverte ni valeur brute réinjectée.
- **Retour au même endroit** : grâce au champ `roadmap`, la redirection vise la même URL, Turbo rafraîchit en morph et la position est conservée. Le zoom de la frise est réappliqué, et un scénario E2E le vérifie.
- **Repli du journal sans JavaScript** : `<details>` natif, paramètre optionnel ; la fiche d'une personne n'a pas changé.

## Hors review (à vérifier en environnement réel)

- `public/assets/` compilé masque le JS modifié en dev : relancer `make build` (ou supprimer le dossier) avant toute recette du zoom de la frise.
- Échec E2E préexistant sur `main`, sans lien avec la story : `tests/e2e/roadmap.spec.ts:73` (« un lead planifie une feuille et la voit sur la roadmap », `roadmap-bar-future` introuvable). À suivre séparément.
- Tableau à huit colonnes sur téléphone (390 px) : défilement horizontal de la `TableCard`, sans débordement de la page. À regarder sur un vrai téléphone.
