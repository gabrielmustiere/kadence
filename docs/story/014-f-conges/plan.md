# Plan technique — Poser ses congés et ses RTT auprès de son manager, et les déduire de la saisie et de la capacité

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/014-f-conges/`
> **Amont** : `pitch.md`

## Approche retenue

Un congé est une demande, `TimeOffRequest`, qui ne stocke que ses **bornes** : un jour et une demi-journée de début, un jour et une demi-journée de fin. Ses demi-journées ne sont jamais matérialisées. Elles se déplient à la lecture, dans le modèle pur `TimeOffDays`, en sautant les week-ends et les jours fériés du calendrier en vigueur. C'est ce qui rend le décompte vivant (règle 6 du pitch) gratuit : un jour qui devient férié ne décompte plus rien, sans reprise de données. Les droits sont une seconde entité, `TimeOffEntitlement`, avec une ligne par personne, par type avec compteur et par année de début de période, en demi-journées entières. Le statut est une enum (`pending`, `approved`, `refused`, `cancelled`). Ses transitions sont gardées par `TimeOffManager`, qui écrit et envoie les e-mails par appel direct à `TimeOffMailer`, et par `TimeOffVoter`, qui dit qui peut décider, annuler ou lire le détail. Le valideur se lit au moment de la décision (`TimeOffApprovers`) : le manager actif, sinon les autres membres actifs de la direction. `TimeOffBook` porte les lectures d'une personne (jours posés, historique, soldes, aperçu d'une demande) et les congés validés de tous pour la capacité ; `TimeOffOverview` porte celles de l'équipe (mois d'absences, liste à valider, soldes d'une période). Un solde se calcule toujours par `Balance::count()`, à partir de demandes, de droits et de jours fériés chargés en lot, pour qu'aucune page ne fasse une requête par demande ou par période.

Les congés s'insèrent dans les calculs par deux points d'entrée existants, sans nouveau moteur. **La saisie** lit les demandes en attente et validées. `TimesheetBuilder` et `TimesheetManager` passent d'un plafond de jour fixe (`Quarters::PER_DAY`) à un plafond par jour (4, 2 ou 0 quarts), et `WeeklyMaximum::cap()` reçoit le nombre de demi-journées posées pour appliquer le prorata (règle 33). **La planification** lit les seuls congés validés. `ScheduleLoader` les charge en une requête et les passe à `DailyCapacity`, par qui transitent déjà la fin calculée, la charge, le refus de surcharge, les coupes de la roadmap et la charge de la fiche personne. `DailyCapacity` dédouble sa notion de jour ouvré. La répartition du maximum de la semaine reste fondée sur les seuls jours fériés, pour qu'un congé n'augmente pas la capacité des autres jours. `isWorkingDay()` exclut en plus les jours de congé entiers, et `units()` vaut la moitié un jour de demi-congé. Les règles 35 à 37 du pitch découlent de ce seul changement. L'affichage (onglets Congés, roadmap, fiche projet, fiche personne) lit ces mêmes données ; les absences de la roadmap sont construites par `TeamAbsences` à partir de `DailyCapacity`, sans requête supplémentaire.

### Mécanismes mobilisés

- **Entités Doctrine + repositories dédiés** (`TimeOffRequest`, `TimeOffEntitlement`) : toutes les lectures passent par des méthodes nommées. Pour une personne : `findActiveOfUserBetween()` et `findActiveOfUser()` (en attente et validées). Pour tous : `findApprovedBetween()` (planification) et `findActiveBetween()` (page Absences, liste à valider, soldes de l'équipe). Pour le valideur : `findPendingDecidableBy()`. Pour les droits : `findForPeriod()` et `findHalfDaysOf()`. Les index servent ces requêtes.
- **Modèles purs** dans `src/Model/TimeOff/` (`TimeOffPeriod`, `TimeOffDays`, `TimeOffDay`, `Balance`, `TimeOffPreview`, `AbsenceMonth`, `AbsenceRow`), sur le modèle de `LeafProgress` et `DailyCapacity` : ils portent les règles de dépliage, de période et de solde hors des entités, comme l'impose `CLAUDE.md`, et se testent unitairement sur les exemples datés du pitch.
- **`DailyCapacity`** (story 006) : c'est le point unique de la capacité. Il reçoit les demi-journées validées par personne et par jour, ce qui couvre la fin calculée, `Scheduler::loadOf()`, `PlanningFitsCapacityValidator`, `RoadmapRunCutter::runs()` et `PersonLoad` sans les toucher.
- **`WeeklyMaximum::cap()`** (story 004) : le plafond de la semaine reste une fonction pure, étendue d'un paramètre.
- **Live Component** (`TimeOffRequestForm`, avec `ComponentWithFormTrait`) : le formulaire de demande recalcule le décompte et le solde à chaque changement (règle 12), comme la grille de saisie (`Timesheet`) se recalcule à chaque cran.
- **Voter** (`TimeOffVoter`, sur le modèle de `PersonVoter` et `LotVoter`) : la décision combine l'état de la demande et le lien hiérarchique, ce qu'un `ROLE_*` ne sait pas dire. `access_control` garde seulement `/conges/compteurs` (direction).
- **Contrainte de classe** (`RequestableTimeOff` sur `TimeOffRequestInput`, sur le modèle de `PlanningFitsCapacity`) : les refus de la règle 14 deviennent des erreurs de formulaire, affichées dans le Live Component.
- **POST avec `isCsrfTokenValid()` manuel** : c'est la convention du projet pour un geste ponctuel sur une ligne (`HolidayController`, `TeamController`), qui valide, refuse ou annule. `#[IsCsrfTokenValid]` est écarté, car il redirige vers `/login` au lieu d'un 403. Le voter est vérifié avant le jeton, comme dans `LotController::progress()`, et l'utilisateur arrive par `#[CurrentUser]`.
- **Mailer + `TemplatedEmail`** dans `src/Mailer/` (convention de `CLAUDE.md`) : `SendEmailMessage` est déjà routé en async (`config/packages/messenger.yaml`), et `DEFAULT_URI` sert les liens absolus depuis le worker.
- **Exception métier dédiée** (`TimeOffRefusedException`, sur le modèle de `TimeEntryRefusedException`) : elle couvre les transitions impossibles (déjà décidée, congé commencé), rendues en flash. `TimeEntryRefusedException::timeOff()` couvre la saisie sur un jour posé.
- **`ClockInterface`** : « aujourd'hui » (annulation avant le premier jour, période en cours, horodatages) suit l'horloge du projet, ce qui le rend testable.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Matérialiser une ligne par demi-journée posée | Deux sources de vérité (bornes et jours) à resynchroniser quand un jour devient férié ou que le calendrier d'une personne change (règle 6). Avec 15 à 40 personnes et quelques demandes par an, déplier les bornes à la lecture ne coûte rien. |
| Composant Workflow de Symfony pour les états | C'est une dépendance nouvelle pour quatre états et trois transitions, toutes terminales. Une enum et des gardes dans `TimeOffManager` et `TimeOffVoter` suffisent. |
| Exclure les congés de `isWorkingDay()` aussi pour répartir le maximum de la semaine | Le maximum serait réparti sur moins de jours, et la capacité des jours restants gonflerait : une personne à 5 j qui pose le lundi apporterait 1,25 j les autres jours. La règle 33 veut au contraire un prorata. |
| Congés en attente dans la capacité | Le pitch le refuse (règle 34) : une demande refusée ferait bouger puis revenir les fins calculées. Les demandes en attente ne verrouillent que la saisie. |
| Événements métier et subscriber pour les e-mails | Un seul consommateur : l'appel direct au mailer depuis `TimeOffManager` est plus lisible et se teste de la même façon (`assertEmailCount()`). |
| Formulaire classique en deux temps (aperçu puis confirmation) | Une étape de plus pour chaque demande. Le Live Component donne l'aperçu sans aller-retour. |
| Préfixe `Leave*` pour les classes | Dans ce code, `leaf`/`leaves` désigne les feuilles (`ScheduleData::$leaves`, `LeafPlan`). `TimeOff*` évite une confusion permanente. |

