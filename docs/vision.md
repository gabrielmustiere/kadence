# Vision — Kadence

> Pitch en une phrase : Kadence est l'outil interne de pilotage de production de notre éditeur logiciel (15 à 40 personnes), qui permet à la direction et aux leads de savoir à date ce que l'équipe peut encore absorber et quand chaque projet sera livré, à partir d'une saisie quotidienne des temps et d'estimations déclaratives par projet et sous-projet.

_Document vivant — enrichi au fil du cycle de vie, refondu lors d'un pivot stratégique. Date de dernière mise à jour : 2026-09-28._

## Changelog

Historique des évolutions structurantes (création, enrichissements, éditions ciblées, pivots). Lecture du haut vers le bas = ordre chronologique. Détails fins dans `git log`.

| Date | Nature | Axe | Motif |
|------|--------|-----|-------|
| 2026-09-28 | Création | — | Vision initiale |

## Le problème

Personne ne sait, à date, combien de charge l'équipe peut encore absorber ni où en sont réellement les projets : chaque date annoncée est un pari.

Les trois symptômes forment une chaîne causale :

1. **Dépassements découverts trop tard** — on constate qu'un projet a explosé son estimation quand il n'est plus temps de réagir.
2. **Planning intenable** — on s'engage sur des dates sans connaître la disponibilité réelle de l'équipe.
3. **Roadmap opaque** — personne ne peut répondre de façon fiable à « c'est pour quand ? ».

**Comment c'est résolu aujourd'hui** : un tableur (Excel / Google Sheets) alimenté et consolidé à la main.
**Pourquoi c'est insuffisant** : la consolidation est manuelle et en retard, le lien estimé / réalisé n'est pas calculé, la capacité réelle (congés, temps partiels) n'est pas croisée avec la charge, et aucune date n'est projetée — elles sont seulement annoncées.
**Ampleur** : 15 à 40 personnes, plusieurs projets produit en parallèle ; la vision d'ensemble ne tient plus dans une tête.

## L'audience

L'équipe travaille essentiellement sur l'évolution de nos propres logiciels (roadmap produit, maintenance, support), pas sur des projets clients.

### Utilisateurs principaux

Deux usages distincts, portés par deux rôles :

- **Direction** — arbitre les priorités et s'engage sur des dates. A besoin de lire la roadmap (dates annoncées vs projetées) et de voir les surcharges à venir avant de dire oui.
- **Leads / chefs de projet** — pilotent au quotidien. Déclarent les estimations des projets et sous-projets, suivent le consommé, réagissent aux alertes de dérive.
- **Volume cible** : quelques personnes (direction + leads) sur un effectif de 15 à 40.
- **Ce qui les bloque aujourd'hui** : l'information existe en morceaux dans le tableur, mais personne n'a la vue consolidée à date sans refaire le calcul à la main.

### Utilisateurs secondaires

- **Équipe de prod** — saisit ses temps chaque jour ; consulte la roadmap pour savoir sur quoi elle sera dans les semaines à venir.

### Hors cible explicite

- **Clients et utilisateurs externes** — pas de portail ni de roadmap publique.
- **Commerciaux et support** — pas d'usage dédié ; ils passent par la direction.

## La proposition de valeur

### Bénéfice utilisateur

- **Direction** : répondre à « c'est pour quand ? » en lisant une page, pas en convoquant une réunion ; voir la surcharge des semaines à venir **avant** de s'engager.
- **Leads** : voir une dérive quand le rythme de consommation s'emballe, pas quand le budget est déjà dépassé.
- **Tous** : supprimer la consolidation manuelle du tableur.

### Pourquoi nous, plutôt qu'eux

Les outils du marché (Harvest, Toggl, Productive, Teamwork…) couplent le suivi des temps à la gestion de tâches, à la facturation ou au pilotage de projets clients. Kadence fait le choix inverse : **pas de tâches, pas d'argent**, uniquement la chaîne estimation → saisie → capacité → date projetée, au niveau projet / sous-projet, calibrée pour un éditeur qui pilote sa propre roadmap.

### Unfair advantage

Outil interne sur mesure : un seul client, nous. Il peut être totalement opinionated sur nos rituels, sans onboarding, sans multi-entreprise, et évoluer au rythme de nos propres irritants.

## Métriques de succès

### North Star

**% des jalons de roadmap livrés à la date annoncée (± 1 semaine).**

Mesure directement le problème : les dates annoncées sont-elles encore des paris ? Se calcule dès qu'on historise, pour chaque jalon, la date annoncée et la date de livraison réelle.

### Métriques secondaires

- **Activation (saisie)** : % de jours ouvrés saisis dans les 48 h, par l'ensemble de l'équipe.
- **Qualité d'estimation** : écart moyen estimé / réalisé par sous-projet terminé.
- **Détection précoce** : délai entre la première alerte de rythme de consommation et le dépassement effectif du budget.
- **Rétention** : le tableur n'est plus maintenu ; la direction lit les dates dans l'outil.
- **Monétisation** : sans objet (outil interne).

### Seuils

- **À 3 mois** : baseline de la North Star mesurée ; saisie ≥ 90 % des jours ouvrés dans les 48 h ; tableur abandonné.
- **À 1 an** : North Star ≥ baseline + 30 points.
- **À 3 ans** : sans seuil fixé — l'outil est un instrument interne, pas un produit en croissance.

