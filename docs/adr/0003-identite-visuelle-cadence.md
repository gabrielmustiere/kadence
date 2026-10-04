# ADR-0003 — Identité visuelle « Cadence » à la place de « Paper »

- **Statut** : accepted
- **Date** : 2026-10-04
- **Déciders** : @gabrielmustiere
- **Story liée** : —

## Contexte

L'identité « Paper » héritée du template (`DESIGN.md`, ADR-0001) n'avait jamais été vraiment appliquée : les tokens de `assets/styles/app.css` étaient ceux de Flowbite par défaut (gris froids, violet Tailwind saturé), Montserrat/Roboto donnaient un rendu générique, et plusieurs gabarits (layout, connexion, toasts, `NavLink`) codaient des couleurs en dur (`gray-*`, `violet-*`, `rose-*`) qui contournaient le mode sombre. Kadence est l'outil quotidien de la direction et des leads : écrans denses (grille de saisie, frise de roadmap, tableaux de lots), lus chaque jour. Une refonte complète du design a été demandée, sans toucher aux fonctionnalités. Une première itération a ensuite été revue avec cinq skills de design et d'accessibilité (Frontend Design d'Anthropic, Web Design Guidelines de Vercel, UI/UX Pro Max, Bencium UX Designer, AccessLint), qui y ont relevé les marqueurs d'une interface générée. La stack front (Tailwind 4 + Flowbite 4 + UX Toolkit, ADR-0001) n'est pas remise en cause : seule l'identité change.

## Decision drivers

- **Lisibilité des écrans denses** — chiffres alignés, hiérarchie nette, couleur réservée à ce qui porte du sens (saisi, dépassement, calculé, focus, alertes).
- **Une seule source de vérité** — toute couleur passe par un token sémantique, mode sombre compris, sans classes de couleur brutes dans les gabarits.
- **Zéro régression fonctionnelle** — mêmes `data-test`, mêmes textes vérifiés ; PHPUnit et Playwright verts sans toucher aux tests.
- **Accessibilité WCAG 2.2 AA** — en clair comme en sombre, vérifiée par outil et au clavier.
- **Identité propre à Kadence** — tirée du sujet (un carnet de production compté en quarts de jour), au lieu d'un rendu par défaut.

## Options considérées

### Option A — « Cadence », le carnet de production (retenue)

Neutres chauds du papier (`ink`), texte et action principale à l'encre bleu-noir (`blue-black`), un accent unique « bleu du stylo » (`pen`) pour ce qui est saisi, le rouge pour ce qui déborde, des hachures pour ce qui est calculé — en OKLCH dans `@theme`, mappés sur les tokens sémantiques Flowbite déjà consommés par les composants (`bg-brand`, `text-heading`, `border-default`…), redéfinis sous `.dark`. Une seule famille, Archivo variable, élargie à 125 % pour les titres et les chiffres de tête ; libellés en casse de phrase ; conteneurs sans ombre. Composants Twig : `PageHeader`, `TableCard`, `EmptyState`, `Stat`, `Avatar`, `Logo` et `Ruler` (la règle graduée en jours des lots et des projets, qui reprend la barre de quarts de la saisie) ; thème de formulaire projet (`templates/form/theme.html.twig`) qui rend les choix multiples en puces.

- Aligne avec **Lisibilité** : oui — chiffres tabulaires, accent réservé aux données, règles des lots sur une échelle commune ; paire saisi / dépassement validée (séparation daltonisme ΔE ≥ 24, contraste ≥ 3:1 en clair et en sombre).
- Aligne avec **Source de vérité** : oui — plus aucune couleur brute dans `templates/`, hors palette nommée (`pen`, `blue-black`) du panneau décoratif de la connexion et hex du logo SVG (favicon, `theme-color`) ; le mode sombre suit sans retouche des gabarits.
- Aligne avec **Zéro régression** : oui — 503 tests PHPUnit et 45 scénarios Playwright verts, tests inchangés.
- Aligne avec **Accessibilité** : oui — aucune violation AccessLint sur les pages principales en clair et en sombre (hors barre de debug Symfony), lien d'évitement, focus visible en contraste forcé.
- Aligne avec **Identité propre** : oui — logo (une journée en quatre quarts), règle graduée et vocabulaire visuel du carnet propres au sujet.
- Coût / trade-off : une police Google Fonts (variable) ; un thème de formulaire et un composant de règle à maintenir.

### Option B — Première itération « Cadence » (Geist, Instrument Serif, iris)

Même architecture de tokens, mais titres en Instrument Serif, interface en Geist, étiquettes en Geist Mono capitales espacées, accent « iris » proche de l'indigo, cartes arrondies avec la même ombre.

- Aligne avec **Lisibilité**, **Source de vérité**, **Zéro régression** : oui (c'est la base de l'option A).
- Aligne avec **Identité propre** : non — la revue a relevé titre serif sur papier crème, libellés mono en capitales au-dessus de chaque titre, chaînes « A · B », polices par défaut de Next.js, accent indigo identique à la recommandation par défaut d'UI/UX Pro Max pour ce type de produit, kit de cartes identiques.
- Aligne avec **Accessibilité** : partiel — `body-subtle` sous 4,5:1 en mode sombre, navigation latérale masquée aux technologies d'assistance sur desktop (`aria-hidden` posé par le drawer Flowbite).

### Option C — Appliquer réellement « Paper »

Câbler les valeurs de `DESIGN.md` (primaire #111111, secondaire #8B5CF6, Montserrat/Roboto/PT Mono) dans les tokens et nettoyer les couleurs en dur.

- Aligne avec **Source de vérité** : oui, après nettoyage.
- Aligne avec **Lisibilité** : partiel — PT Mono et Montserrat se prêtent mal aux tableaux denses ; Paper ne définit ni palette de données, ni mode sombre, ni règles de composants.
- Aligne avec **Identité propre** : non — Paper est l'identité générique du template, partagée avec les autres projets dérivés.

### Option D — Statu quo

Garder les tokens Flowbite par défaut et la typographie actuelle.

- Tentant : aucun travail, aucun risque.
- Insuffisant : l'écart entre `DESIGN.md` et le code persiste, les couleurs en dur cassent le mode sombre, et la demande de refonte reste sans réponse.

## Décision

**Option retenue : A**

Elle est la seule à satisfaire à la fois la lisibilité des écrans denses (chiffres tabulaires, accent réservé aux données, règle graduée) et l'identité propre à Kadence, que l'option B ratait de l'aveu même des skills de revue. Les tokens restent l'unique source de vérité, mode sombre compris, et l'accessibilité est vérifiée (AccessLint en clair et en sombre, parcours clavier). Le zéro régression est tenu parce que la refonte reste dans les gabarits, les composants Twig, `app.css` et deux contrôleurs Stimulus d'interface (`theme`, `sidebar`), sans toucher aux contrôleurs, formulaires PHP, tests ni `data-test`. Le surcoût (un thème de formulaire, un composant de règle, une police) est jugé proportionné.

## Conséquences

**Positives**

- Changer l'identité revient à changer les palettes `ink` / `pen` / `blue-black` dans `@theme` : composants et pages ne référencent que des tokens sémantiques.
- Mode sombre disponible (interrupteur dans la barre du haut avec `aria-pressed`, préférence système par défaut, mémorisé dans `localStorage`).
- Fiches projet et personne réorganisées autour de la question qu'elles servent : où en est le projet face à son estimation (règle graduée en tête, règles des lots), quelle charge et quelle disponibilité pour la personne.
- Navigation latérale à nouveau exposée aux lecteurs d'écran sur desktop (`sidebar_controller.js`), lien « Aller au contenu », débordement horizontal sur mobile corrigé.

**Négatives / coûts assumés**

- Une dépendance à Google Fonts (Archivo), comme l'étaient Montserrat/Roboto/PT Mono.
- `font-display` porte largeur, graisse et interlettrage : ne pas le combiner avec `tracking-*` ni une graisse.
- Le thème `templates/form/theme.html.twig` surcharge des blocs de `@TalesFromADevFlowbite/form/default.html.twig` : à revérifier à chaque montée de version du bundle.
- `sidebar_controller.js` compense le comportement du drawer Flowbite (`aria-hidden` à chaque initialisation) : à revérifier à chaque montée de version de Flowbite.

**Suites obligatoires**

- [x] Réécrire `DESIGN.md` pour décrire « Cadence ».
- [x] Mettre à jour les mentions de « Paper » dans `CLAUDE.md` et `docs/stack.md`.
- [x] Passer l'ADR-0001 en « superseded by ADR-0003 » pour sa partie identité (la stack reste en vigueur).
- [x] Retirer le skill `.claude/skills/design-system` (Paper).

## Links

- ADR superseded : ADR-0001, pour sa partie identité « Paper » uniquement (la stack Tailwind 4 + Flowbite 4 + UX Toolkit reste en vigueur)
- Design system : `DESIGN.md`, tokens `assets/styles/app.css`, composants `templates/components/`
- Références externes : Archivo — https://fonts.google.com/specimen/Archivo ; OKLCH — https://oklch.com ; skills de revue : anthropics/skills (frontend-design), vercel-labs/agent-skills (web-design-guidelines), nextlevelbuilder/ui-ux-pro-max-skill, bencium/bencium-claude-code-design-skill (bencium-controlled-ux-designer), accesslint/claude-marketplace
