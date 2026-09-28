# Product Backlog — Kadence

> Carte des capacités fonctionnelles et backlog priorisé dérivé de `docs/vision.md`.

_Document vivant — enrichi/édité au fil du cycle de vie, refondu lors d'un pivot. Date de dernière mise à jour : 2026-09-28._

## Changelog

Historique des évolutions structurantes (création, enrichissements, éditions ciblées, pivots). Lecture chronologique. Détails fins dans `git log`.

| Date | Nature | Éléments | Motif |
|------|--------|----------|-------|
| 2026-09-28 | Création | — | Backlog initial dérivé de la vision |

## Domaines fonctionnels

| # | Domaine | Résumé en une ligne |
|---|---------|---------------------|
| D1 | Équipe & accès | Qui utilise l'outil et avec quel rôle (direction, lead, prod) |
| D2 | Projets & estimations | Projets, sous-projets, responsable, estimation déclarative, dates prévues et annoncées |
| D3 | Saisie des temps | Déclaration quotidienne du temps passé et discipline de saisie |
| D4 | Capacité | Temps de travail réellement disponible et sa répartition sur les sous-projets |
| D5 | Suivi de consommation | Consommé vs estimé et détection des dérives de rythme |
| D6 | Prévision | Charge vs capacité dans le temps et date de fin projetée |
| D7 | Roadmap | Jalons, dates annoncées vs projetées, tenue des engagements |

## Capacités

### D1 — Équipe & accès

- **C1.1** — La direction peut inscrire une personne et lui attribuer un rôle (direction, lead, prod).
- **C1.2** — La direction peut désactiver une personne qui quitte l'équipe, en conservant son historique.
- **C1.3** — Une personne peut se connecter et ne voit que ce que son rôle autorise.

### D2 — Projets & estimations

- **C2.1** — Un lead peut créer un projet et en désigner le responsable.
- **C2.2** — Un lead peut découper un projet en sous-projets.
- **C2.3** — Le responsable peut déclarer l'estimation d'un sous-projet en jours.
- **C2.4** — Le responsable peut poser la période prévue d'un sous-projet et, le cas échéant, une date annoncée (ce qui en fait un jalon).
- **C2.5** — Le responsable peut clôturer un sous-projet, ce qui enregistre sa date de livraison réelle.
- **C2.6** — Un lead peut archiver un projet terminé pour le retirer de la saisie.

### D3 — Saisie des temps

- **C3.1** — Un membre de l'équipe peut déclarer sa journée en répartissant ses demi-journées sur des sous-projets.
- **C3.2** — Un membre peut retrouver ses sous-projets habituels en tête de liste (pour saisir en moins d'une minute).
- **C3.3** — Un membre peut corriger une journée déjà saisie.
- **C3.4** — Le système peut rappeler à une personne, et à elle seule, qu'elle n'a pas saisi sa journée.
- **C3.5** — La direction peut consulter le taux de saisie de l'équipe (global, jamais par personne).

### D4 — Capacité

- **C4.1** — La direction peut définir le temps de travail de chaque personne (temps plein, temps partiel, jours travaillés).
- **C4.2** — Une personne peut déclarer ses absences (congés, maladie).
- **C4.3** — La direction peut déclarer les jours fériés et les fermetures.
- **C4.4** — Un lead peut affecter une part de la capacité d'une personne à un sous-projet sur une période (pour planifier la charge et projeter les dates).

### D5 — Suivi de consommation

- **C5.1** — Un lead peut consulter le consommé face à l'estimé de chaque sous-projet et de chaque projet.
- **C5.2** — Le système peut alerter le responsable quand le rythme de consommation d'un sous-projet dépasse le rythme prévu sur sa période.
- **C5.3** — La direction peut consulter la liste des sous-projets en dérive.
- **C5.4** — La direction peut consulter l'écart estimé / réalisé des sous-projets terminés (pour mesurer la qualité d'estimation).

### D6 — Prévision

- **C6.1** — La direction et les leads peuvent consulter la charge face à la capacité de l'équipe, semaine par semaine.
- **C6.2** — Le système peut calculer la date de fin projetée d'un sous-projet à partir de son restant (estimé − consommé) et de la capacité qui lui est affectée.
- **C6.3** — Un lead peut repérer les semaines où une personne est en surcharge, pour replanifier.

### D7 — Roadmap

- **C7.1** — Tout le monde peut consulter la roadmap : les jalons à venir, avec leur date annoncée et leur date projetée côte à côte.
- **C7.2** — La direction peut modifier une date annoncée ; l'historique des annonces est conservé.
- **C7.3** — La direction peut consulter la North Star (% de jalons livrés à ± 1 semaine de leur première date annoncée) sur une période.

