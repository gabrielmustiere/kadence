---
name: Cadence
colors:
  heading: "oklch(0.235 0.035 264)"  # encre bleu-noir — titres, action principale
  brand: "oklch(0.5 0.165 262)"      # bleu du stylo — saisi, sélection, focus
  danger: "red-600"                  # stylo rouge — dépassement, destruction
  canvas: "oklch(0.983 0.003 85)"    # papier
  surface: "#FFFFFF"                 # feuille : tableaux, panneaux, modales
  success: "emerald-600"
  warning: "amber-500"
typography:
  family: "Archivo (variable : largeur 100–125 %, graisse 400–700)"
  display: "Archivo élargie 125 %, graisse 620 — titres de page et chiffres de tête"
  body: "Archivo 100 %, 14 px"
  numbers: "tabular-nums partout où des chiffres se comparent"
rounded:
  control: 7px    # rounded-base : boutons, champs
  card: 10px      # rounded-card / rounded-xl : panneaux, tableaux, modales
  inner: 6px      # rounded-lg : segments, entrées de menu (concentriques dans un conteneur p-1)
spacing:
  sourceScale: "4/8/12/16/24/32/48"
---

## Intention

Kadence est le carnet de production de l'équipe. Le texte s'écrit à l'encre bleu-noir sur du papier ; le bleu du stylo marque ce qui a été écrit — le temps saisi, la sélection, le focus ; le stylo rouge marque ce qui déborde ; les hachures, ce qui est calculé et pas encore fait. La seule audace est la **règle graduée** : une barre découpée en jours (ou en paquets de jours sur une longue durée) qui reprend, à l'échelle d'un lot ou d'un projet, la barre de quarts de la saisie. Tout le reste reste calme. La stack (Tailwind 4, Flowbite 4, UX Toolkit) reste celle de l'ADR 0001.

## Tokens

Tout vit dans `@theme` (`assets/styles/app.css`) et se redéfinit sous `.dark` pour le mode sombre.