## Modèle de données

### Nouvelle structure `TimeOffRequest`

`src/Entity/TimeOffRequest.php` (table `time_off_request`) :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `user` | ManyToOne (`User`), `user_id` | non | pas de cascade : une personne n'est jamais supprimée, seulement désactivée |
| `type` | enum `TimeOffType`, `length: 10` | non | `cp`, `rtt`, `unpaid`, `family` |
| `startDay` | `date_immutable`, `start_day` | non | |
| `startHalf` | enum `HalfDay`, `start_half`, `length: 10` | non | `morning` ou `afternoon` |
| `endDay` | `date_immutable`, `end_day` | non | ≥ `startDay` (contrainte `RequestableTimeOff`) |
| `endHalf` | enum `HalfDay`, `end_half` | non | |
| `comment` | `string`, `length: 500` | oui | |
| `status` | enum `TimeOffStatus`, `length: 10` | non | défaut `pending` |
| `createdAt` | `datetime_immutable`, `created_at` | non | |
| `decidedBy` | ManyToOne (`User`), `decided_by_id` | oui | null pour une validation d'office (règle 20) |
| `decidedAt` | `datetime_immutable`, `decided_at` | oui | |
| `refusalReason` | `string`, `refusal_reason`, `length: 500` | oui | obligatoire au refus (contrôlé par `TimeOffManager::refuse()`) |
| `cancelledBy` | ManyToOne (`User`), `cancelled_by_id` | oui | |
| `cancelledAt` | `datetime_immutable`, `cancelled_at` | oui | |

Index `idx_time_off_user_status` (`user_id`, `status`) et `idx_time_off_status_end` (`status`, `end_day`). L'entité expose ses accesseurs et trois mutateurs (`approve()`, `refuse()`, `cancel()`) qui ne font que poser les champs. Les gardes (statut courant, premier jour pas encore arrivé, motif non vide) vivent dans `TimeOffManager`.

### Nouvelle structure `TimeOffEntitlement`

`src/Entity/TimeOffEntitlement.php` (table `time_off_entitlement`) :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `user` | ManyToOne (`User`), `user_id` | non | |
| `type` | enum `TimeOffType`, `length: 10` | non | `cp` ou `rtt` seulement (`hasBalance()`) |
| `year` | `smallint` | non | année de début de la période (2026 pour les CP 2026-2027 comme pour les RTT 2026) |
| `halfDays` | `integer`, `half_days` | non | ≥ 0 ; les droits en jours × 2 (règle 3 : au demi-jour près) |

Contrainte d'unicité `uniq_time_off_entitlement` (`user_id`, `type`, `year`). Une ligne absente vaut 0 j. Il n'y a pas d'historique : la valeur est écrasée à chaque déclaration.

### Enums (`src/Enum/Type/`)

- `TimeOffType` : `PaidLeave = 'cp'`, `Rtt = 'rtt'`, `Unpaid = 'unpaid'`, `FamilyEvent = 'family'`, avec `label()` (« CP », « RTT », « Congé sans solde », « Événement familial »), `hasBalance()` et `periodStartMonth()` (5 pour les CP, 1 pour les RTT).
- `TimeOffStatus` : `Pending`, `Approved`, `Refused`, `Cancelled`, avec `isActive()` (en attente ou validée : verrou de la saisie et chevauchement).
- `HalfDay` : `Morning = 'morning'`, `Afternoon = 'afternoon'`, avec `label()`.

### Modèles calculés (non persistés, `src/Model/TimeOff/`)

