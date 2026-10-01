# Détailler chaque tronçon et chaque feuille dans les infobulles de la roadmap, et zoomer la frise pour les survoler

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/009-f-roadmap-infobulles-zoom/`
> **Amont** : aucun

Chaque tronçon d'une feuille a désormais sa propre infobulle, avec sa période, le temps saisi et l'équipe sur ce seul tronçon. Le titre de la feuille affiche un récapitulatif de la feuille entière. Des boutons de zoom élargissent la frise jusqu'à huit fois, pour lire et survoler les tronçons les plus courts.

## Contexte

Depuis la story 008, la partie saisie d'une feuille est coupée en tronçons sur chaque jour ouvré sans saisie. Une seule infobulle couvre pourtant toute la partie : elle donne la période du premier au dernier jour saisi, le total saisi et ce que chacun a saisi sur toute la feuille. Elle mélange donc deux niveaux de lecture. On voit que la feuille a été interrompue, mais pas ce qui s'est passé pendant chaque bloc de travail : combien de temps il a duré, combien a été saisi, par qui.

S'ajoute un problème de lecture physique. Sur la fenêtre de 41 semaines, un jour occupe environ 3 px à la largeur minimale de la frise. Un tronçon d'un jour est visible, mais presque impossible à viser à la souris, et une période dense de tronçons courts se lit mal. Pour comprendre le rythme d'une feuille, il faut aujourd'hui demander au lead ou ouvrir les saisies.

**Mesure de succès** : deux semaines après la livraison, un lead explique un glissement de feuille (quel bloc de travail, combien saisi, par qui) en survolant ses tronçons sur la roadmap, sans ouvrir les saisies.

## Alignement vision

- **Problème adressé** : le symptôme « roadmap opaque ». La roadmap passe d'une lecture d'ensemble à une lecture détaillée de chaque feuille, bloc par bloc.
- **Audience servie** : la direction et les leads, qui lisent la roadmap pour comprendre et arbitrer. L'équipe de prod consulte les mêmes infobulles.
- **Principes respectés** : le principe 1 est respecté, car la saisie quotidienne n'est pas touchée. Le principe 3 est respecté, car tout se lit au niveau de la feuille. Le principe 2 est **sous tension assumée**, plus fortement que dans la story 008 : l'infobulle d'un tronçon donne le temps saisi par chaque personne sur ce tronçon, visible de tous. Sur un tronçon d'un jour, on lit donc qui a saisi combien ce jour-là. Ce choix a été fait en connaissance de cause au cadrage ; une infobulle de tronçon sans les noms a été écartée.
- **Hypothèse testée** : H3 indirectement. Une fin qui recule s'explique bloc par bloc, ce qui aide à distinguer une estimation fausse d'une feuille mise en pause ou renforcée.
- **Impact North Star** : neutre. La story ne produit ni jalon ni date annoncée.

## Utilisateurs concernés

- **Direction** : lit le détail de chaque bloc de travail et le récapitulatif de chaque feuille pour comprendre un glissement ; zoome sur une période dense.
- **Lead** : mêmes usages, pour repérer les feuilles travaillées par à-coups et qui y a contribué.
- **Prod** : voit les mêmes infobulles et dispose du même zoom. Sa saisie est inchangée.
- **Personne désactivée** : apparaît barrée dans l'équipe des infobulles, comme aujourd'hui.

## User Stories

- En tant que **lead**, je veux survoler un tronçon pour voir sa période, le temps saisi et ce que chacun y a saisi, afin de comprendre ce qui s'est passé pendant ce bloc de travail.
- En tant que **direction**, je veux survoler le titre d'une feuille pour en lire le récapitulatif (dates, estimé, saisi, restant, équipe), afin d'avoir la situation de la feuille sans additionner ses tronçons.
- En tant que **personne connectée**, je veux zoomer la frise horizontalement, afin de lire une période dense et de survoler un tronçon d'un seul jour.
- En tant que **personne connectée**, je veux retrouver mon niveau de zoom quand je navigue de quatre semaines en quatre semaines, afin de ne pas rezoomer à chaque fois.
- En tant que **personne connectée**, je ne veux PAS voir deux infobulles en même temps, car elles se masqueraient l'une l'autre.

## Règles métier

**Infobulles des tronçons**

1. **Tronçon de la partie saisie** : son infobulle reprend le contenu de l'infobulle actuelle, ramené au tronçon. Elle donne la période du tronçon (premier et dernier jour), le temps saisi sur ses jours, son nombre de jours avec saisie, et l'équipe : chaque membre avec sa part et le temps qu'il a saisi sur le tronçon, puis les personnes hors équipe qui y ont saisi.
2. **Tronçon au-delà de l'estimation** : même contenu, pour un tronçon de la partie saisie au-delà de l'estimation. Le jour où l'estimation est franchie reste dessiné dans cette partie (règle de la story 006) et compte en entier dans son tronçon, y compris la part de ce jour encore dans l'estimation. La somme des tronçons d'une feuille donne ainsi toujours le total saisi sur la feuille ; le dépassement exact se lit dans le récapitulatif.
3. **Partie restante** : son infobulle actuelle est conservée (période, restant, équipe et parts).

**Récapitulatif de la feuille**

4. **Déclenchement** : survoler le titre d'une feuille, ou y placer le focus au clavier, affiche le récapitulatif de la feuille. Pour un lead ou la direction, le titre reste le lien vers la fiche de la feuille.
5. **Contenu** : début effectif, fin calculée (ou « inconnue »), estimé, saisi, restant ou dépassement, période saisie (premier et dernier jour saisis), nombre de jours avec saisie, et l'équipe avec la part et le total saisi de chacun, puis les personnes hors équipe qui ont saisi.
6. **Feuilles non planifiées** : toute feuille a un récapitulatif. Celui d'une feuille non planifiée affiche ce qui est connu et nomme ce qui manque : « à estimer », « sans début » ou « sans équipe ».
7. **Lots découpés et projets** : pas de récapitulatif. Leurs barres de cumul et leurs infobulles sont inchangées.

**Affichage des infobulles**

8. **Une seule à la fois** : jamais deux infobulles ne sont affichées en même temps. Passer d'un tronçon à un autre, ou d'un tronçon au titre, ferme la première avant d'ouvrir la seconde.
9. **Trous** : survoler un trou de la barre, entre deux tronçons, n'affiche aucune infobulle.

**Zoom de la frise**

10. **Commandes** : des boutons « − », « + » et « 100 % », près de la navigation de la frise, avec le palier en cours affiché.
11. **Paliers** : ×1, ×2, ×4 et ×8. ×1 est l'affichage actuel et sert de borne basse ; ×8 donne environ 23 px par jour à la largeur minimale de la frise et sert de borne haute. « − » est désactivé à ×1, « + » à ×8 ; « 100 % » ramène à ×1.
12. **Ce qui change** : seule la frise s'élargit et défile horizontalement ; barres, tronçons, repère « aujourd'hui », en-tête des mois et lignes de semaines suivent le zoom. La fenêtre de 41 semaines, la navigation par 4 semaines et la colonne des titres ne changent pas ; la colonne des titres reste visible pendant le défilement.
13. **Point fixe** : zoomer ou dézoomer garde au centre de l'écran la même date.
14. **Mémorisation** : le palier est conservé pendant la session, en naviguant par 4 semaines comme en revenant sur la page. Une frise zoomée s'ouvre centrée sur aujourd'hui, ou sur la semaine de référence de la fenêtre si aujourd'hui n'y figure pas.

**Visibilité**

15. **Tous les rôles** voient les mêmes infobulles et disposent du même zoom. Le signal « à replanifier » reste réservé aux leads et à la direction.

## Critères d'acceptation

- [ ] Sur une feuille de 10 j tenue par une personne au calendrier France à 100 %, saisie d'une journée pleine du lundi 07/09 au vendredi 11/09 puis du lundi 21/09 au vendredi 25/09, survoler le premier tronçon affiche : période du 07/09 au 11/09, 5 j saisis, 5 jours avec saisie, et la personne avec 5 j et 100 %.
- [ ] Dans cet exemple, survoler le second tronçon affiche la période du 21/09 au 25/09 et 5 j saisis.
- [ ] Dans cet exemple, survoler le titre de la feuille affiche le récapitulatif : début le 07/09, fin le 25/09, 10 j estimés, 10 j saisis, 0 j restant, période saisie du 07/09 au 25/09, 10 jours avec saisie, et la personne avec 10 j et 100 %.
- [ ] Sur un tronçon où une personne hors équipe a saisi, cette personne apparaît après les membres, marquée « hors équipe », avec son temps sur le tronçon.
- [ ] Sur une feuille en dépassement, chaque tronçon au-delà de l'estimation a sa propre infobulle ; la somme des temps de tous les tronçons de la feuille égale le total saisi, et le récapitulatif donne le dépassement.
- [ ] La partie restante d'une feuille garde son infobulle actuelle.
- [ ] Une feuille sans début affiche un récapitulatif qui indique « sans début » ; une feuille à estimer, « à estimer ».
- [ ] Passer d'un tronçon à l'autre, ou d'un tronçon au titre, n'affiche jamais deux infobulles à la fois ; survoler un trou n'en affiche aucune.
- [ ] Le récapitulatif s'affiche aussi quand le titre de la feuille reçoit le focus au clavier.
- [ ] « + » fait passer la frise de ×1 à ×2, ×4 puis ×8, où il est désactivé ; « − » est désactivé à ×1 ; « 100 % » ramène à ×1.
- [ ] À ×8, un tronçon d'un jour mesure au moins 20 px de large et affiche son infobulle au survol.
- [ ] En zoomant, la date au centre de l'écran reste la même ; la colonne des titres reste visible pendant le défilement horizontal.
- [ ] Le palier de zoom est conservé après une navigation de 4 semaines et au retour sur la page pendant la session ; une frise zoomée s'ouvre centrée sur aujourd'hui.
- [ ] Les barres de cumul des lots découpés et des projets, et leurs infobulles, sont inchangées.
- [ ] Un membre de prod voit les mêmes infobulles et dispose du même zoom qu'un lead ou la direction.

## Hors scope

- **Infobulle de tronçon sans les noms** : écartée au cadrage, au profit d'un détail par personne visible de tous (tension avec le principe 2 assumée).
- **Récapitulatif des lots découpés et des projets** : non retenu ; seules les feuilles en ont un.
- **Zoom à la molette, au pavé tactile ou au pincement** : non ; seuls les boutons zooment.
- **Zoom continu (curseur)** : non retenu, au profit de paliers fixes.
- **Graduation en jours ou en semaines au fort zoom** : non ; l'en-tête reste en mois, avec les lignes de semaines.
- **Mémorisation du zoom au-delà de la session** : non.
- **Changer la fenêtre affichée en zoomant** : non ; la fenêtre reste de 41 semaines.
- **Vue des saisies par personne hors de la roadmap** : non, conformément au principe 2.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement ; les libellés ajoutés (récapitulatif, boutons de zoom) sont en français.
- **Droits d'accès** : inchangés. La page Roadmap reste ouverte à toute personne connectée ; infobulles et zoom sont les mêmes pour tous les rôles.
- **Cloisonnement des données** : pas de multi-organisation. Côté principe 2, le temps saisi par chaque personne devient lisible tronçon par tronçon, donc parfois jour par jour, pour tous : tension assumée (voir Alignement vision).
- **Apparence / déclinaisons** : non. Une seule apparence.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune reprise. Les temps déjà saisis suffisent.
- **Comportement par défaut** : la frise s'ouvre à ×1, identique à aujourd'hui, tant que personne n'a zoomé dans la session.

## Questions ouvertes

- **Écran tactile** : sans survol, comment afficher l'infobulle d'un tronçon ou le récapitulatif ? Options : (a) un appui affiche l'infobulle, un second appui ailleurs la ferme ; (b) hors scope, la roadmap détaillée reste un usage poste de travail. → tranché : (a) par l'émulation du survol qu'offre le navigateur au toucher, sans comportement dédié ni test spécifique.
- **Libellés des mois au fort zoom** : à ×2 et au-delà, un mois coupé par le bord gauche a plus de place ; faut-il le libeller plus souvent ? Options : (a) oui, le seuil suit le zoom ; (b) non, règle actuelle conservée. → tranché : (b) le serveur ne connaît pas le zoom, la règle actuelle est conservée.

---

## Annexe — Pistes pour le plan

- Une infobulle Flowbite par tronçon crée une instance par élément, recréée à chaque rendu Turbo, alors qu'une feuille peut compter près de cent tronçons. Piste : une infobulle partagée par feuille ou par page, remplie au survol à partir d'attributs `data-` des tronçons par un contrôleur Stimulus, ce qui règle aussi la règle « une seule à la fois ».
- Le détail par personne et par tronçon demande les saisies par feuille, jour et personne ; `findQuartersInOrderForLots()` rend déjà la personne et les quarts dans l'ordre de saisie, mais sans le jour. Le jour de franchissement (règle 2) se lit déjà dans `ScheduleData::overrunDays`.
- Le récapitulatif reprend des données déjà calculées par `RoadmapBuilder` (`start`, `end`, `remainingQuarters`, `realizedTeam`/`overrunTeam`, compteurs de jours) ; le titre de la feuille vit dans `templates/roadmap/_row.html.twig`.
- Zoom : multiplier la largeur minimale de la frise (`min-w-[72rem]`, colonne de `20rem`) par le palier, mémoriser le palier en `sessionStorage` comme les projets dépliés (`assets/controllers/roadmap_controller.js`), recentrer par `scrollLeft`. `Roadmap::MIN_MONTH_WIDTH` est calculé pour ×1.
