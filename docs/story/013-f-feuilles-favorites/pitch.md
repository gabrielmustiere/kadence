# Mettre ses feuilles en favori pour les retrouver chaque semaine en tête de sa saisie

> **But** : figer l'intention métier de la feature — ce qu'on livre et pour qui, jamais comment.
> **Registre** : fonctionnel
> **Story** : `docs/story/013-f-feuilles-favorites/`
> **Amont** : aucun

Depuis sa grille de saisie, chacun met en favori, d'un clic sur une étoile, les feuilles sur lesquelles il travaille habituellement. Une feuille favorite est une ligne de la grille chaque semaine, même sans temps saisi récemment, et passe avant les autres lignes. Au retour de congés, la grille n'est plus vide ; et l'ordre des lignes met d'abord en tête ce sur quoi on travaille vraiment.

## Contexte

La grille de saisie propose d'office les feuilles sur lesquelles la personne a saisi la semaine affichée ou la précédente, triées par projet puis par lot et sous-lot. Toute autre feuille se retrouve par la recherche « Ajouter une ligne », et une ligne ajoutée sans temps disparaît au rechargement. La story 003 avait explicitement écarté l'épinglage d'une ligne : « les lignes suivent les temps saisis ».

À l'usage, deux frictions en ressortent. Au retour de deux semaines de congés, la grille est vide : il faut rechercher une à une les feuilles sur lesquelles on travaille pourtant depuis des mois. Et l'ordre alphabétique des projets place ses feuilles principales n'importe où dans la grille, parmi des lignes saisies une fois la semaine précédente. Les deux allongent la saisie que le principe 1 de la vision veut sous la minute, au moment même où elle est la plus exposée à l'oubli : la reprise après une absence.

Cette story revient donc sur la décision de la story 003, au nom du principe 1 : les lignes suivent toujours les temps saisis, et chacun peut en plus épingler les siennes.

**Mesure de succès** : au retour de deux semaines de congés, une personne retrouve ses feuilles habituelles dans sa grille, en tête, sans aucune recherche, et saisit sa journée en autant de clics que de lignes.

## Alignement vision