## Parcours utilisateurs principaux

### P1 — Saisir sa journée

- **Acteur** : membre de l'équipe de prod (utilisateur secondaire de la vision, dont dépend toute la chaîne).
- **Déclencheur** : fin de journée, ou rappel de saisie manquante.
- **Étapes** : C3.2 → C3.1 (→ C3.3 en cas d'oubli ou d'erreur).
- **État final** : la journée est répartie en demi-journées sur des sous-projets.
- **Fréquence** : 1 fois par jour et par personne (15 à 40 saisies par jour).

### P2 — Lancer un projet

- **Acteur** : lead.
- **Déclencheur** : décision de démarrer un projet.
- **Étapes** : C2.1 → C2.2 → C2.3 → C2.4 → C4.4 → C6.2.
- **État final** : le projet est découpé, estimé, affecté ; ses dates de fin projetées sont visibles.
- **Fréquence** : quelques fois par mois.

### P3 — Réagir à une dérive

- **Acteur** : lead (et direction si une date doit bouger).
- **Déclencheur** : alerte de rythme de consommation (C5.2).
- **Étapes** : C5.2 → C5.1 → C4.4 (réaffecter) et/ou C7.2 (réannoncer).
- **État final** : la dérive est traitée ou assumée, et la date annoncée reflète la réalité.
- **Fréquence** : hebdomadaire.

### P4 — S'engager sur une date

- **Acteur** : direction.
- **Déclencheur** : question « c'est pour quand ? » ou nouvelle demande à arbitrer.
- **Étapes** : C6.1 → C6.2 → C2.4 / C7.2 → C7.1.
- **État final** : une date est annoncée en connaissance de la charge et de la projection, et elle s'affiche à côté de la date projetée.
- **Fréquence** : hebdomadaire à mensuelle.

### P5 — Revue de pilotage

- **Acteur** : direction.
- **Déclencheur** : chaque semaine.
- **Étapes** : C7.1 → C5.3 → C6.1 → C3.5.
- **État final** : les arbitrages sont pris sur des données à jour, sans consolidation manuelle.
- **Fréquence** : hebdomadaire.

### P6 — Ajuster la capacité

- **Acteur** : membre de l'équipe ou direction.
- **Déclencheur** : congé posé, temps partiel, arrivée ou départ.
- **Étapes** : C1.1 / C1.2 → C4.1 / C4.2 / C4.3 → C6.1.
- **État final** : la capacité réelle est à jour et la charge recalculée.
- **Fréquence** : plusieurs fois par mois.

### P7 — Livrer un jalon

- **Acteur** : responsable du sous-projet.
- **Déclencheur** : sous-projet terminé.
- **Étapes** : C2.5 → C5.4 → C7.3.
- **État final** : la livraison réelle est enregistrée, l'écart mesuré, la North Star alimentée.
- **Fréquence** : plusieurs fois par mois.

## Règles métier transverses

### Permissions et rôles

- **Direction** : tous les droits (équipe, projets, capacité, dates annoncées, indicateurs).
- **Lead** : gère tous les projets, sous-projets et affectations (pas seulement les siens — petite structure).
- **Prod** : gère sa propre saisie et ses absences, consulte la roadmap.
- **Pas de lecture individuelle** (principe 2 de la vision) : le consommé n'est visible qu'agrégé par sous-projet ou projet ; le détail des temps d'une personne n'est visible que par elle-même ; la charge individuelle n'apparaît que dans les vues de planification ; le taux de saisie n'est jamais présenté par personne.

### Workflows et états

- Un sous-projet passe par les états **prévu → en cours → clôturé**. Un projet peut être **archivé** quand tous ses sous-projets sont clôturés.
- On ne peut plus saisir de temps sur un sous-projet clôturé ni sur un projet archivé.

### Contraintes de gestion

- **Jalon** : seul un sous-projet portant une date annoncée est un jalon. Un sous-projet sans date annoncée (ex. support, maintenance) sert à la saisie, au rythme de consommation et à la charge, mais ne compte pas dans la North Star.
- **Période prévue ≠ date annoncée** : la période prévue (début, fin) sert au rythme de consommation et à la planification ; la date annoncée est un engagement.
- **Historisation des annonces** : une date annoncée n'est jamais écrasée ; chaque réannonce est conservée.
- **Référence North Star** : un jalon est « tenu » s'il est livré à ± 1 semaine de sa **première** date annoncée. Réannoncer ne rattrape pas un jalon.
- **Estimation déclarative et figée** : l'estimation d'un sous-projet n'est pas révisée (hypothèse H2 de la vision) ; le restant = estimé − consommé.
- **Hors projet** : support, maintenance, réunions, formation sont des projets comme les autres, estimés par période.

