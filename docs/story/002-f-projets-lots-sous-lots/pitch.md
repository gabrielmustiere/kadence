# Découper chaque projet en lots et sous-lots estimés, chacun confié à un responsable

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/002-f-projets-lots-sous-lots/`
> **Amont** : aucun

Les leads et la direction créent les projets dans Kadence et les découpent en lots, eux-mêmes découpables en sous-lots. Chaque élément le plus fin de ce découpage porte une estimation en jours et un responsable. Le projet n'est qu'une enveloppe qui cumule ses lots. C'est le référentiel sur lequel l'équipe saisira ses temps et sur lequel se mesureront le consommé, les dérives et les dates projetées.

## Contexte

Aujourd'hui, Kadence ne connaît que des personnes et leurs rôles. Il n'a aucune notion de projet. Le référentiel des projets, leur découpage et leurs estimations vivent dans le tableur, maintenu à la main.

Sans ce référentiel, rien de la chaîne de la vision ne peut démarrer : l'équipe n'a rien sur quoi saisir ses temps, le consommé n'a pas d'estimé auquel se comparer, et aucune date ne peut être projetée. C'est la première brique métier du MVP, et la condition de l'abandon du tableur.

Un découpage à deux niveaux ne suffit pas : certains lots sont trop gros pour être estimés d'un bloc, et le lead a besoin de les découper en sous-lots pour estimer de façon crédible. L'estimation vit donc toujours au niveau le plus fin du découpage : le sous-lot, ou le lot quand il n'est pas découpé. Les niveaux supérieurs n'en sont que le cumul.

**Mesure de succès** : au lancement, tous les projets en cours du tableur sont repris dans Kadence, avec 100 % des feuilles estimées et dotées d'un responsable. Un lead crée un projet découpé en trois lots, estimés et dotés d'un responsable, en moins de cinq minutes.

## Alignement vision

- **Problème adressé** : indirect. La feature pose l'estimé de référence sans lequel le symptôme « dépassements découverts trop tard » ne peut pas être détecté, et elle supprime une partie de la consolidation manuelle du tableur.
- **Audience servie** : les leads en premier lieu (ils découpent, estiment et désignent les responsables) et la direction (mêmes droits). L'équipe de prod consulte le référentiel et tient l'estimation des feuilles dont elle est responsable.
- **Principes respectés** : le principe 3 a été réécrit le 2026-09-29 pour cette story. Il dit désormais « pas de granularité sous le sous-lot, jamais de tâche » : on accepte un niveau de plus, et un seul. Le principe 1 est sous tension, car plus il y a de feuilles, plus la liste de saisie s'allonge ; c'est la story `saisie-quotidienne` (lots et sous-lots habituels en tête) qui devra tenir la minute. Le principe 2 est respecté : le responsable est une désignation sur un projet, pas un indicateur individuel, et aucune donnée d'activité n'est affichée.
- **Hypothèse testée** : H3 (des estimations déclaratives au niveau le plus fin suffisent à projeter des dates crédibles), dont cette feature pose la matière. Elle prépare aussi H2 en conservant l'estimation initiale quand une révision deviendra possible après saisie.
- **Impact North Star** : indirect. Les jalons, et donc la North Star, seront portés par les feuilles de ce découpage.

## Utilisateurs concernés

- **Lead** (gère tous les projets, pas seulement les siens) : crée, découpe, estime, désigne les responsables, modifie et supprime projets, lots et sous-lots.
- **Direction** : mêmes droits que les leads sur les projets.
- **Prod** : consulte tous les projets en lecture. Lorsqu'il est désigné responsable d'une feuille, il peut en modifier le titre, la description et l'estimation.
- **Personne désactivée** : ne peut plus être désignée responsable. Les feuilles dont elle était responsable sont signalées pour être redésignées.

## User Stories

- En tant que **lead ou direction**, je veux créer un projet avec un titre et, si besoin, une description afin de disposer d'une enveloppe à découper.
- En tant que **lead ou direction**, je veux découper un projet en lots afin de structurer ce qui sera livré.
- En tant que **lead ou direction**, je veux découper un lot en sous-lots afin d'estimer séparément les parties d'un lot trop gros pour être estimé d'un bloc.
- En tant que **lead ou direction**, je veux déclarer l'estimation en jours d'une feuille, ou la laisser « à estimer », afin de découper d'abord et d'estimer ensuite.
- En tant que **lead ou direction**, je veux désigner le responsable d'une feuille parmi les personnes actives, ou le laisser « à désigner », afin que chaque morceau de travail ait un porteur identifié.
- En tant que **lead ou direction**, je veux qu'un lot estimé qui reçoit son premier sous-lot lui transmette son estimation et son responsable afin de ne rien perdre en affinant le découpage.
- En tant que **lead ou direction**, je veux voir pour chaque projet et chaque lot le total estimé, et repérer les feuilles « à estimer », « à désigner » ou « à redésigner », afin de savoir ce qu'il reste à compléter.
- En tant que **lead ou direction**, je veux modifier le titre et la description d'un projet, d'un lot ou d'un sous-lot afin de corriger ou préciser le référentiel.
- En tant que **lead ou direction**, je veux supprimer un projet, un lot ou un sous-lot créé par erreur, tant qu'aucun temps n'y est saisi, afin de garder un référentiel propre.
- En tant que **responsable d'une feuille** (quel que soit mon rôle), je veux modifier son titre, sa description et son estimation afin de la tenir à jour sans passer par un lead.
- En tant que **membre de prod**, je veux consulter tous les projets, leurs lots, sous-lots, estimations et responsables afin de savoir sur quoi l'équipe travaille et de quoi je suis responsable.
- En tant que **membre de prod**, je ne veux PAS pouvoir modifier une feuille dont je ne suis pas responsable, ni créer ou supprimer quoi que ce soit, car la structure des projets relève des leads.

## Règles métier

1. **Trois niveaux, pas plus** : un projet contient des lots, un lot peut contenir des sous-lots, un sous-lot ne contient rien (principe 3 de la vision).
2. **Feuille** : on appelle feuille un lot sans sous-lot, ou un sous-lot. Seule une feuille porte une estimation et un responsable.
3. **Projet = enveloppe** : un projet ne porte ni estimation ni responsable. Son estimation affichée est la somme des estimations de ses feuilles.
4. **Lot découpé** : un lot qui a des sous-lots ne porte ni estimation ni responsable. Son estimation affichée est la somme de ses sous-lots.
5. **Titre et description** : pour les trois niveaux, le titre est obligatoire et la description facultative.
6. **Unicité des titres** : un titre de projet est unique dans l'outil, un titre de lot est unique dans son projet, un titre de sous-lot est unique dans son lot, sans tenir compte des majuscules.
7. **Estimation** : exprimée en jours entiers, strictement positive.
8. **« À estimer »** : une feuille peut exister sans estimation. Elle est signalée « à estimer », et tout total (lot, projet) qui l'inclut est signalé comme partiel.
9. **Responsable** : toute personne active, quel que soit son rôle, peut être désignée responsable d'une feuille. Une feuille peut exister sans responsable ; elle est alors signalée « à désigner ».
10. **Responsable désactivé** : une personne désactivée ne peut pas être désignée. Une feuille dont le responsable est désactivé conserve son nom affiché mais est signalée « à redésigner ».
11. **Bascule vers un lot découpé** : quand un lot reçoit son premier sous-lot, ce sous-lot reprend l'estimation et le responsable du lot (ou leur absence), et le lot n'en porte plus. Le total du lot est inchangé.
12. **Bascule inverse** : quand on supprime le dernier sous-lot d'un lot, l'estimation et le responsable de ce sous-lot remontent sur le lot, qui redevient une feuille.
13. **Droits de gestion** : les leads et la direction créent, modifient et suppriment projets, lots et sous-lots, et désignent les responsables, sur tous les projets.
14. **Droits du responsable** : le responsable d'une feuille, quel que soit son rôle, peut modifier le titre, la description et l'estimation de cette feuille, et rien d'autre. Il ne peut ni créer, ni supprimer, ni changer le responsable, ni modifier le lot ou le projet parent.
15. **Consultation** : toute personne connectée consulte tous les projets, lots et sous-lots, avec leurs estimations et leurs responsables.
16. **Estimation avant saisie** : tant qu'aucun temps n'est saisi sur une feuille, son estimation se modifie librement, sans historique.
17. **Estimation après saisie** (règle posée ici, appliquée quand la saisie existera) : une fois des temps saisis sur une feuille, son estimation peut encore être révisée, mais jamais en dessous du temps déjà consommé. L'estimation en vigueur au premier temps saisi est conservée comme estimation initiale et reste consultable.
18. **Suppression** : un projet, un lot ou un sous-lot peut être supprimé tant qu'aucun temps n'est saisi sur lui ni sur ses descendants.
19. **Pas de déplacement** : un sous-lot reste dans son lot, un lot dans son projet. L'ordre d'affichage ne se modifie pas à la main.

## Critères d'acceptation

- [ ] Un lead crée un projet avec un titre et, s'il le souhaite, une description.
- [ ] Un lead ajoute des lots à un projet et des sous-lots à un lot ; rien ne peut être ajouté sous un sous-lot.
- [ ] Un lead déclare l'estimation d'une feuille en jours entiers ; une valeur nulle, négative ou décimale est refusée.
- [ ] Un projet et un lot découpé en sous-lots n'offrent ni estimation ni responsable à renseigner, et affichent la somme des estimations de leurs feuilles.
- [ ] Une feuille sans estimation apparaît « à estimer », et le total du lot et du projet qui la contiennent est signalé comme partiel.
- [ ] Une feuille sans responsable apparaît « à désigner » ; une feuille dont le responsable a été désactivé apparaît « à redésigner ».
- [ ] Seules des personnes actives, tous rôles confondus, sont proposées comme responsable.
- [ ] Quand un lead ajoute le premier sous-lot à un lot estimé, ce sous-lot reprend l'estimation et le responsable du lot, et le total du lot est inchangé.
- [ ] Quand un lead supprime le dernier sous-lot d'un lot, l'estimation et le responsable de ce sous-lot remontent sur le lot.
- [ ] La création ou le renommage d'un projet, d'un lot ou d'un sous-lot avec un titre déjà utilisé au même niveau est refusé, quelle que soit la casse.
- [ ] Un lead ou la direction modifie ou supprime n'importe quel projet, lot ou sous-lot, y compris ceux dont il n'est pas responsable.
- [ ] Un membre de prod responsable d'une feuille modifie son titre, sa description et son estimation ; il ne peut ni créer, ni supprimer, ni changer le responsable, ni modifier une autre feuille, un lot découpé ou un projet.
- [ ] Un membre de prod consulte tous les projets, lots et sous-lots avec leurs estimations et leurs responsables, sans aucune action de modification hors de ses propres feuilles.
- [ ] Un lead crée un projet découpé en trois lots, estimés et dotés d'un responsable, en moins de cinq minutes.

## Hors scope

- **Période prévue, date annoncée, jalons, états et clôture** : relèvent de `jalons-dates-annoncees`.
- **Saisie des temps** : relève de `saisie-quotidienne`, qui appliquera aussi le gel et la révision bornée de l'estimation (règle 17) et le blocage des suppressions (règle 18).
- **Consommé face à l'estimé** : relève de `consomme-vs-estime`.
- **Archivage d'un projet terminé** : relève de `archivage-projets` (V2).
- **Affectation de capacité aux feuilles** : relève de `affectation-sous-projets` (V2).
- **Déplacement d'un lot ou d'un sous-lot, réordonnancement manuel** : non retenus.
- **Niveau sous le sous-lot** : exclu par le principe 3 de la vision.
- **Historique des estimations avant saisie** : seule l'estimation en vigueur au premier temps saisi est conservée (règle 17).
- **Import depuis le tableur** : la reprise se fait par ressaisie ; anti-objectif « pas d'intégration au départ ».
- **Réalignement du vocabulaire « lot / sous-lot » dans le backlog et le reste de la vision** : à faire via `/product-backlog`.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement (convention du backlog), avec le vocabulaire projet, lot et sous-lot.
- **Droits d'accès** : oui. La gestion des projets est réservée aux leads et à la direction ; la consultation est ouverte à toute personne connectée. Pour la première fois, un droit de modification dépend d'une désignation et non du rôle : le responsable d'une feuille, même en prod, peut la modifier.
- **Cloisonnement des données** : non. Il n'y a pas de multi-organisation et tout le monde voit tous les projets. Le nom du responsable est visible de tous : c'est une désignation, pas une donnée d'activité (principe 2).
- **Apparence / déclinaisons** : les actions de modification affichées varient selon le rôle et selon que la personne est responsable de la feuille. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non. La désignation comme responsable n'est pas notifiée.
- **Données existantes** : aucun projet n'existe encore dans l'outil ; la reprise des projets en cours du tableur se fait à la main au lancement. Les données de démonstration doivent contenir quelques projets découpés.
- **Comportement par défaut** : un projet nouvellement créé est vide ; une feuille nouvellement créée est « à estimer » et « à désigner » tant qu'on ne renseigne rien.

## Questions ouvertes

- **Suppression d'un projet ou d'un lot qui a des enfants** : Options : (a) elle emporte ses descendants après confirmation, (b) elle est refusée tant qu'il reste des enfants. → tranché : (a), la confirmation annonce ce qui sera supprimé avec.
- **Projet sans lot** : Options : (a) permis et signalé « à découper », (b) au moins un lot exigé à la création du projet. → tranché : (a).
- **Ordre d'affichage** des projets, lots et sous-lots : Options : (a) ordre de création, (b) ordre alphabétique. → tranché : projets par ordre alphabétique, lots et sous-lots par ordre de création.
- **Retrouver ses feuilles** : comment un responsable retrouve-t-il les feuilles dont il a la charge ? Options : (a) un filtre « mes responsabilités » dans la liste des projets, (b) rien de spécifique au MVP. → tranché : (a), et ses feuilles sont mises en évidence sur la page du projet.
- **Bascule d'un lot qui a déjà des temps saisis** (à trancher avec `saisie-quotidienne`) : Options : (a) les temps passent sur le premier sous-lot avec l'estimation, (b) la bascule est interdite dès qu'un temps est saisi sur le lot.
- **Notification de désignation** : Options : (a) aucune, la personne le découvre dans l'outil, (b) un e-mail à la personne désignée. → tranché : (a).

---

## Annexe — Pistes pour le plan

- Arbre à profondeur fixe : deux ou trois structures distinctes (projet, lot, sous-lot) plutôt qu'une structure auto-référencée générique, puisque la profondeur est bornée à trois — à confirmer.
- Estimation : piste d'un stockage en nombre entier pour éviter les décimaux — retenue au plan en jours entiers.
- Totaux (lot découpé, projet) calculés à la lecture plutôt que stockés, pour ne jamais diverger des feuilles — à confirmer.
- Responsable : relation vers la personne existante, filtrée sur les comptes actifs à la désignation — à confirmer.
- Droit « responsable de la feuille OU lead » : contrôle d'accès au niveau de l'objet (voter) plutôt qu'une règle d'URL par rôle ; la règle actuelle qui ouvre tout le site aux personnes connectées couvre déjà la consultation — à confirmer.
- Bascule et remontée d'estimation/responsable (règles 11 et 12) : logique métier à porter par un service dédié, à l'image du gestionnaire d'équipe existant — à confirmer.
