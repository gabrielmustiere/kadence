# Report — Verrouiller la saisie des jours fériés selon le calendrier français ou belge de chacun

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/004-f-jours-feries/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 85 % (35 des 41 fichiers du périmètre livrés exactement comme prévu). Écarts structurants :
  - formulaire et DTO d'ajout renommés `HolidayAdditionType` / `HolidayAdditionInput` (collision avec l'enum `HolidayAdjustmentType`) ;
  - retrait sur `POST /jours-feries/retirer`, sans l'année ;
  - ajustements de test purgés après chaque test, au lieu d'une date différente par test.
- **Critères** : 15 / 15 cochés.
- **Review** : 0 bloquant, 0 important ; 4 mineurs, tous corrigés pendant la passe. Statut PRÊT À COMMITER.
- **Périmètre livré** : 20 fichiers de code créés (~1 330 lignes) et 24 fichiers suivis modifiés (+300 / −35), plus les deux fichiers de l'ADR-0002, hors documents de story.

La story livre ce que le plan prévoyait :
- les calendriers France et Belgique, calculés à la volée ;
- les ajustements de la direction (ajout, retrait, annulation) ;
- le rattachement de chaque personne sur la fiche Équipe ;
- le verrou absolu et le plafond de semaine dans la grille ;
- la page « Jours fériés » ;
- les fixtures belges ;
- les scénarios E2E.

