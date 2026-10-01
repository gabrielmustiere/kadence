# Décrire chaque personne par ses compétences, son équipe et son manager

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/007-f-tags-hierarchie/`
> **Amont** : aucun

La direction décrit chaque personne par des tags (compétences techniques, expériences fonctionnelles, type d'équipe) tirés d'un référentiel qu'elle administre, et désigne son manager. Les leads s'appuient sur ces tags pour composer l'équipe d'une feuille, au lieu de se fier à leur mémoire.

## Contexte

Aujourd'hui, une personne dans Kadence se résume à un nom, un e-mail, un rôle d'accès (direction, lead, prod), un calendrier de jours fériés et un maximum de saisie hebdomadaire. Rien ne dit ce qu'elle sait faire, sur quels domaines métier elle a déjà travaillé, dans quel type d'équipe elle travaille, ni à qui elle rapporte.

Quand un lead compose l'équipe d'une feuille, il choisit des personnes dans une simple liste de noms. Il doit savoir de mémoire qui connaît telle technologie ou tel domaine métier. Avec 15 à 40 personnes, ça tient pour un lead ancien, pas pour un nouveau lead ni quand l'équipe grandit. De même, la structure hiérarchique (un CTO ou un CPO qui gère une grande équipe, des managers intermédiaires) n'existe que dans les têtes.

Si on ne fait rien, les équipes des feuilles restent composées par habitude, et toute lecture future de la capacité par compétence reste impossible, faute de donnée.

## Alignement vision

- **Problème adressé** : indirect. La feature sert le symptôme « planning intenable » en aidant à composer l'équipe d'une feuille selon les compétences réelles. Le lien hiérarchique ne s'attaque pas au problème central : c'est une information d'organisation, assumée comme telle.
- **Audience servie** : la direction (référentiel, profils, hiérarchie) et les leads (composition des équipes de feuille). L'équipe de prod n'a rien de plus à saisir.
- **Principes respectés** :
  - **Principe 2 (« jamais des personnes ») :** il est mis sous tension, mais la tension est maîtrisée. Les tags sont binaires et descriptifs, sans niveau. Chacun ne voit que les siens hors direction et composition d'équipe, et le manager n'ouvre aucun droit de regard. L'anti-objectif « évaluation individuelle » est respecté.
  - **Principe 1 (saisie) :** il n'est pas touché.
  - **Anti-objectif « pas d'intégration au départ » (SIRH) :** la hiérarchie est saisie dans Kadence, ce qui le respecte.
- **Hypothèse testée** : aucune directement.
- **Impact North Star** : indirect et faible. Des équipes de feuille mieux composées rendent la planification plus crédible, sans effet mesurable isolable.

## Utilisateurs concernés

- **Direction** : elle administre le référentiel de tags, renseigne les tags et le manager de chaque personne, et filtre la liste Équipe par tag ou par manager.
- **Lead** : il voit les tags des personnes et filtre par tag en composant l'équipe d'une feuille. Il ne voit pas le manager des autres. Comme tout le monde, il voit ses propres tags et son manager sur sa page Mon compte.
- **Membre de la prod** : il voit ses propres tags et son manager, en lecture seule, sur sa page Mon compte. Il ne voit ni les tags ni le manager des autres.
- **Manager désigné** (toute personne active, quel que soit son rôle) : aucun changement perçu. Être manager n'ouvre aucun droit.

## User Stories

- En tant que **direction**, je veux créer, renommer et supprimer les tags de chaque type dans un écran d'administration, afin de disposer d'un vocabulaire commun et sans doublons.
- En tant que **direction**, je veux attribuer à une personne ses compétences techniques, ses expériences fonctionnelles et son type d'équipe quand je l'inscris ou la modifie, afin de tenir son profil à jour au même endroit que le reste de sa fiche.
- En tant que **direction**, je veux créer un tag manquant directement depuis le formulaire d'une personne, afin de ne pas interrompre la saisie de son profil par un aller-retour dans l'administration.
- En tant que **direction**, je veux désigner le manager d'une personne, afin de rendre lisible qui rapporte à qui.
- En tant que **direction**, je veux filtrer la liste Équipe par tag ou par manager, afin de retrouver qui sait faire quoi ou qui compose l'équipe d'un manager.
- En tant que **direction**, je veux ne pas pouvoir désactiver une personne qui manage encore des personnes actives, afin que personne ne reste rattaché à un manager parti.
- En tant que **lead**, je veux voir les tags de chaque personne et filtrer par tag quand je compose l'équipe d'une feuille, afin de choisir les bonnes personnes sans me fier à ma mémoire.
- En tant que **membre de la prod**, je veux ne voir ni les tags ni le manager de mes collègues, afin que Kadence reste un outil de pilotage de projets et non de profilage des personnes.
- En tant que **membre de l'équipe**, je veux voir mes propres tags et mon manager sur ma page Mon compte, afin de savoir ce que Kadence dit de moi.

## Règles métier

**Référentiel de tags**

1. Il existe trois types de tag, fixes : **compétence technique**, **expérience fonctionnelle** et **type d'équipe**. La direction ne crée pas d'autre type.
2. Un tag appartient à un seul type, définitivement : on ne change pas un tag de type.
3. Le libellé d'un tag est obligatoire. Il est unique au sein de son type, sans tenir compte de la casse ni des espaces en début et en fin (« Symfony » et « symfony » sont le même tag). Un même libellé peut exister dans deux types différents (« Support » comme type d'équipe et comme expérience fonctionnelle).
4. Renommer un tag met à jour son libellé sur toutes les personnes qui le portent. Un renommage qui créerait un doublon dans le type est refusé.
5. Supprimer un tag demande une confirmation qui annonce le nombre de personnes, actives ou non, qui le portent. La suppression le retire de leurs profils et ne se défait pas.
6. Seule la direction accède au référentiel de tags.

**Attribution des tags**

7. Seule la direction attribue des tags à une personne, à l'inscription comme à la modification.
8. Une personne porte zéro, une ou plusieurs compétences techniques ; zéro, une ou plusieurs expériences fonctionnelles ; zéro ou un type d'équipe.
9. Aucun tag n'est obligatoire.
10. Dans le formulaire d'une personne, saisir un libellé absent du type crée le tag dans le référentiel. Saisir un libellé déjà présent, à la casse ou aux espaces près, reprend le tag existant, sans doublon. Le tag n'est créé qu'à l'enregistrement de la personne : abandonner ou échouer la saisie ne laisse aucun tag orphelin.
11. Une personne désactivée garde ses tags.

**Visibilité**

12. Les tags d'une personne sont visibles par la direction (liste Équipe, formulaire de la personne), par les leads mais seulement dans la composition de l'équipe d'une feuille, et par la personne elle-même sur sa page Mon compte.
13. Le manager d'une personne n'est visible que par la direction et par la personne elle-même, sur sa page Mon compte.
14. Un membre de la prod ne voit ni les tags ni le manager des autres. Il voit les siens, en lecture seule, sur sa page Mon compte.

**Composition de l'équipe d'une feuille**

15. Dans le choix d'une personne, chaque personne proposée est accompagnée de ses tags, des trois types.
16. Un filtre par tag restreint les personnes proposées à celles qui portent les tags choisis : au plus un tag par type, et la personne doit porter tous les tags choisis. Il ne retire jamais un membre déjà présent dans l'équipe de la feuille et ne change aucune règle existante de la composition (parts, refus de surcharge).

**Liste Équipe**

17. La liste Équipe affiche, pour chaque personne, ses tags et son manager.
18. La liste Équipe se filtre par tag (au plus un par type, tous requis, comme en composition) et par manager. Le filtre par manager retient les personnes dont c'est le manager **direct**.

**Lien hiérarchique**

19. Le manager est facultatif ; une personne en a au plus un.
20. Le manager est une personne active, de n'importe quel rôle d'accès. Le lien hiérarchique est indépendant du rôle : un membre de la prod peut manager, un lead peut n'avoir personne à manager.
21. Une personne ne peut pas être son propre manager, ni avoir pour manager une personne qu'elle manage directement ou indirectement (pas de cycle).
22. Désactiver une personne est refusé tant qu'au moins une personne active la désigne comme manager. Le refus nomme ces personnes, pour que la direction les rattache à quelqu'un d'autre.
23. Quand une personne est désactivée, les personnes déjà désactivées qui la désignaient comme manager perdent ce lien. Ainsi, un manager affiché est toujours une personne active, et une personne réactivée dont le manager est parti revient sans manager.
24. Être manager n'ouvre aucun droit et ne donne accès à aucune donnée supplémentaire.

## Critères d'acceptation

- [ ] La direction accède à un écran Tags depuis le menu Administration ; un lead et un membre de la prod n'y ont pas accès.
- [ ] La direction crée un tag dans chacun des trois types : compétence technique, expérience fonctionnelle, type d'équipe.
- [ ] Créer un tag dont le libellé existe déjà dans le même type, à la casse ou aux espaces près, est refusé avec un message explicite.
- [ ] Un même libellé peut être créé dans deux types différents.
- [ ] Renommer un tag met à jour son libellé sur toutes les personnes qui le portent.
- [ ] Supprimer un tag porté par des personnes demande une confirmation qui annonce leur nombre, puis le retire de leurs profils.
- [ ] À l'inscription comme à la modification d'une personne, la direction lui attribue plusieurs compétences techniques, plusieurs expériences fonctionnelles et au plus un type d'équipe.
- [ ] Dans le formulaire d'une personne, saisir un libellé absent du référentiel crée le tag, qui apparaît ensuite dans l'écran Tags.
- [ ] Dans le formulaire d'une personne, saisir un libellé existant à la casse près réutilise le tag existant, sans créer de doublon.
- [ ] Une personne s'enregistre sans aucun tag ni manager.
- [ ] La direction désigne le manager d'une personne parmi les personnes actives.
- [ ] Désigner comme manager la personne elle-même, une personne désactivée ou une personne qu'elle manage directement ou indirectement est refusé.
- [ ] La liste Équipe affiche les tags et le manager de chaque personne.
- [ ] La liste Équipe se filtre par tag et par manager direct.
- [ ] Désactiver une personne désignée comme manager par au moins une personne active est refusé, et le message nomme ces personnes.
- [ ] Désactiver une personne dont seules des personnes désactivées dépendent retire ce lien de leurs profils.
- [ ] En composant l'équipe d'une feuille, un lead voit les tags de chaque personne proposée.
- [ ] En composant l'équipe d'une feuille, un lead restreint les personnes proposées à celles qui portent les tags choisis, sans que les membres déjà présents soient retirés.
- [ ] Un lead ne voit nulle part le manager d'une autre personne.
- [ ] Un membre de la prod ne voit nulle part les tags ni le manager d'une autre personne.
- [ ] Chaque personne voit, en lecture seule sur sa page Mon compte, ses tags et son manager.
- [ ] Après la mise en production, les personnes existantes apparaissent sans tag ni manager, et aucun écran existant ne change de comportement.

## Hors scope

- **Capacité par compétence** (charge et capacité ventilées par compétence ou type d'équipe) : la vue charge face à capacité semaine par semaine n'existe pas encore. À reprendre avec `charge-vs-capacite`, en tranchant le cas d'une personne qui porte plusieurs compétences.
- **Niveau de compétence** (débutant, confirmé, expert) : trop proche de l'évaluation individuelle (anti-objectif) ; les tags restent binaires.
- **Auto-déclaration** des compétences par la personne : seule la direction attribue.
- **Création de tags par un lead** : un lead voit et filtre, il ne crée ni n'attribue.
- **Nouveaux types de tag** : les trois types sont fixes.
- **Organigramme** et **liste des personnes managées sur la fiche d'un manager** : la hiérarchie se lit dans la colonne Manager de la liste Équipe.
- **Droits liés au manager** (voir les temps, la charge ou les absences de son équipe) : contraire au principe 2.
- **Intitulé de poste** (CTO, CPO, Lead dev…) : CTO et CPO ne sont que des exemples de managers.
- **Filtrer la roadmap ou les vues de planification par type d'équipe ou par manager.**
- **Tags sur une feuille** (compétences requises par une feuille, rapprochement avec les personnes) : piste d'évolution, non cadrée.
- **Historique des tags et du manager** d'une personne : seul l'état courant compte.
- **Import en masse** des tags ou de la hiérarchie : 15 à 40 personnes se renseignent à la main.

## Impacts transverses

- **Traduction / langues** : non, l'interface est en français uniquement. Libellés nouveaux : les trois types de tag, l'écran Tags, les champs du formulaire, la colonne Manager et les filtres.
- **Droits d'accès** : pas de nouveau rôle. L'écran Tags, l'attribution des tags et le manager sont réservés à la direction. Les leads voient les tags dans la composition de l'équipe d'une feuille, et seulement là. Chacun voit les siens sur sa page Mon compte.
- **Cloisonnement des données** : oui, au sein de l'équipe. Les tags et le manager d'une personne sont cachés aux autres membres de la prod, et le manager est caché aux leads ; seule la personne elle-même les voit sur sa page Mon compte.
- **Apparence / déclinaisons** : non.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non. Aucune notification à l'attribution d'un tag ou d'un manager.
- **Données personnelles** : les compétences, l'expérience et le rattachement hiérarchique sont des données personnelles. Elles entrent dans l'information préalable des salariés déjà prévue avant le déploiement (règle transverse du backlog).
- **Données existantes** : aucune reprise. Les personnes existantes démarrent sans tag ni manager, et la direction les complète au fil de l'eau.
- **Comportement par défaut** : tant que rien n'est renseigné, la liste Équipe et la composition de l'équipe d'une feuille affichent les personnes sans tag, et tout fonctionne comme avant.

## Questions ouvertes

- **Accès d'une personne à ses propres tags** : une personne de la prod ne voit pas les tags qu'on lui a attribués, alors que ce sont des données personnelles qui la concernent. Options : (a) jamais dans Kadence, le droit d'accès s'exerçant hors de l'outil ; (b) en lecture seule sur sa page de compte. → tranché : (b), manager compris (règles 12 à 14).
- **Combinaison de plusieurs tags dans un filtre** (composition d'équipe et liste Équipe) : options : (a) la personne porte **tous** les tags choisis ; (b) elle en porte **au moins un** ; (c) **tous** les types choisis, mais **au moins un** tag par type (« Symfony ou React » et « Support »). → tranché : au plus un tag par type, et la personne doit porter tous les tags choisis (règles 16 et 18).
- **Tag créé à la volée puis formulaire abandonné** : options : (a) le tag n'existe qu'une fois la personne enregistrée, donc abandonner ou échouer la saisie ne laisse aucun tag orphelin ; (b) le tag est créé dès sa saisie, même si la personne n'est pas enregistrée. → tranché : (a) (règle 10).
- **Rattachement au backlog** : la feature n'a aucune ligne dans le backlog produit. Il faut une capacité dans « Équipe & accès » et un horizon, à poser avec `/forge:product-backlog`.

---

## Annexe — Pistes pour le plan

- **Personne :** c'est `User` (`src/Entity/User.php`). Son inscription et sa modification passent par `TeamMemberType` et le DTO `TeamMemberInput`, avec `TeamController` (`#[IsGranted('ROLE_DIRECTION')]`) et `templates/team/`. À confirmer.
- **Tag :** une entité dédiée, avec son type en backed string enum dans `src/Enum/Type/` et une unicité (type, libellé normalisé). Pour les relations avec `User`, deux formes sont à trancher : un many-to-many pour les compétences et les expériences, plus un many-to-one pour le type d'équipe ; ou un many-to-many unique, avec la règle « au plus un type d'équipe » portée par la validation.
- **Manager :** une auto-référence `User → User`, nullable. La détection de cycle et le refus de désactivation iraient dans `TeamManager`, à côté du précédent `LastActiveDirectorException`, que lève déjà `TeamController::deactivate()`.
- **Création à la volée :** `symfony/ux-autocomplete` (Tom Select, option `create`) n'est pas installé. Les options : l'ajouter (nouvelle dépendance, potentiellement candidate à une ADR), ou un contrôleur Stimulus maison. À confirmer.
- **Composition d'équipe :** `LotMemberType` (`choice_label` aujourd'hui limité au nom) et `templates/lot/_member_row.html.twig`, dans une collection pilotée par `form-collection`. Avec 15 à 40 personnes, un filtre côté client suffirait probablement. À confirmer.
- **Menu Administration :** `templates/base.html.twig` (story 005), où l'entrée Tags rejoindrait Projets, Équipe et Jours fériés.
- **Fixtures :** `fixtures/DemoCompanyFixtures.php`, pour donner des tags et une hiérarchie de démonstration (un CTO, un CPO et leurs équipes).
