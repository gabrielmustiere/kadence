# Plan technique — Affecter un collaborateur à une feuille par tranches de 10 % de sa capacité

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/019-f-parts-equipe-pas-de-10/`
> **Amont** : `pitch.md`

## Approche retenue

La liste des parts autorisées est portée par une seule constante, `LotMember::SHARES` (`src/Entity/LotMember.php:15`), que lisent le formulaire (`src/Form/LotMemberType.php:37`, libellés « N % »), la contrainte `Assert\Choice` du DTO (`src/Dto/LotMemberInput.php:17`) et le filtre des membres effectifs (`src/Dto/LotInput.php:108`). La feature consiste à remplacer `[25, 50, 75, 100]` par `[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]`, à reformuler le message de refus et à donner ce même message au champ du formulaire. Tout le reste suit sans modification : le calcul de capacité, la charge et le refus de surcharge travaillent sur un pourcentage entier quelconque.

`DailyCapacity::units()` (`src/Model/Schedule/DailyCapacity.php:126-138`) calcule `share × quarters × 120 / workingDays`, puis divise par 2 en cas de demi-journée d'absence. Comme `120 / workingDays` ∈ {120, 60, 40, 30, 24}, tous pairs, le résultat reste entier pour toute part entière, et une part de 10 % ne crée aucun arrondi. `Scheduler`, `ScheduleResult` (somme des parts par jour, `FULL_LOAD`) et `PlanningFitsCapacityValidator` sont indépendants de la liste des parts. Seules les annotations PHPStan `int<25, 100>`, présentes dans 17 occurrences, figent l'ancienne borne basse : elles passent à `int<10, 100>`.

### Mécanismes mobilisés

- **Constante littérale `LotMember::SHARES`** : source unique de la liste. Elle est écrite en littéral, car une expression constante ne peut pas appeler `range()`. Ce littéral permet aussi à PHPStan de réduire `in_array($share, LotMember::SHARES, true)` à l'union `10|20|…|100`, sous-type de `int<10, 100>`.
- **`Assert\Choice` sur `LotMemberInput::$share`** : garde du DTO. Son message devient « Choisissez une part de 10 à 100 %, par pas de 10. », porté par la constante `LotMemberInput::INVALID_SHARE`.
- **`invalid_message` du `ChoiceType`** : une valeur postée hors de la liste échoue à la transformation du formulaire avant la validation du DTO. Le champ `share` reçoit donc le même message, par la même constante, comme `LotType.php:46` et `TeamMemberType.php:71` le font pour leurs champs.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Champ numérique (`IntegerType`, pas de 10) | Tranché au pitch : liste déroulante, sans valeur hors pas possible et sans changement d'écran. |
| Conserver 25 et 75 % en plus des dizaines | Tranché au pitch : liste régulière de dix valeurs. |
| Contrainte CHECK en base sur `lot_member.share` | La colonne n'en a jamais eu (`migrations/Version20260930123737.php:23`) ; la règle vit dans le domaine et la validation, une migration pour la seule borne n'apporterait rien. |
| Contrainte `Assert\DivisibleBy(10)` + `Range(10, 100)` | Deux contraintes pour dire ce que la constante dit déjà ; `Choice` garde la constante comme source unique, partagée avec le formulaire. |

## Modèle de données

Aucun impact modèle. La colonne `lot_member.share` (`SMALLINT NOT NULL`) accepte déjà toute valeur ; seules la constante `LotMember::SHARES` et les annotations `int<10, 100>` de `LotMember` changent, sans migration.

## Périmètre

### Fichiers à créer

_(aucun)_

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Entity/LotMember.php` | `SHARES` = `[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]` ; `int<25, 100>` → `int<10, 100>` (propriété, constructeur, `getShare()`, `setShare()`). |
| `src/Dto/LotMemberInput.php` | Constante `INVALID_SHARE` = « Choisissez une part de 10 à 100 %, par pas de 10. », message de `Assert\Choice` ; annotation `int<10, 100>`. |
| `src/Form/LotMemberType.php` | `invalid_message` du champ `share` = `LotMemberInput::INVALID_SHARE`. |
| `src/Dto/LotInput.php` | Annotations `int<10, 100>` (`$currentShares`, `effectiveMembers()`). |
| `src/Model/Schedule/PlannedMember.php` | Annotation `int<10, 100>`. |
| `src/Model/Schedule/DailyCapacity.php` | Annotation `int<10, 100>` de `units()`. |
| `src/Service/ScheduleLoader.php` | Annotation `int<10, 100>`. |
| `src/Service/ProjectManager.php` | Annotation `int<10, 100>`. |
| `src/Service/PersonRoadmapBuilder.php` | Annotations `int<10, 100>` (`sharesOf()`, `row()`). |
| `src/Twig/Components/LotForm.php` | Annotation `int<10, 100>`. |
| `fixtures/DemoCompanyFixtures.php` | Lucas passe à 30 % sur Webhooks et 70 % sur Documentation (100 % au total, sans surcharge) ; annotation `int<10, 100>`. |
| `tests/Support/CreatesProjects.php` | Annotation `int<10, 100>`. |
| `tests/Unit/Service/SchedulerTest.php` | Annotation `int<10, 100>` ; cas d'une part de 10 % (voir §Stratégie de test) ; part 25 → 20 dans le cas du restant non couvert dans l'horizon. |
| `tests/Unit/Model/Schedule/DailyCapacityTest.php` | Cas d'une part de 10 %. |
| `tests/Twig/Components/LotFormTest.php` | Les dix options proposées. |
| `tests/Controller/LotControllerTest.php` | Part `'25'` → `'20'` dans `testAPersonAppearsOnlyOnceInATeam` ; parts 25/75 posées par `planLot()` passées à 20/70 ; refus d'une part hors liste ; répartition 30 + 30 + 40 acceptée puis 50 refusée. |
| `tests/Unit/Dto/LotInputTest.php` | Parts 25/75 passées à 20/70 ; la part invalide du cas « ligne écartée » passe de 30, devenu valide, à 25. |
| `tests/Unit/Service/ProjectManagerTest.php` | Parts 25/75 passées à 20/70. |
| `tests/Service/RoadmapBuilderTest.php` | Part 25 → 20 dans le cas de la fête nationale belge. |
| `tests/e2e/roadmap.spec.ts` | La part choisie passe de « 50 % » à « 30 % », et l'infobulle lit « Paula Durand 30 % ». |

