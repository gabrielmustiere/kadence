# Couper la partie réalisée d'une feuille sur les jours sans saisie pour montrer ses interruptions sur la roadmap

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/008-f-roadmap-jours-sans-saisie/`
> **Amont** : aucun

Sur la roadmap, la partie réalisée d'une feuille cesse d'être une barre pleine allant du premier au dernier jour saisi. Elle se coupe en tronçons, séparés par du vide sur chaque jour ouvré où personne n'a rien saisi sur la feuille. La direction et les leads voient enfin quand une feuille a été mise en pause, et combien de temps elle a réellement été travaillée.

## Contexte

Depuis la story 006, la partie réalisée d'une feuille s'étend du premier au dernier jour où des temps ont été saisis sur elle. Tout ce qui se trouve entre les deux est dessiné plein, saisi ou non. Une feuille de 10 j, travaillée une semaine, laissée de côté la semaine suivante puis reprise la troisième, s'affiche donc comme trois semaines de travail continu. Rien ne montre la semaine de pause.

La roadmap raconte alors une histoire fausse. La feuille paraît avoir mobilisé l'équipe trois semaines pour 10 j saisis. On ne voit pas qu'elle a été interrompue, alors que c'est souvent ce qui explique qu'une date ait glissé : l'équipe est partie sur une urgence de support, sur une autre feuille, ou en congés. Pour comprendre, il faut aller demander au lead, ce que la roadmap devait justement éviter.

Seul le vide entre la partie réalisée et la partie future est déjà visible : il dit que des jours récents n'ont pas été saisis. Les interruptions plus anciennes, elles, sont recouvertes.

**Mesure de succès** : deux semaines après la livraison, quand une feuille a glissé, la direction voit sur la roadmap si elle a été interrompue et sur quelles périodes, sans demander au lead ni ouvrir les saisies.

## Alignement vision

- **Problème adressé** : directement le symptôme « roadmap opaque ». La partie réalisée d'une barre montre le travail tel qu'il a eu lieu, pauses comprises, au lieu d'une période lissée. Le symptôme « dépassements découverts trop tard » n'est touché qu'indirectement : une feuille souvent interrompue explique une fin qui recule.
- **Audience servie** : la direction et les leads, qui lisent la roadmap pour comprendre un glissement et arbitrer. L'équipe de prod consulte la même roadmap, sans changement d'usage.
- **Principes respectés** : le principe 1 est respecté, car la saisie quotidienne n'est pas touchée. Le principe 3 est respecté, car tout se lit au niveau de la feuille. Le principe 2 est sous tension assumée. Un trou se marque au jour près et se voit de tous. Sur une feuille tenue par une seule personne, il révèle les jours où cette personne n'a rien saisi sur la feuille. Le trou ne dit en revanche jamais qui n'a pas saisi sur une feuille à plusieurs, ni pourquoi personne n'a saisi (congé, temps partiel, autre sujet). L'infobulle de la partie saisie montre par ailleurs déjà à tous combien chacun a saisi sur la feuille.
- **Hypothèse testée** : H3 indirectement. Une date de fin qui recule s'explique désormais par les interruptions visibles, ce qui aide à distinguer une estimation fausse d'une feuille mise en pause. H1 indirectement aussi : une saisie oubliée apparaît comme un trou.
- **Impact North Star** : neutre. La story ne produit ni jalon ni date annoncée.

## Utilisateurs concernés

- **Direction** : lit sur la roadmap les interruptions de chaque feuille pour comprendre un glissement avant d'arbitrer.
- **Lead** : repère les feuilles mises en pause ou travaillées par à-coups, et les jours qui n'ont pas été saisis.
- **Prod** : voit les mêmes barres coupées. Sa saisie est inchangée.
- **Personne désactivée** : son calendrier compte toujours pour dire si un jour était travaillable sur les feuilles dont elle reste membre.

## User Stories

- En tant que **direction**, je veux voir sur la barre d'une feuille les jours où personne n'y a rien saisi, afin de comprendre qu'elle a été interrompue quand sa date glisse.
- En tant que **lead**, je veux lire la partie réalisée d'une feuille comme une suite de tronçons de travail, afin de juger si elle avance d'un bloc ou par à-coups.
- En tant que **personne connectée**, je veux retrouver dans une seule infobulle la période, le total saisi et le nombre de jours avec saisie de la partie réalisée, afin de ne pas avoir à survoler chaque tronçon.
- En tant que **personne connectée**, je ne veux PAS qu'un week-end ou un jour férié de toute l'équipe coupe une barre, car personne ne pouvait y saisir.
- En tant que **membre de prod**, je ne veux PAS que la roadmap dise qui n'a pas saisi ni pourquoi, car la charge individuelle ne sert qu'à planifier.

## Règles métier

1. **Jour sans saisie** : un jour sans saisie d'une feuille est un jour ouvré pour au moins un membre de son équipe, sur lequel personne n'a saisi de temps sur la feuille, membre de l'équipe ou non. Un jour ouvré pour un membre va du lundi au vendredi, hors jours fériés de son calendrier.
2. **Membres pris en compte** : on considère l'équipe actuelle de la feuille, membres désactivés compris. La question est de savoir si ce jour-là était travaillable, et la date de désactivation d'une personne ne dit rien de son passé.
3. **Jours qui ne coupent jamais** : un samedi, un dimanche ou un jour férié pour tous les membres de l'équipe ne coupe pas une barre, puisque personne ne pouvait y saisir. Par exemple, le 11 novembre, férié en France et en Belgique, ne coupe rien. Le 21 juillet, férié en Belgique seulement, coupe la barre d'une feuille dont un membre suit le calendrier France et sur laquelle personne n'a rien saisi ce jour-là.
4. **Tronçons de la partie réalisée** : entre le premier et le dernier jour saisi, la partie réalisée d'une feuille est coupée à chaque jour sans saisie. Elle s'affiche en tronçons séparés par du vide. Un trou d'un seul jour suffit à couper la barre.
5. **Partie au-delà de l'estimation** : sur une feuille en dépassement, la partie saisie au-delà de l'estimation se coupe selon la même règle. Le jour où l'estimation est franchie appartient toujours à cette partie, comme dans la story 006.
6. **Neutralité** : un trou ne dit ni qui n'a pas saisi, ni pourquoi. Kadence ne connaît encore ni les congés ni le jour libre d'un temps partiel, qui coupent donc la barre comme n'importe quel jour sans saisie.
7. **Infobulle commune** : tous les tronçons d'une même partie partagent une seule infobulle, une pour la partie saisie et une pour la partie au-delà de l'estimation. Survoler n'importe quel tronçon l'affiche. Elle garde ses informations actuelles (période du premier au dernier jour de la partie, total saisi ou dépassement, équipe avec ce que chacun a saisi) et y ajoute le nombre de jours avec saisie de la partie.
8. **Bords de la frise** : un tronçon qui déborde de la fenêtre affichée est coupé au bord, avec l'indication qu'il continue. Un tronçon entièrement hors de la fenêtre n'apparaît pas. L'infobulle donne toujours la période complète de la partie.
9. **Inchangés** : la partie future d'une feuille, le vide entre la partie réalisée et la partie future, les dates de début et de fin calculées, le restant et les signaux ne changent pas. Les barres de cumul d'un lot découpé et d'un projet restent continues, du premier jour de la plus précoce de leurs feuilles au dernier jour de la plus tardive.
10. **Visibilité** : les tronçons et leur infobulle se voient de tous les rôles, comme la partie réalisée aujourd'hui.

## Critères d'acceptation

- [ ] Une feuille de 10 j tenue par une personne au calendrier France, saisie du lundi 07/09 au vendredi 11/09 puis du lundi 21/09 au vendredi 25/09, sans saisie la semaine du 14/09, s'affiche en deux tronçons : du 07/09 au 11/09 et du 21/09 au 25/09.
- [ ] Dans cet exemple, survoler l'un ou l'autre tronçon affiche la même infobulle : période du 07/09 au 25/09, 10 j saisis sur 10 j estimés, 10 jours avec saisie.
- [ ] Une feuille saisie le lundi, le mardi, le jeudi et le vendredi d'une même semaine est coupée le mercredi.
- [ ] Une feuille saisie le vendredi puis le lundi suivant reste d'un seul tenant sur le week-end.
- [ ] Une feuille dont tous les membres suivent un calendrier où le 11/11 est férié n'est pas coupée le 11/11, même sans saisie ce jour-là.
- [ ] Une feuille dont un membre suit le calendrier France, sans saisie le 21/07, est coupée le 21/07 ; la même feuille avec une équipe entièrement au calendrier Belgique ne l'est pas.
- [ ] Un membre désactivé de l'équipe compte toujours pour dire si un jour était ouvré : une feuille dont il est le seul membre au calendrier France reste coupée sur un jour férié belge sans saisie.
- [ ] Sur une feuille en dépassement, la partie saisie au-delà de l'estimation est elle aussi coupée sur ses jours sans saisie, et ses tronçons partagent une infobulle avec le dépassement et le nombre de jours avec saisie.
- [ ] Un tronçon qui déborde de la fenêtre de la frise est coupé au bord avec l'indication qu'il continue, et l'infobulle donne la période complète de la partie.
- [ ] La partie future, le vide entre partie réalisée et partie future, les dates de début et de fin calculées et les signaux d'une feuille sont identiques avant et après la livraison.
- [ ] Les barres de cumul d'un lot découpé et d'un projet restent continues, même quand toutes leurs feuilles ont un jour sans saisie en commun.
- [ ] Un membre de prod voit les mêmes tronçons et la même infobulle qu'un lead ou la direction.

## Hors scope

- **Seuil de trou configurable** (couper seulement à partir de plusieurs jours sans saisie) : non retenu. Le moindre jour ouvré sans saisie coupe la barre. À réévaluer à l'usage si les barres des feuilles à temps partiel sont trop hachées.
- **Cause d'un trou** (congé, temps partiel, autre feuille) : Kadence ne connaît pas encore les absences. Elles relèvent de `capacite-equipe`.
- **Coupure des barres de cumul** d'un lot découpé ou d'un projet : non retenue.
- **Infobulle par tronçon** (période et saisi propres à chaque tronçon) : non retenue, au profit de l'infobulle commune.
- **Signal ou alerte d'interruption** (« feuille en pause », « aucune saisie depuis N jours ») : non retenu. Le trou se lit sur la barre, sans badge.
- **Vue des jours saisis par personne** : non, conformément au principe 2.
- **Historique de l'équipe** (savoir qui était membre à une date passée) : non. On se fonde sur l'équipe actuelle de la feuille.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement. Le libellé du nombre de jours avec saisie est ajouté en français.
- **Droits d'accès** : inchangés. La page Roadmap reste ouverte à toute personne connectée, et les tronçons se voient de tous.
- **Cloisonnement des données** : pas de multi-organisation. Côté principe 2, le trou ne nomme personne. Sur une feuille à une seule personne, il révèle ses jours sans saisie sur la feuille : c'est une tension assumée (voir Alignement vision).
- **Apparence / déclinaisons** : non. Une seule apparence.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune reprise. Les temps déjà saisis suffisent, et les barres existantes se découpent dès la livraison sur tout l'historique.
- **Comportement par défaut** : pas d'option à activer. Toutes les barres de feuille sont concernées.

## Questions ouvertes

- **Lisibilité d'un tronçon ou d'un trou d'un jour** : sur la fenêtre par défaut, un jour occupe quelques pixels. Options : (a) largeur réelle, au risque qu'un trou ou un tronçon d'un jour soit à peine visible ; (b) largeur minimale garantie pour un tronçon et pour un trou, au prix d'un léger décalage sur la frise. → tranché : (a) largeur réelle ; un jour fait environ 2,9 px à la largeur minimale de la frise, ce qui reste visible, alors qu'une largeur minimale fausserait les positions.
- **Légende de la roadmap** : faut-il expliquer qu'un vide dans la partie saisie correspond à des jours ouvrés sans saisie ? Options : (a) oui, une mention dans la légende ; (b) non, l'infobulle suffit. → tranché : (a) une entrée « Jour ouvré sans saisie » dans la légende.

---

## Annexe — Pistes pour le plan

- La synthèse par feuille du dépôt des temps ne remonte aujourd'hui que le total, le premier et le dernier jour saisi. Les jours avec saisie de chaque feuille planifiée seront à charger, sur le modèle de la somme par jour déjà utilisée pour les feuilles en dépassement. L'objectif est de garder un nombre de requêtes constant pour la page Roadmap.
- La capacité journalière sait déjà dire si un jour est ouvré pour une personne (jour de semaine et calendrier de jours fériés, sans regarder si elle est active), ce qui couvre la règle 1. Elle ne charge en revanche les jours fériés qu'à partir de la semaine en cours : à étendre vers le passé, jusqu'au premier jour saisi le plus ancien.
- Le calcul des barres de la partie réalisée et de la partie au-delà de l'estimation rend aujourd'hui une barre chacune : il devra rendre une liste de tronçons. Le modèle de ligne de la roadmap et le gabarit de ligne devront boucler dessus.
- Le gabarit de barre embarque aujourd'hui son infobulle. Pour l'infobulle commune, il faudra séparer l'infobulle de la barre et vérifier que plusieurs déclencheurs peuvent viser la même infobulle avec la bibliothèque d'infobulles en place.
- Les données de démonstration devraient contenir au moins une feuille interrompue (une semaine sans saisie) et une feuille saisie un jour sur deux, pour la recette visuelle.