### Exigences réglementaires

- Les temps saisis sont des **données personnelles** : durée de conservation à fixer, information préalable des salariés, et consultation du CSE selon l'effectif, avant tout déploiement.

### Conventions transverses

- Estimations en **jours** ; saisie en **demi-journées**.
- Semaine de travail du lundi au vendredi ; fuseau Europe/Paris.
- Interface en français uniquement.

## Backlog priorisé

> Les cases sont **dérivées** de `docs/story/*/metadata.json` (champ `backlog` + `delivery`) — ne les coche pas à la main, la prochaine passe les recalculerait. Une case est cochée quand la story est **livrée** (`delivery.commit` renseigné) ; les états intermédiaires se lisent sur la ligne « Story ».

### MVP — Lancement initial (abandon du tableur, tests H1/H2, baseline North Star) · `0/7 livrées`

- [ ] `acces-roles` — Permettre à la direction d'inscrire l'équipe avec un rôle, pour que chacun ne voie que ce qui le concerne.
  - Pas encore cadrée
  - C1.1, C1.2, C1.3 · P6 · dép. — · Vision : audience (direction, leads, prod), principe 2
- [ ] `projets-sous-projets` — Permettre à un lead de créer projets et sous-projets estimés en jours, pour disposer d'un référentiel sur lequel saisir.
  - Pas encore cadrée
  - C2.1, C2.2, C2.3 · P2 · dép. `acces-roles` · Vision : principe 3, horizon 3 mois
- [ ] `jalons-dates-annoncees` — Permettre au responsable de poser la période prévue, la date annoncée (historisée) et la livraison réelle d'un sous-projet, pour mesurer la North Star dès le départ.
  - Pas encore cadrée
  - C2.4, C2.5, C7.2 · P4, P7 · dép. `projets-sous-projets` · Vision : North Star (baseline à 3 mois), principe 4
- [ ] `saisie-quotidienne` — Permettre à chacun de saisir sa journée en demi-journées, sous-projets habituels en tête, en moins d'une minute.
  - Pas encore cadrée
  - C3.1, C3.2, C3.3 · P1 · dép. `projets-sous-projets` · Vision : principe 1, hypothèse H1
- [ ] `rappel-saisie` — Rappeler personnellement une saisie manquante et montrer à la direction le taux de saisie de l'équipe, pour tenir la discipline de saisie.
  - Pas encore cadrée
  - C3.4, C3.5 · P1, P5 · dép. `saisie-quotidienne` · Vision : hypothèse H1, signal d'arrêt « saisie non tenue », principe 2
- [ ] `consomme-vs-estime` — Permettre à un lead de voir le consommé face à l'estimé par sous-projet et par projet, pour savoir où en est chaque projet sans tableur.
  - Pas encore cadrée
  - C5.1 · P3 · dép. `saisie-quotidienne` · Vision : problème (où en sont les projets), signal d'arrêt « tableur toujours là »
- [ ] `alerte-derive` — Alerter le responsable quand un sous-projet consomme plus vite que prévu sur sa période, et lister les dérives pour la direction.
  - Pas encore cadrée
  - C5.2, C5.3 · P3, P5 · dép. `consomme-vs-estime`, `jalons-dates-annoncees` · Vision : irritant « dépassements tardifs », hypothèse H2

### V2 — Court terme post-lancement (planifier, rendre la roadmap lisible) · `0/7 livrées`

- [ ] `capacite-equipe` — Permettre de déclarer temps de travail, temps partiels, absences, jours fériés et fermetures, pour connaître la capacité réelle.
  - Pas encore cadrée
  - C4.1, C4.2, C4.3 · P6 · dép. `acces-roles` · Vision : irritant « planning intenable », horizon 6 mois
- [ ] `affectation-sous-projets` — Permettre à un lead d'affecter une part de la capacité d'une personne à un sous-projet sur une période.
  - Pas encore cadrée
  - C4.4 · P2, P3 · dép. `capacite-equipe`, `projets-sous-projets` · Vision : irritant « planning intenable », horizon 6 mois
