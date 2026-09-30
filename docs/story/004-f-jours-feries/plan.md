# Plan technique — Verrouiller la saisie des jours fériés selon le calendrier français ou belge de chacun

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/004-f-jours-feries/`
> **Amont** : `pitch.md`
> **ADR** : `docs/adr/0002-jours-feries-calcules-et-ajustements.md`

## Approche retenue

Les jours fériés légaux ne sont **jamais stockés**. Ils sont **calculés à la volée** par un service pur, `LegalHolidays`, à partir de dates fixes et de la date de Pâques (`easter_days()`). Seuls les **écarts à la loi** sont persistés, dans une table `HolidayAdjustment` : un jour ajouté, avec son libellé, ou un jour légal retiré, pour un calendrier et une date. Chaque personne porte son calendrier dans une colonne `User.holidayCalendar` (enum `HolidayCalendar`, `fr` par défaut). `HolidayManager` est la seule porte d'entrée métier :

- **`holidaysOf(User, Week)`** donne les jours fériés effectifs de la personne sur une semaine, du lundi au vendredi : les jours légaux, plus les ajouts, moins les retraits. Il fait une seule requête sur les ajustements.
- **`yearOf(HolidayCalendar, année)`** construit la vue annuelle de la page de la direction.
- **`add()`**, **`remove()`** et **`cancel()`** gèrent les ajustements, et **`adjustmentAt()`** donne l'ajustement d'une date au validateur.

La saisie s'appuie sur `holidaysOf()` à deux endroits. `TimesheetBuilder` verrouille la colonne d'un jour férié et la nomme (`TimesheetDay::$holiday`). Il ne la signale jamais comme un oubli et plafonne la semaine. `TimesheetManager` refuse **tout** enregistrement sur un jour férié, y compris une baisse ou une remise à 0 (verrou absolu, règles 12 à 14 du pitch). Le plafond de la semaine se calcule en un seul endroit, `WeeklyMaxManager::capFor()` : `min(maximum en vigueur, 4 × jours non fériés)`. `quartersFor()` ne change pas, car la fiche Équipe affiche toujours le maximum réglé. La page `/jours-feries/{année}` (années 1000 à 9999), réservée à la direction, liste les deux calendriers côte à côte. On y ajoute un jour par formulaire, protégé par une contrainte de classe. On retire un jour légal et on annule un ajustement par des boutons protégés par jeton CSRF. Le calendrier d'une personne se règle par un choix France / Belgique dans le formulaire existant de la fiche Équipe.

### Mécanismes mobilisés

- **`easter_days()` (`ext-calendar`)** : Pâques tombe le 21 mars plus `easter_days($year)` jours. On en dérive le lundi de Pâques (+1), l'Ascension (+39) et le lundi de Pentecôte (+50). `ext-calendar` est déclarée dans `composer.json` (`"ext-calendar": "*"`) : une installation sur un hôte qui ne l'a pas échoue explicitement.
- **Enums backed string mappés par `enumType`** (précédent : `Role`) : `HolidayCalendar` (`fr`, `be`, avec `label()`) et `HolidayAdjustmentType` (`added`, `removed`) dans `src/Enum/Type/`.
- **Colonne avec valeur par défaut dans le mapping** (`options: ['default' => 'fr']`) : `make:migration` génère un `DEFAULT 'fr'`, ce qui évite toute reprise des comptes existants.
- **Contrainte de classe sur mesure** `AddableHoliday` sur le DTO `HolidayAdditionInput` (précédent : `UniqueLotTitle`). Elle refuse un samedi ou un dimanche, un jour déjà férié dans ce calendrier (légal non retiré, ou déjà ajouté) et un jour légal retiré (« annulez le retrait »). L'erreur s'affiche sous le champ date. Elle s'appuie sur `HolidayManager` (`isHoliday()`, `adjustmentAt()`), seule source de vérité. Le formulaire et le DTO portent le nom « Addition » : `HolidayAdjustmentType` est déjà le nom de l'enum.
- **Exception de domaine + message flash** (précédent : `LastActiveDirectorException`) : `HolidayAdjustmentRefusedException` sur `remove()`, levée quand le jour n'est pas un jour légal de ce calendrier ou qu'il est déjà retiré. Le bouton ne le propose pas, mais la route reste protégée.
- **`TimeEntryRefusedException::holiday()`** : le Live Component `Timesheet` affiche déjà le message de toute `TimeEntryRefusedException` (`src/Twig/Components/Timesheet.php:101`). Rien à changer côté composant.
- **`#[IsGranted('ROLE_DIRECTION')]` + `access_control` `^/jours-feries`**, avec des boutons POST protégés par jeton CSRF (précédent : `TeamController`).
- **`ClockInterface`** : année par défaut de la page. Les tests fixent l'horloge avec `ClockSensitiveTrait`.
- **Composants Paper existants** : `Card`, `Badge` (« Week-end », « Ajouté », « Retiré »), `Button` et `Alert`. Le token `neutral-tertiary` (gray-100) grise la colonne fériée : `neutral-secondary-soft` (gray-50) ne se distinguait pas à l'écran. Aucun nouveau token.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Bibliothèque `azuyalabs/yasumi` | Une dépendance pour 21 dates, qui ajoute des jours à filtrer (Pâques et Pentecôte le dimanche, sous-régions) et des libellés à réaligner. |
| Calcul de Pâques codé à la main (Meeus) | `easter_days()` est natif et évite dix lignes d'arithmétique à tester. Ce calcul reste le repli si l'hôte de production n'a pas `ext-calendar`. |
| Table matérialisée des jours fériés par année | Il faudrait la générer chaque année (commande, oubli possible), et elle mélangerait loi et ajustements. Le calcul à la volée ne coûte rien. |
| Deux tables, une pour les ajouts et une pour les retraits | Une table avec un type suffit, et la contrainte unique (`calendar`, `day`) interdit d'emblée un ajout et un retrait le même jour. |
| Plafond intégré à `quartersFor()` | La fiche Équipe afficherait le maximum amputé des jours fériés de la semaine en cours, au lieu du maximum réglé. |
| Baisse autorisée sur un jour férié (règle 13 de la story 003) | Le pitch impose un verrou absolu. La reprise des temps existants reste une question ouverte du pitch. |
| Barres désactivées dans la colonne fériée | Choix UX : une colonne chômée reste vide et porte seulement son libellé. |
| Refus d'un ajout par exception et message flash | Le formulaire serait vidé et l'erreur ne serait pas rattachée au champ. On garde ce mécanisme pour les boutons sans formulaire. |

