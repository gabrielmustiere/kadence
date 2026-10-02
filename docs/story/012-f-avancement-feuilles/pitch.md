# Déclarer l'avancement d'une feuille pour recaler sa fin calculée sur le rythme réellement observé

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/012-f-avancement-feuilles/`
> **Amont** : aucun

Un lead, la direction ou le responsable d'une feuille peut déclarer où en est le travail, en pourcentage. Kadence confronte cet avancement au temps déjà saisi pour en déduire ce que la feuille coûtera réellement. Le restant ainsi obtenu remplace `estimé − consommé` dans le calcul de la fin, qui continue de s'appuyer sur la capacité de l'équipe affectée. La fin calculée cesse de supposer que chaque jour saisi fait avancer le travail exactement comme prévu.

## Contexte

Depuis la story 006, la fin calculée d'une feuille répartit son restant sur la capacité de son équipe : nombre de personnes, part de chacune, maximum hebdomadaire et jours fériés. Ce restant, c'est toujours l'estimation moins le temps saisi. Le calcul suppose donc que le travail avance exactement au rythme de l'estimation. Une feuille de 20 j sur laquelle 10 j ont été saisis est réputée faite à moitié, même quand l'équipe sait qu'elle n'en est qu'à 40 %. La fin calculée est alors optimiste, et elle le reste jusqu'au jour où le consommé atteint l'estimation.

Ce jour-là, le calcul ne sait plus rien dire. Une feuille en dépassement n'a plus de fin calculée (« fin inconnue, estimation à réviser »). Sa fin inconnue contamine celle de son lot et de son projet, et le « libre à partir du … » de chaque membre de son équipe sur sa fiche. Le lead n'a qu'un recours : réviser l'estimation au jugé, sans que Kadence l'aide à chiffrer ce qui reste. Les dépassements continuent d'être découverts trop tard, et les dates affichées sur la roadmap restent des paris.

**Mesure de succès** : quatre semaines après la livraison, chaque feuille planifiée sur laquelle du temps a été saisi dans les deux dernières semaines porte un avancement déclaré depuis moins de deux semaines, et plus aucune feuille en dépassement n'affiche « fin inconnue » faute d'avancement.

## Alignement vision

- **Problème adressé** : direct sur deux symptômes. Pour les « dépassements découverts trop tard », le coût projeté à terme d'une feuille apparaît face à son estimation dès qu'un avancement trahit un rythme plus lent que prévu, bien avant que le consommé franchisse l'estimation. Pour la « roadmap opaque », les fins calculées tiennent compte du rythme observé, et une feuille en dépassement retrouve une fin.
- **Audience servie** : les leads et les responsables de feuille déclarent l'avancement, typiquement en revue hebdomadaire d'un projet. La direction lit des fins plus réalistes sur la roadmap et la fiche projet. L'équipe de prod les consulte.
- **Principes respectés** :
  - Principe 1 respecté : la saisie quotidienne n'est pas touchée, l'avancement est un geste de pilotage distinct de la saisie.
  - Principe 2 respecté : l'avancement porte sur une feuille, jamais sur une personne. L'auteur d'une déclaration est visible comme on voit un responsable, sans aucune mesure individuelle.
  - Principe 3 respecté : l'avancement se déclare au niveau de la feuille, jamais de la tâche.
  - Principe 4 renforcé indirectement : la date projetée qui sera confrontée à la date annoncée devient plus crédible.
- **Hypothèse testée** : H2 est sous tension assumée. Elle pariait que le rythme de consommation suffirait **sans reste à faire déclaré**, et prévoyait le repli sur un reste à faire déclaré par le responsable. Cette story pose ce repli, sous la forme d'un avancement, avant que H2 ait été tranchée. Elle le fait en coexistence : une feuille sans avancement garde le calcul actuel, ce qui permettra de comparer les deux. H2 est à reformuler par `/vision` au sync. _(Reformulée au sync : le repli par un avancement déclaré y est désormais nommé.)_ H3 est testée directement : les fins calculées à partir d'un avancement déclaré sont-elles plus proches de la réalité ?
- **Impact North Star** : indirect. Des fins calculées plus réalistes rendent plus crédibles les dates que la direction annoncera.

## Utilisateurs concernés

- **Lead** (gère tous les projets) : déclare l'avancement de toute feuille estimée, depuis la page de gestion du projet. Il voit la fin calculée, le coût projeté et les signaux bouger en conséquence.
- **Direction** : mêmes droits que les leads sur l'avancement. Elle lit des fins plus réalistes sur la roadmap et la fiche projet.
- **Responsable d'une feuille** (quel que soit son rôle) : déclare l'avancement de sa feuille, et d'aucune autre.
- **Prod** (non responsable) : consulte l'avancement, le coût projeté et l'historique des déclarations sur la fiche projet et dans les infobulles de la roadmap. Sa saisie quotidienne est inchangée.
- **Toute personne connectée** : voit la fin calculée et le « libre à partir du … » des fiches de personnes refléter les avancements déclarés.

## User Stories

- En tant que **lead ou direction**, je veux déclarer l'avancement en pourcentage d'une feuille afin que sa fin calculée tienne compte du rythme réellement observé, et pas seulement du temps saisi.
- En tant que **responsable d'une feuille**, je veux déclarer l'avancement de ma feuille afin que la roadmap reflète ce que je sais de son état réel.
- En tant que **lead**, je veux voir le coût projeté à terme d'une feuille face à son estimation afin de repérer une dérive avant que le consommé dépasse l'estimation.
- En tant que **lead**, je veux qu'une feuille en dépassement retrouve une fin calculée dès que j'en déclare l'avancement afin de ne plus laisser son projet en « fin inconnue ».
- En tant que **lead ou direction**, je veux qu'une feuille dont l'avancement déclaré est dépassé par le temps saisi depuis soit signalée « avancement à actualiser » afin de ne pas piloter sur une déclaration périmée.
- En tant que **personne connectée**, je veux lire l'historique des avancements déclarés d'une feuille (date, pourcentage, auteur) sur la fiche projet afin de voir si elle progresse ou stagne.
- En tant que **personne connectée**, je veux voir l'avancement d'une feuille dans son infobulle sur la roadmap afin de comprendre d'où vient sa fin calculée.
- En tant que **responsable d'une feuille**, je ne veux PAS pouvoir déclarer l'avancement d'une feuille dont je ne suis pas responsable, car son pilotage relève de son responsable et des leads.
- En tant que **membre de prod**, je ne veux PAS que ma saisie quotidienne me demande un avancement, car saisir sa journée doit prendre moins d'une minute.

## Règles métier

**Déclaration**

1. **Portée** : seule une feuille estimée (lot sans sous-lot, ou sous-lot) reçoit un avancement. Un lot découpé, un projet et une feuille « à estimer » n'en reçoivent pas. Une feuille dont on retire l'estimation (possible tant qu'aucun temps n'y est saisi) garde ses déclarations dans l'historique, mais n'a plus d'avancement en vigueur. _(Précision apportée à la review.)_
2. **Valeur** : un pourcentage de 0 à 100 par pas de 5 %. L'avancement est facultatif, et une feuille qui n'en a jamais reçu n'a pas d'avancement.
3. **Droits** : les leads et la direction déclarent l'avancement de toute feuille. Le responsable d'une feuille déclare celui de sa feuille. Personne d'autre ne le peut.
4. **Lieu** : la déclaration se fait sur la page de gestion du projet, feuille par feuille, à côté de l'estimation. La fiche projet reste en lecture seule (règle 4 de la story 010).
5. **Date** : une déclaration vaut à l'instant où elle est faite. On ne déclare pas un avancement à une date passée.
6. **Évolution** : un avancement peut monter comme baisser, par exemple quand on découvre du travail en plus.
7. **Historique** : chaque déclaration est conservée avec sa date, son pourcentage et son auteur. Une nouvelle déclaration le même jour sur la même feuille remplace la précédente dans l'historique ; si deux personnes déclarent au même moment, la seconde est refusée avec un message l'invitant à recharger la page. L'avancement en vigueur est la dernière déclaration. _(Précision apportée à la review.)_

**Restant d'une feuille**

8. **Sans avancement, ou à 0 %** : le restant reste l'estimation moins le temps saisi, comme aujourd'hui. Déclarer 0 % revient à retirer l'avancement.
9. **Extrapolation** : au moment de la déclaration, le restant est extrapolé du rythme observé, soit le temps saisi × (100 − avancement) / avancement. Avec 10 j saisis et un avancement de 40 %, la feuille coûtera 25 j au total et il reste 15 j.
10. **Sans temps saisi** : si aucun temps n'est saisi sur la feuille au moment de la déclaration, le restant vaut l'estimation × (100 − avancement) %. Avec 10 j estimés et 30 % déclarés, il reste 7 j.
11. **Ancrage** : le restant obtenu à la déclaration est figé. Tout temps saisi ensuite sur la feuille le diminue, quel que soit le jour concerné ou la personne qui le saisit, et une correction qui retire du temps l'augmente, sauf sur une feuille déclarée à 100 % (règle 16). Dans l'exemple de la règle 9, 3 j saisis après la déclaration laissent 12 j. _(Exception apportée à l'implémentation.)_
12. **Estimation révisée** : réviser l'estimation d'une feuille qui a un avancement ne change pas son restant. L'avancement fait foi. L'estimation reste déclarative et sert de référence au coût projeté.
13. **Arrondi** : le restant est arrondi au quart de journée supérieur.
14. **Chronologie** : ce restant alimente la fin calculée telle que la story 006 la définit (capacité de l'équipe, parts, maximum hebdomadaire, jours fériés, date de début). Le reste du calcul est inchangé.

**Cas particuliers**

15. **Dépassement** : une feuille dont le temps saisi dépasse l'estimation retrouve une fin calculée dès qu'elle a un avancement inférieur à 100 %. Elle reste signalée « en dépassement » avec son ampleur, et ses jours saisis au-delà de l'estimation restent en rouge.
16. **Terminée à 100 %** : une feuille déclarée à 100 % n'a plus de restant ni de partie future. Elle se termine au dernier jour saisi et est signalée « terminée à 100 % », sans alarme. Ce n'est pas une clôture : la saisie y reste possible, et un temps saisi ensuite prolonge sa partie réalisée. Une correction qui retire du temps ne lui rend aucun restant. Déclarer 100 % est refusé tant qu'aucun temps n'est saisi sur la feuille. _(Précisions apportées au plan et à l'implémentation.)_
17. **Avancement à actualiser** : quand le temps saisi depuis la déclaration épuise le restant ancré d'un avancement inférieur à 100 %, la feuille n'a plus de partie future. Sa fin est inconnue, et elle est signalée « avancement à actualiser » jusqu'à une nouvelle déclaration. Comme pour un dépassement, la fin de son lot et de son projet devient inconnue, ainsi que le « libre à partir du … » des membres de son équipe.
18. **Surcharge** : déclarer un avancement n'est pas un geste de planification. Il n'est jamais refusé. La surcharge qu'il provoque en repoussant une fin est une surcharge passive, signalée « à replanifier » (règle 20 de la story 006).
19. **Découpage d'un lot** : quand un lot qui a un avancement reçoit son premier sous-lot, ce sous-lot reprend son avancement et son historique, comme il reprend déjà sa date de début et son équipe (règle 28 de la story 006). La chronologie reste inchangée. À la bascule inverse, l'avancement remonte sur le lot.

**Affichage**

20. **Coût projeté** : pour une feuille qui a un avancement, le coût projeté à terme est le temps saisi plus le restant. Il s'affiche face à l'estimation, avec l'écart : « projeté 25 j pour 20 j estimés (+5 j) ». L'estimation n'est jamais modifiée par Kadence.
21. **Page de gestion du projet** : chaque feuille montre son avancement en vigueur et sa date de déclaration à côté de l'estimation, avec le moyen de le déclarer pour qui en a le droit.
22. **Fiche projet** : le tableau du consommé face à l'estimé montre l'avancement en vigueur de chaque feuille, avec sa date, et son coût projeté. Pour une feuille qui a un avancement, le restant affiché est celui issu de l'avancement, comme pour la fin calculée. Le dépassement reste le temps saisi au-delà de l'estimation, et les cumuls du lot et du projet additionnent ces restants (la règle 7 de la story 010 est amendée). L'historique des déclarations de chaque feuille est lisible dans une section dédiée, sous le tableau, de la plus récente à la plus ancienne. _(Précisions apportées au plan.)_
23. **Cumul** : un lot découpé et un projet affichent un avancement cumulé, moyenne des avancements de leurs feuilles pondérée par leur estimation. Une feuille sans avancement y compte pour son temps saisi divisé par son estimation, plafonné à 100 %. Une feuille « à estimer » n'y compte pas, et le cumul est alors marqué « partiel ». Le cumul est arrondi à l'entier. Exemple : un sous-lot de 20 j déclaré à 50 % et un sous-lot de 20 j sans avancement avec 6 j saisis donnent un lot à 40 %.
24. **Roadmap** : l'infobulle de la partie restante d'une feuille qui a un avancement montre l'avancement, sa date de déclaration et le coût projeté face à l'estimation. Les signaux « avancement à actualiser » et « terminée à 100 % » portent sur une feuille et sont visibles de tous, sur la roadmap comme sur la fiche projet.

## Critères d'acceptation

- [ ] Sur la page de gestion d'un projet, un lead déclare l'avancement d'une feuille estimée en choisissant un pourcentage de 0 à 100 par pas de 5 %.
- [ ] Un lot découpé, un projet et une feuille « à estimer » n'offrent aucun avancement à déclarer.
- [ ] La direction déclare l'avancement de toute feuille ; un membre de prod responsable d'une feuille déclare celui de sa feuille, et ne peut pas déclarer celui d'une feuille dont il n'est pas responsable.
- [ ] Une feuille de 20 j estimés, avec 10 j saisis et une personne à 5 j/semaine à 100 % dans son équipe, déclarée à 40 % : son restant passe de 10 j à 15 j, sa fin calculée recule de 5 jours ouvrés, et la fiche projet affiche un coût projeté de 25 j (+5 j) face aux 20 j estimés. _(Formulation précisée à la review.)_
- [ ] Dans cet exemple, 3 j saisis après la déclaration ramènent le restant à 12 j.
- [ ] Une feuille de 10 j estimés sans aucun temps saisi, déclarée à 30 %, a un restant de 7 j.
- [ ] Déclarer 0 % ramène le restant à l'estimation moins le temps saisi.
- [ ] Réviser l'estimation d'une feuille qui a un avancement ne déplace pas sa fin calculée.
- [ ] Une feuille de 10 j estimés avec 12 j saisis, en « fin inconnue », déclarée à 80 % : elle retrouve une fin calculée sur un restant de 3 j, reste signalée « en dépassement de 2 j », et la fin de son lot et de son projet n'est plus inconnue de son fait.
- [ ] Une feuille déclarée à 100 % n'a plus de partie future, se termine au dernier jour saisi et est signalée « terminée à 100 % » ; la saisie y reste possible.
- [ ] Déclarer 100 % sur une feuille sans aucun temps saisi est refusé avec un message.
- [ ] Sur la fiche projet, une feuille déclarée à 40 % avec 10 j saisis pour 20 j estimés affiche 15 j de restant, et le restant de son lot et de son projet en tient compte.
- [ ] Quand le temps saisi depuis la déclaration épuise le restant ancré, la feuille est signalée « avancement à actualiser » et sa fin, comme celle de son lot et de son projet, est inconnue ; une nouvelle déclaration lève le signal.
- [ ] Une déclaration qui repousse une fin au point de surcharger une personne est acceptée, et les feuilles concernées sont signalées « à replanifier » pour les leads et la direction.
- [ ] La fiche projet affiche, pour chaque feuille, son avancement en vigueur avec sa date, son coût projeté et l'historique de ses déclarations (date, pourcentage, auteur), de la plus récente à la plus ancienne ; deux déclarations le même jour n'y laissent que la dernière.
- [ ] La fiche projet reste en lecture seule : elle n'offre aucun moyen de déclarer un avancement.
- [ ] Un lot dont un sous-lot de 20 j est déclaré à 50 % et un autre de 20 j, sans avancement, a 6 j saisis affiche un avancement cumulé de 40 %.
- [ ] Sur la roadmap, l'infobulle de la partie restante d'une feuille qui a un avancement montre l'avancement, sa date et le coût projeté face à l'estimation.
- [ ] Quand un lot qui a un avancement reçoit son premier sous-lot, ce sous-lot reprend l'avancement et la barre est inchangée.
- [ ] La saisie quotidienne ne demande ni n'affiche aucun avancement.

## Hors scope

- **Alerte de dérive et liste des dérives** : relèvent de `alerte-derive`. Le coût projeté rend la dérive visible, mais aucune alerte n'est émise.
- **Clôture d'une feuille et livraison réelle** : relèvent de `jalons-dates-annoncees`. « Terminée à 100 % » n'est pas une clôture.
- **Révision automatique ou proposée de l'estimation** : non. L'estimation reste un geste humain, et le coût projeté n'est qu'affiché.
- **Avancement déclaré sur un lot découpé ou un projet** : exclu par la règle 1. Leur avancement n'est qu'un cumul.
- **Avancement saisi par l'équipe pendant la saisie quotidienne** : refusé au nom du principe 1.
- **Rappel ou notification d'actualisation** : non. Le signal « avancement à actualiser » suffit à ce stade.
- **Péremption d'un avancement après un délai** : non retenue. Seul l'épuisement du restant ancré rend une déclaration « à actualiser ».
- **Avancement à une date passée** : non. Une déclaration vaut à l'instant où elle est faite.
- **Coût projeté cumulé d'un lot découpé ou d'un projet** : non. Le coût projeté ne s'affiche que par feuille.
- **Remplissage de la barre selon l'avancement sur la frise** : non. La barre change d'elle-même par la fin calculée.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement.
- **Droits d'accès** : oui. Un nouveau geste est réservé aux leads, à la direction et au responsable de la feuille concernée. L'avancement, le coût projeté et l'historique sont visibles de toute personne connectée.
- **Cloisonnement des données** : non, il n'y a pas de multi-organisation. Côté principe 2, l'avancement porte sur une feuille. L'auteur d'une déclaration est visible comme un responsable, sans aucune mesure individuelle.
- **Apparence / déclinaisons** : le moyen de déclarer n'apparaît sur la page de gestion du projet que pour qui en a le droit. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non. Ni une déclaration ni le signal « avancement à actualiser » ne sont notifiés.
- **Données existantes** : aucune reprise. Au lancement, aucune feuille n'a d'avancement, et toutes les fins calculées restent identiques. Les données de démonstration doivent contenir des feuilles avec avancement, dont au moins une extrapolation qui recule la fin, une feuille en dépassement qui retrouve une fin, une feuille « terminée à 100 % », une feuille « avancement à actualiser » et un historique de plusieurs déclarations.
- **Comportement par défaut** : une feuille sans avancement se calcule exactement comme aujourd'hui.

## Questions ouvertes

- **Feuille déclarée à 100 % sans aucun temps saisi** : elle n'a ni partie réalisée ni partie future. Options : (a) l'accepter, la feuille apparaît sans barre et signalée « terminée à 100 % », (b) refuser 100 % tant qu'aucun temps n'est saisi. → tranché : (b).
- **Disposition de l'historique sur la fiche projet** : Options : (a) dépliable sous chaque feuille du tableau du consommé face à l'estimé, (b) section dédiée regroupant les déclarations de toutes les feuilles, (c) entrées mêlées à la timeline des tronçons. → tranché : (b).
- **Restant affiché sur la fiche projet pour une feuille avec avancement** : Options : (a) le restant issu de l'avancement, (b) l'estimation moins le temps saisi, avec l'avancement à côté. → tranché au plan : (a), règle 22 précisée.

---

## Annexe — Pistes pour le plan

- Historique des déclarations : entité dédiée rattachée à `Lot` (pourcentage, date, auteur, temps saisi en quarts au moment de la déclaration, restant ancré en quarts). L'avancement en vigueur est la dernière déclaration — à confirmer.
- Restant : aujourd'hui calculé dans `Scheduler::scheduleLeaf()` (`src/Service/Scheduler.php:38`) à partir de `LeafPlan::estimateQuarters` et `consumedQuarters`. `LeafPlan` pourrait porter le restant ancré et le consommé à la déclaration, chargés par `ScheduleLoader` — à confirmer.
- Signaux « terminée à 100 % » et « avancement à actualiser » : à côté de ceux de `LeafSchedule` et `RoadmapSignal::of()` — à confirmer.
- Droits : réutiliser la logique de `LOT_EDIT` de `LotVoter` (lead, direction, responsable), qui pilote déjà le bouton « Modifier » de `templates/project/_lot_row.html.twig` — à confirmer.
- Transfert à la bascule lot ↔ sous-lot : dans `ProjectManager`, qui transfère déjà date de début et équipe (règles 28 et 29 de la story 006) — à confirmer.
- Cumul pondéré : dans `ProjectRollup` / `LotSummary` / `ProjectSummary` ; affichage dans `templates/roadmap/_consumption_row.html.twig` et l'infobulle `templates/roadmap/_tooltip.html.twig` (partie `future`) — à confirmer.