Les écarts sont des renommages, une route simplifiée, un jeton de couleur et une méthode d'accès ajoutée pour le validateur. La question du sort des temps saisis sur un jour qui devient férié reste ouverte avant la mise en production.

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Enum/Type/HolidayCalendar.php` | Enum `fr` / `be` avec `label()` (« France », « Belgique »). | Oui |
| `src/Enum/Type/HolidayAdjustmentType.php` | Enum `added` / `removed`. | Oui |
| `src/Service/LegalHolidays.php` | Calcul pur des jours légaux d'une année pour un calendrier, week-ends compris, via `easter_days()`. | Oui |
| `src/Entity/HolidayAdjustment.php` | Ajustement d'un calendrier à une date, créé par `added()` (libellé obligatoire) ou `removed()`. Unique par (calendrier, jour). | Oui |
| `src/Repository/HolidayAdjustmentRepository.php` | `findBetween()`, `findOneAt()`. | Oui |
| `src/Service/HolidayManager.php` | Porte d'entrée unique :<br>• `holidaysOf()`, `isHoliday()`, `yearOf()` ;<br>• `add()`, `remove()`, `cancel()` ;<br>• `adjustmentAt()` (ajout). | Oui |
| `src/Model/Holiday/HolidayLine.php` | Ligne de la vue annuelle : jour, libellé, ajustement, et `isAdded()`, `isRemoved()`, `isWeekend()`. | Oui |
| `src/Exception/HolidayAdjustmentRefusedException.php` | Refus d'un retrait : `notALegalHoliday()`, `alreadyRemoved()`. | Oui |
| `src/Dto/HolidayAdditionInput.php` | Données du formulaire d'ajout, avec contrainte de classe `AddableHoliday`. | Écart volontaire (cf. §Écarts) |
| `src/Validator/AddableHoliday.php` | Contrainte de classe (cible `day`). | Oui |
| `src/Validator/AddableHolidayValidator.php` | Refuse un week-end, un jour légal retiré ou un jour déjà férié, via `HolidayManager`. | Oui |
| `src/Form/HolidayAdditionType.php` | Choix du calendrier, date, libellé. | Écart volontaire (cf. §Écarts) |
| `src/Controller/HolidayController.php` | Routes :<br>• `GET\|POST /jours-feries` et `/jours-feries/{année}` (année entre 1000 et 9999) ;<br>• `POST /jours-feries/retirer` ;<br>• `POST /jours-feries/ajustements/{id}/annuler`. | Écart volontaire (cf. §Écarts) |
| `templates/holiday/index.html.twig` | Navigation d'année bornée entre 1000 et 9999, formulaire d'ajout, une carte par calendrier avec badges et actions. | Oui |
| `migrations/Version20260930084254.php` | Générée : table `holiday_adjustment` et `user.holiday_calendar` (`DEFAULT 'fr'`). | Oui |
| `tests/Unit/Service/LegalHolidaysTest.php` | France et Belgique 2026 exactes, fêtes mobiles 2027 et 2028 dans les deux calendriers. | Oui |
| `tests/Service/HolidayManagerTest.php` | Calendrier de la personne, semaine 2026-W53, ajout, retrait, annulation, refus, `yearOf()`. | Oui |
| `tests/Controller/HolidayControllerTest.php` | Accès, vue 2026, ajout et annulation, refus d'ajout, retrait, CSRF, années hors bornes. | Oui |
| `tests/e2e/holidays.spec.ts` | Six scénarios : ajout, grille sur ordinateur, grille sur téléphone, annulation, retrait, accès refusé au lead. | Oui |
| `tests/Support/PurgesHolidayAdjustments.php` | Trait qui purge les ajustements de 2031 et après à la fin de chaque test. | Non (ajout — cf. §Écarts) |
| `docs/adr/0002-jours-feries-calcules-et-ajustements.md` | ADR du modèle des jours fériés, produit par `/forge:adr`. | Non (ajout — cf. §Écarts) |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `composer.json` / `composer.lock` | Ajout de `"ext-calendar": "*"` dans `require`. | Oui |
| `src/Entity/User.php` | Ajout de `holidayCalendar` (enum, défaut `France`, colonne `DEFAULT 'fr'`), avec son getter et son setter. | Oui |
| `src/Service/WeeklyMaxManager.php` | Ajout de `capFor(User, Week, int $holidayCount): int<0, 20>`. | Oui |
| `src/Model/Timesheet/TimesheetDay.php` | Ajout de `?string $holiday` (libellé), sans méthode `isHoliday()`. | Écart volontaire (cf. §Écarts) |
| `src/Model/Timesheet/WeekGrid.php` | Non modifié. | Écart volontaire (cf. §Écarts) |
| `src/Service/TimesheetBuilder.php` | Injection de `HolidayManager`. Cases verrouillées les jours fériés, libellé dans `TimesheetDay`, jours fériés jamais oubliés, plafond via `capFor()`. | Oui |
| `src/Service/TimesheetManager.php` | Injection de `HolidayManager`. Refus de tout enregistrement sur un jour férié avant les plafonds, plafond via `capFor()`. | Oui |
| `src/Exception/TimeEntryRefusedException.php` | Ajout de `holiday($day, $label)`. | Oui |
| `templates/components/Timesheet.html.twig` | Colonne fériée (en-tête, cellules sans barre, onglet de téléphone, avis `holiday-notice`) sur fond `bg-neutral-tertiary`. | Écart volontaire (cf. §Écarts) |
| `src/Dto/TeamMemberInput.php` | Ajout de `holidayCalendar` (`NotNull`, défaut `France`), repris par `fromUser()`. | Oui |
| `src/Form/TeamMemberType.php` | Ajout de `holidayCalendar` en boutons radio, libellé « Jours fériés », aide « pas la nationalité ». | Oui |
| `src/Service/TeamManager.php` | `apply()` recopie `holidayCalendar`. | Oui |
| `templates/team/_form.html.twig` | Ajout de `form_row(form.holidayCalendar)`. | Oui |
| `config/packages/security.yaml` | Ajout de `access_control` `^/jours-feries` pour `ROLE_DIRECTION`. | Oui |
| `templates/base.html.twig` | Entrée de menu « Jours fériés » (`nav-holidays`, `tabler:calendar-off`) réservée à la direction. | Oui |
| `fixtures/DemoCompanyFixtures.php` | Hugo et Inès rattachés à la Belgique, jours de remplacement des jours légaux belges tombés un week-end, aucun temps sur un jour férié. | Oui |
| `tests/Service/TimesheetBuilderTest.php` | Semaine 2026-W29 pour la France (verrou, libellé, oubli, maximum 16 quarts pour 20 et 18) et pour la Belgique. | Oui |
| `tests/Service/TimesheetManagerTest.php` | Refus sur le 14/07 même à 0, acceptation pour la Belgique, plafond réduit. `quartersOf()` accepte un intervalle. | Oui |
| `tests/Service/WeeklyMaxManagerTest.php` | `capFor()` avec 0, 1, 2, 3 et 5 jours fériés. | Oui |
| `tests/Twig/Components/TimesheetTest.php` | Colonne fériée sans cran, total « 1 j / 4 j », message de refus. `component()` et `quartersOf()` paramétrables. | Oui |
| `tests/Controller/TeamControllerTest.php` | France par défaut à l'inscription, Belgique enregistrée en modification. | Oui |
| `tests/e2e/timesheet.spec.ts` | Neutralisation du calendrier France sur la semaine en cours et la précédente (retraits `Neutralisation E2E`, purgés avant et après). | Oui |
| `tests/Support/CreatesUsers.php` | `createUser()` accepte un calendrier. | Non (ajout — cf. §Écarts) |
| `docs/adr/README.md` | Ligne de l'ADR-0002 dans l'index. | Non (ajout — cf. §Écarts) |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| DTO `src/Dto/HolidayAdjustmentInput.php` et formulaire `src/Form/HolidayAdjustmentType.php` | `HolidayAdditionInput` et `HolidayAdditionType` | Le formulaire `App\Form\HolidayAdjustmentType` aurait porté le même nom court que l'enum `App\Enum\Type\HolidayAdjustmentType`. Le formulaire ne sert qu'aux ajouts, d'où le nom commun « Addition » pour le DTO et le formulaire. |
| `POST /jours-feries/{année}/retirer` | `POST /jours-feries/retirer` | L'année faisait doublon avec la date postée. La redirection se fait vers l'année du jour retiré. |
| Route `/jours-feries/{année}` avec `\d{4}` | Contrainte `[1-9]\d{3}`, liens d'année masqués aux bornes 1000 et 9999 | Finding de review **[ROBUSTESSE] Une année hors 1000–9999 fait planter la page « Jours fériés »** : `easter_days(0)` échouait, et les liens hors bornes ne pouvaient pas être générés. |
| `TimesheetDay` : `?string $holiday` et `isHoliday()` | `?string $holiday` seul | Finding de review **[STYLE] `TimesheetDay::isHoliday()` n'est jamais appelée** : le gabarit lit la propriété. |
| `WeekGrid::maxQuarters` passe à `int<0, 20>` | `WeekGrid` inchangé | Sans objet : la propriété est typée `int` sans PHPDoc, et un maximum à 0 est déjà accepté. |
| Colonne fériée sur les tokens `neutral-secondary-soft` / `fg-disabled` | `bg-neutral-tertiary` (gray-100) | `neutral-secondary-soft` vaut gray-50 et rendait la colonne indiscernable à l'écran (constat par capture pendant l'implémentation). |
| Validateur appuyé sur `HolidayManager::isHoliday()` et `findOneAt()` | `isHoliday()` et `HolidayManager::adjustmentAt()` | Le validateur ne dépend que de `HolidayManager`, conformément à la règle « porte d'entrée unique » du plan. |
| Convention de test : dates de 2031 et après, une date différente par test | Dates de 2031 et après, purgées après chaque test par le trait `PurgesHolidayAdjustments` | Les tests se relancent avec `bin/phpunit` sans réinitialiser la base de test, ce qu'une simple date par test ne garantissait pas. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| Aucun | — | — |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `HolidayManager::adjustmentAt()` | Permet au validateur de repérer un jour légal retiré sans passer par le repository. |
| `tests/Support/PurgesHolidayAdjustments.php` | Isole les tests qui écrivent des ajustements (cf. convention de test ci-dessus). |
| Paramètre `holidayCalendar` de `CreatesUsers::createUser()`, intervalle de `quartersOf()`, semaine de `component()` et de `build()` dans les tests | Nécessaires pour tester des personnes rattachées à la Belgique et des semaines de juillet 2026, sans changer le comportement des tests existants (valeurs par défaut inchangées). |
| `docs/adr/0002-jours-feries-calcules-et-ajustements.md` et sa ligne dans `docs/adr/README.md` | Décision gravée par `/forge:adr` entre le plan et l'implémentation. |
| Test `testYearsOutsideFourDigitsAreNotFound` | Couvre la correction du finding de review [ROBUSTESSE]. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Service/LegalHolidays.php` | unit | unit, 6 cas (France et Belgique 2026 exactes, fêtes mobiles 2027 et 2028 dans les deux calendriers) | Fait |
| `src/Service/HolidayManager.php` | functional (base) | functional, 7 cas | Fait |
| `src/Service/WeeklyMaxManager.php` | functional (base) | functional, `capFor()` avec 0, 1, 2, 3 et 5 jours fériés | Fait — couverture étendue |
| `src/Service/TimesheetBuilder.php` | functional (base) | functional, 2 cas (France, Belgique) | Fait |
| `src/Service/TimesheetManager.php` | functional (base) | functional, 2 cas (refus même à 0, acceptation pour l'autre calendrier, plafond réduit) | Fait |
| `src/Twig/Components/Timesheet.php` | functional | functional : message de refus, et aussi colonne sans cran et total de la semaine | Fait — couverture étendue |
| `src/Controller/TeamController.php` | functional | functional, défaut France et modification vers Belgique | Fait |
| `src/Controller/HolidayController.php` + `AddableHolidayValidator` | functional | functional, 11 cas, dont les années hors bornes (ajout de review) | Fait — couverture étendue |
| `tests/e2e/holidays.spec.ts` | E2E | E2E, 6 scénarios | Fait |
| `tests/e2e/timesheet.spec.ts` | E2E | E2E, neutralisation, 10 scénarios existants verts | Fait |
| `AddableHolidayValidator` isolé | hors scope assumé | pas écrit | Conforme : dépend de `HolidayManager`, classe finale sans double, et couvert par le contrôleur |
| Fixtures de démonstration | hors scope assumé | pas écrit ; contrôle manuel par SQL après `make db-reset` | Conforme : deux personnes rattachées à la Belgique, un remplacement (17/08 pour le 15/08), aucun temps sur un jour férié du calendrier de chacun |
| Migration | hors scope assumé | `doctrine:schema:validate` et aller-retour `migrate` / `migrate prev` en local | Conforme |

Derniers résultats consignés :
- à la fin de l'implémentation : 199 tests PHPUnit, 24 E2E et `make lint` verts ;
- après les corrections de la review : 199 tests PHPUnit et les 6 E2E de `holidays.spec.ts` verts, ainsi que PHPStan niveau 10 et `lint:twig`.

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Dans la grille d'une personne rattachée à la France, le 11 novembre 2026 est affiché grisé avec « Férié · Armistice 1918 », et aucun cran n'y est actif.
- [x] Le 14 juillet est férié et le 21 juillet ordinaire pour une personne rattachée à la France ; l'inverse vaut pour une personne rattachée à la Belgique. Le 8 mai n'est férié que pour la France.
- [x] Les fêtes mobiles suivent l'année : en 2027, le lundi de Pâques (29 mars), l'Ascension (6 mai) et le lundi de Pentecôte (17 mai) sont fériés dans les deux calendriers.
- [x] Un enregistrement sur un jour férié, par exemple depuis un onglet ouvert avant l'ajout de ce jour, est refusé avec un message, et la case reprend sa valeur précédente.
- [x] Un jour férié n'est jamais signalé comme un oubli, et sa colonne ne passe pas au vert.
- [x] Sur une semaine qui contient un jour férié, l'en-tête affiche un maximum de 4 j pour une personne à 5 j comme pour une personne à 4,5 j, et la semaine est complète, sans signal d'oubli, une fois 4 j saisis.
- [x] Sur un écran de téléphone, un jour férié est présenté verrouillé avec son libellé dans la présentation jour par jour.
- [x] La direction choisit France ou Belgique sur la fiche d'une personne. Une personne inscrite sans choix, ou déjà inscrite avant la feature, suit le calendrier France. Ni un lead ni un membre de prod ne peut modifier ce rattachement.
- [x] Passer une personne de France à Belgique change ses jours verrouillés sur toutes les semaines, passées comprises.
- [x] La page « Jours fériés » liste, pour l'année choisie et pour chaque calendrier, les jours fériés avec leur date et leur libellé, y compris ceux qui tombent un week-end, signalés comme tels.
- [x] La direction ajoute un jour férié à un calendrier avec un libellé. Ce jour est alors verrouillé dans la grille des seules personnes rattachées à ce calendrier, avec ce libellé.
- [x] La direction retire un jour férié légal d'un calendrier, et ce jour redevient saisissable pour les personnes rattachées à ce calendrier. Annuler ce retrait le rend de nouveau férié.
- [x] Un ajustement ne vaut que pour sa date : retirer le lundi de Pentecôte 2027 laisse férié le lundi de Pentecôte 2028.
- [x] Ajouter un jour déjà férié, ajouter un samedi ou un dimanche, ajouter un jour sans libellé, ou retirer un jour qui n'est pas férié légal est refusé avec un message.
- [x] Un lead ou un membre de prod n'accède pas à la page « Jours fériés ».

Moyens de vérification des critères cités sur une date :
- **11 novembre** : vérifié sur le 14/07/2026 (tests de la grille et du composant) et sur 2030-W24 en E2E. Le verrou ne dépend pas de la date.
- **Pentecôte 2027 et 2028** : vérifiée sur 2031 et 2032 (`HolidayManagerTest`), convention des dates de test de 2031 et après.
- **Onglet ouvert avant l'ajout** : le refus a lieu à chaque enregistrement dans `TimesheetManager`, qui recalcule les jours fériés.
- **Passage de France à Belgique** : le calendrier est lu à chaque rendu, sans historique (règle 11 du pitch).

## Dette technique identifiée

Issus de la review (mineurs non traités) :

_(aucun — les 4 mineurs ont été corrigés pendant la passe)_

Au-delà de la review :

1. **Temps saisis sur un jour qui devient férié** (question ouverte du pitch et du plan). Aujourd'hui, ils restent en base, comptent dans les totaux et ne sont pas affichés dans la colonne fériée. Il faut trancher entre deux options : autoriser seulement la baisse, ou refuser l'ajustement et le changement de calendrier tant que des temps existent. **Critique** avant la mise en production.
2. **`ext-calendar` chez l'hébergeur de production** (suite obligatoire de l'ADR-0002) : à vérifier au choix de l'hébergement. **Critique** au premier déploiement.
3. **Ligne de backlog `capacite-equipe`** : la story y est rattachée. Sa livraison cochera la ligne, alors que le temps de travail détaillé (C4.1), les absences (C4.2) et les fermetures (C4.3) ne sont pas livrés. À découper via `/product-backlog` si l'avancement doit rester exact.
4. **E2E de saisie** : la neutralisation (`INSERT OR IGNORE`) ne couvre pas un ajout réel du calendrier France dans la semaine en cours ou la précédente de la base de dev. Un tel ajout ferait échouer les scénarios qui saisissent le lundi.