- `TimeOffPeriod` (type, année) : `containing(TimeOffType, day)`, `firstDay()`, `lastDay()`, `label()` (« 2026-2027 » ou « 2026 »), `previous()`, `next()` et `key()` (type et année, pour indexer les droits).
- `TimeOffDays` : demi-journées posées par jour (`Y-m-d` → `list<HalfDay>`), dépliées depuis des bornes, hors week-ends et hors jours fériés fournis. Il expose `halfDayCount()`, `halfDayCountIn(TimeOffPeriod)` et `halvesOn(day)`.
- `TimeOffDay` : ce que porte un jour de la grille, c'est-à-dire ses demi-journées avec, pour chacune, le type et le statut. Il donne le libellé (« Congé · CP · en attente », « Congé · RTT (après-midi) ») et le plafond du jour en quarts (0, 2 ou 4). `collect()` regroupe par jour les demi-journées d'une liste de demandes.
- `Balance` (type, période, droits, validés, en attente, en demi-journées) : `remainingHalfDays()`, éventuellement négatif ; `count()` le calcule à partir des demandes d'une personne et de ses jours fériés.
- `TimeOffPreview` : les demi-journées décomptées par période et le solde après la demande, avec le dépassement ; `of()` le construit à partir d'une fonction qui donne le solde d'une période.
- `AbsenceMonth` : les jours ouvrés du mois, puis une ligne par personne active avec, chaque jour, son libellé férié ou ses demi-journées de congé. Le type et les demandes en attente n'y figurent que pour qui y a droit. Chaque ligne est un `AbsenceRow` (personne, droit de voir le détail, jours fériés, jours posés), et le nom du mois est donné en français par `label()`.

### Modification de modèles calculés existants

