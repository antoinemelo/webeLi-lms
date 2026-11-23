# QCM adaptatif — prototype sous dev

Chaque activité utilise les questions de sa page et celles des blocs **QCM tiers** ajoutés explicitement à cette page. Aucune recherche automatique n’est effectuée dans les autres pages, domaines ou bibliothèques.

## Préparer le contenu

Dans **Créer un QCM en Markdown**, choisir **Adaptatif**, puis **Copier l’exemple**. Coller le texte dans un ou plusieurs blocs Markdown, par exemple :

```text
:::qcm_adap[Finance][Provision]
# Intitulé de la question
[1][120][-][0][0]
[v] Proposition correcte
[x] Proposition incorrecte
[-] Proposition neutre

# Autre question
[2][90][-][0][0]
[v] Première proposition correcte
[v] Deuxième proposition correcte
[x] Proposition incorrecte
:::
```

L’ordre des deux étiquettes est **domaine**, puis **thématique**. Le marqueur `qcm_adaptatif` est également accepté. Le classement `[Domaine][Thématique]` est facultatif pour les QCM classiques, stricts et en classe ; il ne change pas leur fonctionnement.

Les cinq paramètres sous chaque question sont :

1. Niveau initial de la question, entre 0 et 3, fixé par l’enseignant.
2. Temps maximal, en secondes, fixé par l’enseignant (1 à 86 400).
3. Temps moyen observé, en secondes ; `-` lorsqu’aucune réponse dans le délai n’est disponible.
4. Nombre de présentations lors d’évaluations.
5. Nombre de réponses entièrement correctes lors d’évaluations.

Les trois dernières valeurs sont calculées dans la base dédiée. Une saisie, une copie ou un import de compteurs ne remplace jamais les statistiques. Elles sont actualisées à l’ouverture de l’éditeur et dans le tableau **QCM adaptatif : réservoir et réglages**. Une modification de la question, de ses propositions, de son classement, de son niveau ou de son temps maximal crée une nouvelle version statistique. Modifier uniquement les compteurs du texte ne change pas son identité.

Le nombre de propositions est libre, avec au moins deux propositions et une bonne réponse. Toutes les propositions `[v]` doivent être sélectionnées et aucune `[x]` ne doit l’être. Une proposition `[-]` est neutre. Plusieurs bonnes propositions entraînent un affichage à cases à cocher.

## Exemple complet : Finance et comptabilité / Transitoires

Le fichier [expl_qcm-adaptatif.md](../expl_qcm-adaptatif.md) contient les **20 questions fournies**, leurs quatre propositions et leurs corrections. Il peut être collé tel quel dans un bloc **Texte Markdown**. Les consignes de l’activité peuvent être placées dans un texte séparé, avant le réservoir.

Chaque question conserve le niveau 2 et 60 secondes maximum. Le fichier a été adapté par son auteur avec deux présentations et une ou deux réponses justes dans les champs descriptifs. Ces valeurs ne sont pas importées dans la base : elles ne créent pas des taux observés de 50 % ou 100 %. Le moteur utilise les statistiques des évaluations effectivement enregistrées. Le format abrégé `[2][60]` n’est pas accepté : les cinq champs doivent être présents.

Après enregistrement, choisir le niveau de départ **2** dans **Créer un QCM en Markdown → QCM adaptatif : réservoir et réglages**, puis fixer la durée estimée du contenu. Par exemple, **10 minutes** donne une activité de 10 minutes maximum, avec 60 secondes maximum par question. Cette durée est une proposition de réglage, pas une instruction contenue dans le fichier Markdown.

Dans une nouvelle page sans statistiques enregistrées, les questions de cet exemple restent toutes au même taux provisoire de **40 %**, puisque leur niveau vaut 2, indépendamment des compteurs saisis dans le texte. Les premières sélections sont donc départagées au hasard ; les évaluations permettront de distinguer les questions pour les évaluations suivantes. Le curseur et le score évoluent dès la première tentative. Les entraînements ne calibrent pas le réservoir.

## Durée et déroulement

La **durée estimée du contenu**, en minutes, est la limite globale de l’activité. Le chronomètre commence au clic sur **Commencer**. Dans **Créer un QCM en Markdown**, l’entrée **QCM adaptatif : réservoir et réglages** du menu déroulant permet de choisir le niveau de départ (1 par défaut) et de consulter les questions enregistrées. Le réglage est enregistré sans recharger la page ; les modifications du contenu en cours restent en place. Enregistrez le contenu pour actualiser la liste des questions et leurs statistiques.

Le niveau enseignant sélectionne uniquement la première question, au niveau demandé ou au plus proche disponible. Les questions suivantes sont choisies selon leur taux de réussite de référence. En l’absence de statistiques : N0 = 80 %, N1 = 60 %, N2 = 40 %, N3 = 20 %. Le taux observé est `100 × réponses correctes / présentations`.

