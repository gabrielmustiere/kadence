# Review — Détailler chaque tronçon et chaque feuille dans les infobulles de la roadmap, et zoomer la frise pour les survoler

> **But** : juger le diff au regard de l'intention — dire si on commite, et ce qui bloque.
> **Registre** : technique
> **Story** : `docs/story/009-f-roadmap-infobulles-zoom/`
> **Amont** : `plan.md` · `pitch.md`
> **Diff examiné** : working tree, 18 fichiers modifiés (+445 / −224 lignes) et 6 fichiers créés (`roadmap_tooltip_controller.js`, `RoadmapRun.php`, `RoadmapSegment.php`, `_recap.html.twig`, `_team.html.twig`, documents de story)

## Synthèse

- **Bloquants restants** : 0 / 0
- **Importants restants** : 0 / 0
- **Mineurs restants** : 0 / 3
- **Statut** : **PRÊT À COMMITER**

Le diff suit le plan. Aucun impact modèle, le nombre de requêtes reste constant, le découpage par personne dans et au-delà de l'estimation a disparu, et toutes les infobulles de la frise passent par `roadmap-tooltip`. Les trois mineurs ont été corrigés pendant la passe (44 tests de la roadmap, 8 scénarios E2E, lint propre). Le point d'abord classé important (infobulle figée après un retour arrière) reposait sur un mauvais diagnostic et a été reclassé en mineur, voir ci-dessous. Prochaine étape : `/forge:report`, puis `/forge:sync` et `/forge:commit`.

## Bloquants

_(aucun)_

## Importants

_(aucun)_

## Mineurs

- [x] **[ROBUSTESSE] La fermeture des infobulles avant la mise en cache Turbo reposait sur `disconnect()`** — `templates/roadmap/index.html.twig:54` — Turbo envoie `turbo:before-cache`, attend un tour de boucle, puis copie la page avant d'y rendre la suivante : `disconnect()`, prévu au plan contre ce risque, peut intervenir après la copie, et une infobulle ouverte partirait alors dans le cache. Le défaut n'a pas été reproduit en pratique : le récap visible au premier essai venait d'un survol légitime (pointeur resté sur le lien), et trois essais sans le correctif, pointeur sur le lien ou ailleurs, n'ont laissé aucune infobulle figée. Corrigé par sécurité, sur le motif de `tag-filter` : `turbo:before-cache@document->roadmap-tooltip#hide`, avec un scénario E2E déterministe qui échoue sans ce branchement et passe avec.
- [x] **[ARCHI] `RoadmapRow::realizedQuarters()` n'a plus d'usage** — `src/Model/Roadmap/RoadmapRow.php` — elle servait au « Saisi X sur Y j estimés » de l'infobulle de partie supprimée par ce diff ; aucun gabarit ni test ne l'appelle plus. Corrigé : méthode retirée.
- [x] **[A11Y] Le récap ouvert au clavier n'est pas annoncé** — `templates/roadmap/_row.html.twig:10` — le titre reçoit le focus et affiche le récap, mais rien ne relie l'un à l'autre pour un lecteur d'écran. Corrigé : `aria-describedby` vers le récap sur le lien et sur le `<span>` du titre.

## Points positifs

- **« Une seule infobulle à la fois » garanti par construction** : un seul contrôleur par délégation, qui ferme avant d'ouvrir, sans dépendre d'une transition ni d'une instance par tronçon.
- **Code orphelin retiré** : `beyondEstimate()`, `findQuartersInOrderForLots()`, `realizedTeam` et `overrunTeam` disparaissent, avec une requête de moins sur les feuilles en dépassement.
- **Recette menée jusqu'aux défauts** : récap rogné sous la barre latérale (corrigé par `preventOverflow` borné à la frise) et infobulle perdue après un défilement (corrigée par `pointermove`), tous deux trouvés en manipulant la page.
- **Zoom sans calcul serveur** : la largeur `20rem + max(52rem, 100% − 20rem) × palier` double réellement l'affichage quelle que soit la largeur d'écran, et tout ce qui est placé en pourcentage suit.
- **E2E ciblés sur le comportement à risque** : passage d'un tronçon à l'autre, puis au titre, avec le décompte des infobulles visibles ; survol d'un trou ; focus clavier ; bornes et mémoire du zoom.

## Hors review (à vérifier en environnement réel)

- Compression HTTP en production : le HTML brut de `/roadmap` atteint 333 Ko (513 Ko sur une fenêtre dense), mais 26 à 32 Ko une fois compressé. L'hébergement n'est pas encore décidé ; vérifier que la compression y est active.