- **Problème adressé** : indirect. La feature ne traite aucun des trois symptômes, mais elle protège la saisie fiable et fraîche dont dépend toute la chaîne, précisément à la reprise après une absence.
- **Audience servie** : toute l'équipe, dans son geste quotidien de saisie — l'utilisateur secondaire de la vision dont dépend la chaîne ; direction et leads saisissent aussi leurs temps.
- **Principes respectés** : le principe 1 (« saisir sa journée prend moins d'une minute ») est le cœur de la feature, et justifie de revenir sur l'épinglage écarté par la story 003. Le principe 2 est respecté : les favoris sont strictement personnels, ne disent rien du travail de la personne et ne sont visibles de personne d'autre. Le principe 3 est respecté : on épingle une feuille, rien de plus fin. L'anti-objectif « pas d'application mobile native » est tenu : l'étoile fonctionne aussi sur téléphone.
- **Hypothèse testée** : H1 (une saisie de moins d'une minute est tenue à 90 % ou plus). La feature en retire une cause d'abandon identifiée, la grille vide après une absence.
- **Impact North Star** : neutre à indirect, par la fraîcheur du consommé.

## Utilisateurs concernés

- **Toute personne active** (direction, lead, prod) — met en favori et retire de ses favoris les feuilles de sa propre grille ; ses favoris apparaissent en tête de sa grille chaque semaine.
- **Lead et direction, en tant que gestionnaires des projets** — aucun nouveau geste. Découper un lot ou supprimer une feuille, un lot ou un projet reste possible dans les mêmes conditions qu'avant ; les favoris des personnes suivent ou disparaissent sans les bloquer.
- **Personne désactivée** — ne se connecte plus ; ses favoris sont conservés et sans effet.

## User Stories

- En tant que **membre de l'équipe**, je veux mettre en favori une feuille de ma grille d'un clic sur l'étoile de sa ligne afin de la retrouver chaque semaine sans la rechercher.
- En tant que **membre de l'équipe**, je veux mettre en favori une feuille directement depuis les résultats de « Ajouter une ligne » afin de l'épingler en un seul geste, sans l'ajouter d'abord.
- En tant que **membre de l'équipe**, je veux retrouver mes feuilles favorites en tête de ma grille, sur toutes les semaines, même sans temps saisi récemment, afin de saisir dès mon retour de congés.
- En tant que **membre de l'équipe**, je veux distinguer d'un coup d'œil mes favoris des autres lignes afin de savoir ce qui restera dans ma grille la semaine prochaine.
- En tant que **membre de l'équipe**, je veux retirer une feuille de mes favoris d'un clic afin d'alléger ma grille quand je n'y travaille plus.
- En tant que **membre de l'équipe sur téléphone**, je veux mettre en favori et retirer un favori avec la même étoile afin de régler ma grille hors de mon poste.
- En tant que **membre de l'équipe**, je veux que mon favori sur un lot passe sur son premier sous-lot quand un lead le découpe afin de ne pas perdre ma ligne.
- En tant que **membre de l'équipe**, je ne veux PAS que quiconque, lead ou direction compris, voie ou règle mes favoris, afin qu'ils restent un réglage personnel.
- En tant que **lead ou direction**, je ne veux PAS qu'un favori m'empêche de supprimer une feuille, un lot ou un projet sans temps, afin que le référentiel reste sous ma main.

## Règles métier

### Favori

1. **Ce qu'on met en favori** : une feuille, c'est-à-dire un lot sans sous-lot ou un sous-lot. Un projet et un lot découpé ne se mettent jamais en favori.
2. **Nombre** : une personne peut avoir autant de favoris qu'elle le souhaite, sans limite.
3. **Personnel** : les favoris d'une personne ne sont visibles que d'elle-même. Aucun rôle, pas même la direction, ne voit ni ne règle les favoris d'un autre.
4. **Geste** : chaque ligne de la grille porte une étoile ; chaque résultat de « Ajouter une ligne » aussi. Un clic sur l'étoile met la feuille en favori, un nouveau clic l'en retire. Le changement est enregistré immédiatement, sans bouton de validation, comme une case de saisie.
5. **Depuis la recherche** : mettre une feuille en favori depuis les résultats de « Ajouter une ligne » l'ajoute à la grille, parmi les favoris, comme le ferait un ajout.
6. **Sans date** : un favori ne dépend d'aucune semaine. Il vaut pour toutes les semaines affichées, passées, en cours et futures, dès qu'il est posé et jusqu'à ce qu'il soit retiré.

### Grille

7. **Toujours présent** : une feuille favorite est une ligne de la grille sur toute semaine affichée, même sans aucun temps saisi. Les règles de saisie s'y appliquent à l'identique : jours futurs verrouillés, jours fériés, plafonds du jour et de la semaine.
8. **En tête** : les favoris passent avant toutes les autres lignes. Entre eux, ils suivent le tri du référentiel (projet par ordre alphabétique, puis lot et sous-lot par ordre de création) ; les autres lignes gardent ce même tri en dessous.
9. **Distinction** : une feuille favorite porte une étoile pleine, les autres lignes une étoile vide. Aucun intertitre ni séparateur ne sépare les deux groupes.
10. **Une seule ligne par feuille** : une feuille favorite qui porte aussi des temps sur la semaine affichée ou la précédente n'apparaît qu'une fois, parmi les favoris.
11. **Retrait** : une feuille retirée des favoris qui porte des temps sur la semaine affichée ou la précédente reste dans la grille, parmi les autres lignes. Sans temps sur cette période, elle reste affichée jusqu'à ce qu'on change de semaine ou qu'on recharge la page, comme une ligne ajoutée, ce qui permet de se raviser.
12. **Ligne ajoutée puis épinglée** : une ligne ajoutée par la recherche puis mise en favori ne disparaît plus au rechargement.
13. **Grille sans saisie** : une personne qui a des favoris et aucun temps sur la période voit ses favoris, et non le message de grille vide.

### Évolution du référentiel

14. **Découpage** : quand un lot favori reçoit son premier sous-lot, le favori passe sur ce sous-lot, comme ses temps, son estimation et son responsable (règle 27 de la story 003).
15. **Retour à une feuille** : quand la suppression du dernier sous-lot d'un lot fait redevenir ce lot une feuille, les favoris posés sur ce sous-lot passent sur le lot, comme son estimation, son responsable et son équipe.
16. **Suppression** : un favori ne bloque jamais la suppression d'une feuille, d'un lot ou d'un projet. Les favoris des feuilles supprimées disparaissent avec elles, sans avertir les personnes concernées.
17. **Clôture et archivage, plus tard** : quand une feuille sera clôturée ou un projet archivé, ses favoris seront retirés définitivement, puisqu'on ne pourra plus y saisir. Intention à appliquer par les stories `jalons-dates-annoncees` et `archivage-projets`, ces états n'existant pas encore.

## Critères d'acceptation

- [ ] Chaque ligne de la grille porte une étoile ; un clic met la feuille en favori, un second clic l'en retire, sans bouton de validation, et l'état est conservé après rechargement.
- [ ] Un clic sur l'étoile d'un résultat de « Ajouter une ligne » met la feuille en favori et l'ajoute à la grille, parmi les favoris.
- [ ] Une feuille favorite sans aucun temps sur la semaine affichée ni la précédente apparaît dans la grille, y compris sur une semaine passée et sur une semaine future.
- [ ] Les favoris s'affichent avant toutes les autres lignes, triés entre eux par projet puis par lot et sous-lot, les autres lignes gardant ce tri en dessous.
- [ ] Une feuille favorite porte une étoile pleine, les autres lignes une étoile vide.
- [ ] Une feuille favorite qui porte des temps sur la période n'apparaît qu'une fois dans la grille.
- [ ] Une feuille retirée des favoris qui porte des temps sur la semaine affichée ou la précédente reste dans la grille parmi les autres lignes ; sans temps, elle n'est plus affichée après rechargement.
- [ ] Une ligne ajoutée par la recherche puis mise en favori est toujours affichée après rechargement.
- [ ] Sur une feuille favorite, les jours futurs sont verrouillés, les jours fériés non saisissables, et les plafonds du jour et de la semaine s'appliquent comme sur toute ligne.
- [ ] Une personne qui a des favoris et aucun temps sur la période voit ses favoris au lieu du message de grille vide.
- [ ] Sur un écran de téléphone, l'étoile est présente sur chaque ligne et s'utilise de la même façon.
- [ ] Les favoris d'une personne n'apparaissent dans la grille d'aucune autre personne, quel que soit son rôle.
- [ ] Quand un lot favori reçoit son premier sous-lot, ce sous-lot apparaît en favori dans la grille de la personne.
- [ ] Quand la suppression du dernier sous-lot d'un lot fait redevenir ce lot une feuille, le lot apparaît en favori chez les personnes qui avaient ce sous-lot en favori.
- [ ] La suppression d'une feuille, d'un lot ou d'un projet sans temps reste possible quand des personnes l'ont en favori, et la ligne disparaît de leur grille.
- [ ] Au retour de deux semaines sans saisie, une personne retrouve ses feuilles favorites en tête de sa grille sans aucune recherche.

## Hors scope

- **Mettre un projet entier en favori** : non retenu. Un projet de dix sous-lots ajouterait dix lignes à chaque semaine ; on épingle la feuille sur laquelle on saisit.
- **Ordre manuel des favoris** (glisser-déposer) : non retenu. Le tri du référentiel est prévisible et ne demande aucun réglage.
- **Élargir la fenêtre des lignes proposées** au-delà de la semaine affichée et de la précédente : non retenu. Les favoris répondent au retour de congés de façon explicite, sans allonger la grille de lignes devenues inutiles.
- **Favoris réglés par un lead ou la direction pour une autre personne**, ou proposés d'après les affectations d'une feuille : non retenus. Les favoris sont un réglage personnel (principe 2).
- **Masquer une ligne** proposée d'office : non retenu. Les lignes non favorites suivent toujours les temps saisis.
- **Page dédiée à la gestion des favoris** : non retenue. Le geste vit dans la grille et la recherche.
- **Retrait des favoris à la clôture d'une feuille ou à l'archivage d'un projet** : ces états n'existent pas encore ; l'intention est posée (règle 17), son application revient à `jalons-dates-annoncees` et `archivage-projets`.

## Impacts transverses

- **Traduction / langues** : non. L'interface est en français uniquement ; l'étoile porte un libellé accessible (« Mettre en favori » / « Retirer des favoris », suivi du chemin de la feuille).
- **Droits d'accès** : inchangés. Toute personne active règle ses propres favoris, et seulement les siens.
- **Cloisonnement des données** : oui, les favoris sont une donnée personnelle, visible de la seule personne, comme ses temps.
- **Apparence / déclinaisons** : la grille de la semaine sur ordinateur et la grille d'un jour sur téléphone portent la même étoile. Pas de thème ni de déclinaison.
- **Exposition à des tiers** : non.
- **Emails / notifications** : non. La disparition d'un favori par suppression d'une feuille n'est notifiée à personne.
- **Données existantes** : aucun favori n'existe ; chacun part sans favori. Les données de démonstration doivent contenir quelques favoris, dont une feuille favorite sans temps récent.
- **Comportement par défaut** : une personne qui ne pose aucun favori voit sa grille exactement comme aujourd'hui, avec une étoile vide sur chaque ligne.

## Questions ouvertes

_(aucune)_

---

## Annexe — Pistes pour le plan

- Une entité de favori (personne, feuille), unique par couple, supprimée en cascade avec la feuille ; ou une relation plusieurs-à-plusieurs `User` ↔ `Lot` — à confirmer.
- `TimesheetBuilder::build` : fusionner les feuilles favorites avec les feuilles saisies et ajoutées, puis trier en deux groupes (favoris d'abord) avec `LeafOrder::compare` ; `TimesheetRow` porterait un drapeau « favori » — à confirmer. Charger les favoris en une requête pour tenir le budget de requêtes de la page de saisie.
- Live Component `Timesheet` : une `LiveAction` de bascule du favori ; l'étoile dans les résultats de `LeafFinder::search` met en favori et ajoute la feuille d'un même geste — à confirmer.
- `ProjectManager::addSubLot` : déplacer les favoris du lot vers le premier sous-lot, à côté de `TimeEntryRepository::moveToLot` et `LotProgressRepository::moveToLot` ; `ProjectManager::deleteLot` (via `detach`) : les ramener sur le lot redevenu feuille ; `deleteProject` / `deleteLot` : ne jamais bloquer sur un favori — à confirmer.
- Accessibilité : étoile en bouton avec `aria-pressed` et libellé incluant le chemin de la feuille, comme les crans de la barre de quarts.
- Fixtures de démonstration : quelques favoris pour les comptes de test, dont un sans temps sur les deux dernières semaines.
