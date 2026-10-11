# Report — Poser ses congés et ses RTT auprès de son manager, et les déduire de la saisie et de la capacité

> **But** : constater l'écart entre l'intention et le code livré — écarts, dette, suites.
> **Registre** : factuel
> **Story** : `docs/story/014-f-conges/`
> **Amont** : `pitch.md` · `plan.md` · `review.md`

## Synthèse

- **Conformité au plan** : 90 % (85 lignes sur 94 livrées telles que prévues). Les écarts structurants : les lectures d'équipe sorties de `TimeOffBook` dans `TimeOffOverview`, les absences de la roadmap calculées par `TeamAbsences`, et les soldes calculés en lot par `Balance::count()` après la review.
- **Critères** : 28 / 28 cochés.
- **Review** : 0 bloquant ; 2 importants, dont 1 corrigé (requêtes en boucle) et 1 arbitré (aucun e-mail en production, assumé pour la démonstration) ; 7 mineurs sur 7 corrigés. Statut : PRÊT À COMMITER SOUS RÉSERVE.
- **Périmètre livré** : 63 fichiers créés et 39 modifiés (hors `docs/`), ~5 380 lignes ajoutées, 60 retirées.

Le module de congés est livré de bout en bout : demande à la journée ou à la demi-journée, décision par le manager ou la direction, droits par période, verrou de la saisie, congés validés déduits de la capacité, onglets Congés, e-mails, absences sur la roadmap et les fiches. Deux tests prévus ne sont pas écrits (`PersonLoadTest`, `ScheduleLoaderTest`). La règle 43 du pitch (périodes de « Mes soldes ») a été resserrée en review. 626 tests PHPUnit et 49 E2E passent, `make lint` est propre (consigné par l'implémentation et la review).

## Périmètre livré

### Fichiers créés

| Fichier | Rôle | Prévu dans le plan |
|---|---|---|
| `src/Enum/Type/TimeOffType.php` | Les quatre types, leur libellé, `hasBalance()` et `withBalance()` ; le mois de début de période vit dans `TimeOffPeriod`. | Oui |
| `src/Enum/Type/TimeOffStatus.php` | Les quatre statuts, leur libellé et `isActive()`. | Oui |
| `src/Enum/Type/HalfDay.php` | Matin ou après-midi, avec leur libellé. | Oui |
| `src/Entity/TimeOffRequest.php` | La demande, avec ses bornes, sa décision et son annulation (`cancelledBy` nul à la désactivation). | Oui |
| `src/Entity/TimeOffEntitlement.php` | Les droits d'une personne pour un type et une période, en demi-journées. | Oui |
| `src/Repository/TimeOffRequestRepository.php` | `findActiveOfUserBetween()`, `findActiveOfUser()`, `findActiveBetween()`, `findApprovedBetween()`, `findApprovedOfUser()`, `findOfUser()`, `findPendingOfUser()`, `findPendingDecidableBy()` et `countPendingDecidableBy()`. | Écart volontaire (cf. §Écarts) |
| `src/Repository/TimeOffEntitlementRepository.php` | `findOneFor()`, `findForPeriod()` et `findHalfDaysOf()` (droits de plusieurs personnes, par clé de période). | Écart volontaire (cf. §Écarts) |
| `migrations/Version20261011094243.php` | Création des deux tables, de leurs index et de la contrainte d'unicité, générée par `make migration`. | Oui |
| `src/Model/TimeOff/TimeOffPeriod.php` | Période d'un type contenant un jour, ses bornes, son libellé et sa clé `key()`. | Oui |
| `src/Model/TimeOff/TimeOffDays.php` | Dépliage des bornes en demi-journées ouvrées, et décompte global ou par période. | Oui |
| `src/Model/TimeOff/TimeOffDay.php` | Les demi-journées posées sur un jour de la grille, leur libellé, le plafond du jour, et `collect()` qui les regroupe par jour. | Oui |
| `src/Model/TimeOff/Balance.php` | Droits, validés, en attente et solde pour un type et une période, et `count()` qui le calcule à partir des demandes d'une personne. | Oui |
| `src/Model/TimeOff/TimeOffPreview.php` | Décompte par période et solde après une demande, avec le dépassement, et `of()` qui le construit. | Oui |
| `src/Model/TimeOff/AbsenceMonth.php` | Grille du mois de la page « Absences », avec le nom du mois en français. | Oui |
| `src/Model/Roadmap/RoadmapAbsence.php` | Une absence d'un membre d'une feuille, nommée dans l'infobulle. | Oui |
| `src/Service/TimeOffBook.php` | Lectures d'une personne : jours posés, historique, soldes, aperçu, et congés validés de tous pour la capacité. | Écart volontaire (cf. §Écarts) |
| `src/Service/TimeOffManager.php` | Écritures : `request()` (avec la validation d'office de la règle 20), `approve()`, `refuse()`, `cancel()` et `cancelPendingOf()`, puis les e-mails. | Oui |
| `src/Service/TimeOffApprovers.php` | Valideurs d'une demande, « peut décider », « peut voir le détail » et « a une liste à traiter ». | Oui |
| `src/Service/TimeOffEntitlementManager.php` | Déclaration des droits de toute l'équipe pour une période et un type. | Oui |
| `src/Exception/TimeOffRefusedException.php` | Transitions refusées : demande déjà décidée, congé commencé, refus sans motif. | Oui |
| `src/Dto/TimeOffRequestInput.php` | Données du formulaire de demande, avec le demandeur, posé par le composant. | Oui |
| `src/Dto/TimeOffEntitlementsInput.php` | Droits en jours par personne pour une période et un type. | Oui |
| `src/Form/TimeOffRequestType.php` | Type, début et demi-journée, fin et demi-journée, commentaire. | Oui |
| `src/Form/TimeOffEntitlementsType.php` | Un champ de droits (pas de 0,5) par personne active. | Oui |
| `src/Validator/RequestableTimeOff.php` | Contrainte de classe de la règle 14. | Oui |
| `src/Validator/RequestableTimeOffValidator.php` | Fin avant début, aucun jour ouvré, chevauchement d'une demi-journée active, temps déjà saisi au-delà de ce que le congé laisse. | Oui |
| `src/Security/Voter/TimeOffVoter.php` | `TIME_OFF_DECIDE`, `TIME_OFF_CANCEL`, `TIME_OFF_DETAIL` (sur une personne) et `TIME_OFF_INBOX` (sans sujet). | Oui |
| `src/Mailer/TimeOffMailer.php` | E-mails de demande, de décision et d'annulation (règle 45), à contexte scalaire. | Oui |
| `src/Controller/TimeOffController.php` | Mes congés, À valider (valider, refuser), annuler, Absences, Compteurs ; droits vérifiés avant le jeton CSRF, `#[CurrentUser]`. | Écart volontaire (cf. §Écarts) |
| `src/Twig/Components/TimeOffRequestForm.php` | Live Component du formulaire de demande avec aperçu du décompte et du solde. | Oui |
| `templates/components/TimeOffRequestForm.html.twig` | Formulaire, aperçu par période et dépassement signalé. | Oui |
| `templates/time_off/_tabs.html.twig` | Onglets Mes congés, À valider (avec le nombre en attente), Absences et Compteurs, selon les droits. | Oui |
| `templates/time_off/index.html.twig` | Mes congés : formulaire, soldes et historique avec annulation. | Oui |
| `templates/time_off/inbox.html.twig` | À valider : demandes avec solde après, dépassement et autres absents (sauf sans manager), validation et refus en modale. | Oui |
| `templates/time_off/absences.html.twig` | Grille du mois, navigation de mois en mois, demi-journées. | Oui |
| `templates/time_off/entitlements.html.twig` | Choix de la période et du type, tableau de l'équipe, enregistrement unique. | Oui |
| `templates/time_off/_request.html.twig` | Ligne d'une demande dans Mes congés. | Oui |
| `templates/emails/_layout.html.twig` | Gabarit commun des e-mails. | Oui |
| `templates/emails/time_off/requested.html.twig` | Nouvelle demande, au valideur. | Oui |
| `templates/emails/time_off/decided.html.twig` | Validation ou refus avec motif, à la personne, ou information du manager remplacé. | Oui |
| `templates/emails/time_off/cancelled.html.twig` | Annulation d'un congé validé. | Oui |
| `templates/roadmap/_absences.html.twig` | Bande hachurée des absences de l'équipe sous la ligne d'une feuille, une marque par absence. | Oui |
| `templates/person/_absences.html.twig` | Bloc « Absences » de la fiche d'une personne, avec annulation selon le voter. | Oui |
| `tests/Support/CreatesTimeOff.php` | Trait de création de demandes et de droits pour les tests. | Oui |
| `tests/Unit/Model/TimeOff/TimeOffPeriodTest.php` | Périodes CP et RTT, bornes du 30/04 au 01/05 et du 31/12 au 01/01, libellés. | Oui |
| `tests/Unit/Model/TimeOff/TimeOffDaysTest.php` | Dépliage : après-midi de début, matin de fin, demi-journée isolée, week-ends, jours fériés, décompte par période. | Oui |
| `tests/Unit/Model/TimeOff/BalanceTest.php` | Solde, négatif compris. | Oui |
| `tests/Unit/Security/Voter/TimeOffVoterTest.php` | Décider, annuler et lire le détail selon le rôle, le lien de manager et le statut. | Oui |
| `tests/Service/TimeOffBookTest.php` | Jours posés, soldes et leurs périodes, historique, aperçu, décompte vivant, démo sans temps sur un jour posé. | Oui |
| `tests/Service/TimeOffManagerTest.php` | Transitions, `cancelPendingOf()`, annulation par la direction, e-mails envoyés et destinataires. | Oui |
| `tests/Service/TimeOffApproversTest.php` | Manager actif, direction sans manager, jamais soi-même, dernier membre de la direction (repository en `createStub()`). | Oui |
| `tests/Unit/Validator/RequestableTimeOffValidatorTest.php` | Chacun des refus de la règle 14 et les cas acceptés (deux demi-journées le même jour, jours passés), avec les repositories en `createStub()`. | Oui |
| `tests/Controller/TimeOffControllerTest.php` | Onglets, décider, refuser sans motif, 403, jeton invalide, annuler avant et après le début, chemin de retour, liste sans manager, nombre de requêtes, absences et type masqué, compteurs. | Oui |
| `tests/Twig/Components/TimeOffRequestFormTest.php` | Aperçu du décompte et du solde, dépassement, demande créée, demande en chevauchement refusée sans enregistrement. | Écart volontaire (cf. §Écarts) |
| `tests/e2e/time-off.spec.ts` | Demande, verrou dans la grille, validation par le manager, page Absences. | Oui |
| `src/Service/TimeOffOverview.php` | Lectures de l'équipe : mois d'absences, liste à valider avec aperçus et collègues absents, soldes de l'équipe sur une période, chargés en lot. | Non (ajout — cf. §Écarts) |
| `src/Service/TeamAbsences.php` | Absences validées des membres de chaque feuille planifiée, sur ses jours dans la fenêtre. | Non (ajout — cf. §Écarts) |
| `src/Model/TimeOff/AbsenceRow.php` | Une personne de la page Absences : jours fériés, jours posés, et droit de voir le détail. | Non (ajout — cf. §Écarts) |
| `templates/time_off/_period.html.twig` | Dates d'une demande avec ses demi-journées aux bornes. | Non (ajout — cf. §Écarts) |
| `templates/roadmap/_absence_period.html.twig` | Dates d'une absence de la roadmap. | Non (ajout — cf. §Écarts) |
| `templates/emails/time_off/_summary.html.twig` | Récapitulatif commun aux e-mails (type, dates, décompte, commentaire). | Non (ajout — cf. §Écarts) |
| `tests/Unit/Model/TimeOff/TimeOffDayTest.php` | Plafond et libellé d'un jour entier, d'une demi-journée et de deux demi-journées différentes. | Non (ajout — cf. §Écarts) |
| `tests/Unit/Service/TimeOffManagerTest.php` | Validation d'office du seul membre actif de la direction, sans e-mail. | Non (ajout — cf. §Écarts) |

### Fichiers modifiés

| Fichier | Modification | Prévu dans le plan |
|---|---|---|
| `src/Model/WeeklyMaximum.php` | `cap()` prend en plus les demi-journées de congé de la semaine et applique le prorata, arrondi au quart inférieur. | Oui |
| `src/Service/WeeklyMaxManager.php` | `capFor()` transmet les demi-journées de congé. | Oui |
| `src/Model/Timesheet/TimesheetDay.php` | Ajout de `capQuarters` et `timeOff` ; `isComplete()` revu. | Oui |
| `src/Service/TimesheetBuilder.php` | Jours posés lus par `TimeOffBook` (jours fériés déjà lus transmis), plafond par jour, cellule verrouillée à plafond 0, pas d'oubli, demi-journées passées au maximum de la semaine. | Oui |
| `src/Service/TimesheetManager.php` | Refus au-delà du plafond du jour (`timeOff()` si le jour est entièrement posé) et demi-journées passées au maximum de la semaine. | Oui |
| `src/Exception/TimeEntryRefusedException.php` | Ajout de `timeOff(day, label)` ; `dayFull()` reçoit le reste du jour calculé sur son plafond. | Oui |
| `templates/components/Timesheet.html.twig` | En-tête, onglet de jour et notice mobile « Congé · … », cellule verrouillée ou plafonnée, attributs `data-time-off`. | Oui |
| `src/Model/Schedule/DailyCapacity.php` | Congés validés en paramètre optionnel ; `isWorkingDay()` exclut les jours entiers, répartition de la semaine sur les jours prévus, demi-unités, `DAY_DIVISOR` à 120 ; `timeOffHalves()` (0 les week-ends et jours fériés) et `timeOffSpans()` pour l'affichage. | Oui |
| `src/Service/ScheduleLoader.php` | Congés validés chargés de `firstDay` à l'horizon par `TimeOffBook::approvedHalfDaysBetween()`. | Oui |
| `src/Model/Person/PersonLoad.php` | `MAX_DAYS_OFF` passe de 31 à 120, et barres des jours de congé (`timeOff`) sur la ligne de charge. | Oui |
| `src/Model/Roadmap/RoadmapRow.php` | Ajout d'`absences` seulement. | Écart volontaire (cf. §Écarts) |
| `src/Service/RoadmapBuilder.php` | Absences fournies par `TeamAbsences` et passées à chaque feuille ; `reach()` devient publique. | Écart volontaire (cf. §Écarts) |
| `templates/roadmap/_row.html.twig` | Inclusion de `_absences.html.twig` sous les barres d'une feuille. | Oui |
| `templates/roadmap/_tooltip.html.twig` | Absences de l'équipe sur la période de la barre, sans type. | Oui |
| `templates/roadmap/_legend.html.twig` | Entrée « Absence d'un membre de l'équipe ». | Oui |
| `src/Model/Person/PersonRoadmap.php` | Ajout des absences validées, avec `upcomingAbsences()` et `pastAbsences()`. | Oui |
| `src/Service/PersonRoadmapBuilder.php` | Absences validées de la personne (`findApprovedOfUser()`). | Oui |
| `templates/person/show.html.twig` | Bloc « Absences » et barres des jours de congé sur la ligne de charge. | Oui |
| `src/Service/TeamManager.php` | `deactivate()` annule les demandes en attente de la personne (`cancelPendingOf()`), sans e-mail. | Oui |
| `src/Repository/UserRepository.php` | `findActiveDirectors()`, `findActiveWithManager()` et `hasActiveReports()`. | Écart volontaire (cf. §Écarts) |
| `src/Repository/TimeEntryRepository.php` | Non modifié : `findForUserBetween()` suffit à la contrainte. | Oui |
| `src/Twig/NavigationExtension.php` | Section `time_off` sur le préfixe `app_time_off_`. | Oui |
| `templates/base.html.twig` | Entrée de menu « Congés » pour tous, après « Ma semaine ». | Oui |
| `config/packages/security.yaml` | `access_control` : `^/conges/compteurs` réservé à `ROLE_DIRECTION`. | Oui |
| `.env` | `MAILER_SENDER`, l'adresse d'expédition des e-mails. | Oui |
| `.env.test` | `MAILER_DSN=null://null`, pour qu'aucun test n'appelle Mailpit. | Oui |
| `fixtures/DemoCompanyFixtures.php` | Droits CP et RTT de chacun, les deux semaines sans saisie de chaque personne en CP validés, sept congés à venir de tous statuts hors équipe Atlas, aucun sur les comptes de test. | Oui |
| `tests/Unit/Model/WeeklyMaximumTest.php` | Exemples du prorata de la règle 33, avec et sans jour férié. | Oui |
| `tests/Unit/Model/Schedule/DailyCapacityTest.php` | Jour entier posé, demi-journée, unités inchangées des autres jours, tronçons de congé. | Oui |
| `tests/Unit/Model/Person/PersonLoadTest.php` | Non modifié. | Non (cf. §Non implémenté) |
| `tests/Service/WeeklyMaxManagerTest.php` | `capFor()` avec des demi-journées. | Oui |
| `tests/Service/TimesheetBuilderTest.php` | Jour verrouillé, demi-journée plafonnée à 2 quarts, pas d'oubli, maximum réduit, demi-journée complète à 2 quarts. | Oui |
| `tests/Service/TimesheetManagerTest.php` | Refus sur un jour posé en attente, rouverture après refus, demi-journée plafonnée. | Oui |
| `tests/Service/ScheduleLoaderTest.php` | Non modifié : le chargement des congés validés et l'oubli des demandes en attente sont couverts par `RoadmapBuilderTest`. | Non (cf. §Non implémenté) |
| `tests/Service/RoadmapBuilderTest.php` | Fin calculée 27/11 → 30/11 (en attente sans effet), coupe un jour entier contre une demi-journée, absences nommées, surcharge passive « à replanifier ». | Oui |
| `tests/Service/PersonRoadmapBuilderTest.php` | « libre à partir du … » qui saute les congés, absences à venir et passées, barres de congé sur la charge. | Oui |
| `tests/Unit/Service/TeamManagerTest.php` | `deactivate()` annule les demandes en attente, avec un vrai `TimeOffManager` monté sur des stubs. | Écart volontaire (cf. §Écarts) |
| `tests/Unit/Twig/NavigationExtensionTest.php` | Section `time_off` pour les routes `app_time_off_*`. | Oui |
| `tests/Controller/NavigationTest.php` | Entrée « Congés » présente pour tous les rôles et allumée sur les onglets Congés. | Oui |
| `src/Twig/DaysExtension.php` | Filtre `half_days` (demi-journées affichées en jours). | Non (ajout — cf. §Écarts) |
| `tests/Controller/PersonControllerTest.php` | Type des congés visible de la personne et de la direction, masqué pour la prod. | Non (ajout — cf. §Écarts) |
| `tests/Controller/TimesheetControllerTest.php` | Seuil de requêtes de la grille relevé de 6 à 7. | Non (ajout — cf. §Écarts) |

## Écarts avec le plan

### Écarts volontaires

| Prévu | Réalisé | Raison |
|---|---|---|
| `TimeOffBook` porte toutes les lectures, mois d'absences compris | Lectures d'une personne dans `TimeOffBook` ; mois d'absences, liste à valider et soldes de l'équipe dans `TimeOffOverview` ; regroupement par jour dans `TimeOffDay::collect()` | Plafond de complexité cognitive de PHPStan (classe à 43 pour 40). |
| `RoadmapBuilder` construit les absences, `RoadmapRow` reçoit `absences` et `absenceBand` | Service `TeamAbsences` appelé par `RoadmapBuilder` (`reach()` rendue publique) ; `RoadmapRow` ne porte que `absences`, dont chaque élément a sa barre | Plafond de complexité de `RoadmapBuilder` (48 pour 40) ; une liste unique suffit à dessiner la bande et l'infobulle. |
| Soldes calculés un par un (`balanceOf()`), historique par `daysOfRequest()` | `Balance::count()` et `TimeOffPreview::of()` nourris de données chargées en lot ; `TimeOffBook::historyOf()` ; `findHalfDaysOf()` à la place de `findForUser()` | Review, [PERF] Requêtes en boucle sur « Mes congés » et « À valider » (12 → 66 et 88 requêtes, désormais 8 et 10). |
| Méthodes de repository listées au plan | Ajout de `findActiveOfUser()`, `findActiveBetween()` et `findApprovedOfUser()` ; `findActiveWithManager()` à la place de `findActiveForAbsences()`, et `hasActiveReports()` | Besoins apparus à l'implémentation : liste de collègues absents, page Absences avec le manager de chacun, onglet « À valider ». |
| Jeton CSRF contrôlé avant le voter | Voter avant le jeton, comme `LotController::progress()` ; `#[CurrentUser]` dans les actions | Un 403 en test prouve un refus du voter ; review, [CONV] Utilisateur courant lu par une méthode privée. |
| `TeamManagerTest` : `createMock()` de `TimeOffManager` | Vrai `TimeOffManager` monté sur des stubs | `TimeOffManager` est `final readonly`, donc non simulable. |
| Test du formulaire : erreurs de la contrainte affichées | Refus vérifié (exception 422, rien enregistré) ; affichage vérifié dans le navigateur | Le composant de test lève l'exception au lieu de rendre la page. |
| Règle 43 : « toute période sur laquelle il a une demande » | Périodes en cours, plus les périodes non terminées où tombe une demande | Review, [UX] « Mes soldes » garde toutes les périodes passées qui ont eu une demande. |
| Annulation d'un congé commencé refusée à son auteur | Refusée seulement hors direction ; la direction annule à tout moment, y compris son propre congé | Review, [BUG] Un membre de la direction voit un bouton « Annuler » inopérant sur son propre congé commencé. |

### Non implémenté

| Élément prévu | Raison | Action requise |
|---|---|---|
| `tests/Unit/Model/Person/PersonLoadTest.php` : prochain jour ouvré au-delà de 31 jours de congé, barres de congé | Oubli. Les barres sont couvertes par `PersonRoadmapBuilderTest`, pas le relèvement de `MAX_DAYS_OFF` à 120. | Dette n° 2. |
| `tests/Service/ScheduleLoaderTest.php` : congés validés chargés, demandes en attente ignorées | Couvert de bout en bout par `RoadmapBuilderTest` (fin calculée inchangée pour une demande en attente, repoussée pour un congé validé). | Aucune. |

### Ajouts non prévus

| Élément ajouté | Raison |
|---|---|
| `TimeOffOverview`, `TeamAbsences`, `TimeOffDay::collect()` | Découpages imposés par les plafonds de complexité de PHPStan. |
| `AbsenceRow` et `AbsenceMonth::label()` | Ligne par personne de la page Absences ; nom du mois en français sans `twig/intl-extra`, non installé. |
| `Balance::count()`, `TimeOffPreview::of()`, `TimeOffPeriod::key()`, `TimeOffBook::historyOf()`, `TimeOffEntitlementRepository::findHalfDaysOf()` | Correctif de l'important [PERF] de la review. |
| `DailyCapacity::timeOffSpans()` et `timeOffHalves()` | Absences de la roadmap et barres de congé de la fiche, sans requête supplémentaire. |
| Filtre Twig `half_days` | Affichage des soldes et décomptes, stockés en demi-journées. |
| Partiels `time_off/_period`, `roadmap/_absence_period`, `emails/time_off/_summary` | Rendu partagé entre pages, infobulles et e-mails. |
| `TimeOffDayTest`, `Unit/Service/TimeOffManagerTest`, test du type sur `PersonControllerTest`, tests de review (chemin de retour, liste sans manager, nombre de requêtes, surcharge passive, annulation par la direction) | Couverture des règles 20, 32, 39 et 41, et des corrections de la review. La base de test partagée ne permet pas de contrôler le nombre de membres de la direction : la validation d'office est testée en unitaire. |
| Seuil de requêtes de la grille relevé de 6 à 7 (`TimesheetControllerTest`) | Une requête de plus pour lire les congés de la semaine ; les jours fériés déjà lus sont transmis à `daysOf()` pour éviter une seconde. |
| Annulation d'un congé par la direction depuis le bloc « Absences » de la fiche personne | Le plan ne fixait pas où la direction annule un congé commencé. |

## Tests

| Code | Type prévu | Type réalisé | Statut |
|---|---|---|---|
| `src/Model/TimeOff/TimeOffPeriod.php` | unit | unit, 9 cas | Fait |
| `src/Model/TimeOff/TimeOffDays.php` | unit | unit, 7 cas | Fait |
| `src/Model/TimeOff/Balance.php` | unit | unit, 2 cas ; `count()` couvert par `TimeOffBookTest` | Fait |
| `src/Model/TimeOff/TimeOffDay.php` | non prévu | unit, 3 cas | Fait — couverture étendue |
| `src/Model/WeeklyMaximum.php` | unit | unit, 2 cas ajoutés | Fait |
| `src/Model/Schedule/DailyCapacity.php` | unit | unit, 4 cas ajoutés | Fait — couverture étendue (tronçons) |
| `src/Security/Voter/TimeOffVoter.php` | unit | unit, 7 cas | Fait |
| `src/Service/TimeOffApprovers.php` | functional | functional, 6 cas (dont un repository en stub) | Fait |
| `src/Validator/RequestableTimeOffValidator.php` | unit | unit, 9 cas | Fait |
| `src/Service/TimeOffManager.php` | functional | functional, 11 cas + unit, 1 cas (validation d'office) | Fait |
| `src/Service/TimeOffBook.php` | functional | functional, 11 cas | Fait |
| `src/Service/TimesheetBuilder.php` | functional | functional, 2 cas ajoutés | Fait |
| `src/Service/TimesheetManager.php` | functional | functional, 2 cas ajoutés | Fait |
| `src/Service/ScheduleLoader.php` + `RoadmapBuilder.php` | functional | functional, 4 cas ajoutés dans `RoadmapBuilderTest` | Fait |
| `src/Service/PersonRoadmapBuilder.php` | functional | functional, 2 cas ajoutés | Fait |
| `src/Model/Person/PersonLoad.php` (`MAX_DAYS_OFF`) | unit | pas écrit | Manque mineur — risque : régression silencieuse sur un congé de plus de 31 jours |
| `src/Controller/TimeOffController.php` | functional | functional, 14 cas | Fait — couverture étendue |
| `src/Twig/Components/TimeOffRequestForm.php` | functional | functional, 4 cas, sans l'affichage des erreurs | Manque mineur — risque : régression d'affichage des erreurs non détectée (vérifié dans le navigateur) |
| `src/Service/TeamManager.php` | unit | unit, 1 cas | Fait |
| Liste des collègues absents dans « À valider » | functional (contrôleur) | absence vérifiée sans manager ; présence non testée | Manque mineur — risque faible, vérifié dans le navigateur |
| `tests/e2e/time-off.spec.ts` | E2E | E2E, 3 scénarios | Fait |
| Rendu fin des e-mails | hors scope assumé | destinataires, sujet, motif et décompte vérifiés | Conforme — hors scope du plan |
| E2E des compteurs et du refus | hors scope assumé | couverts par les tests fonctionnels du contrôleur | Conforme — hors scope du plan |
| E2E de la roadmap et de la fiche | hors scope assumé | couverts par `RoadmapBuilderTest` et `PersonRoadmapBuilderTest` | Conforme — hors scope du plan |

## Critères d'acceptation

Reprise des critères du `pitch.md` :

- [x] Alice, qui suit le calendrier France, demande des CP du lundi 9 au vendredi 13 novembre 2026. Le décompte annoncé est de 4 j, puisque le 11 novembre est férié.
- [x] Une demande de CP du jeudi 29 avril au mardi 4 mai 2027 décompte 2 j sur la période 2026-2027 et 2 j sur la période 2027-2028. Une demande de RTT du jeudi 31 décembre 2026 au lundi 4 janvier 2027 décompte 1 j sur 2026 et 1 j sur 2027.
- [x] Une demande du lundi 16 novembre après-midi au mercredi 18 novembre matin décompte 2 j.
- [x] Avec 25 j de CP déclarés pour 2026-2027 et 22 j déjà validés ou en attente, une demande de 5 j est acceptée. Alice voit un solde de −2 j avant l'envoi, et son manager voit le dépassement signalé.
- [x] Une personne sans droits déclarés pour une période voit un solde de 0 j, et toute demande de CP ou de RTT sur cette période est signalée en dépassement.
- [x] Un congé sans solde ou pour un événement familial ne modifie aucun solde, mais verrouille la saisie et, une fois validé, pèse sur la capacité.
- [x] Une demande dont la fin précède le début, une demande qui ne couvre que des week-ends ou des jours fériés, et une demande qui chevauche le même matin d'une autre demande en attente ou validée sont refusées avec un message.
- [x] Alice a saisi 1 j le 18 novembre. Sa demande pour la journée du 18 novembre est refusée avec un message. Avec 2 quarts saisis ce jour-là, elle peut demander l'après-midi du 18, mais pas la journée.
- [x] Alice peut avoir un CP le matin du 18 novembre et une RTT l'après-midi : le jour est alors entièrement verrouillé.
- [x] Dès sa demande envoyée, les jours posés sont verrouillés dans la grille d'Alice avec la mention « Congé · CP · en attente ». Si la demande est refusée ou annulée, ils redeviennent saisissables.
- [x] Un enregistrement de temps sur un jour de congé, depuis un onglet ouvert avant la demande, est refusé avec un message, et la case reprend sa valeur précédente.
- [x] Une demi-journée de congé laisse 2 quarts saisissables ce jour-là, et le jour est complet une fois ces 2 quarts saisis.
- [x] Le maximum d'une semaine sans jour férié passe de 5 j à 4 j avec un jour posé, de 4,5 j à 4 j avec un matin posé, et de 4 j à 3 j avec un jour posé. Une semaine entièrement posée est complète sans aucun temps saisi, sans signal d'oubli.
- [x] Alice, dont Bob est le manager, envoie une demande. Bob reçoit un e-mail, la voit dans sa liste à traiter, y lit le solde après la demande et voit que Charles, autre collaborateur de Bob, est absent sur une partie de la période.
- [x] Bob ne peut pas refuser sans motif. Après un refus, Alice reçoit un e-mail avec le motif et le voit dans « Mes congés ».
- [x] Un lead qui n'est pas le manager d'Alice ne peut pas décider de sa demande. Un membre de la direction le peut : Alice reçoit la décision et Bob en est informé par e-mail.
- [x] Une personne sans manager actif voit sa demande envoyée à chaque membre actif de la direction, qui peut la décider.
- [x] Bob ne voit pas ses propres demandes dans sa liste à traiter, et ne peut pas les décider.
- [x] Le seul membre actif de la direction, sans manager actif, voit sa demande validée dès qu'il la pose, sans e-mail.
- [x] Alice annule seule un congé validé qui commence demain ; ses jours reviennent au solde, sa grille se rouvre et Bob reçoit un e-mail. Elle ne peut plus annuler un congé commencé ; la direction le peut, et Alice en reçoit un e-mail.
- [x] Une feuille de 10 j qui démarre le lundi 16 novembre 2026 avec une seule personne à 5 j par semaine et à 100 % finit le vendredi 27 novembre. Si cette personne a un congé validé le lundi 23 novembre, la feuille finit le lundi 30 novembre. Si ce congé est seulement en attente, elle finit toujours le vendredi 27.
- [x] Un congé validé qui fait reculer une feuille sur la période d'une autre affectation de la même personne, au-delà de 100 %, signale la feuille « à replanifier ».
- [x] Sur la roadmap, une feuille dont le seul membre est en congé validé le 18 novembre, sans saisie ce jour-là, n'est pas coupée le 18. Avec une demi-journée de congé seulement, elle l'est.
- [x] Sur la roadmap et la fiche projet, le jour de congé d'un membre de l'équipe d'une feuille est signalé sur sa barre, et l'infobulle le nomme sans le type.
- [x] Sur la fiche d'Alice, « libre à partir du … » saute ses jours de congé validés, et ses congés à venir et passés y figurent.
- [x] Sur la page « Absences » de novembre 2026, un membre de la prod voit qu'Alice est absente du 9 au 13 novembre, mais pas le type. Bob et la direction voient « CP ». Une demande en attente d'Alice n'apparaît qu'à Alice, à Bob et à la direction.
- [x] La direction déclare 25 j de CP pour 2026-2027 et 10 j de RTT pour 2026 à une personne. Elle voit, sur les compteurs de l'équipe, ses droits, ses jours validés, ses jours en attente et son solde. Un lead ou un membre de la prod n'accède pas aux compteurs de l'équipe.
- [x] Un jour de congé validé que la direction ajoute ensuite comme jour férié au calendrier de la personne ne décompte plus rien, et son solde remonte de 1 j.

## Dette technique identifiée

Issus de la review : aucun mineur non traité.

Au-delà de la review :

1. **Aucun e-mail ne part en production** — `environments/prod/.env` — `MAILER_DSN=null://` et pas de `MAILER_SENDER` : arbitré à la review comme assumé pour l'instance de démonstration. **Critique** le jour où l'instance sert réellement : déclarer un DSN SMTP et l'expéditeur.
2. **[TEST] Le relèvement de `MAX_DAYS_OFF` n'a pas de test** — `tests/Unit/Model/Person/PersonLoadTest.php` — écrire le cas « prochain jour ouvré après un congé de plus de 31 jours ».
3. **[TEST] L'affichage des erreurs du formulaire de demande et la liste des collègues absents n'ont pas de test automatisé** — `tests/Twig/Components/TimeOffRequestFormTest.php`, `tests/Controller/TimeOffControllerTest.php` — vérifiés dans le navigateur ; un scénario E2E les fixerait.
4. **Réalignement de la doc d'intention** — `pitch.md` règle 43 (périodes de « Mes soldes ») ; backlog : règle transverse « être manager n'ouvre aucun droit » contredite, ligne `capacite-equipe` (C4.2) livrée, note `rappel-saisie` (exclure les jours de congé des jours ouvrés) — à traiter par `/forge:sync`.
5. **Décision d'architecture non gravée** — stockage des congés en bornes, demi-journées dépliées à la lecture : ADR proposée au plan, pas encore écrite (`/forge:adr 014-f-conges`).

## Leçons apprises

- **Un point d'entrée unique paie au moment d'y brancher une donnée** : `DailyCapacity` alimentait déjà fin calculée, charge, refus de surcharge, coupes et fiche. Y séparer « jour prévu » (répartition de la semaine) et « jour ouvré » (qui exclut le congé) a suffi, et les 233 tests de planification sont passés inchangés.
- **Les plafonds de complexité de PHPStan dessinent les services** : 40 par classe et 9 par fonction ont imposé `TimeOffOverview` et `TeamAbsences` en cours de route. Un plan qui empile plus de cinq ou six lectures dans un service doit prévoir le découpage d'emblée.
- **Le chargement en lot se planifie, il ne se rattrape pas** : relire les jours fériés par demande paraissait anodin, mais le coût croît avec l'historique de chacun. Mesurer le nombre de requêtes d'une page dont les données s'accumulent fait partie de la stratégie de test.
- **La base de test partagée et jamais remise à zéro accumule les utilisateurs** : un test qui dépend du nombre de membres de la direction doit passer par un repository en stub. Avec `WebTestCase`, créer toutes les données avant la première requête : le noyau redémarre ensuite, et les entités créées avant sont détachées.
- **Le navigateur piloté par MCP ne clique pas de façon fiable sur un formulaire Live** : les clics y sont restés sans effet alors que le lanceur Playwright envoie le formulaire en 252 ms. Vérifier un doute d'interaction avec le lanceur avant de conclure à un bug.
