# Report — Consulter la fiche d'une personne : sa charge, ses affectations à venir et la timeline de tout son travail saisi

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/011-f-fiche-collaborateur/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 95 % — les six étapes sont livrées selon l'approche prévue. Écarts structurants : la liste des fins inconnues porte des lignes de feuille au lieu de lots, et deux factorisations non prévues (`LeafPath`, `RoadmapRunCutter::split()`) ; la légende de la frise est partagée par paramétrage de `_legend.html.twig` (mineur ARCHI de la review).
- **Critères** : 17 / 17 cochés.
- **Review** : 0 bloquant, 0 important, 3 mineurs, tous corrigés pendant la passe ; statut PRÊT À COMMITER.
- **Périmètre livré** : 18 fichiers créés (~1 570 lignes, tests compris, hors documents de story), 15 fichiers modifiés (+188 / −45 lignes).

La fiche d'une personne est livrée sous `/personnes/{id}`, ouverte à toute personne connectée en lecture seule, sans entrée de menu active. Elle comprend un en-tête (temps par projet ; profil et charge selon `PersonVoter`), une frise d'une ligne par feuille avec la seule part de la personne et une ligne de charge réservée, le bloc « À venir » et le journal de ses tronçons. On y accède depuis les noms de la timeline d'une fiche projet, « Ma fiche » et la liste Équipe. La charge n'est calculée que pour un lecteur autorisé. Restent hors code le réalignement de la vision et la mise en service.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Service/PersonRoadmapBuilder.php` | `build(User, bool $withOverloads, bool $withLoad)` : planifie toutes les feuilles, retient celles de la personne (saisies ou membre) dans l'ordre de `LeafOrder`, découpe ses tronçons par `cutFor()`, borne la fenêtre, construit les lignes, « À venir » (trié par début, fins inconnues à la fin), la timeline et, si demandé et si la personne est active, sa charge. | Oui |
| `src/Model/Person/PersonRoadmap.php` | La fiche : `roadmap` sans ligne, `projects`, `upcoming`, `timeline`, `dated`, `load` ; `enteredProjects()` du plus récent au plus ancien. | Oui |
| `src/Model/Person/PersonProject.php` | Un projet de la personne : `Project`, lignes, temps saisi et dernier jour saisi. | Oui |
| `src/Model/Person/PersonLeafRow.php` | Une feuille de la personne : `lot`, `share`, `start`, `end`, `realized`, `overrun`, `future`, `signals` ; `leafPath()`, `isUpcoming()`. | Oui |
| `src/Model/Person/PersonLoad.php` | La charge : plages fusionnées (`merge()` puis placement), prochain jour ouvré et sa charge, `freeFrom`, `unknownEnds` (`list<PersonLeafRow>`). | Écart volontaire (cf. §Écarts) |
| `src/Model/Person/LoadSpan.php` | Une plage de charge : premier et dernier jour, pourcentage, `RoadmapBar`, `isOverload()`. | Oui |
| `src/Security/Voter/PersonVoter.php` | `PERSON_PLANNING` : `ROLE_LEAD` ou la personne elle-même ; `PERSON_MANAGER` : `ROLE_DIRECTION` ou la personne elle-même ; sujet `User`. | Oui |
| `src/Controller/PersonController.php` | `show()` : route `app_person` `GET /personnes/{id}`, `User` résolu par le résolveur d'entité par défaut, `build($person, isGranted('ROLE_LEAD'), isGranted(PERSON_PLANNING, $person))`. | Écart volontaire (cf. §Écarts) |
| `templates/person/show.html.twig` | En-tête (nom, « désactivée », profil selon le voter, charge ou « Désactivée, aucune charge », « libre à partir du … »), temps saisi par projet avec lien vers la fiche projet, frise (`_zoom`, `_legend` paramétrée, `_frieze` embarquée avec ligne de charge, projets et feuilles), « À venir », journal ; messages des cas vides. | Oui |
| `templates/person/_row.html.twig` | Ligne d'une feuille : `leafPath` et signaux, barres `realized`, `overrun` et `future` étiquetée de la part, via `_bar.html.twig` et `person/_tooltip.html.twig`. | Oui |
| `templates/person/_tooltip.html.twig` | Infobulle de tronçon (feuille, période, saisi, jours avec saisie), d'affectation (feuille, période, part) et de plage de charge (période, charge). | Oui |
| `templates/person/_upcoming.html.twig` | Bloc « À venir » : projet (lien) et feuille, période, part « de sa capacité » et signaux de la feuille ; message sans affectation. | Écart volontaire (cf. §Écarts) |
| `tests/Unit/Model/Person/PersonLoadTest.php` | Six cas : plage fusionnée par-dessus le week-end et coupée sur un jour sans charge, surcharge, prochain jour ouvré (week-end, jour férié), `freeFrom` (y compris depuis un vendredi), fin inconnue, aucune charge. | Oui |
| `tests/Unit/Security/Voter/PersonVoterTest.php` | Huit cas : direction, lead et prod sur la fiche d'un autre pour les deux attributs, la personne sur sa propre fiche, abstention hors attribut et hors sujet `User`. | Oui |
| `tests/Service/PersonRoadmapBuilderTest.php` | Dix cas sur l'exemple du pitch et au-delà : lignes et tronçons de la personne seule, fenêtre, temps par projet, timeline, « À venir » et charge, fins inconnues (feuille en dépassement, feuille sans début), découpe sur ses seuls jours ouvrés, « à replanifier » sur les vues de planification, charge absente sans droit ou si désactivée, personne sans rien. | Oui |
| `tests/Controller/PersonControllerTest.php` | Onze cas : visibilité par rôle sur le HTML (rôle, tags, charge, ligne de charge, manager et son nom), fiche de la personne elle-même, page de l'exemple (en-tête, frise, « À venir », journal, liens vers les fiches projet, aucune entrée de menu active, lecture seule), surcharge réservée aux leads et mise en évidence, date « inconnue », personne désactivée, fiche vide, 404, nombre de requêtes constant. | Oui |
| `tests/e2e/person.spec.ts` | Nom de la timeline d'une fiche projet → fiche de la personne, infobulle d'un tronçon de sa frise, journal ; « Ma fiche » depuis le menu du compte. | Oui |
| `src/Model/Roadmap/LeafPath.php` | `of(Lot)` : « Lot · Sous-lot », ou le titre du lot ; partagé par `TimelineEntry` et `PersonLeafRow`. | Non (ajout — cf. §Écarts) |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Enum/Type/RoadmapSignal.php` | `of(LeafSchedule): list<self>`, le corps de l'ancienne `RoadmapBuilder::signalsOf()`. | Oui |
| `src/Service/RoadmapBuilder.php` | `signalsOf()` remplacée par `RoadmapSignal::of()`. | Oui |
| `src/Model/Schedule/ScheduleResult.php` | `loadsOf(int $userId)` : la charge de la personne par jour, triée. | Oui |
| `src/Repository/TimeEntryRepository.php` | `sumQuartersByLotAndDayForUser(int $userId)` : ses quarts par feuille et par jour, dans l'ordre des jours. | Oui |
| `src/Service/RoadmapRunCutter.php` | `cutFor(ScheduleData, int $userId)` : ses tronçons coupés sur ses seuls jours ouvrés ; `split()` extraite, partagée par `cut()` et `cutFor()`. | Oui |
| `templates/roadmap/_bar.html.twig` | Variable `tooltip_template`, `roadmap/_tooltip.html.twig` par défaut. | Oui |
| `templates/roadmap/_timeline.html.twig` | Variables `with_team`, `with_project` (titre du projet en lien avant la feuille) et `empty_message` ; noms de l'équipe en lien (`links: true`). | Oui |
| `templates/roadmap/_team.html.twig` | Variable `links` : nom en lien vers `app_person`, barré si la personne est désactivée. | Oui |
| `templates/base.html.twig` | Entrée « Ma fiche » (`tabler:file-description`, `data-test="nav-person"`) dans le menu du compte. | Oui |
| `templates/team/index.html.twig` | Bouton-icône `tabler:file-description` vers `app_person` dans les actions de chaque ligne (`data-test="member-person"`). | Oui |
| `tests/Controller/RoadmapProjectControllerTest.php` | Les noms de la timeline, « hors équipe » compris, mènent à `/personnes/{id}` ; aucun lien dans les infobulles. | Oui |
| `tests/Controller/TeamControllerTest.php` | Chaque ligne porte le lien vers la fiche de la personne, avec son `aria-label`. | Oui |
| `tests/Controller/NavigationTest.php` | Aucune entrée active sur `/personnes/{id}` ; nouveau test « Ma fiche » qui ouvre la fiche de la personne connectée. | Oui |
| `docs/story/011-f-fiche-collaborateur/pitch.md` | Annotations du plan : règles 1, 8 et 16, user story et comportement par défaut alignés, critère de la charge, questions « infobulles » et « téléphone » tranchées. | Oui |
| `src/Model/Roadmap/TimelineEntry.php` | `leafPath()` délègue à `LeafPath::of()`. | Non (ajout — cf. §Écarts) |
| `templates/roadmap/_legend.html.twig` | Variables `future_label` (« Restant, calculé » par défaut), `with_span` (vrai par défaut) et `with_overload` (faux par défaut). | Non (ajout — cf. §Écarts) |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| `PersonLoad::unknownEnds` en `list<Lot>` | `list<PersonLeafRow>` | Le template nomme chaque feuille par « Projet · Lot · Sous-lot » avec `leafPath()`, sans recomposer le chemin dans le template. |
| `User` résolu par `#[MapEntity]` dans `PersonController` | Résolveur d'entité par défaut, sans attribut | Aucune expression ni requête dédiée n'est nécessaire : la personne est chargée par son identifiant, son profil en lazy-load (nombre de requêtes constant, testé). |
| « À venir » : la période, ou le signal d'une feuille non planifiée | La période quand elle existe, et les signaux de la feuille dans tous les cas ; part suivie de « de sa capacité » | Une feuille planifiée peut porter un signal utile au lecteur (« à replanifier », « démarrage en retard ») ; mise en page reprise de celle du journal après relecture visuelle. |
| Réutilisation de `_legend.html.twig` (Mécanismes mobilisés), légende d'abord recopiée dans `person/show.html.twig` à l'implémentation | `_legend.html.twig` paramétrée (`future_label`, `with_span`, `with_overload`) et incluse par la fiche | Mineur de review **[ARCHI] Légende de la frise dupliquée dans la fiche d'une personne**, corrigé pendant la passe. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `src/Model/Roadmap/LeafPath.php` et `TimelineEntry::leafPath()` qui y délègue | Le libellé « Lot · Sous-lot » sert au journal et aux lignes de la fiche personne ; extraction plutôt que duplication. |
| `RoadmapRunCutter::split()` | Le partage entre l'estimation et le dépassement est commun à `cut()` et `cutFor()`. |
| Test `NavigationTest::testTheAccountMenuLeadsToMyPage()` | Le plan prévoyait de vérifier « Ma fiche » dans `NavigationTest` ; un test dédié suit le clic depuis le menu du compte. |
| Assertion sur la classe de la plage en surcharge (`PersonControllerTest`) | Mineur de review **[TEST] Mise en évidence de la surcharge non vérifiée**, corrigé pendant la passe. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Model/Person/PersonLoad.php` | unit | unit, 6 cas | Fait |
| `src/Security/Voter/PersonVoter.php` | unit | unit, 8 cas | Fait |
| `src/Service/PersonRoadmapBuilder.php` | functional (noyau) | functional, 10 cas | Fait — couverture étendue (« à replanifier » selon la vue) |
| `src/Service/RoadmapRunCutter.php` (`cutFor()`) | functional (via `PersonRoadmapBuilderTest`) | idem | Fait ; `cut()` couvert par `RoadmapBuilderTest`, inchangé et vert |
| `src/Enum/Type/RoadmapSignal.php` (`of()`) | functional (via `RoadmapBuilderTest` et `PersonRoadmapBuilderTest`) | idem | Fait |
| `src/Controller/PersonController.php` | functional | functional, 11 cas | Fait |
| `templates/roadmap/_timeline.html.twig`, `_team.html.twig` | functional | 1 cas dans `RoadmapProjectControllerTest` | Fait |
| `templates/base.html.twig`, `templates/team/index.html.twig` | functional | `NavigationTest` (cas étendu + 1 cas), `TeamControllerTest` (1 cas) | Fait |
| Parcours fiche projet → fiche personne | E2E | 2 scénarios dans `person.spec.ts` | Fait |
| Infobulle d'une affectation à venir | non détaillé au plan | **non vérifiée** sur le HTML de la fiche | Manque mineur — risque faible, le partiel `person/_tooltip.html.twig` est rendu par `_bar.html.twig` comme celui des tronçons |
| Marquage « au-delà de l'estimation » sur la fiche personne | couvert par le constructeur | tronçons au-delà vérifiés dans `PersonRoadmapBuilderTest`, rendu par les partiels partagés | Conforme |
| Visibilité par rôle en E2E | hors scope assumé | pas écrit | Conforme — portée par le voter, vérifiée en fonctionnel |
| Zoom de la fiche | hors scope assumé | pas écrit | Conforme — `roadmap_controller.js` avec `remember` à faux, couvert par la story 010 |
| Tactile | hors scope assumé | pas écrit | Conforme — comme les stories 009 et 010 |

Résultats consignés à l'implémentation : 433 tests PHPUnit et 44 E2E verts, lint propre. Après les corrections de la review : lint propre, `PersonControllerTest`, `RoadmapProjectControllerTest`, `RoadmapControllerTest`, `person.spec.ts` et `roadmap.spec.ts` verts ; suite complète non rejouée depuis (non mesuré).

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Sur une fiche projet, le nom d'une personne dans l'équipe d'une entrée de la timeline, « hors équipe » compris, ouvre sa fiche ; les infobulles n'ont pas de lien.
- [x] Dans l'administration de l'équipe, la ligne de chaque personne mène à sa fiche, et l'entrée « Ma fiche » du menu du compte ouvre la fiche de la personne connectée.
- [x] Un membre de prod qui ouvre la fiche d'Alice voit le temps par projet, la frise, le bloc « À venir » et le journal, mais ni son rôle, ni ses tags, ni son manager, ni sa charge, ni « libre à partir du … », ni la ligne de charge.
- [x] Alice voit tout sur sa propre fiche ; la direction voit tout sur toute fiche ; un lead voit tout sauf le manager. Aucune action de gestion n'y figure.
- [x] Dans l'exemple, l'en-tête liste « Refonte » avec 10 j saisis, puis « Support » avec 3 j saisis, sans total ni moyenne.
- [x] Dans l'exemple, pour un lead, l'en-tête affiche une charge de 50 % au prochain jour ouvré, le lundi 05/10, et « libre à partir du 27/10 », et la ligne de charge est à 50 % du lundi 05/10 au lundi 26/10.
- [x] Si Alice fait aussi partie de l'équipe d'une feuille sans début, « libre à partir du … » vaut « inconnue » et nomme cette feuille.
- [x] Quand la charge d'une personne dépasse 100 % (surcharge passive), la ligne de charge met ces jours en évidence pour un lead, la direction et la personne elle-même.
- [x] Dans l'exemple, la frise montre sous « Refonte » la ligne « API » avec deux tronçons (07/09 au 11/09, 21/09 au 25/09), sous « Support » la ligne « Maintenance » avec un tronçon (14/09 au 16/09), et sous « Mobile » la ligne « Front · Login » avec une affectation à 50 % du 05/10 au 26/10.
- [x] Dans l'exemple, la frise va du lundi 07/09 au dimanche 01/11, s'ouvre à ×1 et se zoome jusqu'à ×8 comme celle d'une fiche projet.
- [x] Survoler un tronçon de la frise affiche la feuille, la période, le temps saisi par la personne et ses jours avec saisie ; survoler une affectation affiche la feuille, la part, le début et la fin calculée.
- [x] Dans l'exemple, le bloc « À venir » liste « Mobile · Front · Login », 50 %, du 05/10 au 26/10.
- [x] Dans l'exemple, le journal présente, sous l'intertitre « Septembre 2026 » et dans cet ordre : « Refonte · API » du 21/09 au 25/09 avec 5 j saisis et 5 jours avec saisie ; « Support · Maintenance » du 14/09 au 16/09 avec 3 j saisis ; « Refonte · API » du 07/09 au 11/09 avec 5 j saisis.
- [x] Un tronçon de la personne situé au-delà de l'estimation de sa feuille est marqué comme tel dans la frise et dans le journal.
- [x] Les noms de projets de la fiche mènent à la fiche du projet ; les titres des lots et des feuilles ne sont pas des liens.
- [x] La fiche d'une personne désactivée reste accessible, marquée « désactivée », avec tout son historique.
- [x] Une personne sans saisie ni affectation a une fiche sans frise, avec un message dans le bloc « À venir » et dans le journal.

Les projets de la frise suivent l'ordre de la roadmap (« Mobile », « Refonte », « Support »), le critère ne fixant pas d'ordre. Le zoom de la fiche et l'infobulle d'une affectation sont livrés par réutilisation des partiels et du contrôleur de la fiche projet, sans test dédié (cf. §Tests).

## Dette technique identifiée

Issus de la review (mineurs non traités) :

_(aucun — les trois mineurs ont été corrigés pendant la passe)_

Au-delà de la review :

1. **Vision non réalignée** — le pitch contredit le principe 2 et l'anti-objectif « évaluation individuelle » ; `/vision` est à lancer. **Critique** avant la mise en service, avec l'information des salariés et, selon l'effectif, la consultation du CSE (question ouverte « Mise en service » du pitch).
2. **Backlog sans ligne pour la story et règle transverse « pas de lecture individuelle » dépassée** — la story est cadrée hors backlog ; la règle dit encore que le détail des temps d'une personne n'est visible que par elle-même. À reprendre par `/forge:sync`.
3. **« Libre à partir du … » inconnue tant qu'une ancienne feuille en dépassement garde la personne dans son équipe** — comportement voulu par la règle 8 ; observé sur les données de démonstration. À reprendre avec la clôture d'une feuille (ligne `jalons-dates-annoncees`).
4. **Infobulle d'une affectation non vérifiée sur le HTML de la fiche** — ajouter une assertion sur `person-tooltip-future` (part, période) dans `PersonControllerTest` si le partiel évolue.

## Leçons apprises

- **Lire la sémantique du planificateur avant d'écrire une règle sur « aujourd'hui »** : la partie à venir d'une feuille commence au plus tôt demain, donc une « charge d'aujourd'hui » vaut toujours 0 %. Le pitch a dû être amendé au plan (règle 8).
- **Une infobulle ne porte pas d'élément interactif** : `pointer-events-none`, `role="tooltip"` et fermeture au départ du déclencheur rendent un lien inatteignable. Toute demande de lien dans une infobulle se tranche au cadrage.
- **Une donnée réservée se protège à la source** : ne pas calculer la charge sans droit, puis tester l'absence des `data-test` et des valeurs dans le HTML de chaque rôle, plutôt que de masquer dans le template.
- **Un partiel partagé se paramètre avec des valeurs par défaut égales au rendu existant** (`_bar`, `_timeline`, `_team`, `_legend`) : les tests existants de la roadmap et de la fiche projet, inchangés, servent de garde.
- **La fenêtre bornée de la story 010 est un bon point d'extension** : `RoadmapWindow::spanning()`, `minTrackRem()` et `_frieze.html.twig` ont servi tels quels à une frise par personne.
