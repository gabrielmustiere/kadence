# Poser ses congés et ses RTT auprès de son manager, et les déduire de la saisie et de la capacité

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/014-f-conges/`
> **Amont** : aucun

Chacun demande ses congés dans Kadence, à la journée ou à la demi-journée. Il les impute sur un compteur (congés payés de mai à mai, RTT de janvier à janvier) dont la direction déclare les droits, ou sur un type sans compteur. Son manager valide ou refuse. Dès la demande, les jours concernés sont retirés de sa grille de saisie. Une fois le congé validé, ils sont aussi déduits de sa capacité : la fin calculée des feuilles, la charge, la roadmap et sa fiche en tiennent compte. Kadence devient l'outil officiel des congés de l'équipe.

## Contexte

Aujourd'hui, Kadence ne connaît pas les congés. Ils se demandent et se valident hors de l'outil, par un message ou à l'oral, et les soldes se suivent à la main. Les stories précédentes en ont toutes noté l'effet. La planification (story 006) calcule la fin des feuilles comme si personne ne partait jamais : « les barres sont optimistes pendant les congés ». La roadmap (story 008) coupe la barre d'une feuille chaque jour de congé de son équipe, comme un jour d'interruption. La fiche d'une personne (story 011) annonce une date « libre à partir du … » qui ignore ses vacances. Et la grille de saisie présente un jour de congé comme un jour à saisir : la semaine reste incomplète tant qu'on n'a pas saisi un temps qu'on n'a pas travaillé.

Le circuit actuel a ses propres défauts. Une demande se perd dans un fil de messages, le manager décide sans voir le solde ni les autres absents de son équipe, et personne ne sait à date ce qu'il reste à chacun. Les leads, eux, planifient sans savoir qui sera absent.

Si on ne fait rien, toute date projetée par Kadence reste fausse dès qu'un membre de l'équipe pose une semaine de vacances, alors que c'est exactement ce que la vision promet de corriger.

**Mesure de succès** : une semaine de congé validée repousse d'autant la fin calculée des feuilles de la personne, sans aucune action d'un lead. Une semaine entièrement posée est complète dans sa grille sans aucun temps saisi.

## Alignement vision

- **Problème adressé** : direct. La vision nomme cette lacune : « la capacité réelle (congés, temps partiels) n'est pas croisée avec la charge ». La feature corrige le symptôme « planning intenable » et fiabilise les dates projetées, dans l'horizon à 6 mois (« charge vs capacité, congés inclus »).
- **Audience servie** : toute l'équipe, qui demande ses congés. Le manager, qui gagne un nouvel usage : décider. La direction, qui déclare les droits et peut décider à la place d'un manager. Les leads, dont la planification devient réaliste.
- **Principes respectés** :
  - Principe 1 respecté : la saisie quotidienne n'est pas alourdie, elle s'allège même, puisqu'un jour de congé n'est plus à saisir. La demande est un geste à part, rare.
  - Principe 2 respecté : une absence est visible de tous, mais son type (congé payé, RTT, sans solde, événement familial) ne l'est que de la personne, de son manager et de la direction. Aucune vue ne compare ni ne classe les absences des personnes.
  - **Règle de la story 007 contredite, assumé au cadrage** : « être manager n'ouvre aucun droit » ne tient plus. Le manager obtient un droit et un seul : décider des demandes de congé de ses collaborateurs. Cette règle transverse du backlog est à réaligner. Le principe 2 de la vision n'est pas touché, puisqu'il interdit de comparer et de classer les personnes, pas de valider leurs congés.
  - **Positionnement sous tension, assumé** : Kadence devient l'outil officiel des congés. Il tient des droits et des soldes, ce qui l'approche d'un outil de gestion du personnel. Le lien avec la paie reste hors scope.
- **Hypothèse testée** : H3. Des dates projetées crédibles supposent une capacité qui tient compte des absences.
- **Impact North Star** : indirect. Des fins calculées qui intègrent les congés rendent les dates projetées plus fiables, et donc les dates annoncées mieux tenues.
- **Risque externe renforcé** : les congés de chacun sont des données personnelles, et Kadence en devient la référence. L'information des salariés prévue avant le déploiement doit couvrir ce module.

## Utilisateurs concernés

- **Toute personne active** (direction, lead, prod) — elle demande ses congés, suit ses demandes et ses soldes, et annule une demande. Les jours qu'elle a posés sont verrouillés dans sa grille. Elle voit les absences de tous, sans leur type.
- **Manager** (toute personne active désignée comme manager d'au moins une personne, quel que soit son rôle) — il reçoit les demandes de ses collaborateurs actifs, les valide ou les refuse, et voit le type de leurs absences.
- **Direction** — elle déclare les droits de chacun, par type et par période, et consulte les compteurs de toute l'équipe. Elle décide des demandes des personnes sans manager actif, et peut décider de n'importe quelle demande à la place du manager. Elle annule un congé validé, même commencé.
- **Lead** — aucun droit nouveau sur les congés des autres. Sa planification tient compte des congés validés, et il voit les absences des membres de l'équipe d'une feuille sur la roadmap et la fiche projet.
- **Personne désactivée** — elle ne demande plus rien. Ses congés passés restent visibles sur sa fiche.

## User Stories

- En tant que **membre de l'équipe**, je veux demander un congé payé, une RTT ou un congé sans compteur, à la journée ou à la demi-journée (matin ou après-midi), afin de ne plus passer par un message qui se perd.
- En tant que **membre de l'équipe**, je veux voir, avant d'envoyer ma demande, combien de jours elle décompte et le solde qu'il me restera afin de savoir si je dépasse mes droits.
- En tant que **membre de l'équipe**, je veux que les jours que j'ai posés soient verrouillés dans ma grille et que ma semaine soit complète sans eux afin de ne pas voir de faux oubli.
- En tant que **membre de l'équipe**, je veux être prévenu par e-mail quand ma demande est validée ou refusée, avec le motif du refus, afin de m'organiser.
- En tant que **membre de l'équipe**, je veux annuler une demande en attente, ou un congé validé qui n'a pas commencé, afin de récupérer mes jours.
- En tant que **manager**, je veux recevoir par e-mail chaque nouvelle demande de mes collaborateurs et la décider en voyant le solde qu'elle laisse et les autres absents de mon équipe sur la période afin de valider en connaissance de cause.
- En tant que **direction**, je veux déclarer les droits de congés payés et de RTT de chaque personne pour une période afin que chacun connaisse son solde.
- En tant que **direction**, je veux pouvoir décider d'une demande à la place du manager afin qu'un manager absent ne bloque personne.
- En tant que **lead**, je veux que la fin calculée des feuilles tienne compte des congés validés de leur équipe afin de planifier sur une capacité réelle.
- En tant que **personne connectée**, je veux voir qui est absent tel jour, sur une page dédiée, sur la fiche d'une personne et sur la roadmap, afin de savoir sur qui compter.
- En tant que **membre de l'équipe**, je ne veux PAS que mes collègues voient le type de mes absences afin que leur motif reste entre mon manager, la direction et moi.
- En tant que **direction**, je ne veux PAS qu'un lead décide des congés de personnes dont il n'est pas le manager afin que la décision reste au manager ou à la direction.

## Règles métier

### Types et compteurs

1. **Types d'absence** : quatre types, en liste fixe.
   - **Avec compteur** : congé payé (CP) et RTT.
   - **Sans compteur** : congé sans solde et événement familial.
2. **Périodes** : les congés payés se comptent du 1er mai au 30 avril suivant (période « 2026-2027 », par exemple), les RTT du 1er janvier au 31 décembre. Ces périodes s'appliquent à tout le monde, y compris aux personnes rattachées au calendrier belge.
3. **Droits** : la direction déclare, pour chaque personne, chaque type avec compteur et chaque période, des droits en jours, au demi-jour près. Elle peut les modifier à tout moment, sans historique. Une personne dont les droits n'ont pas été déclarés pour une période dispose de 0 j.
4. **Pas de report** : le reliquat d'une période ne passe pas tout seul à la suivante. La direction l'intègre, si elle le souhaite, dans les droits qu'elle déclare pour la période suivante. Les droits ne sont jamais calculés par Kadence (pas d'acquisition mensuelle).
5. **Décompte** : une demande décompte 1 j par jour ouvré de la personne couvert en entier, et 0,5 j par demi-journée. Un jour ouvré va du lundi au vendredi, hors jours fériés du calendrier de la personne. Un week-end ou un jour férié compris dans une demande ne décompte rien.
6. **Décompte vivant** : le décompte suit le calendrier en vigueur. Un jour de congé qui devient férié (jour ajouté par la direction, changement de calendrier de la personne) ne décompte plus rien, et le solde en tient compte.
7. **Imputation par période** : chaque jour d'une demande s'impute sur la période où il tombe. Une demande à cheval sur le 30 avril et le 1er mai (CP), ou sur le 31 décembre et le 1er janvier (RTT), se répartit sur deux périodes.
8. **Solde** : pour une personne, un type avec compteur et une période, le solde vaut droits − jours validés − jours en attente. Il peut devenir négatif (règle 13).
9. **Types sans compteur** : ils n'ont ni droits ni solde. Ils verrouillent la saisie et pèsent sur la capacité comme les autres types.

### Demande

10. **Qui demande** : toute personne active, pour elle-même uniquement.
11. **Contenu** : un type, une date de début avec sa demi-journée (matin ou après-midi), une date de fin avec sa demi-journée, et un commentaire facultatif. Commencer un après-midi ou finir un matin pose une demi-journée à ce bout. Une demi-journée isolée a la même date et la même demi-journée au début et à la fin.
12. **Avant l'envoi** : la personne voit le nombre de jours décomptés et, pour un type avec compteur, son solde après la demande sur chaque période concernée.
13. **Dépassement** : une demande qui porte un solde en dessous de 0 est acceptée. Le dépassement est signalé à la personne avant l'envoi, puis au valideur.
14. **Demandes refusées à la création**, avec un message :
    - une fin antérieure au début ;
    - une demande qui ne couvre aucun jour ouvré de la personne ;
    - une demande qui chevauche, sur une même demi-journée, une autre demande de la personne en attente ou validée ;
    - une demande qui couvre un jour où la personne a déjà saisi plus de temps que le congé ne lui en laisse : tout temps pour une journée entière, plus de 2 quarts pour une demi-journée. Elle retire d'abord sa saisie.
15. **Deux demi-journées** : un même jour peut porter deux demandes, une le matin et une l'après-midi, de types différents ou non.
16. **Jours passés** : une demande peut porter sur des jours passés (congé oublié), aux mêmes conditions.

### Décision

17. **Valideur** : le manager de la personne, s'il est actif. Sans manager, ou si son manager est désactivé, ce sont les membres actifs de la direction. Le valideur se lit au moment de la décision : si le manager change pendant qu'une demande est en attente, c'est le nouveau qui la reçoit.
18. **Suppléance** : un membre de la direction peut décider de n'importe quelle demande, à tout moment, à la place du manager.
19. **Jamais pour soi** : personne ne décide de sa propre demande. Celle d'un manager va à son propre manager, ou à la direction. Celle d'un membre de la direction sans manager actif va aux autres membres actifs de la direction.
20. **Validation d'office** : la demande d'un membre de la direction sans manager actif, alors qu'aucun autre membre de la direction n'est actif, est validée dès qu'elle est posée.
21. **Ce que voit le valideur** : les dates et les demi-journées, le type, le commentaire, le nombre de jours décomptés, le solde après la demande sur chaque période concernée, avec le dépassement signalé, et les autres absents parmi ses collaborateurs sur la période (validés ou en attente). Pour la direction qui décide à la place d'un manager, ce sont les collaborateurs de ce manager. Une personne sans manager n'a pas d'équipe à regarder : rien n'est affiché à la place des autres absents.
22. **Décision** : le valideur valide ou refuse la demande en entier. Un refus exige un motif, que la personne voit. Une décision est définitive : une demande validée ne se refuse plus, une demande refusée ne se valide plus.
23. **Pas d'expiration** : une demande reste en attente sans limite de durée, même quand ses dates sont passées. Elle reste décidable.
24. **Liste à traiter** : chaque valideur voit la liste des demandes en attente qu'il peut décider. La direction voit toutes les demandes en attente, sauf les siennes.

### Annulation

25. **Par la personne** : elle annule seule une demande en attente, à tout moment, et un congé validé tant que son premier jour n'est pas arrivé.
26. **Par la direction** : elle annule un congé validé à tout moment, y compris commencé ou passé.
27. **En entier** : une demande s'annule en entier. Pour modifier un congé, on l'annule puis on en demande un autre.
28. **Effet** : l'annulation, comme le refus, rend les jours au solde et rouvre les jours dans la grille de saisie.

### Saisie

29. **Verrou** : dans la grille de la personne, un jour couvert en entier par des demandes en attente ou validées (une journée, ou un matin et un après-midi) n'est pas saisissable : son plafond vaut 0. Un jour couvert par une seule demi-journée est plafonné à 2 quarts.
30. **En attente aussi** : le verrou vaut dès la demande, avant la décision, pour qu'un congé ne soit jamais validé sur du temps saisi. Un refus ou une annulation rouvre le jour.
31. **Contrôle à l'enregistrement** : un enregistrement qui dépasse le plafond d'un jour de congé est refusé avec un message, et la case reprend sa valeur précédente. Cela vaut aussi pour une grille ouverte avant la demande, dans un second onglet par exemple.
32. **Affichage** : la colonne d'un jour de congé reste affichée, grisée, avec la mention « Congé » suivie du type, et « en attente » tant que la demande n'est pas décidée. Pour une demi-journée, la mention précise « matin » ou « après-midi ». Un jour de congé entier n'est jamais signalé comme un oubli et ne passe pas au vert. Un jour à demi-journée de congé est complet dès 2 quarts saisis.
33. **Maximum de la semaine** : le maximum établi par la story 004 (le plus petit du maximum hebdomadaire et du nombre de jours non fériés) est réduit au prorata des jours de congé de la semaine, en attente ou validés. Il est multiplié par les jours ouvrés restants, divisé par les jours ouvrés de la semaine, puis arrondi au quart de jour inférieur. Exemples : 5 j avec un jour posé donnent 4 j ; 4,5 j avec un matin posé donnent 4 j ; 4 j avec un jour posé donnent 3 j ; une semaine entièrement posée donne 0 j.

### Calculs

34. **Congés validés seulement** : seuls les congés validés pèsent sur les calculs de planification. Une demande en attente verrouille la saisie (règle 30) mais ne retire rien à la capacité.
35. **Capacité** : un jour de congé validé, la personne n'apporte aucune capacité à ses feuilles. Une demi-journée en retire la moitié. La fin calculée des feuilles dont elle fait partie de l'équipe recule d'autant, sans action d'un lead.
36. **Surcharge passive** : un congé validé s'ajoute aux causes de surcharge passive de la story 006. Si le recul d'une fin calculée porte une personne au-delà de 100 % un jour donné, la feuille est signalée « à replanifier ». Un congé n'est jamais refusé pour cette raison.
37. **Jours ouvrés d'une personne** : partout où Kadence compte les jours ouvrés d'une personne, un jour de congé validé entier n'en est plus un. Une demi-journée de congé laisse le jour ouvré. En conséquence :
    - sur la roadmap (story 008), un jour où tous les membres de l'équipe d'une feuille sont en congé ou fériés ne coupe pas sa barre ;
    - sur la fiche d'une personne (story 011), ses tronçons ne sont pas coupés par ses jours de congé, et sa charge actuelle se lit au prochain jour ouvré hors congé ;
    - « libre à partir du … » est le premier jour ouvré hors congé après sa dernière affectation.

### Visibilité

38. **Absences visibles de tous** : toute personne connectée voit les congés validés de chacun, avec leurs dates et leurs demi-journées, mais sans leur type, leur commentaire ni le motif d'un refus.
39. **Détail réservé** : le type, le commentaire et les demandes en attente ou refusées ne sont visibles que de la personne, de son manager actif et de la direction.
40. **Page « Absences »** : ouverte à tous. Pour le mois choisi (le mois en cours par défaut, avec navigation de mois en mois), elle présente une ligne par personne active, avec ses jours de congé (journée, matin ou après-midi) et ses jours fériés. Le détail réservé (règle 39) n'y apparaît que pour qui y a droit.
41. **Fiche d'une personne** : ses congés à venir et passés y figurent, et sa ligne de charge marque ses jours de congé. La ligne de charge reste réservée comme le prévoit la story 011.
42. **Roadmap et fiche projet** : les jours de congé validés des membres de l'équipe d'une feuille sont signalés sur sa barre et nommés dans son infobulle (« Alice absente »), sans leur type.
43. **Mes congés** : chacun voit ses demandes (en attente, validées, refusées avec leur motif, annulées) et, pour la période en cours et toute période non terminée sur laquelle il a une demande, ses droits, ses jours validés, ses jours en attente et son solde, par type avec compteur.
44. **Compteurs de l'équipe** : la direction consulte, pour une période et un type avec compteur, les droits, les jours validés, les jours en attente et le solde de chaque personne active, et y déclare les droits.

### Notifications

45. **E-mails** :
    - une nouvelle demande est envoyée au valideur : au manager actif, sinon à chaque membre actif de la direction ;
    - une décision est envoyée à la personne, avec le motif en cas de refus ; si la direction a décidé à la place du manager, celui-ci en est informé ;
    - l'annulation d'un congé validé par la personne est envoyée à son valideur ; l'annulation d'un congé validé par la direction est envoyée à la personne ;
    - l'annulation d'une demande en attente n'envoie rien : la demande disparaît de la liste du valideur ;
    - une demande validée d'office (règle 20) n'envoie rien.

## Critères d'acceptation

- [ ] Alice, qui suit le calendrier France, demande des CP du lundi 9 au vendredi 13 novembre 2026. Le décompte annoncé est de 4 j, puisque le 11 novembre est férié.
- [ ] Une demande de CP du jeudi 29 avril au mardi 4 mai 2027 décompte 2 j sur la période 2026-2027 et 2 j sur la période 2027-2028. Une demande de RTT du jeudi 31 décembre 2026 au lundi 4 janvier 2027 décompte 1 j sur 2026 et 1 j sur 2027.
- [ ] Une demande du lundi 16 novembre après-midi au mercredi 18 novembre matin décompte 2 j.
- [ ] Avec 25 j de CP déclarés pour 2026-2027 et 22 j déjà validés ou en attente, une demande de 5 j est acceptée. Alice voit un solde de −2 j avant l'envoi, et son manager voit le dépassement signalé.
- [ ] Une personne sans droits déclarés pour une période voit un solde de 0 j, et toute demande de CP ou de RTT sur cette période est signalée en dépassement.
- [ ] Un congé sans solde ou pour un événement familial ne modifie aucun solde, mais verrouille la saisie et, une fois validé, pèse sur la capacité.
- [ ] Une demande dont la fin précède le début, une demande qui ne couvre que des week-ends ou des jours fériés, et une demande qui chevauche le même matin d'une autre demande en attente ou validée sont refusées avec un message.
- [ ] Alice a saisi 1 j le 18 novembre. Sa demande pour la journée du 18 novembre est refusée avec un message. Avec 2 quarts saisis ce jour-là, elle peut demander l'après-midi du 18, mais pas la journée.
- [ ] Alice peut avoir un CP le matin du 18 novembre et une RTT l'après-midi : le jour est alors entièrement verrouillé.
- [ ] Dès sa demande envoyée, les jours posés sont verrouillés dans la grille d'Alice avec la mention « Congé · CP · en attente ». Si la demande est refusée ou annulée, ils redeviennent saisissables.
- [ ] Un enregistrement de temps sur un jour de congé, depuis un onglet ouvert avant la demande, est refusé avec un message, et la case reprend sa valeur précédente.
- [ ] Une demi-journée de congé laisse 2 quarts saisissables ce jour-là, et le jour est complet une fois ces 2 quarts saisis.
- [ ] Le maximum d'une semaine sans jour férié passe de 5 j à 4 j avec un jour posé, de 4,5 j à 4 j avec un matin posé, et de 4 j à 3 j avec un jour posé. Une semaine entièrement posée est complète sans aucun temps saisi, sans signal d'oubli.
- [ ] Alice, dont Bob est le manager, envoie une demande. Bob reçoit un e-mail, la voit dans sa liste à traiter, y lit le solde après la demande et voit que Charles, autre collaborateur de Bob, est absent sur une partie de la période.
- [ ] Bob ne peut pas refuser sans motif. Après un refus, Alice reçoit un e-mail avec le motif et le voit dans « Mes congés ».
- [ ] Un lead qui n'est pas le manager d'Alice ne peut pas décider de sa demande. Un membre de la direction le peut : Alice reçoit la décision et Bob en est informé par e-mail.
- [ ] Une personne sans manager actif voit sa demande envoyée à chaque membre actif de la direction, qui peut la décider.
- [ ] Bob ne voit pas ses propres demandes dans sa liste à traiter, et ne peut pas les décider.
- [ ] Le seul membre actif de la direction, sans manager actif, voit sa demande validée dès qu'il la pose, sans e-mail.
- [ ] Alice annule seule un congé validé qui commence demain ; ses jours reviennent au solde, sa grille se rouvre et Bob reçoit un e-mail. Elle ne peut plus annuler un congé commencé ; la direction le peut, et Alice en reçoit un e-mail.
- [ ] Une feuille de 10 j qui démarre le lundi 16 novembre 2026 avec une seule personne à 5 j par semaine et à 100 % finit le vendredi 27 novembre. Si cette personne a un congé validé le lundi 23 novembre, la feuille finit le lundi 30 novembre. Si ce congé est seulement en attente, elle finit toujours le vendredi 27.
- [ ] Un congé validé qui fait reculer une feuille sur la période d'une autre affectation de la même personne, au-delà de 100 %, signale la feuille « à replanifier ».
- [ ] Sur la roadmap, une feuille dont le seul membre est en congé validé le 18 novembre, sans saisie ce jour-là, n'est pas coupée le 18. Avec une demi-journée de congé seulement, elle l'est.
- [ ] Sur la roadmap et la fiche projet, le jour de congé d'un membre de l'équipe d'une feuille est signalé sur sa barre, et l'infobulle le nomme sans le type.
- [ ] Sur la fiche d'Alice, « libre à partir du … » saute ses jours de congé validés, et ses congés à venir et passés y figurent.
- [ ] Sur la page « Absences » de novembre 2026, un membre de la prod voit qu'Alice est absente du 9 au 13 novembre, mais pas le type. Bob et la direction voient « CP ». Une demande en attente d'Alice n'apparaît qu'à Alice, à Bob et à la direction.
- [ ] La direction déclare 25 j de CP pour 2026-2027 et 10 j de RTT pour 2026 à une personne. Elle voit, sur les compteurs de l'équipe, ses droits, ses jours validés, ses jours en attente et son solde. Un lead ou un membre de la prod n'accède pas aux compteurs de l'équipe.
- [ ] Un jour de congé validé que la direction ajoute ensuite comme jour férié au calendrier de la personne ne décompte plus rien, et son solde remonte de 1 j.

## Hors scope

- **Lien avec la paie** : ni récapitulatif mensuel ni export des congés pris. La paie reste alimentée à la main, à partir des compteurs.
- **Maladie et absences non demandées** (arrêt de travail, accident du travail) : non traitées. Elles continuent de rendre la capacité optimiste.
- **Fermetures d'entreprise** (reste de C4.3) : non traitées.
- **Acquisition des droits** : pas de calcul mensuel des CP, pas de règle d'ancienneté ou de fractionnement, pas de calcul des RTT à partir du temps de travail, pas de report automatique ni d'expiration. Les droits sont déclarés par la direction.
- **Types d'absence administrables** et autres types (récupération, congé parental, formation) : la liste est fixe.
- **Jours travaillés d'un temps partiel** (reste de C4.1) : Kadence ne sait pas quel jour un temps partiel ne travaille pas. Un congé posé ce jour-là décompte 1 j ; la personne ne le pose donc pas.
- **Poser un congé pour quelqu'un d'autre**, par le manager ou par la direction : non retenu.
- **Modification ou annulation partielle** d'un congé validé : on annule, puis on redemande.
- **Validation à plusieurs niveaux, délégation à une autre personne que la direction, relance ou expiration d'une demande en attente** : non retenues.
- **Règles de pose** (délai de prévenance, congé d'été obligatoire, nombre maximal d'absents simultanés) : rien n'est contrôlé. Le manager en juge.
- **Refus d'une demande pour cause de surcharge** : jamais. Un congé validé crée une surcharge passive (règle 36).
- **Demandes en attente dans la capacité** : seules les demandes validées pèsent sur la planification.
- **Rappel de saisie et taux de saisie** : relèvent de `rappel-saisie`, qui devra exclure les jours de congé des jours ouvrés.
- **Historique des droits déclarés** : non retenu, comme pour le rattachement à un calendrier.
- **Synchronisation avec un agenda** : non retenue (anti-objectif « pas d'intégration au départ »).

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement. Les libellés ajoutés (types, « Congé », « en attente », « matin », « après-midi », « Absences », « Mes congés ») et les e-mails sont en français.
- **Droits d'accès** : oui. Le manager obtient le droit de décider des demandes de ses collaborateurs actifs, ce qui contredit la règle transverse « être manager n'ouvre aucun droit » (story 007). Seule la direction déclare les droits, consulte les compteurs de l'équipe, décide à la place d'un manager et annule un congé commencé. Un lead n'a aucun droit nouveau.
- **Cloisonnement des données** : oui. Les dates d'absence sont visibles de tous. Le type, le commentaire, le motif d'un refus, les demandes en attente ou refusées et les soldes ne sont visibles que de la personne, de son manager actif et de la direction.
- **Apparence / déclinaisons** : la grille de saisie, sur ordinateur et sur téléphone, présente les jours de congé verrouillés et nommés. Nouvelles pages : « Mes congés », liste à traiter, « Absences », compteurs de l'équipe. La roadmap, la fiche projet et la fiche d'une personne signalent les absences.
- **Exposition à des tiers** : non. Aucun export, aucun agenda externe.
- **Emails / notifications** : oui. Une nouvelle demande au valideur, une décision à la personne (et au manager si la direction a décidé à sa place), l'annulation d'un congé validé au valideur ou à la personne (règle 45).
- **Données existantes** :
  - aucun congé n'existe, et tous les droits valent 0 j : la direction déclare les droits de chacun avant l'ouverture du module ;
  - les managers désignés par la story 007 deviennent valideurs sans autre action ;
  - aucun temps déjà saisi n'est touché ;
  - les données de démonstration comptent des droits déclarés, des congés validés, en attente, refusés et annulés, dont des demi-journées, et aucun temps saisi sur un jour de congé.
- **Comportement par défaut** : tant que personne n'a posé de congé, la grille, la planification, la roadmap et les fiches sont inchangées.

## Questions ouvertes

- **Désactivation d'une personne qui a des demandes en attente ou des congés validés à venir** : options : (a) ses demandes en attente sont annulées, ses congés validés à venir sont conservés ; (b) tout est conservé en l'état ; (c) tout ce qui n'a pas commencé est annulé. → tranché au plan : (a), sans e-mail.
- **Changement de manager ou de rôle d'un valideur pendant qu'une demande est en attente** : la règle 17 envoie la demande au nouveau valideur. Faut-il le prévenir par e-mail ? Options : (a) oui, il reçoit les demandes en attente qui lui arrivent ; (b) non, elles apparaissent seulement dans sa liste à traiter. → tranché au plan : (b).

---

## Annexe — Pistes pour le plan

- Le manager existe déjà (`User::$manager`, story 007). La décision peut passer par un voter qui accepte le manager actif et la direction, et refuse la personne elle-même. À confirmer.
- Les états d'une demande (en attente → validée, refusée ou annulée) se prêtent au composant Workflow de Symfony, ou à une simple enum. À confirmer.
- Types d'absence et demi-journée : enums backed string dans `src/Enum/Type/`. À confirmer.
- Verrou et maximum : `TimesheetBuilder` (cellule verrouillée ou plafonnée), `TimesheetManager` (refus à l'enregistrement), `WeeklyMaxManager::capFor()`, qui prend aujourd'hui le nombre de jours fériés, à étendre au prorata des jours de congé. À confirmer.
- Capacité : `ScheduleLoader::capacity()` construit la `DailyCapacity` à partir du maximum hebdomadaire et des jours fériés ; c'est là que les congés validés retireraient la capacité d'un jour. À confirmer.
- Jours ouvrés d'une personne : `RoadmapRunCutter` (coupes de la roadmap) et `PersonRoadmapBuilder` (fiche d'une personne) s'appuient sur les jours ouvrés, et devraient exclure les jours de congé validés. À confirmer.
- E-mails : aucun e-mail n'est encore envoyé par l'application (pas de `src/Mailer/`). Ce seraient les premières classes dédiées avec `TemplatedEmail`, éventuellement dispatchées par Messenger, et vérifiables dans Mailpit en dev. À confirmer.
- Décompte vivant (règle 6) : calculer le décompte à la lecture à partir des demi-journées et du calendrier plutôt que de stocker un nombre de jours figé. À confirmer.
