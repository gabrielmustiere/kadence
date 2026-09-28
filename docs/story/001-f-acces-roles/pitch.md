# Inscrire l'équipe avec un rôle pour que chacun ne voie que ce qui le concerne

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/001-f-acces-roles/`
> **Amont** : aucun

La direction inscrit chaque membre de l'équipe avec un rôle (direction, lead ou prod), peut le désactiver à son départ sans perdre son historique, et chacun n'accède qu'à ce que son rôle autorise. C'est le socle de toutes les features suivantes : sans identités ni rôles, ni la saisie des temps ni le principe « on pilote des projets, jamais des personnes » ne peuvent être tenus.

## Contexte

Aujourd'hui, Kadence connaît une seule notion : une personne connectée. Il n'existe ni rôle métier, ni nom, ni moyen pour la direction d'ajouter quelqu'un ou de retirer l'accès à une personne qui quitte l'équipe. Toute personne connectée voit tout.

Or toute la chaîne de la vision repose sur des rôles distincts : la direction arbitre et s'engage sur des dates, les leads pilotent les projets, l'équipe de prod saisit ses temps. Les règles de confidentialité du backlog (le détail des temps d'une personne n'est visible que par elle-même, aucun indicateur par personne) supposent que l'outil sache qui est qui. Sans cette feature, aucune autre feature du MVP ne peut être ouverte à l'équipe.

**Mesure de succès** : au déploiement, 100 % de l'effectif est inscrit avec le bon rôle, et chacun s'est connecté et a remplacé son mot de passe provisoire ; la direction inscrit une personne en moins d'une minute ; aucun compte lead ou prod n'accède à la gestion d'équipe, et aucun compte désactivé ne peut se connecter.

## Alignement vision

- **Problème adressé** : indirect — la feature ne résout aucun des trois symptômes (dépassements tardifs, planning intenable, roadmap opaque), mais elle est le prérequis de toutes les features qui les attaquent.
- **Audience servie** : la direction (gestion de l'équipe) en premier lieu ; les leads et l'équipe de prod y gagnent un accès personnel et cloisonné.
- **Principes respectés** : principe 2 (« on pilote des projets et une capacité, jamais des personnes ») — la liste d'équipe n'affiche aucune donnée d'activité individuelle, et les rôles rendent possible la confidentialité des temps. Anti-objectif « pas d'intégration au départ » respecté : pas d'authentification unique d'entreprise, comptes gérés dans Kadence.
- **Hypothèse testée** : aucune directement ; la feature conditionne H1 (saisie tenue ≥ 90 %), qui exige que chacun ait un accès personnel.
- **Impact North Star** : neutre — prérequis technique à sa mesure.

## Utilisateurs concernés

- **Direction** (quelques personnes, tous les droits) — gère l'équipe : inscrit, modifie, désactive, réactive, réattribue un mot de passe provisoire.
- **Lead** (chefs de projet) — se connecte avec son propre compte ; dispose en plus de tous les droits d'un membre de prod. Aucun accès à la gestion d'équipe.
- **Prod** (équipe de production) — se connecte avec son propre compte, change son mot de passe. Aucun accès à la gestion d'équipe.
- **Personne ayant quitté l'équipe** — ne peut plus se connecter ; son historique reste dans l'outil.

## User Stories

- En tant que **direction**, je veux inscrire une personne avec son prénom, son nom, son e-mail et son rôle afin qu'elle puisse utiliser Kadence avec les bons droits.
- En tant que **direction**, je veux obtenir un mot de passe provisoire généré par l'outil au moment de l'inscription afin de le transmettre à la personne sans inventer de secret moi-même.
- En tant que **personne nouvellement inscrite**, je veux être obligée de choisir mon propre mot de passe à ma première connexion afin que personne d'autre que moi ne le connaisse.
- En tant que **direction**, je veux consulter la liste de l'équipe (identité, rôle, statut) afin de savoir qui a accès à l'outil et avec quels droits.
- En tant que **direction**, je veux modifier le prénom, le nom, l'e-mail ou le rôle d'une personne afin de corriger une erreur ou d'acter une promotion.
- En tant que **direction**, je veux désactiver une personne qui quitte l'équipe afin qu'elle ne puisse plus se connecter, sans perdre ce qu'elle a saisi.
- En tant que **direction**, je veux réactiver une personne désactivée afin qu'elle retrouve son accès et son historique à son retour.
- En tant que **direction**, je veux attribuer un nouveau mot de passe provisoire à une personne qui a oublié le sien afin de la débloquer sans intervention technique.
- En tant que **membre de l'équipe** (tout rôle), je veux changer mon mot de passe depuis « Mon compte » afin de le renouveler quand je le souhaite.
- En tant que **lead ou prod**, je ne veux PAS voir la gestion d'équipe dans ma navigation ni pouvoir y accéder, car elle ne me concerne pas.
- En tant que **responsable technique à l'installation**, je veux créer le premier compte direction sans passer par une page publique afin que personne d'autre ne puisse s'attribuer ce rôle.

## Règles métier

1. **Trois rôles, un seul par personne, cumulatifs** : direction ⊃ lead ⊃ prod. Un lead a tous les droits d'un membre de prod ; la direction a tous les droits d'un lead. Toute personne, quel que soit son rôle, peut saisir ses temps.
2. **Identité** : une personne a un prénom, un nom, un e-mail et un rôle, tous obligatoires. L'e-mail sert d'identifiant de connexion.
3. **Unicité de l'e-mail** : un e-mail ne peut appartenir qu'à une seule personne, qu'elle soit active ou désactivée, sans tenir compte des majuscules. Si l'e-mail saisi à l'inscription appartient à une personne désactivée, l'inscription est refusée et le message oriente vers la réactivation.
4. **Mot de passe provisoire** : généré aléatoirement par l'outil à l'inscription et à chaque réinitialisation, affiché **une seule fois** à la direction, jamais consultable ensuite. La direction le transmet elle-même (aucun e-mail envoyé).
5. **Changement obligatoire** : tant qu'une personne se connecte avec un mot de passe provisoire, elle ne peut rien faire d'autre que choisir son propre mot de passe. Le nouveau mot de passe doit différer du provisoire.
6. **Réinitialisation par la direction** : attribuer un nouveau mot de passe provisoire invalide immédiatement l'ancien mot de passe de la personne, et la règle 5 s'applique à sa connexion suivante.
7. **Changement volontaire** : depuis « Mon compte », une personne change son mot de passe en confirmant d'abord son mot de passe actuel. La direction ne voit jamais le mot de passe choisi par une personne.
8. **Pas de suppression** : une personne n'est jamais supprimée, seulement désactivée. Tout ce qui lui est rattaché (temps saisis, historique) est conservé et continue de compter dans les agrégats.
9. **Désactivation** : une personne désactivée ne peut plus ouvrir de nouvelle session, et une session déjà ouverte au moment de la désactivation est coupée dès sa prochaine action.
10. **Réactivation** : une personne réactivée retrouve son accès, son rôle et son historique, avec son mot de passe inchangé.
11. **Connexion refusée sans indice** : une tentative de connexion avec un compte désactivé affiche le même message que des identifiants invalides, pour ne pas révéler l'existence du compte.
12. **Toujours une direction active** : il est impossible de désactiver ou de faire changer de rôle le dernier membre actif de la direction, y compris soi-même.
13. **Premier compte direction** : créé à l'installation par une opération réservée à l'équipe technique, jamais par une page accessible sans connexion. Aucune inscription libre n'existe.
14. **Gestion d'équipe réservée à la direction** : seule la direction voit la liste de l'équipe et y agit. La navigation n'affiche l'entrée « Équipe » qu'à la direction ; un lead ou un membre de prod qui tente d'y accéder directement est refusé.
15. **Aucune donnée d'activité dans la liste d'équipe** : la liste affiche identité, rôle et statut (active / désactivée), et rien sur l'usage de l'outil (pas de date de dernière connexion, pas d'indicateur de saisie).
16. **Droits des écrans futurs** : cette feature pose les rôles et protège la gestion d'équipe ; chaque feature suivante applique à ses propres écrans la matrice de droits du backlog (§Permissions et rôles).
17. **Limitation des tentatives de connexion** : après plusieurs échecs de connexion successifs pour un même identifiant depuis un même poste, les tentatives sont temporairement bloquées et un message invite à réessayer quelques minutes plus tard.

## Critères d'acceptation

- [ ] La direction inscrit une personne en renseignant prénom, nom, e-mail et rôle, et voit s'afficher une seule fois un mot de passe provisoire généré par l'outil.
- [ ] La direction inscrit une personne en moins d'une minute.
- [ ] L'inscription est refusée si l'e-mail est déjà utilisé par une personne active ou désactivée (y compris avec une casse différente), avec un message orientant vers la réactivation dans le second cas.
- [ ] À sa première connexion avec le mot de passe provisoire, la personne est obligée de choisir un nouveau mot de passe, différent du provisoire, avant d'accéder à quoi que ce soit.
- [ ] La direction consulte la liste de l'équipe avec, pour chaque personne, prénom, nom, e-mail, rôle et statut — et aucune donnée d'activité.
- [ ] La direction modifie le prénom, le nom, l'e-mail ou le rôle d'une personne ; la personne se connecte ensuite avec son nouvel e-mail le cas échéant.
- [ ] Une personne désactivée ne peut plus se connecter, et le message affiché est identique à celui d'identifiants invalides.
- [ ] Une personne désactivée alors qu'elle est connectée est déconnectée dès sa prochaine action.
- [ ] Une personne réactivée se reconnecte avec son mot de passe inchangé et retrouve son rôle.
- [ ] La direction attribue un nouveau mot de passe provisoire à une personne ; l'ancien mot de passe ne fonctionne plus et la personne doit en choisir un nouveau à sa connexion suivante.
- [ ] Toute personne connectée change son mot de passe depuis « Mon compte » après avoir confirmé son mot de passe actuel.
- [ ] Il est impossible de désactiver ou de changer le rôle du dernier membre actif de la direction, y compris le sien.
- [ ] Un lead ou un membre de prod ne voit pas l'entrée « Équipe » dans la navigation et est refusé s'il accède directement à une page de gestion d'équipe.
- [ ] Le premier compte direction se crée sans passer par une page de l'application, et aucune page ne permet de s'inscrire soi-même.

## Hors scope

- **Authentification unique d'entreprise (SSO)** : anti-objectif « pas d'intégration au départ ».
- **Invitation et mot de passe oublié en libre-service par e-mail** : remplacés par le mot de passe provisoire transmis par la direction ; à reconsidérer sur irritant prouvé.
- **« Se souvenir de moi »** : la session se termine à la fermeture du navigateur ; à reconsidérer si la reconnexion quotidienne devient un irritant.
- **Suppression définitive d'une personne** : seule la désactivation existe ; la purge des données personnelles est traitée par `purge-donnees-temps` (V3).
- **Intitulé de poste et modification de son propre nom par la personne** : non retenus, la direction gère l'identité.
- **Temps de travail, temps partiel, jours travaillés** : relèvent de `capacite-equipe` (C4.1, V2).
- **Droits d'accès des écrans à venir** (projets, saisie, suivi, roadmap) : posés par chaque feature concernée.
- **Liste d'équipe pour les leads** : ouverte le moment venu par `affectation-sous-projets` (V2) si besoin.

## Impacts transverses

- **Traduction / langues** : non — interface en français uniquement (convention du backlog).
- **Droits d'accès** : oui — trois niveaux (direction ⊃ lead ⊃ prod) ; la gestion d'équipe est réservée à la direction ; « Mon compte » est accessible à tous.
- **Cloisonnement des données** : oui — la liste d'équipe n'est visible que de la direction et n'expose aucune donnée d'activité (principe 2). Pas de multi-organisation.
- **Apparence / déclinaisons** : la navigation varie selon le rôle (entrée « Équipe » pour la direction seulement). Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non — aucune donnée exposée hors de l'interface.
- **Emails / notifications** : non — le mot de passe provisoire est transmis de vive voix par la direction.
- **Données existantes** : les comptes de démonstration existants doivent recevoir un prénom, un nom et un rôle ; aucune donnée réelle en production à reprendre.
- **Comportement par défaut** : toute personne inscrite commence avec un mot de passe provisoire à remplacer ; aucune page n'est accessible sans connexion hormis la page de connexion.

## Questions ouvertes

- **Exigence minimale sur le mot de passe choisi** : quelle robustesse imposer ? Options : (a) longueur minimale seule (ex. 12 caractères), (b) longueur + refus des mots de passe connus comme compromis, (c) règles de composition (majuscule, chiffre…). → tranché : 12 caractères minimum et refus des mots de passe trop prévisibles (ex. « aaaaaaaaaaaa »), sans vérification auprès d'un service externe.
- **Durée de vie d'une session** : combien de temps une personne désactivée peut-elle encore agir au pire ? Options : (a) jusqu'à la fermeture du navigateur, (b) expiration après une durée d'inactivité (ex. quelques heures), (c) « se souvenir de moi » sur plusieurs jours — à arbitrer contre le confort de la saisie quotidienne (principe 1). → tranché : la session est coupée dès la désactivation (règle 9) ; elle dure jusqu'à la fermeture du navigateur, sans « se souvenir de moi ».
- **Validité du mot de passe provisoire** : expire-t-il s'il n'est pas utilisé ? Options : (a) pas d'expiration, (b) expiration après quelques jours, la direction en régénère un. → tranché : (a) pas d'expiration.
- **Rôle proposé par défaut à l'inscription** : Options : (a) prod présélectionné, (b) aucun, choix obligatoire. → tranché : (a) prod présélectionné.
- **Présentation des personnes désactivées dans la liste** : Options : (a) mêlées aux actives avec leur statut, (b) masquées par défaut avec un filtre, (c) section séparée. → tranché : (a), avec un badge de statut, les personnes actives en premier.

---

## Annexe — Pistes pour le plan

- L'entité utilisateur existante (e-mail, rôles, mot de passe, connexion par formulaire déjà en place) est le point de départ : pistes d'ajout d'un prénom, d'un nom, d'un statut actif et d'un marqueur « mot de passe provisoire », à confirmer.
- Hiérarchie de rôles native du framework de sécurité (direction ⊃ lead ⊃ prod) plutôt qu'une liste de rôles cumulés à la main — à confirmer.
- Refus de connexion d'un compte désactivé via un contrôle au moment de l'authentification (vérificateur d'utilisateur), en conservant le message générique — à confirmer.
- Changement obligatoire du mot de passe provisoire : interception des requêtes tant que le marqueur est levé — à confirmer.
- Premier compte direction via une commande console ; les identifiants de test du `CLAUDE.md` (`admin@example.com`) et les fixtures sont à aligner sur le rôle direction.
- L'unicité insensible à la casse de l'e-mail suppose une normalisation à l'enregistrement — à vérifier sur la contrainte d'unicité existante.