- **Palettes de base** : `ink-0` → `ink-950` (neutres chauds du papier, teinte 85), `pen-50` → `pen-950` (le bleu du stylo, teinte 262) et `blue-black` (l'encre des titres), en OKLCH.
- **Tokens sémantiques** (les seuls à utiliser dans les gabarits) :
  - surfaces : `bg-canvas`, `bg-surface`, `bg-neutral-secondary-soft` (survol, en-têtes de tableau), `bg-neutral-tertiary` (puces, pistes vides) ;
  - texte : `text-heading`, `text-body`, `text-body-subtle` (≥ 4,5:1 sur toutes les surfaces, en clair comme en sombre) ;
  - bordures : `border-default`, `border-default-subtle` (séparateurs internes), `border-default-medium` (champs) ;
  - accent : `bg-brand`, `bg-brand-softer`, `text-fg-brand`, `ring-brand-medium` (focus) ;
  - action principale : `bg-primary` / `text-on-primary` (encre bleu-noir, s'inverse en mode sombre) ;
  - états : `success`, `warning`, `danger` et leurs variantes `-soft`, `-subtle`, `fg-*-strong`.
- **Utilitaires maison** : `font-display` (Archivo élargie pour les titres et les chiffres de tête), `eyebrow` (libellé de champ ou d'en-tête de tableau : 12 px, casse de phrase), `hatch-brand` (hachures de ce qui est calculé, jamais saisi).
- **Rayons** : `rounded-base` (7 px) pour les contrôles, `rounded-card` / `rounded-xl` (10 px) pour les conteneurs, `rounded-lg` (6 px) pour ce qui s'y emboîte. **Ombres** : aucune sur les conteneurs (bordure et fond suffisent) ; `shadow-md`/`shadow-lg` seulement pour ce qui flotte (menus, infobulles, modales).

## Typographie

- **Archivo** est la seule famille. Les titres de page (`h1`) et les chiffres de tête (heures saisies d'un projet, charge d'une personne) sont en `font-display` : largeur 125 %, graisse 620, interlettrage serré. Pas d'italique ni de mot mis en couleur dans un titre.
- Titres de section en `text-lg font-semibold tracking-tight`.
- Libellés en casse de phrase (`eyebrow`) ; jamais de capitales espacées ni de police à chasse fixe pour les données. Seuls un mot de passe provisoire et l'e-mail qui l'accompagne s'affichent en `font-mono` (système).
- Chiffres toujours en `tabular-nums`.

## Composants

Réutiliser `templates/components/` avant d'écrire des classes ad hoc :

- **`PageHeader`** — titre, description, actions ; `compact` pour les écrans de données (actions sur la ligne du titre). Pas de libellé au-dessus du titre sauf s'il porte une information que la page ne donne pas ailleurs (catégorie d'un tag, projet d'un lot).
- **`Ruler`** — la règle graduée. `size="lg"` en tête d'une fiche, avec graduation et légende (bloc `content`) ; `size="sm"` dans une ligne de tableau, avec `scale` partagé par toutes les lignes pour qu'elles se comparent. Saisi en bleu, restant hachuré, dépassement en rouge, trait plein à la fin de l'estimation dépassée, trait pointillé au projeté.
- **`Card`**, **`TableCard`** (cadre défilant d'un tableau, positionné pour contenir ses `sr-only`), **`Table:*`** (en-têtes en `eyebrow`).
- **`EmptyState`** — icône + message, bord pointillé.
- **`Stat`** — indicateur chiffré (`dt` en `eyebrow`, `dd` en tabulaire).
- **`Avatar`** — initiales ; `tone="ink"` pour la personne connectée et l'en-tête d'une fiche, `soft` dans les listes.
- **`Button`** — `primary` (encre bleu-noir) par défaut ; `brand` pour l'action de création d'une page ; `outline`, `ghost`, `outline-danger`, `danger`. Pas de flèche ajoutée au libellé.
- **`Badge`**, **`Alert`**, **`Modal`** (pied grisé, action principale à droite), **`NavLink`**, **`Logo`** (une journée en quatre quarts, trois saisis).
- **Formulaires** : thème `templates/form/theme.html.twig` (déclaré dans `config/packages/twig.yaml`) ; les choix multiples s'affichent en puces (`.choice-chip`).
- **Contrôles segmentés** (navigation de semaine, d'année, de période, filtres) : conteneur `rounded-xl border bg-surface p-1`, segment `rounded-lg`, actif en `bg-primary` ou `bg-neutral-tertiary`.

## Fiches

- **Fiche projet** (`roadmap/project.html.twig`) : en tête, une phrase d'état (« N j saisis sur une estimation de M j »), les dates de début et de fin calculée, la règle graduée et sa légende chiffrée ; puis les lots avec une règle par ligne sur une échelle commune ; la frise ; le journal et, à côté, les avancements déclarés.
- **Page projet d'administration** (`project/show.html.twig`) : la même règle par lot, à côté du responsable, de l'estimation et de la déclaration d'avancement ; lien vers la fiche du projet.
- **Fiche personne** (`person/show.html.twig`) : pour qui planifie, un bandeau charge (jauge bleue jusqu'à une journée pleine, rouge au-delà) et date de disponibilité ; puis ce qui vient, le temps saisi par projet (barres relatives) et le profil ; la frise ; le journal.

## Données (frise, règles, jauges)

- Saisi : `bg-brand` plein. Dépassement : `bg-danger` (statut réservé, toujours accompagné d'un badge ou d'un chiffre). Restant calculé : `bg-brand-softer` + `hatch-brand` (+ bord pointillé sur la frise). Lot ou projet : `bg-brand-medium` fin.
- Ligne « aujourd'hui » à l'encre (`bg-primary/70`), jamais en rouge.
- Palette validée en clair et en sombre (séparation daltonisme ΔE ≥ 24 entre saisi et dépassement, contraste ≥ 3:1 sur les surfaces).

## Règles

- **À faire** : tokens sémantiques uniquement ; états explicites (survol, `focus-visible` en anneau bleu, désactivé) ; une seule action `primary` par zone ; liens dans un texte soulignés.
- **À éviter** : classes de couleur brutes (`gray-*`, `violet-*`…) dans les gabarits ; dégradés décoratifs ; chaînes « A · B » inventées pour la mise en page ; données fictives qui pourraient passer pour réelles ; icônes destructives rouges au repos (rouge au survol seulement) ; libellés ambigus.
- **Accessibilité** : WCAG 2.2 AA (vérifié avec AccessLint en clair et en sombre), lien d'évitement « Aller au contenu », navigation clavier, `aria-label` sur tout bouton-icône, couleur jamais seule porteuse d'information.
