# Plan technique — Regrouper Projets, Équipe et Jours fériés dans une section Administration en bas du menu

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/005-f-menu-administration/`
> **Amont** : `pitch.md`

## Approche retenue

La story ne touche que la présentation. Aucune route, aucun droit et aucune donnée ne changent. Le menu latéral de `templates/base.html.twig` est réorganisé en deux zones :

- **en haut** : Tableau de bord et Ma semaine ;
- **en bas** : la section « Administration », avec Projets, Équipe et Jours fériés. Ces deux dernières entrées restent derrière le `is_granted('ROLE_DIRECTION')` existant.

Le conteneur défilant du menu devient une colonne flex. La liste du haut et la section du bas sont marquées `shrink-0`, et la section reçoit `mt-auto`. Elle se colle en bas quand la hauteur le permet et suit les entrées du haut sinon, sans position fixe : le conteneur défile, rien ne se chevauche. La liste du haut garde une marge fixe (`mb-6`), car `mt-auto` tombe à 0 quand la place manque. Le titre de la section est associé à sa liste (`aria-labelledby`), pour qu'un lecteur d'écran annonce le groupe.

Chaque entrée est rendue par un composant Twig anonyme `NavLink` (lien, icône, état actif), qui centralise les classes et l'état actif. L'entrée active vient d'une fonction Twig pure, `nav_section(route)`, portée par une extension PHP `NavigationExtension`. Elle fait correspondre un préfixe de route à une section (`dashboard`, `timesheet`, `projects`, `team`, `holidays`) et renvoie `null` pour une route sans entrée, comme `app_account_password`. Le gabarit l'appelle une fois avec `app.current_route`, puis passe `active` à chaque `NavLink`.

La page d'accueil (`templates/page/index.html.twig`) garde sa macro `shortcut`, répartie en deux grilles titrées :
- **le quotidien** : Ma semaine, Mon compte ;
- **« Administration »** : Projets, puis Équipe et le nouveau raccourci Jours fériés pour la direction.

### Mécanismes mobilisés

- **Composant Twig anonyme** (`templates/components/NavLink.html.twig`, `{% props %}`), rangé avec les composants Paper existants (`Button`, `Badge`…). Il transmet les attributs restants (`data-test`) par `attributes`, pose `aria-current="page"` sur l'entrée active et fusionne les classes avec `tailwind_merge`, comme les autres composants.
- **Fonction Twig déclarée par attribut** `#[AsTwigFunction('nav_section')]` (Twig 3.30), sur le modèle de `DaysExtension` et de ses `#[AsTwigFilter]`. La fonction est pure : elle reçoit la route au lieu de lire la requête, ce qui la rend testable unitairement sans noyau.
- **`app.current_route`** (variable globale Twig de Symfony) : c'est la route de la requête principale, `null` sur une page d'erreur.
- **Colonne flex, `mt-auto` et `shrink-0`** (Tailwind 4) dans le conteneur `overflow-y-auto` existant : pas de JavaScript ni de `position: fixed`. `mb-6` sur la liste du haut garde l'espacement quand la section la suit.
- **`aria-labelledby`** : la liste de la section Administration pointe vers son titre (`id="nav-admin-title"`).
- **Tokens Paper pour l'état actif** : `bg-brand-softer`, avec texte et icône en `text-fg-brand-strong` et une police semi-grasse. Le survol reste gris (`hover:bg-gray-100`), comme aujourd'hui.
- **Menu déroulant Flowbite existant** (`data-drawer-toggle="app-sidebar"`) : inchangé. Le bouton reçoit seulement `data-test="sidebar-toggle"` pour l'E2E.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Bloc Twig `nav_section` déclaré dans chaque gabarit de page | Une quinzaine de gabarits à toucher, et une page oubliée n'allume rien. La correspondance par préfixe de route couvre d'office une nouvelle route bien préfixée. |
| Tests `starts with` sur la route directement dans `base.html.twig` | De la logique dans le template, contraire aux conventions du projet, et non testable unitairement. |
| Extension qui lit elle-même la requête (`RequestStack`) | Couplage inutile : `app.current_route` fournit déjà la route au gabarit, et la fonction pure se teste sans requête. |
| Macro locale dans `base.html.twig` au lieu d'un composant | Le projet range ses briques d'UI dans `templates/components/` (convention `CLAUDE.md`) ; le composant porte l'état actif et l'accessibilité au même endroit. |
| Section Administration en `position: fixed` / `sticky` en bas | Elle recouvrirait les entrées du haut sur un écran peu haut (règle 3 du pitch). Le `mt-auto` dans une colonne flex n'a pas ce défaut. |
| Style actif gris (fond du survol rendu permanent) | Choix produit : l'entrée active ne doit pas se confondre avec l'entrée survolée. |

## Modèle de données

