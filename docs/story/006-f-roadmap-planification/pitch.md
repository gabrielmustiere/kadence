# Planifier chaque feuille avec une date de début et une équipe pour dessiner la chronologie des projets sur une roadmap

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/006-f-roadmap-planification/`
> **Amont** : aucun

Les leads et la direction posent, sur chaque feuille estimée, une date de début et une équipe dont chaque membre consacre une part de sa capacité. Kadence en déduit la chronologie de la feuille. Le passé correspond strictement aux temps saisis. Le futur se calcule sur le restant, en tenant compte de la capacité de l'équipe (maximum hebdomadaire et jours fériés de chacun). Une page Roadmap dessine, projet par projet, cette chronologie sur une frise en semaines : c'est la première réponse lisible à « c'est pour quand ? ».

## Contexte

Aujourd'hui, Kadence connaît le découpage des projets en lots et sous-lots, l'estimation et le responsable de chaque feuille, les temps saisis chaque jour, le maximum hebdomadaire de chacun et les jours fériés de son calendrier. Mais aucune de ces données n'a de dimension temporelle. On ne sait ni quand une feuille commence, ni qui y travaille en dehors de son responsable, ni quand elle se termine. La roadmap vit encore dans le tableur ou dans la tête des leads.

Répondre à « c'est pour quand ? » demande donc de refaire le calcul à la main : diviser l'estimation par le nombre de personnes, retirer les jours fériés et les temps partiels, et deviner qui est déjà pris ailleurs sur les mêmes semaines. Ce calcul n'est jamais à jour. Il ignore ce qui a réellement été consommé, et il ne voit pas qu'une même personne est promise à deux sujets en même temps. Les dates restent des paris, et le planning est intenable parce qu'on s'engage sans connaître la disponibilité réelle de l'équipe.

Le backlog prévoyait quatre briques successives : une période prévue saisie (début et fin), une affectation de capacité, une date de fin projetée et une roadmap. Cette story les ramène à un modèle plus simple. On pose un début et une équipe, et la fin en découle, recalée en continu sur la saisie. La date annoncée et son historique restent pour plus tard.

**Mesure de succès** : deux semaines après la livraison, toutes les feuilles estimées des projets en cours ont une date de début et une équipe. La direction répond à « c'est pour quand ? » sur un projet en lisant la page Roadmap, sans recalcul. Un lead planifie une feuille (date de début et équipe de deux personnes avec leurs parts) en moins d'une minute.

## Alignement vision

- **Problème adressé** : direct sur deux des trois symptômes. « Roadmap opaque » : la chronologie de chaque projet se lit sur une page. « Planning intenable » : on ne peut plus promettre une personne au-delà de sa capacité. Le troisième symptôme, « dépassements découverts trop tard », n'est touché qu'indirectement : une barre qui s'allonge rend visible un projet qui glisse.
- **Audience servie** : la direction lit la roadmap pour s'engager. Les leads planifient et replanifient. L'équipe de prod consulte la roadmap pour savoir sur quoi elle sera dans les semaines à venir, usage prévu par la vision.
- **Principes respectés** : le principe 1 est respecté, car la saisie quotidienne n'est pas alourdie. Elle devient même plus précieuse : le passé de la roadmap, c'est elle. Le principe 3 est respecté, car on planifie au niveau de la feuille, jamais de la tâche. Le principe 2 est sous tension assumée. L'équipe et la part de chacun sont visibles de tous, comme le responsable, car ce sont des désignations et non des mesures d'activité. En revanche, la surcharge d'une personne n'est signalée qu'aux leads et à la direction, et la partie réalisée d'une barre reste agrégée par feuille, sans jamais dire qui a saisi quoi. Le principe 4 est lui aussi sous tension assumée : la roadmap n'affiche pour l'instant que des dates calculées, faute de date annoncée. Quand `jalons-dates-annoncees` arrivera, la date annoncée devra se placer sur la même frise, à côté de la fin calculée.
- **Hypothèse testée** : H3 directement. C'est la première fois que des dates de fin sont calculées à partir des estimations déclaratives au niveau le plus fin, et leur crédibilité devient observable. H1 indirectement : une saisie non tenue fait reculer les barres, ce qui rend la discipline de saisie visible au niveau des projets.
- **Impact North Star** : indirect. La story produit la date calculée qui sera confrontée à la date annoncée. Sans jalon ni date annoncée, rien n'alimente encore la North Star.

## Utilisateurs concernés

- **Lead** (gère tous les projets) : pose et modifie la date de début, l'équipe et les parts de toute feuille. Il voit les signaux de surcharge « à replanifier ».
- **Direction** : mêmes droits que les leads sur la planification. Lit la roadmap pour répondre à « c'est pour quand ? ».
- **Prod** : consulte la roadmap et les équipes, sans les signaux de surcharge. Sa saisie, inchangée, alimente la partie réalisée des barres.
- **Responsable d'une feuille** (quel que soit son rôle) : fait automatiquement partie de l'équipe de sa feuille. Il continue de réviser l'estimation, mais ne modifie ni la date de début ni l'équipe.
- **Personne désactivée** : ne peut pas être ajoutée à une équipe. À partir de sa désactivation, sa capacité ne compte plus dans les équipes dont elle fait partie.

## User Stories

- En tant que **lead ou direction**, je veux poser une date de début sur une feuille afin de dire quand le travail commence.
- En tant que **lead ou direction**, je veux composer l'équipe d'une feuille parmi les personnes actives, avec la part de capacité que chacune y consacre, afin que la fin se calcule sur la capacité réellement engagée.
- En tant que **lead ou direction**, je veux qu'une planification qui ferait dépasser 100 % de sa capacité à une personne, un jour donné, soit refusée avec la feuille en conflit, afin de ne pas construire un planning intenable.
- En tant que **lead ou direction**, je veux voir les feuilles « à replanifier » quand une surcharge apparaît d'elle-même (saisie plus lente, estimation révisée, capacité réduite), afin de décaler ou de rééquilibrer.
- En tant que **direction**, je veux que la fin d'une feuille se recale sur ce qui a réellement été saisi afin de lire une chronologie à jour, pas un plan périmé.
- En tant que **personne connectée**, je veux consulter une page Roadmap où chaque projet se déplie en lots et sous-lots sur une frise en semaines, afin de savoir quand chaque projet se termine et sur quoi l'équipe sera dans les semaines à venir.
- En tant que **lead ou direction**, je veux repérer sur la roadmap les feuilles non planifiées (à estimer, sans début, sans équipe) afin de compléter le planning.
- En tant que **personne connectée**, je veux naviguer vers les semaines précédentes ou suivantes de la frise afin de voir au-delà de la fenêtre par défaut.
- En tant que **membre de prod**, je ne veux PAS que la roadmap montre qui a saisi quel jour ni qui est en surcharge, car la charge individuelle ne sert qu'à planifier.
- En tant que **responsable d'une feuille**, je ne veux PAS pouvoir modifier sa date de début ni son équipe, car la planification relève des leads.

## Règles métier

**Planification d'une feuille**

1. **Portée** : seule une feuille (lot sans sous-lot, ou sous-lot) porte une date de début et une équipe. Un lot découpé et un projet n'en portent pas.
2. **Date de début** : facultative, passée ou à venir. Si elle tombe sur un jour non ouvré, le calcul part du premier jour ouvré qui suit.
3. **Équipe** : aucune, une ou plusieurs personnes actives, tous rôles confondus, chacune au plus une fois.
4. **Part** : chaque membre consacre à la feuille 25, 50, 75 ou 100 % de sa capacité, 100 % par défaut. La part vaut pour toute la durée de la feuille.
5. **Responsable dans l'équipe** : désigner un responsable l'ajoute à l'équipe à 100 % s'il n'y figure pas déjà ; sa part reste modifiable. Il ne peut pas être retiré de l'équipe tant qu'il est responsable. Changer de responsable ne retire pas l'ancien de l'équipe.
6. **Droits** : les leads et la direction posent et modifient la date de début, l'équipe et les parts de toute feuille. Le responsable d'une feuille ne peut pas les modifier ; ses droits (titre, description, estimation) sont inchangés.

**Capacité et calcul de la chronologie**

7. **Jours ouvrés** : du lundi au vendredi, hors jours fériés du calendrier de chaque membre.
8. **Capacité d'un membre** : sur une semaine, un membre apporte à la feuille sa part de son maximum hebdomadaire en vigueur cette semaine-là, plafonné à ses jours non fériés comme pour la saisie. Les absences (congés, maladie) ne sont pas encore connues de Kadence et ne sont pas déduites.
9. **Membre désactivé** : à partir de sa désactivation, sa capacité ne compte plus. Il reste affiché dans l'équipe, et la feuille est signalée « équipe à revoir ».
10. **Restant** : estimation moins l'ensemble des temps saisis sur la feuille, quelle que soit la personne qui les a saisis, membre de l'équipe ou non.
11. **Passé** : la partie réalisée d'une barre s'étend du premier au dernier jour où des temps ont été saisis sur la feuille. Aujourd'hui étant encore saisissable, il appartient au passé.
12. **Futur** : le restant se répartit sur la capacité de l'équipe, jour ouvré après jour ouvré. Le calcul commence le lendemain d'aujourd'hui, ou à la date de début si elle est plus tardive. La fin calculée est le jour où le restant est couvert.
13. **Temps saisis avant la date de début** : la barre commence au premier jour saisi.
14. **Démarrage en retard** : une feuille dont la date de début est passée et sur laquelle aucun temps n'est saisi n'a pas de partie réalisée. Son futur commence demain, et elle est signalée « démarrage en retard ».
15. **Estimation atteinte ou dépassée** : une feuille dont le restant est nul ou négatif n'a pas de partie future.
    - **Estimation atteinte** (saisi = estimé) : la feuille se termine au dernier jour saisi. Elle est signalée « estimation atteinte », sans alarme, car c'est le cas normal d'une feuille terminée.
    - **En dépassement** (saisi > estimé) : le travail peut continuer, mais rien ne dit combien de temps tant que l'estimation n'est pas révisée. La feuille n'a donc pas de fin calculée (« fin inconnue, estimation à réviser »). Elle est signalée « en dépassement », avec l'ampleur (« dépassé de 2 j »), et les jours saisis au-delà de l'estimation apparaissent en rouge sur sa barre, à partir du jour où l'estimation a été franchie.
    - Kadence ne distingue pas encore une feuille terminée d'une feuille en cours : c'est la clôture, qui relève de `jalons-dates-annoncees`.
16. **Équipe sans capacité** : une feuille dont l'équipe n'apporte plus aucune capacité (tous ses membres sont désactivés), ou dont le restant n'est pas couvert dans les trois ans, n'a pas de fin calculée. Elle est signalée « équipe à revoir ».
17. **Recalcul continu** : la chronologie d'une feuille dépend de sa date de début, de son estimation, de son équipe et des parts, du maximum hebdomadaire et des jours fériés des membres, de leur désactivation éventuelle et des temps saisis. Tout changement de l'une de ces données la déplace, sans action du lead. Chaque feuille se calcule indépendamment des autres.

**Surcharge**

18. **Charge d'une personne** : un jour donné, la somme de ses parts sur les feuilles dont la partie future couvre ce jour. Le passé n'entre pas dans la charge.
19. **Refus à la planification** : un geste de planification est refusé s'il ferait dépasser 100 % de charge à une personne un jour où elle ne dépassait pas, ou s'il augmenterait un dépassement existant. Sont concernés : poser ou modifier une date de début, ajouter ou retirer un membre, changer une part, désigner un responsable. Le message nomme la personne, la ou les feuilles en conflit et le premier jour concerné.
20. **Surcharge passive** : une surcharge qui n'est pas causée par un geste de planification est acceptée. C'est le cas d'une saisie plus lente que prévu, d'une estimation révisée, d'un maximum hebdomadaire abaissé, d'un jour férié ajouté ou d'une désactivation. Les feuilles concernées sont signalées « à replanifier » tant que la surcharge dure.
21. **Visibilité des surcharges** : le signal « à replanifier » n'est visible que des leads et de la direction. Les autres signaux (« démarrage en retard », « estimation atteinte », « en dépassement », « équipe à revoir », « à estimer », « sans début », « sans équipe ») portent sur une feuille et sont visibles de tous.

**Page Roadmap**

22. **Accès** : toute personne connectée consulte la page Roadmap. Son entrée figure dans la zone du haut du menu, à côté de Tableau de bord et Ma semaine.
23. **Structure** : tous les projets, par ordre alphabétique, chacun dépliable en lots puis en sous-lots, par ordre de création comme ailleurs dans l'outil.
24. **Barre d'une feuille** : elle montre sa partie réalisée, la part de cette partie saisie au-delà de l'estimation le cas échéant, et sa partie future, visuellement distinctes. Il y a un vide entre la partie réalisée et la partie future quand des jours passés n'ont pas été saisis. On lit pour chaque feuille sa date de début effective, sa fin calculée (ou « fin inconnue »), son restant ou son dépassement en jours, et son équipe avec la part de chacun.
25. **Barre de cumul** : un lot découpé et un projet affichent une barre qui va du premier jour de la plus précoce de leurs feuilles au dernier jour de la plus tardive. Dès qu'une de leurs feuilles planifiées n'a pas de fin calculée, leur fin est inconnue.
26. **Feuilles non planifiées** : une feuille sans barre apparaît quand même dans son projet, avec ce qui lui manque : « à estimer », « sans début » ou « sans équipe ». Un projet qui contient au moins une feuille non planifiée est signalé « planning partiel ». Sa barre de cumul ne couvre que les feuilles planifiées.
27. **Frise** : axe en semaines, avec un repère « aujourd'hui ». La fenêtre par défaut va de 4 semaines avant la semaine en cours à 36 semaines après. On peut naviguer vers la période précédente ou suivante et revenir à la fenêtre par défaut. Une barre qui déborde de la fenêtre est coupée au bord, avec l'indication qu'elle continue.

**Découpage d'un lot**

28. **Bascule vers un lot découpé** : quand un lot reçoit son premier sous-lot, ce sous-lot reprend aussi sa date de début et son équipe avec leurs parts. Sa chronologie reste donc inchangée. Ce transfert n'est pas un geste de planification.
29. **Bascule inverse** : quand on supprime le dernier sous-lot d'un lot, sa date de début et son équipe remontent sur le lot, avec l'estimation et le responsable.

## Critères d'acceptation

- [ ] Un lead pose une date de début et une équipe de deux personnes actives avec leurs parts sur une feuille estimée ; la feuille apparaît avec une barre sur la page Roadmap.
- [ ] Seules des personnes actives sont proposées comme membres ; la part se choisit parmi 25, 50, 75 et 100 %, 100 % par défaut.
- [ ] Un lot découpé et un projet n'offrent ni date de début ni équipe à renseigner.
- [ ] Désigner un responsable l'ajoute à l'équipe à 100 % ; tant qu'il est responsable, il ne peut pas en être retiré.
- [ ] Un membre de prod responsable d'une feuille ne peut modifier ni sa date de début ni son équipe.
- [ ] Une feuille de 10 j qui démarre un lundi sans jour férié, avec une personne à 5 j/semaine à 100 %, finit le vendredi de la semaine suivante. Avec deux personnes à 100 %, elle finit le vendredi de la même semaine. Avec une personne à 50 %, elle finit le vendredi de la quatrième semaine.
- [ ] Un jour férié du calendrier d'un membre dans la période repousse la fin calculée ; un maximum hebdomadaire abaissé la repousse aussi.
- [ ] Dans le premier exemple, si seuls 3 j sont saisis la première semaine, la fin calculée constatée le vendredi de cette semaine recule de deux jours ouvrés, au mardi de la troisième semaine.
- [ ] La partie réalisée d'une barre couvre exactement les jours saisis, du premier au dernier ; elle ne dit pas qui a saisi.
- [ ] Une feuille dont la date de début est passée et sans aucun temps saisi démarre demain sur la frise et est signalée « démarrage en retard ».
- [ ] Une feuille dont des temps sont saisis avant sa date de début commence au premier jour saisi.
- [ ] Une feuille dont le consommé égale l'estimation se termine au dernier jour saisi et est signalée « estimation atteinte », sans alarme.
- [ ] Une feuille dont le consommé dépasse l'estimation est signalée « en dépassement » avec l'ampleur du dépassement ; les jours saisis au-delà de l'estimation sont en rouge sur sa barre, et sa fin, comme celle de son lot et de son projet, est « inconnue » tant que l'estimation n'est pas révisée.
- [ ] Ajouter un membre, changer une part, poser une date de début ou désigner un responsable qui ferait dépasser 100 % à une personne un jour donné est refusé ; le message nomme la personne, la feuille en conflit et le premier jour concerné.
- [ ] Quand une saisie plus lente ou une estimation révisée fait chevaucher deux feuilles d'une même personne au-delà de 100 %, les deux feuilles sont signalées « à replanifier » pour un lead et la direction, et pas pour un membre de prod.
- [ ] Désactiver un membre allonge la barre de ses feuilles et les signale « équipe à revoir » ; une personne désactivée n'est pas proposée comme membre.
- [ ] Quand un lot planifié reçoit son premier sous-lot, ce sous-lot reprend la date de début et l'équipe, et la barre est inchangée.
- [ ] La page Roadmap est accessible depuis la zone du haut du menu pour tous les rôles.
- [ ] Les projets sont listés par ordre alphabétique et se déplient en lots puis en sous-lots ; un projet et un lot découpé affichent une barre de cumul de leurs feuilles.
- [ ] La frise est en semaines, avec un repère « aujourd'hui », et affiche par défaut de 4 semaines avant à 36 semaines après la semaine en cours ; on navigue vers la période précédente ou suivante et on revient à la fenêtre par défaut.
- [ ] Une feuille à estimer, sans début ou sans équipe apparaît dans son projet sans barre, avec ce qui lui manque, et son projet est signalé « planning partiel ».

## Hors scope

- **Date annoncée, historique des annonces, jalons, clôture et livraison réelle** : relèvent de `jalons-dates-annoncees`, qui posera la date annoncée sur la même frise (principe 4).
- **North Star** : relève de `north-star`.
- **Absences, fermetures, temps de travail détaillé** : relèvent de `capacite-equipe`. D'ici là, les barres sont optimistes pendant les congés.
- **Dépendances entre feuilles, enchaînement ou décalage automatique en cascade** : non retenus. Chaque feuille se calcule seule, et le lead replanifie.
- **Part variable dans le temps** (une part différente selon la période) : non retenue. La part vaut pour toute la durée de la feuille.
- **Vue de charge par personne, semaine par semaine** : relève de `charge-vs-capacite`.
- **Consommé face à l'estimé détaillé et alerte de dérive** : relèvent de `consomme-vs-estime` et `alerte-derive`. La roadmap n'affiche que le restant, qui sert de base au calcul.
- **Affectation suggérée et simulation « et si »** : écarts non retenus par la vision.
- **Planification d'un lot découpé ou d'un projet** : exclue par la règle 1.
- **Modifier le planning depuis la frise** (glisser-déposer, redimensionnement) : non. La planification se fait sur la feuille.
- **Zoom en mois, export ou impression de la roadmap** : non.
- **Réalignement du backlog** (période prévue de C2.4, affectation de C4.4, roadmap de C7.1 sans date annoncée) : à faire via `/product-backlog`.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement.
- **Droits d'accès** : oui. La planification (date de début, équipe, parts) est réservée aux leads et à la direction. La page Roadmap est ouverte à toute personne connectée. Le signal « à replanifier » est réservé aux leads et à la direction. Les droits du responsable d'une feuille sont inchangés.
- **Cloisonnement des données** : non, il n'y a pas de multi-organisation. Côté principe 2, l'équipe et les parts sont visibles de tous, comme le responsable. La surcharge d'une personne ne se lit que par les leads et la direction. La partie réalisée d'une barre ne révèle jamais qui a saisi.
- **Apparence / déclinaisons** : les signaux affichés varient selon le rôle. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non. Ni l'ajout à une équipe ni une surcharge ne sont notifiés.
- **Données existantes** : oui. Chaque feuille qui a déjà un responsable le reçoit dans son équipe, à 100 %. Aucune feuille n'a de date de début, donc au lancement toutes apparaissent « sans début » et chaque projet « planning partiel ». Les données de démonstration doivent contenir des feuilles planifiées, dont au moins un démarrage en retard, une estimation atteinte, un dépassement et une surcharge passive.
- **Comportement par défaut** : une feuille nouvellement créée n'a pas de date de début, et son équipe se limite à son responsable s'il existe. Elle apparaît sur la roadmap sans barre, « sans début ».

## Questions ouvertes

- **Répartition d'un temps partiel dans la semaine** : une personne à 4 j/semaine apporte-t-elle sa capacité (a) répartie également sur ses jours ouvrés non fériés (0,8 j par jour), ou (b) en jours pleins à partir du lundi, jusqu'à épuisement de son maximum ? Le choix déplace la fin calculée de quelques jours et la charge jour par jour. → tranché : (a).
- **Pas de navigation de la frise** : Options : (a) une fenêtre entière (41 semaines), (b) 4 semaines. → tranché : (b), avec un retour « Aujourd'hui ».
- **Dépliage par défaut de la roadmap** : Options : (a) projets repliés (une barre par projet), (b) projets dépliés jusqu'aux feuilles, (c) dernier état retenu pour chaque personne. → tranché : (a).

---

## Annexe — Pistes pour le plan

- Équipe : association feuille ↔ personne portant la part (entité dédiée entre `Lot` et `User` avec un pourcentage en 25/50/75/100), et `Lot.startDate` nullable — à confirmer.
- Chronologie calculée à la lecture, jamais stockée, à partir de `TimeEntry`, `WeeklyMax` (via `WeeklyMaxManager::capFor()`), `HolidayManager` et de l'état actif des personnes — à confirmer, surveiller le coût du calcul jour par jour sur toutes les feuilles (SQLite).
- Refus de surcharge : contrainte de validation sur `LotInput`, dans l'esprit de `EstimateCoversConsumed` et `AddableHoliday`, qui calcule la charge future par personne et par jour avant/après le geste — à confirmer.
- Invariant « responsable ⇒ membre » et transfert de la date de début et de l'équipe lors des bascules : à porter par `ProjectManager` (déjà garant des règles 11 et 12 de la story 002) — à confirmer.
- Reprise des responsables existants dans les équipes : migration avec reprise de données — à confirmer.
- Frise : rendu serveur (grille CSS à colonnes-semaines), dépliage côté client (Stimulus ou `<details>`), fenêtre passée en paramètre d'URL ; un Live Component ne semble pas nécessaire — à confirmer.
- Menu : entrée `NavLink` « Roadmap » dans la zone du haut, section reconnue par `nav_section` — à confirmer.
- Signaux « à replanifier » conditionnés à `ROLE_LEAD` ; droits de planification alignés sur la branche lead/direction de `LotVoter` (sans la branche responsable) — à confirmer.