## Modèle de données

### Nouvelle structure `HolidayAdjustment`

`src/Entity/HolidayAdjustment.php` (table `holiday_adjustment`) :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `id` | entier, auto | non | |
| `calendar` | `HolidayCalendar` (`enumType`, colonne `calendar`, longueur 2) | non | |
| `day` | `DATE_IMMUTABLE` (colonne `day`) | non | du lundi au vendredi pour un ajout (validé par `AddableHoliday`) |
| `type` | `HolidayAdjustmentType` (`enumType`, colonne `type`, longueur 10) | non | `added` ou `removed` |
| `label` | chaîne 100 (colonne `label`) | oui | obligatoire pour `added` (`NotBlank` sur le DTO), `null` pour `removed` |

Contrainte unique `uniq_holiday_adjustment_calendar_day` (`calendar`, `day`). Elle sert aussi d'index aux requêtes par intervalle (`calendar = ? AND day BETWEEN ? AND ?`). La structure n'a pas de lien vers `User` : un ajustement vaut pour tout un calendrier. Le repository `HolidayAdjustmentRepository` expose `findBetween(HolidayCalendar, \DateTimeImmutable $from, \DateTimeImmutable $to): list<HolidayAdjustment>` et `findOneAt(HolidayCalendar, \DateTimeImmutable $day): ?HolidayAdjustment`.

### Modification de `User`

`src/Entity/User.php` :

| Champ | Type | Nullable | Contrainte |
|---|---|---|---|
| `holidayCalendar` | `HolidayCalendar` (`enumType`, colonne `holiday_calendar`, longueur 2, `options: ['default' => 'fr']`) | non | valeur PHP par défaut `HolidayCalendar::France` |