## Leçons apprises

- **Vérifier les collisions de noms dès le plan** : un nom de classe prévu (`HolidayAdjustmentType` pour un formulaire) peut entrer en collision avec un type du même périmètre dans un autre namespace. Le plan listait les deux sans le voir.
- **Donnée partagée en test** : la base de test n'est pas réinitialisée entre les tests. Toute donnée commune à plusieurs personnes, comme un calendrier, fuit vers les autres tests. Il faut prévoir la purge au plan, pas seulement une convention de dates.
- **Base de test polluée après un run interrompu** : relancer `make phpunit`, qui recrée la base de test, plutôt que `bin/phpunit` seul après un échec. Un passage a échoué parce que `admin@example.com` était resté désactivé.
- **Service sans consommateur** : un service que personne n'utilise encore est retiré du conteneur de test. Un test fonctionnel écrit avant son premier consommateur échoue tant que ce consommateur n'existe pas.
- **Assets précompilés en dev** : `public/assets/`, laissé par un `make build` de release, masque en dev les nouvelles classes Tailwind (`gap-6` manquait à l'écran). Il faut le supprimer après un build de release. Seule une capture d'écran a révélé le problème.
- **Le plafond de semaine réduit sert surtout à l'affichage** : quatre jours ouvrés plafonnent déjà la saisie à 16 quarts. `capFor()` rend la semaine complète et éteint les faux oublis. Il ne refuse une saisie qu'en présence de temps déjà posés sur un jour férié.
