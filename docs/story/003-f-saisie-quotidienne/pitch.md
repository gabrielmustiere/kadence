# Saisir ses temps de la semaine en quarts de journée, d'un clic par case

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/003-f-saisie-quotidienne/`
> **Amont** : aucun

Chaque personne saisit ses temps dans la grille de sa semaine : les jours en colonnes, ses feuilles (lots et sous-lots) en lignes, et dans chaque case une barre de quatre quarts de journée qu'on remplit d'un clic, à la manière d'une notation par étoiles. Une journée ne dépasse jamais une journée pleine, ni la semaine le maximum de la personne. Une journée complète se colore en vert, un oubli se voit d'un coup d'œil. C'est la saisie fiable et fraîche sur laquelle repose toute la chaîne de Kadence.

## Contexte

Kadence connaît l'équipe et ses rôles (story 001), ainsi que le référentiel des projets découpés en lots et sous-lots estimés (story 002). Mais personne ne peut encore y déclarer son temps. Les temps vivent dans le tableur, consolidés à la main et en retard. Sans eux, l'estimé n'a pas de consommé auquel se comparer, aucune dérive n'est détectable et aucune date ne peut être projetée.

La saisie est le seul geste quotidien demandé à toute l'équipe (15 à 40 personnes). Si elle est lourde, elle n'est pas tenue, et la chaîne entière tombe : c'est le premier signal d'arrêt de la vision. Elle doit donc tenir en moins d'une minute. On retrouve d'office les feuilles sur lesquelles on travaille, on saisit d'un clic par case, et on voit d'un coup d'œil si sa journée et sa semaine sont complètes.

La saisie se fait au quart de journée, et non à la demi-journée comme le prévoyait le backlog. La demi-journée est trop grossière pour les journées morcelées (support, réunions, deux projets en parallèle). La barre à crans cumulatifs garde un seul clic par case, quelle que soit la valeur. Pour qu'une semaine « complète » ait un sens chez les temps partiels, la direction fixe pour chaque personne un maximum de saisie hebdomadaire : 5 jours par défaut, 4,5 jours pour un 90 %.

Les premiers temps saisis déclenchent enfin les garde-fous posés par la story 002 : estimation initiale conservée, révision bornée par le consommé, suppression interdite dès qu'un temps existe.

**Mesure de succès** : une journée répartie sur une ou deux lignes déjà proposées se saisit en autant de clics, en moins d'une minute depuis la connexion. À terme, au moins 90 % des jours ouvrés sont saisis dans les 48 h (métrique d'activation de la vision, mesurée par la story `rappel-saisie`).

## Alignement vision

- **Problème adressé** : fondateur, bien qu'indirect. La feature fournit le consommé sans lequel aucun des trois symptômes (dépassements découverts trop tard, planning intenable, roadmap opaque) ne peut être traité. Elle supprime aussi la consolidation manuelle des temps dans le tableur.
- **Audience servie** : toute l'équipe, qui saisit chaque jour. C'est l'utilisateur secondaire de la vision, dont dépend toute la chaîne. La direction et les leads saisissent aussi leurs propres temps et exploiteront la donnée dans les stories suivantes.
- **Principes respectés** : le principe 1 (« saisir sa journée prend moins d'une minute ») est le cœur de la feature : lignes courantes proposées d'office, un clic par case, enregistrement sans bouton, arrivée directe sur la saisie après connexion. Le principe 2 est respecté : la saisie est strictement personnelle, personne ne voit ni ne saisit les temps d'un autre, et les signaux d'oubli ne sont visibles que de la personne. Le principe 3 est respecté : on saisit sur les feuilles, jamais plus finement. L'anti-objectif « pas d'application mobile native » est tenu par une saisie utilisable sur téléphone, un jour à la fois.
- **Hypothèse testée** : H1 (une saisie de moins d'une minute est tenue à 90 % ou plus) ; cette feature en est la matière, et le taux sera mesuré par `rappel-saisie`. Elle prépare aussi H2 en conservant l'estimation initiale de chaque feuille au premier temps saisi.
- **Impact North Star** : indirect. Le consommé alimentera les dates projetées confrontées aux dates annoncées.

## Utilisateurs concernés

- **Toute personne active** (direction, lead, prod) : saisit ses propres temps dans la grille de sa semaine, sur laquelle elle arrive après connexion.
- **Direction** : fixe en plus, sur la fiche de chaque personne de l'équipe, son maximum de saisie hebdomadaire.
- **Lead et direction, en tant que gestionnaires des projets** : ne peuvent plus supprimer une feuille, un lot ou un projet qui porte des temps. Quand ils découpent un lot qui porte des temps, ces temps passent sur le premier sous-lot.
- **Responsable d'une feuille** (quel que soit son rôle) : une fois des temps saisis, il révise l'estimation sans descendre sous le consommé, et l'estimation initiale reste consultable.
- **Personne désactivée** : ne saisit plus, puisque sa connexion est refusée. Ses temps sont conservés.

## User Stories

- En tant que **membre de l'équipe**, je veux arriver sur la grille de ma semaine en cours dès ma connexion afin de saisir sans chercher.
- En tant que **membre de l'équipe**, je veux retrouver d'office en lignes les feuilles sur lesquelles j'ai saisi cette semaine ou la précédente afin de ne pas les rechercher chaque jour.
- En tant que **membre de l'équipe**, je veux ajouter à ma grille n'importe quelle feuille en la cherchant par son nom afin de saisir sur un travail nouveau.
- En tant que **membre de l'équipe**, je veux voir au survol d'une case les quarts de journée se remplir jusqu'au cran pointé afin de jauger ma saisie avant de cliquer.
- En tant que **membre de l'équipe**, je veux saisir d'un clic ¼, ½, ¾ ou 1 journée dans une case, enregistrée immédiatement, afin de ne rien avoir à valider ni risquer de perdre.
- En tant que **membre de l'équipe**, je veux remettre une case à zéro en recliquant sur son cran actif afin de corriger une erreur sans autre commande.
- En tant que **membre de l'équipe**, je veux que les crans qui me feraient dépasser une journée par jour ou mon maximum de la semaine soient verrouillés afin de ne jamais saisir une valeur incohérente.
- En tant que **membre de l'équipe**, je veux voir le total de chaque jour et de ma semaine, une colonne passer au vert quand la journée est complète et mes jours passés incomplets signalés afin de repérer un oubli d'un coup d'œil.
- En tant que **membre de l'équipe**, je veux naviguer de semaine en semaine afin de corriger une journée passée.
- En tant que **membre de l'équipe sur téléphone**, je veux saisir un jour à la fois avec les mêmes crans afin de saisir hors de mon poste.
- En tant que **direction**, je veux fixer pour chaque personne son maximum de saisie hebdomadaire afin que la semaine d'un temps partiel soit complète à son niveau, et non à 5 jours.
- En tant que **lead ou direction**, je veux découper en sous-lots un lot qui porte déjà des temps, ces temps suivant le premier sous-lot, afin d'affiner le découpage au moment où je découvre qu'un lot est trop gros.
- En tant que **responsable d'une feuille**, je veux réviser son estimation après les premiers temps saisis, sans descendre sous le consommé, et retrouver son estimation initiale afin de tenir une estimation réaliste sans effacer la référence.
- En tant que **membre de l'équipe**, je ne veux PAS pouvoir saisir sur un jour futur afin de ne jamais déclarer un temps que je n'ai pas encore passé.
- En tant que **membre de l'équipe**, je ne veux PAS que quiconque, lead ou direction compris, voie ou saisisse mes temps, afin que ma saisie reste honnête.
- En tant que **lead ou direction**, je ne veux PAS pouvoir supprimer une feuille, un lot ou un projet qui porte des temps afin de ne jamais perdre du consommé.

## Règles métier

### Grille

1. **Grille personnelle** : chaque personne ne voit et ne saisit que ses propres temps. Aucun rôle, pas même la direction, ne voit ni ne saisit les temps d'une autre personne (principe 2 de la vision).
2. **Semaine** : la grille présente une semaine, du lundi au vendredi. Le samedi et le dimanche ne sont ni affichés ni saisissables.
3. **Navigation** : des boutons « semaine précédente », « semaine suivante » et « cette semaine » changent la semaine affichée.
4. **Page d'accueil** : après connexion, toute personne arrive sur la grille de sa semaine en cours. La grille est aussi accessible depuis le menu principal.
5. **Lignes = feuilles** : seules les feuilles (lot sans sous-lot, ou sous-lot) sont saisissables. Chaque ligne est intitulée « Projet › Lot » ou « Projet › Lot › Sous-lot ». Un lot découpé et un projet sans lot ne sont jamais des lignes.
6. **Lignes proposées d'office** : ce sont les feuilles qui portent au moins un temps de la personne sur la semaine affichée ou sur la semaine précédente. Elles sont triées par projet (ordre alphabétique), puis par lot et sous-lot (ordre de création), comme dans le référentiel.
7. **Ajouter une ligne** : la personne peut chercher n'importe quelle feuille par le titre de son projet, de son lot ou de son sous-lot, et l'ajouter à la grille. Une ligne ajoutée qui ne reçoit aucun temps n'est pas conservée : elle disparaît quand on change de semaine ou qu'on recharge la page.

### Saisie

8. **Crans** : une case vaut 0, ¼, ½, ¾ ou 1 journée, et aucune autre valeur.
9. **Survol** : au survol d'un cran, ce cran et ceux qui le précèdent se remplissent, pour jauger la valeur avant de cliquer.
10. **Clic** : cliquer sur un cran fixe directement la case à cette valeur. Cliquer sur le cran actif, c'est-à-dire le dernier rempli, remet la case à 0.
11. **Enregistrement immédiat** : chaque clic est enregistré sans bouton de validation, avec une confirmation visuelle discrète. Un clic refusé est signalé par un message, et la case reprend sa valeur précédente.
12. **Plafond du jour** : le cumul d'un jour, toutes lignes confondues, ne dépasse jamais 1 journée. Dans chaque case, les crans qui feraient dépasser ce plafond sont grisés et inactifs, et le survol s'arrête au maximum possible.
13. **Plafond de la semaine** : le cumul d'une semaine ne dépasse jamais le maximum hebdomadaire de la personne. Les crans qui feraient dépasser ce plafond sont verrouillés de la même façon. Des deux plafonds, c'est le plus restrictif qui s'applique. Diminuer une case reste toujours possible, même sur une semaine qui dépasse un maximum abaissé depuis.
14. **Contrôle à l'enregistrement** : les deux plafonds sont revérifiés à chaque enregistrement, y compris quand la grille est ouverte à plusieurs endroits à la fois. Un clic qui ferait dépasser un plafond est refusé.
15. **Jours saisissables** : tous les jours passés, sans limite d'ancienneté, ainsi qu'aujourd'hui. Les jours futurs sont affichés mais verrouillés. On peut naviguer vers une semaine future sans pouvoir y saisir.
16. **L'estimation ne bloque jamais la saisie** : on peut saisir sur une feuille « à estimer », et le consommé d'une feuille peut dépasser son estimation. C'est précisément ce que la détection des dérives devra voir.

### Maximum hebdomadaire

17. **Maximum hebdomadaire** : chaque personne a un maximum de saisie par semaine, exprimé en jours au quart de journée près, entre ¼ et 5 jours. Il vaut 5 jours tant qu'aucune valeur n'a été fixée, y compris pour les personnes déjà inscrites. Seule la direction le fixe, sur la fiche de la personne, en indiquant la semaine à partir de laquelle il s'applique.
18. **Historique du maximum** : chaque semaine applique la valeur en vigueur à son lundi, et les semaines antérieures à un changement gardent l'ancienne valeur. Une nouvelle valeur pour une semaine qui en avait déjà une la remplace. L'historique est affiché sous la fiche, et une valeur fixée par erreur peut y être supprimée : la précédente s'applique alors de nouveau.

### Retour visuel

19. **Totaux** : chaque colonne affiche le total saisi du jour. L'en-tête de la grille affiche le total de la semaine face au maximum hebdomadaire (par exemple « 3 j / 4,5 j »).
20. **Jour complet** : une colonne dont le total atteint 1 journée prend un fond vert très clair.
21. **Aujourd'hui** : la colonne du jour courant est mise en évidence.
22. **Oubli signalé** : tant que le total de la semaine n'a pas atteint le maximum hebdomadaire, chaque jour passé (avant aujourd'hui) dont le total est inférieur à 1 journée est signalé discrètement. Dès que la semaine atteint son maximum, ces signaux disparaissent, car les jours restants correspondent au temps non travaillé. Ils ne sont visibles que de la personne elle-même.
23. **Téléphone** : sur petit écran, la grille présente un seul jour à la fois, avec un sélecteur des jours de la semaine. Le jour affiché par défaut est aujourd'hui pour la semaine en cours, et le lundi pour une autre semaine. Les lignes, les crans, les plafonds, les totaux et les signaux s'appliquent à l'identique.

### Garde-fous sur le référentiel (règles 17 et 18 de la story 002)

24. **Estimation initiale** : l'estimation en vigueur au premier temps saisi sur une feuille, toutes personnes confondues, est conservée comme estimation initiale et reste consultable sur la feuille. Si la feuille était « à estimer » à ce moment-là, l'estimation initiale est la première estimation déclarée ensuite.
25. **Révision bornée** : une fois des temps saisis, l'estimation d'une feuille reste révisable par ceux qui la gèrent (lead, direction, responsable). Une révision ne descend jamais sous le consommé arrondi au jour supérieur (3,25 j consommés imposent 4 j au minimum), et l'estimation ne peut plus être retirée. La borne ne porte que sur un changement d'estimation : une feuille dont le consommé a dépassé l'estimation (règle 16) reste modifiable (titre, description, responsable, premier sous-lot) sans relever son estimation.
26. **Suppression interdite** : un projet, un lot ou un sous-lot ne peut plus être supprimé dès qu'un temps est saisi sur lui ou sur l'un de ses descendants. Cela vaut aussi pour le dernier sous-lot d'un lot. La suppression est refusée avec un message qui l'explique.
27. **Découpage d'un lot qui porte des temps** : quand un lot qui porte des temps reçoit son premier sous-lot, ce sous-lot reprend ses temps, en plus de son estimation, de son estimation initiale et de son responsable (prolongement de la règle 11 de la story 002). Les totaux sont inchangés. Chez les personnes concernées, la ligne devient « Projet › Lot › Sous-lot ».
28. **Conservation** : les temps d'une personne désactivée sont conservés. Un changement de responsable d'une feuille n'a aucun effet sur les temps qui y sont saisis.

## Critères d'acceptation

- [ ] Après connexion, toute personne active arrive sur la grille de sa semaine en cours, du lundi au vendredi, avec la colonne du jour mise en évidence.
- [ ] La grille propose d'office les feuilles sur lesquelles la personne a saisi la semaine affichée ou la précédente, intitulées « Projet › Lot › Sous-lot », et aucune autre.
- [ ] « Ajouter une ligne » retrouve une feuille par le titre de son projet, de son lot ou de son sous-lot ; un lot découpé et un projet sans lot n'y sont pas proposés.
- [ ] Une ligne ajoutée sans aucun temps n'est plus affichée après rechargement de la semaine.
- [ ] Au survol du n-ième cran d'une case, les n premiers crans se remplissent.
- [ ] Un clic fixe la case à ¼, ½, ¾ ou 1 j sans bouton de validation, et la valeur est conservée après rechargement.
- [ ] Un clic sur le cran actif d'une case la remet à 0 ; un clic sur un autre cran la fixe directement à la nouvelle valeur.
- [ ] Quand ¾ j sont déjà saisis sur un jour, une autre case de ce jour n'offre que son premier cran, les trois autres étant grisés et inactifs.
- [ ] Quand la semaine a atteint le maximum hebdomadaire de la personne, aucun cran supplémentaire n'est actif sur la semaine.
- [ ] Sur une semaine qui dépasse un maximum abaissé depuis, une case peut encore être diminuée ou remise à 0.
- [ ] Un enregistrement qui ferait dépasser 1 j sur un jour ou le maximum de la semaine, par exemple depuis un second onglet, est refusé avec un message, et la case reprend sa valeur précédente.
- [ ] Les jours futurs sont affichés verrouillés ; un jour d'une semaine passée reste saisissable et corrigeable.
- [ ] Les boutons « semaine précédente », « semaine suivante » et « cette semaine » changent la semaine affichée.
- [ ] Chaque colonne affiche son total, et l'en-tête affiche le total de la semaine face au maximum (« 3 j / 4,5 j »).
- [ ] Une colonne dont le total atteint 1 j prend un fond vert très clair.
- [ ] Un jour passé sous 1 j est signalé tant que la semaine n'a pas atteint son maximum, et le signal disparaît dès que le maximum est atteint.
- [ ] Sur un écran de téléphone, la grille présente un seul jour à la fois avec un sélecteur des jours ; les crans, plafonds et totaux s'y appliquent à l'identique.
- [ ] La direction fixe le maximum hebdomadaire d'une personne au quart de journée, entre ¼ et 5 j, à partir d'une semaine choisie ; une personne sans réglage a 5 j ; ni un lead ni un membre de prod ne peut le modifier.
- [ ] Un changement de maximum ne s'applique qu'à partir de la semaine choisie : les semaines antérieures gardent l'ancienne valeur, et supprimer une valeur de l'historique rétablit la précédente.
- [ ] Aucun rôle ne peut consulter ni modifier les temps d'une autre personne.
- [ ] On peut saisir sur une feuille « à estimer », et au-delà de l'estimation d'une feuille.
- [ ] Après le premier temps saisi sur une feuille, son estimation initiale est consultable sur la feuille.
- [ ] Une révision d'estimation sous le consommé arrondi au jour supérieur, ou le retrait de l'estimation, est refusé une fois des temps saisis.
- [ ] La suppression d'un projet, d'un lot ou d'un sous-lot qui porte des temps, directement ou par ses descendants, est refusée avec un message.
- [ ] Quand un lot qui porte des temps reçoit son premier sous-lot, ce sous-lot reprend les temps, l'estimation, l'estimation initiale et le responsable du lot, et les totaux sont inchangés.
- [ ] Une journée répartie sur deux lignes déjà proposées se saisit en deux clics, en moins d'une minute depuis la connexion.

## Hors scope

- **Rappel de saisie et taux de saisie de l'équipe** : relèvent de `rappel-saisie`. Le signal d'oubli de cette story reste personnel et n'envoie rien.
- **Consommé face à l'estimé** (dans la grille ou dans le référentiel) : relève de `consomme-vs-estime`. Seul le message de refus d'une révision d'estimation mentionne le consommé de la feuille.
- **Jours fériés, fermetures, absences, jours travaillés d'un temps partiel** : relèvent de `capacite-equipe` (V2). D'ici là, un jour férié est un jour ordinaire, et une semaine qui en contient un reste signalée incomplète tant que son maximum n'est pas atteint.
- **Temps de travail détaillé** (jours travaillés, répartition du temps partiel sur la semaine) : relève de `capacite-equipe`. Cette story n'avance qu'un maximum hebdomadaire par personne, historisé par semaine d'effet.
- **Saisie pour le compte d'une autre personne** : exclue par le principe 2 de la vision.
- **Duplication de la semaine précédente, saisie en masse** : non retenues.
- **Commentaire ou description sur un temps** : non retenu. On ne descend pas à la tâche (principe 3).
- **Épinglage ou masquage manuel d'une ligne** : non retenu. Les lignes suivent les temps saisis.
- **Saisie le week-end** : non retenue.
- **Blocage de la saisie sur une feuille clôturée ou un projet archivé** : ces états n'existent pas encore. `jalons-dates-annoncees` et `archivage-projets` poseront ce blocage.
- **Durée de conservation et purge des temps** : relèvent de `purge-donnees-temps`.
- **Import des temps historiques du tableur** : non retenu (anti-objectif « pas d'intégration au départ »).
- **Réalignement du backlog** : la convention « saisie en demi-journées » et la capacité C3.1 passent au quart de journée, et une part de C4.1 (le maximum hebdomadaire) est avancée dans cette story. À reporter via `/product-backlog`.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement. Les valeurs de case s'affichent en quarts (¼, ½, ¾, 1 j) et les totaux en jours au quart près (« 3,75 j »).
- **Droits d'accès** : oui. Les temps sont une donnée strictement personnelle : seule la personne qui les saisit les voit et les modifie, quel que soit son rôle. Le maximum hebdomadaire est réservé à la direction. Les gestionnaires de projets perdent la suppression de tout élément qui porte des temps.
- **Cloisonnement des données** : oui, c'est la première donnée cloisonnée par personne. Les temps d'une personne ne sont visibles que d'elle-même. Les totaux par feuille, lot et projet seront exposés agrégés par `consomme-vs-estime`, jamais par personne.
- **Apparence / déclinaisons** : la grille de la semaine sur ordinateur, un jour à la fois sur téléphone. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucun temps n'existe encore. Toutes les personnes déjà inscrites reçoivent un maximum hebdomadaire de 5 jours. Les données de démonstration doivent contenir quelques semaines de temps, dont une personne à temps partiel et un lot découpé après saisie.
- **Comportement par défaut** : une personne qui n'a encore rien saisi arrive sur une grille sans ligne, avec « Ajouter une ligne » ; son maximum hebdomadaire est de 5 jours.

## Questions ouvertes

- **Modification du maximum hebdomadaire d'une personne** : Options : (a) la nouvelle valeur s'applique à toutes les semaines, passées comprises, sans toucher aux temps déjà saisis au-delà ; aucun cran supplémentaire n'est alors possible sur une semaine qui dépasse le nouveau maximum, (b) chaque valeur prend effet à une date, et les semaines antérieures gardent l'ancienne. (b) rejoint le temps de travail historisé de `capacite-equipe`. → tranché : (b), avec suppression possible d'une valeur de l'historique (règle 18) ; une diminution de case reste toujours possible sur une semaine qui dépasse un maximum abaissé (règle 13).
- **Consommé antérieur au lancement** (à trancher avec `consomme-vs-estime`) : Options : (a) rien de spécifique, le consommé part de zéro au lancement et chacun peut ressaisir à la main ses semaines passées, puisqu'elles restent ouvertes, (b) un consommé de reprise est déclaré par feuille au lancement, pour que le rythme de consommation des projets en cours ne parte pas de zéro.

---

## Annexe — Pistes pour le plan

- Temps stocké en nombre entier de quarts (1 à 4) plutôt qu'en décimal, pour éviter les arrondis ; une case à 0 correspond à l'absence de saisie — à confirmer.
- Une saisie unique par triplet (personne, feuille, jour), rattachée à `Lot` (feuille) et `User` — à confirmer.
- Interaction de case (survol, clic, re-clic) : contrôleur Stimulus avec enregistrement unitaire par requête (Turbo Stream ou `fetch`), ou Live Component — à arbitrer au plan. Le serveur renvoie les plafonds recalculés pour mettre à jour les crans verrouillés de la colonne et de la semaine.
- Plafonds (jour ≤ 4 quarts, semaine ≤ maximum) vérifiés dans un service métier, avec une protection contre les enregistrements concurrents depuis deux onglets — à confirmer.
- Maximum hebdomadaire : champ en quarts sur `User` (défaut 20), éditable dans le formulaire Équipe (`TeamController`), migration avec valeur par défaut pour les comptes existants — à confirmer.
- Estimation initiale : champ sur `Lot` figé au premier temps saisi ; la bascule vers le premier sous-lot (`ProjectManager`) est à étendre pour y déplacer les temps ; la suppression est à bloquer dans `ProjectManager` et le voter `LOT_EDIT` — à confirmer.
- Page d'accueil : `default_target_path` et la route `/` (`PageController`) à faire pointer vers la saisie — à confirmer.
- Téléphone : même page, disposition « un jour » en responsive avec onglets (composant `Tabs` existant) — à confirmer.
- Design system Paper : tokens pour le vert très clair et le signal d'oubli, composant Twig dédié pour la barre à 4 segments ; accessibilité clavier (groupe de boutons radio, flèches) à prévoir.
