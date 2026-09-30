# Review — Planifier chaque feuille avec une date de début et une équipe pour dessiner la chronologie des projets sur une roadmap

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/006-f-roadmap-planification/`
> **Amont** : `plan.md` · `pitch.md`
> **Diff examiné** : working tree, 25 fichiers modifiés (+770 / −30) et 37 fichiers nouveaux (~2 740 lignes), hors documents de story ; `templates/components/Timesheet.html.twig`, modifié avant la story, est hors périmètre

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 1
- **Mineurs restants** : 0 / 6
- **Statut** : **PRÊT À COMMITER**

Le diff suit le plan. Les écarts sont documentés dans `metadata.json` : migration de données séparée, `RoadmapBuilderTest` contre la base, chargeur en requêtes constantes mais plus nombreuses que prévu, et signal de dépassement scindé à la demande. Aucune faille : le retour vers la roadmap est limité à une semaine ISO valide, les champs de planification sont absents du formulaire d'un membre de prod et rejetés s'il les soumet, le signal « à replanifier » n'est pas calculé pour lui, et les choix de personnes sont bornés côté serveur. L'important et les six mineurs sont corrigés pendant la passe, chacun couvert par un test. La QA repasse au vert : 295 tests PHPUnit, 31 E2E, `make lint` et le build CSS. Prochaine étape : `/forge:report`, puis `/forge:sync` et `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

- [x] **[BUG] Retirer la ligne du responsable remonte sa part à 100 %** — `src/Dto/LotInput.php:106-107` — `effectiveMembers()` rajoute le responsable absent des lignes à 100 %, sans tenir compte de sa part en vigueur. Un lead qui retire la ligne d'un responsable à 50 % enregistre donc ce responsable à 100 %, le contraire de son intention ; le contrôle de surcharge peut même le refuser avec un message incompréhensible. La règle 5 du pitch dit que le responsable « ne peut pas être retiré » : il doit revenir avec sa part en vigueur, 100 % seulement s'il est nouvellement désigné. Corrigé : `LotInput` mémorise la planification en vigueur (`currentStartDate` et les parts), et `effectiveMembers()` rend au responsable sa part en vigueur. Couvert par `LotInputTest` et par `LotControllerTest::testRemovingARowRemovesTheMemberButNeverTheOwner`, qui part désormais d'un responsable à 50 %.

## Mineurs