La migration ajoute la colonne avec `DEFAULT 'fr'` : tous les comptes existants suivent la France (règle 9 du pitch), sans étape de remplissage.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `src/Enum/Type/HolidayCalendar.php` | Enum `fr` / `be` avec `label()` (« France », « Belgique »). |
| `src/Enum/Type/HolidayAdjustmentType.php` | Enum `added` / `removed`. |
| `src/Service/LegalHolidays.php` | Calcul pur des jours légaux d'une année pour un calendrier, week-ends compris : `array<'Y-m-d', libellé>` trié par date. |
| `src/Entity/HolidayAdjustment.php` | Ajustement d'un calendrier à une date (ajout avec libellé, ou retrait). |
| `src/Repository/HolidayAdjustmentRepository.php` | `findBetween()`, `findOneAt()`. |
| `src/Service/HolidayManager.php` | `holidaysOf(User, Week)`, `isHoliday(HolidayCalendar, jour)`, `adjustmentAt(HolidayCalendar, jour)`, `yearOf(HolidayCalendar, année)`, `add()`, `remove()`, `cancel()`. Couvre le cas d'une semaine à cheval sur deux années. |
| `src/Model/Holiday/HolidayLine.php` | Ligne de la vue annuelle : jour, libellé, statut (légal, ajouté, retiré), week-end, ajustement éventuel. |
| `src/Exception/HolidayAdjustmentRefusedException.php` | Refus d'un retrait : jour non légal dans ce calendrier, ou déjà retiré. |
| `src/Dto/HolidayAdditionInput.php` | Données du formulaire d'ajout : calendrier, jour, libellé (`NotNull`, `NotBlank`, `Length(max: 100)`), et contrainte de classe `AddableHoliday`. |
| `src/Validator/AddableHoliday.php` | Contrainte de classe (cible `day`). |
| `src/Validator/AddableHolidayValidator.php` | Refuse un week-end, un jour déjà férié ou un jour légal retiré, via `HolidayManager`. |
| `src/Form/HolidayAdditionType.php` | Choix du calendrier (boutons radio), date (`single_text`), libellé. |
| `src/Controller/HolidayController.php` | Routes `GET\|POST /jours-feries/{année}` (vue et ajout, année `[1-9]\d{3}` ; `/jours-feries` affiche l'année en cours), `POST /jours-feries/retirer` (redirige vers l'année du jour retiré) et `POST /jours-feries/ajustements/{id}/annuler`. |
| `templates/holiday/index.html.twig` | Navigation d'année en année (liens masqués aux bornes 1000 et 9999), formulaire d'ajout, une carte par calendrier (liste : date, jour, libellé, badges, actions). |
| `migrations/VersionXXXX.php` | Générée : `user.holiday_calendar` (`DEFAULT 'fr'`) et table `holiday_adjustment`. |
| `tests/Unit/Service/LegalHolidaysTest.php` | Calendrier France : 11 dates et libellés ; Belgique : 10. Fêtes mobiles 2026, 2027 et 2028. Le 8 mai n'est férié qu'en France, le 21 juillet qu'en Belgique. |
| `tests/Service/HolidayManagerTest.php` | `holidaysOf` (calendrier de la personne, du lundi au vendredi, ajouts et retraits, semaine 2026-W53 avec le 1er janvier 2027), `yearOf` (statuts, week-end), `remove` refusé, `cancel` qui rétablit. |
| `tests/Controller/HolidayControllerTest.php` | Accès réservé à la direction, vue 2026, ajout, refus d'ajout, retrait, annulation, jeton CSRF invalide, entrée de menu, années hors bornes. |
| `tests/Support/PurgesHolidayAdjustments.php` | Trait de test : purge les ajustements de 2031 et après à la fin de chaque test. |
| `tests/e2e/holidays.spec.ts` | Parcours de la direction (ajout, retrait, annulation) et effet dans la grille de `prod@example.com`, sur ordinateur et sur téléphone. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `composer.json` / `composer.lock` | Ajouter `"ext-calendar": "*"` dans `require` (`symfony composer require ext-calendar:*`). |
| `src/Entity/User.php` | Ajouter `holidayCalendar` (enum, défaut `France`), avec son getter et son setter. |
| `src/Service/WeeklyMaxManager.php` | Ajouter `capFor(User, Week, int $holidayCount): int<0, 20>`, égal à `min(quartersFor(), 4 × (5 − holidayCount))`. |
| `src/Model/Timesheet/TimesheetDay.php` | Ajouter `?string $holiday` (libellé), lu directement par le gabarit. |
| `src/Service/TimesheetBuilder.php` | Injecter `HolidayManager`. Verrouiller les cases d'un jour férié (`locked`), porter le libellé dans `TimesheetDay`, exclure les jours fériés de `forgotten`, plafonner avec `capFor()`. |
| `src/Service/TimesheetManager.php` | Injecter `HolidayManager`. Refuser tout enregistrement sur un jour férié avant les plafonds (valeur 0 comprise). Remplacer `quartersFor()` par `capFor()`. |
| `src/Exception/TimeEntryRefusedException.php` | Ajouter `holiday(\DateTimeImmutable $day, string $label)` : « Le 11/11 est férié (Armistice 1918) : on n'y saisit pas de temps. » |
| `templates/components/Timesheet.html.twig` | Colonne fériée sur fond `bg-neutral-tertiary` :<br>• en-tête `data-holiday="true"` avec « Férié » et le libellé, sans total ;<br>• cellules vides (`data-holiday="true"`, sans barre) ;<br>• onglet de téléphone marqué « Férié » ;<br>• sur téléphone, un avis `data-test="holiday-notice"` (« Jour férié · Toussaint ») quand le jour affiché est férié. |
| `src/Dto/TeamMemberInput.php` | Ajouter `holidayCalendar` (`NotNull`, défaut `France`), repris par `fromUser()`. |
| `src/Form/TeamMemberType.php` | Ajouter `holidayCalendar` : `EnumType` en boutons radio, libellé « Jours fériés », aide « Le calendrier suivi, pas la nationalité. », `data-test="member-holiday-calendar-{fr\|be}"`. |
| `src/Service/TeamManager.php` | `apply()` recopie `holidayCalendar`. |
| `templates/team/_form.html.twig` | Afficher `form_row(form.holidayCalendar)`. |
| `config/packages/security.yaml` | Ajouter `access_control` `^/jours-feries` pour `ROLE_DIRECTION`. |
| `templates/base.html.twig` | Ajouter l'entrée « Jours fériés » (`data-test="nav-holidays"`, `tabler:calendar-off`) dans le bloc réservé à la direction. |
| `fixtures/DemoCompanyFixtures.php` | Injecter `LegalHolidays` et `HolidayManager`. Rattacher deux personnes à la Belgique. Ajouter un jour de remplacement (« Remplacement du jj/mm ») pour chaque jour légal belge tombé un week-end dans la fenêtre des 26 semaines : le premier jour ouvré suivant qui n'est pas férié. Ne générer aucun temps sur un jour férié de la personne. |
| `tests/Service/TimesheetBuilderTest.php` | Semaine 2026-W29 :<br>• pour une personne en France, le 14/07 est verrouillé, nommé et jamais oublié ; les autres jours restent signalés ; le maximum passe à 16 quarts pour 20 comme pour 18 ;<br>• pour une personne en Belgique, le 14/07 est un jour ordinaire. |
| `tests/Service/TimesheetManagerTest.php` | Refus sur le 14/07 pour une personne en France, même à 0 ; accepté pour une personne en Belgique ; plafond de 16 quarts sur la semaine 2026-W29. `quartersOf()` accepte un intervalle de dates. |
| `tests/Service/WeeklyMaxManagerTest.php` | `capFor()` : 0, 1 et 5 jours fériés ; maximum inférieur aux jours ouvrés restants. |
| `tests/Twig/Components/TimesheetTest.php` | Colonne fériée sans cran, total de la semaine réduit, et message de refus de l'action `record` sur un jour férié. `component()` et `quartersOf()` acceptent une semaine et un intervalle. |
| `tests/Support/CreatesUsers.php` | `createUser()` accepte un calendrier (France par défaut). |
| `tests/Controller/TeamControllerTest.php` | Une inscription sans choix donne France ; une modification enregistre Belgique. |
| `tests/e2e/timesheet.spec.ts` | Neutraliser les jours fériés : `beforeAll` insère en SQL (`INSERT OR IGNORE`) un retrait sur le calendrier France pour chaque jour de la semaine en cours et de la précédente, avec le libellé `Neutralisation E2E`. `beforeAll` (avant l'insertion) et `afterAll` purgent ces lignes. |

## Hors scope

- **Reprise des temps déjà saisis sur un jour férié** (question ouverte du pitch) : aucune. Ces temps restent en base, comptent dans les totaux et ne sont pas affichés dans une colonne fériée.
- **Cache des jours légaux** : le calcul coûte quelques additions par année.
- **Notion générique de « jours ouvrés »** pour la capacité ou le rappel de saisie : `holidaysOf()` suffira à `rappel-saisie` et `capacite-equipe`, qui l'adapteront.
- **Colonne « calendrier » dans la liste Équipe, carte d'accueil « Jours fériés »** : le menu suffit, et le pitch ne les demande pas.
- **Refonte de `Week`** : inchangée.

## Impacts transverses

- **Cloisonnement des données** : la grille lit le calendrier de l'utilisateur de la sécurité (inchangé : `Timesheet::user()`), jamais celui d'une prop. Les ajustements sont communs à un calendrier et ne portent aucune donnée personnelle.
- **Déclinaisons / thèmes** : non.
- **Traduction / i18n** : non. Les libellés sont en français en dur, comme dans le reste de l'application. Les libellés des jours légaux vivent dans `LegalHolidays`.
- **API / exposition externe** : non.
- **Droits d'accès** :
  - `/jours-feries` est protégé par `#[IsGranted('ROLE_DIRECTION')]` et par `access_control` ;
  - le retrait et l'annulation passent par des POST avec jeton CSRF, et l'ajout par le CSRF du formulaire ;
  - le champ calendrier hérite de la protection de `/equipe` ;
  - le verrou de saisie s'applique à tous les rôles, dans `TimesheetManager`.
- **Emails / notifications** : non.
- **Migration de données** : ajout de la colonne `user.holiday_calendar` avec `DEFAULT 'fr'` (remplissage implicite) et création de `holiday_adjustment`. Le `down()` supprime les deux. Aucune reprise des temps.
- **Comportement par défaut** : tout le monde suit le calendrier France, et les jours légaux sont verrouillés dès la mise en service, sans action de la direction.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `src/Service/LegalHolidays.php` | unit | France 2026 : 11 dates et libellés ; Belgique 2026 : 10. Lundi de Pâques, Ascension et lundi de Pentecôte en 2027 (29/03, 06/05, 17/05) et en 2028 (17/04, 25/05, 05/06). 8 mai en France seulement, 21 juillet en Belgique seulement. Jours de week-end inclus (15/08/2026, 01/11/2026). |
| `src/Service/HolidayManager.php` | functional (base) | Semaine 2026-W29 : 14/07 pour la France, rien pour la Belgique. Semaine 2026-W53 : 01/01/2027 férié. Un ajout sur 2031-06-11 apparaît, un retrait du 02/06/2031 (lundi de Pentecôte) disparaît, et l'annulation rétablit. Un retrait sur un jour non légal ou déjà retiré lève `HolidayAdjustmentRefusedException`. `yearOf` expose les statuts et les week-ends. |
| `src/Service/WeeklyMaxManager.php` | functional (base) | `capFor()` : 20 → 16 avec un jour férié, 18 → 16, 12 → 12, 20 → 0 avec cinq. |
| `src/Service/TimesheetBuilder.php` | functional (base) | Colonne fériée verrouillée et nommée, jamais oubliée ; plafond de la semaine ; personne rattachée à la Belgique. |
| `src/Service/TimesheetManager.php` | functional (base) | Refus sur un jour férié (y compris à 0), acceptation pour l'autre calendrier, plafond de la semaine réduit. |
| `src/Twig/Components/Timesheet.php` | functional | Message de refus affiché pour un enregistrement sur un jour férié. |
| `src/Controller/TeamController.php` | functional | Défaut France à l'inscription ; Belgique enregistré en modification. |
| `src/Controller/HolidayController.php` + `AddableHolidayValidator` | functional | Lead et prod en 403. Vue 2026 : 11 et 10 lignes, 15/08 et 01/11 marqués « Week-end », France cochée par défaut. Ajouts refusés : un samedi, un jour légal, sans libellé, un jour légal retiré. Ajout accepté (« Ajouté »), retrait (« Retiré »), annulation. Jeton CSRF invalide en 403. Menu visible de la direction seule. `/jours-feries/0000` en 404, pas de lien hors des bornes 1000 et 9999. |
| `tests/e2e/holidays.spec.ts` | E2E | Semaine 2030-W24 :<br>• la direction ajoute le 12/06/2030 en France (« Remplacement E2E ») ;<br>• `prod@example.com`, sur `/saisie/2030-W24`, voit « Férié · Remplacement E2E » et une colonne sans barre, puis l'onglet et l'avis sur téléphone ;<br>• la direction annule l'ajout et la colonne redevient ordinaire ;<br>• la direction retire le 11/11/2030 (« Retiré »), puis annule ;<br>• le lead reçoit un 403. |
| `tests/e2e/timesheet.spec.ts` | E2E | Scénarios existants inchangés, rendus indépendants des jours fériés de la semaine réelle par la neutralisation. |

**Hors scope tests** :

- Pas de test unitaire isolé de `AddableHolidayValidator` : il dépend de `HolidayManager` (classe finale, sans double), et chaque refus est couvert par le test fonctionnel du contrôleur.
- Pas de test des fixtures de démonstration : elles sont vérifiées à la main après `make db-reset` (critère de sortie).
- Pas de test de la migration au-delà de `doctrine:schema:validate` et d'un aller-retour `migrate` / `migrate prev` en local.

**Convention de test** : la base de test n'est pas remise à zéro entre les tests. Tout ajustement écrit par un test PHPUnit porte donc sur une date de **2031 ou après**, pour ne jamais toucher les semaines 2026 des autres tests de saisie. Le trait `PurgesHolidayAdjustments` supprime ces ajustements à la fin de chaque test, ce qui rend les tests rejouables sans réinitialiser la base.

## Ordre d'exécution

1. [x] **Calendriers légaux**
   - Objectif : jours légaux France et Belgique calculés pour n'importe quelle année.
   - Fichiers : `composer.json`, `composer.lock`, `HolidayCalendar`, `LegalHolidays`, `LegalHolidaysTest`.
   - Vérification : `make phpunit-filter LegalHolidaysTest`, `make lint`.
   - Commitable seule : oui.

2. [x] **Modèle et migration**
   - Objectif : rattachement des personnes et ajustements persistés.
   - Fichiers : `User`, `HolidayAdjustmentType`, `HolidayAdjustment`, `HolidayAdjustmentRepository`, migration générée par `make migration` puis relue.
   - Vérification : `symfony console doctrine:schema:validate` ; aller-retour `doctrine:migrations:migrate` / `migrate prev` ; `make lint`.
   - Commitable seule : oui.

3. [x] **HolidayManager**
   - Objectif : jours fériés effectifs, vue annuelle, ajout, retrait, annulation.
   - Fichiers : `HolidayManager`, `HolidayLine`, `HolidayAdjustmentRefusedException`, `HolidayManagerTest`, `PurgesHolidayAdjustments`, `CreatesUsers`.
   - Vérification : `make phpunit-filter HolidayManagerTest`, `make lint`.
   - Commitable seule : oui.

4. [x] **Verrou dans la saisie**
   - Objectif : colonne fériée verrouillée et nommée, refus à l'enregistrement, plafond de la semaine.
   - Fichiers : `WeeklyMaxManager`, `TimesheetDay`, `TimesheetBuilder`, `TimesheetManager`, `TimeEntryRefusedException`, `Timesheet.html.twig`, et les tests Builder, Manager, WeeklyMax et composant.
   - Vérification : `make phpunit` ; contrôle visuel de `/saisie/2026-W46` (11/11) après `make serve`.
   - Commitable seule : oui.

5. [x] **Calendrier sur la fiche Équipe**
   - Objectif : la direction choisit France ou Belgique.
   - Fichiers : `TeamMemberInput`, `TeamMemberType`, `TeamManager`, `team/_form.html.twig`, `TeamControllerTest`.
   - Vérification : `make phpunit-filter TeamControllerTest`.
   - Commitable seule : oui.

6. [x] **Page « Jours fériés »**
   - Objectif : consultation par année, ajout, retrait et annulation par la direction.
   - Fichiers : `HolidayAdditionInput`, `AddableHoliday`, `AddableHolidayValidator`, `HolidayAdditionType`, `HolidayController`, `holiday/index.html.twig`, `security.yaml`, `base.html.twig`, `HolidayControllerTest`.
   - Vérification : `make phpunit-filter HolidayControllerTest`, `make lint`.
   - Commitable seule : oui.

7. [x] **Fixtures de démonstration**
   - Objectif : deux personnes en Belgique, jours de remplacement, aucun temps sur un jour férié.
   - Fichiers : `fixtures/DemoCompanyFixtures.php`.
   - Vérification : `make db-reset`, puis contrôle dans la grille d'une personne rattachée à la Belgique (21/07 férié et vide, 14/07 saisi).
   - Commitable seule : oui.

8. [x] **E2E et QA finale**
   - Objectif : parcours de bout en bout, scénarios existants protégés des jours fériés.
   - Fichiers : `tests/e2e/holidays.spec.ts`, `tests/e2e/timesheet.spec.ts`.
   - Vérification : `make serve` puis `make playwright`, `make phpunit`, `make lint`.
   - Commitable seule : oui.

## Critères de sortie

- [ ] `composer.json` déclare `ext-calendar`, et `LegalHolidays` n'utilise que `easter_days()` pour les fêtes mobiles.
- [ ] `symfony console doctrine:schema:validate` est vert. La migration passe dans les deux sens, et les comptes existants reçoivent `holiday_calendar = 'fr'`.
- [ ] Aucune requête hors de `src/Repository/`. `HolidayManager` est le seul consommateur de `HolidayAdjustmentRepository` et de `LegalHolidays` hors fixtures.
- [ ] Un rendu de grille et un enregistrement n'ajoutent qu'une requête (les ajustements de la semaine).
- [ ] `make phpunit` est vert, sans régression sur les tests de saisie existants.
- [ ] `make lint` est propre (PHP-CS-Fixer, PHPStan niveau 10).
- [ ] `make playwright` est vert, `holidays.spec.ts` compris. Aucune ligne `Neutralisation E2E` ne reste en base après le run.
- [ ] Après `make db-reset`, les données de démonstration comptent deux personnes rattachées à la Belgique, au moins un jour de remplacement, et aucun temps sur un jour férié de la personne.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| `ext-calendar` absente de l'hôte de production | faible | Déclarée dans `composer.json`, l'installation échoue explicitement. Repli documenté : calcul de Pâques maison (Meeus), limité à `LegalHolidays`. |
| Ajustements écrits par un test qui fuient vers les autres tests (base non réinitialisée entre les tests) | moyenne | Convention : dates de 2031 et après, purgées à la fin de chaque test par `PurgesHolidayAdjustments`. Les semaines 2026 des tests de saisie ne sont jamais touchées. |
| Retraits `Neutralisation E2E` laissés en base de dev par un run interrompu | faible | Libellé marqueur, purge au début de `beforeAll` comme dans `afterAll`. Un retrait sur un jour non légal est ignoré par `holidaysOf()`. |
| Base de dev existante avec des temps sur des jours fériés, avant `make db-reset` | élevée | Temps invisibles mais comptés dans les totaux. Relancer `make db-reset` après la migration (fixtures régénérées). Aucune donnée de production. |
| Semaine à cheval sur deux années (2026-W53) | moyenne | `holidaysOf()` calcule les jours légaux de chaque année couverte par la semaine. Cas testé. |
| Règles d'ajout dupliquées entre le validateur et le manager | faible | Le validateur interroge `HolidayManager::isHoliday()` et `adjustmentAt()`, sans logique propre. |
| Onglet de saisie ouvert avant l'ajout d'un jour férié | moyenne | `TimesheetManager` revérifie les jours fériés à chaque enregistrement (règle 14 du pitch). Le re-rendu du composant affiche ensuite la colonne verrouillée. |

## Questions ouvertes

- **Temps saisis sur un jour qui devient férié, une fois en production** (question ouverte du pitch, à trancher avant la mise en service) : le plan refuse tout enregistrement sur un jour férié, et ces temps restent invisibles dans la colonne. Options techniques selon l'arbitrage : (a) afficher la barre et n'autoriser que la baisse sur un jour férié qui porte des temps ; (b) refuser `add()` et le changement de calendrier tant que des temps existent sur les jours concernés (requête dans `TimeEntryRepository`).
