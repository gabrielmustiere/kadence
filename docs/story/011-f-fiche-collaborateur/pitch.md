# Consulter la fiche d'une personne : sa charge, ses affectations à venir et la timeline de tout son travail saisi

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/011-f-fiche-collaborateur/`
> **Amont** : aucun

Chaque personne a une fiche de consultation, ouverte à toute personne connectée, sur le modèle de la fiche d'un projet. On y trouve sur une seule page le temps qu'elle a saisi sur chaque projet. Une frise montre ses tronçons de travail passés et ses affectations à venir, avec sa charge pour les leads et la direction. Une timeline donne ce qui l'attend, puis tout ce qu'elle a fait, du plus récent au plus ancien.

## Contexte

Aujourd'hui, rien dans Kadence ne se lit par personne. Pour savoir sur quoi une personne est affectée et quand elle se libère, un lead parcourt la roadmap, déplie chaque projet et survole l'équipe de chaque feuille. Sa charge est pourtant calculée jour par jour. Elle ne se manifeste qu'au moment d'un geste de planification, sous la forme d'un refus, ou par le signal « à replanifier » sur une feuille. Composer ou replanifier une équipe se fait donc à tâtons, ou en demandant directement aux personnes si elles sont disponibles.

Côté passé, la fiche d'un projet raconte qui a travaillé sur ce projet, mais aucune page ne dit sur quoi une personne a travaillé, tous projets confondus. Pour répondre à « sur quoi est X ? » ou pour préparer un point individuel, la direction reconstitue l'histoire d'une personne en ouvrant les fiches projet une à une.

**Mesure de succès** : deux semaines après la livraison, un lead compose ou replanifie une équipe en lisant la charge et les affectations à venir sur les fiches des personnes, sans leur demander si elles sont disponibles.

## Alignement vision

- **Problème adressé** : le symptôme « planning intenable » de la vision. Le lead voit qui est disponible et quand avant d'affecter, au lieu de le découvrir par un refus de planification. La question « où en sont réellement les projets » est servie indirectement : chacun sait sur quoi est une personne et quand elle se libère.
- **Audience servie** : les leads, pour composer et replanifier. La direction, pour savoir sur quoi est une personne et pour préparer un point individuel. L'équipe de prod, pour consulter sa propre fiche et s'informer sur ce que font ses collègues.
- **Principes respectés** :
  - **Principe 1 respecté** : la saisie quotidienne n'est pas touchée, et la fiche ne s'ouvre pas depuis Ma semaine.
  - **Principe 3 respecté** : tout se lit au niveau de la feuille, jamais de la tâche.
  - **Principe 4 non concerné** : la date annoncée n'existe pas encore (ligne `jalons-dates-annoncees` non livrée). La fiche ne montre que des fins calculées.
  - **Principe 2 contredit, et non plus seulement sous tension** : la fiche consolide par personne tout son travail saisi, sur toute son histoire, visible de toute personne connectée. Elle est destinée notamment à préparer un point individuel. Cela contredit le principe 2 (« on pilote des projets et une capacité, jamais des personnes ; aucune vue de productivité individuelle ») et l'anti-objectif « évaluation individuelle ». Cela renverse aussi la règle transverse du backlog selon laquelle le détail des temps d'une personne n'est visible que par elle-même. Les stories 009 et 010 montraient déjà le temps de chacun, mais toujours rapporté à un projet ou à une feuille ; ici, la personne devient l'objet de la page. Le porteur assume cette contradiction sans modifier la vision au préalable : la vision est à réaligner par `/vision` (principe 2 et anti-objectif) avant la mise en service. _(Vision réalignée au sync post-livraison : principe 2 et anti-objectif reformulés autour de l'absence de comparaison, de classement et d'indicateur de productivité.)_
  - **Garde-fous retenus** : aucune comparaison, aucun classement ni aucun indicateur de productivité entre personnes (moyennes, ratios, taux de saisie individuel). La charge, la surcharge, les tags et le manager d'une personne restent réservés aux rôles qui y avaient déjà accès.
- **Hypothèse testée** : aucune directement. La story **met en jeu H1** plus qu'elle ne la teste : la vision fait du principe 2 « la condition d'une saisie honnête », et le risque de rejet par l'équipe (« flicage ») devient concret. H3 est servie indirectement : une fin calculée qui recule se rattache aux personnes qui portent la feuille et à leur charge.
- **Impact North Star** : indirect et faible. Une équipe composée en connaissant la charge réelle tient mieux ses dates, sans que la story produise de jalon.

## Utilisateurs concernés

- **Direction** : ouvre la fiche de n'importe quelle personne et y voit tout : profil, charge, affectations à venir, temps par projet et journal de ses tronçons.
- **Lead** : voit la même fiche que la direction, sauf le manager de la personne, réservé à la direction et à la personne elle-même (story 007). Il la consulte pour composer ou replanifier une équipe. La planification reste sur la feuille, inchangée.
- **Prod** : ouvre la fiche de n'importe quelle personne, sans le profil ni la charge des autres. Il ouvre sa propre fiche, complète, depuis le menu de son compte. Sa saisie est inchangée.
- **Personne consultée** (tout le monde) : tout son travail saisi, son historique et ses affectations à venir deviennent lisibles de toute personne connectée.
- **Personne désactivée** : garde une fiche consultable, marquée « désactivée », avec tout son historique.

## User Stories

- En tant que **lead**, je veux voir la charge d'une personne dans le temps et ses affectations à venir, afin de savoir quand elle se libère avant de l'affecter à une feuille.
- En tant que **direction**, je veux lire sur la fiche d'une personne sur quoi elle est et sera affectée, afin de répondre à « sur quoi est X ? » sans demander au lead.
- En tant que **direction**, je veux lire tout le travail saisi d'une personne, projet par projet et tronçon par tronçon, afin de préparer un point individuel.
- En tant que **personne connectée**, je veux ouvrir la fiche d'une personne en cliquant sur son nom dans la timeline de la fiche d'un projet, afin de passer d'un projet aux personnes qui le portent.
- En tant que **membre de l'équipe**, je veux ouvrir ma propre fiche depuis le menu de mon compte, afin de retrouver tout ce que j'ai fait et ce qui m'attend.
- En tant que **direction**, je veux ouvrir la fiche d'une personne depuis l'administration de l'équipe, afin de passer de sa gestion à son activité.
- En tant que **membre de prod**, je ne veux PAS voir la charge, la surcharge, les tags ni le manager d'un collègue, car ils ne servent qu'à la planification et à la direction.
- En tant que **personne connectée**, je ne veux PAS trouver de comparaison, de classement ni d'indicateur de productivité entre personnes.

## Règles métier

**Accès**

1. **Ouverture** : le nom d'une personne ouvre sa fiche dans l'équipe des entrées de la timeline d'une fiche projet, « hors équipe » compris. Les infobulles de la roadmap et de la frise restent sans lien. _(Règle amendée au plan : les noms des infobulles devaient aussi être des liens ; question ouverte tranchée en (b).)_ Dans l'administration de l'équipe, la ligne de chaque personne mène à sa fiche. Une entrée « Ma fiche » du menu du compte ouvre sa propre fiche.
2. **Menu** : la fiche n'a pas d'entrée propre dans le menu principal, et aucune entrée du menu principal n'y est mise en évidence.
3. **Lecture seule** : la fiche ne porte aucune action de gestion. Les noms de projets mènent à la fiche du projet ; les titres des lots et des feuilles n'y sont pas des liens.
4. **Ce que chacun voit** : toute personne connectée voit l'en-tête (nom et temps saisi par projet), la frise sans ligne de charge, le bloc « À venir » et le journal. Le reste suit la règle 5.
5. **Données réservées** : sur la fiche d'une personne, la charge (ligne de charge, charge du jour, « libre à partir du … ») et les tags ne sont visibles que de la direction, des leads et de la personne elle-même. Son manager n'est visible que de la direction et de la personne elle-même. Son rôle suit la même règle que ses tags.

**En-tête**

6. **Identité** : le nom de la personne, marqué « désactivée » le cas échéant, puis son profil selon la règle 5 : rôle, tags (compétences techniques, expériences fonctionnelles, type d'équipe) et manager.
7. **Temps par projet** : le temps total que la personne a saisi sur chaque projet, sur toute son histoire. Les projets sont listés du plus récemment travaillé au plus ancien, selon le dernier jour saisi. Aucun total tous projets confondus, aucune moyenne, aucun ratio.
8. **Charge actuelle** (règle 5) : la charge de la personne au prochain jour ouvré après aujourd'hui, avec sa date, et la date « libre à partir du … ». _(Règle amendée au plan : la planification ne charge jamais le jour courant, qui appartient à la saisie ; la charge d'aujourd'hui vaudrait toujours 0 %.)_ Cette date est le premier jour ouvré après le dernier jour où sa charge n'est pas nulle. Elle vaut « inconnue » dès qu'une de ses affectations n'a pas de fin calculée (feuille sans début, à estimer ou en dépassement), et la feuille en cause est nommée. Une personne sans aucune affectation n'a pas de date, et l'en-tête l'indique.

**Frise**

9. **Lignes** : une ligne par feuille sur laquelle la personne a saisi ou à l'équipe de laquelle elle appartient, regroupées sous leur projet, dans l'ordre de la roadmap.
10. **Contenu d'une ligne** : uniquement la part de la personne. On y voit ses tronçons saisis, puis la partie à venir de la feuille tant qu'elle en fait partie, marquée de sa part (25, 50, 75 ou 100 %) jusqu'à la fin calculée. Le travail des autres membres de l'équipe et la partie restante de la feuille n'y figurent pas.
11. **Tronçons** : ses tronçons sont coupés à chacun de ses jours ouvrés sans saisie sur la feuille, en jours ouvrés selon son calendrier de jours fériés. Ils suivent le même partage que la roadmap entre la partie saisie et la partie au-delà de l'estimation ; un tronçon au-delà de l'estimation est marqué comme tel.
12. **Signaux** : chaque feuille porte ses signaux tels que la roadmap les affiche, « à replanifier » restant réservé aux leads et à la direction. Une feuille non planifiée à l'équipe de laquelle la personne appartient apparaît sans barre d'affectation, avec son signal (« à estimer », « sans début » ou « sans équipe »).
13. **Ligne de charge** (règle 5) : en tête de frise, la charge de la personne jour par jour à partir d'aujourd'hui, c'est-à-dire la somme de ses parts sur les feuilles dont la partie à venir couvre ce jour (story 006). Le passé n'a pas de charge. Les jours au-delà de 100 % sont mis en évidence.
14. **Infobulles** : survoler un tronçon affiche la feuille, la période, le temps que la personne y a saisi et son nombre de jours avec saisie. Survoler une affectation à venir affiche la feuille, la part de la personne, le début et la fin calculée. Une seule infobulle s'affiche à la fois.
15. **Bornes et zoom** : mêmes règles que la frise de la fiche d'un projet. La frise va du premier jour connu de la personne au dernier : le plus tôt entre son premier jour saisi et le premier début de ses affectations, et le plus tard entre son dernier jour saisi et la fin calculée la plus tardive de ses affectations. Elle couvre des semaines entières, sur au moins 4 semaines, et défile horizontalement si elle est longue. Le zoom va de ×1 à ×8 ; la fiche s'ouvre toujours à ×1, centrée sur aujourd'hui ou sur le bord le plus proche, et le palier n'est pas mémorisé. Le repère « aujourd'hui » n'apparaît que s'il tombe entre les bornes.

**Timeline**

16. **À venir** : en tête de timeline, un bloc liste les affectations de la personne dont la feuille n'est pas terminée : sa fin calculée tombe après aujourd'hui, ou elle est inconnue (feuille non planifiée, en dépassement ou au-delà de l'horizon de calcul). _(Précision apportée au plan : une feuille en dépassement, sans partie à venir, y figure avec son signal, comme dans « libre à partir du … ».)_ Chaque affectation est triée par début, les feuilles sans début à la fin, et indique la feuille (projet, lot, puis sous-lot s'il y en a un), la part de la personne, le début et la fin calculée quand la feuille en a, et les signaux de la feuille. _(Précision apportée à l'implémentation : les signaux s'affichent aussi pour une feuille planifiée, par exemple « à replanifier ».)_
17. **Journal** : sous le bloc « À venir », une entrée par tronçon saisi par la personne, toutes feuilles mêlées, du plus récent au plus ancien selon le dernier jour du tronçon. Un intertitre marque chaque mois, et un tronçon se range sous le mois de son dernier jour. Tout l'historique est affiché, sans pagination.
18. **Contenu d'une entrée** : la feuille (projet, lot, puis sous-lot s'il y en a un), la période du tronçon (premier et dernier jour), le temps que la personne y a saisi et son nombre de jours avec saisie. Un tronçon au-delà de l'estimation est marqué comme tel.

**Cas limites**

19. **Sans saisie ni affectation** : la fiche affiche l'en-tête, sans frise, et des messages l'indiquent dans le bloc « À venir » et dans le journal.
20. **Sans date** : une personne dont aucune feuille n'a de début et qui n'a rien saisi n'a pas de frise ; un message l'indique, et ses affectations sur des feuilles non planifiées restent listées dans « À venir ».
21. **Hors équipe** : une feuille sur laquelle la personne a saisi sans faire partie de son équipe a sa ligne dans la frise, avec ses seuls tronçons, et ses tronçons figurent dans le journal.
22. **Personne désactivée** : sa fiche reste accessible à tous, marquée « désactivée », avec tout son historique et ses affectations telles que la planification les garde. Sans capacité, elle n'a pas de charge : l'en-tête l'indique et la frise n'a pas de ligne de charge. _(Précision apportée au plan.)_ Son nom reste un lien partout où il apparaît barré.

## Critères d'acceptation

Exemple de référence : une personne de prod, « Alice », au calendrier France, avec un maximum hebdomadaire de 5 j. Elle fait partie à 100 % de l'équipe de la feuille « API » (projet « Refonte », 10 j estimés), qu'elle saisit d'une journée pleine du lundi 07/09 au vendredi 11/09, puis du lundi 21/09 au vendredi 25/09. Elle saisit aussi une journée pleine du lundi 14/09 au mercredi 16/09 sur la feuille « Maintenance » (projet « Support »), dont elle ne fait pas partie de l'équipe. Elle seule compose l'équipe de la feuille « Login » (projet « Mobile », lot « Front », 8 j estimés), à 50 %, à partir du lundi 05/10. Aujourd'hui est le vendredi 02/10.

- [ ] Sur une fiche projet, le nom d'une personne dans l'équipe d'une entrée de la timeline, « hors équipe » compris, ouvre sa fiche ; les infobulles n'ont pas de lien.
- [ ] Dans l'administration de l'équipe, la ligne de chaque personne mène à sa fiche, et l'entrée « Ma fiche » du menu du compte ouvre la fiche de la personne connectée.
- [ ] Un membre de prod qui ouvre la fiche d'Alice voit le temps par projet, la frise, le bloc « À venir » et le journal, mais ni son rôle, ni ses tags, ni son manager, ni sa charge, ni « libre à partir du … », ni la ligne de charge.
- [ ] Alice voit tout sur sa propre fiche ; la direction voit tout sur toute fiche ; un lead voit tout sauf le manager. Aucune action de gestion n'y figure.
- [ ] Dans l'exemple, l'en-tête liste « Refonte » avec 10 j saisis, puis « Support » avec 3 j saisis, sans total ni moyenne.
- [ ] Dans l'exemple, pour un lead, l'en-tête affiche une charge de 50 % au prochain jour ouvré, le lundi 05/10, et « libre à partir du 27/10 », et la ligne de charge est à 50 % du lundi 05/10 au lundi 26/10.
- [ ] Si Alice fait aussi partie de l'équipe d'une feuille sans début, « libre à partir du … » vaut « inconnue » et nomme cette feuille.
- [ ] Quand la charge d'une personne dépasse 100 % (surcharge passive), la ligne de charge met ces jours en évidence pour un lead, la direction et la personne elle-même.
- [ ] Dans l'exemple, la frise montre sous « Refonte » la ligne « API » avec deux tronçons (07/09 au 11/09, 21/09 au 25/09), sous « Support » la ligne « Maintenance » avec un tronçon (14/09 au 16/09), et sous « Mobile » la ligne « Front · Login » avec une affectation à 50 % du 05/10 au 26/10.
- [ ] Dans l'exemple, la frise va du lundi 07/09 au dimanche 01/11, s'ouvre à ×1 et se zoome jusqu'à ×8 comme celle d'une fiche projet.
- [ ] Survoler un tronçon de la frise affiche la feuille, la période, le temps saisi par la personne et ses jours avec saisie ; survoler une affectation affiche la feuille, la part, le début et la fin calculée.
- [ ] Dans l'exemple, le bloc « À venir » liste « Mobile · Front · Login », 50 %, du 05/10 au 26/10.
- [ ] Dans l'exemple, le journal présente, sous l'intertitre « Septembre 2026 » et dans cet ordre : « Refonte · API » du 21/09 au 25/09 avec 5 j saisis et 5 jours avec saisie ; « Support · Maintenance » du 14/09 au 16/09 avec 3 j saisis ; « Refonte · API » du 07/09 au 11/09 avec 5 j saisis.
- [ ] Un tronçon de la personne situé au-delà de l'estimation de sa feuille est marqué comme tel dans la frise et dans le journal.
- [ ] Les noms de projets de la fiche mènent à la fiche du projet ; les titres des lots et des feuilles ne sont pas des liens.
- [ ] La fiche d'une personne désactivée reste accessible, marquée « désactivée », avec tout son historique.
- [ ] Une personne sans saisie ni affectation a une fiche sans frise, avec un message dans le bloc « À venir » et dans le journal.

## Hors scope

- **Comparaison, classement et indicateurs de productivité** (moyennes, ratios, temps total tous projets confondus, taux de saisie individuel, jours sans saisie mis en évidence) : exclus, ce sont les garde-fous de la story.
- **Liste de consultation de l'équipe et entrée dans le menu principal** : non retenues. On arrive sur la fiche par un nom, par l'administration de l'équipe ou par « Ma fiche ».
- **Lien depuis Ma semaine** : non, pour ne rien ajouter à la saisie (principe 1).
- **Lien de retour vers la page d'origine** : non. La fiche s'ouvre depuis plusieurs endroits, et le retour se fait par le navigateur ou par le menu.
- **Actions de gestion sur la fiche** (affecter, modifier le profil ou le maximum hebdomadaire) : non, la fiche est en lecture seule.
- **Travail des autres membres et partie restante des feuilles dans la frise** : non, la frise ne montre que la part de la personne.
- **Charge passée** : non. Comme sur la planification (story 006), le passé n'a pas de charge.
- **Absences et congés dans la charge** : non, ils ne sont pas encore déduits de la capacité (ligne `capacite-equipe`).
- **Filtre par période ou par projet, pagination ou repli du journal** : non, tout l'historique est affiché.
- **Interaction entre le journal et la frise** (surbrillance, filtre) : non.
- **Export ou impression de la fiche** : non.
- **Notification à la personne quand sa fiche est consultée** : non.
- **Modification de la vision** : hors de cette story, elle passe par `/vision` (voir Alignement vision).

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement ; les libellés ajoutés (fiche, « Ma fiche », « À venir », « libre à partir du », « désactivée », intertitres de mois) sont en français.
- **Droits d'accès** : une nouvelle page ouverte à toute personne connectée, en lecture seule, avec des données réservées : la charge et les tags à la direction, aux leads et à la personne elle-même ; le manager à la direction et à la personne elle-même. Les leads voient désormais les tags d'une personne hors de la composition d'une équipe. Aucune action nouvelle.
- **Cloisonnement des données** : pas de multi-organisation. En revanche, la story change qui voit les données de qui : le travail saisi de chaque personne, consolidé sur toute son histoire, devient lisible par toute personne connectée. Elle contredit le principe 2 et la règle transverse « pas de lecture individuelle » (voir Alignement vision).
- **Apparence / déclinaisons** : non. Une seule apparence.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune reprise. Les temps saisis, les équipes et les profils existants suffisent ; tout l'historique déjà saisi devient lisible par personne dès la livraison.
- **Comportement par défaut** : la roadmap, la fiche projet et l'administration de l'équipe gardent leur comportement ; les noms de personnes de la timeline d'une fiche projet deviennent des liens, la liste de l'équipe gagne un lien vers chaque fiche, et le menu du compte gagne « Ma fiche ».
- **Cadre social** : les temps saisis sont des données personnelles, et la vision prévoit l'information des salariés et, selon l'effectif, la consultation du CSE. Une fiche par personne, ouverte à tous et destinée aussi aux points individuels, entre dans ce cadre (voir Questions ouvertes).

## Questions ouvertes

- **Noms dans les infobulles** : une infobulle s'affiche au survol et se ferme quand la souris la quitte ; un nom cliquable à l'intérieur n'est atteignable que si elle reste ouverte. Options : (a) l'infobulle reste ouverte tant que la souris est dessus, et ses noms sont des liens ; (b) les noms ne sont des liens que hors des infobulles, dans les entrées de la timeline d'une fiche projet ; (c) les noms des infobulles mènent à la fiche via un autre geste (clic sur le tronçon, par exemple). → tranché : (b), une infobulle ne peut pas contenir d'élément focusable et se ferme dès qu'on quitte son déclencheur.
- **Mise en service** : la fiche rend le travail de chacun lisible de tous. Options : (a) livrer et informer l'équipe en parallèle ; (b) ne mettre la fiche en service qu'après l'information des salariés et, selon l'effectif, la consultation du CSE ; (c) livrer « Ma fiche » d'abord, et ouvrir les fiches des autres après l'information.
- **Téléphone** : la frise de la fiche projet garde au moins la largeur de celle de la roadmap et défile. Options : (a) même règle ; (b) frise masquée sur téléphone, seuls l'en-tête et la timeline s'affichent. → tranché : (a), même frise que la fiche projet.

---

## Annexe — Pistes pour le plan

- `RoadmapBuilder::buildProject()` et `ProjectRoadmap` (story 010) construisent une frise bornée et une timeline pour un projet. Piste : une variante par personne, qui filtre les tronçons sur ses seules saisies ; `RoadmapRunCutter` découpe aujourd'hui les tronçons sur les jours ouvrés de l'équipe d'une feuille, et devra couper sur ceux d'une seule personne.
- `ScheduleResult::loadAt()` donne déjà la charge d'une personne pour un jour, et `overloadedLots()` les feuilles en surcharge (story 006). La ligne de charge et « libre à partir du … » peuvent s'en déduire, à confirmer.
- `RoadmapWindow::spanning()` et `Roadmap::minTrackRem()` (story 010) portent les bornes, les 4 semaines minimum et le défilement ; le zoom passe par la valeur `remember` de `roadmap_controller.js`.
- Les noms de l'équipe sont rendus par `templates/roadmap/_team.html.twig` (infobulles et timeline de la fiche projet). Les infobulles passent par le contrôleur `roadmap-tooltip`, à revoir pour la question ouverte des liens.
- L'entrée « Ma fiche » va dans le menu déroulant du compte de `templates/base.html.twig`, à côté de « Mon compte ». La ligne d'une personne dans `templates/team/index.html.twig` peut porter le lien vers sa fiche.
- La visibilité des tags et du manager est posée par la story 007 (`TeamController` réservé à `ROLE_DIRECTION`). La fiche a besoin d'une règle d'accès par donnée (personne elle-même, lead, direction), probablement un voter.
