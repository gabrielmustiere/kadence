# ADR-0001 — Stack front du template : Paper + Tailwind 4 + Flowbite 4 + UX Toolkit

- **Statut** : accepted — partie identité « Paper » superseded by ADR-0003 (la stack reste en vigueur)
- **Date** : 2026-05-26
- **Déciders** : @gabrielmustiere
- **Story liée** : —

> Hérité de `gabrielmustiere/symfony-template` lors de l'amorçage de Kadence (2026-09-28). La décision s'applique telle quelle au projet ; le contexte ci-dessous est celui du template.

## Contexte

Ce dépôt est un **template Symfony 8** destiné à servir de point de départ à plusieurs projets. À ce titre, son front doit livrer "out of the box" un look pro, des composants interactifs (drawer, toast, datepicker, dropdown) et un theming centralisé que chaque projet dérivé peut customiser sans repartir d'une feuille blanche.

La contrainte forte est de rester **aligné avec la philosophie Symfony UX** (Twig + Stimulus + Turbo, hotwire-style) — pas de framework JS lourd (React/Vue) qui doublerait la couche de rendu et romprait le modèle mental Symfony des contributeurs. En parallèle, le commit `fc0b0ef` a introduit un design system documenté ("Paper", cf. `DESIGN.md`) qu'il faut câbler au CSS et aux composants.

Les modifications en cours (UX Toolkit + `tales-from-a-dev/twig-tailwind-extra` + composant `templates/components/Button.html.twig`) complètent ce socle en apportant un mécanisme de composants Twig réutilisables avec variants (`html_cva`) et fusion intelligente de classes Tailwind (`tailwind_merge`). L'ADR grave le choix global avant qu'il ne se dissolve dans le diff.

## Decision drivers

- **Productivité immédiate** — un nouveau projet dérivé du template doit pouvoir construire un écran complet en assemblant des composants prêts, sans phase "je refais une CSS".
- **Alignement Symfony UX / Twig** — pas de framework JS, tout en Twig + Stimulus, pour préserver la cohérence avec le reste de la stack et le modèle mental des contributeurs Symfony.
- **Theming centralisé et override-friendly** — les tokens (couleurs, typo, radius) vivent à un seul endroit (`@theme` dans `assets/styles/app.css`) pour que chaque projet dérivé puisse repeindre l'identité sans toucher aux composants.

## Options considérées

### Option A — Paper + Tailwind 4 + Flowbite 4 + UX Toolkit + Twig Tailwind Extra (retenue)

Empilement de quatre briques complémentaires :

- **Tailwind CSS 4** pour les utilitaires + theming par variables CSS (`@theme`, `@source`, `@custom-variant dark`).
- **Flowbite 4** pour les composants JS interactifs (drawer sidebar, dropdown, toast, datepicker) via `importmap`, sans framework JS.
- **Symfony UX Toolkit** (`symfony/ux-toolkit`) pour la syntaxe `<twig:Button>` / `<twig:ux:icon>` et l'écosystème de composants Twig réutilisables.
- **`tales-from-a-dev/twig-tailwind-extra`** pour `html_cva` (variants typés) et `tailwind_merge` (fusion de classes utilisateur sans collisions).
- **Design system Paper** documenté dans `DESIGN.md` (Roboto / Montserrat / PT Mono, palette monochrome + accent violet).

- Aligne avec **Productivité immédiate** : oui — drawer/toast/datepicker prêts, composants Twig versionnés (`<twig:Button variant="brand">`).
- Aligne avec **Alignement Symfony UX** : oui — Flowbite est piloté côté HTML par data-attributes (compatible Turbo), UX Toolkit est officiel Symfony, aucun framework JS.
- Aligne avec **Theming centralisé** : oui — toutes les couleurs/typo passent par les variables `@theme`, les composants consomment des tokens (`bg-brand`, `text-heading`), changer d'identité = remplacer un bloc de variables.
- Coût / trade-off : empilement de 4 dépendances front à maintenir + courbe d'apprentissage de `html_cva` pour les contributeurs.

### Option B — Tailwind Plus + Stimulus pur