- `DailyCapacity` reçoit les demi-journées de congé validées par personne et par jour (`array<int, array<string, 1|2>>`). `DAY_DIVISOR` passe de 60 à 120, pour qu'une demi-journée reste un nombre entier d'unités sur 1 à 5 jours ouvrés. `timeOffHalves()` (0 les week-ends et jours fériés) et `timeOffSpans()` servent l'affichage des absences.
- `TimesheetDay` reçoit `capQuarters` (0, 2 ou 4) et `?TimeOffDay $timeOff`. `isComplete()` devient `capQuarters > 0 && quarters >= capQuarters`.
- `RoadmapRow` reçoit `absences` : une liste de `RoadmapAbsence` (personne, premier et dernier jour, demi-journée isolée, barre placée sur la fenêtre), qui suffit à dessiner la bande et l'infobulle.
- `PersonRoadmap` reçoit les absences de la personne (validées, passées et à venir), et `PersonLoad` les bandes de ses jours de congé sur la ligne de charge.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Enum/Type/TimeOffType.php` | Les quatre types, leur libellé, `hasBalance()` et le mois de début de leur période. |
| `src/Enum/Type/TimeOffStatus.php` | Les quatre statuts et `isActive()`. |
| `src/Enum/Type/HalfDay.php` | Matin ou après-midi. |
| `src/Entity/TimeOffRequest.php` | La demande, avec ses bornes, sa décision et son annulation. |
| `src/Entity/TimeOffEntitlement.php` | Les droits d'une personne pour un type et une période, en demi-journées. |
| `src/Repository/TimeOffRequestRepository.php` | `findActiveOfUserBetween()`, `findActiveOfUser()`, `findActiveBetween()`, `findApprovedBetween()`, `findApprovedOfUser()`, `findOfUser()`, `findPendingOfUser()`, `findPendingDecidableBy()` et `countPendingDecidableBy()`. |
| `src/Repository/TimeOffEntitlementRepository.php` | `findOneFor()`, `findForPeriod()` et `findHalfDaysOf()` (droits de plusieurs personnes, par clé de période). |
| `migrations/Version20261011094243.php` | Création des deux tables, de leurs index et de la contrainte d'unicité, générée par `make migration`. |
| `src/Model/TimeOff/TimeOffPeriod.php` | Période d'un type contenant un jour, ses bornes et son libellé. |
| `src/Model/TimeOff/TimeOffDays.php` | Dépliage des bornes en demi-journées ouvrées, et décompte global ou par période. |
| `src/Model/TimeOff/TimeOffDay.php` | Les demi-journées posées sur un jour de la grille, leur libellé et le plafond du jour. |
| `src/Model/TimeOff/Balance.php` | Droits, validés, en attente et solde pour un type et une période. |
| `src/Model/TimeOff/TimeOffPreview.php` | Décompte par période et solde après une demande, avec le dépassement. |
| `src/Model/TimeOff/AbsenceMonth.php` | Grille du mois de la page « Absences », avec le nom du mois en français. |
| `src/Model/TimeOff/AbsenceRow.php` | Une personne de la page Absences : jours fériés, jours posés, et droit de voir le détail. |
| `src/Model/Roadmap/RoadmapAbsence.php` | Une absence d'un membre d'une feuille, nommée dans l'infobulle. |
| `src/Service/TimeOffBook.php` | Lectures d'une personne : jours posés (en attente et validés), historique, soldes, aperçu ; congés validés de tous pour la capacité. |
| `src/Service/TimeOffOverview.php` | Lectures de l'équipe, chargées en lot : mois d'absences, liste à valider avec aperçus et collègues absents, soldes de l'équipe sur une période. |
| `src/Service/TeamAbsences.php` | Absences validées des membres de chaque feuille planifiée, sur ses jours dans la fenêtre de la roadmap. |
| `src/Service/TimeOffManager.php` | Écritures : `request()` (avec la validation d'office de la règle 20), `approve()`, `refuse()`, `cancel()` et `cancelPendingOf()`, puis les e-mails. |
| `src/Service/TimeOffApprovers.php` | Valideurs d'une demande, et « peut décider » pour une personne et une demande. |
| `src/Service/TimeOffEntitlementManager.php` | Déclaration des droits de toute l'équipe pour une période et un type. |
| `src/Exception/TimeOffRefusedException.php` | Transitions refusées : demande déjà décidée, congé commencé, refus sans motif. |
| `src/Dto/TimeOffRequestInput.php` | Données du formulaire de demande, avec le demandeur, posé par le composant. |
| `src/Dto/TimeOffEntitlementsInput.php` | Droits en jours par personne pour une période et un type. |
| `src/Form/TimeOffRequestType.php` | Type, début et demi-journée, fin et demi-journée, commentaire. |
| `src/Form/TimeOffEntitlementsType.php` | Un champ de droits (pas de 0,5) par personne active. |
| `src/Validator/RequestableTimeOff.php` | Contrainte de classe de la règle 14. |
| `src/Validator/RequestableTimeOffValidator.php` | Fin avant début, aucun jour ouvré, chevauchement d'une demi-journée active, temps déjà saisi au-delà de ce que le congé laisse. |
| `src/Security/Voter/TimeOffVoter.php` | `TIME_OFF_DECIDE`, `TIME_OFF_CANCEL`, `TIME_OFF_DETAIL` (sur une personne) et `TIME_OFF_INBOX` (sans sujet). |
| `src/Mailer/TimeOffMailer.php` | E-mails de demande, de décision et d'annulation (règle 45). |
| `src/Controller/TimeOffController.php` | Mes congés, À valider (valider, refuser), annuler, Absences, Compteurs. |
| `src/Twig/Components/TimeOffRequestForm.php` | Live Component du formulaire de demande avec aperçu du décompte et du solde. |
| `templates/components/TimeOffRequestForm.html.twig` | Formulaire, aperçu par période et dépassement signalé. |
| `templates/time_off/_tabs.html.twig` | Onglets Mes congés, À valider (avec le nombre en attente), Absences et Compteurs, selon les droits. |
| `templates/time_off/index.html.twig` | Mes congés : formulaire, soldes et historique avec annulation. |
| `templates/time_off/inbox.html.twig` | À valider : demandes avec solde après, dépassement et autres absents, validation et refus en modale. |
| `templates/time_off/absences.html.twig` | Grille du mois, navigation de mois en mois, demi-journées. |
| `templates/time_off/entitlements.html.twig` | Choix de la période et du type, tableau de l'équipe, enregistrement unique. |
| `templates/time_off/_request.html.twig` | Ligne d'une demande dans Mes congés. |
| `templates/time_off/_period.html.twig` | Dates d'une demande avec ses demi-journées aux bornes. |
| `templates/emails/_layout.html.twig` | Gabarit commun des e-mails. |
| `templates/emails/time_off/requested.html.twig` | Nouvelle demande, au valideur. |
| `templates/emails/time_off/decided.html.twig` | Validation ou refus avec motif, à la personne, ou information du manager remplacé. |
| `templates/emails/time_off/cancelled.html.twig` | Annulation d'un congé validé. |
| `templates/emails/time_off/_summary.html.twig` | Récapitulatif commun aux e-mails (type, dates, décompte, commentaire). |
| `templates/roadmap/_absences.html.twig` | Bande hachurée des absences de l'équipe sous la ligne d'une feuille. |
| `templates/roadmap/_absence_period.html.twig` | Dates d'une absence de la roadmap. |
| `templates/person/_absences.html.twig` | Bloc « Absences » de la fiche d'une personne. |
| `tests/Support/CreatesTimeOff.php` | Trait de création de demandes et de droits pour les tests. |
| `tests/Unit/Model/TimeOff/TimeOffPeriodTest.php` | Périodes CP et RTT, bornes du 30/04 au 01/05 et du 31/12 au 01/01, libellés. |
| `tests/Unit/Model/TimeOff/TimeOffDaysTest.php` | Dépliage : après-midi de début, matin de fin, demi-journée isolée, week-ends, jours fériés, décompte par période. |
| `tests/Unit/Model/TimeOff/BalanceTest.php` | Solde, négatif compris. |
| `tests/Unit/Model/TimeOff/TimeOffDayTest.php` | Plafond et libellé d'un jour entier, d'une demi-journée et de deux demi-journées différentes. |
| `tests/Unit/Security/Voter/TimeOffVoterTest.php` | Décider, annuler et lire le détail selon le rôle, le lien de manager et le statut. |
| `tests/Service/TimeOffBookTest.php` | Jours posés, soldes et leurs périodes, historique, aperçu, décompte vivant, démo sans temps sur un jour posé. |
| `tests/Service/TimeOffManagerTest.php` | Transitions, `cancelPendingOf()`, annulation par la direction, e-mails envoyés et destinataires. |
| `tests/Unit/Service/TimeOffManagerTest.php` | Validation d'office du seul membre actif de la direction, sans e-mail. |
| `tests/Service/TimeOffApproversTest.php` | Manager actif, direction sans manager, jamais soi-même, dernier membre de la direction. |
| `tests/Unit/Validator/RequestableTimeOffValidatorTest.php` | Chacun des refus de la règle 14 et les cas acceptés (deux demi-journées le même jour, jours passés), avec les repositories en `createStub()`. |
| `tests/Controller/TimeOffControllerTest.php` | Accès aux onglets, décider, refuser sans motif, 403, jeton invalide, annuler avant et après le début, chemin de retour, liste sans manager, nombre de requêtes, absences et type masqué, compteurs réservés. |
| `tests/Twig/Components/TimeOffRequestFormTest.php` | Aperçu du décompte et du solde, dépassement, demande créée, demande en chevauchement refusée sans enregistrement. |
| `tests/e2e/time-off.spec.ts` | Demande, verrou dans la grille, validation par le manager, page Absences. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Model/WeeklyMaximum.php` | `cap()` prend en plus les demi-journées de congé de la semaine et applique le prorata, arrondi au quart inférieur. |
| `src/Service/WeeklyMaxManager.php` | `capFor()` transmet les demi-journées de congé. |
| `src/Model/Timesheet/TimesheetDay.php` | Ajouter `capQuarters` et `timeOff`, et revoir `isComplete()`. |
| `src/Service/TimesheetBuilder.php` | Lire les jours posés par `TimeOffBook`, calculer le plafond de chaque jour, verrouiller une cellule à plafond 0, ne pas marquer d'oubli, passer les demi-journées au maximum de la semaine. |
| `src/Service/TimesheetManager.php` | Refuser au-delà du plafond du jour (`timeOff()` si le jour est entièrement posé), et passer les demi-journées au maximum de la semaine. |
| `src/Exception/TimeEntryRefusedException.php` | Ajouter `timeOff(day, label)` et le plafond de jour dans `dayFull()`. |
| `templates/components/Timesheet.html.twig` | En-tête, onglet de jour et notice mobile « Congé · … », cellule verrouillée ou plafonnée, attributs `data-time-off`. |
| `src/Model/Schedule/DailyCapacity.php` | Recevoir les demi-journées validées (paramètre de constructeur optionnel, `[]` par défaut, pour ne pas toucher les tests existants). `isWorkingDay()` exclut les jours entiers, la répartition de la semaine reste sur les jours non fériés, `units()` vaut 0 ou la moitié, `DAY_DIVISOR` passe à 120, et `timeOffHalves()` sert l'affichage. |
| `src/Service/ScheduleLoader.php` | Charger les congés validés de `firstDay` à l'horizon, en une requête, et les déplier pour `DailyCapacity`. |
| `src/Model/Person/PersonLoad.php` | `MAX_DAYS_OFF` passe de 31 à 120, et bandes des jours de congé sur la ligne de charge. |
| `src/Model/Roadmap/RoadmapRow.php` | Ajouter `absences`. |
| `src/Service/RoadmapBuilder.php` | Recevoir de `TeamAbsences` les absences de chaque feuille et les passer à sa ligne ; `reach()` devient publique. |
| `templates/roadmap/_row.html.twig` | Inclure `_absences.html.twig` sous les barres d'une feuille. |
| `templates/roadmap/_tooltip.html.twig` | Lister les absences de l'équipe sur la période de la barre, sans type. |
| `templates/roadmap/_legend.html.twig` | Entrée « Absence d'un membre de l'équipe ». |
| `src/Model/Person/PersonRoadmap.php` | Ajouter les absences de la personne. |
| `src/Service/PersonRoadmapBuilder.php` | Remplir les absences et les bandes de la ligne de charge. |
| `templates/person/show.html.twig` | Inclure le bloc « Absences », avec le type si `TIME_OFF_DETAIL`, et les bandes sur la ligne de charge. |
| `src/Service/TeamManager.php` | `deactivate()` annule les demandes en attente de la personne (`cancelPendingOf()`), sans e-mail. |
| `src/Repository/UserRepository.php` | `findActiveDirectors()`, `findActiveWithManager()` (personnes actives triées par nom, avec leur manager) et `hasActiveReports()`. |
| `src/Twig/NavigationExtension.php` | Section `time_off` sur le préfixe `app_time_off_`. |
| `src/Twig/DaysExtension.php` | Filtre `half_days` (demi-journées affichées en jours). |
| `templates/base.html.twig` | Entrée de menu « Congés » pour tous, après « Ma semaine ». |
| `config/packages/security.yaml` | `access_control` : `^/conges/compteurs` réservé à `ROLE_DIRECTION`. |
| `.env` | `MAILER_SENDER`, l'adresse d'expédition des e-mails. |
| `.env.test` | `MAILER_DSN=null://null`, pour qu'aucun test n'appelle Mailpit. |
| `fixtures/DemoCompanyFixtures.php` | Droits CP et RTT déclarés, congés passés et à venir (validés, en attente, refusés, annulés, dont des demi-journées) sur des personnes de démonstration, et génération des temps qui saute les jours posés. |
| `tests/Unit/Model/WeeklyMaximumTest.php` | Exemples du prorata de la règle 33, avec et sans jour férié. |
| `tests/Unit/Model/Schedule/DailyCapacityTest.php` | Jour entier posé, demi-journée, unités inchangées des autres jours de la semaine, tronçons de congé. |
| `tests/Unit/Model/Person/PersonLoadTest.php` | Prochain jour ouvré au-delà de 31 jours de congé : non écrit, reporté en dette (cf. `report.md`). Les bandes de congé sont couvertes par `PersonRoadmapBuilderTest`. |
| `tests/Service/WeeklyMaxManagerTest.php` | `capFor()` avec des demi-journées. |
| `tests/Service/TimesheetBuilderTest.php` | Jour verrouillé, demi-journée plafonnée à 2 quarts, pas d'oubli, maximum réduit. |
| `tests/Service/TimesheetManagerTest.php` | Refus sur un jour posé (en attente ou validé), demi-journée plafonnée, rouverture après refus. |
| `tests/Service/RoadmapBuilderTest.php` | Exemple de fin calculée (27/11 → 30/11, inchangée pour une demande en attente, ce qui couvre aussi le chargement de `ScheduleLoader`), pas de coupe un jour de congé entier, coupe avec une demi-journée, absences nommées, surcharge passive « à replanifier ». |
| `tests/Service/PersonRoadmapBuilderTest.php` | « libre à partir du … » qui saute les congés, bloc Absences. |
| `tests/Unit/Service/TeamManagerTest.php` | `deactivate()` annule les demandes en attente, avec un vrai `TimeOffManager` monté sur des stubs (classe `final`, non simulable). |
| `tests/Unit/Twig/NavigationExtensionTest.php` | Section `time_off` pour les routes `app_time_off_*`. |
| `tests/Controller/NavigationTest.php` | Entrée « Congés » présente pour tous les rôles et allumée sur les onglets Congés. |
| `tests/Controller/PersonControllerTest.php` | Type des congés visible de la personne et de la direction, masqué pour la prod. |
| `tests/Controller/TimesheetControllerTest.php` | Seuil de requêtes de la grille relevé de 6 à 7 (lecture des congés de la semaine). |

