# Lire sur la roadmap, sans rien survoler, quand chaque projet et chaque lot se termine

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/018-f-roadmap-dates-lisibles/`
> **Amont** : aucun

Sur la roadmap, chaque projet et chaque lot affiche en toutes lettres, à côté de son titre, sa fin calculée. Quand cette fin est inconnue ou que rien n'est planifié, la ligne dit pourquoi et quels lots sont en cause. La date se lit sur un projet replié, hors de la période affichée et sur un téléphone, sans survoler la frise.

## Contexte

La roadmap dessine la chronologie de chaque projet depuis la story 006, qui promettait qu'on y lirait « pour chaque feuille sa fin calculée (ou « fin inconnue ») ». En pratique, aucune date n'est écrite sur la page. La fin d'un lot ne s'obtient qu'en survolant sa barre ou son titre (stories 009 et 010). Celle d'un projet replié, l'état par défaut, n'apparaît qu'au survol d'une barre pâle. Pour répondre à « c'est pour quand ? » sur sept projets, la direction doit survoler sept barres, et estimer à l'œil sur l'axe des mois ce qu'elle ne peut pas survoler.

Trois cas restent muets :

- **Un projet sans barre** ne dit pas pourquoi. Aujourd'hui, Kadence et Support et maintenance n'affichent qu'une pastille « planning partiel », alors que rien n'y est planifié. Évolution du portail porte une pastille « à découper ». Rien n'indique quels lots manquent de quoi, ni s'il s'agit d'un projet non planifié ou d'un projet entièrement hors de la période affichée.
- **Une fin inconnue** n'est expliquée que sur la page du projet. La roadmap montre une barre qui s'arrête, sans dire qu'elle n'a pas de fin ni pourquoi.
- **Sur un téléphone**, la colonne des titres occupe presque tout l'écran : il faut faire défiler la frise pour voir où une barre s'arrête.

C'est le symptôme « roadmap opaque » que la vision met au centre, et le signal d'arrêt « la direction continue de demander les dates à l'oral ». Une date calculée qui ne se lit qu'au survol ne remplace pas la question posée au lead.

**Mesure de succès** : deux semaines après la livraison, la direction donne la fin calculée de chaque projet en cours, ou la raison pour laquelle elle est inconnue, en lisant la roadmap repliée, sans survol ni défilement, y compris sur son téléphone.

## Alignement vision

- **Problème adressé** : direct, le symptôme « roadmap opaque ». La page répond à « c'est pour quand ? » en une lecture. Le symptôme « dépassements découverts trop tard » est servi indirectement : un projet dont la fin devient inconnue parce qu'un lot dépasse se voit sur sa ligne, même replié.
- **Audience servie** : la direction, qui s'engage sur des dates. Les leads, qui repèrent sur la roadmap les lots qui privent leur projet d'une fin. L'équipe de prod, qui consulte la roadmap pour savoir sur quoi elle sera dans les semaines à venir.
- **Principes respectés** : le principe 1 est respecté, car la saisie n'est pas touchée. Le principe 2 est respecté : aucune information nouvelle sur les personnes, seulement des dates et des états de lots. Le principe 3 est respecté : tout se lit au niveau du projet et du lot. Le principe 4 reste sous tension assumée, comme depuis la story 006 : la date annoncée n'existe pas encore, et la roadmap ne montre que la fin calculée. La mise en page prévoit la place où la date annoncée s'affichera à côté.
- **Hypothèse testée** : H3 indirectement. Des fins calculées lisibles d'un coup d'œil sont plus faciles à confronter aux dates réelles.
- **Impact North Star** : indirect. La story ne crée ni jalon ni date annoncée, mais elle rend lisible la date calculée qu'il faudra confronter à la date annoncée.

## Utilisateurs concernés

- **Direction** : lit la fin calculée de chaque projet sur la roadmap repliée, ou la raison pour laquelle elle est inconnue, sans survol.
- **Lead** : repère, sur la ligne d'un projet, combien de lots le privent d'une fin et pourquoi, puis le détail lot par lot en dépliant.
- **Prod** : voit les mêmes dates et les mêmes explications. Le signal « à replanifier » reste réservé aux leads et à la direction ; il n'entre pas dans les explications d'une fin.
- **Toute personne sur téléphone** : lit la fin de chaque projet sans faire défiler la frise.
- **Toute personne qui consulte la page d'un projet ou d'une personne** : la frise de ces pages garde son contenu. Seules ses lignes de lots gagnent leur date de fin, et sa colonne des titres se resserre sur téléphone comme celle de la roadmap.

## User Stories

- En tant que **direction**, je veux lire la fin calculée de chaque projet à côté de son titre, projets repliés, afin de répondre à « c'est pour quand ? » en parcourant une seule colonne.
- En tant que **direction**, je veux qu'un projet dont la fin est inconnue le dise sur sa ligne, avec le nombre de lots en cause, afin de savoir que la date ne peut pas encore être donnée.
- En tant que **lead**, je veux voir quels lots privent un projet d'une fin et pourquoi (en dépassement, équipe à revoir), afin de savoir quoi corriger.
- En tant que **lead**, je veux qu'un projet partiellement planifié dise combien de lots sont laissés hors de sa fin, et lesquels, afin de ne pas prendre sa date pour celle du projet entier.
- En tant que **personne connectée**, je veux qu'un projet sans barre dise pourquoi (pas encore découpé, rien de planifié, ou hors de la période affichée), afin de ne pas le croire oublié.
- En tant que **personne connectée**, je veux lire la fin d'un projet qui tombe avant ou après la période affichée, afin de ne pas devoir naviguer de quatre semaines en quatre semaines pour la trouver.
- En tant que **personne connectée sur téléphone**, je veux lire la fin de chaque projet sans faire défiler la frise, afin de répondre à la question depuis mon téléphone.
- En tant que **personne qui utilise un lecteur d'écran**, je veux entendre la fin d'un projet ou d'un lot avec son titre, afin d'accéder à l'information que les barres donnent aux autres.
- En tant que **membre de prod**, je ne veux PAS que l'explication d'une fin révèle une surcharge, car la charge individuelle reste réservée à ceux qui planifient.

## Règles métier

### Ce qu'affiche chaque ligne

1. **Ligne de date** : chaque ligne de projet, de lot découpé et de lot de la roadmap porte, sous son titre et ses pastilles, une ligne qui dit sa fin. Elle est visible que le projet soit replié ou déplié, et quel que soit le zoom.
2. **Lot planifié dont la fin est connue** : « Fin calculée : » suivi du jour et de la date, par exemple « Fin calculée : Ven 20/11/2026 ». C'est la date que donne déjà son récapitulatif ; les règles de calcul de la story 006 sont inchangées.
3. **Lot planifié dont la fin est inconnue** : « Fin inconnue : » suivi de ce qui la retient, avec les mots des pastilles : « en dépassement », « équipe à revoir ». _(Révisé par la story 020 : une feuille « avancement à actualiser » garde sa fin calculée.)_
4. **Lot non planifié** : « Non planifié : » suivi de ce qui lui manque : « à estimer », « sans début », « sans équipe ».
5. **Projet ou lot découpé dont la fin est connue** : « Fin calculée : » suivi de la date de son lot le plus tardif, comme aujourd'hui pour sa barre.
6. **Projet ou lot découpé partiellement planifié** : la date est suivie du nombre de lots non planifiés qu'elle laisse de côté, par exemple « Fin calculée : Ven 20/11/2026, hors 2 lots non planifiés ».
7. **Projet ou lot découpé dont la fin est inconnue** : « Fin inconnue : » suivi du nombre de lots en cause, par exemple « Fin inconnue : 3 lots en cause ». Sont en cause les lots planifiés sans fin calculée et les lots non planifiés.
8. **Projet ou lot découpé où rien n'est planifié** : « Non planifié : » suivi du nombre de lots, par exemple « Non planifié : 6 lots ».
9. **Projet sans lot** : « Pas encore découpé en lots ».
10. **Détail à la demande** : sur une ligne de projet ou de lot découpé qui compte des lots en cause (règles 6 à 8), survoler la ligne de date affiche la liste de ces lots, chacun avec ce qui le retient ou ce qui lui manque ; sur un lot découpé, elle s'ouvre aussi au clavier. C'est la même explication que sur la page du projet. En dépliant le projet, chaque lot donne aussi sa propre raison sur sa ligne (règles 3 et 4).
11. **Mots des explications** : seules les raisons qui privent un lot de sa fin entrent dans une explication. « À replanifier », réservé aux leads et à la direction, n'y figure jamais, pas plus que « démarrage en retard », « estimation atteinte » ou « terminée à 100 % ».
12. **Pastilles inchangées** : les pastilles de chaque ligne et leurs infobulles restent telles quelles. La ligne de date s'ajoute, elle ne les remplace pas.

### Hors de la période affichée

13. **Date toujours lisible** : la ligne de date ne dépend pas de la période affichée. La fin d'un projet terminé avant cette période, ou qui commence après, se lit sur sa ligne comme les autres.
14. **Repère au bord de la frise** : une ligne dont toute la barre tombe avant la période affichée porte, au bord gauche de la frise, l'indication qu'elle se situe avant. Une ligne dont toute la barre tombe après porte la même indication au bord droit. L'indication reste visible au bord quand la frise défile. Les barres qui débordent seulement d'un côté restent coupées au bord, comme aujourd'hui.

### Téléphone

15. **Colonne des titres resserrée** : sur un écran de téléphone, la colonne des titres de la frise est plus étroite, pour que la frise reste visible à côté. Les titres, les pastilles et la ligne de date passent à la ligne au besoin.
16. **Date sans défilement** : sur un écran de téléphone, la ligne de date de chaque projet est visible sans faire défiler la frise horizontalement.

### Accessibilité

17. **Lisible sans la vue** : la fin d'un projet ou d'un lot, ou sa raison, est lue par un lecteur d'écran avec son titre. La liste des lots en cause est rattachée à la ligne du projet, pour être lue sans survol.

### Pages qui partagent la frise

18. **Page d'un projet** : les lignes de lots de sa frise portent la même ligne de date. La ligne du projet en tête de cette frise est inchangée, puisque la fin du projet et son explication figurent déjà en haut de la page.
19. **Page d'une personne** : sa frise est inchangée, à la largeur de la colonne des titres et au retour à la ligne des pastilles près (règle 15).

## Critères d'acceptation

- [x] Sur la roadmap, projets repliés, chaque projet planifié affiche « Fin calculée : » suivi du jour et de la date de son lot le plus tardif, identique à la fin donnée par son récapitulatif.
- [x] Un projet dont un lot planifié est en dépassement sans avancement déclaré affiche « Fin inconnue : » avec le nombre de lots en cause ; survoler cette ligne liste ce lot avec « en dépassement ».
- [x] En dépliant ce projet, le lot en dépassement affiche « Fin inconnue : en dépassement », et un lot planifié dont la fin est connue affiche sa fin calculée.
- [x] Un projet dont une partie seulement des lots est planifiée affiche sa fin calculée suivie de « hors N lots non planifiés » ; survoler la ligne liste ces lots avec ce qui leur manque.
- [x] Un projet dont aucun lot n'est planifié affiche « Non planifié : » avec son nombre de lots, et un projet sans lot affiche « Pas encore découpé en lots ».
- [x] Un lot non planifié affiche « Non planifié : » suivi de ce qui lui manque (« à estimer », « sans début », « sans équipe »).
- [x] Le signal « à replanifier » n'apparaît dans aucune ligne de date ni aucune liste de lots en cause, quel que soit le rôle.
- [x] Un projet dont tous les lots se terminent avant la période affichée affiche sa fin calculée et un repère au bord gauche de la frise ; un projet qui commence après la période affichée, un repère au bord droit.
- [x] La ligne de date d'un projet est identique à ×1 et à ×8, et après une navigation de quatre semaines.
- [x] Sur un écran de 390 px de large, la ligne de date de chaque projet est entièrement visible sans défilement horizontal.
- [x] Un lecteur d'écran annonce, sur la ligne d'un projet, son titre et sa ligne de date, et donne accès à la liste des lots en cause sans survol.
- [x] Sur la page d'un projet, chaque lot de la frise affiche sa ligne de date ; l'en-tête de la page est inchangé.
- [x] Sur la page d'une personne, la frise affiche les mêmes barres et les mêmes pastilles qu'avant.
- [x] Un membre de prod voit les mêmes lignes de date qu'un lead ou la direction.

## Hors scope

- **Date annoncée, jalons et écart entre date annoncée et fin calculée** : relèvent de `jalons-dates-annoncees`. La ligne de date prévoit la place de la date annoncée, sans l'afficher.
- **Liste des jalons à venir, date annoncée à côté de la fin calculée** : c'est la ligne de backlog `roadmap-interne`. Cette story n'en réalise aucune des deux moitiés (ni date annoncée, ni liste de jalons) ; elle rend seulement lisible la fin calculée qui existe déjà. Elle n'est donc pas rattachée à cette ligne.
- **Changement des règles de calcul** (période prévue, fin calculée, signaux) : inchangées. La story affiche ce qui est déjà calculé.
- **Tri des projets par date de fin, filtre sur les projets en retard** : non retenus. L'ordre alphabétique est conservé.
- **Date écrite au bout de chaque barre, dans la frise** : écartée au profit de la colonne des titres, toujours visible quel que soit le défilement, le zoom ou la période.
- **Récapitulatif complet d'un projet ou d'un lot découpé** (estimé, saisi, équipe) : non retenu, conformément à la story 009. Seule la liste des lots en cause est ajoutée.
- **Ligne de date sur la frise de la page d'une personne** : non retenue. Sa section « À venir » donne déjà la période de chaque affectation.
- **Vue dédiée au téléphone** (liste des projets sans frise) : non retenue. La frise reste la même, avec une colonne des titres plus étroite.
- **Révision des mots employés** : relève de la story 015, qui fixe le vocabulaire commun. Cette story emploie déjà ses termes (« lot », « Fin calculée », « Dépassement »).

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement ; les libellés ajoutés (« Fin calculée », « Fin inconnue », « Non planifié », « hors N lots non planifiés », « Pas encore découpé en lots », les repères de bord) sont en français.
- **Droits d'accès** : inchangés. Toute personne connectée consulte la roadmap. Le signal « à replanifier » reste réservé aux leads et à la direction, et n'entre jamais dans une explication.
- **Cloisonnement des données** : non. Aucune donnée nouvelle sur les personnes.
- **Apparence / déclinaisons** : la colonne des titres de la frise est plus étroite sur téléphone, sur la roadmap comme sur la page d'un projet et celle d'une personne. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non.
- **Données existantes** : aucune reprise. Les dates affichées sont celles déjà calculées.
- **Comportement par défaut** : tout le monde voit les lignes de date dès la mise en service, sans réglage.

## Questions ouvertes

- **Pastille « planification partielle » d'un projet où rien n'est planifié** (aujourd'hui « planning partiel », renommée par la story 015) : la pastille dit « partielle » alors qu'aucun lot n'est planifié (Kadence aujourd'hui). Options : (a) la garder, la ligne de date disant « Non planifié » ; (b) ne l'afficher que si au moins un lot est planifié. Tranchée à la livraison : (a), les signaux de la story 006 sont inchangés et la ligne de date dit « Non planifié ».
- **Forme du repère hors période** : options : (a) un court texte au bord (« ‹ avant cette période », « après cette période › ») ; (b) une flèche seule, avec le texte pour les lecteurs d'écran. Tranchée à la livraison : (a), un court texte au bord.

---

## Annexe — Pistes pour le plan

- Tout est déjà calculé par `RoadmapBuilder::build()` : `RoadmapRow::$start`, `$end`, `$lastDay`, `$signals`, `$children` ; `RoadmapRow::unknownEndLeaves()` et `RoadmapSignal::withholdsEnd()` (livrés avec l'audit du 2026-10-12) donnent les lots en cause et leurs raisons. Piste : aucune nouvelle requête, seulement une méthode `unplannedLeaves()` sur `RoadmapRow` — à confirmer.
- Le partiel `templates/roadmap/_unknown_ends.html.twig` (« Faute d'une fin calculée pour : ») sert déjà la page d'un projet et celle d'une personne ; il peut remplir l'infobulle de la règle 10 — à confirmer.
- La ligne d'un projet vit dans un `<summary>` (`templates/roadmap/index.html.twig`) : tout ce qu'il contient forme son nom accessible ; l'infobulle doit y rester non focalisable, comme les pastilles (`focusable: false`), et être rattachée par `aria-describedby` — à confirmer.
- La largeur `20rem` de la colonne des titres est écrite en dur dans `_frieze.html.twig`, `index.html.twig`, `_row.html.twig`, `roadmap/project.html.twig`, `person/show.html.twig` et `person/_row.html.twig` ; piste : une variable CSS posée sur le cadre de la frise, plus étroite sous le point de rupture `md` — à confirmer, `roadmap_controller.js` mesurant la piste à l'exécution.
- Repère hors période : `RoadmapWindow::bar()` rend `null` quand la barre tombe entièrement hors fenêtre ; comparer `start` et `lastDay` aux bornes de la fenêtre — à confirmer.
