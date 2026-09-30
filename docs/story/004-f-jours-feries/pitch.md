# Verrouiller la saisie des jours fériés selon le calendrier français ou belge de chacun

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/004-f-jours-feries/`
> **Amont** : aucun

Chaque personne suit le calendrier de jours fériés de la France ou de la Belgique. Dans sa grille de saisie, un jour férié de son calendrier est verrouillé et nommé. Une semaine qui en contient un est complète dès que ses jours ouvrés sont saisis. La direction rattache chacun à son calendrier et l'ajuste quand la loi ne suffit pas : jour de remplacement belge, lundi de Pentecôte travaillé.

## Contexte

Aujourd'hui, la grille de saisie traite un jour férié comme un jour ordinaire. La story 003 l'a assumé, en renvoyant les jours fériés à `capacite-equipe`. Cela produit trois défauts. D'abord, on peut saisir du temps un jour où personne ne travaille, ce qui fausse le consommé. Ensuite, une semaine qui contient un jour férié n'atteint son maximum qu'à condition de saisir ce jour-là. Sans cela, elle reste affichée incomplète (« 4 j / 5 j ») et ses jours passés incomplets restent signalés comme des oublis. Un faux signal d'oubli, répété à chaque jour férié, use la discipline de saisie dont dépend toute la chaîne. Enfin, une petite minorité de l'équipe suit le calendrier belge, qui diffère du français : le 21 juillet est férié en Belgique, alors que le 8 mai et le 14 juillet ne le sont pas. Un calendrier unique serait faux pour ces personnes.

La loi ne suffit pas à fixer le calendrier. En Belgique, un jour férié qui tombe un dimanche ou un jour habituellement non travaillé doit être remplacé par un autre jour, que fixe l'employeur. En 2026, c'est le cas du 15 août (samedi) et du 1er novembre (dimanche). En France, le lundi de Pentecôte est souvent travaillé au titre de la journée de solidarité. La direction doit donc pouvoir ajuster chaque calendrier.

Pourquoi maintenant, alors que la ligne `capacite-equipe` est prévue en V2 : la saisie est en place depuis la story 003, et `rappel-saisie` (MVP) mesurera le « % de jours ouvrés saisis dans les 48 h ». Sans jours fériés, un jour férié non saisi compterait comme un oubli, et le rappel relancerait les gens un jour chômé.

**Mesure de succès** : sur une semaine qui contient un jour férié, une personne à temps plein obtient une semaine complète en saisissant ses quatre jours ouvrés, sans aucun signal d'oubli. Aucun temps ne peut être saisi sur un jour férié.

## Alignement vision

- **Problème adressé** : indirect. La feature fiabilise la notion de jour ouvré, sur laquelle reposent la complétude de la saisie et, demain, la capacité réelle. La vision note que la capacité réelle n'est aujourd'hui pas croisée avec la charge, ce qui rend le planning intenable.
- **Audience servie** : toute l'équipe, dont la grille devient juste, en particulier la minorité rattachée à la Belgique. La direction, qui règle les calendriers.
- **Principes respectés** :
  - Principe 1 renforcé : plus de faux signal d'oubli, une semaine complète atteignable, plus de doute sur un jour férié. Le seul geste ajouté revient à la direction, jamais à la saisie.
  - Principe 2 respecté : on rattache une personne au calendrier qu'elle suit, pas à sa nationalité, et ce rattachement ne sert à rien d'autre qu'à verrouiller ses jours fériés.
  - Anti-objectif « pas d'intégration au départ » tenu : les calendriers sont calculés par l'application, sans agenda ni service externe.
- **Hypothèse testée** : H1. La métrique d'activation (« % de jours ouvrés saisis dans les 48 h ») suppose de savoir quels jours sont ouvrés, pour chaque personne.
- **Impact North Star** : indirect. Les jours fériés sont un prérequis de la capacité réelle (horizon 6 mois), qui alimentera les dates projetées.

## Utilisateurs concernés

- **Toute personne active** (direction, lead, prod) : dans sa propre grille, les jours fériés de son calendrier sont verrouillés et nommés, et le maximum d'une semaine qui en contient tient compte de ces jours.
- **Personnes rattachées à la Belgique** (petite minorité) : leur grille suit les jours fériés belges et non les français.
- **Direction** : rattache chaque personne à un calendrier sur la fiche Équipe. Consulte les jours fériés de chaque calendrier sur une page dédiée, et y ajoute ou en retire des jours.
- **Lead et prod** : aucun droit nouveau. Ils ne voient les jours fériés que dans leur grille.
- **Personne désactivée** : aucun changement.

## User Stories

- En tant que **membre de l'équipe**, je veux voir les jours fériés de mon calendrier verrouillés et nommés dans ma grille afin de ne pas me demander si je dois y saisir.
- En tant que **membre de l'équipe**, je veux qu'une semaine qui contient un jour férié soit complète une fois mes jours ouvrés saisis afin de ne pas voir de faux oubli.
- En tant que **collaborateur rattaché à la Belgique**, je veux que ma grille suive les jours fériés belges et non les français afin de saisir le 14 juillet et non le 21 juillet.
- En tant que **direction**, je veux rattacher chaque personne au calendrier France ou Belgique sur sa fiche afin que sa grille suive ses jours fériés.
- En tant que **direction**, je veux consulter pour une année les jours fériés de chaque calendrier, y compris ceux qui tombent un week-end, afin de repérer les jours de remplacement à fixer.
- En tant que **direction**, je veux ajouter un jour férié à un calendrier, avec un libellé, afin d'appliquer un jour de remplacement.
- En tant que **direction**, je veux retirer un jour férié légal d'un calendrier afin que l'équipe concernée puisse saisir un jour férié travaillé, comme la journée de solidarité.
- En tant que **membre de l'équipe**, je ne veux PAS pouvoir saisir sur un jour férié, même exceptionnellement, afin que mes temps restent cohérents avec mon calendrier.
- En tant que **direction**, je ne veux PAS qu'un lead ou un membre de prod modifie un calendrier ou le rattachement d'une personne afin que les jours fériés restent une décision de la direction.

## Règles métier

### Calendriers

1. **Deux calendriers** : France et Belgique. Chacun comprend les jours fériés légaux de son pays, établis automatiquement pour n'importe quelle année, fêtes mobiles comprises. Personne n'a de liste à saisir chaque année.
2. **Jours légaux** :
   - **France** (11) : Jour de l'an (1er janvier), lundi de Pâques, Fête du Travail (1er mai), Victoire 1945 (8 mai), Ascension, lundi de Pentecôte, Fête nationale (14 juillet), Assomption (15 août), Toussaint (1er novembre), Armistice 1918 (11 novembre), Noël (25 décembre).
   - **Belgique** (10) : Jour de l'an, lundi de Pâques, Fête du Travail, Ascension, lundi de Pentecôte, Fête nationale (21 juillet), Assomption, Toussaint, Armistice 1918, Noël.
3. **Week-end** : un jour férié qui tombe un samedi ou un dimanche n'a aucun effet sur la saisie, puisque la grille ne présente que les jours du lundi au vendredi. Il n'est jamais reporté automatiquement : un jour de remplacement est un ajustement (règle 4).
4. **Ajustements** : la direction peut, pour un calendrier et une date :
   - **ajouter** un jour férié, du lundi au vendredi, avec un libellé obligatoire (par exemple « Remplacement du 1er novembre ») ;
   - **retirer** un jour férié légal (par exemple le lundi de Pentecôte, travaillé au titre de la journée de solidarité).
5. **Portée d'un ajustement** : un ajustement vaut pour une seule date d'un seul calendrier. Il ne se reconduit pas d'une année sur l'autre. Il peut porter sur une date passée ou future.
6. **Annulation** : la direction peut supprimer un ajustement. La date retrouve alors son statut légal : le jour ajouté redevient ordinaire, le jour retiré redevient férié.
7. **Ajustements refusés** : ajouter un jour qui est déjà férié dans ce calendrier, ajouter un samedi ou un dimanche, ou retirer un jour qui n'est pas férié légal dans ce calendrier est refusé avec un message.

### Rattachement

8. **Un calendrier par personne** : chaque personne suit exactement un calendrier, France ou Belgique. C'est le calendrier des jours fériés qu'elle suit, et non sa nationalité.
9. **Par défaut** : France, à l'inscription comme pour les personnes déjà inscrites.
10. **Qui le fixe** : seule la direction, sur la fiche Équipe de la personne, à l'inscription ou en modification.
11. **Sans historique** : le calendrier en vigueur s'applique à toutes les semaines, passées comprises. Changer le calendrier d'une personne change ses jours verrouillés sur toute sa grille.

### Saisie

12. **Verrou** : dans la grille d'une personne, un jour férié de son calendrier n'est pas saisissable. Aucun cran n'y est actif, et le plafond du jour y vaut 0.
13. **Verrou absolu** : aucun rôle ne peut lever le verrou pour une personne, pas même pour sa propre grille. Seule la direction peut rouvrir un jour, et pour tout un calendrier, en retirant le jour férié (règle 4).
14. **Contrôle à l'enregistrement** : un enregistrement sur un jour férié est refusé avec un message, et la case reprend sa valeur précédente. Cela vaut aussi quand la grille était ouverte avant que le jour ne devienne férié, par exemple dans un second onglet.
15. **Affichage** : la colonne d'un jour férié reste affichée, grisée, avec la mention « Férié » et son libellé (« Férié · Toussaint »). Un jour férié d'une semaine future est présenté de la même façon.
16. **Téléphone** : dans la présentation jour par jour, un jour férié apparaît dans le sélecteur des jours, et il est présenté verrouillé avec son libellé.
17. **Pas d'oubli** : un jour férié n'est jamais signalé comme un oubli. Il ne passe pas non plus au vert, puisqu'il n'est pas « complet » mais chômé.
18. **Maximum de la semaine** : le maximum d'une semaine est le plus petit de ces deux nombres : le maximum hebdomadaire de la personne, et le nombre de jours de la semaine qui ne sont pas fériés dans son calendrier. Avec un jour férié, 5 j et 4,5 j deviennent 4 j. Avec deux jours fériés, 4,5 j devient 3 j. L'en-tête de la grille affiche ce maximum (« 4 j / 4 j »). Le plafond de la semaine et les signaux d'oubli (règles 13 et 22 de la story 003) s'appuient sur lui.

### Consultation et réglage

19. **Page « Jours fériés »** : réservée à la direction. Pour l'année choisie (l'année en cours par défaut, avec navigation d'année en année) et pour chaque calendrier, elle liste les jours fériés avec leur date, leur jour de la semaine et leur libellé.
20. **Contenu de la page** :
    - les jours légaux qui tombent un samedi ou un dimanche y figurent, signalés comme tels, pour repérer les jours de remplacement à fixer ;
    - les jours ajoutés et les jours légaux retirés y sont distingués des jours légaux ;
    - c'est de cette page que la direction ajoute un jour, en retire un, ou annule un ajustement.
21. **Autres rôles** : un lead ou un membre de prod n'a pas accès à cette page. Il voit ses jours fériés dans sa grille.

## Critères d'acceptation

- [ ] Dans la grille d'une personne rattachée à la France, le 11 novembre 2026 est affiché grisé avec « Férié · Armistice 1918 », et aucun cran n'y est actif.
- [ ] Le 14 juillet est férié et le 21 juillet ordinaire pour une personne rattachée à la France ; l'inverse vaut pour une personne rattachée à la Belgique. Le 8 mai n'est férié que pour la France.
- [ ] Les fêtes mobiles suivent l'année : en 2027, le lundi de Pâques (29 mars), l'Ascension (6 mai) et le lundi de Pentecôte (17 mai) sont fériés dans les deux calendriers.
- [ ] Un enregistrement sur un jour férié, par exemple depuis un onglet ouvert avant l'ajout de ce jour, est refusé avec un message, et la case reprend sa valeur précédente.
- [ ] Un jour férié n'est jamais signalé comme un oubli, et sa colonne ne passe pas au vert.
- [ ] Sur une semaine qui contient un jour férié, l'en-tête affiche un maximum de 4 j pour une personne à 5 j comme pour une personne à 4,5 j, et la semaine est complète, sans signal d'oubli, une fois 4 j saisis.
- [ ] Sur un écran de téléphone, un jour férié est présenté verrouillé avec son libellé dans la présentation jour par jour.
- [ ] La direction choisit France ou Belgique sur la fiche d'une personne. Une personne inscrite sans choix, ou déjà inscrite avant la feature, suit le calendrier France. Ni un lead ni un membre de prod ne peut modifier ce rattachement.
- [ ] Passer une personne de France à Belgique change ses jours verrouillés sur toutes les semaines, passées comprises.
- [ ] La page « Jours fériés » liste, pour l'année choisie et pour chaque calendrier, les jours fériés avec leur date et leur libellé, y compris ceux qui tombent un week-end, signalés comme tels.
- [ ] La direction ajoute un jour férié à un calendrier avec un libellé. Ce jour est alors verrouillé dans la grille des seules personnes rattachées à ce calendrier, avec ce libellé.
- [ ] La direction retire un jour férié légal d'un calendrier, et ce jour redevient saisissable pour les personnes rattachées à ce calendrier. Annuler ce retrait le rend de nouveau férié.
- [ ] Un ajustement ne vaut que pour sa date : retirer le lundi de Pentecôte 2027 laisse férié le lundi de Pentecôte 2028.
- [ ] Ajouter un jour déjà férié, ajouter un samedi ou un dimanche, ajouter un jour sans libellé, ou retirer un jour qui n'est pas férié légal est refusé avec un message.
- [ ] Un lead ou un membre de prod n'accède pas à la page « Jours fériés ».

## Hors scope

- **Fermetures d'entreprise** (entre Noël et le Nouvel An, par exemple) : relèvent de `capacite-equipe`. Les ajustements servent aux jours fériés : jours de remplacement et jours fériés travaillés.
- **Reste de la ligne `capacite-equipe`** : temps de travail détaillé (C4.1), absences (C4.2) et fermetures (C4.3) ne sont pas livrés par cette story. Elle est pourtant rattachée à cette ligne : sa livraison cochera la ligne dans le backlog.
- **Jours fériés régionaux** : Alsace-Moselle (Vendredi saint, 26 décembre) et fêtes des communautés belges ne sont pas retenus. Un ajustement vaut pour tout un calendrier, jamais pour une partie des personnes qui le suivent.
- **Autres pays** : seuls la France et la Belgique sont proposés.
- **Report automatique d'un jour férié belge tombant un week-end** : le jour de remplacement est fixé par l'employeur, et la direction l'ajoute (règle 4).
- **Ajustement récurrent** (retirer le lundi de Pentecôte chaque année) : non retenu. Un ajustement vaut pour une date.
- **Déverrouillage individuel d'un jour férié** (astreinte, mise en production) : non retenu, le verrou est absolu (règle 13). Un travail isolé un jour férié n'est pas saisi.
- **Historique du rattachement d'une personne** : non retenu (règle 11).
- **Reprise des temps déjà saisis sur un jour qui devient férié** : non traitée, puisqu'aucune donnée de production n'existe à ce jour. Ces temps restent en l'état. Le traitement à retenir avant la mise en production est une question ouverte.
- **Consultation des calendriers par les leads et la prod** : non retenue. Ils voient leurs jours fériés dans leur grille.
- **Prise en compte des jours fériés par le rappel et le taux de saisie, la capacité ou la charge** : relève de `rappel-saisie`, `capacite-equipe` et `charge-vs-capacite`, qui s'appuieront sur ces calendriers.
- **Import ou synchronisation depuis un agenda ou un service externe** : non retenu (anti-objectif « pas d'intégration au départ »).

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement, et les libellés des jours fériés belges sont en français.
- **Droits d'accès** : oui. Le rattachement d'une personne, la page « Jours fériés » et ses ajustements sont réservés à la direction. Le verrou s'applique à tous les rôles, direction comprise, dans leur propre grille.
- **Cloisonnement des données** : non. Les jours fériés sont communs à tous ceux qui suivent un calendrier. Le rattachement d'une personne n'est visible que de la direction, sur la fiche Équipe.
- **Apparence / déclinaisons** : la grille (sur ordinateur et sur téléphone) présente les jours fériés verrouillés et nommés, et une nouvelle page est réservée à la direction. Pas de déclinaison.
- **Exposition à des tiers** : non. Les calendriers sont établis par l'application, sans service externe.
- **Emails / notifications** : non.
- **Données existantes** :
  - toutes les personnes déjà inscrites suivent le calendrier France ;
  - aucun temps existant n'est repris ;
  - les données de démonstration comptent une ou deux personnes rattachées à la Belgique et un jour de remplacement belge ajouté, et aucun temps saisi sur un jour férié.
- **Comportement par défaut** : sans aucune action de la direction, tout le monde suit le calendrier légal français, et ses jours fériés sont verrouillés dès la mise en service.

## Questions ouvertes

- **Temps saisis sur un jour qui devient férié, une fois en production** (à trancher avant la mise en service) : le cas se produira quand la direction ajoute un jour de remplacement après coup, ou quand elle change le calendrier d'une personne. Options : (a) les temps sont conservés et la case peut seulement diminuer ou être remise à 0, comme sur une semaine qui dépasse un maximum abaissé (règle 13 de la story 003) ; (b) les temps sont conservés et figés ; (c) l'ajustement ou le changement de calendrier est refusé tant que des temps existent sur les jours concernés.

---

## Annexe — Pistes pour le plan

- Calcul des fériés légaux : bibliothèque dédiée couvrant la France et la Belgique (Yasumi, par exemple), ou calcul maison à partir de la date de Pâques (`easter_days()`, extension `calendar`). Dans les deux cas, pas d'appel à un service externe. À confirmer.
- Rattachement : enum backed string dans `src/Enum/Type/` (`fr`, `be`), champ sur `User` avec défaut `fr` et migration avec valeur par défaut pour les comptes existants, champ dans `TeamMemberType`. À confirmer.
- Ajustements : entité dédiée (calendrier, date, ajout ou retrait, libellé), unique par couple (calendrier, date). À confirmer.
- Un service unique qui renvoie les jours fériés effectifs d'un calendrier entre deux dates (légaux, plus les ajouts, moins les retraits), avec leur libellé. Il serait consommé par `TimesheetBuilder`, pour la cellule verrouillée et le maximum effectif `min(WeeklyMaxManager::quartersFor(), 4 × jours non fériés)`, et par `TimesheetManager`, pour le refus à l'enregistrement. À confirmer.
- Page direction : contrôleur dédié sous `ROLE_DIRECTION`, navigation par année, formulaires d'ajout et de retrait. À confirmer.
- Fixtures de démonstration : exclure les jours fériés de la génération des temps ; rattacher une ou deux personnes à la Belgique ; ajouter un jour de remplacement. À confirmer.
- Ce service sera réutilisé par `rappel-saisie` (jours ouvrés) et `capacite-equipe`. Rien de plus à anticiper ici.
