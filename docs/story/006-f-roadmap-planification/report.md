# Report — Planifier chaque feuille avec une date de début et une équipe pour dessiner la chronologie des projets sur une roadmap

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/006-f-roadmap-planification/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 90 %. Écarts structurants :
  - la reprise des responsables passe par une migration de données séparée ;
  - le chargeur émet 6 à 7 requêtes constantes au lieu de 4 ;
  - le signal « estimation épuisée » est scindé en « estimation atteinte » et « en dépassement », à la demande de l'utilisateur après l'implémentation.
- **Critères** : 21 / 21 cochés. Le critère sur la part par défaut était manqué ; il a été constaté puis corrigé pendant cette passe.
- **Review** : 0 bloquant, 1 important et 6 mineurs, tous résolus. Statut : PRÊT À COMMITER.
- **Périmètre livré** : 37 fichiers créés (~2 780 lignes), 25 fichiers modifiés (+830 / −33), hors documents de story.

La planification (date de début, équipe et parts) se pose dans le formulaire du lot. Une chronologie calculée, jamais stockée, alimente la page `/roadmap` et le contrôle de surcharge. Tout ce que le plan prévoyait est livré. Les écarts tiennent à des contraintes d'outillage (migration, contrainte `Unique`, classe finale non simulable), aux retours de la revue et à la redéfinition du dépassement. La dette porte sur ce que d'autres lignes du backlog doivent apporter : clôture, absences, consommé face à l'estimé.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Entity/LotMember.php` | Membre d'une feuille avec sa part de capacité (`SHARES` = 25/50/75/100), unique par feuille et personne. | Oui |
| `migrations/Version20260930123737.php` | Table `lot_member` (FK, index, unicité) et colonne `lot.start_date`, générées par `make:migration`. | Écart volontaire (cf. §Écarts) |
| `migrations/Version20260930123747.php` | Migration de données : chaque feuille avec responsable le reçoit dans son équipe à 100 % ; `down()` sans effet, documenté. | Non (ajout — cf. §Écarts) |
| `src/Dto/LotMemberInput.php` | Ligne d'équipe : personne et part (`NotNull`, `Choice`), normaliseur d'unicité `identify()`. | Oui |
| `src/Form/LotMemberType.php` | Sous-formulaire d'une ligne : personne parmi l'option `people` (actifs et membres désactivés présents), part de 25 à 100 %. | Oui |
| `assets/controllers/form_collection_controller.js` | Ajoute une ligne depuis le prototype, en retire une. | Oui |
| `src/Model/Schedule/PlannedMember.php` | Membre vu par le moteur : id de personne et part. | Écart volontaire (cf. §Écarts) |
| `src/Model/Schedule/LeafPlan.php` | Entrée du moteur pour une feuille, avec `withPlanning()` pour le contrôle avant/après. | Oui |
| `src/Model/Schedule/LeafSchedule.php` | Sortie par feuille : parties réalisée et future, restant, manques, démarrage en retard, estimation atteinte ou dépassée, équipe à revoir. | Oui |
| `src/Model/Schedule/ScheduleResult.php` | Chronologies, charge par personne et par jour, feuilles contributrices, feuilles en surcharge. | Oui |
| `src/Model/Schedule/DailyCapacity.php` | Capacité en unités entières (1/6000 de quart), répartie uniformément sur les jours non fériés. | Oui |
| `src/Model/Schedule/ScheduleData.php` | Données chargées : feuilles, plans, personnes, capacité, date du jour, jours de dépassement. | Non (ajout — cf. §Écarts) |
| `src/Service/Scheduler.php` | Moteur pur : futur réparti jour ouvré après jour ouvré, charge, horizon de 3 ans. | Oui |
| `src/Service/ScheduleLoader.php` | Chargement en requêtes constantes, construction des plans, de la capacité et des jours de dépassement ; plage de jours fériés extensible à une date saisie. | Écart volontaire (cf. §Écarts) |
| `src/Validator/PlanningFitsCapacity.php` | Contrainte de classe sur `LotInput`. | Oui |
| `src/Validator/PlanningFitsCapacityValidator.php` | Refuse le geste qui crée ou aggrave une surcharge, à estimation égale ; sort sans charger si la planification ne change pas. | Oui |
| `src/Model/Roadmap/RoadmapWindow.php` | Fenêtre de `WEEK_COUNT` = 41 semaines (−4 / +36), pas de 4, barres en % coupées aux bords. | Oui |
| `src/Model/Roadmap/RoadmapRow.php` | Ligne de frise : début, fin (ou inconnue), dernier jour, barres (cumul, saisi, dépassement, futur), détails, signaux, enfants. | Oui |
| `src/Model/Roadmap/RoadmapBar.php` | Barre positionnée en % avec indicateurs de coupe. | Non (ajout — cf. §Écarts) |
| `src/Model/Roadmap/Roadmap.php` | Modèle de page : fenêtre, date du jour, projets, position du repère « aujourd'hui », mois de l'en-tête. | Non (ajout — cf. §Écarts) |
| `src/Enum/Type/RoadmapSignal.php` | Signaux de la roadmap, avec libellé et variante de badge. | Non (ajout — cf. §Écarts) |
| `src/Service/RoadmapBuilder.php` | Assemble projets, lots et feuilles ; surcharge calculée seulement si demandée ; jours saisis au-delà de l'estimation. | Oui |
| `src/Controller/RoadmapController.php` | `GET /roadmap` (`app_roadmap`) et `GET /roadmap/{week}` (`app_roadmap_week`), 404 sur une semaine inexistante. | Oui |
| `templates/roadmap/index.html.twig` | Page : navigation, légende, mois, repère aujourd'hui, projets en `<details>`, variable `--roadmap-weeks`. | Oui |
| `templates/roadmap/_row.html.twig` | Libellé, lien vers la feuille, détails, barres, signaux, récursion. | Oui |
| `templates/roadmap/_bar.html.twig` | Barre avec fondu sur un bord coupé. | Non (ajout — cf. §Écarts) |
| `templates/roadmap/_dates.html.twig` | « début → fin », « fin inconnue » ou « fin inconnue, estimation à réviser ». | Non (ajout — cf. §Écarts) |
| `templates/roadmap/_today.html.twig` | Repère « aujourd'hui ». | Non (ajout — cf. §Écarts) |
| `templates/lot/_member_row.html.twig` | Ligne d'équipe, partagée par les lignes existantes et le prototype. | Non (ajout — cf. §Écarts) |
| `tests/Unit/Service/SchedulerTest.php` | 17 cas : exemples chiffrés du pitch, signaux, charge, surcharge, horizon. | Oui |
| `tests/Unit/Model/Schedule/DailyCapacityTest.php` | 7 cas : répartition uniforme, jours fériés, historique des maximums, inactif. | Oui |
| `tests/Unit/Model/Roadmap/RoadmapWindowTest.php` | 7 cas : fenêtre, navigation, positions, coupe, repère, mois. | Oui |
| `tests/Service/RoadmapBuilderTest.php` | 6 cas contre la base : barres, cumul, planning partiel, dépassement, feuille sans capacité, surcharge selon le drapeau. | Écart volontaire (cf. §Écarts) |
| `tests/Unit/Dto/LotInputTest.php` | 7 cas : responsable ajouté ou gardé avec sa part, lignes invalides écartées, pré-remplissages, `planningChanged()`. | Oui |
| `tests/Service/ScheduleLoaderTest.php` | 5 cas : plans, feuilles, capacité, jour de dépassement, jours fériés au-delà de l'horizon. | Oui |
| `tests/Controller/RoadmapControllerTest.php` | 8 cas : accès par rôle, barres et détails, dépassement, manques, surcharge selon le rôle, retour vers la roadmap, navigation, requêtes constantes. | Oui |
| `tests/e2e/roadmap.spec.ts` | 3 scénarios : planifier et voir la barre, naviguer, ouvrir une feuille depuis la roadmap et revenir. | Oui |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Entity/Lot.php` | `startDate` (normalisée à minuit) et collection `members` avec accesseurs. | Oui |
| `src/Dto/LotInput.php` | `startDate`, `members` (`Valid`, `Unique` avec normaliseur), `effectiveMembers()`, `planningChanged()`, planification en vigueur mémorisée, pré-remplissages, `#[PlanningFitsCapacity]`. | Écart volontaire (cf. §Écarts) |
| `src/Form/LotType.php` | Option `with_planning` : `startDate` et `members` (prototype à 100 %, `error_bubbling` désactivé) ; personnes actives chargées une fois. | Oui |
| `templates/lot/_form.html.twig` | Bloc « Planification » branché sur `form-collection`. | Oui |
| `templates/lot/new.html.twig` | Messages de reprise et d'aide mentionnant date de début et équipe. | Oui |
| `templates/lot/edit.html.twig` | Lien « Annuler » vers `back_path` (roadmap ou projet). | Oui |
| `src/Controller/LotController.php` | `with_planning` en création, premier sous-lot et modification ; paramètre `roadmap` validé par `Week::fromIso` pour le retour. | Oui |
| `src/Service/ProjectManager.php` | Date et membres appliqués depuis `effectiveMembers()`, mis à jour en place ; vidés par `clearLeafData()` ; remontés par `deleteLot()`. | Oui |
| `src/Repository/LotRepository.php` | `findLeavesForSchedule()`. | Oui |
| `src/Repository/TimeEntryRepository.php` | `summarizeByLot()` et `sumQuartersByDayForLots()` (jour de dépassement). | Écart volontaire (cf. §Écarts) |
| `src/Repository/WeeklyMaxRepository.php` | `findAllQuartersByUser()` au lieu de `findAllOrdered()`. | Écart volontaire (cf. §Écarts) |
| `src/Service/WeeklyMaxManager.php` | Helper statique `cap()`, réutilisé par `capFor()` et `DailyCapacity`. | Oui |
| `src/Service/HolidayManager.php` | `between()` devient `holidaysBetween()`, public. | Oui |
| `src/Twig/NavigationExtension.php` | Section `roadmap`. | Oui |
| `templates/base.html.twig` | Entrée « Roadmap » dans la zone du haut. | Oui |
| `fixtures/ProjectFixtures.php` | Responsable ajouté à l'équipe à 100 %. | Oui |
| `fixtures/DemoCompanyFixtures.php` | Équipes des feuilles en cours, démarrage en retard, surcharge passive ; feuilles terminées ramenées à une estimation atteinte ou dépassée. | Écart volontaire (cf. §Écarts) |
| `tests/Support/CreatesProjects.php` | Responsable ajouté en membre ; `planLot()` qui met à jour les membres en place. | Oui |
| `tests/Unit/Service/ProjectManagerTest.php` | 4 cas : application, mise à jour en place, bascules. | Oui |
| `tests/Controller/LotControllerTest.php` | 12 cas : planification, droits, unicité, personnes proposées, part par défaut, surcharge, bascule. | Oui |
| `tests/Controller/NavigationTest.php` | Entrée Roadmap pour les trois rôles et active sur ses pages. | Oui |
| `tests/Unit/Twig/NavigationExtensionTest.php` | Routes `app_roadmap` et `app_roadmap_week`. | Oui |
| `assets/styles/app.css` | Classe `.roadmap-track` : quadrillage des semaines via `--roadmap-weeks`. | Non (ajout — cf. §Écarts) |
| `tests/e2e/projects.spec.ts` | Purge des équipes avant les lots. | Non (ajout — cf. §Écarts) |
| `templates/components/Timesheet.html.twig` | Bordures pointillées entre les colonnes de jours de la grille de saisie. | Non (ajout — cf. §Écarts) |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| Une migration générée, complétée de la reprise des responsables. | Migration de schéma générée et non retouchée, plus une migration de données générée vide (`doctrine:migrations:generate`) portant l'`INSERT … SELECT`. | La checklist migration interdit de modifier le contenu d'une migration générée. |
| `ScheduleLoader` en 4 requêtes. | 6 requêtes constantes (feuilles, agrégats de saisie, personnes, maximums, jours fériés par calendrier), plus une requête quand des feuilles sont en dépassement. | Toutes les personnes sont chargées pour qu'un membre ajouté dans le formulaire soit connu de la capacité. Les jours fériés passent par `HolidayManager` (un appel par calendrier). Le jour de dépassement demande les saisies jour par jour. Le nombre reste indépendant du nombre de feuilles (`RoadmapControllerTest`). |
| `WeeklyMaxRepository::findAllOrdered()`. | `findAllQuartersByUser()`, qui rend directement les couples (lundi, quarts) par personne. | Forme attendue par `DailyCapacity`. |
| `Assert\Unique(fields: ['user'])` sur `LotInput::$members`. | `Assert\Unique` avec le normaliseur `LotMemberInput::identify()`. | L'option `fields` ne s'applique qu'à des éléments tableaux, pas à des objets. |
| `PlannedMember` : id, part, calendrier, actif. | Id et part ; calendrier et état actif lus dans `DailyCapacity`. | Une seule source pour la capacité d'une personne. |
| `RoadmapBuilderTest` en test unitaire. | Test contre la base de test (`tests/Service/`). | `ScheduleLoader` est une classe `final readonly`, qu'on ne peut pas simuler. |
| Nombre de requêtes constant vérifié dans `ScheduleLoaderTest`. | Vérifié dans `RoadmapControllerTest` via le profiler. | Le profiler et son collecteur Doctrine ne sont disponibles qu'en test HTTP. |
| Signal « estimation épuisée » : barre arrêtée au dernier jour saisi (règle 15 d'origine). | « estimation atteinte » (gris, fin au dernier jour saisi) et « en dépassement » (rouge, ampleur affichée, jours au-delà de l'estimation en rouge, fin inconnue propagée au lot et au projet). | Demande de l'utilisateur après l'implémentation : le signal confondait feuille terminée et dépassement, et le restant plafonné à 0 masquait l'ampleur. Pitch réécrit (règles 15, 21, 24, 25 et critères). |
| Fixtures : feuilles terminées estimées autour de leur consommé. | Terminées dans les temps : dernière saisie rognée à des jours entiers, donc « estimation atteinte ». Terminées en retard : dépassement réel. | Sans clôture, une feuille terminée avec un restant positif déborderait dans le futur ; un arrondi inférieur généralisé aurait mis presque toutes les feuilles terminées en dépassement de quelques quarts. |
| `LotInput` sans état de la planification en vigueur. | `currentStartDate` et parts en vigueur mémorisées ; `planningChanged()`. | Review : [BUG] part du responsable remontée à 100 % et [PERF] chargement du planning à chaque enregistrement. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `ScheduleData` | Regroupe ce que le chargeur rend aux deux consommateurs (roadmap, validateur). |
| `Roadmap`, `RoadmapBar` | Modèle de page (repère aujourd'hui, mois) et barre positionnée, sortis de `RoadmapRow` et `RoadmapWindow`. |
| `RoadmapSignal` (enum) | Libellés et variantes de badge au même endroit, selon la convention des enums du projet (`src/Enum/Type/`). |
| Fragments `roadmap/_bar`, `_dates`, `_today`, `lot/_member_row` | Réutilisés par les projets, les lots et les feuilles, et par le prototype de la collection. |
| `.roadmap-track` dans `app.css` | Quadrillage des semaines ; son nombre de colonnes vient de `RoadmapWindow::WEEK_COUNT` (review [ARCHI]). |
| `TimeEntryRepository::sumQuartersByDayForLots()` | Jour de franchissement de l'estimation, pour la barre de dépassement. |
| Migration de données séparée | Voir §Écarts volontaires. |
| Purge des équipes dans `tests/e2e/projects.spec.ts` | Les lots créés par ce scénario ont un responsable, donc un membre ; SQLite n'applique pas les clés étrangères. |
| `prototype_data` à 100 % sur la collection d'équipe | Constaté pendant ce report : une ligne ajoutée s'affichait à 25 %, contre le critère « 100 % par défaut ». Corrigé et testé (`testAMemberAddedToTheTeamGivesAFullShareByDefault`). |
| Bordures pointillées de la grille de saisie (`Timesheet.html.twig`) | Modification présente avant la story, étrangère à la roadmap ; rattachée à la story sur décision de l'utilisateur. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Service/Scheduler.php` | unit | unit, 17 cas | Fait — couverture étendue (atteinte et dépassement distingués) |
| `src/Model/Schedule/DailyCapacity.php` | unit | unit, 7 cas | Fait |
| `src/Model/Roadmap/RoadmapWindow.php` | unit | unit, 7 cas (mois et repère inclus) | Fait — couverture étendue |
| `src/Service/RoadmapBuilder.php` | unit | functional, 6 cas | Fait — type changé (cf. §Écarts) |
| `src/Dto/LotInput.php` | unit | unit, 7 cas | Fait — couverture étendue (`planningChanged()`, part en vigueur) |
| `src/Service/ProjectManager.php` | unit | unit, 4 cas ajoutés | Fait |
| `src/Service/ScheduleLoader.php` | functional | functional, 5 cas ; requêtes constantes vérifiées dans `RoadmapControllerTest` | Fait — cf. §Écarts |
| `src/Validator/PlanningFitsCapacityValidator.php` (via `LotController`) | functional | functional, 5 cas | Fait |
| `src/Controller/LotController.php` | functional | functional, 7 cas (planification, droits, unicité, personnes proposées, part par défaut, bascule, retour) | Fait |
| `src/Controller/RoadmapController.php` | functional | functional, 8 cas | Fait |
| `src/Twig/NavigationExtension.php` | unit | unit, 2 routes ajoutées | Fait |
| `tests/e2e/roadmap.spec.ts` | E2E | E2E, 3 scénarios | Fait |
| Reprise des responsables par la migration | hors scope assumé | vérifiée à la main sur la base de démo (31 membres, aller-retour) | Conforme — base de test reconstruite vide |
| Positions en pixels | hors scope assumé | positions en % testées, présence des barres en E2E | Conforme |
| Performance au-delà des requêtes constantes | hors scope assumé | pas de test | Conforme |

Résultats consignés en amont (review) : 295 tests PHPUnit et 31 E2E verts, `make lint` propre. Après le correctif du prototype fait pendant ce report, seul `LotControllerTest` a été relancé (31 tests verts) ; la suite complète n'a pas été relancée.

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Un lead pose une date de début et une équipe de deux personnes actives avec leurs parts sur une feuille estimée ; la feuille apparaît avec une barre sur la page Roadmap.
- [x] Seules des personnes actives sont proposées comme membres ; la part se choisit parmi 25, 50, 75 et 100 %, 100 % par défaut.
- [x] Un lot découpé et un projet n'offrent ni date de début ni équipe à renseigner.
- [x] Désigner un responsable l'ajoute à l'équipe à 100 % ; tant qu'il est responsable, il ne peut pas en être retiré.
- [x] Un membre de prod responsable d'une feuille ne peut modifier ni sa date de début ni son équipe.
- [x] Une feuille de 10 j qui démarre un lundi sans jour férié, avec une personne à 5 j/semaine à 100 %, finit le vendredi de la semaine suivante. Avec deux personnes à 100 %, elle finit le vendredi de la même semaine. Avec une personne à 50 %, elle finit le vendredi de la quatrième semaine.
- [x] Un jour férié du calendrier d'un membre dans la période repousse la fin calculée ; un maximum hebdomadaire abaissé la repousse aussi.
- [x] Dans le premier exemple, si seuls 3 j sont saisis la première semaine, la fin calculée constatée le vendredi de cette semaine recule de deux jours ouvrés, au mardi de la troisième semaine.
- [x] La partie réalisée d'une barre couvre exactement les jours saisis, du premier au dernier ; elle ne dit pas qui a saisi.
- [x] Une feuille dont la date de début est passée et sans aucun temps saisi démarre demain sur la frise et est signalée « démarrage en retard ».
- [x] Une feuille dont des temps sont saisis avant sa date de début commence au premier jour saisi.
- [x] Une feuille dont le consommé égale l'estimation se termine au dernier jour saisi et est signalée « estimation atteinte », sans alarme.
- [x] Une feuille dont le consommé dépasse l'estimation est signalée « en dépassement » avec l'ampleur du dépassement ; les jours saisis au-delà de l'estimation sont en rouge sur sa barre, et sa fin, comme celle de son lot et de son projet, est « inconnue » tant que l'estimation n'est pas révisée.
- [x] Ajouter un membre, changer une part, poser une date de début ou désigner un responsable qui ferait dépasser 100 % à une personne un jour donné est refusé ; le message nomme la personne, la feuille en conflit et le premier jour concerné.
- [x] Quand une saisie plus lente ou une estimation révisée fait chevaucher deux feuilles d'une même personne au-delà de 100 %, les deux feuilles sont signalées « à replanifier » pour un lead et la direction, et pas pour un membre de prod.
- [x] Désactiver un membre allonge la barre de ses feuilles et les signale « équipe à revoir » ; une personne désactivée n'est pas proposée comme membre.
- [x] Quand un lot planifié reçoit son premier sous-lot, ce sous-lot reprend la date de début et l'équipe, et la barre est inchangée.
- [x] La page Roadmap est accessible depuis la zone du haut du menu pour tous les rôles.
- [x] Les projets sont listés par ordre alphabétique et se déplient en lots puis en sous-lots ; un projet et un lot découpé affichent une barre de cumul de leurs feuilles.
- [x] La frise est en semaines, avec un repère « aujourd'hui », et affiche par défaut de 4 semaines avant à 36 semaines après la semaine en cours ; on navigue vers la période précédente ou suivante et on revient à la fenêtre par défaut.
- [x] Une feuille à estimer, sans début ou sans équipe apparaît dans son projet sans barre, avec ce qui lui manque, et son projet est signalé « planning partiel ».

## Dette technique identifiée

Issus de la review : aucun, les sept findings ont été corrigés pendant la passe.

Au-delà de la review :

1. **Reprise des responsables à vérifier en production** — après migration, contrôler que chaque feuille avec responsable a un membre à 100 %. **Critique** au prochain déploiement.
2. **Suite complète non relancée après le correctif du prototype** — `/forge:commit` doit relancer `make phpunit` et `make playwright`.
3. **Pas de clôture de feuille** — une feuille terminée en dépassement rend « inconnue » la fin de son projet (Orion dans la démo), jusqu'à ce que l'estimation soit révisée au niveau du consommé. Relève de `jalons-dates-annoncees` (C2.5).
4. **Feuilles en dépassement hors de la charge** — sans restant, elles ne chargent plus leur équipe et ne comptent pas dans le contrôle de surcharge. Relève de `consomme-vs-estime` et `alerte-derive`.
5. **Absences non déduites** — les barres sont optimistes pendant les congés. Relève de `capacite-equipe` (C4.2).
6. **Backlog à réaligner** — la story couvre C6.2 et une partie de C4.4 et C7.1, et remplace la période prévue saisie de C2.4 par une fin calculée. À traiter par `/forge:sync`, ou `/product-backlog`.
7. **Plan à réaligner** — `plan.md` annonce 4 requêtes pour le chargeur, `findAllOrdered()`, `Assert\Unique(fields: ['user'])` et un `RoadmapBuilderTest` unitaire (cf. §Écarts). À traiter par `/forge:sync`.
8. **Lisibilité mobile de la frise** — défilement horizontal dans le conteneur ; vérifié en 1440 px seulement.

## Leçons apprises

- **Unicité sur une collection Doctrine** : retirer puis rajouter la même personne dans un seul `flush` échoue, car les insertions passent avant les suppressions. Toute synchronisation de collection sous contrainte d'unicité doit mettre à jour en place, comme `ProjectManager::applyMembers()`.
- **Tests sur une base qui s'accumule** : la base de test n'est pas vidée entre deux tests. Un contrôle de surcharge rend les comptes de fixtures (lead, prod) instables d'un test à l'autre : chaque test de planification doit créer ses propres personnes et figer l'horloge.
- **Erreurs d'une collection de formulaire** : un formulaire composé fait remonter ses erreurs au parent par défaut (`error_bubbling`). Sans `form_errors(form)` dans le gabarit, elles disparaissent en silence ; il faut les désactiver sur la collection.
- **Prototype de collection** : la valeur par défaut d'un DTO ne s'applique pas au prototype rendu. Il faut `prototype_data`, sinon le navigateur sélectionne la première option.
- **Sémantique des signaux avant la clôture** : « estimation atteinte » et « en dépassement » auraient dû être distingués dès le pitch. Sans clôture, une feuille terminée et une feuille en retard se ressemblent ; tout signal fondé sur le restant doit dire comment il traite ces deux cas.
- **Navigateur de contrôle après réinitialisation de la base** : après un `make db-reset`, la session du navigateur MCP devient inutilisable (clics et soumissions ignorés). Repartir d'un navigateur neuf, ou s'appuyer sur `make playwright`.
