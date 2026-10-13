# Plan technique — Parler la même langue sur tous les écrans : « lot » à la place de « feuille », un seul mot et une seule unité par notion

> **But** : figer le comment technique de la feature — architecture, périmètre de code, ordre d'exécution.
> **Registre** : technique
> **Story** : `docs/story/015-f-vocabulaire-lot/`
> **Amont** : `pitch.md`

## Approche retenue

La story ne touche que des chaînes affichées. Le projet n'a aucun catalogue de traduction (`config/packages/translation.yaml` ne déclare que la locale `fr`, `translations/` est vide, seul `security/login.html.twig:33` appelle `trans`) : les libellés vivent en dur dans quatre couches, réécrites en place. Ce sont les gabarits Twig, deux énumérations (`RoadmapSignal` pour les pastilles et leurs infobulles, `TagCategory` pour les catégories de tags), les messages de contraintes et de DTO (`EstimateCoversConsumed`, `TeamMemberInput`), et un libellé de formulaire (`TeamMemberType`). Aucun nom de classe, de route, de paramètre d'URL ni de valeur enregistrée ne change, et les attributs `data-test` restent, sauf `roadmap-overrun-percent`, fondu dans `roadmap-overrun` puisque le récapitulatif affiche jours et part d'un seul tenant : le mot « feuille » reste le terme du code et de `docs/`, où il désigne exactement la même notion.

Le seul ajout de logique est le format d'écart des règles 5 à 7 du pitch. Un filtre Twig `gap` rejoint `days` dans `DaysExtension`. Il écrit « 3,25 j (13 %) » pour un dépassement et « +6,5 j (+25 %) » ou « −6 j (−30 %) » pour un écart projeté (signe moins typographique U+2212). Il s'appuie sur une fonction pure `Quarters::percentOf()`, qui reprend l'arrondi « jamais 0 % » de `RoadmapRow::overrunPercent()` (`src/Model/Roadmap/RoadmapRow.php:85`) ; cette méthode est supprimée, la pastille et le récapitulatif, ses seuls appelants, passant par le filtre. Les sept endroits où un écart est chiffré passent par ce filtre. Un test fonctionnel garde-fou parcourt les pages principales et échoue si un terme banni réapparaît dans le HTML rendu.

### Mécanismes mobilisés

- **Filtre Twig par attribut `#[AsTwigFilter]`** dans `DaysExtension` (`src/Twig/DaysExtension.php`) : même mécanisme que `days`, `half_days` et `weekday`. Un seul endroit formate un écart, ce qui tient la règle « jours puis part, toujours ensemble » quel que soit l'écran.
- **Fonction pure dans `App\Model\Quarters`** (`src/Model/Quarters.php`) : la conversion quarts → jours y vit déjà (`toDays()`). Le pourcentage « jamais 0 % » y devient testable sans Twig, et remplace `RoadmapRow::overrunPercent()`, supprimée.
- **Énumérations porteuses de leurs libellés** (`RoadmapSignal::label()`, `meaning()`, `reason()` ; `TagCategory::label()`, `pluralLabel()`) : les gabarits (`roadmap/_signal.html.twig`, `_signal_tooltips.html.twig`, `roadmap/_unknown_ends.html.twig`, `components/TagFilter.html.twig`, `person/show.html.twig`, `account/password.html.twig`, `tag/index.html.twig`) les lisent déjà. Les réécrire en un point propage le lexique partout.
- **Test fonctionnel `WebTestCase` sur les fixtures** pour le garde-fou : `make phpunit` recharge les fixtures avant la suite, les pages ont donc des données réelles (projet « Atlas — Plateforme de paie », personnes, congés en attente), y compris les infobulles cachées présentes dans le HTML.

### Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| Introduire un catalogue de traduction (`messages.fr.yaml`) pour centraliser le lexique | L'application est en français uniquement. Un catalogue ajouterait une indirection sur chaque libellé pour un seul usage, et toute la base devrait migrer pour que la centralisation ait un sens. |
| Renommer aussi le code (`Leaf*`, `isLeaf()`, `LeafPath`, `data-test="leaf-…"`) | Hors scope du pitch. Diff massif sans effet visible, risque de régression élevé sur les sélecteurs E2E. « Feuille » reste un terme exact dans le code. |
| Renommer la valeur enregistrée de la catégorie (`equipe` → `pole`) | Exigerait une migration de données et casserait le paramètre d'URL du filtre de la liste des personnes (`TeamList::$equipe`) pour un gain nul : la valeur n'est jamais affichée. |
| Garder `overrunPercent()` et calculer le pourcentage des lignes cumulées dans les gabarits | Deux arrondis dans deux langages finiraient par diverger. Le filtre unique couvre les lots, les lots découpés et les projets. |

## Modèle de données

Aucun impact modèle.

## Périmètre

### Fichiers à créer

| Fichier | Rôle |
|---|---|
| `tests/Unit/Model/QuartersTest.php` | `percentOf()` : arrondi, jamais 0 % sur un écart, signe conservé, `null` sur une estimation de 0 j. |
| `tests/Unit/Twig/DaysExtensionTest.php` | Filtre `gap` : « 3,25 j (13 %) », signé « +6,5 j (+25 %) » et « −6 j (−30 %) », sans part sur une estimation de 0 j. |
| `tests/Unit/Enum/Type/RoadmapSignalTest.php` | Aucun `label()`, `meaning()` ni `reason()` ne contient « feuille » ni « partie future » ; `PartialPlanning` se lit « planification partielle ». |
| `tests/Controller/VocabularyTest.php` | Garde-fou : en direction puis en prod, les pages principales rendues ne contiennent aucun terme banni. |

### Fichiers à modifier

