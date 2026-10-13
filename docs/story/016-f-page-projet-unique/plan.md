# Plan technique — Lire et gérer un projet sur une seule page, chacun selon ses droits

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/016-f-page-projet-unique/`
> **Amont** : `pitch.md`

## Approche retenue

La page unique est servie par `ProjectController::show()` (`src/Controller/ProjectController.php:63`), sur la route existante `app_project_show` (`/projets/{id}`). C'est la cible de toutes les redirections de `ProjectController` et de `LotController` (`src/Controller/LotController.php:52, 76, 93, 134, 153`), des formulaires de lot et de projet (`templates/lot/new.html.twig`, `templates/project/edit.html.twig`) et de la liste des projets. Garder cette route évite de toucher la quinzaine de points de retour existants. L'action reçoit ce que reçoit aujourd'hui `RoadmapController::project()` (`src/Controller/RoadmapController.php:44-56`). C'est d'abord le `ProjectRoadmap` de `RoadmapBuilder::buildProject($project, $this->isGranted('ROLE_LEAD'))` : frise, timeline, signaux et feuilles à fin inconnue. C'est ensuite le `ProjectSummary` de `ProjectRollup::summarize()`, que la page de gestion reçoit déjà, puis la semaine d'origine lue dans le paramètre `roadmap`. Les deux pages chargent déjà le projet par `findOneForDetail()`, et la fusion n'ajoute donc que les requêtes de `buildProject()`, en nombre constant (story 010). `RoadmapController::project()` ne rend plus rien. Il redirige en 301 vers `app_project_show` en conservant `roadmap`. Il résout le projet par son seul id, sans `findOneForDetail()` inutile à une redirection, si bien qu'un projet inconnu reste un 404.

Le gabarit `templates/project/show.html.twig` est réécrit à partir de `templates/roadmap/project.html.twig`, qui est supprimé. On y garde l'en-tête de page, la synthèse et la règle graduée, la frise, le journal et les avancements déclarés. On y ajoute les actions de la page de gestion actuelle : boutons et modale de suppression du projet, « Ajouter un lot », signaux « à estimer / à désigner / à redésigner » de `project/_signals.html.twig`. Le tableau unique `project/_lots_table.html.twig` fusionne `roadmap/_consumption.html.twig` et `project/show.html.twig` (échelle partagée des règles, en-têtes, ligne de cumul). Ses lignes viennent de `project/_lot_row.html.twig`, qui absorbe les cellules de `roadmap/_consumption_row.html.twig`. Les deux partiels `roadmap/_consumption*.html.twig` sont supprimés. Chaque action reste gardée par les contrôles actuels : `is_granted('ROLE_LEAD')` pour la structure, `LOT_EDIT` et `LOT_PROGRESS` de `LotVoter` (`src/Security/Voter/LotVoter.php:33-49`) pour le lot. Aucun voter n'est écrit. Le total à terme vient de deux méthodes de modèle, `ProjectSummary::projectedQuarters()` et `ProjectSummary::isProjectedAtLeast()`, qui s'appuient sur une méthode de feuille, `LotSummary::isRemainingUnknown()`. La page les lit, sans calcul dans le gabarit.

### Mécanismes mobilisés

- **Route conservée et redirection 301 dans le contrôleur** (`RoadmapController::project()` → `redirectToRoute('app_project_show', [...], 301)`) : la résolution du projet par son id garde le 404 d'un projet inconnu, et les signets comme les liens partagés suivent. `NavigationExtension` (`src/Twig/NavigationExtension.php:12-20`) allume déjà « Projets » sur le préfixe `app_project_`. Aucun changement n'y est donc nécessaire.
- **`#[MapQueryParameter] ?string $roadmap` + `Week::tryFromIso()`** : même lecture tolérante que `RoadmapController::project()` et `LotController::edit()`. Une semaine invalide retombe sur « Projets » au lieu d'une erreur.
- **Voters et rôles existants dans le gabarit** (`is_granted`) : ce sont exactement les contrôles de `project/show.html.twig` et `project/_lot_row.html.twig` actuels, déplacés sans être modifiés. Le contrôleur ne transmet aucun drapeau de droits.
- **Méthodes de modèle sur `ProjectSummary` / `LotSummary`** : même forme que `LotSummary::projectedGapQuarters()` et `progressPercent()`. Elles sont testables en unitaire et tiennent le gabarit hors du calcul.
- **`<details>` natif pour le journal** : les mois au-delà des trois plus récents sont repliés sans JavaScript ni requête. L'élément est accessible au clavier. Le paramètre optionnel `recent_months` de `roadmap/_timeline.html.twig` laisse la fiche d'une personne inchangée.
- **Champ caché `roadmap` sur les formulaires de la page** (avancement, suppression d'un lot) : `LotController::progress()` et `delete()` le relisent et redirigent avec. Le lien « Roadmap » survit ainsi à une action sur la page (question ouverte du pitch, option recommandée (b)).
- **Contrôleur Stimulus `roadmap`** : une action `turbo:before-render@document->roadmap#hold` retient le centre visible de la frise, puis `turbo:morph@document->roadmap#refresh` réapplique le palier de zoom et recentre sur ce point après le rafraîchissement en morph qui suit une action, puisque le morph réécrit le `style` du cadre et le libellé du palier rendus par le serveur.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Garder la fiche sur `/roadmap/projets/{id}` et rediriger la page de gestion | Il faudrait changer la cible des 5 redirections de `LotController`, des 3 de `ProjectController` et des gabarits de formulaires. La page appartiendrait aussi à la section « Roadmap » du menu, alors que la story 017 range « Projets » dans « Pilotage ». |
| Deux gabarits, lecture et gestion, choisis selon le rôle | Ce serait une seconde source de vérité pour la même page, et un responsable de rôle prod aurait besoin d'un troisième cas. Les `is_granted` en place couvrent tous les cas dans un seul gabarit. |
| Calculer le total à terme dans le gabarit | Ce calcul porte une règle métier, le minimum quand un restant est inconnu, que le gabarit ne peut pas tester. La forme du projet est d'en faire une méthode de modèle. |
| Repli du journal par un contrôleur Stimulus ou une pagination serveur | La pagination ajoute une requête et une route ; Stimulus ajoute du code pour un comportement que `<details>` donne nativement. |
| `data-turbo-permanent` sur la frise | La frise resterait figée après une déclaration d'avancement, alors que la partie future d'un lot change avec l'avancement. Il faut au contraire qu'elle se mette à jour. |

## Modèle de données

Aucun impact modèle persistant. Deux modèles calculés gagnent des méthodes :

### Modification de `ProjectSummary` (non persisté)

`src/Model/ProjectSummary.php` :

| Méthode | Retour | Règle |
|---|---|---|
| `projectedQuarters()` | `int` | `enteredQuarters + remainingQuarters` : le dépassement est déjà dans le saisi, et le restant n'est jamais compensé (pitch, règles 7 et 9). |
| `projectedGapQuarters()` | `int` | `projectedQuarters() − estimateDays × Quarters::PER_DAY`. |
| `isProjectedAtLeast()` | `bool` | Vrai si une feuille du projet (lot sans sous-lot ou sous-lot) a un restant inconnu (pitch, règle 8). |

### Modification de `LotSummary` (non persisté)

`src/Model/LotSummary.php` : `isRemainingUnknown(): bool`, vrai pour une feuille dont le restant est nul alors que le travail n'est pas fini. C'est le cas d'une feuille sans avancement qui dépasse son estimation (`overrunQuarters > 0`), ou d'une feuille dont l'avancement en vigueur est sous 100 % et dont le restant est épuisé : c'est le signal « avancement à actualiser » de la story 012. Toujours faux pour un lot découpé, puisque `ProjectSummary` parcourt les feuilles.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `templates/project/_remaining.html.twig` | Le restant puis le dépassement d'une ligne du tableau (`consumption-remaining` / `consumption-overrun`), partagé par les lignes de lots et la ligne de cumul. |
| `templates/project/_lots_table.html.twig` | Le tableau unique : échelle partagée des règles (reprise de `_consumption.html.twig`, projeté compris), en-têtes Lot · Responsable · Estimation · Saisi · Restant ou dépassement · Avancement · Projeté · actions, lignes de lots et sous-lots, ligne de cumul `lot-total-row` en pied. |
| `tests/Unit/Model/ProjectSummaryTest.php` | `projectedQuarters()` et `projectedGapQuarters()` ; `isProjectedAtLeast()` vrai avec une feuille en dépassement sans avancement ou à avancement épuisé, faux sinon. |
| `tests/Unit/Model/LotSummaryTest.php` | `isRemainingUnknown()` : feuille dans l'estimation, en dépassement sans avancement, avec avancement en vigueur, avancement épuisé sous 100 %, terminée à 100 %. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Controller/ProjectController.php` | `show()` : injecter `RoadmapBuilder`, lire `?roadmap` (`Week::tryFromIso`), rendre `project/show.html.twig` avec `summary`, `page` (`buildProject($project, $this->isGranted('ROLE_LEAD'))`) et `back`. |
| `src/Controller/RoadmapController.php` | `project()` : redirection 301 vers `app_project_show` avec le paramètre `roadmap` s'il est une semaine valide ; retirer `ProjectRollup`, le `MapEntity` et le rendu. |
| `src/Controller/LotController.php` | `progress()` et `delete()` : relire un champ `roadmap` facultatif du formulaire et le reporter dans la redirection vers `app_project_show` quand c'est une semaine valide. |
| `src/Model/ProjectSummary.php` | Ajouter `projectedQuarters()`, `projectedGapQuarters()`, `isProjectedAtLeast()`. |
| `src/Model/LotSummary.php` | Ajouter `isRemainingUnknown()`. |
| `templates/project/show.html.twig` | Réécrit : en-tête de page (retour « Roadmap » vers la semaine ou « Projets », actions du projet pour `ROLE_LEAD`, signaux de roadmap et de `_signals`) ; synthèse (projeté en chiffre de tête, « au moins » et sa raison, « Estimation partielle » et les lots à estimer quand il en reste (`project-page-projected-partial`), écart, saisi, restant, dépassement, phrase de non-compensation, début, fin calculée et `_unknown_ends`, règle graduée) ; « Ajouter un lot » ; tableau ; frise ; journal replié ; avancements déclarés ; projet non découpé. `data-test` de la fiche conservés (`project-page-*`). |
| `templates/project/_lot_row.html.twig` | Ligne fusionnée : titre et description, responsable (« à désigner », « à redésigner », « vous »), estimation et initiale, saisi avec la règle (`projected` sur une feuille), restant ou dépassement (`consumption-remaining` / `consumption-overrun`), avancement (`_progress.html.twig`), projeté (`consumption-projected`), actions. `data-test="lot-row"`, `data-title`, `data-mine` conservés. |
| `templates/project/_progress.html.twig` | Lecture alignée sur l'ancienne fiche (« 40 % » puis « au 28/09 », « — » sans avancement) ; ajouter le champ caché `roadmap` au formulaire quand la page en a un. |
| `templates/roadmap/_timeline.html.twig` | Paramètre optionnel `recent_months` : au-delà, les mois sont rendus dans un `<details data-test="timeline-earlier">` dont le résumé est « Afficher les mois précédents (N) », puis « Masquer » une fois dépliés. Sans le paramètre, le rendu est inchangé. Lien `with_project` vers `app_project_show`. |
| `templates/roadmap/index.html.twig` | Icône « Ouvrir la fiche » (ligne 65) vers `app_project_show` avec `roadmap`. |
| `templates/person/show.html.twig` | Les deux liens vers un projet (lignes 97 et 196) vers `app_project_show`. |
| `templates/person/_upcoming.html.twig` | Lien du projet (ligne 14) vers `app_project_show`. |
| `assets/controllers/roadmap_controller.js` | Méthodes publiques `hold()` (retient le centre visible, sur `turbo:before-render@document`) et `refresh()` (réapplique `#render()` et recentre sur ce point, sur `turbo:morph@document`), branchées par l'élément de la frise de la page projet. |
| `templates/roadmap/project.html.twig` | Supprimé (contenu repris par `project/show.html.twig`). |
| `templates/roadmap/_consumption.html.twig` | Supprimé (remplacé par `project/_lots_table.html.twig`). |
| `templates/roadmap/_consumption_row.html.twig` | Supprimé (absorbé par `project/_lot_row.html.twig`). |
| `tests/Controller/ProjectControllerTest.php` | `project-heading` → `project-page-heading`, `project-description` → `project-page-description`, `lot-estimate-cell` → `consumption-estimate`, saisi et dépassement lus dans leurs cellules ; `testProdBrowsesProjectsButCannotManageThem` vérifie aussi l'absence de `lot-new`, `lot-edit`, `lot-delete`, `progress-form` ; la page de détail garde son plafond de requêtes constant (ligne 165). |
| `tests/Controller/RoadmapProjectControllerTest.php` | `url()` → `/projets/{id}` ; test des rôles réécrit (prod sans action, responsable de rôle prod avec ses deux actions, lead et direction avec toutes) ; menu `nav-projects` ; nouveau test de redirection 301 de `/roadmap/projets/{id}?roadmap=…` ; helper `consumption()` sur `lot-row` / `lot-total-row` ; total à terme et « au moins » ; repli du journal ; retour « Projets » sans origine. |
| `tests/Controller/LotProgressControllerTest.php` | Nouveau cas : déclaration envoyée avec `roadmap` → redirection vers `/projets/{id}?roadmap=…` ; les autres cas sont inchangés (les redirections visent déjà `/projets/{id}`). |
| `tests/Controller/LotControllerTest.php` | Suppression d'un lot avec `roadmap` → redirection qui le conserve. |
| `tests/e2e/roadmap.spec.ts` | Scénario « l'icône d'un projet ouvre sa fiche » : URL `/projets/\d+?roadmap=…` et `nav-projects` courant. |
| `tests/e2e/progress.spec.ts` | Plus de `goto('/roadmap/projets/…')` : le restant, l'avancement et le projeté se lisent sur la ligne `lot-row` de la même page après la déclaration ; la frise est vérifiée sans changer de page, après le morph. |
| `tests/e2e/projects.spec.ts` | `project-heading` → `project-page-heading`, `lots-empty` → `project-page-unsplit`, `project-total` → `project-page-estimate`. |
| `tests/Controller/VocabularyTest.php` | `/roadmap/projets/{atlas}` retiré des pages parcourues : il répond désormais en 301. |
| `tests/Controller/PersonControllerTest.php` | Les liens vers un projet visent `/projets/{id}`. |
| `DESIGN.md` | §Fiches : « Fiche projet » et « Page projet d'administration » fusionnées en une entrée « Page projet » (fait par `/forge:sync` après livraison). |

## Hors scope

- **Vocabulaire des libellés** : la page emploie les termes de la story 015, livrée avant. Si elle ne l'est pas, les libellés actuels sont repris tels quels et réalignés par 015.
- **Lien de la frise vers la modification d'une feuille** (`roadmap/_row.html.twig`, paramètre `links`) : toujours désactivé sur la page projet (`links: false`).
- **Optimisation de `buildProject()`** (planification de toutes les feuilles à chaque affichage) : coût connu depuis la story 010. Ce n'est pas un irritant mesuré, il n'y a donc pas de cache.
- **Retour « Roadmap » après un passage par un écran de modification** (`app_lot_edit`, `app_project_edit`, `app_lot_new`) : non embarqué (option (b) du pitch). `app_lot_edit` garde son retour vers la semaine de roadmap quand il est ouvert depuis la roadmap.
- **Repli du journal de la fiche d'une personne** : `recent_months` n'est pas passé par `person/show.html.twig`.
- **Suppression de la route `app_roadmap_project`** : elle reste, en redirection, pour les signets.

## Impacts transverses

- **Cloisonnement des données** : sans objet (une seule organisation). La page n'expose rien que l'une des deux pages n'exposait déjà à toute personne connectée.
- **Déclinaisons / thèmes** : non. Tokens du design system « Cadence », en clair comme en sombre. Le tableau reste dans `TableCard`, qui défile horizontalement.
- **Traduction / i18n** : libellés en français dans les gabarits (« projetés », « au moins », « Afficher les mois précédents »), comme le reste de l'application.
- **API / exposition externe** : non.
- **Droits d'accès** : mécanismes existants suffisants (`ROLE_LEAD` sur `app_project_edit`, `app_project_delete`, `app_lot_new`, `app_lot_new_sub_lot`, `app_lot_delete` ; `LotVoter::EDIT` et `PROGRESS`). Seuls les gabarits changent, et chaque action reste refusée côté serveur à qui n'a pas le droit.
- **Emails / notifications** : non.
- **Migration de données** : aucune.
- **Comportement par défaut** : la page unique remplace les deux pages pour tout le monde dès la mise en service. L'ancienne adresse redirige.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `tests/Unit/Model/ProjectSummaryTest.php` | unit | Projeté = saisi + restant ; écart positif, négatif, nul ; « au moins » avec une feuille en dépassement sans avancement, avec une feuille à avancement épuisé, et pas avec une feuille en dépassement dont l'avancement en vigueur laisse du restant. |
| `tests/Unit/Model/LotSummaryTest.php` | unit | `isRemainingUnknown()` sur les cinq cas de feuille ; faux pour un lot découpé. |
| `tests/Controller/RoadmapProjectControllerTest.php` | functional (HTTP) | Redirection 301 avec et sans `roadmap`, 404 conservé ; retour « Roadmap » vers la semaine et « Projets » sans origine ; `nav-projects` courant ; actions par rôle (prod, responsable prod, lead, direction) ; tableau unique et cumul séparé ; total à terme, écart et « au moins » ; repli au-delà de trois mois ; nombre de requêtes constant. |
| `tests/Controller/ProjectControllerTest.php` | functional (HTTP) | La prod ne voit aucune action ; création et renommage mènent à la page (`project-page-heading`) ; nombre de requêtes de la page indépendant du nombre de lots. |
| `tests/Controller/LotProgressControllerTest.php` | functional (HTTP) | Déclaration depuis la page, redirection qui conserve `roadmap` ; refus inchangés. |
| `tests/Controller/LotControllerTest.php` | functional (HTTP) | Suppression d'un lot avec `roadmap` conservé dans la redirection. |
| `tests/e2e/roadmap.spec.ts` | E2E | Icône de la roadmap → page du projet, menu « Projets », retour à la même semaine. |
| `tests/e2e/progress.spec.ts` | E2E | Déclaration d'avancement puis lecture du restant, du projeté et de l'infobulle de la frise sur la même page ; palier de zoom cohérent après le morph. |

**Hors scope tests** :

- Pas de test E2E du repli du journal : le `<details>` natif est couvert par le test fonctionnel (mois visibles, mois repliés, libellé).
- Pas de test de non-régression visuelle du tableau élargi : le défilement horizontal est celui de `TableCard`, déjà éprouvé ; vérification manuelle sur téléphone.
- Pas de nouveau test des voters : `LotVoter` est inchangé et déjà couvert par `LotControllerTest` et `LotProgressControllerTest`.

## Ordre d'exécution

1. [x] **Projeté du projet et restant inconnu**
   - Objectif : `projectedQuarters()`, `projectedGapQuarters()`, `isProjectedAtLeast()`, `isRemainingUnknown()`.
   - Fichiers : `src/Model/ProjectSummary.php`, `src/Model/LotSummary.php`, `tests/Unit/Model/ProjectSummaryTest.php`, `tests/Unit/Model/LotSummaryTest.php`.
   - Vérification : `make phpunit-filter 'ProjectSummaryTest|LotSummaryTest'`.
   - Commitable seule : oui.

2. [x] **Page unique rendue par `app_project_show`**
   - Objectif : `show()` charge la frise, la timeline et la synthèse ; `project/show.html.twig` reprend la fiche et y ajoute les actions du projet ; nouvel en-tête du total à terme ; retour selon `roadmap`.
   - Fichiers : `src/Controller/ProjectController.php`, `templates/project/show.html.twig`, `tests/Controller/ProjectControllerTest.php`.
   - Vérification : `make phpunit-filter ProjectControllerTest` ; contrôle à l'œil sur Atlas avec les trois rôles.
   - Commitable seule : non (les deux pages coexistent encore, la fiche de la roadmap restant servie à l'ancienne adresse).

3. [x] **Tableau unique des lots**
   - Objectif : fusion des lignes et du tableau, ligne de cumul, avancement en lecture ou en formulaire selon `LOT_PROGRESS`.
   - Fichiers : `templates/project/_lots_table.html.twig`, `templates/project/_lot_row.html.twig`, `templates/project/_progress.html.twig` ; suppression de `roadmap/_consumption.html.twig` et `roadmap/_consumption_row.html.twig`.
   - Vérification : `make phpunit-filter 'ProjectControllerTest|LotProgressControllerTest'`.
   - Commitable seule : non.

4. [x] **Redirection de l'ancienne fiche et liens**
   - Objectif : 301 depuis `/roadmap/projets/{id}`, liens de la roadmap et de la fiche d'une personne vers la page, champ `roadmap` sur les formulaires de la page et dans les redirections de `LotController`.
   - Fichiers : `src/Controller/RoadmapController.php`, `src/Controller/LotController.php`, `templates/roadmap/index.html.twig`, `templates/roadmap/_timeline.html.twig` (lien), `templates/person/show.html.twig`, `templates/person/_upcoming.html.twig` ; suppression de `templates/roadmap/project.html.twig` ; `tests/Controller/RoadmapProjectControllerTest.php`, `LotProgressControllerTest.php`, `LotControllerTest.php`.
   - Vérification : `make phpunit-filter 'RoadmapProjectControllerTest|LotProgressControllerTest|LotControllerTest|NavigationTest|PersonControllerTest'`.
   - Commitable seule : oui, avec les étapes 2 et 3.

5. [x] **Journal replié**
   - Objectif : `recent_months: 3` sur la page projet ; fiche d'une personne inchangée.
   - Fichiers : `templates/roadmap/_timeline.html.twig`, `templates/project/show.html.twig`, `tests/Controller/RoadmapProjectControllerTest.php`.
   - Vérification : `make phpunit-filter 'RoadmapProjectControllerTest|PersonControllerTest'`.
   - Commitable seule : oui.

6. [x] **Zoom de la frise après un morph, et E2E**
   - Objectif : `refresh()` sur `turbo:morph` ; scénarios E2E migrés.
   - Fichiers : `assets/controllers/roadmap_controller.js`, `tests/e2e/roadmap.spec.ts`, `tests/e2e/progress.spec.ts`, `tests/e2e/projects.spec.ts`.
   - Vérification : `make serve` puis `make playwright-file tests/e2e/progress.spec.ts`, `roadmap.spec.ts`, `projects.spec.ts`, `person.spec.ts`.
   - Commitable seule : oui.

7. [x] **QA finale**
   - Objectif : suite complète et qualité.
   - Fichiers : aucun.
   - Vérification : `make lint`, `make phpunit`, `make playwright`.
   - Commitable seule : oui.

## Critères de sortie

- [x] `GET /roadmap/projets/{id}` répond 301 vers `/projets/{id}`, avec `?roadmap=` conservé ; un projet inconnu reste un 404.
- [x] Plus aucun gabarit ne génère `app_roadmap_project` (`grep` vide hors `RoadmapController`).
- [x] `templates/roadmap/project.html.twig`, `roadmap/_consumption.html.twig` et `roadmap/_consumption_row.html.twig` n'existent plus.
- [x] Chaque `data-test` d'action de l'ancienne page de gestion (`project-edit`, `project-delete`, `lot-new`, `lot-edit`, `lot-new-sub-lot`, `lot-delete`, `progress-form`) est présent sur la page pour le rôle qui y avait droit, et absent pour les autres.
- [x] Le nombre de requêtes de `/projets/{id}` ne croît ni avec le nombre de lots ni avec le nombre de feuilles (tests de `ProjectControllerTest` et `RoadmapProjectControllerTest` conservés).
- [x] Suite de tests du projet verte, sans nouvelle régression (`make phpunit`, `make playwright`).
- [x] `make lint` propre (PHP-CS-Fixer et PHPStan level 10).

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Une action ou une information de l'ancienne page de gestion disparaît à la fusion (modale de suppression bloquée par des temps, « vous », estimation initiale, description d'un lot) | moyenne | Liste des `data-test` des deux pages contrôlée au critère de sortie ; tests existants de `ProjectControllerTest` et `LotControllerTest` conservés tels quels. |
| Une action apparaît à qui n'y a pas droit | faible | Les `is_granted` sont déplacés sans modification ; test des quatre profils (prod, responsable prod, lead, direction) ; les routes gardent leurs contrôles serveur. |
| Lien mort ou signet cassé vers l'ancienne fiche | faible | Redirection 301 testée ; `grep` de `app_roadmap_project` au critère de sortie. |
| Le rafraîchissement en morph désynchronise le palier de zoom de la frise après une action | moyenne | `refresh()` sur `turbo:morph` ; scénario E2E de `progress.spec.ts` qui zoome, déclare, puis vérifie le palier affiché et la largeur. |
| Budget de requêtes de la page de gestion alourdi par `buildProject()` | élevée (attendu) | Coût constant, déjà accepté sur la fiche (story 010) ; les tests « ne croît pas » restent la garde. Le plafond absolu n'est pas figé, la page n'en ayant pas. |
| Tableau à huit colonnes trop large | moyenne | `TableCard` défile horizontalement ; colonnes `whitespace-nowrap` limitées aux chiffres ; vérification manuelle à 390 px. |
| Collision de `data-test` entre l'ancienne fiche et l'ancienne page de gestion | moyenne | Convention fixée au plan : en-tête et synthèse `project-page-*`, lignes `lot-row`, cellules chiffrées `consumption-*`, cumul `lot-total-row`. |

## Questions ouvertes

- **Forme de la redirection de l'ancienne adresse** : Options : (a) action de contrôleur qui redirige, avec `MapEntity` (404 pour un projet inconnu, paramètre `roadmap` filtré) ; (b) route de redirection déclarée en configuration (`RedirectController`), sans contrôle d'existence. Recommandé : (a). **Tranché : (a)**, le projet résolu par son id.
- **Nom du fichier de gabarit** : `project/show.html.twig` réécrit, ou un nouveau gabarit avec suppression de l'ancien. Recommandé : réécrire `project/show.html.twig`, déjà rendu par `app_project_show`, pour garder l'historique git de la page de gestion. **Tranché : réécrit.**