### Signal d'arrêt

À 3 mois, un seul de ces signaux suffit à remettre l'outil en cause :

- saisie non tenue (< 90 % des jours ouvrés saisis dans les 48 h après le premier mois) ;
- tableur toujours maintenu en parallèle parce que l'outil ne suffit pas ;
- direction qui continue de demander les dates à l'oral au lieu de les lire dans l'outil.

## Principes produit

1. **Saisir sa journée prend moins d'une minute** — toute feature qui alourdit la saisie quotidienne est refusée, quelle que soit la valeur de la donnée supplémentaire. Toute la chaîne repose sur une saisie fiable et fraîche.
2. **On pilote des projets et une capacité, jamais des personnes** — la charge individuelle sert à planifier (qui est disponible quand), jamais à comparer, classer ou évaluer. Aucune vue de productivité individuelle. C'est la condition d'une saisie honnête.
3. **Pas de granularité sous le sous-projet** — l'estimation est déclarative, au niveau projet ou sous-projet. Un besoin qui exige de descendre à la tâche est hors périmètre.
4. **Une date annoncée s'affiche toujours à côté de sa date projetée** — l'écart est visible, jamais masqué. L'outil ne remplace pas l'engagement humain, il le confronte au calcul.

## Anti-objectifs

Ce qu'on **refuse explicitement** de faire, et pourquoi :

- **Gestion de tâches** — l'outil ne gère ni tâches ni tickets ; il ne concurrence pas un outil de tickets et reste au niveau projet / sous-projet.
- **Évaluation individuelle** — aucune mesure de productivité par personne (cf. principe 2).
- **Facturation, devis, comptabilité** — on pilote du temps et des dates, pas de l'argent.
- **Accès clients / roadmap publique** — la roadmap est interne (direction + prod).
- **Intégrations au départ** (forge, SIRH, agenda…) — saisie native d'abord ; une intégration ne se fait qu'en réponse à un irritant prouvé.
- **Application mobile native** — le web responsive suffit à la saisie quotidienne.

## Hypothèses critiques

| # | Hypothèse | Comment l'invalider | Statut |
|---|-----------|---------------------|--------|
| 1 | 15 à 40 personnes tiennent une saisie quotidienne ≥ 90 % si elle prend moins d'une minute | Mesurer le taux de saisie à 48 h dès le premier mois d'usage | À tester |
| 2 | Le rythme de consommation (consommé vs estimé, rapporté à l'avancement dans le temps) suffit à détecter les dépassements assez tôt, sans reste à faire ni estimation révisable | Sur 3 mois, comparer la date de première alerte à la date de dépassement effectif ; si l'alerte arrive trop tard, repli sur une estimation révisable (l'initiale conservée) | À tester |
| 3 | Des estimations déclaratives au niveau sous-projet sont assez fines pour projeter des dates de fin crédibles | Comparer dates projetées et dates réelles sur les premiers jalons livrés | À tester |
| 4 | La baseline de la North Star est mesurable sur 3 mois (assez de jalons livrés pour être significative) | Compter les jalons échus sur la période ; si trop peu, allonger la fenêtre de baseline | À tester |

## Risques externes

- **Cadre social et données personnelles** : un outil de suivi des temps des salariés traite des données personnelles et peut être perçu comme un dispositif de contrôle de l'activité. Information préalable des salariés et, selon l'effectif, consultation du CSE à prévoir avant déploiement ; le principe 2 doit être explicite dans la communication. Mitigation : cadrer le déploiement avec la direction / les RH avant le lancement.
- **Rejet par l'équipe** : si la saisie est perçue comme du flicage ou une corvée, l'hypothèse 1 tombe et toute la chaîne avec. Mitigation : principes 1 et 2, affichés et tenus.

## Horizons

### 3-6 mois

- **À 3 mois** : saisie quotidienne des temps en place, projets et sous-projets estimés, consommé vs estimé, alerte sur le rythme de consommation. Le tableur est abandonné. Baseline de la North Star mesurée.
- **À 6 mois** : charge vs capacité semaine par semaine (congés, temps partiels inclus), date de fin projetée par projet, roadmap interne affichant dates annoncées et dates projetées.

### 1 an

North Star ≥ baseline + 30 points, calculée sur un historique réel de jalons. Hypothèses 1 à 4 tranchées.

### 3 ans

Pas d'ambition au-delà de l'usage interne. Une éventuelle commercialisation à d'autres éditeurs serait un changement d'audience et de modèle : elle passerait par un `/vision` en mode Pivot, pas par un enrichissement.

## Notes pour les features à venir

Pointeurs bruts pour `/product-backlog` et `/feature-pitch` — **ne pas concevoir ici** :

- Saisie quotidienne des temps (< 1 min) sur projets / sous-projets.
- Référentiel projets / sous-projets avec estimation déclarative.
- Consommé vs estimé et alerte de rythme de consommation.
- Capacité de l'équipe : congés, temps partiels, disponibilités.
- Charge vs capacité semaine par semaine.
- Date de fin projetée par projet.
- Roadmap interne : jalons, date annoncée vs date projetée, historique pour la North Star.
- Suivi du taux de saisie (relances ?) — sans dériver vers l'évaluation individuelle.
- Rôles et droits : direction, leads, équipe.
- Écarts : simulation « et si » et affectation suggérée évoquées mais non retenues à ce stade.