| Fichier | Modification |
|---|---|
| `src/Model/Quarters.php` | Ajouter `percentOf(int $quarters, int $estimateDays): ?int` : part arrondie, jamais 0 en valeur absolue dès que `$quarters ≠ 0`, signe conservé, `null` si `$estimateDays === 0`. |
| `src/Model/Roadmap/RoadmapRow.php` | `overrunPercent()` (l. 85) supprimée : plus aucun gabarit ne l'appelle une fois la pastille et le récapitulatif passés par `gap`. |
| `src/Twig/DaysExtension.php` | Ajouter le filtre `gap(int $quarters, int $estimateDays, bool $signed = false)` : jours (`Quarters::toDays()` sur la valeur absolue) puis part entre parenthèses ; signe « + » ou « − » (U+2212) devant les deux quand `signed`. |
| `src/Enum/Type/RoadmapSignal.php` | `PartialPlanning` (l. 37) → « planification partielle » ; `Completed` → « terminé à 100 % », accordé au masculin ; `meaning()` et `reason()` (l. 45 à 73) : « feuille » → « lot » accordé au masculin, « partie future » → « restant » (ex. l. 45 « …ni le restant ni la fin du lot ne peuvent être calculés : rien n'est placé après aujourd'hui. »), l. 50 « Les jours saisis en dépassement apparaissent en rouge… », l. 52 « …comme celle de son lot découpé et de son projet… », l. 55 et 73 « lots planifiés » / « Au moins un lot du projet n'est pas planifié ». « Au-delà de 100 % » (l. 54, charge) reste. |
| `src/Enum/Type/TagCategory.php` | `TeamType` : `label()` « Pôle », `pluralLabel()` « Pôles » (l. 18 et 27). Valeur `equipe` inchangée. |
| `src/Validator/EstimateCoversConsumed.php` | `belowMessage` et `removedMessage` (l. 12-13) : « sur cette feuille » → « sur ce lot ». |
| `src/Dto/TeamMemberInput.php` | Message de `isTeamTypeUnambiguous` (l. 70) : « Choisissez un pôle existant ou saisissez-en un nouveau, pas les deux. » |
| `src/Form/TeamMemberType.php` | Champ `newTeamType` (l. 88) : libellé « Nouveau pôle ». |
| `templates/components/Timesheet.html.twig` | « Ajouter une ligne » → « Ajouter un lot » (bouton l. 16, titre de la fenêtre l. 21) ; « Aucune feuille ne correspond. » → « Aucun lot ne correspond. » (l. 46) ; état vide (l. 165) « …ajoutez un lot pour commencer. ». |
| `templates/components/LotForm.html.twig` | Descriptions de sections : l. 7 « Ce que recouvre le lot, tel que chacun le lira dans sa semaine. » ; l. 22 « La date de début et l'équipe placent le lot sur la roadmap. ». |
| `templates/components/TeamList.html.twig` | En-tête de colonne « Équipe » (l. 42) → « Pôle ». Les filtres lisent déjà `TagCategory::label()`. |
| `templates/components/TimeOffInbox.html.twig` | l. 29 « · dépassement » → « · solde dépassé » ; l. 35 « Aucun autre collaborateur du même manager n'est absent sur la période. ». |
| `templates/time_off/inbox.html.twig` | Description (l. 6) : « …et les autres absents parmi les collaborateurs du même manager. ». |
| `templates/project/_signals.html.twig` | l. 6 « N responsable(s) à désigner », l. 9 « N responsable(s) à remplacer » (pluriel selon N). |
| `templates/project/_lot_row.html.twig` | Badge « à redésigner » (l. 24) → « à remplacer » ; l. 46 « …saisis, <gap> de dépassement » avec `summary.overrunQuarters|gap(summary.estimateDays)`. |
| `templates/project/_estimate.html.twig` | Badge « partiel » (l. 3) → « estimation partielle ». |
| `templates/project/show.html.twig` | En-tête de colonne « Consommé » (l. 111) → « Saisi ». |
| `templates/roadmap/index.html.twig` | Description (l. 10) : « …réparti sur la capacité de son équipe. ». |
| `templates/roadmap/project.html.twig` | Légende de l'en-tête (l. 72) : dépassement via `gap(summary.estimateDays)`, « 0 j » sans part quand il n'y en a pas ; titre de section (l. 78) « Lots, saisi face à l'estimation » ; état vide de la frise (l. 117) « Aucun lot n'a encore… ». |
| `templates/roadmap/_consumption.html.twig` | En-têtes : « Consommation » → « Saisi face à l'estimation » (l. 16), « Estimé » → « Estimation » (l. 17). |
| `templates/roadmap/_consumption_row.html.twig` | Dépassement (l. 39) : `line.overrunQuarters|gap(line.estimateDays)` suivi de « de dépassement » ; « partiel » (l. 53) → « sur les lots estimés » ; projeté (l. 58-61) : total puis, à la ligne, `projectedGap|gap(line.estimateDays, signed: true)`, la variable `gap` renommée pour ne plus porter le nom du filtre. |
| `templates/roadmap/_legend.html.twig` | « Saisi au-delà de l'estimation » → « Saisi en dépassement » (l. 8) ; « Lot ou projet » → « Lot découpé ou projet » (l. 13). |
| `templates/roadmap/_tooltip.html.twig` | Légende `overrun` (l. 7) → « Saisi en dépassement » ; `span` (l. 9) « Lot » → « Lot découpé » ; l. 32 « …sur une estimation de N j ». |
| `templates/roadmap/_recap.html.twig` | `<dt>Estimé</dt>` (l. 21) → « Estimation » ; dépassement (l. 27) via `gap(row.lot.estimateDays)`, dans le seul `data-test="roadmap-overrun"` (`roadmap-overrun-percent` disparaît). |
| `templates/roadmap/_progress.html.twig` | Projeté (l. 10-11) : « 20 j pour une estimation de 10 j, +10 j (+100 %) » via `projectedGap|gap(…, signed: true)`. |
| `templates/roadmap/_timeline.html.twig` | Badge « au-delà de l'estimation » (l. 33) → « en dépassement ». |
| `templates/roadmap/_signal.html.twig` | Pastille de dépassement (l. 10) : « en dépassement de <gap> » quand `row.overrunQuarters` est défini et positif (lignes de la roadmap) ; libellé seul sinon (fiche personne). |
| `templates/person/_tooltip.html.twig` | Légende `overrun` (l. 8) → « Saisi en dépassement ». |
| `templates/team/_form.html.twig` | Section « Rattachement » (l. 18) : « Le pôle dont la personne fait partie, et la personne à qui elle rapporte. » ; « Compétences » (l. 23) : « …à composer l'équipe de chaque lot. ». |
| `templates/account/password.html.twig` | Profil (l. 35) : « …Il aide les leads à composer l'équipe de chaque lot. ». |
| `templates/tag/index.html.twig` | Description (l. 6) : « Le vocabulaire qui décrit les personnes : compétences techniques, expériences fonctionnelles et pôles… ». |
| `templates/page/index.html.twig` | Accueil (l. 9) « Suivez la charge, l'avancement et la fin calculée de chaque projet. » ; raccourcis : Roadmap (l. 32) « …et sa fin calculée. » ; Projets (l. 40) « …estimer et confier chaque lot. » ; Tags (l. 43) « …des expériences et des pôles. ». |
| `templates/security/login.html.twig` | Texte de présentation (l. 121) : « Temps saisis, saisi face à l'estimation, capacité de l'équipe et fins calculées, au même endroit. » (« l'équipe » au sens global, exception de la règle 11). |
| `tests/Controller/RoadmapControllerTest.php` | l. 139 pastille « en dépassement de 5 j (50 %) » (valeurs du cas) ; l. 179 « 20 j pour une estimation de 10 j, +10 j (+100 %) ». |
| `tests/Controller/RoadmapProjectControllerTest.php` | l. 99-100 « 5 j (…) de dépassement » selon l'estimation de chaque ligne ; l. 102 en-tête « 5 j (50 %) » ; l. 124 projeté « 12 j +4 j (+50 %) ». |
| `tests/Controller/ProjectControllerTest.php` | l. 134-135 « 1 responsable à désigner », « 1 responsable à remplacer » ; nouveau test du dépassement de la liste des lots, « 0,5 j (25 %) de dépassement ». |
| `tests/Controller/PersonControllerTest.php` | Nouveau test : la pastille de dépassement de la fiche personne dit seulement « en dépassement ». |
| `tests/Unit/Model/Roadmap/RoadmapRowTest.php`, `tests/Service/RoadmapBuilderTest.php` | Assertions sur `overrunPercent()` retirées avec la méthode ; ses cas d'arrondi passent dans `QuartersTest`. |
| `tests/Controller/TeamControllerTest.php` | l. 342 message de pôle ambigu. |
| `tests/Controller/LotControllerTest.php` | l. 246 « …déjà saisis sur ce lot : … ». |
| `tests/e2e/progress.spec.ts` | l. 70 projeté « 14 j » et « −6 j (−30 %) » ; l. 82 « 14 j pour une estimation de 20 j, −6 j (−30 %) ». |

## Hors scope

- **Libellés de la page des personnes** (menu « Équipe », titre, retours « Équipe », `team/temporary_password.html.twig:29`, messages de `UniqueTeamEmail`) : la story 017 renomme la page et ces libellés avec elle (règle 13 du pitch).
- **Structure des pages projet, accueil, menu** : stories 016 et 017. Les gabarits `project/show.html.twig`, `project/_lot_row.html.twig` et `page/index.html.twig` changent ici de quelques mots ; 016 et 017 les reprennent ensuite.
- **Messages d'exceptions non affichés** (`NotFoundHttpException` de `src/Twig/Components/Timesheet.php:95` et `:147`) : rendus par la page d'erreur générique en production, jamais lus par une personne.
- **Code, `data-test`, routes, paramètres d'URL, valeurs enregistrées, `docs/`** : inchangés, y compris les titres de tests E2E qui parlent de « feuille ». Seules exceptions : `RoadmapRow::overrunPercent()`, supprimée, et `data-test="roadmap-overrun-percent"`, fondu dans `roadmap-overrun`.
- **E-mails de congés** : aucun terme du lexique ; le pied « le pilotage de production de l'équipe » relève de l'exception.

## Impacts transverses

- **Cloisonnement des données** : non. Aucune requête ni aucun contrôle d'accès ne change.
- **Déclinaisons / thèmes** : non. Mêmes composants (`Badge`, `Table:Head`), mêmes tokens.
- **Traduction / i18n** : libellés français réécrits en dur, comme le reste de l'application ; pas de catalogue introduit.
- **API / exposition externe** : non.
- **Droits d'accès** : inchangés.
- **Emails / notifications** : non.
- **Migration de données** : aucune. La catégorie `TagCategory::TeamType` garde la valeur `equipe`.
- **Comportement par défaut** : tout le monde voit les nouveaux libellés à la mise en service.

## Stratégie de test

| Code | Type | Ce qu'on vérifie |
|---|---|---|
| `tests/Unit/Model/QuartersTest.php` | unit | `percentOf(13, 26)` → 13 (3,25 j sur 26 j) ; un écart minime → 1, jamais 0 ; écart négatif → part négative ; estimation 0 → `null`. |
| `tests/Unit/Twig/DaysExtensionTest.php` | unit | `gap(13, 26)` → « 3,25 j (13 %) » ; `gap(26, 26, signed: true)` → « +6,5 j (+25 %) » ; `gap(-24, 20, signed: true)` → « −6 j (−30 %) » ; `gap(13, 0)` → « 3,25 j ». |
| `tests/Unit/Model/Roadmap/RoadmapRowTest.php` | unit | Cas d'`overrunPercent()` retirés avec la méthode ; le coût projeté reste couvert. |
| `tests/Unit/Enum/Type/RoadmapSignalTest.php` | unit | Pour chaque cas : `label()`, `meaning()`, `reason()` sans « feuille » ni « partie future » ; `PartialPlanning->label()` vaut « planification partielle ». |
| `tests/Controller/VocabularyTest.php` | functional (HTTP) | En direction puis en prod, `/`, `/saisie`, `/roadmap`, `/projets`, `/projets/{Atlas}`, `/roadmap/projets/{Atlas}`, `/personnes/{id}`, `/mon-compte/mot-de-passe`, puis en direction seulement `/tags`, `/lots/{id}/modifier`, `/conges/a-valider`, `/equipe` et `/equipe/{id}/modifier` : le contenu rendu ne contient ni `/\bfeuilles?\b/iu`, ni « consomm », ni « au-delà de l'estimation », ni « type d'équipe », ni « planning partiel », ni « date de fin projetée ». |
| `tests/Controller/RoadmapControllerTest.php` | functional (HTTP) | La pastille de dépassement et le projeté au survol au nouveau format. |
| `tests/Controller/RoadmapProjectControllerTest.php` | functional (HTTP) | Le dépassement du tableau des lots au nouveau format ; le test de fin inconnue (l. 323) reste vert, le libellé « en dépassement » ne changeant pas. |
| `tests/Controller/ProjectControllerTest.php`, `TeamControllerTest.php`, `LotControllerTest.php` | functional (HTTP) | Les libellés de la liste des projets, le dépassement de la liste des lots, le message de pôle ambigu et le refus d'estimation reformulés. |
| `tests/Controller/PersonControllerTest.php` | functional (HTTP) | La pastille de dépassement de la fiche personne, sans écart chiffré (règle 7). |
| `tests/e2e/progress.spec.ts` | E2E | Le projeté de la fiche et de l'infobulle au nouveau format, signe moins compris. |

**Hors scope tests** :

- Pas de test dédié par gabarit modifié : le garde-fou parcourt les pages qui les rendent, et les tests existants ciblent déjà les zones chiffrées par leur `data-test`.
- Pas de garde-fou sur les e-mails : ils ne portent aucun terme du lexique.
- Pas de test sur la page de connexion : texte de présentation statique, couvert par la relecture.

## Ordre d'exécution

1. [x] **Format d'écart**
   - Objectif : `Quarters::percentOf()`, remplacement de `RoadmapRow::overrunPercent()`, filtre `gap`.
   - Fichiers : `src/Model/Quarters.php`, `src/Model/Roadmap/RoadmapRow.php`, `src/Twig/DaysExtension.php`, `tests/Unit/Model/QuartersTest.php`, `tests/Unit/Twig/DaysExtensionTest.php`, `tests/Unit/Model/Roadmap/RoadmapRowTest.php`, `tests/Service/RoadmapBuilderTest.php`.
   - Vérification : `make phpunit-filter 'QuartersTest|DaysExtensionTest|RoadmapRowTest'`.
   - Commitable seule : oui.

2. [x] **Énumérations et messages**
   - Objectif : `RoadmapSignal` et `TagCategory` réécrits, messages de contraintes et libellé « Nouveau pôle ».
   - Fichiers : `src/Enum/Type/RoadmapSignal.php`, `src/Enum/Type/TagCategory.php`, `src/Validator/EstimateCoversConsumed.php`, `src/Dto/TeamMemberInput.php`, `src/Form/TeamMemberType.php`, `tests/Unit/Enum/Type/RoadmapSignalTest.php`, `tests/Controller/TeamControllerTest.php`, `tests/Controller/LotControllerTest.php`.
   - Vérification : `make phpunit-filter 'RoadmapSignalTest|TeamControllerTest|LotControllerTest|TagControllerTest'`.
   - Commitable seule : oui.

3. [x] **Saisie**
   - Objectif : « Ajouter un lot », recherche et état vide.
   - Fichiers : `templates/components/Timesheet.html.twig`.
   - Vérification : `make phpunit-filter 'TimesheetTest|TimesheetControllerTest'`.
   - Commitable seule : oui.

4. [x] **Projets et lots**
   - Objectif : liste des projets, page d'administration, formulaire de lot.
   - Fichiers : `templates/project/_signals.html.twig`, `_lot_row.html.twig`, `_estimate.html.twig`, `show.html.twig`, `templates/components/LotForm.html.twig`, `tests/Controller/ProjectControllerTest.php`.
   - Vérification : `make phpunit-filter 'ProjectControllerTest|LotControllerTest|LotFormTest'`.
   - Commitable seule : oui.

5. [x] **Roadmap et fiche d'un projet**
   - Objectif : tableau des lots, légendes, infobulles, récapitulatif, projeté, journal, pastille.
   - Fichiers : `templates/roadmap/index.html.twig`, `project.html.twig`, `_consumption.html.twig`, `_consumption_row.html.twig`, `_legend.html.twig`, `_tooltip.html.twig`, `_recap.html.twig`, `_progress.html.twig`, `_timeline.html.twig`, `_signal.html.twig`, `templates/person/_tooltip.html.twig`, `tests/Controller/RoadmapControllerTest.php`, `tests/Controller/RoadmapProjectControllerTest.php`, `tests/e2e/progress.spec.ts`.
   - Vérification : `make phpunit-filter 'Roadmap|PersonControllerTest'` puis `make playwright-file tests/e2e/progress.spec.ts`.
   - Commitable seule : oui.

6. [x] **Personnes, profil et tags**
   - Objectif : pôle dans la liste et le formulaire des personnes, Mon compte, Tags.
   - Fichiers : `templates/components/TeamList.html.twig`, `templates/team/_form.html.twig`, `templates/account/password.html.twig`, `templates/tag/index.html.twig`.
   - Vérification : `make phpunit-filter 'TeamControllerTest|TeamListTest|TagControllerTest|AccountControllerTest'`.
   - Commitable seule : oui.

7. [x] **Congés, accueil, connexion**
   - Objectif : collaborateurs du même manager, solde dépassé, raccourcis de l'accueil, texte de connexion.
   - Fichiers : `templates/components/TimeOffInbox.html.twig`, `templates/time_off/inbox.html.twig`, `templates/page/index.html.twig`, `templates/security/login.html.twig`.
   - Vérification : `make phpunit-filter 'TimeOffControllerTest|TimeOffInboxTest|NavigationTest|SecurityControllerTest'`.
   - Commitable seule : oui.

8. [x] **Garde-fou et QA finale**
   - Objectif : le test de vocabulaire passe ; toute la suite, l'analyse statique et les scénarios E2E touchés sont verts.
   - Fichiers : `tests/Controller/VocabularyTest.php`.
   - Vérification : `make phpunit`, `make lint`, `make playwright-file tests/e2e/progress.spec.ts`, puis `roadmap.spec.ts`, `projects.spec.ts`, `timesheet.spec.ts`, `time-off.spec.ts`, `team.spec.ts`, `tags.spec.ts`.
   - Commitable seule : oui.

## Critères de sortie

- [x] `tests/Controller/VocabularyTest.php` vert pour la direction et la prod.
- [x] Aucune chaîne affichée dans `templates/` et `src/` ne contient « feuille » (vérifié par `grep -rniE "['\">][^'\"<]*feuille" templates src` hors commentaires et noms de code).
- [x] Les sept affichages d'un écart chiffré passent par le filtre `gap`.
- [x] Suite PHPUnit verte, sans nouvelle régression (`make phpunit`).
- [x] `make lint` propre (PHP-CS-Fixer et PHPStan niveau 10).
- [ ] Scénarios Playwright touchés verts.
- [x] Aucune migration générée, aucune donnée modifiée.

## Risques et mitigations

| Risque | Probabilité | Mitigation |
|---|---|---|
| Un libellé oublié, dans un gabarit peu visité ou une infobulle cachée | moyenne | Le garde-fou rend les pages principales avec les fixtures et cherche les termes bannis dans tout le HTML, infobulles cachées comprises ; le test d'énumération couvre tous les cas de `RoadmapSignal`. |
| Un test E2E ou fonctionnel cible un texte modifié | moyenne | Inventaire fait au plan (six fichiers de test listés) ; les autres ciblent des `data-test`, inchangés. Lancer les specs E2E de chaque zone à l'étape correspondante. |
| La pastille de dépassement, plus longue, déborde de la colonne des titres de la roadmap ou d'une fiche | faible | Les pastilles sont dans un conteneur `flex-wrap` ; vérification visuelle à 1440 px et à 390 px. |
| Le signe moins typographique (U+2212) gêne une comparaison de texte ou une copie | faible | Le format est produit à un seul endroit ; les tests l'assertent explicitement. |
| Conflit avec les stories 016, 017 et 018, qui retouchent les mêmes gabarits | moyenne | 015 se livre en premier ; les trois autres reprennent le lexique et le filtre `gap` au lieu de les redéfinir. |

## Questions ouvertes

- **Nom de la catégorie « Type d'équipe »** : question métier reproduite du pitch. Le plan retient « Pôle » / « Pôles » ; changer de mot ne touche que `TagCategory`, les deux messages de `TeamMemberInput` et `TeamMemberType`, et l'en-tête de `TeamList`.