Le curseur de difficulté commence à `100 − taux de réussite de la première question`. Une réponse correcte l’augmente ; une réponse incorrecte ou un délai dépassé le diminue. Le déplacement est de 20 points dans le premier tiers du temps total, 10 dans le deuxième et 5 dans le dernier. Le tiers est fixé au moment où la question est présentée : attendre pour répondre ne change pas ce déplacement. Le curseur reste entre 0 et 100.

La prochaine question est tirée parmi celles dont le taux de réussite est le plus proche de `100 − curseur`. Les égalités sont départagées aléatoirement. Une question n’est présentée qu’une fois par tentative ; les doublons de contenu sont écartés. Si le réservoir est épuisé avant la fin du temps, l’activité s’arrête et l’indique. Il n’y a pas de nombre maximal de questions à configurer (limite technique du réservoir : 2 000 questions).

Le serveur contrôle les deux échéances. Une question expirée est enregistrée comme non réussie et la suivante apparaît automatiquement. À l’échéance globale, l’activité se termine. Recharger ou quitter la page ne remet pas les chronomètres à zéro ; reprendre après l’échéance termine la tentative. En cas de panne réseau, une commande permet de réessayer l’envoi ; une réponse arrivée hors délai ne devient pas une réponse à temps.

## Entraînement et évaluation

Le statut de l’étape dans le parcours détermine le mode :

- **Entraînement** : tentatives renouvelables, retour après chaque réponse. Aucune mise à jour des statistiques de questions.
- **Évaluation** : une tentative par apprenant et étape, correction à la fin. Chaque question effectivement présentée contribue au compteur des présentations. Une question jamais présentée n’est pas comptée.

Le temps moyen porte sur les réponses correctes ou incorrectes reçues dans le délai, lors d’évaluations uniquement. Les dépassements de délai restent dans le dénominateur du taux de réussite, mais sont exclus du temps moyen.

Les taux de référence sont figés au début de la première tentative d’évaluation d’une étape pour cette version du réservoir, du niveau de départ et de la durée. Les apprenants suivants utilisent la même référence. Les statistiques globales continuent à progresser et serviront aux autres évaluations. Une tentative commencée conserve ses questions et paramètres même si le contenu est modifié ensuite.

## Tester depuis l’aperçu enseignant

Dans l’**Aperçu apprenant** de l’étape (`?view=teacher-preview-page&item=…`), utiliser **Commencer le test**. Les questions, les délais, l’adaptation et le calcul du score utilisent le même moteur que pour les apprenants. Une étape Évaluation garde sa correction à la fin ; un entraînement garde le retour immédiat.

**Recommencer le test** permet de repartir de zéro, pendant ou après l’essai, y compris pour une évaluation. Recharger la page conserve la question et les échéances du test en cours. Le dernier test reste lié à la session du navigateur ; commencer un test sur une autre étape le remplace.

L’aperçu est une simulation isolée : aucune tentative, aucun résultat et aucun compteur ne sont enregistrés dans les données réelles. Les taux de référence d’une évaluation déjà commencée sont repris lorsqu’ils correspondent à la même configuration. Les droits sur le parcours et sur les contenus tiers restent vérifiés à chaque requête. Seul un enseignant autorisé peut lancer cette simulation.

## Arrêts anticipés

Les décisions utilisent les taux de référence figés pour la tentative, y compris les questions des blocs tiers. La **difficulté** d’une question vaut `100 − taux de réussite` : un taux de réussite de 80 % donne une difficulté de 20 %, tandis qu’un taux de 30 % donne une difficulté de 70 %.

Pour un réservoir dont les difficultés ne sont pas toutes identiques :

- **Réussite de la question la plus difficile du réservoir** : le test se termine immédiatement et attribue **10,0 /10**. Plusieurs questions peuvent partager cette difficulté maximale ; il suffit d’en réussir une. Une question plus difficile déjà présentée reste prise en compte : réussir seulement la dernière question disponible ne suffit pas.
- **Échec de la question la plus facile** : le test se termine s’il ne reste aucune question non présentée plus facile ou de facilité équivalente. Sinon, il continue. Un dépassement du délai d’une question compte comme un échec. La note est calculée avec le barème ci-dessous.
- La fin du temps global et l’épuisement du réservoir continuent aussi à terminer le test.

Si **toutes les difficultés sont identiques**, les nouveaux arrêts anticipés sont désactivés : l’activité garde son déroulement initial jusqu’à la fin du temps ou l’épuisement du réservoir. Une première bonne réponse n’attribue donc pas automatiquement 10/10 aux vingt questions encore non calibrées de l’exemple.

## Score et note sur 10

Le **score de difficulté S** reste la moyenne des deux dernières positions du curseur après traitement des réponses, délais dépassés compris. Avec une seule réponse, cette seule position est utilisée. Il ne s’agit pas du pourcentage de bonnes réponses.

En dehors du 10/10 automatique pour la réussite maximale, la note utilise les bornes de difficulté de **tout le réservoir de référence**, pas seulement des questions présentées :