- [x] **[PERF] Le contrôle de surcharge charge tout le planning à chaque enregistrement d'un lot** — `src/Validator/PlanningFitsCapacityValidator.php:47-59` — `ScheduleLoader::load()` (6 à 7 requêtes et le calcul de toutes les feuilles) s'exécute avant la comparaison avant/après, y compris quand la planification ne change pas : titre modifié, révision d'estimation par un responsable. Corrigé : le validateur sort avant tout chargement quand `LotInput::planningChanged()` est faux ; la comparaison `samePlanning()`, devenue inutile, est retirée. Couvert par `LotInputTest::testPlanningChangesWithTheStartDateOrTheTeamOnly`.
- [x] **[PERF] Une requête des personnes actives par ligne d'équipe** — `src/Form/LotMemberType.php:32` — chaque ligne, prototype compris, rappelle `findActiveForOwnerChoice()`. Corrigé : `LotType` charge la liste une fois pour le responsable et les lignes, et la passe aux lignes par l'option obligatoire `people` ; `LotMemberType` ne dépend plus du repository.
- [x] **[ROBUSTESSE] Une feuille planifiée sans aucune capacité ni saisie compte comme non planifiée dans son projet** — `src/Service/RoadmapBuilder.php:84` et `:57` — sans partie réalisée ni future, `start()` est nul. Le projet affiche alors « planning partiel » et une fin connue qui ignore cette feuille, contrairement à la règle 25 du pitch (« dès qu'une feuille planifiée n'a pas de fin, la fin est inconnue »). Corrigé : la ligne prend sa date de début planifiée à défaut de barre, et le planning partiel se reconnaît à l'absence de début. Couvert par `RoadmapBuilderTest::testPlannedLeafWithoutCapacityNorEntryKeepsItsStartAndLeavesTheEndOfItsProjectUnknown`.
- [x] **[ROBUSTESSE] Jours fériés non chargés au-delà de l'horizon des feuilles existantes** — `src/Service/ScheduleLoader.php:123` — la plage des jours fériés s'arrête trois ans après la plus tardive des dates de début enregistrées. Une date de début saisie plus loin est contrôlée sans ses jours fériés. Corrigé : `ScheduleLoader::load()` accepte la date de début saisie, et le validateur la lui passe. Couvert par `ScheduleLoaderTest::testHolidaysReachAStartDateNotRecordedYet`.
- [x] **[ARCHI] Le nombre de semaines de la frise est dupliqué entre PHP et CSS, et `RoadmapWindow::weeks()` est du code mort** — `assets/styles/app.css:293`, `src/Model/Roadmap/RoadmapWindow.php:51` — le quadrillage code 41 en dur, alors que `weeks()` n'est appelé que par les tests. Corrigé : `weeks()` est remplacé par la constante `RoadmapWindow::WEEK_COUNT`, que la page transmet au quadrillage par la variable CSS `--roadmap-weeks` (vérifié dans le navigateur : pas de 2,439 %).
- [x] **[TEST] Aucun test ne vérifie qu'une personne désactivée n'est pas proposée comme membre** — `tests/Controller/LotControllerTest.php` — deux critères d'acceptation du pitch le demandent (« Seules des personnes actives sont proposées comme membres », « une personne désactivée n'est pas proposée comme membre »). Corrigé : `LotControllerTest::testOnlyActivePeopleAreOfferedAsMembersBesidesADeactivatedMemberAlreadyInTheTeam` vérifie les lignes existantes et le prototype.

## Points positifs

- **Moteur pur et exact** : `Scheduler` et `DailyCapacity` ne touchent pas la base. Ils calculent en unités entières de 1/6000 de quart, ce qui rend exacte la répartition d'un temps partiel sur 1 à 5 jours. Tous les exemples chiffrés du pitch sont des tests unitaires lisibles.
- **Une seule vérité pour deux consommateurs** : la roadmap et le contrôle de surcharge partagent le même chargeur et le même moteur. La comparaison avant/après à estimation égale garantit par construction qu'une révision d'estimation seule n'est jamais refusée, et un test le prouve.
- **Principe 2 tenu côté serveur** : le signal « à replanifier » n'est pas masqué dans le gabarit, il n'est pas calculé pour la prod. Le test fonctionnel vérifie son absence dans le HTML.
- **Piège Doctrine anticipé** : `applyMembers()` met les membres à jour en place au lieu de les supprimer et de les recréer. Cela évite l'insertion avant suppression qui violerait l'unicité (feuille, personne) dans un même `flush`, et le docblock explique pourquoi.
- **Requêtes constantes** : le nombre de requêtes de `/roadmap` ne dépend pas du nombre de feuilles, et un test le vérifie avec le profiler.
- **Isolation des tests** : les tests de planification utilisent des personnes neuves et une horloge figée. Ils restent verts quand la base de test accumule des données d'une exécution à l'autre.

## Hors review (à vérifier en environnement réel)

- **Reprise des responsables sur la base réelle** : la migration de données n'est testée que sur la base de démo (31 membres créés, aller-retour vérifié). À contrôler après migration en production : chaque feuille avec responsable a un membre à 100 %.
- **Lisibilité de la frise sur mobile** : défilement horizontal dans le conteneur, colonne des libellés collante. Vérifié en 1440 px seulement.
- **Feuilles terminées sans clôture** : tant que `jalons-dates-annoncees` n'est pas livrée, une feuille terminée en dépassement rend « fin inconnue » la fin de son projet (Orion dans la démo). Il faut réviser l'estimation au niveau du consommé pour retrouver une fin.