## Hors scope

- **Libellés de part dans les gabarits** : `templates/roadmap/_team.html.twig:16` et les gabarits de la fiche personne affichent `share ~ ' %'`, qui convient à toute valeur et reste donc inchangé.
- **`Ruler.html.twig`** : son `25` concerne les graduations en jours de la règle, sans rapport avec les parts.
- **Contrôle saisie vs part** : la saisie reste en quarts et libre (pitch, règle 5).

## Impacts transverses

- **Cloisonnement des données** : non concerné (outil mono-organisation).
- **Déclinaisons / thèmes** : non. La liste déroulante garde son composant et son thème de formulaire, avec dix options.
- **Traduction / i18n** : non. Les messages sont en français, en dur, comme les autres contraintes du projet.
- **API / exposition externe** : non.
- **Droits d'accès** : inchangés. La composition d'équipe reste réservée aux leads et à la direction par les contrôles existants du formulaire de lot.
- **Emails / notifications** : non.
- **Migration de données** : aucune. La base est réinitialisée avec les fixtures à chaque déploiement (`deploy.sh`), et les fixtures n'utilisent que 50 et 100 %, puis 30 et 70 % après cette story.
- **Comportement par défaut** : une ligne d'équipe ajoutée vaut toujours 100 % (`LotMemberInput::$share = 100`), et un responsable rejoint l'équipe à 100 % (`LotInput::effectiveMembers()`).

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/Schedule/DailyCapacity.php` | unit | Une part de 10 % d'un temps plein de 20 quarts sur 5 jours donne 0,4 quart par jour en unités entières, et la moitié exacte de ce résultat sur une demi-journée d'absence. |
| `src/Service/Scheduler.php` | unit | À estimation égale, une feuille dont l'unique membre est à 10 % finit plus tard qu'à 50 % (critère « la fin recule »). |
| `src/Twig/Components/LotForm.php` (formulaire d'équipe) | functional | La liste propose exactement 10 %, 20 %, …, 100 %, avec 100 % sélectionné par défaut. |
| `src/Controller/LotController.php` (modification d'une feuille) | functional | Une part `25` postée est refusée avec « Choisissez une part de 10 à 100 %, par pas de 10. » ; une personne à 30 + 30 + 40 % sur trois feuilles qui se chevauchent est acceptée, et passer l'une des parts à 50 % est refusé avec le message de surcharge existant ; le test du doublon de personne passe avec `'20'`. |
| Parcours lead → roadmap | E2E | Le lead choisit « 30 % », et l'infobulle de la feuille sur la roadmap affiche « Paula Durand 30 % ». |

**Hors scope tests** :

- Pas de nouveau test sur la fiche d'une personne. Elle affiche la part par le même `share ~ ' %'` que la roadmap, que l'E2E couvre déjà, et ses tests existants (`PersonControllerTest`, `PersonRoadmapBuilderTest`) restent valides.
- Pas de test sur la saisie quotidienne : elle n'est pas touchée.
- Pas de test du responsable « ramené à 10 % » dédié : c'est le même chemin qu'une modification de part, couvert par le test du refus ou de l'acceptation de 30, 40 et 50 %.

## Ordre d'exécution

1. [x] **Liste des parts et messages**
   - Objectif : `LotMember::SHARES` passe aux dizaines, le message du DTO est reformulé et le champ `share` reçoit son `invalid_message`.
   - Fichiers : `src/Entity/LotMember.php`, `src/Dto/LotMemberInput.php`, `src/Form/LotMemberType.php`.
   - Vérification : `make phpunit-filter LotControllerTest` (le test du doublon casse tant que `'25'` n'est pas remplacé, voir étape 3).
   - Commitable seule : non.

2. [x] **Annotations `int<10, 100>`**
   - Objectif : plus aucune occurrence de `int<25, 100>`.
   - Fichiers : `src/Dto/LotInput.php`, `src/Model/Schedule/PlannedMember.php`, `src/Model/Schedule/DailyCapacity.php`, `src/Service/ScheduleLoader.php`, `src/Service/ProjectManager.php`, `src/Service/PersonRoadmapBuilder.php`, `src/Twig/Components/LotForm.php`, `fixtures/DemoCompanyFixtures.php`, `tests/Support/CreatesProjects.php`, `tests/Unit/Service/SchedulerTest.php`.
   - Vérification : `grep -rn "int<25" src fixtures tests` vide ; `make lint` propre.
   - Commitable seule : non.

3. [x] **Tests unitaires et fonctionnels**
   - Objectif : couvrir la capacité à 10 %, la fin qui recule, les dix options, le refus d'une part hors liste et la répartition 30 + 30 + 40.
   - Fichiers : `tests/Unit/Model/Schedule/DailyCapacityTest.php`, `tests/Unit/Service/SchedulerTest.php`, `tests/Twig/Components/LotFormTest.php`, `tests/Controller/LotControllerTest.php`, `tests/Unit/Dto/LotInputTest.php`, `tests/Unit/Service/ProjectManagerTest.php`, `tests/Service/RoadmapBuilderTest.php`.
   - Vérification : `make phpunit` vert.
   - Commitable seule : oui (avec 1 et 2).

4. [x] **Démo**
   - Objectif : Lucas à 30 % sur Webhooks et 70 % sur Documentation.
   - Fichiers : `fixtures/DemoCompanyFixtures.php`.
   - Vérification : `make db-reset`, puis la fiche de Lucas affiche 30 et 70 % sans jour au-delà de 100 %.
   - Commitable seule : oui.

5. [x] **E2E et QA finale**
   - Objectif : le parcours roadmap choisit « 30 % » et le lit dans l'infobulle.
   - Fichiers : `tests/e2e/roadmap.spec.ts`.
   - Vérification : `make serve` lancé, `make playwright-file tests/e2e/roadmap.spec.ts` vert ; `make quality` propre ; `make phpunit` vert.
   - Commitable seule : oui.

## Critères de sortie

- [x] `LotMember::SHARES` vaut `[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]` et aucune occurrence de `int<25, 100>` ne subsiste.
- [x] Une part hors liste, postée par le formulaire, est refusée avec « Choisissez une part de 10 à 100 %, par pas de 10. »
- [x] Aucune migration ajoutée ; `symfony console doctrine:schema:validate` reste synchrone.
- [x] Suite PHPUnit verte (`make phpunit`), sans nouvelle régression.
- [x] `make lint` propre (PHP-CS-Fixer + PHPStan level 10).
- [x] `make playwright-file tests/e2e/roadmap.spec.ts` vert.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Un test ou une fixture poste encore une part de 25 ou 75 % et échoue pour une autre raison que celle qu'il vérifie | faible | Recensement des parts postées par formulaire (`LotControllerTest.php:463`), complété à l'implémentation par les parts 25/75 posées directement (`planLot()`, constructeurs, `LotMemberInput::of()`) dans cinq fichiers de tests, passées à 20/70. |
| Arrondi de capacité à 10 % qui fausserait la fin ou la charge | faible | Démontré entier par construction (`120 / workingDays` pair) et verrouillé par le test unitaire de `DailyCapacity`, demi-journée comprise. |
| Fin au-delà de l'horizon de 3 ans plus fréquente avec de petites parts | moyenne | Comportement voulu par le pitch (règle 6) : « fin inconnue » existe déjà et est testé (`testRemainingTimeNotCoveredWithinTheHorizonHasNoEnd`). |

## Questions ouvertes

_(aucune)_
