# Consulter la fiche d'un projet : son consommé face à l'estimé, sa frise complète et la timeline de tous ses tronçons

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/010-f-fiche-projet/`
> **Amont** : aucun

Chaque projet a une fiche de consultation, ouverte à tous depuis la roadmap. On y trouve, sur une seule page, la synthèse du projet, le consommé face à l'estimé de chaque lot et de chaque feuille, une frise qui couvre toute la vie du projet et une timeline verticale de tous ses tronçons de travail, du plus récent au plus ancien.

## Contexte

Aujourd'hui, un projet se lit en deux endroits incomplets. Sa page d'administration, rangée sous « Administration », sert à gérer son découpage : lots, responsables, estimations. Elle ne dit ni ce qui a été saisi, ni ce qui reste, ni quand le projet finira. La roadmap montre le déroulé dans le temps, mais tous projets confondus, sur une fenêtre fixe de 41 semaines. Un projet long ou ancien y est coupé aux bords. Pour reconstituer l'histoire d'un projet, il faut naviguer de 4 semaines en 4 semaines et survoler les tronçons un par un.

Personne ne peut donc répondre en lisant une seule page à « où en est ce projet, et pourquoi a-t-il glissé ? ». Le consommé face à l'estimé, lot par lot, n'est visible nulle part en un coup d'œil : il faut survoler le récapitulatif de chaque feuille sur la roadmap et additionner. C'est précisément la consolidation que le tableur faisait à la main.

**Mesure de succès** : deux semaines après la livraison, la direction répond à « où en est ce projet et pourquoi a-t-il glissé ? » en lisant sa fiche, sans demander au lead.

## Alignement vision

- **Problème adressé** : directement le symptôme « roadmap opaque » et la question « où en sont réellement les projets ». La fiche donne le consommé face à l'estimé par lot et par feuille, la fin calculée et le récit complet du travail, pauses comprises. Le symptôme « dépassements découverts trop tard » est servi indirectement : un dépassement se lit lot par lot, sans être compensé par le restant d'une autre feuille.
- **Audience servie** : la direction, pour sa revue d'un projet. Les leads, pour le pilotage au fil des semaines. L'équipe de prod, pour s'informer sur un projet où elle travaille ou va travailler.
- **Principes respectés** :
  - **Principe 1 respecté** : la saisie quotidienne n'est pas touchée, et la fiche ne s'ouvre pas depuis Ma semaine.
  - **Principe 3 respecté** : tout se lit au niveau projet, lot ou feuille.
  - **Principe 4** : dans son esprit, le restant et le dépassement ne se compensent jamais dans un cumul, pour que l'écart reste visible. La date annoncée n'existe pas encore (ligne `jalons-dates-annoncees` non livrée) : la fiche ne montre que la fin calculée. Quand la date annoncée sera livrée, elle devra s'afficher à côté.
  - **Principe 2 sous tension assumée, plus fortement que dans la story 009** : la timeline montre en clair, pour tout le monde et sur toute l'histoire du projet, le temps saisi par chaque personne sur chaque tronçon. L'infobulle de la story 009 donnait déjà cette information, mais il fallait survoler les tronçons un par un. Une timeline sans les noms, ou sans le temps de chacun, a été écartée au cadrage.
- **Hypothèse testée** : H2 et H3 indirectement. Le consommé face à l'estimé de chaque feuille est le socle de l'alerte de rythme (H2). Une fin qui recule s'explique tronçon par tronçon (H3). Le signal d'arrêt « tableur toujours maintenu » est aussi visé : la fiche fait la consolidation par projet que le tableur faisait à la main.
- **Impact North Star** : neutre. La story ne produit ni jalon ni date annoncée.

## Utilisateurs concernés

- **Direction** : ouvre la fiche d'un projet depuis la roadmap pour comprendre où il en est et pourquoi il a glissé, avant d'arbitrer.
- **Lead** : suit son projet au fil des semaines : ce qui avance, ce qui est en pause, ce qui dépasse, ce qui reste. La gestion du projet reste dans l'administration, inchangée.
- **Prod** : voit la même fiche que les leads et la direction, sans aucune action. Sa saisie est inchangée.
- **Personne désactivée** : apparaît barrée dans l'équipe des tronçons de la timeline, comme dans les infobulles de la roadmap.

## User Stories

- En tant que **direction**, je veux ouvrir depuis la roadmap la fiche d'un projet, afin de lire sur une seule page où il en est et comment il s'est déroulé.
- En tant que **lead**, je veux voir le consommé face à l'estimé du projet, de chaque lot et de chaque feuille, afin de savoir où en est le projet sans additionner les récapitulatifs de la roadmap.
- En tant que **direction**, je veux voir toute la vie du projet sur une frise, de son premier à son dernier jour connu, afin de saisir son déroulé d'un coup d'œil sans naviguer de 4 semaines en 4 semaines.
- En tant que **lead**, je veux lire tous les tronçons du projet dans une timeline verticale, du plus récent au plus ancien, avec la feuille, la période, le temps saisi et l'équipe de chacun, afin de comprendre quel bloc de travail a eu lieu, quand et par qui.
- En tant que **membre de prod**, je veux consulter la même fiche, afin de m'informer sur un projet où je travaille ou vais travailler.
- En tant que **lead**, je ne veux PAS que le dépassement d'une feuille soit compensé par le restant d'une autre dans le cumul d'un lot ou du projet, car cela masquerait la dérive.
- En tant que **personne connectée**, je ne veux PAS perdre l'habitude de déplier un projet en cliquant sur sa ligne de la roadmap.

## Règles métier

**Accès**

1. **Ouverture** : sur la roadmap, une icône « Ouvrir la fiche » à côté du titre de chaque projet mène à sa fiche. Cliquer ailleurs sur la ligne du projet le déplie ou le replie, comme aujourd'hui.
2. **Retour** : un lien « Roadmap » ramène à la semaine de la roadmap d'où l'on venait.
3. **Menu** : la fiche n'a pas d'entrée propre dans le menu. L'entrée « Roadmap » y reste mise en évidence.
4. **Lecture seule** : la fiche est la même pour toute personne connectée, quel que soit son rôle. Elle ne porte aucune action de gestion, et les titres des feuilles n'y sont pas des liens.

**Synthèse**

5. **En-tête** : titre et description du projet, puis la synthèse : estimé, saisi, restant, dépassement, début, fin calculée (ou « inconnue ») et les signaux du projet tels que la roadmap les affiche. Le début et la fin du projet suivent les règles de la roadmap. Un estimé incomplet, parce que des feuilles restent à estimer, est marqué « partiel ».

**Consommé face à l'estimé**

6. **Tableau** : une ligne pour le projet, chaque lot et chaque sous-lot, dans l'ordre du découpage. Chaque ligne a trois colonnes : estimé, saisi, restant ou dépassement.
7. **Feuille** : son estimé en vigueur, le temps saisi sur elle, puis son restant, ou son dépassement si le saisi dépasse l'estimé. Une feuille à estimer affiche « à estimer » et son temps saisi.
8. **Cumuls** : un lot découpé cumule ses sous-lots, et le projet cumule ses lots. Le restant et le dépassement s'additionnent **séparément** et ne se compensent jamais. Un lot dont un sous-lot dépasse de 5 j et un autre a 3 j de restant affiche 3 j de restant et 5 j de dépassement.

**Frise**

9. **Contenu** : les mêmes lignes que la roadmap pour ce projet (le projet, ses lots, ses feuilles, toutes dépliées). On y retrouve les mêmes tronçons, la même partie au-delà de l'estimation, la même partie restante et les mêmes barres de cumul. Les infobulles, les récapitulatifs, les signaux, la légende et le repère « aujourd'hui » sont aussi les mêmes. Une seule infobulle s'affiche à la fois.
10. **Bornes** : la frise commence au premier jour connu du projet (le plus tôt entre le début de ses feuilles et le premier jour saisi). Elle finit à son dernier jour connu (le plus tard entre les fins calculées et le dernier jour saisi). Elle couvre les semaines entières qui contiennent ces jours, du lundi au dimanche. À ×1, la frise n'est jamais plus serrée que la roadmap à ×1 : un projet plus court que la fenêtre de la roadmap tient dans la largeur, un projet plus long élargit la frise, qui défile horizontalement, pour que chaque mois garde la place de son libellé. _(Règle amendée au plan : la frise devait d'abord toujours tenir dans la largeur, sans défilement.)_
11. **Durée minimale** : la frise couvre au moins 4 semaines à partir de la semaine du premier jour connu, pour qu'un projet plus court ne s'étire pas sur toute la largeur.
12. **Aujourd'hui** : le repère « aujourd'hui » n'apparaît que s'il tombe entre les bornes.
13. **Zoom** : les paliers ×1, ×2, ×4 et ×8 de la roadmap, avec les mêmes commandes, gardent la date au centre de l'écran. La fiche s'ouvre toujours à ×1, centrée sur aujourd'hui, ou sur le bord le plus proche d'aujourd'hui si aujourd'hui tombe hors des bornes, et le palier n'est pas mémorisé.
14. **Sans date** : un projet découpé dont aucune feuille n'a de début ni de temps saisi n'a pas de frise. Un message l'indique, et la synthèse et le tableau restent affichés.

**Timeline**

15. **Entrées** : une entrée par tronçon de la partie saisie ou de la partie au-delà de l'estimation, toutes feuilles du projet mélangées. Les tronçons sont ceux de la roadmap. Une feuille non planifiée qui a des temps saisis a aussi ses tronçons dans la timeline, coupés sur les jours ouvrés de son équipe, ou des personnes qui y ont saisi si elle n'a pas d'équipe. _(Précision apportée au plan.)_ La partie restante n'apparaît pas dans la timeline.
16. **Ordre** : du plus récent au plus ancien, selon le dernier jour du tronçon. Un intertitre marque chaque mois, et un tronçon se range sous le mois de son dernier jour. Tout l'historique est affiché.
17. **Contenu d'une entrée** : le même que l'infobulle du tronçon sur la roadmap, plus le nom de la feuille. On y lit la feuille (lot, puis sous-lot s'il y en a un), la période du tronçon (premier et dernier jour), le temps saisi et le nombre de jours avec saisie. Suit l'équipe : chaque membre avec sa part et le temps qu'il a saisi sur le tronçon, puis les personnes hors équipe qui y ont saisi, marquées « hors équipe ».
18. **Au-delà de l'estimation** : un tronçon de la partie au-delà de l'estimation est marqué comme tel. Le jour où l'estimation est franchie compte en entier dans son tronçon, comme sur la roadmap.
19. **Sans saisie** : un projet sur lequel personne n'a rien saisi affiche une timeline vide avec un message.

**Cas limites**

20. **Projet non découpé** : la fiche affiche le titre, la description et « pas encore découpé », sans tableau, frise ni timeline.
21. **Feuille non planifiée** : elle figure dans le tableau et apparaît dans la frise sans barre, avec son signal (« à estimer », « sans début » ou « sans équipe »), comme sur la roadmap. Ses temps saisis figurent dans la timeline (règle 15).

## Critères d'acceptation

Exemple de référence : un projet « Refonte » découpé en un lot « API » sans sous-lot, de 10 j, tenu par une personne au calendrier France à 100 %, et un lot « Front » découpé en un sous-lot « Login » de 8 j, tenu par une seconde personne à 100 %. Le lot « API » est saisi d'une journée pleine du lundi 07/09 au vendredi 11/09, puis du lundi 21/09 au vendredi 25/09. Le sous-lot « Login » est saisi d'une journée pleine du lundi 14/09 au mercredi 16/09.

- [ ] Sur la roadmap, chaque projet porte une icône « Ouvrir la fiche » qui mène à sa fiche ; cliquer ailleurs sur la ligne du projet le déplie ou le replie, comme avant.
- [ ] Le lien « Roadmap » de la fiche ramène à la semaine de la roadmap d'où l'on venait, et l'entrée « Roadmap » du menu est mise en évidence sur la fiche.
- [ ] Un membre de prod, un lead et la direction voient la même fiche ; aucune action de gestion n'y figure et les titres des feuilles ne sont pas des liens.
- [ ] Dans l'exemple, le tableau affiche pour « API » 10 j estimés, 10 j saisis et 0 j de restant ; pour « Login » 8 j estimés, 3 j saisis et 5 j de restant ; pour « Front » le même cumul que « Login » ; pour le projet 18 j estimés, 13 j saisis et 5 j de restant.
- [ ] Un lot dont un sous-lot dépasse son estimation de 5 j et un autre a 3 j de restant affiche 3 j de restant et 5 j de dépassement, et le projet les cumule de la même façon.
- [ ] Une feuille à estimer affiche « à estimer » dans le tableau, avec son temps saisi, et l'estimé de son lot et du projet est marqué « partiel ».
- [ ] La synthèse affiche l'estimé, le saisi, le restant, le dépassement s'il y en a un, le début, la fin calculée (ou « inconnue ») et les signaux du projet.
- [ ] À ×1, la frise couvre tout le projet, du premier au dernier jour connu ; un projet de quelques mois tient dans la largeur, un projet plus long que la fenêtre de la roadmap défile horizontalement avec un libellé lisible pour chaque mois ; ses barres, tronçons, infobulles et récapitulatifs sont ceux de la roadmap.
- [ ] Un projet dont toutes les dates tiennent dans une semaine s'affiche sur une frise de 4 semaines.
- [ ] La fiche s'ouvre à ×1 même après un zoom sur la roadmap ou sur une autre fiche ; « + » fait passer la frise à ×2, ×4 puis ×8, en gardant la date au centre de l'écran.
- [ ] Le repère « aujourd'hui » n'apparaît que si aujourd'hui tombe entre les bornes de la frise.
- [ ] Dans l'exemple, la timeline présente, sous l'intertitre « Septembre 2026 » et dans cet ordre : « API » du 21/09 au 25/09 avec 5 j saisis et 5 jours avec saisie ; « Front · Login » du 14/09 au 16/09 avec 3 j saisis ; « API » du 07/09 au 11/09 avec 5 j saisis.
- [ ] Chaque entrée de la timeline donne l'équipe du tronçon : chaque membre avec sa part et son temps saisi sur le tronçon, puis les personnes hors équipe, marquées « hors équipe ». Une personne désactivée y apparaît barrée.
- [ ] Un tronçon à cheval sur août et septembre se range sous l'intertitre de septembre.
- [ ] Sur une feuille en dépassement, les tronçons au-delà de l'estimation figurent dans la timeline, marqués comme tels.
- [ ] Le temps saisi sur une feuille non planifiée apparaît en tronçons dans la timeline, alors que la feuille n'a pas de barre dans la frise.
- [ ] Un projet sans aucune saisie affiche une timeline vide avec un message ; un projet découpé sans aucun début ni saisie n'a pas de frise, et un message l'indique.
- [ ] Un projet non découpé affiche « pas encore découpé », sans tableau, frise ni timeline.

## Hors scope

- **Lien depuis l'administration du projet, et lien « Gérer » en retour** : non retenu. La fiche s'ouvre depuis la roadmap seule.
- **Entrée de menu et liste de consultation des projets** : non retenues.
- **Lien depuis Ma semaine** : non, pour ne rien ajouter à la saisie (principe 1).
- **Actions de gestion sur la fiche**, y compris un lien de modification sur le titre d'une feuille : non, la fiche est en lecture seule.
- **Date annoncée et jalons** : la ligne `jalons-dates-annoncees` n'est pas livrée. La fiche ne montre que la fin calculée.
- **Partie restante et événements dans la timeline** (début posé, estimation franchie, fin calculée) : écartés au profit d'un journal des seuls tronçons saisis.
- **Timeline groupée par feuille** : écartée au profit du journal chronologique.
- **Timeline sans les noms ou sans le temps de chacun** : écartée au cadrage (tension avec le principe 2 assumée).
- **Pagination ou repli de la timeline** : non, tout l'historique est affiché.
- **Interaction entre la timeline et la frise** (surbrillance d'un tronçon, filtre par feuille) : non.
- **Estimation initiale, pourcentage consommé et dates dans le tableau** : non retenus. Seuls l'estimé, le saisi et le restant ou le dépassement y figurent.
- **Consommé face à l'estimé de tous les projets sur une même page** : non. C5.1 est couvert projet par projet.
- **Mémorisation du zoom de la fiche** : non.
- **Export ou impression de la fiche** : non.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement ; les libellés ajoutés (fiche, tableau, timeline, intertitres de mois) sont en français.
- **Droits d'accès** : une nouvelle page, ouverte à toute personne connectée, en lecture seule. Aucun droit nouveau. La page d'administration du projet et ses droits sont inchangés.
- **Cloisonnement des données** : pas de multi-organisation. Côté principe 2, le temps saisi par chaque personne sur chaque tronçon devient lisible d'un coup d'œil, par tous, sur toute l'histoire du projet : tension assumée (voir Alignement vision).
- **Apparence / déclinaisons** : non. Une seule apparence.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune reprise. Les découpages, planifications et temps déjà saisis suffisent.
- **Comportement par défaut** : la roadmap garde son comportement ; seule l'icône « Ouvrir la fiche » s'ajoute à côté du titre de chaque projet.

## Questions ouvertes

- **Téléphone** : la frise ajustée tient-elle sur un écran de téléphone ? Options : (a) même largeur minimale que la roadmap, avec un défilement horizontal ; (b) frise masquée sur téléphone, seules la synthèse, le tableau et la timeline s'affichent. → tranché : (a), la frise garde au moins la largeur de celle de la roadmap et défile.
- **Volume de la timeline** : un projet de plusieurs centaines de tronçons s'affiche en entier. Si la page devient lourde ou longue à lire, faut-il revenir sur ce choix ? Options : (a) tout afficher, retenu au cadrage ; (b) replier par défaut les mois les plus anciens. → tranché : (a), à revoir si le poids de la page devient un irritant.

---

## Annexe — Pistes pour le plan

- `RoadmapBuilder::build()` travaille sur une `RoadmapWindow` de 41 semaines (`WEEK_COUNT`). Piste : une fenêtre bornée aux dates du projet (au moins 4 semaines) et un build filtré sur un seul projet, pour réutiliser lignes, tronçons, infobulles et récapitulatifs. `Roadmap::MIN_MONTH_WIDTH` est calculé pour la fenêtre de 41 semaines.
- La timeline peut aplatir les `RoadmapRun` / `RoadmapSegment` de toutes les feuilles du projet (période, temps, jours, équipe par tronçon depuis la story 009), puis les trier par dernier jour. Le partiel `roadmap/_team.html.twig` est réutilisable.
- Le tableau peut s'appuyer sur `ProjectRollup` / `ProjectSummary` (totaux, « partiel », signaux) et sur `TimeEntryRepository::sumQuartersByLot()`. Le cumul séparé du restant et du dépassement est à ajouter.
- Sur la roadmap, le titre du projet vit dans le `<summary>` d'un `<details>` (`templates/roadmap/index.html.twig`). L'icône doit ouvrir la fiche sans basculer le dépliage.
- La route `/projets/{id}` est la page d'administration, rattachée à l'entrée « Projets » du menu (`nav_section`). La fiche a besoin d'une route qui allume « Roadmap » et qui transporte la semaine d'origine, comme le paramètre `roadmap` de `app_lot_edit`.
- Zoom : `assets/controllers/roadmap_controller.js` mémorise le palier en `sessionStorage`. La fiche en a besoin sans mémorisation. Les infobulles passent par le contrôleur `roadmap-tooltip`.
