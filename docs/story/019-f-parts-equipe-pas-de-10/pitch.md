# Affecter un collaborateur à une feuille par tranches de 10 % de sa capacité

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/019-f-parts-equipe-pas-de-10/`
> **Amont** : aucun

La part de capacité qu'un membre consacre à une feuille se choisit désormais par pas de 10 % (de 10 à 100 %) au lieu de 25, 50, 75 ou 100 %. Les leads et la direction composent ainsi des équipes qui reflètent la répartition réelle des personnes entre plusieurs feuilles, et les fins calculées gagnent en justesse.

## Contexte

Depuis la story `006-f-roadmap-planification`, chaque membre de l'équipe d'une feuille y consacre 25, 50, 75 ou 100 % de sa capacité. La fin calculée de la feuille et la charge de chaque personne en découlent, et un geste de planification qui porterait quelqu'un au-delà de 100 % un jour donné est refusé.

Le pas de 25 % est trop grossier. Une personne répartie sur trois feuilles ne peut pas y être posée à un tiers chacune : il faut l'affecter à 25 + 25 + 50 ou à 25 + 25 + 25, ce qui fausse les fins calculées ou laisse un quart de capacité qui n'existe pas dans la réalité. De même, une contribution d'appoint (relecture, recette, support à une autre équipe) pèse au minimum 25 %, ce qui surestime sa charge et bloque à tort d'autres affectations au nom de la surcharge. Les estimations des feuilles restent en jours entiers ou en quarts, assez grandes pour qu'une part fine ait du sens. Si rien ne change, les leads arrondissent les parts et la roadmap, comme la charge des personnes, s'écarte de ce qui se passe réellement.

## Alignement vision

- **Problème adressé** : le « planning intenable ». Une part plus fine rapproche la capacité engagée de la disponibilité réelle de chacun, donc la fin calculée de la date atteignable.
- **Audience servie** : leads et direction, quand ils composent les équipes des feuilles et arbitrent la charge.
- **Principes respectés** : le principe 1 est respecté, car la saisie quotidienne ne change pas. Elle reste en quarts de journée, et chacun y répartit son temps comme il l'entend, sans contrôle de cohérence avec ses parts. Le principe 2 est respecté : la part reste une désignation qui sert à planifier, pas une mesure. Le principe 3 est respecté : on planifie toujours à la feuille.
- **Hypothèse testée** : aucune hypothèse nouvelle.
- **Impact North Star** : indirect. Des fins calculées plus justes rendent les dates annoncées moins hasardeuses.

## Utilisateurs concernés

- **Lead** et **direction** : choisissent la part de chaque membre parmi 10, 20, …, 100 % lorsqu'ils composent l'équipe d'une feuille.
- **Responsable d'une feuille** : rejoint toujours l'équipe à 100 %, et sa part se modifie avec le même pas que celle des autres membres.
- **Équipe de prod** : lit des parts de 10 en 10 sur la roadmap et sur sa fiche. Sa saisie ne change pas.

## User Stories

- En tant que **lead ou direction**, je veux affecter une personne à une feuille avec une part de 10, 20, …, 90 ou 100 % de sa capacité, afin que la fin calculée reflète ce qu'elle y consacre réellement.
- En tant que **lead ou direction**, je veux répartir une personne sur plusieurs feuilles jusqu'à exactement 100 % avec des parts comme 30 + 30 + 40, afin de ne pas devoir arrondir sa répartition aux quarts.
- En tant que **lead ou direction**, je veux poser une contribution d'appoint à 10 ou 20 %, afin que sa charge ne soit pas surestimée et ne bloque pas à tort d'autres affectations.
- En tant que **membre de l'équipe de prod**, je veux continuer à saisir ma journée en quarts sans que mes parts m'imposent quoi que ce soit, afin que la saisie reste rapide.

## Règles métier

1. **Parts possibles** : un membre consacre à une feuille 10, 20, 30, 40, 50, 60, 70, 80, 90 ou 100 % de sa capacité. Aucune autre valeur n'est acceptée : 25 et 75 % ne sont plus proposés.
2. **Part par défaut** : 100 %, comme aujourd'hui. Un responsable désigné qui n'est pas encore dans l'équipe la rejoint à 100 %.
3. **Choix de la part** : la part se choisit dans une liste de ces dix valeurs, à l'endroit où elle se choisit aujourd'hui.
4. **Calcul inchangé** : un membre apporte chaque jour sa part de sa capacité, quelle que soit cette part. La fin calculée, la charge jour par jour et le refus de dépasser 100 % de charge s'appliquent sans changement de règle.
5. **Saisie inchangée** : la saisie reste en quarts de journée. Aucune règle ne relie ce qu'une personne saisit sur une feuille à sa part, et c'est à chacun de répartir ses quarts sur les jours.
6. **Fin au-delà de l'horizon** : une feuille dont le restant n'est pas couvert dans l'horizon de calcul affiche « fin inconnue », comme aujourd'hui. C'est plus fréquent avec de petites parts, et c'est voulu : le signal dit que l'équipe est sous-dimensionnée.

## Critères d'acceptation

- [x] Dans la composition de l'équipe d'une feuille, la part se choisit parmi 10, 20, 30, 40, 50, 60, 70, 80, 90 et 100 %, avec 100 % par défaut. 25 et 75 % ne sont plus proposés.
- [x] Une part hors de ces dix valeurs est refusée avec un message qui indique les valeurs possibles.
- [x] Un responsable désigné qui n'est pas dans l'équipe la rejoint à 100 %, et sa part peut ensuite être ramenée à 10 % par exemple.
- [x] Une personne affectée à 30, 30 et 40 % sur trois feuilles qui se chevauchent est acceptée. Passer l'une de ces parts à 50 % est refusé pour dépassement de 100 % de charge, et le message nomme la personne, la feuille en conflit et le premier jour concerné.
- [x] À estimation et équipe égales, une feuille dont l'unique membre passe de 50 à 10 % voit sa fin calculée reculer, et la part de 10 % s'affiche sur la roadmap et sur la fiche de la personne.
- [x] La saisie quotidienne est inchangée : on saisit toujours en quarts de journée, quelle que soit sa part sur la feuille.

## Hors scope

- **Part variable dans le temps** (une part différente selon la période, ligne `affectation-sous-projets` du backlog) : non traitée, la part vaut toujours pour toute la durée de la feuille.
- **Parts par pas de 5 %** ou valeurs libres : dix valeurs suffisent, et une liste plus longue alourdit le choix sans besoin exprimé.
- **Conservation de 25 et 75 %** : retirées pour garder une liste régulière.
- **Allongement de l'horizon de calcul** : « fin inconnue » reste le signal d'une feuille sous-dotée.
- **Modification de la saisie** (saisie en dixièmes de journée, rappel de la part dans la feuille de temps, contrôle saisie vs part) : la saisie reste en quarts et libre.

## Impacts transverses

- **Traduction / langues** : non, l'application est en français seul. Seul le message de refus d'une part invalide change.
- **Droits d'accès** : inchangés. Les leads et la direction composent les équipes, et tout le monde lit les parts.
- **Cloisonnement des données** : non concerné.
- **Apparence / déclinaisons** : non. La liste des parts compte dix options au lieu de quatre, et les parts s'affichent de la même façon sur la roadmap et sur la fiche des personnes.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune reprise. La base est réinitialisée avec les données de démonstration à chaque déploiement, et celles-ci n'utilisent que des parts de 50 et 100 %, déjà conformes au nouveau pas.
- **Comportement par défaut** : un membre ajouté sans choisir de part est à 100 %, comme aujourd'hui.

## Questions ouvertes

_(aucune)_

---

## Annexe — Pistes pour le plan

- La liste des parts est portée par une constante unique, `LotMember::SHARES` (`src/Entity/LotMember.php:15`), reprise par le formulaire (`src/Form/LotMemberType.php:37`) et la contrainte de `src/Dto/LotMemberInput.php:17`, dont le message cite « 25, 50, 75 ou 100 % » : à reformuler.
- Les annotations PHPStan `int<25, 100>` (`LotMember`, `LotMemberInput`, `LotInput`, `PlannedMember`, `DailyCapacity::units`, `ScheduleLoader`, `ProjectManager`, `PersonRoadmapBuilder`, `DemoCompanyFixtures`) sont à passer en `int<10, 100>`.
- `DailyCapacity` compte en 1/12000 de quart (`src/Model/Schedule/DailyCapacity.php:13`) : une part de 10 % reste un nombre entier d'unités, à confirmer par un test unitaire.
- Les fixtures de démonstration pourraient montrer une part de 30 ou 70 % (`fixtures/DemoCompanyFixtures.php:101-107`) pour rendre le nouveau pas visible en démo.
- Tests à revoir s'ils figent les quatre valeurs (choix proposés, message de validation, E2E de composition d'équipe).