## Hors scope

- **Matérialisation ou cache des demi-journées** : le dépliage à la lecture suffit à l'échelle de l'équipe. Il sera à reconsidérer seulement si un profilage le désigne.
- **Badge du nombre de demandes à valider dans le menu global** : il n'apparaît que sur l'onglet « À valider », pour éviter une requête sur chaque page.
- **Mise en forme avancée des e-mails** (feuille de style intégrée, version texte soignée) : un gabarit HTML simple, sans nouvelle dépendance.
- **Refonte de `WeeklyMaxManager::capFor()` pour lire elle-même jours fériés et congés** : ses appelants restent responsables du comptage, comme pour les jours fériés aujourd'hui.
- **Extraction d'un composant Twig « grille de mois »** : la page Absences est la seule à en avoir besoin.

## Impacts transverses

- **Cloisonnement des données** : il n'y a ni organisation ni canal. Le seul filtrage est par personne. `TimeOffVoter::TIME_OFF_DETAIL` (soi-même, manager actif, direction) masque le type, le commentaire, le motif et les demandes en attente sur la page Absences, la fiche personne et la roadmap. `TimeOffBook::month()` le calcule en bloc pour toutes les lignes, sans un appel au voter par cellule.
- **Déclinaisons / thèmes** : non. Les nouveaux écrans réutilisent les composants Cadence (`PageHeader`, `Tabs`, `TableCard`, `Modal`, `Badge`, `Button`) et les jetons de `DESIGN.md`. La bande d'absences réutilise le motif hachuré existant (`hatch-*`).
- **Traduction / i18n** : non. Les libellés sont en français, en dur, comme dans le reste de l'application.
- **API / exposition externe** : non.
- **Droits d'accès** : nouveau `TimeOffVoter`.
  - `TIME_OFF_DECIDE` : demande en attente, demandeur différent de soi, et l'on est son manager ou de la direction.
  - `TIME_OFF_CANCEL` : soi-même tant que la demande est en attente, ou tant que le congé validé n'a pas commencé (`startDay` > aujourd'hui) ; la direction sur un congé validé, à tout moment.
  - `TIME_OFF_DETAIL` : sur une personne.
  - `TIME_OFF_INBOX` : direction, ou personne qui a au moins un collaborateur actif.
  - `access_control` ajoute `^/conges/compteurs` pour `ROLE_DIRECTION`. Le reste de `/conges` relève de `ROLE_USER`.
