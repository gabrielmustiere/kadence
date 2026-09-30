# Regrouper Projets, Équipe et Jours fériés dans une section Administration en bas du menu

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/005-f-menu-administration/`
> **Amont** : aucun

Le menu sépare ce que l'on fait au quotidien (Tableau de bord, Ma semaine) des écrans d'administration (Projets, Équipe, Jours fériés). Ces derniers sont regroupés dans une section « Administration », ancrée en bas du menu. La page d'accueil suit le même découpage, et le menu signale la page où l'on se trouve.

## Contexte

Le menu latéral aligne aujourd'hui cinq entrées, sans hiérarchie : Tableau de bord, Ma semaine et Projets pour tout le monde, puis Équipe et Jours fériés pour la direction. Chaque story en ajoute une en bout de liste. À mesure que Kadence grandit (roadmap, charge, capacité), le geste du quotidien se perd au milieu des écrans de gestion : saisir sa semaine, le seul geste demandé chaque jour à toute l'équipe. Rien n'indique non plus la page où l'on se trouve.

La page d'accueil reprend les mêmes raccourcis, sans découpage non plus. Elle est déjà en retard sur le menu : la page « Jours fériés », livrée par la story 004, n'y a pas de raccourci.

## Alignement vision

- **Problème adressé** : indirect. La feature ne touche aucun des trois symptômes. Elle garde l'outil lisible à mesure que des écrans s'ajoutent.
- **Audience servie** :
  - toute l'équipe, dont le geste quotidien reste en tête du menu ;
  - la direction et les leads, qui retrouvent leurs écrans de gestion au même endroit.
- **Principes respectés** : le principe 1 (« saisir sa journée prend moins d'une minute ») est servi, puisque Ma semaine reste en tête et n'est plus concurrencée par les écrans de gestion. Aucun droit ne change, et aucun anti-objectif n'est concerné.
- **Hypothèse testée** : aucune.
- **Impact North Star** : neutre.

## Utilisateurs concernés

- **Direction** : voit en haut Tableau de bord et Ma semaine, et en bas la section « Administration » avec Projets, Équipe et Jours fériés.
- **Lead et prod** : voient en haut Tableau de bord et Ma semaine, et en bas la section « Administration » avec la seule entrée Projets. Leurs droits sur les projets ne changent pas.
- **Toute personne connectée** : voit la page où elle se trouve mise en évidence dans le menu, et retrouve le même découpage sur la page d'accueil.

## User Stories

- En tant que **membre de l'équipe**, je veux trouver Ma semaine en tête du menu, séparée des écrans de gestion, afin de saisir sans chercher.
- En tant que **direction**, je veux retrouver Projets, Équipe et Jours fériés regroupés dans une section « Administration » en bas du menu afin de distinguer la gestion du travail quotidien.
- En tant que **personne connectée**, je veux voir dans le menu l'entrée de la page où je me trouve, même sur une sous-page, afin de savoir où j'en suis.
- En tant que **direction**, je veux retrouver sur la page d'accueil les raccourcis d'administration regroupés, Jours fériés compris, afin que l'accueil et le menu racontent la même chose.
- En tant que **lead ou membre de prod**, je ne veux PAS voir dans le menu ni sur l'accueil d'entrée vers un écran qui m'est interdit (Équipe, Jours fériés).

## Règles métier

### Menu

1. **Deux zones** : le haut du menu présente Tableau de bord puis Ma semaine. Le bas présente la section « Administration », séparée du haut et titrée.
2. **Contenu de la section Administration** : Projets, Équipe, Jours fériés, dans cet ordre. Chacun n'y voit que les entrées de son rôle :
   - la direction voit les trois ;
   - un lead ou un membre de prod ne voit que Projets.
3. **Ancrage en bas** : la section se place en bas du menu, quelle que soit la hauteur de l'écran. Si la hauteur ne suffit pas, elle suit les entrées du haut sans les recouvrir, et le menu défile.
4. **Téléphone** : le menu déroulant présente le même découpage.
5. **Droits inchangés** : le regroupement ne donne ni ne retire aucun accès. Un écran interdit reste interdit, et n'apparaît pas dans le menu.

### Page courante

6. **Entrée active** : l'entrée qui correspond à la page affichée est mise en évidence, y compris sur une sous-page :
   - la fiche d'un projet, la création, la modification d'un projet, d'un lot ou d'un sous-lot allument « Projets » ;
   - une fiche de personne allume « Équipe » ;
   - une autre année allume « Jours fériés » ;
   - une autre semaine allume « Ma semaine ».
7. **Aucune entrée active** : sur une page qui n'a pas d'entrée dans le menu (Mon compte), rien n'est mis en évidence.

### Page d'accueil

8. **Même découpage** : la page d'accueil présente d'abord les raccourcis du quotidien (Ma semaine, Mon compte), puis un bloc « Administration » titré avec Projets, Équipe et Jours fériés. Chaque raccourci suit les droits du rôle, comme dans le menu.
9. **Raccourci Jours fériés** : la direction dispose d'un raccourci vers la page « Jours fériés », qui manquait jusqu'ici.

## Critères d'acceptation

- [ ] Pour toute personne connectée, le haut du menu présente Tableau de bord puis Ma semaine, et Projets n'y figure plus.
- [ ] Pour la direction, le bas du menu présente une section titrée « Administration » avec Projets, Équipe et Jours fériés, dans cet ordre, séparée du haut.
- [ ] Pour un lead et pour un membre de prod, la section « Administration » ne contient que Projets.
- [ ] Sur un écran d'ordinateur assez haut, la section « Administration » est collée en bas du menu ; sur un écran peu haut, elle suit les entrées du haut sans les recouvrir.
- [ ] Sur un écran de téléphone, le menu déroulant présente le même découpage.
- [ ] L'entrée de la page affichée est mise en évidence : « Ma semaine » sur n'importe quelle semaine, « Projets » sur la liste, la fiche, la création ou la modification d'un projet, d'un lot ou d'un sous-lot, « Équipe » sur la liste et les fiches, « Jours fériés » sur n'importe quelle année, « Tableau de bord » sur l'accueil.
- [ ] Sur la page « Mon compte », aucune entrée du menu n'est mise en évidence.
- [ ] La page d'accueil présente les raccourcis Ma semaine et Mon compte, puis un bloc titré « Administration » avec Projets, et, pour la direction seulement, Équipe et Jours fériés.
- [ ] Aucun accès ne change : un lead ou un membre de prod n'a toujours accès ni à Équipe ni à Jours fériés, et continue de consulter les projets.

## Hors scope

- **Menu utilisateur en haut à droite** (Mon compte, déconnexion) : inchangé.
- **Droits d'accès** : aucun changement. Le regroupement est purement visuel.
- **Groupe « Pilotage »** pour les futurs écrans (roadmap, charge, capacité) : non retenu. Les stories qui les ajouteront choisiront leur place dans le menu.
- **Section repliable ou dépliable** : non retenue.
- **Refonte visuelle du menu** (icônes, largeur, thème) : non retenue. Seuls le regroupement et la page active changent.
- **Nouvelles pages ou nouveaux raccourcis** au-delà de Jours fériés sur l'accueil : non retenus.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement : « Administration » et les libellés existants.
- **Droits d'accès** : inchangés. Le menu et l'accueil continuent de n'afficher que les entrées autorisées pour le rôle.
- **Cloisonnement des données** : non.
- **Apparence / déclinaisons** : le menu latéral (ordinateur et téléphone) et la page d'accueil. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune donnée touchée.
- **Comportement par défaut** : tout le monde voit le nouveau menu dès la mise en service. Aucun réglage à faire.

## Questions ouvertes

- **Libellé de la section pour les leads et la prod** : pour eux, la section « Administration » ne contient que Projets, qu'ils consultent sans l'administrer au sens strict. Options : (a) garder « Administration » pour tous (choix actuel) ; (b) un libellé plus neutre, comme « Gestion », si le retour d'usage montre une confusion.

---

## Annexe — Pistes pour le plan

- Menu latéral dans `templates/base.html.twig` : `aside` à pleine hauteur ; la section du bas peut s'ancrer avec une colonne flex (`flex flex-col`, `mt-auto`) dans le conteneur défilant, sans position fixe. À confirmer.
- Page active : comparer la route courante (`app.request.attributes.get('_route')`) à des préfixes (`app_project_`, `app_lot_`, `app_team_`, `app_holiday_`, `app_timesheet`), ou exposer une petite fonction Twig. À confirmer. Il faudra aussi un attribut `aria-current="page"` sur l'entrée active.
- Accueil : `templates/page/index.html.twig`, macro `shortcut` réutilisable pour le bloc Administration, nouveau `data-test="home-holidays"`. À confirmer.
- Tests existants touchés : les assertions de menu et d'accueil (`nav-team`, `home-team`, `nav-holidays`) dans `TeamControllerTest` et `HolidayControllerTest`, et `tests/e2e/turbo-navigation.spec.ts` (menu déroulant Flowbite). À vérifier.
