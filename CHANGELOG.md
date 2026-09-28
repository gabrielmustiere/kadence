# Changelog

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Chaque version porte un **titre** et distingue les **évolutions fonctionnelles**
(perceptibles à l'usage) des **évolutions techniques** (internes, outillage, plomberie).

## [Unreleased]

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

[Unreleased]: https://github.com/gabrielmustiere/kadence/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/gabrielmustiere/kadence/releases/tag/v0.1.0