- **Emails / notifications** : oui. `TimeOffMailer` envoie un `TemplatedEmail` depuis `MAILER_SENDER`, en async par Messenger (déjà routé), avec des liens absolus via `DEFAULT_URI`. En test, Messenger est en `sync://` et `MAILER_DSN` passe à `null://null`, et les tests vérifient avec `assertEmailCount()` et `getMailerMessages()`.
- **Migration de données** : création de deux tables, sans reprise de l'existant. Aucun congé, et tous les droits valent 0 j.
- **Comportement par défaut** : sans congé posé, `TimeOffBook` ne renvoie rien. Le plafond du jour reste à 4 quarts, `WeeklyMaximum::cap()` donne la même valeur qu'aujourd'hui, et `DailyCapacity` les mêmes unités (le passage de `DAY_DIVISOR` à 120 multiplie numérateur et dénominateur). La grille, les fins calculées, la roadmap et les fiches sont inchangées.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Model/TimeOff/TimeOffPeriod.php` | unit | CP du 01/05 au 30/04 et RTT sur l'année civile, période contenant un jour aux bornes, libellés « 2026-2027 » et « 2026 ». |
| `src/Model/TimeOff/TimeOffDays.php` | unit | 9 au 13/11/2026 = 4 j (11/11 férié) ; 16/11 après-midi au 18/11 matin = 2 j ; demi-journée isolée = 0,5 j ; 29/04 au 04/05/2027 = 2 j + 2 j par période ; 31/12/2026 au 04/01/2027 = 1 j + 1 j ; week-ends et jours fériés ignorés. |
| `src/Model/TimeOff/Balance.php` | unit | 25 j de droits, 22 j validés ou en attente, demande de 5 j : solde après de −2 j et dépassement. |
| `src/Model/WeeklyMaximum.php` | unit | 5 j avec un jour posé donne 4 j ; 4,5 j avec un matin, 4 j ; 4 j avec un jour, 3 j ; semaine entière, 0 j ; un férié et un jour posé (base 4 j), 3 j ; sans congé, valeur inchangée. |
| `src/Model/Schedule/DailyCapacity.php` | unit | Jour entier posé : 0 unité et pas de jour ouvré. Demi-journée : moitié des unités, jour ouvré. Les autres jours de la semaine gardent leurs unités. Sans congé, les unités sont les mêmes qu'avec `DAY_DIVISOR` 60, rapportées à 120. |
| `src/Security/Voter/TimeOffVoter.php` | unit | Le manager décide, un lead non manager non, la direction oui, personne pour soi. On annule une demande en attente à tout moment, un congé validé seulement avant son début, et la direction toujours. Le détail est visible de soi, du manager et de la direction. |
| `src/Service/TimeOffApprovers.php` | functional | Manager actif, sinon direction sans le demandeur ; le seul membre de la direction sans manager n'a aucun valideur. |
| `src/Validator/RequestableTimeOffValidator.php` | unit | Fin avant début, aucun jour ouvré, chevauchement d'un même matin, 1 j saisi contre une journée, 3 quarts contre un après-midi : refus. 2 quarts et un après-midi, matin et après-midi de types différents, jours passés sans temps : acceptés. |
| `src/Service/TimeOffManager.php` | functional + unit | Une demande crée `pending` et envoie un e-mail au valideur. La validation d'office (règle 20) n'envoie rien (unit : la base de test partagée ne permet pas de contrôler le nombre de membres de la direction). La direction annule son propre congé commencé. Valider ou refuser envoie à la personne, et au manager quand la direction décide à sa place. Refuser sans motif ou décider une demande déjà décidée : exception. L'annulation d'un congé validé envoie au valideur, ou à la personne si c'est la direction qui annule. `cancelPendingOf()` n'envoie rien. |
| `src/Service/TimeOffBook.php` | functional | Jours posés (en attente et validés, ni refusés ni annulés). Soldes par période. Aperçu. Un jour de congé validé devenu férié ne décompte plus. Historique et soldes limités aux périodes non terminées. Le mois d'absences (`TimeOffOverview`) est vérifié par le contrôleur. |
| `src/Service/TimesheetBuilder.php` | functional | Jour posé verrouillé avec « Congé · CP · en attente », demi-journée à 2 quarts au plus, jour posé jamais en oubli, maximum de semaine au prorata. |
| `src/Service/TimesheetManager.php` | functional | Enregistrement refusé sur un jour posé (onglet ouvert avant la demande), au-delà de 2 quarts sur une demi-journée, et accepté après refus ou annulation. |
| `src/Service/ScheduleLoader.php` + `RoadmapBuilder.php` | functional | Feuille de 10 j au 16/11/2026, une personne à 100 % : fin le 27/11, le 30/11 avec un congé validé le 23/11, le 27/11 si ce congé est en attente. Feuille non coupée un jour de congé entier, coupée une demi-journée. Bande et infobulle d'absences. |
| `src/Service/PersonRoadmapBuilder.php` | functional | « libre à partir du … » et prochain jour ouvré sautent les congés validés ; bloc Absences. |
| `src/Controller/TimeOffController.php` | functional | Onglets selon le rôle. Valider ou refuser par le manager, refus sans motif rejeté, 403 pour un lead non manager. Annulation avant et après le début. Compteurs réservés à la direction et enregistrés. Page Absences : type visible du manager et de la direction, masqué pour la prod. Chemin de retour limité au site, liste à valider sans collègues pour une personne sans manager, nombre de requêtes fixe. |
| `src/Twig/Components/TimeOffRequestForm.php` | functional | L'aperçu suit les champs (jours, solde par période, dépassement), l'envoi crée la demande puis redirige, et une demande en chevauchement est refusée sans rien enregistrer. |
| `src/Service/TeamManager.php` | unit | La désactivation annule les demandes en attente ; `TimeOffManagerTest` vérifie que les congés validés sont conservés. |
| `tests/e2e/time-off.spec.ts` | E2E | La prod demande un jour en 2030 : jour verrouillé « en attente » dans sa grille. Le manager le voit dans À valider et le valide. Il apparaît sur la page Absences sans type pour la prod, avec type pour la direction. |

**Hors scope tests** :

- Pas de rendu fin des e-mails (mise en page) : on vérifie les destinataires, le sujet et la présence du motif ou des dates.
- Pas d'E2E des compteurs ni du refus : les tests fonctionnels du contrôleur couvrent ces formulaires, sans interaction JS.
- Pas d'E2E sur la roadmap et la fiche personne : `RoadmapBuilderTest` et `PersonRoadmapBuilderTest` couvrent les données, et les gabarits ne font que les parcourir.
- Pas de test de charge : 15 à 40 personnes, une requête de plus par calcul de planification. Le nombre de requêtes de « Mes congés » et « À valider » est en revanche fixé par `TimeOffControllerTest` : il ne dépend ni de l'historique ni du nombre de demandes en attente.
- Pas de test de l'affichage des erreurs du formulaire de demande : le composant de test lève l'exception 422 au lieu de rendre la page ; affichage vérifié au navigateur.

## Ordre d'exécution

1. [x] **Socle : enums, entités, migration, modèles purs**
   - Objectif : stocker demandes et droits ; déplier, décompter et imputer par période.
   - Fichiers : `TimeOffType`, `TimeOffStatus`, `HalfDay`, `TimeOffRequest`, `TimeOffEntitlement`, les deux repositories, la migration (`make migration`, relue), `TimeOffPeriod`, `TimeOffDays`, `TimeOffDay`, `Balance`, `TimeOffPreview`, `CreatesTimeOff`, les tests unitaires des modèles.
   - Vérification : `make phpunit-filter TimeOff`, `symfony console doctrine:schema:validate`, `make lint`.
   - Commitable seule : oui.

2. [x] **Lecture : `TimeOffBook` et valideurs**
   - Objectif : jours posés d'une personne, soldes, aperçu, décompte vivant, et valideurs d'une demande.
   - Fichiers : `TimeOffBook`, `TimeOffApprovers`, `UserRepository` (`findActiveDirectors()`), tests.
   - Vérification : `make phpunit-filter TimeOffBook`, `make phpunit-filter TimeOffApprovers`.
   - Commitable seule : oui.

3. [x] **Écriture : demande, décision, annulation, droits**
   - Objectif : transitions gardées, validation d'office, contrainte de la règle 14, voter, e-mails, déclaration des droits, désactivation.
   - Fichiers : `TimeOffManager`, `TimeOffEntitlementManager`, `TimeOffRefusedException`, `TimeOffRequestInput`, `RequestableTimeOff` et son validateur, `TimeOffVoter`, `TimeOffMailer`, gabarits `emails/`, `.env` (`MAILER_SENDER`), `.env.test` (`MAILER_DSN=null://null`), `TeamManager::deactivate()`, tests.
   - Vérification : `make phpunit-filter TimeOff`, `make phpunit-filter TeamManager`, puis une demande créée en dev et visible dans Mailpit.
   - Commitable seule : oui.

