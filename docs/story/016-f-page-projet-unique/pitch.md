# Lire et gérer un projet sur une seule page, chacun selon ses droits

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/016-f-page-projet-unique/`
> **Amont** : aucun

Chaque projet n'a plus qu'une page. On y lit où il en est : synthèse, lots, frise, journal. Chacun y trouve aussi les actions que ses droits lui ouvrent : modifier le projet, ajouter un lot, déclarer un avancement. La direction lit et le lead agit au même endroit, sur les mêmes chiffres et avec les mêmes mots. L'en-tête annonce enfin ce que le projet coûtera à terme face à son estimation, au lieu d'un saisi qui laisse croire que tout va bien.

## Contexte

Un projet a aujourd'hui deux pages qui portent le même titre. La page de gestion, rangée sous « Administration », sert au découpage : responsables, estimations, déclaration d'avancement. La fiche s'ouvre depuis la roadmap. Elle est en lecture seule et montre la synthèse, le consommé face à l'estimé, la frise et le journal. Les deux présentent les lots avec une règle graduée, mais avec des colonnes et des mots différents : « Consommé » et « au-delà » sur l'une, « Saisi » et « de dépassement » sur l'autre. Rien n'indique laquelle est la page du projet. Un lead déclare l'avancement sur la page de gestion, puis doit ouvrir la fiche pour en voir l'effet sur le restant et la fin calculée. La direction lit la fiche, mais n'y voit ni les responsables ni ce qui reste à compléter.

La fiche se contredit aussi dans son en-tête. Sur le projet Atlas, elle annonce « 362,5 j saisis sur une estimation de 390 j », ce qui laisse croire qu'il reste 27,5 j. Juste en dessous, la légende indique pourtant 99,75 j de restant et 26 j de dépassement, soit un projet qui coûtera au moins 462 j. Ce total n'est écrit nulle part. Enfin, son journal liste tout l'historique sans repli : la fiche d'Atlas dépasse dix mille pixels de haut, et y ajouter la gestion l'allongerait encore.

Cette story revient donc sur des décisions des stories 010 et 012, au nom de la lisibilité :

- **Une fiche séparée en lecture seule** (story 010, règle 4 ; story 012, règle 4 et critère « la fiche projet reste en lecture seule ») : la page unique porte les actions de chacun selon ses droits, y compris la déclaration d'avancement.
- **Ni lien « Gérer » ni lien depuis la gestion** (story 010, hors scope) : il n'y a plus qu'une page, donc plus rien à relier.
- **L'entrée « Roadmap » allumée sur la fiche** (story 010, règle 3) : la page du projet allume l'entrée « Projets », d'où que l'on vienne ; le lien de retour garde la mémoire de l'origine.
- **Tout l'historique affiché dans le journal** (story 010, règle 16 et question tranchée « à revoir si la page devient longue ») : les mois anciens sont repliés.
- **Pas de coût projeté cumulé pour le projet** (story 012, hors scope) : l'en-tête annonce le total à terme du projet. Le projeté des lots découpés reste hors scope.

Les règles de calcul de la story 010 ne changent pas : cumul séparé du restant et du dépassement, frise, timeline et signaux.

**Mesure de succès** : deux semaines après la livraison, la direction lit où en est un projet et ce qu'il coûtera à terme sans demander au lead. Un lead déclare l'avancement de ses lots et en voit l'effet sur le restant et la fin calculée, sans changer de page.

## Alignement vision

- **Problème adressé** : directement le symptôme « roadmap opaque » et la question « où en sont réellement les projets ? ». Un total à terme annoncé dès l'en-tête attaque aussi le symptôme « dépassements découverts trop tard » : un projet de 390 j qui en coûtera au moins 462 se lit sans calcul.
- **Audience servie** :
  - la direction, en revue d'un projet ;
  - les leads, dans le pilotage hebdomadaire (lire, déclarer, réagir) ;
  - les responsables de lot, qui déclarent l'avancement du leur ;
  - l'équipe de prod, qui consulte.
- **Principes respectés** :
  - **Principe 1** : la saisie quotidienne n'est pas touchée.
  - **Principe 2** : inchangé. Le journal montre déjà le temps saisi par chacun sur chaque tronçon (tension assumée par la story 010), et aucune comparaison ni aucun classement n'apparaît.
  - **Principe 3** : tout se lit au niveau projet, lot ou sous-lot.
  - **Principe 4** : la date annoncée n'existe toujours pas (ligne `jalons-dates-annoncees`). La page montre la fin calculée, et la date annoncée devra s'afficher à côté quand elle sera livrée.
- **Hypothèse testée** : H2, indirectement. Déclarer l'avancement là où se lisent le restant et le coût projeté devrait rendre la déclaration plus fréquente, et c'est le repli de H2.
- **Impact North Star** : neutre. La story ne produit ni jalon ni date annoncée.

## Utilisateurs concernés

- **Direction** : lit sur une seule page la synthèse, les lots, la frise et le journal d'un projet. Y trouve aussi ses actions de gestion (modifier, supprimer, ajouter un lot, déclarer un avancement).
- **Lead** : mêmes actions que la direction sur tous les projets, sur la page où il lit l'effet de chaque action.
- **Responsable d'un lot** (tout rôle, y compris la prod) : modifie son lot et en déclare l'avancement depuis la page du projet, comme aujourd'hui sur la page de gestion. Les autres lots, il ne fait que les lire.
- **Prod non responsable** : lit la même page, sans aucune action.
- **Personne désactivée** : ne se connecte plus. Elle apparaît barrée comme responsable d'un lot et dans les équipes du journal, comme aujourd'hui.

## User Stories

- En tant que **direction**, je veux une seule page par projet afin de savoir où lire son état sans hésiter entre deux pages au même titre.
- En tant que **direction**, je veux lire dans l'en-tête ce que le projet coûtera à terme face à son estimation afin de voir un dépassement sans additionner restant et saisi.
- En tant que **lead**, je veux déclarer l'avancement d'un lot sur la page où je lis son restant, son projeté et la fin calculée afin de voir l'effet de ma déclaration sans changer de page.
- En tant que **lead**, je veux retrouver sur cette page le responsable de chaque lot et ce qui reste à compléter (lots à estimer, responsables à désigner ou à remplacer) afin de piloter et de gérer au même endroit.
- En tant que **responsable d'un lot**, je veux modifier mon lot et déclarer son avancement depuis la page du projet afin de tenir mon lot à jour là où je le lis.
- En tant que **membre de prod**, je veux consulter la même page que tout le monde, sans action que je ne pourrais pas faire, afin de m'informer sans risque de me tromper.
- En tant que **personne connectée**, je veux que le lien de retour me ramène d'où je viens, la roadmap à la même semaine ou la liste des projets, afin de reprendre mon parcours.
- En tant que **personne connectée**, je veux qu'un ancien lien vers la fiche d'un projet mène toujours à sa page afin que mes signets et les liens partagés restent bons.
- En tant que **lecteur d'un projet ancien**, je veux voir d'abord les derniers mois du journal et déplier les plus anciens à la demande afin de ne pas faire défiler des mois d'historique pour atteindre la fin de la page.
- En tant que **direction ou lead**, je ne veux PAS que le restant d'un lot compense le dépassement d'un autre, ni dans les cumuls ni dans le total à terme, car cela masquerait la dérive.

## Règles métier

### Accès et adresse

1. **Une page par projet** : chaque projet a une seule page, à l'adresse de l'actuelle page de gestion. Elle est ouverte à toute personne connectée.
2. **Ancienne adresse** : l'adresse de l'ancienne fiche mène à la page du projet et conserve la semaine de roadmap d'où l'on venait. Un signet ou un lien partagé reste donc valable.
3. **Liens** : tout lien vers un projet mène à sa page. Cela vaut pour la liste des projets, l'icône de la roadmap, le journal et la section « À venir » de la fiche d'une personne, et son temps saisi par projet.
4. **Retour** : venue de la roadmap, la page propose un lien « Roadmap » qui ramène à la semaine d'origine. Venue d'ailleurs, elle propose « Projets ».
5. **Menu** : sur la page d'un projet, l'entrée « Projets » est mise en évidence, d'où que l'on vienne. Son emplacement dans le menu relève de la story 017.

### En-tête

6. **Identité** : titre et description du projet, signaux du projet tels que la roadmap les affiche, et ce qui reste à compléter : nombre de lots à estimer, de responsables à désigner et de responsables à remplacer.
7. **Total à terme** : la synthèse annonce d'abord le projeté, c'est-à-dire le saisi plus le restant de tous les lots, face à l'estimation, avec l'écart. Elle détaille ensuite le saisi, le restant et le dépassement. Exemple Atlas : 462,25 j projetés pour une estimation de 390 j, soit +72,25 j (+19 %), dont 362,5 j saisis, 99,75 j de restant et 26 j de dépassement.
8. **Total minimum** : tant qu'un lot dépasse son estimation sans avancement déclaré, son restant est inconnu. Le total à terme est alors annoncé comme un minimum (« au moins »), et la synthèse dit pourquoi en une phrase.
9. **Pas de compensation** : la synthèse rappelle en une phrase que le restant d'un lot ne compense jamais le dépassement d'un autre. Le restant et le dépassement s'additionnent séparément, comme le fixe la story 010.
10. **Estimation partielle** : une estimation incomplète, parce que des lots restent à estimer, est marquée « estimation partielle », et le total à terme aussi.
11. **Dates** : le début et la fin calculée. Une fin inconnue est suivie des lots qui en privent le projet, avec leurs raisons, comme aujourd'hui.

### Actions, selon les droits actuels

12. **Lead et direction** : modifient et suppriment le projet, et y ajoutent un lot. Sur chaque lot, ils peuvent le modifier, lui ajouter un sous-lot (lot de premier niveau seulement) ou le supprimer. Ils déclarent l'avancement de tout lot estimé.
13. **Responsable d'un lot** : modifie son lot et en déclare l'avancement, quel que soit son rôle.
14. **Invisible plutôt que grisé** : une action que la personne n'a pas le droit de faire n'apparaît pas. Un membre de prod qui n'est responsable d'aucun lot du projet n'y voit aucune action.
15. **Droits inchangés** : la story ne donne ni ne retire aucun droit. Elle déplace des actions existantes, sans en créer.
16. **Après une action** : une déclaration d'avancement ou une suppression de lot ramène sur la page du projet, au même endroit de la page, avec son message de confirmation.

### Tableau des lots

17. **Un seul tableau** : une ligne par lot et par sous-lot, dans l'ordre du découpage, puis une ligne de cumul du projet.
18. **Contenu d'une ligne** :
    - le lot, avec sa description ;
    - son responsable, avec « responsable à désigner », « responsable à remplacer » ou « vous » ;
    - son estimation, avec l'estimation initiale si elle diffère ;
    - le saisi, avec la règle graduée ;
    - le restant ou le dépassement ;
    - l'avancement : pour qui peut déclarer, la valeur en vigueur avec sa date, ou « Non déclaré », et de quoi la déclarer ; pour les autres, la valeur en vigueur ;
    - le projeté du lot quand un avancement est en vigueur ;
    - les actions permises.
19. **Cumuls** : un lot découpé cumule ses sous-lots et le projet cumule ses lots, le restant et le dépassement séparément (story 010, règle 8).
20. **Lot à estimer** : la ligne affiche « à estimer » et son saisi, sans restant ni avancement.

### Frise, journal, avancements déclarés

21. **Frise** : celle de la fiche actuelle, inchangée (story 010, règles 9 à 14). Les titres des lots n'y sont pas des liens : les actions vivent dans le tableau.
22. **Journal replié** : les trois mois les plus récents qui ont des tronçons sont affichés. Les mois plus anciens sont repliés derrière « Afficher les mois précédents », qui indique leur nombre. Le contenu et l'ordre des entrées ne changent pas.
23. **Avancements déclarés** : la liste des déclarations de chaque lot reste à côté du journal, inchangée.

### Cas limites

24. **Projet non découpé** : la page affiche « pas encore découpé », sans tableau, frise ni journal. Le lead et la direction y trouvent « Ajouter un lot ».
25. **Projet sans date** : la frise est remplacée par son message, et le tableau et le journal restent affichés (story 010, règle 14).
26. **Suppression du projet** : elle mène à la liste des projets, comme aujourd'hui. Un projet qui porte des temps ne peut toujours pas être supprimé.

## Critères d'acceptation

- [ ] La liste des projets, l'icône de la roadmap et les liens de la fiche d'une personne mènent tous à la même page du projet.
- [ ] L'adresse de l'ancienne fiche d'un projet mène à sa page, et, venue de la roadmap, le lien « Roadmap » ramène à la même semaine.
- [ ] Venue de la liste des projets, la page propose un lien de retour « Projets » ; sur la page d'un projet, l'entrée « Projets » du menu est mise en évidence.
- [ ] Sur Atlas, l'en-tête annonce 462,25 j projetés pour une estimation de 390 j, soit +72,25 j (+19 %), comme un minimum puisque des lots dépassent leur estimation sans avancement déclaré, et détaille 362,5 j saisis, 99,75 j de restant et 26 j de dépassement.
- [ ] Sur un projet dont aucun lot ne dépasse son estimation sans avancement déclaré, le total à terme est annoncé sans « au moins ».
- [ ] La synthèse rappelle que le restant d'un lot ne compense jamais le dépassement d'un autre.
- [ ] Un lead et la direction voient sur la page « Modifier », « Supprimer » et « Ajouter un lot », et sur chaque lot ses actions de modification, d'ajout de sous-lot et de suppression.
- [ ] Un responsable de lot de rôle prod voit la modification et la déclaration d'avancement de son lot seulement ; un membre de prod qui n'est responsable d'aucun lot du projet ne voit aucune action.
- [ ] Une personne qui peut déclarer l'avancement d'un lot le fait depuis le tableau ; après l'envoi, elle revient sur la page, au même endroit, et lit le nouveau restant, le projeté du lot et la fin calculée mise à jour.
- [ ] Le tableau présente une seule ligne par lot et par sous-lot, avec le responsable, l'estimation, le saisi, le restant ou le dépassement, l'avancement, le projeté et les actions permises, puis le cumul du projet.
- [ ] Un lot dont un sous-lot dépasse de 5 j et dont un autre a 3 j de restant affiche 3 j de restant et 5 j de dépassement, et le projet les cumule de la même façon.
- [ ] Sur un projet dont le journal couvre plus de trois mois, seuls les trois mois les plus récents sont affichés, et « Afficher les mois précédents » déplie les autres.
- [ ] La frise, ses infobulles et la liste des avancements déclarés sont identiques à celles de l'ancienne fiche.
- [ ] Un projet non découpé affiche « pas encore découpé » et, pour un lead ou la direction, « Ajouter un lot ».
- [ ] Aucune personne n'obtient une action qu'elle ne pouvait pas faire avant ; aucune action de l'ancienne page de gestion n'a disparu.

## Hors scope

- **Choix du mot « lot » et des libellés** (« Saisi », « Dépassement », « Projeté »…) : fixés par la story 015, que cette page applique.
- **Emplacement de « Projets » dans le menu** : relève de la story 017. D'ici là, l'entrée reste dans la section « Administration ».
- **Dates de fin sur la roadmap** : relèvent de la story 018.
- **Date annoncée et jalons** : ligne `jalons-dates-annoncees`, non livrée.
- **Projeté d'un lot découpé** : seul le projet (en-tête) et chaque lot avec un avancement (tableau) annoncent un projeté.
- **Création et modification sur la page même** : créer un projet, ajouter un lot ou le modifier restent des écrans à part, où l'on va depuis la page et d'où l'on y revient.
- **Actions sur la frise** (titre d'un lot qui mène à sa modification) : non, les actions vivent dans le tableau.
- **Repli du journal de la fiche d'une personne** : la fiche d'une personne garde tout son journal. Ce pourrait être une suite si sa longueur gêne aussi.
- **Ordre des couleurs de la règle graduée** (dépassement placé après le restant) : inchangé.
- **Liste des projets** : inchangée, hormis le lien vers la page.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement. Les nouveaux libellés (total à terme, « au moins », « Afficher les mois précédents ») sont en français.
- **Droits d'accès** : inchangés. Chaque action reste réservée à qui pouvait la faire sur la page de gestion. La page reste ouverte à toute personne connectée, comme les deux pages actuelles.
- **Cloisonnement des données** : non. La page montre ce que montraient déjà l'une ou l'autre page, à tout le monde.
- **Apparence / déclinaisons** : une seule page, sur ordinateur et téléphone. Le tableau, plus large, défile horizontalement sur un petit écran.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune donnée touchée. Les signets vers l'ancienne fiche restent valables.
- **Comportement par défaut** : tout le monde arrive sur la page unique dès la mise en service, sans réglage.

## Questions ouvertes

- **Lien de retour après une action** : venue de la roadmap, une personne déclare un avancement. La page qui se recharge garde-t-elle le lien « Roadmap » ? Options : (a) non, la page rechargée propose « Projets » ; (b) oui pour les actions faites sur la page même (avancement, suppression d'un lot), « Projets » après un passage par un écran de modification ; (c) oui partout, écrans de modification compris. Recommandé : (b), qui couvre le rituel hebdomadaire sans alourdir les écrans de modification.
- **Ampleur du journal affiché** : Options : (a) les trois mois les plus récents qui ont des tronçons ; (b) les vingt entrées les plus récentes, quel que soit le mois ; (c) tout afficher, comme aujourd'hui. Recommandé : (a), qui garde l'intertitre de mois comme unité de lecture.
- **« Projeté » à l'échelle du projet** : le lexique de la story 015 définit le projeté d'un lot d'après son avancement déclaré ; cette story annonce aussi le projeté du projet entier, saisi plus restant de tous ses lots, déclarés ou non. Options : (a) élargir la définition du lexique au projet (« ce qu'un lot ou un projet aura coûté une fois terminé : son saisi plus son restant ») ; (b) nommer ce total autrement, « total à terme », et l'ajouter au lexique. Recommandé : (a), un seul mot pour une seule notion.

---

## Annexe — Pistes pour le plan

- La page vit sur `app_project_show` (`/projets/{id}`). `RoadmapController::project()` peut rediriger de façon permanente en conservant le paramètre `roadmap`. Les redirections de `LotController` et `ProjectController` visent déjà `app_project_show`. À confirmer.
- `ProjectController::show()` peut appeler `RoadmapBuilder::buildProject()` comme `RoadmapController::project()` aujourd'hui (frise, timeline, signaux, fin inconnue), en plus de `ProjectRollup::summarize()`. Les deux pages chargent déjà `findOneForDetail()`.
- Le tableau peut naître de la fusion de `project/_lot_row.html.twig` et `roadmap/_consumption_row.html.twig`, qui s'appuient tous deux sur `LotSummary`. `project/_progress.html.twig` porte déjà le formulaire et « Non déclaré ».
- Total à terme : `ProjectSummary` porte `enteredQuarters`, `remainingQuarters`, `overrunQuarters` et `estimateDays`. Le minimum se déduit des feuilles en dépassement sans avancement (`LotSummary::overrunQuarters` et `progress`).
- Repli du journal : `roadmap/_timeline.html.twig` est partagé avec la fiche d'une personne. Un paramètre optionnel garderait la fiche d'une personne inchangée.
- Turbo rafraîchit la page en morph après une action. Le contrôleur `roadmap` de la frise garde son zoom en mémoire, ce qui peut le désynchroniser.
- Tests à migrer : `RoadmapProjectControllerTest` (URL, menu, lecture seule), `ProjectControllerTest` (`project-heading`), les E2E `roadmap.spec.ts`, `progress.spec.ts`, `person.spec.ts` et `projects.spec.ts`.
