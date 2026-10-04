# Changelog

Toutes les modifications notables de ce projet sont documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Chaque version porte un **titre** et distingue les **évolutions fonctionnelles**
(perceptibles à l'usage) des **évolutions techniques** (internes, outillage, plomberie).

## [Unreleased]

## [0.14.0] - 2026-10-04 — Nouvelle interface & favoris de saisie

### ✨ Fonctionnel
- **Feuilles favorites dans « Ma semaine »** — une étoile sur chaque ligne de la grille et sur chaque résultat de « Ajouter une ligne » met une feuille en favori, ou l'en retire. Les favoris restent en tête de la grille chaque semaine, même sans temps saisi récemment : au retour de congés, plus besoin de les rechercher. Quand un lot favori est découpé en sous-lots, le favori passe au premier sous-lot.
- **Nouvelle identité visuelle** — l'interface adopte le design system « Cadence » : nouvelle typographie, encre bleu-noir, bleu du stylo pour le temps saisi, rouge pour le dépassement, hachures pour ce qui est calculé, et un mode sombre complet.
- **Pages revues** — la fiche d'un projet, la page de gestion d'un projet et la fiche d'une personne sont réorganisées, le tableau de bord et la page de connexion revus ; une règle graduée montre d'un coup d'œil le saisi, le restant et le dépassement de chaque lot et de chaque projet.
- **Accessibilité** — navigation latérale lisible par les lecteurs d'écran, lien d'évitement vers le contenu, meilleurs contrastes en mode sombre, focus toujours visible.

### 🔧 Technique
- **Favoris en base** — nouvelle table des favoris (migration à appliquer) ; les favoris suivent le découpage et la suppression des lots, et la page de saisie ne fait qu'une requête de plus, quel que soit leur nombre.
- **Composants d'interface partagés** — en-tête de page, tableaux, états vides, indicateurs, avatars, règle graduée et thème de formulaire communs à toutes les pages.
- **Démo en ligne** — `deploy.sh` publie `main` sur kadence.mustiere.fr dans une image FrankenPHP, en recréant la base et ses données de démonstration à chaque déploiement.

## [0.13.1] - 2026-10-02 — Cases cochées & socle consolidé

### ✨ Fonctionnel
- **Cases à cocher et boutons radio** — une case cochée ou un bouton radio sélectionné s'affiche de nouveau clairement, aux couleurs de l'application, dans tous les formulaires.

### 🔧 Technique
- **Intégrité des données garantie par la base** — la base refuse d'effacer une feuille ou un projet sur lesquels du temps vient d'être saisi, au lieu de laisser des saisies orphelines ; les migrations restent possibles sur une base remplie.
- **Règles des lots portées par l'entité** — la profondeur des sous-lots, leur rattachement au bon projet et le gel de l'estimation initiale ne sont plus dispersés dans les services.
- **Dépendances entre couches assainies** — le modèle ne dépend plus d'aucun service, et services et validateurs ne dépendent plus les uns des autres.
- **Résumé de projet chargé par un seul service** — les contrôleurs ne l'assemblent plus eux-mêmes, et l'historique d'avancement s'y lit sans requête supplémentaire.
- **Lisibilité et typage** — planification de toutes les feuilles en un appel, arguments nommés pour les plans de feuille, `declare(strict_types=1)` imposé à tout fichier PHP.

## [0.13.0] - 2026-10-02 — Avancement des feuilles

### ✨ Fonctionnel
- **Déclarer l'avancement d'une feuille** — sur la page d'un projet, un lead, la direction ou le responsable d'une feuille estimée déclare où en est le travail, de 0 à 100 % par pas de 5 %.
- **Fin calculée sur le rythme réel** — le temps restant d'une feuille est déduit du temps déjà saisi et de l'avancement déclaré, puis diminue au fil de la saisie. La roadmap, la fiche d'un projet et celle d'une personne en tiennent compte. Sans avancement déclaré, rien ne change.
- **Une fin pour les feuilles en dépassement** — une feuille qui a dépassé son estimation retrouve une fin calculée dès que son avancement est déclaré, et son lot et son projet aussi.
- **Coût projeté et historique sur la fiche d'un projet** — on y voit l'avancement de chaque feuille, son coût projeté face à l'estimation (« 25 j (+5 j) »), l'avancement cumulé des lots et du projet, et l'historique des déclarations (date, pourcentage, auteur).
- **Nouveaux signaux** — « terminée à 100 % », et « avancement à actualiser » quand le temps saisi depuis la déclaration a épuisé le restant. L'infobulle de la roadmap montre l'avancement et le coût projeté.

### 🔧 Technique
- **Historique des déclarations et dépassement séparé du restant** — chaque déclaration fige le temps saisi et le restant au jour où elle est faite, et le calcul de la chronologie distingue désormais le temps restant du dépassement de l'estimation.

## [0.12.0] - 2026-10-02 — Fiche d'une personne

### ✨ Fonctionnel
- **Fiche d'une personne** — chaque personne a une fiche consultable par tous : on l'ouvre en cliquant sur un nom dans la timeline de la fiche d'un projet, par « Ma fiche » dans le menu du compte, ou depuis la liste Équipe.
- **Temps saisi par projet** — la fiche résume le temps que la personne a saisi sur chaque projet, du plus récent au plus ancien.
- **Frise de la personne** — une ligne par feuille où elle a saisi ou dont elle fait partie de l'équipe, avec ses seuls blocs de travail et ses affectations à venir marquées de sa part.
- **Charge et disponibilité** — la direction, les leads et la personne elle-même voient sa charge jour par jour, les jours au-delà de 100 %, sa charge au prochain jour ouvré et la date à partir de laquelle elle est libre (« inconnue » tant qu'une de ses feuilles n'a pas de fin calculée), ainsi que son rôle et ses tags ; la direction et la personne voient aussi son manager.
- **À venir et journal** — la timeline liste ses affectations à venir, puis tous ses blocs de travail du plus récent au plus ancien, mois par mois.

### 🔧 Technique
- **Briques partagées et droits par donnée** — signaux des feuilles, découpage des tronçons et partiels de la frise sont partagés entre la roadmap, la fiche d'un projet et celle d'une personne ; un voter dédié décide de ce que chacun voit, et la charge n'est calculée que pour un lecteur autorisé.

## [0.11.0] - 2026-10-02 — Fiche d'un projet

### ✨ Fonctionnel
- **Fiche d'un projet** — sur la roadmap, une icône dans la colonne du titre de chaque projet ouvre sa fiche, consultable par tous. Un lien ramène à la même semaine de la roadmap.
- **Consommé face à l'estimé** — la fiche résume l'estimé, le saisi, le restant, le dépassement, le début et la fin calculée du projet, puis les détaille lot par lot. Le dépassement d'une feuille n'est jamais compensé par le restant d'une autre.
- **Frise de tout le projet** — la frise couvre le projet du premier au dernier jour connu. Un projet plus long que la roadmap élargit la frise, qui défile, et chaque mois garde son libellé. Elle s'ouvre à ×1, sans toucher au zoom de la roadmap.
- **Timeline des blocs de travail** — tous les tronçons du projet, du plus récent au plus ancien et mois par mois, avec la feuille, la période, le temps saisi et ce que chacun y a saisi, y compris sur une feuille pas encore planifiée.

### 🔧 Technique
- **Découpage des tronçons** — sorti dans un service dédié, partagé par la roadmap et la fiche.

## [0.10.0] - 2026-10-01 — Interruptions et zoom de la roadmap

### ✨ Fonctionnel
- **Interruptions visibles sur la roadmap** — la partie saisie d'une feuille est coupée en tronçons sur chaque jour ouvré sans saisie : on voit quand une feuille a été mise en pause. Un week-end ou un jour férié de toute l'équipe ne coupe pas la barre, et la légende explique le vide.
- **Une infobulle par tronçon** — survoler un tronçon affiche sa période, le temps saisi, le nombre de jours avec saisie et ce que chacun y a saisi. Une seule infobulle s'affiche à la fois.
- **Récapitulatif d'une feuille** — survoler son titre, ou y placer le focus au clavier, affiche ses dates, son estimé, son saisi, son restant ou son dépassement, sa période saisie et son équipe, même pour une feuille pas encore planifiée.
- **Zoom de la frise** — les boutons « − », « + » et « 100 % » élargissent la frise jusqu'à huit fois pour lire une période dense. Le zoom garde la date au centre et reste en mémoire pendant la session.
- **Libellés des mois** — le libellé d'un mois coupé par le bord gauche de la frise ne chevauche plus celui du mois suivant.

### 🔧 Technique
- **Infobulles de la roadmap** — un contrôleur Stimulus remplace Flowbite sur la frise et n'ouvre qu'une infobulle à la fois.

## [0.9.0] - 2026-10-01 — Écrans plus lisibles

### ✨ Fonctionnel
- **Formulaires découpés en sections** — les fiches personne, lot et projet regroupent leurs champs en sections titrées, chacune avec une phrase d'aide. Les choix exclusifs, comme le rôle ou le calendrier de jours fériés, tiennent sur une ligne.
- **Liste Équipe allégée** — nom et e-mail réunis, nom cliquable vers la fiche, compétences et expériences sur deux lignes distinctes ; seul un badge « Désactivée » signale les comptes inactifs. Les actions tiennent sur une ligne en icônes, avec une infobulle au survol.
- **Ajouter une ligne depuis une fenêtre** — dans « Ma semaine », le bouton « Ajouter une ligne » au-dessus de la grille ouvre la recherche ; choisir un projet, lot ou sous-lot ajoute la ligne et referme la fenêtre.

## [0.8.0] - 2026-10-01 — Tags et hiérarchie de l'équipe

### ✨ Fonctionnel
- **Écran Tags** — dans l'administration, la direction tient le vocabulaire qui décrit l'équipe : compétences techniques, expériences fonctionnelles et types d'équipe. Elle les ajoute, les renomme, et les supprime après une confirmation qui annonce combien de personnes les portent.
- **Profil d'une personne** — à l'inscription comme à la modification, la direction coche les compétences, les expériences et le type d'équipe de la personne, ou en saisit de nouveaux, qui rejoignent la liste.
- **Manager** — chaque personne peut avoir un manager, à titre informatif. Une personne qui manage encore des personnes actives ne peut pas être désactivée, et le message nomme ces personnes.
- **Liste Équipe enrichie** — elle affiche le type d'équipe, les tags et le manager de chacun, et se filtre par tag et par manager.
- **Composer une équipe par compétence** — en composant l'équipe d'un lot, les leads voient les tags de chaque personne et peuvent ne garder que celles qui portent les tags choisis.
- **Mon profil** — chacun voit ses propres tags et son manager sur sa page Mon compte.
- **Temps saisi par personne dans la roadmap** — les bulles des tronçons réalisé et en dépassement indiquent le temps saisi par chacun, avec sa part dans l'équipe ou la mention « hors équipe ».
- **Listes déroulantes** — leur flèche ne colle plus à la bordure.

### 🔧 Technique
- **Schéma** — nouvelles tables `tag` et `user_tag`, et manager sur les personnes. Sur une base de dev existante, `make db-reset` régénère une démonstration avec tags et hiérarchie.
- **Base de test partagée** — les tests tournent sur la base de dev (`var/data.db`). `make phpunit` recharge les fixtures avant et après, et la démonstration est aussi chargée en test.

## [0.7.0] - 2026-09-30 — Roadmap plus lisible

### ✨ Fonctionnel
- **Détails au survol** — la colonne de gauche de la roadmap ne garde que le titre et les signaux. Survoler un tronçon de la frise ouvre une bulle avec sa période exacte, la quantité saisie, restante ou dépassée rapportée à l'estimation, et l'équipe avec la part de chacun.
- **Dépassement en pourcentage** — le badge « en dépassement » et la bulle du tronçon rouge indiquent l'écart par rapport à l'estimation (150 j saisis pour 100 j estimés : +50 %).
- **Projets dépliés conservés** — un projet déplié le reste quand on avance, recule ou revient à aujourd'hui dans la frise.
- **Fin du saisi exacte** — pour un lot en dépassement, la partie saisie dans l'estimation s'arrête sur son dernier jour saisi, et non plus la veille du dépassement.

## [0.6.0] - 2026-09-30 — Planification et roadmap

### ✨ Fonctionnel
- **Page Roadmap** — une entrée « Roadmap » dans le menu montre, projet par projet, la chronologie des lots et sous-lots sur une frise de 41 semaines : ce qui a été saisi, puis ce qui reste, réparti sur la capacité de l'équipe. Les projets se déplient, on avance ou recule de 4 semaines, et « Aujourd'hui » ramène à la semaine en cours.
- **Date de début et équipe** — les leads et la direction posent sur chaque lot non découpé ou sous-lot une date de début et une équipe, où chacun consacre 25, 50, 75 ou 100 % de sa capacité. Le responsable fait toujours partie de l'équipe.
- **Fin calculée** — la fin de chaque lot se calcule sur le restant, le maximum hebdomadaire et les jours fériés de chacun. Elle se recale sur ce qui est saisi : une semaine moins remplie repousse la fin.
- **Surcharge refusée ou signalée** — une planification qui chargerait une personne au-delà de 100 % un jour donné est refusée, avec le lot en conflit. Une surcharge qui apparaît d'elle-même est signalée « à replanifier » aux leads et à la direction.
- **Signaux de la roadmap** — démarrage en retard, estimation atteinte, dépassement (avec son ampleur, et les jours saisis au-delà de l'estimation en rouge), équipe à revoir, lots non planifiés et planning partiel.
- **Grille de saisie** — les colonnes de jours de « Ma semaine » sont séparées par des pointillés.

### 🔧 Technique
- **Schéma** — nouvelle table `lot_member` et date de début sur les lots. La migration place chaque responsable existant dans l'équipe de son lot, à 100 %. Sur une base de dev existante, `make db-reset` régénère une démonstration planifiée.
- **Chronologie calculée à la volée** — elle n'est jamais stockée et se recalcule à chaque affichage, en un nombre constant de requêtes.

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

[Unreleased]: https://github.com/gabrielmustiere/kadence/compare/v0.14.0...HEAD
[0.14.0]: https://github.com/gabrielmustiere/kadence/compare/v0.13.1...v0.14.0
[0.13.1]: https://github.com/gabrielmustiere/kadence/compare/v0.13.0...v0.13.1
[0.13.0]: https://github.com/gabrielmustiere/kadence/compare/v0.12.0...v0.13.0
[0.12.0]: https://github.com/gabrielmustiere/kadence/compare/v0.11.0...v0.12.0
[0.11.0]: https://github.com/gabrielmustiere/kadence/compare/v0.10.0...v0.11.0
[0.10.0]: https://github.com/gabrielmustiere/kadence/compare/v0.9.0...v0.10.0
[0.9.0]: https://github.com/gabrielmustiere/kadence/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/gabrielmustiere/kadence/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/gabrielmustiere/kadence/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/gabrielmustiere/kadence/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/gabrielmustiere/kadence/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/gabrielmustiere/kadence/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/gabrielmustiere/kadence/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/gabrielmustiere/kadence/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/gabrielmustiere/kadence/releases/tag/v0.1.0