4. [x] **Saisie : verrou, plafond du jour, maximum au prorata**
   - Objectif : règles 29 à 33 dans la grille et à l'enregistrement.
   - Fichiers : `WeeklyMaximum`, `WeeklyMaxManager`, `TimesheetDay`, `TimesheetBuilder`, `TimesheetManager`, `TimeEntryRefusedException`, `Timesheet.html.twig`, tests.
   - Vérification : `make phpunit-filter Timesheet`, `make phpunit-filter WeeklyMax`, puis grille vérifiée en dev sur une semaine avec un jour posé.
   - Commitable seule : oui.

5. [x] **Capacité : congés validés dans `DailyCapacity`**
   - Objectif : règles 34 à 37 (fin calculée, surcharge passive, coupes, charge de la fiche).
   - Fichiers : `DailyCapacity` (`DAY_DIVISOR` 120, dédoublement des jours ouvrés, demi-unités), `ScheduleLoader`, `PersonLoad::MAX_DAYS_OFF`, tests (`DailyCapacity`, `ScheduleLoader`, `RoadmapBuilder`, `PersonRoadmapBuilder`).
   - Vérification : suite complète `make phpunit`, dont tous les tests existants de planification, inchangés et verts.
   - Commitable seule : oui.

6. [x] **Pages Congés : Mes congés et formulaire**
   - Objectif : demander avec aperçu, voir ses soldes et son historique, annuler.
   - Fichiers : `TimeOffController` (index, cancel), `TimeOffRequestType`, `TimeOffRequestForm` et son gabarit, `time_off/_tabs`, `index`, `_request`, menu (`base.html.twig`, `NavigationExtension`), tests.
   - Vérification : `make phpunit-filter TimeOffController`, `make phpunit-filter TimeOffRequestForm`, `make phpunit-filter Navigation`.
   - Commitable seule : oui.

