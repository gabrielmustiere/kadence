# Changelog

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Chaque version porte un **titre** et distingue les **évolutions fonctionnelles**
(perceptibles à l'usage) des **évolutions techniques** (internes, outillage, plomberie).

## [Unreleased]

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

[Unreleased]: https://github.com/gabrielmustiere/kadence/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/gabrielmustiere/kadence/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/gabrielmustiere/kadence/releases/tag/v0.1.0