- [ ] `charge-vs-capacite` — Permettre à la direction et aux leads de voir, semaine par semaine, la charge face à la capacité et de repérer les surcharges avant de s'engager.
  - Pas encore cadrée
  - C6.1, C6.3 · P4, P5, P6 · dép. `affectation-sous-projets` · Vision : valeur direction (voir la surcharge avant de s'engager), principe 2
- [ ] `date-fin-projetee` — Calculer la date de fin projetée de chaque sous-projet à partir de son restant et de la capacité affectée.
  - Pas encore cadrée
  - C6.2 · P2, P4 · dép. `affectation-sous-projets`, `consomme-vs-estime` · Vision : hypothèse H3, horizon 6 mois
- [ ] `roadmap-interne` — Permettre à tous de consulter les jalons à venir avec date annoncée et date projetée côte à côte, pour répondre à « c'est pour quand ? » en lisant une page.
  - Pas encore cadrée
  - C7.1 · P4, P5 · dép. `date-fin-projetee`, `jalons-dates-annoncees` · Vision : irritant « roadmap opaque », principe 4, signal d'arrêt « dates toujours demandées »
- [ ] `north-star` — Permettre à la direction de consulter le % de jalons livrés à ± 1 semaine de leur première date annoncée.
  - Pas encore cadrée
  - C7.3 · P7 · dép. `jalons-dates-annoncees` · Vision : North Star, seuil à 1 an (baseline + 30 pts)
- [ ] `archivage-projets` — Permettre à un lead d'archiver un projet terminé pour alléger la liste de saisie.
  - Pas encore cadrée
  - C2.6 · P2 · dép. `projets-sous-projets` · Vision : principe 1

### V3 — Long terme · `0/2 livrées`

- [ ] `qualite-estimation` — Permettre à la direction de consulter l'écart estimé / réalisé des sous-projets terminés, pour mieux estimer les suivants.
  - Pas encore cadrée
  - C5.4 · P7 · dép. `jalons-dates-annoncees`, `consomme-vs-estime` · Vision : métrique secondaire « qualité d'estimation »
- [ ] `purge-donnees-temps` — Purger automatiquement les temps saisis au-delà de la durée de conservation définie.
  - Pas encore cadrée
  - — (règle transverse « données personnelles ») · — · dép. `saisie-quotidienne` · Vision : risque externe « cadre social et données personnelles »

## Couverture

_(dérivé — recalculé à chaque passe, ne pas maintenir à la main)_

### Capacités par horizon

- **MVP** — livrées : — · planifiées : C1.1, C1.2, C1.3, C2.1, C2.2, C2.3, C2.4, C2.5, C3.1, C3.2, C3.3, C3.4, C3.5, C5.1, C5.2, C5.3, C7.2
- **V2** — livrées : — · planifiées : C2.6, C4.1, C4.2, C4.3, C4.4, C6.1, C6.2, C6.3, C7.1, C7.3
- **V3** — livrées : — · planifiées : C5.4

### Capacités non couvertes (à challenger)

- Aucune.

### Parcours supportés

- **P1 — Saisir sa journée** : entièrement supporté en MVP.
- **P2 — Lancer un projet** : partiellement supporté en MVP (C4.4 et C6.2 en V2).
- **P3 — Réagir à une dérive** : partiellement supporté en MVP (réaffectation C4.4 en V2 ; réannonce C7.2 disponible).
- **P4 — S'engager sur une date** : partiellement supporté en MVP (C6.1, C6.2, C7.1 en V2 ; annonce C2.4/C7.2 disponible).
- **P5 — Revue de pilotage** : partiellement supporté en MVP (C7.1 et C6.1 en V2 ; dérives C5.3 et taux de saisie C3.5 disponibles).
- **P6 — Ajuster la capacité** : partiellement supporté en MVP (C1.1/C1.2 disponibles ; C4.1–C4.3 et C6.1 en V2).
- **P7 — Livrer un jalon** : partiellement supporté en MVP (clôture C2.5 disponible ; C7.3 en V2, C5.4 en V3).

## Notes pour `/feature-pitch`

- `saisie-quotidienne` : le « moins d'une minute » est un critère d'acceptation à rendre mesurable ; penser à la saisie d'une journée passée (C3.3) sans alourdir le cas nominal. Web responsive (anti-objectif : pas d'app mobile native).
- `rappel-saisie` : canal du rappel à définir (e-mail, notification…) sans intégration externe au départ ; le taux affiché doit rester global (principe 2).
- `jalons-dates-annoncees` : distinguer clairement période prévue et date annoncée ; la première date annoncée fait foi pour la North Star.
- `alerte-derive` : définir le « rythme prévu » (estimation étalée sur la période prévue ?) et le seuil d'alerte ; l'efficacité de l'alerte est exactement ce que teste H2.
- `acces-roles` : mode de connexion à trancher (anti-objectif : pas d'intégration au départ — un SSO en serait une).
- Avant déploiement du MVP : cadrage social et données personnelles (information des salariés, CSE selon l'effectif) — hors outil, mais bloquant pour le lancement.
- Écarts non retenus à ce stade (vision) : simulation « et si », affectation suggérée.
