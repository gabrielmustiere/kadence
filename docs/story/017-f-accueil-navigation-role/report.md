# Report — Voir à l'accueil ce qui attend chacun, et trouver dans le menu le quotidien, le pilotage et l'administration

> **But** : garder la trace de ce qui a dérivé entre l'intention et le livré, et de ce qui reste à faire.
> **Registre** : factuel
> **Story** : `docs/story/017-f-accueil-navigation-role/`
> **Amont** : `pitch.md` · `plan.md`

## Synthèse

- **Écarts** : 12 — 11 réalignés dans `plan.md`/`pitch.md`, 1 laissé tel quel.
- **Critères** : 19 / 19 atteints, cochés dans `pitch.md` ; 8 / 8 critères de sortie cochés dans `plan.md`.
- **Non livré** : aucun.
- **Dette** : aucune.

Deux règles de l'accueil ont été précisées par la review : un lot déclaré terminé et une demande validée d'office n'y remontent plus. Un modèle `WatchedProject` porte les pastilles d'un projet à surveiller, que la ligne projet de la roadmap ne porte pas.

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison | Réaligné dans |
|---|---|---|---|
| `watchedProjects` : `list<RoadmapRow>` | `list<WatchedProject>` (ligne projet + pastilles retenues) | Les pastilles d'un projet à surveiller viennent de ses feuilles, qu'une ligne projet ne porte pas (implémentation) | `plan.md` §Approche retenue, §Modèle de données |
| Pastilles « de ce qui le signale » | Signaux de surveillance des feuilles, plus ceux qui retiennent la fin d'une feuille à fin inconnue ; fin calculée ou « Fin inconnue » en regard | Choix d'implémentation pour dire pourquoi la fin est inconnue | `plan.md` §Approche retenue ; `pitch.md` §Règles métier 15.4 |
| « Mes lots » et « Projets à surveiller » sur les signaux de la roadmap | Feuilles déclarées terminées écartées | Review [BUG] « Un lot terminé à 100 % en dépassement ne quitte jamais l'accueil » : `Overrun` reste levé sur un lot terminé | `plan.md` §Approche retenue, §Modèle de données ; `pitch.md` §Règles métier 15.3 et 15.4 |
| « Mes congés » : demandes décidées depuis moins de 7 jours | Demande validée d'office (sans valideur) écartée | Review [BUG] « Une demande validée automatiquement remonte sept jours comme décidée par un autre », arbitrée par l'utilisateur : l'écarter | `plan.md` §Modèle de données ; `pitch.md` §Règles métier 15.5 |
| `TeamList::isFiltered()` inchangé | Décidé sur le filtre effectif | Review [ROBUSTESSE] « Tout afficher » s'affichait sans filtre appliqué | `plan.md` §Périmètre |
| Bouton « Fiche » conservé, `data-email` sur chaque ligne | Le nom mène à la fiche ; `data-email` pour la direction seule, `data-member` pour tous | Ne pas exposer l'e-mail dans le HTML hors direction (implémentation) | `plan.md` §Périmètre |
| `TimesheetControllerTest` ligne 82 : assertion retirée | Test `testTheDashboardOffersTheTimesheet` supprimé | Vide sans son assertion ; le lien vers la semaine est couvert par `HomeControllerTest` (implémentation) | `plan.md` §Périmètre |
| Libellé « Accueil » et `nav-home` en tranche 4 | Posés dès la tranche 1 ; l'ancien accueil repointé sur `app_person_index` en tranche 2 | Le menu était restructuré en tranche 1 ; l'ancien accueil devait rester rendu jusqu'à sa réécriture (implémentation) | — |
| `home.spec.ts` sur les données des fixtures | Personne sans aucun temps créée puis purgée en SQL | Les fixtures tirent au hasard les journées oubliées (`DemoCompanyFixtures::dayCapacity()`) | `plan.md` §Périmètre |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Réalignement de `DESIGN.md` (menu en trois zones, accueil, liste Personnes) | `DESIGN.md` ne décrit ni le menu, ni l'accueil, ni la liste des personnes : rien à réaligner | aucune |
| Réalignement du changelog de la story 005, dont des décisions sont renversées | Hors du dossier de cette story ; le renversement est tracé dans le `metadata.json` de la story 017 (entrée « Création ») | aucune |

### Ajouts non prévus

| Élément ajouté | Raison | Réaligné dans |
|---|---|---|
| `src/Model/Home/WatchedProject.php` ; variable `signals` de `_signal_tooltips.html.twig` | Voir l'écart sur `watchedProjects` | `plan.md` §Périmètre |
| Libellés « Équipe » réalignés : description de la page Tags, message de `UniqueTeamEmail`, retour du mot de passe provisoire | Ils nommaient une page qui n'existe plus (implémentation) | `plan.md` §Périmètre |
| `VocabularyTest`, `login.spec.ts`, `logout-prefetch.spec.ts`, `PersonControllerTest::testPageOfThePitch` ajustés | Ils visaient `/equipe`, lisaient « Tableau de bord » dans le menu, ou attendaient aucune entrée active sur la fiche (implémentation) | `plan.md` §Périmètre |

## Dette technique identifiée

_(aucune)_

## Leçons apprises

- **Un signal de la roadmap n'est pas une action à faire** : `Overrun` reste levé sur un lot déclaré terminé, et `Completed` peut l'accompagner. Toute vue qui dérive une liste de choses à faire des signaux doit écarter `Completed`.
- **Les fixtures tirent les journées oubliées au hasard** : un scénario E2E qui a besoin d'une journée incomplète crée sa propre personne en SQL plutôt que de compter sur `prod@example.com`.
- **Un Live Component ouvert à plusieurs rôles se décide côté serveur** : colonnes, filtres et actions à partir du rôle, jamais des props, avec des tests qui forgent les props interdites.
- **Mesurer le nombre de requêtes après une requête de chauffe** : la première requête d'un `WebTestCase` partage le gestionnaire d'entités du test et fausse le compte.