Aucun impact modèle.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Twig/NavigationExtension.php` | Fonction Twig `nav_section(?string $route): ?string` : correspondance des préfixes de route vers `dashboard`, `timesheet`, `projects` (`app_project_`, `app_lot_`), `team`, `holidays`, sinon `null`. |
| `templates/components/NavLink.html.twig` | Entrée de menu : props `href`, `icon`, `active` ; classes du menu actuelles, état actif en accent de marque avec `aria-current="page"` ; libellé en contenu. |
| `tests/Unit/Twig/NavigationExtensionTest.php` | Chaque route connue vers sa section, avec les sous-pages : `app_timesheet_week`, `app_project_show`, `app_lot_edit`, `app_lot_new_sub_lot`, `app_team_edit`, `app_holiday_year`. `app_account_password`, `app_login` et `null` renvoient `null`. |
| `tests/Controller/NavigationTest.php` | Pour la direction, le lead et la prod :<br>• contenu et ordre des deux zones du menu ;<br>• titre de section associé à sa liste ;<br>• entrée active par page, avec `aria-current` ;<br>• aucune entrée active sur Mon compte ;<br>• blocs de l'accueil selon le rôle. |
| `tests/e2e/navigation.spec.ts` | Section collée en bas sur un écran haut, sans recouvrement sur un écran bas, menu déroulant sur téléphone avec le même découpage, entrée active au fil de la navigation. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `templates/base.html.twig` | Menu latéral :<br>• conteneur défilant en colonne flex ;<br>• liste du haut (Tableau de bord, Ma semaine) en `mb-6 shrink-0` ;<br>• section `data-test="nav-admin"` en `mt-auto shrink-0` : filet, titre « Administration » (`id="nav-admin-title"`), liste en `aria-labelledby` avec Projets, puis Équipe et Jours fériés pour la direction ;<br>• entrées rendues par `<twig:NavLink>` avec `active` calculé par `nav_section(app.current_route)` ;<br>• `data-test="sidebar-toggle"` sur le bouton du menu déroulant, et `data-test="nav-dashboard"` sur Tableau de bord, qui n'en avait pas ;<br>• `data-test` des autres entrées inchangés. |
| `templates/page/index.html.twig` | Deux grilles titrées :<br>• le quotidien : Ma semaine, Mon compte ;<br>• « Administration » (`data-test="home-admin"`) : Projets, Équipe (direction), Jours fériés (direction, nouveau `data-test="home-holidays"`). |

## Hors scope

- **Menu utilisateur de la barre du haut** : inchangé.
- **Passage des classes grises Flowbite du menu aux tokens Paper** : seul l'état actif utilise des tokens. Le reste du menu garde ses classes actuelles (modification chirurgicale).
- **Refactor de la macro `shortcut` de l'accueil en composant** : non retenu, elle ne sert qu'à l'accueil.
- **Nouvelles routes, nouveaux droits, nouveaux contrôleurs** : aucun.

## Impacts transverses

- **Cloisonnement des données** : non concerné.
- **Déclinaisons / thèmes** : non. Les variantes sombres (`dark:`) du menu sont conservées dans `NavLink`.
- **Traduction / i18n** : non. Le libellé « Administration » est en français en dur, comme le reste de l'interface.
- **API / exposition externe** : non.
- **Droits d'accès** : inchangés. Les entrées et raccourcis Équipe et Jours fériés restent derrière `is_granted('ROLE_DIRECTION')`, et les contrôleurs gardent leurs `#[IsGranted]` et leur `access_control`.
- **Emails / notifications** : non.
- **Migration de données** : aucune.
- **Comportement par défaut** : le nouveau menu s'applique à tous dès la mise en service.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Twig/NavigationExtension.php` | unit | Chaque préfixe vers sa section, sous-pages comprises (lots vers `projects`). Routes sans entrée (`app_account_password`, `app_login`) et `null` renvoient `null`. |
| `templates/base.html.twig` + `NavLink` | functional | **Direction** :<br>• le haut contient `nav-dashboard` et `nav-timesheet`, sans `nav-projects` ;<br>• `nav-admin` contient `nav-projects`, `nav-team`, `nav-holidays` dans cet ordre ;<br>• la liste de `nav-admin` est associée au titre « Administration ».<br>**Lead et prod** : `nav-admin` ne contient que `nav-projects`.<br>**Entrée active**, avec `aria-current="page"` sur une seule entrée :<br>• `/` → `nav-dashboard` ;<br>• `/saisie/2026-W40` → `nav-timesheet` ;<br>• une fiche de projet et `/lots/{id}/modifier` → `nav-projects` ;<br>• `/equipe/{id}/modifier` → `nav-team` ;<br>• `/jours-feries/2031` → `nav-holidays` ;<br>• `/mon-compte/mot-de-passe` → aucune entrée. |
| `templates/page/index.html.twig` | functional | Direction : `home-timesheet`, `home-account`, et dans `home-admin` : `home-projects`, `home-team`, `home-holidays`. Prod : `home-admin` ne contient que `home-projects`. |
| `tests/e2e/navigation.spec.ts` | E2E | **1280 × 900** : le bas de `nav-admin` touche le bas du menu. **1280 × 360** : le haut de `nav-admin` est sous le bas de `nav-timesheet`, et le menu défile jusqu'à la section. **390 × 844** : `sidebar-toggle` ouvre le menu, qui affiche `nav-timesheet` puis `nav-admin`. **Navigation** : l'entrée active suit la page après un clic sur Projets puis sur Jours fériés. |

