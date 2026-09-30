# Changelog

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Chaque version porte un **titre** et distingue les **évolutions fonctionnelles**
(perceptibles à l'usage) des **évolutions techniques** (internes, outillage, plomberie).

## [Unreleased]

## [0.5.0] - 2026-09-30 — Menu d'administration

### ✨ Fonctionnel
- **Section Administration** — Projets, Équipe et Jours fériés sont regroupés dans une section « Administration » en bas du menu, à part de Tableau de bord et Ma semaine. Chacun n'y voit que ce que son rôle autorise, et le découpage est le même sur téléphone.
- **Page courante signalée** — l'entrée du menu de la page affichée est mise en évidence, y compris sur la fiche d'un projet, d'un lot ou d'une personne.
- **Accueil réorganisé** — les raccourcis du quotidien d'abord, puis un bloc « Administration » ; la direction y trouve désormais un raccourci vers les jours fériés.

### 🔧 Technique
- **Menu factorisé** — les entrées du menu passent par un composant commun, et l'entrée active se déduit de la page affichée.

## [0.4.0] - 2026-09-30 — Jours fériés français et belges

### ✨ Fonctionnel
- **Jours fériés verrouillés** — dans « Ma semaine », un jour férié est grisé et nommé (« Férié · Toussaint ») : on n'y saisit plus de temps, et il n'est jamais signalé comme un oubli. Sur téléphone, l'onglet du jour indique « Férié ».
- **Semaine complète malgré un jour férié** — le maximum d'une semaine qui compte un jour férié ne dépasse plus ses jours ouvrés : 5 j et 4,5 j deviennent 4 j, et la semaine passe au complet une fois ces jours saisis.
- **France ou Belgique** — la direction choisit, sur la fiche de chaque personne, le calendrier de jours fériés qu'elle suit (France par défaut). La France compte 11 jours légaux et la Belgique 10, Pâques, Ascension et Pentecôte comprises, pour n'importe quelle année.
- **Page « Jours fériés »** — réservée à la direction, elle présente les deux calendriers année par année et signale les jours tombant un week-end. On peut y ajouter un jour (un jour de remplacement belge, par exemple), retirer un jour légal travaillé (comme le lundi de Pentecôte) et annuler un ajustement.

### 🔧 Technique
- **Extension PHP `calendar` requise** — elle sert au calcul de Pâques, et l'hébergement devra la fournir.
- **Schéma** — nouvelle table `holiday_adjustment` et calendrier par personne (France pour les comptes existants). Sur une base de dev existante, `make db-reset` régénère la démonstration, avec désormais deux personnes en Belgique et aucun temps sur les jours fériés.

## [0.3.0] - 2026-09-29 — Saisie quotidienne des temps

### ✨ Fonctionnel
- **Ma semaine** — après la connexion, chacun arrive sur la grille de sa semaine : les jours en colonnes, ses lots et sous-lots en lignes. Ceux de la semaine et de la précédente sont proposés d'office, et « Ajouter une ligne » retrouve n'importe quel lot ou sous-lot par son nom.
- **Un clic par case** — chaque case se saisit au quart de journée (¼, ½, ¾ ou 1 j) sur une barre de quatre crans qui se remplit au survol, comme une notation par étoiles. Le clic enregistre aussitôt, et un nouveau clic sur le cran actif remet la case à zéro.
- **Jamais plus d'une journée par jour** — les crans qui feraient dépasser 1 j sur un jour, ou le maximum de la semaine, sont grisés. Un enregistrement refusé, par exemple depuis un second onglet, est expliqué, et diminuer une case reste toujours possible. Les jours à venir sont verrouillés.
- **Journée complète en vert** — chaque jour affiche son total, et la semaine son total face au maximum (« 3 j / 4,5 j »). Une journée complète passe au vert, et un jour passé incomplet est signalé tant que la semaine n'a pas atteint son maximum.
- **De semaine en semaine** — les boutons semaine précédente, suivante et « Cette semaine » permettent de corriger une journée passée.
- **Sur téléphone** — la grille présente un jour à la fois, avec un sélecteur des jours.
- **Maximum hebdomadaire** — la direction fixe, sur la fiche de chaque personne, son maximum de saisie par semaine (5 j par défaut, 4,5 j pour un 90 %…) à partir d'une semaine choisie. L'historique des valeurs s'affiche sous la fiche, et une valeur saisie par erreur peut y être supprimée.
- **Temps protégés dans les projets** — un projet, un lot ou un sous-lot qui porte des temps ne peut plus être supprimé. Le premier sous-lot d'un lot reprend ses temps. L'estimation initiale reste consultable, et une révision ne peut pas descendre sous le temps déjà saisi.

### 🔧 Technique
- **Schéma de la saisie** — nouvelles tables `time_entry` et `weekly_max`, et estimation initiale sur les lots. Sur une base de dev existante, `make db-reset` est requis.
- **Entreprise de démonstration** — en développement, `make db-reset` charge une fausse entreprise : 4 projets découpés, 14 personnes dont des temps partiels, et six mois de saisie.
- **Fixtures sous analyse statique** — les fixtures sont désormais vérifiées par PHPStan.

## [0.2.0] - 2026-09-29 — Projets, lots et sous-lots

### ✨ Fonctionnel
- **Projets découpés en lots et sous-lots** — les leads et la direction créent des projets et les découpent en lots, eux-mêmes découpables en sous-lots ; un titre ne peut pas être utilisé deux fois au même niveau.
- **Estimation et responsable** — chaque lot non découpé ou sous-lot porte une estimation en jours entiers et un responsable ; les totaux des lots et des projets se calculent tout seuls.
- **Ce qu'il reste à compléter** — les parties « à estimer », « à désigner » ou « à redésigner » (responsable parti) et les projets « à découper » sont signalés ; un total incomplet est marqué « partiel ».
- **Découper sans rien perdre** — le premier sous-lot d'un lot reprend son estimation et son responsable, et supprimer le dernier sous-lot les fait remonter sur le lot.
- **Responsables autonomes** — le responsable d'un lot ou d'un sous-lot, quel que soit son rôle, en modifie le titre, la description et l'estimation.
- **Projets visibles par tous** — chacun consulte les projets ; le filtre « Mes responsabilités » retrouve ceux où l'on porte une partie.
- **Création plus rapide** — « Enregistrer et ajouter un autre » enchaîne les lots, et les formulaires occupent toute la largeur de l'écran.

### 🔧 Technique
- **Schéma projets et lots** — nouvelles tables `project` et `lot` ; `make db-reset` (ou `make migrate` puis `make fixtures`) charge les projets de démonstration.

## [0.1.0] - 2026-09-28 — Équipe, rôles et accès

### ✨ Fonctionnel
- **Gestion de l'équipe** — la direction inscrit chaque personne (prénom, nom, e-mail, rôle direction, lead ou prod), modifie sa fiche, la désactive à son départ ou la réactive, sans perdre son historique.
- **Mot de passe provisoire** — généré à l'inscription ou à la demande et affiché une seule fois à la direction ; la personne choisit le sien à sa première connexion.
- **Accès selon le rôle** — la gestion de l'équipe est réservée à la direction ; un compte désactivé ne peut plus se connecter et sa session en cours est coupée ; les tentatives de connexion répétées sont temporairement bloquées.
- **Mon compte** — chacun peut changer son mot de passe (12 caractères minimum).
- **Interface Kadence** — nouvelle page de connexion et accueil avec des raccourcis selon le rôle, entièrement en français.

### 🔧 Technique
- **Premier compte direction** — commande `app:create-director` pour l'installation.
- **Schéma utilisateur** — rôle unique, statut actif et marqueur de mot de passe provisoire ; `make db-reset` est requis sur une base de dev existante.
- **Connexion rapide en dev** — choix d'un compte de fixtures sur la page de connexion, uniquement en environnement de développement.
- **Nettoyage du template d'amorçage** — suppression de la page design system et de la route de test d'e-mail.

[Unreleased]: https://github.com/gabrielmustiere/kadence/compare/v0.5.0...HEAD
[0.5.0]: https://github.com/gabrielmustiere/kadence/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/gabrielmustiere/kadence/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/gabrielmustiere/kadence/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/gabrielmustiere/kadence/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/gabrielmustiere/kadence/releases/tag/v0.1.0
