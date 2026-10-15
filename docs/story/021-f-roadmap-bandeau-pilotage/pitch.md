# Lire en tête de la roadmap combien de lots demandent une action, et n'afficher qu'eux d'un clic

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/021-f-roadmap-bandeau-pilotage/`
> **Amont** : aucun

Les leads et la direction trouvent au-dessus de la frise de la roadmap un bandeau de compteurs. Chaque compteur dit combien de lots demandent une action (« 4 à replanifier », « 2 à estimer », « 3 avancements à actualiser ») et laquelle. Un clic sur un compteur ne laisse sur la frise que ces lots, dépliés sous leur projet. Ce qu'il faut traiter se lit sans ouvrir les projets un par un.

## Contexte

La frise de la roadmap répond bien à « qui travaille sur quoi, et jusqu'à quand ». Depuis la story 018, elle écrit sous chaque titre la fin calculée, ou ce qui la retient. Mais elle ne dit pas d'emblée **où il faut agir**. Projets repliés, l'état par défaut, on ne voit qu'une barre par projet et, au mieux, la pastille « planification partielle ». Les pastilles qui appellent une action sont toutes portées par les lots : « en dépassement », « à replanifier », « à estimer », « sans équipe », « avancement à actualiser »… On ne les voit qu'en dépliant chaque projet. Un projet déplié est long, et le mélange décourage : sur SCA, une douzaine de lots sur environ vingt-cinq sont terminés et placés « avant cette période ». Plusieurs portent encore « en dépassement », alors qu'il n'y a plus rien à faire.

Pour préparer la revue de pilotage, un lead ou la direction doit donc déplier chaque projet, parcourir chaque lot et compter de tête ce qui reste à traiter. C'est une consolidation manuelle, celle que l'outil devait supprimer. L'accueil liste bien les « Projets à surveiller », mais projet par projet, sans dire combien de lots sont en cause ni lesquels, et sans mener à la frise.

**Mesure de succès** : en revue de pilotage, la direction et les leads passent en revue ce qui est à traiter depuis le bandeau, compteur par compteur, sans déplier les projets un par un. D'une semaine à l'autre, les compteurs baissent à mesure que les actions sont faites. C'est un constat d'usage, sans instrumentation.

## Alignement vision

- **Problème adressé** : le symptôme « dépassements découverts trop tard » et le bénéfice direction « répondre en lisant une page, pas en convoquant une réunion ». Les dérives et les trous de planification se comptent sans consolidation manuelle.
- **Audience servie** : la direction et les leads, qui pilotent, dans la revue de pilotage hebdomadaire (parcours P5) et au fil de la semaine. L'équipe de prod n'est pas concernée.
- **Principes respectés** : le principe 1 est respecté, la saisie n'est pas touchée. Le principe 2 l'est aussi : les compteurs comptent des lots, jamais des personnes, et aucun ne compare ni ne classe qui que ce soit. « À replanifier » reste réservé aux leads et à la direction, comme depuis la story 006. Le principe 3 est respecté : tout se compte au niveau du lot. Le principe 4 n'est pas concerné, car il n'y a toujours pas de date annoncée.
- **Hypothèse testée** : H2 indirectement. Une dérive comptée et ciblée d'un clic devrait être traitée plus tôt qu'une dérive enfouie dans un projet replié.
- **Impact North Star** : indirect. La story ne crée pas de jalon, mais elle raccourcit le délai entre l'apparition d'une dérive et sa prise en charge.

## Utilisateurs concernés

- **Direction** : lit en tête de la roadmap ce qui est à traiter sur tout le portefeuille, et ouvre d'un clic la liste des lots concernés pour la revue de pilotage.
- **Lead** : voit les mêmes compteurs, sur tout le portefeuille et pas seulement sur ses lots, et filtre la frise pour traiter un type d'action à la fois.
- **Prod** : aucun changement perçu. Ni bandeau ni filtre ; un lien filtré partagé lui montre la roadmap normale.
- **Toute personne qui consulte l'accueil, la page d'un projet ou d'une personne** : aucun changement.

## User Stories

- En tant que **direction**, je veux voir en tête de la roadmap combien de lots sont en dépassement, à replanifier ou à l'équipe à revoir, afin de savoir d'un coup d'œil s'il y a des dérives à arbitrer.
- En tant que **lead**, je veux voir combien de lots sont à estimer, sans début, sans équipe ou en démarrage en retard, afin de compléter la planification avant que les fins calculées ne manquent.
- En tant que **lead**, je veux voir combien de lots ont un avancement à actualiser, afin de déclarer les avancements qui fiabilisent les fins calculées.
- En tant que **lead ou direction**, je veux que chaque compteur me dise l'action à mener, afin de savoir quoi faire sans relire la signification de la pastille.
- En tant que **lead ou direction**, je veux cliquer sur un compteur pour ne garder sur la frise que les lots concernés, dépliés sous leur projet, afin de les traiter sans chercher dans une page très haute.
- En tant que **lead ou direction**, je veux partager le lien d'une roadmap filtrée et la retrouver filtrée en changeant de période ou de zoom, afin de préparer la revue et d'y renvoyer mes collègues.
- En tant que **lead ou direction**, je ne veux PAS que les lots terminés gonflent les compteurs, car il n'y a plus rien à y faire.
- En tant que **membre de prod**, je ne veux PAS voir de bandeau ni de frise filtrée, car ces actions ne me reviennent pas.

## Règles métier

### Le bandeau

1. **Qui le voit** : les leads et la direction, au-dessus de la frise de la roadmap. La prod ne le voit pas.
2. **Un compteur par pastille de lot**, rangé en trois familles, dans cet ordre, chacun avec son action :

   | Famille | Pastille comptée | Action affichée |
   |---|---|---|
   | Dérives | en dépassement | Réviser l'estimation ou déclarer l'avancement |
   | Dérives | à replanifier | Réaffecter l'équipe ou décaler le début |
   | Dérives | équipe à revoir | Recomposer l'équipe |
   | Planification | à estimer | Poser une estimation |
   | Planification | sans début | Poser une date de début |
   | Planification | sans équipe | Composer l'équipe |
   | Planification | démarrage en retard | Décaler la date de début |
   | Avancement | avancement à actualiser | Déclarer l'avancement |

3. **Ce qui se compte** : des lots (les lots sans sous-lot et les sous-lots, là où se posent estimation, début, équipe et avancement), sur tout le portefeuille, quelle que soit la période affichée. Chaque compteur précise aussi sur combien de projets ces lots se répartissent (« sur 2 projets »).
4. **Les lots terminés ne comptent pas** : un lot « terminé à 100 % » ou à « estimation atteinte » n'entre dans aucun compteur, même s'il porte une autre pastille (un dépassement constaté, par exemple).
5. **Un compteur à zéro ne s'affiche pas.** Une famille sans compteur affiché disparaît. Quand plus rien n'est à traiter, le bandeau dit « Rien à traiter ».
6. **Les pastilles de projet n'ont pas de compteur** : « à découper » et « planification partielle » restent sur la ligne du projet, comme aujourd'hui.
7. **Un lot peut entrer dans plusieurs compteurs** s'il porte plusieurs pastilles (« à estimer » et « sans équipe », par exemple).

### Le filtre

8. **Un clic sur un compteur filtre la frise** : elle ne garde que les lots qu'il compte, chacun sous son projet et, le cas échéant, sous son lot découpé. Ces lignes sont dépliées. Les projets sans lot concerné disparaissent. Le compteur actif est marqué comme tel.
9. **Un seul filtre à la fois** : cliquer sur un autre compteur remplace le filtre. Un nouveau clic sur le compteur actif, ou « Tout afficher », rend la frise complète.
10. **Le contexte n'est pas recalculé** : la barre et la fin d'un projet ou d'un lot découpé restent celles de leur ensemble, pas celles des seuls lots filtrés.
11. **Le filtre tient dans le lien** : il survit au passage de quatre semaines en quatre semaines, au retour à aujourd'hui et au zoom, et le lien se partage. Le retour vers la roadmap depuis l'édition d'un lot, ou depuis la page d'un projet ouverte à partir de la frise filtrée, le garde aussi, y compris après une action faite sur cette page. _(Précision apportée au plan.)_
12. **Lot filtré hors période** : sa ligne apparaît avec l'indication existante « avant / après cette période ».
13. **Lien périmé** : si le compteur visé est retombé à zéro, la frise dit « Rien à traiter pour « <pastille> » » et propose « Tout afficher ».
14. **Prod** : un lien filtré ouvert par la prod affiche la roadmap complète, sans filtre ni mention.

### Affichage

15. **Sur téléphone**, les compteurs passent à la ligne ; ils restent lisibles et cliquables sans faire défiler la page horizontalement.
16. **Les lignes de la frise ne changent pas** : titres, pastilles, fins calculées et récapitulatifs gardent le comportement des stories 006 à 020.

## Critères d'acceptation

- [x] Connecté en lead ou en direction, je vois au-dessus de la frise un compteur par pastille présente sur au moins un lot non terminé. Chacun indique son nombre de lots, le nombre de projets concernés et son action.
- [x] Les compteurs sont rangés en « Dérives », « Planification » et « Avancement », dans l'ordre de la table des règles. Un compteur à zéro et une famille vide ne s'affichent pas.
- [x] Un lot « terminé à 100 % » qui porte aussi « en dépassement » n'est pas compté dans « en dépassement ».
- [x] Les compteurs sont identiques quelle que soit la période affichée.
- [x] Quand aucun lot n'appelle d'action, le bandeau affiche « Rien à traiter ».
- [x] Un clic sur un compteur ne laisse sur la frise que les lots qu'il compte, dépliés sous leur projet et leur lot découpé. Les autres projets disparaissent, et le compteur actif est marqué.
- [x] Un nouveau clic sur le compteur actif, ou « Tout afficher », rend la frise complète.
- [x] Passer à la période précédente ou suivante, revenir à aujourd'hui ou zoomer conserve le filtre. Recharger le lien de la page filtrée la rouvre filtrée.
- [x] Un lien filtré dont le compteur est retombé à zéro affiche « Rien à traiter pour « <pastille> » » et propose « Tout afficher ».
- [x] Connecté en prod, je ne vois pas de bandeau, et un lien filtré m'affiche la roadmap complète.
- [x] Sur un téléphone, les compteurs passent à la ligne sans défilement horizontal de la page.

## Hors scope

- **Charge et capacité de l'équipe** : il faudrait un calcul nouveau à l'échelle de la page, et c'est délicat au regard du principe 2. Cela relève de la ligne `charge-vs-capacite`.
- **Remonter les pastilles des lots sur la ligne repliée d'un projet** : écarté au profit d'indicateurs macro. Le détail par projet reste l'affaire de la frise et de la page projet.
- **Masquer les lots terminés hors filtre** : la frise complète reste inchangée ; c'est le filtre qui fait le tri.
- **Bouton d'action par ligne** : le titre d'un lot mène déjà à son édition, et la flèche d'un projet à sa page, où se déclare l'avancement.
- **Combiner plusieurs filtres, ou filtrer sur « mes lots »** : un seul filtre à la fois, sur tout le portefeuille.
- **Compteurs en projets** (« à découper », « planification partielle ») : ces pastilles découlent de lots déjà comptés, ou restent visibles sur la ligne du projet.
- **Accueil** : le bloc « Projets à surveiller » est inchangé ; le recouvrement est assumé et sera réévalué après usage.
- **Historique des compteurs** : pas de relevé hebdomadaire ni de tendance.
- **Alerte de rythme de consommation au responsable** (C5.2) : non couverte. Le bandeau recoupe en partie C5.3 (liste des feuilles en dérive pour la direction), mais avec les pastilles existantes, sans notion de rythme.
- **Date annoncée et jalons** : toujours absents (ligne `jalons-dates-annoncees`).

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français seul ; les libellés nouveaux sont les noms de famille, les actions, « Rien à traiter » et « Tout afficher ».
- **Droits d'accès** : les leads et la direction voient le bandeau et filtrent. La prod ne voit rien de nouveau, et le filtre est ignoré pour elle. Pas de nouveau niveau d'autorisation.
- **Cloisonnement des données** : non. Les compteurs portent sur des lots déjà visibles de leurs lecteurs. « À replanifier » reste réservé aux leads et à la direction.
- **Apparence / déclinaisons** : mode clair et mode sombre du design system, comme le reste de la page.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune reprise. Les compteurs se calculent à l'affichage à partir des données saisies.
- **Comportement par défaut** : sans clic sur un compteur, la frise est celle d'aujourd'hui.

## Questions ouvertes

_(aucune)_

---

## Annexe — Pistes pour le plan

- Les compteurs peuvent sortir des pastilles que le constructeur de la roadmap pose déjà sur chaque feuille (`RoadmapSignal::of()` plus `ToReplan` dans `RoadmapBuilder::leafRow()`), sans calcul parallèle. Exclusion des feuilles terminées via `Completed` et `EstimateReached`. À confirmer.
- Le constructeur reçoit déjà le rôle lead (`RoadmapController::renderAround()`, `isGranted('ROLE_LEAD')`). Le paramètre `?signal=` pourrait être lu et validé par le contrôleur (`RoadmapSignal::tryFrom`, limité aux huit pastilles de feuille), puis ignoré pour la prod.
- Les liens de navigation (`_zoom.html.twig`, boutons ± 4 semaines, « Aujourd'hui ») devront propager `?signal=`. Le zoom est peut-être géré côté Stimulus : à vérifier.
- En mode filtré, les `<details>` doivent être ouverts sans tenir compte de l'état mémorisé par `roadmap#remember`, ni l'écraser.
- Les tuiles pourraient réutiliser `<twig:Badge>` et ses variantes (`RoadmapSignal::variant()`) pour garder la couleur des pastilles de la frise.