Les tests existants (`TeamControllerTest`, `ProjectControllerTest`, `TimesheetControllerTest`, `HolidayControllerTest`, E2E `projects`, `team`, `timesheet`, `holidays`, `turbo-navigation`) servent de filet : leurs `data-test` sont conservés.

**Hors scope tests** :

- Pas de test visuel du style actif (couleurs) : l'état actif se vérifie par `aria-current`, et le rendu par une capture pendant l'implémentation.
- Pas de test du menu en mode sombre : les variantes `dark:` sont reprises telles quelles.

## Ordre d'exécution

1. [x] **Fonction `nav_section`**
   - Objectif : correspondance des routes vers les entrées du menu.
   - Fichiers : `src/Twig/NavigationExtension.php`, `tests/Unit/Twig/NavigationExtensionTest.php`.
   - Vérification : `make phpunit-filter NavigationExtensionTest`, `make lint`.
   - Commitable seule : oui.

2. [x] **Menu en deux zones avec entrée active**
   - Objectif : composant `NavLink`, section Administration ancrée en bas, entrée active.
   - Fichiers : `templates/components/NavLink.html.twig`, `templates/base.html.twig`, `tests/Controller/NavigationTest.php` (menu).
   - Vérification : `make phpunit-filter NavigationTest` et les tests des contrôleurs existants ; contrôle visuel par capture (écran haut, écran bas, téléphone).
   - Commitable seule : oui.

3. [x] **Accueil découpé**
   - Objectif : bloc Administration et raccourci Jours fériés.
   - Fichiers : `templates/page/index.html.twig`, `tests/Controller/NavigationTest.php` (accueil).
   - Vérification : `make phpunit-filter NavigationTest`.
   - Commitable seule : oui.

4. [x] **E2E et QA finale**
   - Objectif : géométrie du menu vérifiée de bout en bout, sans régression.
   - Fichiers : `tests/e2e/navigation.spec.ts`.
   - Vérification : `make serve` puis `make playwright`, `make phpunit`, `make lint`.
   - Commitable seule : oui.

## Critères de sortie

- [ ] `nav_section()` couvre chaque préfixe de route de l'application, vérifié par le test unitaire.
- [ ] Aucune logique de correspondance de route dans les gabarits : ils n'appellent que `nav_section(app.current_route)`.
- [ ] Une seule entrée porte `aria-current="page"` sur chaque page du menu, et aucune sur Mon compte.
- [ ] Les `data-test` existants du menu et de l'accueil sont inchangés, et leurs tests passent sans modification.
- [ ] `make phpunit` est vert.
- [ ] `make lint` est propre (PHP-CS-Fixer, PHPStan niveau 10).
- [ ] `make playwright` est vert, `navigation.spec.ts` compris, avec `make serve` en marche.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| La section du bas recouvre ou écrase la liste du haut sur un écran peu haut (rétrécissement flex) | moyenne | `shrink-0` sur les deux blocs, `mb-6` sur la liste du haut, scénario E2E à 360 px de haut. |
| Nouvelles classes Tailwind invisibles en dev si `public/assets/` a été régénéré par `make build` | moyenne | Vérifier l'absence de `public/assets/` avant le contrôle visuel (piège constaté à la story 004), et contrôler par capture. |
| Une future route mal préfixée n'allume aucune entrée | faible | Préfixes documentés dans `NavigationExtension`, testés entrée par entrée. Une nouvelle entrée de menu impose une ligne de correspondance. |
| Menu déroulant Flowbite inopérant après une navigation Turbo sur téléphone | faible | Menu déroulant inchangé. Le scénario E2E sur téléphone l'ouvre après une connexion, qui passe par une redirection Turbo. |
| Sélecteurs des tests existants cassés par la réorganisation | faible | `data-test` des entrées et raccourcis conservés, suite existante rejouée. |

## Questions ouvertes

- **Libellé « Administration » pour les leads et la prod** (question ouverte du pitch) : aucun impact technique. Le libellé est une chaîne unique dans `base.html.twig` et `page/index.html.twig`, à changer en deux endroits si l'usage le demande.