```text
Dmin = difficulté minimale du réservoir
Dmax = difficulté maximale du réservoir
Note /10 = 10 × (S − Dmin) / (Dmax − Dmin)
```

Le calcul conserve la précision du score de difficulté jusqu’à la conversion. La note est limitée à **0–10**, puis **arrondie au dixième**, sans arrondi intermédiaire. Une valeur sous la borne minimale donne 0,0 ; au-dessus de la borne maximale, 10,0. Les bornes ne changent pas au fil des réponses et ne sont pas recalculées à partir des statistiques que la tentative est en train d’alimenter.

| Bornes de difficulté | Score S | Note /10 |
|---|---:|---:|
| 30–80 | 80 | 10,0 |
| 30–80 | 75 | 9,0 |
| 30–80 | 70 | 8,0 |
| 30–80 | 65 | 7,0 |
| 0–100 | 50 | 5,0 |
| 0–100 | 66,66 | 6,7 |
| 20–70 | 20 | 0,0 |
| 20–70 | 33,33 | 2,7 |
| 20–70 | 45 | 5,0 |
| 20–70 | 60 | 8,0 |
| 20–70 | 70 | 10,0 |

**Bornes identiques :** le barème initial est conservé, soit `Note /10 = S / 10`, affichée au dixième. Il n’y a aucune division par zéro. L’apprentissage statistique du réservoir permettra d’utiliser le barème relatif dans de futures évaluations disposant de taux différenciés.

L’écran final indique la **note sur 10**, le score de difficulté et, lorsqu’elles s’appliquent, les deux bornes. Le niveau enseignant 0–3 n’est pas affiché aux apprenants. Le suivi et les exports utilisent la même note QCM ; la note d’évaluation saisie par l’enseignant reste distincte. Dans une page mélangeant QCM classiques et adaptatif, les exports détaillent les résultats séparément ; la synthèse QCM de l’étape retient le score adaptatif.

Le nouveau barème s’applique aux **nouvelles tentatives**. Une tentative déjà commencée garde ses anciennes règles jusqu’à sa fin ; les notes déjà obtenues restent inchangées. Le moteur conserve les questions, les taux de référence, les réponses, les échéances et les positions du curseur pour expliquer le résultat. Le score dépend toujours du niveau de départ et du réservoir disponible.

## Ajouter un QCM tiers

Dans l’éditeur, ajouter un bloc de type **QCM tiers**, puis coller l’URL issue de **Partager ce contenu**. Le propriétaire doit autoriser **Contenu partagé**. Les modes Copie indépendante ou Utilisation externe seuls ne donnent pas ce droit d’intégration.

Le prototype accepte les liens de la même installation liike. Il ne télécharge aucune URL distante. Les questions des blocs QCM directs de la page partagée enrichissent le réservoir ; ses propres références à des blocs tiers ne sont pas suivies. Les questions classiques sans paramètres utilisent provisoirement N1 et 120 secondes. Les questions et statistiques restent rattachées à la source ; plusieurs références identiques ne les dupliquent pas.

La disponibilité du partage est revérifiée au démarrage et à chaque réponse ou reprise. Retirer le partage empêche de poursuivre la séance ; les questions déjà enregistrées et l’historique restent conservés.

## Isolation et retour en arrière

Le module crée uniquement `storage/adaptive-qcm.sqlite` (et ses fichiers SQLite WAL/SHM) à côté de la base LMS. Il ne modifie pas le schéma principal. Les blocs tiers utilisent une représentation Markdown compatible avec les transferts de pages. Les réglages adaptatifs, les tentatives et les statistiques appartiennent à la base dédiée ; une copie de page repart avec de nouvelles statistiques et le niveau initial par défaut.

Pour désactiver l’essai, configurer `APP_ADAPTIVE_QCM=0` dans l’environnement PHP. Les questions ne sont plus proposées et la base dédiée est conservée. Garder le parseur actuel lors de la désactivation : il masque les blocs de réservoir au lieu de les interpréter comme du Markdown ordinaire. Restaurer un ancien parseur tout en conservant des blocs adaptatifs actifs pourrait exposer leur texte aux lecteurs.

Inclure cette base dans les sauvegardes, par une sauvegarde SQLite cohérente. Le nettoyage des anciennes sauvegardes et mises à jour ne touche pas ce fichier actif. Aucun déploiement automatique n’est associé au prototype.

## Vérifications

```sh
php dev/tests/adaptive_qcm.php
php dev/tests/adaptive_qcm_concurrency.php
node dev/tests/adaptive_qcm_browser.mjs
php dev/tests/qcm_modes.php
php dev/tests/smoke.php
```

Les tests utilisent des bases temporaires : sélection locale, délais, double envoi, reprise, étalonnage provisoire, statistiques réservées aux évaluations, score final, affichage mobile, ajout d’un bloc tiers et révocation des droits.