7. [x] **Pages Congés : À valider, Absences, Compteurs**
   - Objectif : décider (refus avec motif en modale), grille du mois avec le détail selon les droits, déclaration des droits de l'équipe.
   - Fichiers : `TimeOffController` (inbox, approve, refuse, absences, entitlements), `AbsenceMonth`, `TimeOffEntitlementsInput`, `TimeOffEntitlementsType`, gabarits `inbox`, `absences`, `entitlements`, `security.yaml`, tests.
   - Vérification : `make phpunit-filter TimeOffController`.
   - Commitable seule : oui.

8. [x] **Roadmap, fiche projet et fiche personne**
   - Objectif : règles 41 et 42 (bande et infobulle d'absences, bloc Absences, jours de congé sur la ligne de charge).
   - Fichiers : `RoadmapAbsence`, `RoadmapRow`, `RoadmapBuilder`, `_row`, `_absences`, `_tooltip`, `_legend`, `PersonRoadmap`, `PersonRoadmapBuilder`, `PersonLoad`, `person/show`, `person/_absences`, tests.
   - Vérification : `make phpunit-filter Roadmap`, `make phpunit-filter Person`, puis roadmap et fiche vérifiées en dev.
   - Commitable seule : oui.

9. [x] **Données de démonstration**
   - Objectif : droits, congés de tous les statuts et demi-journées sur des personnes de démonstration, et aucun temps sur un jour posé.
   - Fichiers : `fixtures/DemoCompanyFixtures.php`.
   - Vérification : `make db-reset`, puis la suite complète `make phpunit` et `make playwright` existante verte. Aucun congé sur `admin@`, `lead@` ni `prod@`, pour ne pas verrouiller les jours des E2E existants.
   - Commitable seule : oui.

10. [x] **E2E et QA finale**
    - Objectif : parcours bout en bout et qualité.
    - Fichiers : `tests/e2e/time-off.spec.ts`. Le manager de `prod@` est posé par SQL avant le scénario puis retiré, et les demandes de 2030 sont purgées avant et après.
    - Vérification : `make playwright-file tests/e2e/time-off.spec.ts`, `make playwright`, `make phpunit`, `make lint`.
    - Commitable seule : oui.

## Critères de sortie

- [x] Une demande ne stocke que ses bornes ; aucune table ne matérialise ses demi-journées.
- [x] Sans aucun congé, les tests existants de saisie, de planification, de roadmap et de fiche passent sans modification de leurs attentes, à une exception près : le seuil de requêtes de la grille passe de 6 à 7 (`TimesheetControllerTest`), pour la lecture des congés de la semaine.
- [x] Un jour en attente ou validé est verrouillé à l'affichage **et** refusé à l'enregistrement ; une demi-journée est plafonnée à 2 quarts.
- [x] Les demandes en attente ne modifient aucune fin calculée ; un congé validé repousse la fin d'une feuille de son équipe.
- [x] Seul `TimeOffVoter` décide de qui valide, annule ou voit le détail ; `/conges/compteurs` renvoie 403 à un lead et à la prod.
- [x] Aucun test PHPUnit n'ouvre de connexion SMTP (`MAILER_DSN=null://null` en test).
- [x] `symfony console doctrine:schema:validate` est vert après la migration.
- [x] `make phpunit` est vert, sans nouvelle régression.
- [x] `make playwright` est vert, nouveau scénario compris.
- [x] `make lint` est vert : PHP-CS-Fixer et PHPStan niveau 10.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Le dédoublement « jour non férié » / « jour ouvré » de `DailyCapacity` oublie un appelant, et la capacité gonfle les semaines de congé | moyenne | `week()` passe par une méthode privée distincte de `isWorkingDay()`, et un test unitaire vérifie qu'un jour posé laisse inchangées les unités des autres jours. Les cinq appelants de `isWorkingDay()` et `hasWorkingDayBetween()` (`Scheduler::loadOf()`, `RoadmapRunCutter`, `PersonLoad` deux fois, `DailyCapacity::units()`) sont couverts par l'étape 5. |
| Le passage de `DAY_DIVISOR` à 120 décale un arrondi dans un test de planification existant | faible | Les divisions restent exactes (120 est divisible par 1 à 5, et 60 aussi pour les demi-journées). Les tests existants sont lancés tels quels à l'étape 5, avant d'en ajouter. |
| Les fixtures génèrent des temps sur un jour posé, ce qui contredit le verrou | moyenne | Les congés sont générés avant les temps, et la génération saute les jours posés. Un test fonctionnel vérifie, sur les données chargées par `make phpunit`, qu'aucun temps ne tombe sur un jour posé. |
| Un congé de démonstration verrouille un jour utilisé par un E2E existant (`timesheet.spec.ts`) | moyenne | Aucun congé de démonstration sur les comptes de test ; le scénario E2E des congés travaille en 2030 et purge ses données. |
| E-mails envoyés pendant les tests vers un Mailpit absent | élevée sans mitigation | `MAILER_DSN=null://null` dans `.env.test` (étape 3). |
| E-mails non partis en dev si le worker Messenger ne tourne pas | faible | `.symfony.local.yaml` lance déjà `messenger:consume async` avec `make serve`. |
| Le Live Component de demande diverge de la contrainte (aperçu accepté, envoi refusé) | faible | L'aperçu et la contrainte partagent `TimeOffDays` et `TimeOffBook`, et le test du composant vérifie qu'une demande refusée par la contrainte n'est pas enregistrée ; l'affichage des erreurs est vérifié au navigateur. |
| La page Absences ou la roadmap appelle le voter par cellule (N appels) | moyenne | La visibilité du détail est calculée en bloc par `TimeOffOverview` (soi-même, collaborateurs, ou tout pour la direction), en une requête par mois ou par fenêtre. |
| Une double soumission crée deux demandes identiques | faible | La seconde chevauche la première, en attente : la contrainte `RequestableTimeOff` la refuse. |

## Questions ouvertes

- **Sujet et contenu exacts des e-mails** : à fixer à l'implémentation. Options : (a) sujet court et préfixé « [Kadence] » (« [Kadence] Demande de congé de Paula Durand ») avec un lien vers l'onglet concerné ; (b) sans préfixe. → tranché : (a).
- **Personnes listées sur la page Absences** : options : (a) les personnes actives seulement, tri par nom (retenu par défaut) ; (b) également les personnes désactivées ayant un congé dans le mois affiché. → tranché : (a).