Utiliser **Tailwind Plus** (ex Tailwind UI, composants HTML/JS officiels Tailwind Labs, payants) et piloter les interactions avec Stimulus seul, sans Flowbite ni UX Toolkit.

- Aligne avec **Productivité immédiate** : partiellement — les composants Tailwind Plus sont fournis en HTML brut à copier/coller, pas en composants Twig packagés. Chaque projet dérivé doit re-coller et re-styler.
- Aligne avec **Alignement Symfony UX** : oui — Stimulus fait déjà partie de la stack.
- Aligne avec **Theming centralisé** : partiel — Tailwind Plus suit Tailwind, mais les composants livrés ont leurs propres conventions de classes, ce qui frotte avec un design system custom comme Paper.
- Coût / trade-off : licence payante par développeur, pas de versioning npm/composer des composants (copier/coller), réécriture manuelle des comportements JS que Flowbite donne gratuitement (datepicker notamment).

## Décision

**Option A retenue.**

Tailwind Plus + Stimulus pur (Option B) répond à *Alignement Symfony UX* mais échoue sur *Productivité immédiate* dans un contexte **template** : ce qui compte n'est pas qu'un projet *puisse* construire ses composants, mais qu'il *parte avec* des composants packagés et versionnés (composer/importmap), réutilisables d'un projet dérivé à l'autre. Le modèle copier/coller de Tailwind Plus est antinomique avec la notion de template.

Le coût accepté — empilement de 4 dépendances + dépendance à Flowbite (projet indépendant non aligné sur le cycle Symfony) — est jugé proportionné parce que (a) chaque brique remplit un rôle distinct sans chevauchement, (b) Flowbite est piloté en HTML/data-attributes, donc remplaçable composant par composant si le projet devait diverger.

## Conséquences

**Positives**

- Un nouveau projet dérivé du template part avec un écran d'accueil professionnel, une sidebar mobile, des toasts de flash et un thème sombre fonctionnel sans aucune écriture de CSS.
- Les composants Twig (`<twig:Button>`, à venir : Badge, Card, Input, Alert) sont versionnés dans le template et héritables par chaque projet.
- Le theming Paper est centralisé dans `assets/styles/app.css` — repeindre l'identité d'un projet dérivé = remplacer un bloc de variables `@theme`.
- `html_cva` apporte une syntaxe typée pour les variants des composants Twig, contrôlée par les `@prop` du fichier.

**Négatives / coûts assumés**

- **Empilement de 4 dépendances front** (Tailwind, Flowbite, UX Toolkit, Twig Tailwind Extra) : surface de maintenance augmentée et risque de divergence entre les couches (ex: un upgrade Tailwind majeur peut casser Flowbite ou les classes générées par `html_cva`).
- **Dépendance à Flowbite** : projet indépendant, son cycle de release ne suit pas Symfony. Risque d'incompatibilité sur les majeures futures de Tailwind ou de Symfony UX — à surveiller à chaque upgrade.
- Courbe d'apprentissage : les contributeurs doivent comprendre le pattern `html_cva` (variants / sizes / shapes) pour créer de nouveaux composants Twig sans dupliquer la logique.

**Suites obligatoires**

- [ ] Documenter dans `CLAUDE.md` la règle : « tout composant UI = composant Twig dans `templates/components/` avec `html_cva` pour les variants, classes consommant les tokens `@theme` (pas de couleurs hard-codées) ».

## Links

- Commit fondateur : `fc0b0ef` — "feat(template): adopter le design system Paper avec intégration Flowbite"
- Design system : `DESIGN.md` (front-matter Paper)
- Superseded par : ADR-0003 (`0003-identite-visuelle-cadence.md`) pour l'identité « Paper » uniquement — Tailwind 4, Flowbite 4 et UX Toolkit restent en vigueur
- Theming : `assets/styles/app.css` (variables `@theme` + mode sombre via `.dark`)
- Composant de référence : `templates/components/Button.html.twig` (pattern `html_cva` + `tailwind_merge`)
- Références externes :
  - Flowbite — https://flowbite.com/
  - Symfony UX Toolkit — https://symfony.com/bundles/ux-toolkit/current/index.html
  - `tales-from-a-dev/twig-tailwind-extra` — https://github.com/tales-from-a-dev/twig-tailwind-extra
