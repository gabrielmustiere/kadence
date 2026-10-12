# Parler la même langue sur tous les écrans : « lot » à la place de « feuille », un seul mot et une seule unité par notion

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/015-f-vocabulaire-lot/`
> **Amont** : aucun

Chaque notion de Kadence porte désormais un seul nom et une seule unité, sur tous les écrans. Le mot « feuille », jamais défini, disparaît au profit de « lot ». « Saisi » remplace « Consommé », « dépassement » remplace « au-delà », et un écart à l'estimation s'écrit toujours en jours suivis de sa part de l'estimation. L'équipe redevient celle d'un lot. On lit un tableau, une pastille ou une infobulle sans se demander si deux mots désignent la même chose.

## Contexte

L'audit UX du 12 octobre 2026 a parcouru tous les écrans avec les trois rôles. Il conclut que les écrans de pilotage sont plus difficiles à lire qu'ils ne devraient, et que le vocabulaire en est une cause directe :

- **« Feuille »** apparaît dans les infobulles des pastilles de la roadmap, dans le message qui refuse une estimation sous le temps saisi, dans Mon compte et dans le formulaire d'une personne (« composer l'équipe des feuilles »). Il n'est défini nulle part à l'écran. La même unité de travail s'appelle aussi « Lot » (en-tête de Ma semaine), « ligne » (« Ajouter une ligne ») et « partie » (formulaire d'un lot).
- **Le temps passé** s'appelle « Consommé » sur la page d'administration d'un projet, « Consommation » et « Saisi » sur sa fiche, et « réalisé » sur la page de connexion.
- **Le dépassement** s'écrit « 3,25 j au-delà » dans un tableau, « 3,25 j de dépassement » dans l'autre, « en dépassement +13 % » sur la pastille de la roadmap, et « Saisi au-delà de l'estimation » dans la légende. Un même fait se lit en jours à un endroit et en pourcentage à un autre.
- **« Équipe »** a au moins quatre sens : la page qui liste les personnes, la catégorie de tags « Type d'équipe » (Back, Front…), les personnes affectées à un lot, et les collaborateurs d'un manager dans les congés à valider.
- **« Partiel »** désigne tantôt une estimation dont des lots restent à estimer, tantôt une roadmap dont des lots ne sont pas planifiés (« planning partiel »).
- **« Projeté »** désigne le coût d'un lot calculé d'après son avancement, mais aussi, ailleurs, une date de fin (« sa date de fin projetée »), que la fiche d'un projet appelle « fin calculée ».

Chacun de ces doublons oblige le lecteur à reconstituer un glossaire qu'il n'a pas. C'est précisément le lecteur visé par la vision qui en pâtit : la direction qui veut « lire une page » et le lead qui veut voir une dérive.

L'utilisateur a tranché le mot principal au cadrage : « lot » partout, « feuille » disparaît de l'interface. Les stories 016 (page projet unique), 017 (accueil et navigation par rôle) et 018 (dates lisibles sur la roadmap) reprennent ce lexique ; celle-ci se livre donc en premier.

**Mesure de succès** : une personne qui découvre Kadence lit la fiche d'un projet, la roadmap et ses infobulles sans rencontrer un mot qu'elle ne comprend pas, ni deux mots pour la même chose.

## Alignement vision

- **Problème adressé** : indirect. La story ne crée aucune donnée, mais elle rend lisibles les écrans qui attaquent les symptômes « dépassements découverts trop tard » et « roadmap opaque ». Un dépassement écrit de trois façons se voit moins bien qu'un dépassement écrit d'une seule.
- **Audience servie** : la direction et les leads d'abord, qui lisent la roadmap, les fiches et les tableaux de lots. L'équipe de prod aussi, dans Ma semaine et sur la roadmap.
- **Principes respectés** :
  - Le principe 3 (« pas de granularité sous le sous-lot ») devient lisible : l'unité où l'on estime et saisit s'appelle « lot », qu'il s'agisse d'un lot ou d'un sous-lot. Aucune nouvelle notion n'apparaît.
  - Le principe 4 est servi : le dépassement et l'écart projeté s'écrivent toujours de la même façon, jamais masqués par un changement d'unité.
  - Le principe 1 est respecté : la saisie ne change que d'un libellé de bouton.
  - Le principe 2 est respecté : aucun libellé ne compare ni ne classe les personnes.
- **Hypothèse testée** : aucune directement. H2 en bénéficie : une alerte de dépassement lisible au même format partout est une alerte qu'on remarque.
- **Impact North Star** : neutre.

## Utilisateurs concernés

- **Direction** — lit le même lexique sur la roadmap, les fiches, la liste et les pages des projets. Retrouve la catégorie « Pôle » à la place de « Type d'équipe » sur la page Tags et dans le formulaire d'une personne.
- **Lead** — lit le même lexique en pilotant un projet et en composant l'équipe d'un lot.
- **Prod** — voit « Ajouter un lot » dans Ma semaine, et le même lexique sur la roadmap et les fiches.
- **Manager** — lit, dans les congés à valider, si d'autres collaborateurs du même manager sont absents sur la période.
- **Personne non connectée** — lit la page de connexion avec le même lexique.

## User Stories

- En tant que **direction**, je veux lire « lot » partout où l'on estime, confie et saisit afin de ne jamais me demander ce qu'est une « feuille ».
- En tant que **lead**, je veux voir un dépassement écrit au même format sur la roadmap, la fiche et la page d'un projet afin de le reconnaître d'un coup d'œil et de comparer deux lots sans conversion.
- En tant que **lead**, je veux voir un écart projeté écrit au même format qu'un dépassement, avec son signe, afin de savoir immédiatement si un lot finira au-dessus ou en dessous de son estimation.
- En tant que **direction**, je veux que « Saisi » désigne partout le temps saisi afin de ne pas chercher la différence entre « Consommé », « Consommation » et « Saisi ».
- En tant que **lead qui compose l'équipe d'un lot**, je veux que « équipe » désigne toujours les personnes affectées à un lot afin de ne pas la confondre avec un pôle ou avec la liste des personnes.
- En tant que **direction**, je veux distinguer une estimation partielle d'une planification partielle afin de savoir s'il manque des estimations ou des dates.
- En tant que **direction**, je veux lire dans la liste des projets qu'un responsable est à désigner ou à remplacer afin de comprendre ce qu'il reste à compléter.
- En tant que **membre de l'équipe**, je veux un bouton « Ajouter un lot » dans Ma semaine afin de savoir ce que j'ajoute à ma grille.
- En tant que **manager**, je veux lire si d'autres de mes collaborateurs sont absents sur la période d'une demande afin de décider en connaissance de cause.
- En tant que **personne connectée**, je ne veux PAS voir changer mes données, mes tags ni mes temps : seuls les mots changent.

## Règles métier

### Lexique

1. **Un nom par notion** : chaque notion du tableau ci-dessous porte ce nom, et seulement celui-là, sur tous les écrans : tableaux, légendes, infobulles, pastilles, formulaires, messages de confirmation ou d'erreur, e-mails.

| Terme | Ce qu'il désigne | Remplace |
|---|---|---|
| **Lot** | Un lot ou un sous-lot sur lequel on estime, confie à un responsable, planifie et saisit : l'unité de travail de Kadence. | « feuille », « partie », et « ligne » quand elle désigne cette unité |
| **Lot découpé** | Un lot qui a des sous-lots. Il n'a ni estimation, ni responsable, ni saisie en propre : il cumule ceux de ses sous-lots. | « lot » employé seul pour la barre d'un lot découpé |
| **Sous-lot** | Un lot rattaché à un lot découpé. Le mot sert quand on parle de la hiérarchie ; ailleurs, un sous-lot est un lot. | — |
| **Estimation** | Les jours déclarés pour un lot ; cumulée pour un lot découpé et un projet. L'**estimation initiale** est celle en vigueur au premier temps saisi. | « Estimé », « j estimés » |
| **Saisi** | Le temps déclaré dans Ma semaine sur un lot, cumulé pour un lot découpé et un projet. | « Consommé », « Consommation », « réalisé » |
| **Restant** | Ce qu'il reste à faire sur un lot, calculé, et sa barre hachurée sur la frise. | « partie future » |
| **Dépassement** | Le temps saisi au-delà de l'estimation d'un lot. Cumulé pour un lot découpé et un projet, jamais compensé par le restant d'un autre lot. | « au-delà », « Saisi au-delà de l'estimation » |
| **Projeté** | Ce qu'un lot aura coûté une fois terminé, d'après son avancement déclaré. Ne désigne jamais une date. | — |
| **Avancement** | Le pourcentage déclaré sur un lot ; cumulé pour un lot découpé et un projet. | — |
| **Fin calculée** | La date à laquelle un lot ou un projet devrait se terminer, d'après son restant et la capacité de son équipe ; « inconnue » quand elle ne peut pas se calculer. | « date de fin projetée », « dates projetées » |
| **Équipe** | Les personnes affectées à un lot, chacune avec sa part. | « l'équipe » au sens de l'ensemble des personnes, des collaborateurs d'un manager ou d'un type d'équipe |
| **Hors équipe** | Une personne qui a saisi sur un lot sans faire partie de son équipe. | — (inchangé) |
| **Pôle** | La catégorie de tags qui range une personne (Back, Front, QA…). | « Type d'équipe » |
| **Responsable** | La personne qui répond d'un lot. | « à désigner » et « à redésigner » employés seuls |
| **Collaborateurs** | Les personnes dont quelqu'un est le manager. | « l'équipe » dans les congés |
| **Estimation partielle** | L'estimation d'un lot découpé ou d'un projet dont des lots restent à estimer. | « partiel » |
| **Planification partielle** | Un projet dont des lots ne sont pas encore placés sur la roadmap. | « planning partiel » |

2. **« Feuille » disparaît** : le mot n'apparaît plus sur aucun écran, dans aucune infobulle, aucun message, aucun e-mail.
3. **Accord** : « lot » est masculin ; les textes réécrits accordent en conséquence (« il n'a pas de fin calculée », « un lot terminé »).

### Unités

4. **Le jour** est l'unité de toute quantité de temps (estimation, saisi, restant, dépassement, projeté), écrit « j », au quart de jour près.
5. **Un écart à l'estimation s'écrit toujours en jours, suivis de sa part de l'estimation entre parenthèses**. Il n'est jamais chiffré en jours seuls ni en pourcentage seul :
   - un dépassement : « 3,25 j (13 %) » ; le mot « dépassement » qui l'accompagne en donne le sens, sans signe ;
   - un écart projeté, qui peut aller dans les deux sens : « +6,5 j (+25 %) » ou « −6 j (−30 %) ».
6. **Part de l'estimation** : l'écart rapporté à l'estimation de la même ligne (celle du lot, ou l'estimation cumulée d'un lot découpé ou d'un projet), arrondie à l'unité, jamais « 0 % » dès qu'il y a un écart. Sur un lot estimé à 0 j, la part ne s'écrit pas.
7. **Partout où l'écart est chiffré** : pastille de la roadmap et de la fiche d'un projet (« en dépassement de 3,25 j (13 %) »), tableau des lots de la fiche, liste des lots de la page d'administration, en-tête de la fiche, récapitulatif au survol d'un lot, coût projeté. Là où l'écart n'est pas chiffré, comme dans la section « À venir » d'une fiche personne, la pastille dit seulement « en dépassement ».
8. **« Au-delà »** ne sert plus qu'à la charge d'une personne au-dessus de 100 % de sa capacité (« Charge au-delà de 100 % »), jamais au temps saisi.
9. **« Dépassement »**, le nom, est réservé au temps saisi. Un solde de congés négatif se dit « solde dépassé ».

### Équipe et personnes

10. **« Équipe » sans précision** désigne l'équipe d'un lot. Pour l'ensemble des personnes, on dit « les personnes » ou « chacun » ; pour les personnes dont on est le manager, « collaborateurs ».
11. **Exception** : les textes de présentation où aucun lot n'est en jeu (page de connexion, pied des e-mails) gardent « l'équipe » au sens de toute l'entreprise.
12. **Congés à valider** : la ligne qui cite les autres absents de la période parle des collaborateurs du même manager (« Aucun autre collaborateur du même manager n'est absent sur la période. »).
13. **La page qui liste les personnes** garde son nom actuel : la story 017 la renomme, avec les libellés qui la désignent (menu, titre, liens de retour, messages qui y renvoient).

### Libellés précisés

14. **Ma semaine** : le bouton et la fenêtre « Ajouter une ligne » deviennent « Ajouter un lot » ; une recherche sans résultat dit « Aucun lot ne correspond. » ; la grille vide invite à « ajouter un lot pour commencer ».
15. **Liste des projets** (colonne « À compléter ») : « N à estimer », « N responsable(s) à désigner », « N responsable(s) à remplacer ». Dans la colonne « Responsable » de la liste des lots d'un projet, où le nom est déjà donné : « à désigner », « à remplacer ». « À redésigner » disparaît.
16. **Avancement cumulé** d'un lot découpé ou d'un projet dont des lots restent à estimer : la mention « partiel » devient « sur les lots estimés ».
17. **Légende des frises** : « Saisi en dépassement » remplace « Saisi au-delà de l'estimation » ; « Lot découpé ou projet » remplace « Lot ou projet ».
18. **Tableaux de lots** : « Saisi » remplace « Consommé » et « Consommation » ; « Estimation » remplace « Estimé » ; la section de la fiche s'intitule « Lots, saisi face à l'estimation ».

### Données

19. **Aucune donnée ne change** : les tags rangés sous « Type d'équipe » se retrouvent sous « Pôle », avec les mêmes valeurs et les mêmes personnes. Les temps, estimations, avancements et congés sont intacts.

## Critères d'acceptation

- [ ] Connecté en direction, en lead ou en prod, aucun écran, aucune infobulle et aucun message de validation n'affiche le mot « feuille ».
- [ ] Les tableaux de lots de la fiche et de la page d'administration d'un projet titrent « Saisi » et « Estimation », jamais « Consommé », « Consommation » ou « Estimé ».
- [ ] Un dépassement chiffré s'affiche au format « 3,25 j (13 %) » sur la pastille de la roadmap, dans le tableau des lots de la fiche, dans la liste des lots de la page d'administration, dans l'en-tête de la fiche et dans le récapitulatif au survol.
- [ ] Un coût projeté affiche son écart au format « +6,5 j (+25 %) » ou « −6 j (−30 %) », sur la fiche et au survol de la roadmap.
- [ ] La légende des frises dit « Saisi en dépassement » et « Lot découpé ou projet » ; le journal d'un projet marque « en dépassement » les tronçons saisis au-delà de l'estimation.
- [ ] Les infobulles des pastilles de la roadmap parlent du lot, accordé au masculin, et ne parlent plus de « partie future ».
- [ ] La catégorie de tags s'appelle « Pôle » (« Pôles » au pluriel) sur la page Tags, dans le formulaire et la liste des personnes, ses filtres, la fiche d'une personne, Mon compte et le filtre de composition de l'équipe d'un lot ; tous les tags existants y figurent.
- [ ] Un projet dont des lots restent à estimer affiche « estimation partielle » ; un projet dont des lots ne sont pas planifiés affiche la pastille « planification partielle ».
- [ ] La liste des projets affiche « responsable à désigner » et « responsable à remplacer » ; la liste des lots d'un projet n'affiche plus « à redésigner ».
- [ ] Ma semaine propose « Ajouter un lot », et une recherche sans résultat dit « Aucun lot ne correspond. ».
- [ ] Le refus d'une estimation inférieure au temps déjà saisi parle du lot.
- [ ] Dans les congés à valider, la ligne des autres absents parle des collaborateurs du même manager.
- [ ] Aucun écran ne parle de « date de fin projetée » : une date calculée s'appelle « fin calculée ».
- [ ] Les données existantes (tags, temps, estimations, avancements, congés) s'affichent à l'identique, seuls les libellés ont changé.

## Hors scope

- **Structure des pages** : la page unique d'un projet relève de la story 016, l'accueil, le menu et la page des personnes de la story 017. Celle-ci ne change que des mots.
- **Renommage de la page Équipe en « Personnes »** : story 017 (règle 13).
- **Dates de fin sur la roadmap** : story 018.
- **Code et documentation** : le mot « feuille » reste dans le code et la documentation de conception, où il désigne précisément la même notion. Aucune adresse de page ni aucune valeur enregistrée ne change.
- **Glossaire ou page d'aide** : non retenu. Un mot qui se comprend seul vaut mieux qu'un mot qu'il faut aller chercher.
- **Messages d'erreur jamais affichés** à une personne (erreurs internes sur une ressource introuvable) : inchangés.
- **Traduction dans une autre langue** : non, l'interface reste en français uniquement.
- **Nouveaux indicateurs** (pourcentage consommé, écart moyen) : non. La part de l'estimation n'accompagne que des écarts déjà affichés.

## Impacts transverses

- **Traduction / langues** : non. L'interface reste en français uniquement ; seuls des libellés français changent.
- **Droits d'accès** : inchangés.
- **Cloisonnement des données** : inchangé. Aucune information nouvelle n'est montrée à quiconque.
- **Apparence / déclinaisons** : la pastille de dépassement s'allonge un peu (« en dépassement de 3,25 j (13 %) ») ; elle passe à la ligne comme les autres pastilles, sur ordinateur comme sur téléphone. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : les e-mails de congés ne parlent ni de lot ni d'équipe ; leur pied (« le pilotage de production de l'équipe ») relève de l'exception de la règle 11. Aucun nouvel envoi.
- **Données existantes** : aucune reprise. Les tags de la catégorie renommée gardent leurs valeurs.
- **Comportement par défaut** : tout le monde voit les nouveaux libellés dès la mise en service, sans réglage.

## Questions ouvertes

- **Nom de la catégorie « Type d'équipe »** : options (a) « Pôle », court, sans le mot « équipe », qui se dit naturellement d'une personne (« pôle Back ») ; (b) « Spécialité », au risque de recouvrir les compétences techniques ; (c) « Métier ». Recommandation : (a).

---

## Annexe — Pistes pour le plan

- Les libellés vivent dans quatre couches : les gabarits Twig, les énumérations `RoadmapSignal` (libellés, `meaning()`, `reason()`) et `TagCategory` (`label()`, `pluralLabel()`), les messages des contraintes et des DTO (`EstimateCoversConsumed`, `TeamMemberInput`), et les libellés de formulaire (`TeamMemberType`). Pas de catalogue de traduction dans le projet. À confirmer.
- Le format d'écart (règles 5 et 6) gagnerait à vivre dans un seul filtre Twig, à côté de `days` dans `DaysExtension`, appuyé sur une fonction pure de `Quarters` ; `RoadmapRow::overrunPercent()` porte déjà l'arrondi « jamais 0 % ». À confirmer.
- Le récapitulatif au survol (`roadmap/_recap.html.twig`) affiche déjà « 3,25 j (+13 %) » : c'est le modèle à généraliser.
- Garde-fou : un test fonctionnel qui parcourt les pages principales avec les fixtures et échoue si le HTML rendu (infobulles cachées comprises) contient « feuille », « consomm », « au-delà de l'estimation », « type d'équipe » ou « planning partiel ». À confirmer.
- Les valeurs enregistrées (`equipe` pour la catégorie de tags, paramètre d'URL du filtre de la liste des personnes) restent telles quelles.
