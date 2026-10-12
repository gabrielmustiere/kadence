# Voir à l'accueil ce qui attend chacun, et trouver dans le menu le quotidien, le pilotage et l'administration

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/017-f-accueil-navigation-role/`
> **Amont** : aucun

L'accueil cesse de recopier le menu et devient la liste de ce qui attend la personne connectée : ses journées incomplètes, ses demandes de congé, les lots dont elle est responsable et qui demandent une action, et, selon son rôle, les demandes à valider et les projets à surveiller. Le menu se range en trois zones : le quotidien, le pilotage, ouvert à tous (Roadmap, Projets, Personnes), et l'administration, réservée à la direction. Une page Personnes, ouverte à tous, remplace la page Équipe.

## Contexte

Le « Tableau de bord » n'affiche aucune donnée. Il annonce « Suivez la charge, l'avancement et les dates de livraison de l'équipe », puis répète les liens du menu : Roadmap, Mon compte, Projets, et pour la direction Équipe, Tags, Jours fériés. Les Congés n'y figurent pas, et « Mon compte » est rangé sous « Suivre ». La prod y voit un bloc « Administration » qui promet de « découper les projets en lots et sous-lots, estimer et confier chaque partie », ce qu'elle ne peut pas faire. Personne n'y apprend qu'une journée est restée incomplète, qu'une demande de congé attend une décision, ou qu'un lot dont il est responsable a dépassé son estimation : il faut ouvrir les écrans un à un pour le découvrir. L'arrivée varie aussi : la première connexion mène à Ma semaine, mais une déconnexion suivie d'une reconnexion mène au Tableau de bord.

Le menu hérite de la story 005 deux zones : le quotidien en haut, une section « Administration » en bas. Pour un lead ou un membre de prod, cette section ne contient que Projets, qu'ils consultent sans l'administrer ; la story 005 avait laissé ce libellé en question ouverte. La Roadmap, ajoutée depuis, s'est glissée dans le haut du menu. L'administration de la capacité est éparpillée : les droits à congés sont un onglet de Congés, les jours fériés sont dans Administration, le maximum hebdomadaire est dans la modification d'une personne. Enfin, ni les leads ni la prod n'ont de liste des personnes : la fiche d'une personne ne s'atteint que depuis les absences, le journal d'un projet ou « Ma fiche ». Un lead qui compose l'équipe d'un lot n'a pas de chemin direct vers la disponibilité de chacun.

**Mesure de succès** : en ouvrant l'accueil, une personne sait en un coup d'œil s'il lui reste quelque chose à faire et l'atteint en un clic ; un lead ouvre la fiche de n'importe quelle personne depuis le menu en deux clics.

## Alignement vision

- **Problème adressé** : indirect. Le symptôme « dépassements découverts trop tard » est servi : un lot en dépassement ou dont l'avancement est à actualiser apparaît à l'accueil de son responsable, et les projets concernés à celui des leads et de la direction, sans qu'ils aient à parcourir la roadmap. Ce n'est pas l'alerte de dérive (ligne `alerte-derive`), qui reste à cadrer. Le symptôme « planning intenable » est servi par la page Personnes, qui ouvre aux leads la fiche, et donc la disponibilité, de chacun.
- **Audience servie** :
  - toute l'équipe, pour sa saisie, ses congés et les lots dont elle est responsable ;
  - les managers, pour les demandes à décider ;
  - les leads et la direction, pour les projets à surveiller et la composition des équipes.
- **Principes respectés** :
  - **Principe 1** : l'arrivée après connexion reste Ma semaine, toujours. Rien ne s'ajoute à la saisie ; l'accueil rappelle à la seule personne concernée ses journées incomplètes.
  - **Principe 2** : l'accueil ne montre à chacun que ce qui le concerne, plus des projets ; aucun indicateur par personne, aucun taux de saisie individuel, aucune comparaison. La page Personnes est une liste alphabétique : ni charge, ni temps saisi, ni classement.
  - **Principe 4** : non concerné ; la date annoncée n'existe pas encore.
- **Hypothèse testée** : H1 indirectement, par un rappel passif et personnel des journées incomplètes ; H2 indirectement, en portant les lots en dépassement devant leur responsable.
- **Impact North Star** : neutre.

## Utilisateurs concernés

- **Toute personne active** — voit le menu en trois zones et un accueil qui liste ce qui l'attend ; arrive toujours sur Ma semaine après connexion ; consulte la liste des personnes.
- **Membre de prod** — ne voit plus de section « Administration » ; retrouve Projets dans « Pilotage », à côté de la Roadmap et de Personnes.
- **Responsable d'un lot** (quel que soit son rôle) — voit à l'accueil ses lots à estimer, en dépassement ou dont l'avancement est à actualiser.
- **Manager** — voit à l'accueil les demandes de congé de ses collaborateurs à décider.
- **Lead** — voit à l'accueil les projets à surveiller ; lit dans la liste des personnes leur rôle, leur pôle, leurs compétences et expériences, et filtre par ces tags.
- **Direction** — retrouve dans la page Personnes tout ce que faisait la page Équipe ; trouve les droits à congés dans « Administration » ; voit à l'accueil les demandes à valider de toute l'équipe et les projets à surveiller.
- **Personne désactivée** — ne se connecte plus ; n'apparaît plus dans la liste des personnes, sauf pour la direction.

## User Stories

- En tant que **membre de l'équipe**, je veux voir à l'accueil mes journées incomplètes de la semaine et de la précédente afin de les saisir avant de les oublier.
- En tant que **membre de l'équipe**, je veux voir à l'accueil mes demandes de congé en attente et celles décidées ces derniers jours afin de savoir où j'en suis sans ouvrir Congés.
- En tant que **responsable d'un lot**, je veux voir à l'accueil mes lots à estimer, en dépassement ou dont l'avancement est à actualiser afin de m'en occuper avant qu'ils ne faussent les dates.
- En tant que **manager**, je veux voir à l'accueil les demandes de congé à décider afin de ne pas faire attendre mes collaborateurs.
- En tant que **lead ou direction**, je veux voir à l'accueil les projets dont la fin est inconnue ou dont un lot est en dépassement, à replanifier ou en retard afin de savoir où intervenir cette semaine.
- En tant que **membre de l'équipe**, je veux un accueil qui me dit clairement quand rien ne m'attend afin de ne pas chercher.
- En tant que **membre de l'équipe**, je veux arriver sur Ma semaine à chaque connexion, y compris après une déconnexion, afin de saisir sans détour.
- En tant que **membre de prod**, je veux trouver la Roadmap, les Projets et les Personnes dans une même zone « Pilotage » afin de ne plus voir d'« Administration » que je n'administre pas.
- En tant que **lead**, je veux ouvrir depuis le menu la liste des personnes, filtrée par compétence, et aller à la fiche de chacune afin de composer l'équipe d'un lot en connaissant les disponibilités.
- En tant que **direction**, je veux inscrire, modifier, désactiver et réactiver les personnes depuis la page Personnes, comme depuis l'ancienne page Équipe, afin de ne rien perdre de la gestion.
- En tant que **direction**, je veux trouver les droits à congés dans « Administration », à côté des jours fériés et des tags, afin de régler la capacité au même endroit.
- En tant que **membre de prod**, je ne veux PAS voir dans la liste des personnes ni charge, ni temps saisi, ni classement, afin que la liste reste un annuaire et non une comparaison.
- En tant que **membre de l'équipe**, je ne veux PAS voir à l'accueil d'information sur la saisie ou les congés d'une autre personne que moi, hors des demandes que j'ai à décider.

## Règles métier

### Menu

1. **Trois zones** :
   - en haut, sans titre : Accueil, Ma semaine, Congés ;
   - « Pilotage », titrée, pour tout le monde : Roadmap, Projets, Personnes ;
   - « Administration », titrée, pour la direction seule : Tags, Jours fériés, Droits à congés.
2. **Disposition** : « Pilotage » suit les entrées du haut. « Administration » se place en bas du menu quand la hauteur le permet ; sinon elle suit « Pilotage » sans la recouvrir, et le menu défile. Le menu déroulant du téléphone présente le même découpage.
3. **Entrée active** : l'entrée de la page affichée est mise en évidence, sous-pages comprises :
   - « Congés » sur Mes congés, À valider et Absences, quel que soit le mois ;
   - « Projets » sur la liste, la page d'un projet, la création et la modification d'un projet ou d'un lot ;
   - « Personnes » sur la liste, la fiche d'une personne, l'inscription, la modification et le mot de passe provisoire ;
   - « Droits à congés » sur toute période et tout type de droits ;
   - « Accueil », « Ma semaine », « Roadmap », « Tags » et « Jours fériés » sur leurs pages et sous-pages.
   Sur Mon compte, aucune entrée n'est mise en évidence.
4. **Droits** : seule la liste des personnes s'ouvre à tous (règle 8). Les autres écrans gardent leurs droits ; un écran interdit n'apparaît pas dans le menu. La pastille du nombre de demandes à valider sur « Congés » est inchangée.

### Arrivée

5. **Après connexion** : on arrive toujours sur Ma semaine, y compris après une déconnexion puis une reconnexion.
6. **Lien suivi** : une personne déconnectée qui suit un lien vers une page de Kadence (par exemple « Décider de la demande » dans un e-mail) arrive sur cette page après connexion.

### Personnes

7. **Liste** : la page Personnes liste les personnes actives par nom, chacune menant à sa fiche. Elle ne montre ni charge, ni temps saisi, ni indicateur, et ne classe personne.
8. **Ce que chacun y lit** (les mêmes niveaux que sur la fiche d'une personne) :
   - tout le monde : le nom ;
   - les leads et la direction : en plus, le rôle, le pôle, les compétences techniques et les expériences fonctionnelles, et les filtres par ces tags ;
   - la direction : en plus, l'e-mail, le manager, le filtre par manager, et les personnes désactivées, signalées comme telles.
9. **Gestion par la direction** : inscrire, modifier, générer un mot de passe provisoire, désactiver et réactiver se font depuis la page Personnes, avec les mêmes règles et les mêmes confirmations qu'aujourd'hui. Aucune action n'est proposée aux autres rôles.
10. **Filtres** : ils s'appliquent dès qu'on les change, se retrouvent dans l'adresse de la page et restent en place après une désactivation ou une réactivation, comme aujourd'hui.
11. **Ancienne page Équipe** : elle disparaît du menu ; ses anciennes adresses mènent aux pages correspondantes de Personnes.

### Droits à congés

12. **Page** : la page des droits à congés (aujourd'hui l'onglet « Compteurs » de Congés) s'ouvre depuis « Administration », sous le titre « Droits à congés ». Son contenu et ses règles ne changent pas.
13. **Congés** : la page Congés garde les onglets Mes congés, À valider (pour qui décide) et Absences. L'onglet « Compteurs » disparaît.

### Accueil

14. **Remplacement** : l'accueil, titré « Accueil », remplace le Tableau de bord. Il salue la personne et ne reprend plus les raccourcis du menu.
15. **Sections**, dans cet ordre, chacune affichée seulement si elle n'est pas vide, chaque élément menant directement à l'écran où agir :
    1. **Ma saisie** : les journées incomplètes de la semaine en cours (jours passés) et de la précédente, selon la règle de Ma semaine : un jour férié, un jour de congé ou une semaine qui a atteint son maximum ne sont pas incomplets. Chaque journée mène à sa semaine.
    2. **À valider** (manager et direction) : les demandes de congé que la personne peut décider, avec le demandeur, le type et les dates, les plus proches d'abord. Elles mènent à la page À valider.
    3. **Mes lots** : les lots dont la personne est responsable et qui sont à estimer, en dépassement ou dont l'avancement est à actualiser, avec leurs pastilles et le même sens que sur la roadmap. Chacun mène à la page de son projet.
    4. **Projets à surveiller** (leads et direction) : les projets dont la fin calculée est inconnue, ou dont au moins un lot est en dépassement, à replanifier, avec une équipe à revoir, en démarrage en retard ou avec un avancement à actualiser. Chaque projet porte les pastilles de ce qui le signale, sans nom de personne, et mène à sa page.
    5. **Mes congés** : les demandes de la personne en attente de décision, et celles validées, refusées ou annulées par quelqu'un d'autre depuis moins de 7 jours.
16. **Rien à faire** : quand aucune section n'a d'élément, l'accueil le dit explicitement et propose d'ouvrir Ma semaine.
17. **Strictement personnel** : en dehors des demandes à décider et des projets à surveiller, l'accueil ne montre que ce qui concerne la personne connectée. Il ne montre jamais un taux de saisie, les journées d'une autre personne, ni un indicateur par personne.
18. **À jour à l'ouverture** : l'accueil reflète l'état au moment où il s'affiche ; il n'envoie aucune notification.
19. **Ordre** : les journées et les demandes par date, les projets par ordre alphabétique, les lots dans l'ordre du projet.

## Critères d'acceptation

- [ ] Pour toute personne, le haut du menu présente Accueil, Ma semaine et Congés, puis une zone « Pilotage » avec Roadmap, Projets et Personnes.
- [ ] Pour la direction seule, le menu présente une zone « Administration » avec Tags, Jours fériés et Droits à congés ; un lead ou un membre de prod n'en voit aucune.
- [ ] Sur un écran d'ordinateur assez haut, « Administration » est collée en bas du menu ; sur un écran peu haut, elle suit « Pilotage » sans la recouvrir ; sur téléphone, le menu déroulant présente le même découpage.
- [ ] La fiche d'une personne, l'inscription et la modification d'une personne mettent « Personnes » en évidence ; la page des droits à congés met « Droits à congés » en évidence ; Mon compte n'en met aucune.
- [ ] Après une déconnexion puis une reconnexion, on arrive sur Ma semaine.
- [ ] Une personne déconnectée qui ouvre le lien « Décider de la demande » d'un e-mail arrive sur la page À valider après connexion.
- [ ] Un membre de prod ouvre la page Personnes et y voit les personnes actives par nom, chacune menant à sa fiche, sans rôle, tag, e-mail, charge ni temps saisi.
- [ ] Un lead y voit en plus le rôle, le pôle, les compétences et expériences, et filtre la liste par ces tags ; il n'y voit ni e-mail, ni manager, ni personne désactivée, ni action de gestion.
- [ ] La direction y inscrit, modifie, désactive et réactive une personne, génère un mot de passe provisoire et filtre par manager, comme depuis l'ancienne page Équipe.
- [ ] L'ancienne adresse de la page Équipe et celles de ses sous-pages mènent aux pages correspondantes de Personnes.
- [ ] La page Congés ne présente plus d'onglet « Compteurs » ; la direction règle les droits depuis « Droits à congés », avec le même contenu qu'avant.
- [ ] L'accueil s'intitule « Accueil » et ne reprend plus les liens du menu.
- [ ] Une personne dont une journée passée de la semaine est incomplète la voit dans « Ma saisie », et le lien ouvre la semaine concernée ; un jour férié ou un jour de congé n'y figure pas.
- [ ] Un manager voit dans « À valider » les demandes de ses collaborateurs ; la direction y voit toutes les demandes qu'elle peut décider ; une personne qui ne décide d'aucune demande ne voit pas la section.
- [ ] Le responsable d'un lot en dépassement le voit dans « Mes lots », avec sa pastille, et le lien ouvre la page du projet.
- [ ] Un lead voit dans « Projets à surveiller » un projet dont la fin calculée est inconnue ; un membre de prod ne voit pas cette section.
- [ ] Une personne voit dans « Mes congés » sa demande en attente, et sa demande refusée il y a moins de 7 jours ; une demande décidée il y a plus de 7 jours n'y figure plus.
- [ ] Une personne à qui rien n'est demandé voit un accueil qui l'indique explicitement, avec un lien vers Ma semaine.
- [ ] Aucune section de l'accueil ne montre la saisie, les journées incomplètes ou les congés d'une autre personne, hors des demandes qu'elle a à décider.

## Hors scope

- **Taux de saisie de l'équipe et rappel actif de saisie** : ligne `rappel-saisie`, à cadrer. L'accueil n'envoie rien et ne montre jamais de taux.
- **Alerte de dérive** : ligne `alerte-derive`, à cadrer. L'accueil montre des signaux déjà calculés, il n'en crée pas.
- **Charge face à la capacité de l'équipe, semaine par semaine** : ligne `charge-vs-capacite`.
- **Indicateurs chiffrés ou graphiques sur l'accueil** : non retenus, l'accueil liste des choses à faire.
- **Réglage de l'accueil** (masquer ou réordonner une section) : non retenu.
- **Recherche de personnes par nom** : non retenue ; la liste tient en une page pour 15 à 40 personnes, les filtres suffisent.
- **Déplacement du maximum hebdomadaire et du calendrier de jours fériés d'une personne vers « Administration »** : non, ils restent dans la modification de la personne.
- **Mon compte** : inchangé, dans le menu utilisateur.
- **Page unique d'un projet, vocabulaire, dates de fin sur la roadmap** : stories 016, 015 et 018.
- **Nouveaux droits sur la fiche d'une personne** : aucun.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement : « Accueil », « Pilotage », « Personnes », « Droits à congés » et les intitulés des sections de l'accueil.
- **Droits d'accès** : la liste des personnes s'ouvre à toute personne connectée, avec les mêmes niveaux de lecture que la fiche d'une personne. La gestion des personnes et les droits à congés restent réservés à la direction. Les projets à surveiller sont réservés aux leads et à la direction.
- **Cloisonnement des données** : oui. L'accueil ne montre à chacun que sa saisie, ses congés et ses lots, plus les demandes qu'il a à décider. La pastille « à replanifier », qui signale une surcharge sans nommer personne, n'apparaît qu'aux leads et à la direction, comme sur la roadmap.
- **Apparence / déclinaisons** : le menu (ordinateur et téléphone), l'accueil, la page Personnes et la page des droits à congés. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non. Les liens des e-mails de congés continuent d'ouvrir la page demandée après connexion.
- **Données existantes** : aucune donnée touchée.
- **Comportement par défaut** : tout le monde voit le nouveau menu et le nouvel accueil dès la mise en service ; les anciennes adresses de la page Équipe mènent à Personnes.

## Questions ouvertes

- **Arrivée après connexion** : faut-il arriver sur l'accueil plutôt que sur Ma semaine, puisque l'accueil rappelle désormais les journées incomplètes ? Options : (a) Ma semaine, toujours (proposé : principe 1, la saisie est le geste quotidien) ; (b) l'accueil, qui renvoie à Ma semaine en un clic.
- **Taille de la story** : elle livre un menu, une page Personnes, un déplacement d'écran et un nouvel accueil. Options : (a) une story livrée en tranches indépendantes, dans l'ordre menu, Personnes, droits à congés, accueil, arrivée (choix du cadrage) ; (b) deux stories, la navigation d'abord, l'accueil ensuite. Recommandation : (a), à basculer sur (b) si la revue de la première tranche montre un diff trop lourd.

---

## Annexe — Pistes pour le plan

- Menu : `templates/base.html.twig` (lignes 105 à 143) et `NavigationExtension::SECTIONS` ; une section `people` couvrant `app_person` et `app_team_`, une section `entitlements` placée avant `time_off`, à confirmer.
- Personnes : la liste `GET /personnes` peut vivre dans `PersonController`, à côté de `/personnes/{id}` ; le composant `TeamList` s'ouvrirait à tous en masquant colonnes, filtres et actions selon `PersonVoter` ; `TeamController` garderait ses droits par attribut et changerait de préfixe d'adresse, avec des redirections depuis `/equipe`. À confirmer.
- Droits à congés : la route `/conges/compteurs` peut rester, seule sa section de menu change ; l'onglet sort de `time_off/_tabs.html.twig`.
- Accueil : `PageController::index` ; les signaux des lots et des projets se lisent dans les lignes de `RoadmapBuilder::build()` (`RoadmapRow::isEndUnknown()`, `unknownEndLeaves()`) ; les journées incomplètes dans `TimesheetDay::$forgotten` de `TimesheetBuilder` ; les demandes dans `TimeOffRequestRepository`.
- Arrivée : `security.yaml`, déconnexion sans `target`, ce qui mémorise `/` comme page cible ; ne pas utiliser `always_use_default_target_path`, qui casserait les liens des e-mails.
