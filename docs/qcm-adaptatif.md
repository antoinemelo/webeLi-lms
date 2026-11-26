# QCM adaptatif — fonctionnement et calibration

Chaque activité utilise exclusivement les questions adaptatives de sa page et les blocs **QCM tiers** explicitement ajoutés. Aucun réservoir extérieur n’est parcouru automatiquement.

## Préparer et voir les questions

Dans **Créer un QCM en Markdown**, choisir **Adaptatif**, puis copier l’exemple dans un bloc Markdown. Prévoir au moins cinq questions distinctes et assez de temps pour les traiter.

```text
:::qcm_adap[Finance][Transitoires]
# Intitulé de la question
[2][60][-][0][0]
[v] Proposition correcte
[x] Proposition incorrecte
:::
```

Les cinq champs sont : niveau initial enseignant (0–3), délai maximal en secondes, temps moyen observé, présentations en évaluation ou exercice contrôlé, réponses justes dans ces modes. Les trois derniers sont descriptifs : les saisir ou les importer ne remplace jamais les statistiques en base. `[2][60]` seul est incomplet. Les compteurs sont actualisés à l’ouverture de l’éditeur, y compris avec des retours à la ligne Windows (CRLF).

Le tableau **Niveaux et statistiques des questions**, directement dans l’éditeur, distingue :

- **Niveau initial** : repère fixé par l’enseignant, jamais remplacé automatiquement.
- **Difficulté estimée** : 100 moins le taux de réussite estimé ; un pourcentage élevé désigne une question plus difficile.
- **Réussite observée** et **réponses retenues** : observations admissibles pour la calibration.
- **Temps moyen**, présentations et réponses justes : informations descriptives.
- **Estimation initiale**, **Historique limité**, **Historique disponible** : respectivement aucune observation, moins de 20 observations, au moins 20. Le seuil de 20 est un repère de volume, pas une validation scientifique.

Le menu **QCM adaptatif : réservoir et réglages** rassemble ces informations, le niveau de départ du test et l’actualisation des références d’évaluation. Enregistrer ces réglages conserve le texte non enregistré dans l’éditeur.

Toutes les propositions `[v]` doivent être choisies et aucune `[x]` ne doit l’être. `[-]` reste neutre. Il n’y a pas de crédit partiel ; plusieurs bonnes propositions entraînent des cases à cocher.

## Choisir le type d’étape et autoriser une reprise

Dans les réglages du parcours, le type d’étape détermine le traitement des réponses :

| Type | Résultat du QCM | Calibration des questions adaptatives |
| --- | --- | --- |
| Consultation simple / Autoévaluation | Entraînement, nouvelle tentative après la fin | Non |
| Exercice contrôlé | Correction à la fin ; aucune note conservée | Oui |
| Évaluation | Une tentative, résultat sur 10 si au moins cinq questions | Oui |

En quittant **Évaluation**, une alerte et une case de confirmation précèdent la suppression des notes, validations, commentaires d’évaluation, résultats QCM et brouillons de l’étape. Tout changement de type remet les tentatives à zéro ; revenir au type précédent ne restaure pas les résultats. Les statistiques acquises des questions restent conservées. Un simple changement de date, de pondération ou d’accès ne remet pas les tentatives à zéro.

Pour reprendre une activité qui s’est mal passée : **Apprenants → Progression → activité évaluée → Autoriser un nouvel essai**. Confirmer la suppression du résultat précédent. Les QCM classiques et adaptatifs de cette activité sont rouverts pour ce seul apprenant. Ses autres activités et les autres apprenants restent inchangés. La nouvelle tentative utilise la référence d’évaluation commune, sauf actualisation explicite de celle-ci. Une reprise ne fait pas contribuer deux fois la même personne à la calibration d’une même version de question.

Les anciens onglets sont refusés après une réinitialisation. Les identifiants de génération sont contrôlés côté serveur ; un double clic sur l’autorisation ne crée pas deux reprises. Les générations remplacées disparaissent immédiatement des bilans, puis sont nettoyées par la maintenance. Pour un QCM classique contrôlé, seule une trace de réalisation est stockée, sans note ; les statistiques de difficulté concernent le réservoir adaptatif.

## Démarrer sans historique

Les taux initiaux de réussite restent N0 = 80 %, N1 = 60 %, N2 = 40 %, N3 = 20 %. Ils constituent une estimation de départ, pas des observations fictives.

Les résultats prennent progressivement le relais. Sans effet de rapidité, le taux estimé est :

```text
(réponses correctes retenues + 10 × taux initial) / (réponses retenues + 10)
```

Le taux initial est exprimé entre 0 et 1. Une question initialement à 40 % passe à environ **45,5 %** après une réussite, au lieu de 100 %. Une réponse en attente ne fait pas baisser le taux.

Si toutes les questions ont le même niveau et aucune observation, leurs difficultés sont indifférenciées. Le tirage les départage au hasard ; le système ne peut pas inventer une différence fiable. Varier les niveaux initiaux et consacrer les premiers groupes à la calibration améliore le réservoir. Les statistiques ne transforment pas instantanément une banque novice en instrument de mesure validé.

## Quelles observations contribuent ?

Les évaluations et exercices contrôlés d’apprenants authentifiés contribuent à la calibration. Les entraînements, aperçus enseignants et séances anonymes en sont exclus.

Pour une version de question, seule la première observation admissible de chaque apprenant contribue, même si la question est utilisée dans plusieurs parcours. Les présentations et réponses descriptives continuent à être comptées ; le tableau distingue donc ces compteurs du nombre d’observations retenues.

Une observation doit être résolue et avoir bénéficié de la fenêtre complète de temps prévue. Un délai individuel complet dépassé compte comme un échec. Une question tronquée par la fin globale est exclue de la calibration. Les anciennes observations sont reprises avec ces mêmes critères lors de la migration, sans modifier leurs anciennes notes.

### Réponse correcte et rapide

Une réponse correcte peut recevoir un poids de **1,25** au lieu de 1 dans la calibration uniquement si :

- la référence contient déjà au moins cinq réponses chronométrées pour cette question ;
- le temps est au moins `max(3 secondes, 5 % du délai maximal)` ;
- il est au plus égal à la moitié du temps moyen de référence.

La référence de temps est figée pour l’évaluation. Une réponse presque instantanée, une réponse lente ou une erreur garde le poids normal. La lenteur n’ajoute aucune pénalité de difficulté. Le poids supplémentaire est enregistré, pas ajouté aux compteurs bruts. Le taux estimé utilise les réussites pondérées et la somme des poids, avec le même poids initial de 10.

**La rapidité ne donne aucun bonus direct à la note de l’apprenant.** Elle peut seulement faciliter légèrement la question pour les évaluations futures. Ces seuils prudents restent des paramètres de fonctionnement à confronter aux usages réels.

## Identité, modifications et partage

Les questions sont identifiées et versionnées en base. Le Markdown est l’interface de rédaction, pas le journal des réponses.

- Modifier l’énoncé, une proposition, sa correction ou le temps maximal crée une nouvelle version statistique. Les observations de l’ancienne version et les tentatives restent conservées.
- Modifier les compteurs descriptifs, le classement, le niveau initial ou l’ordre des propositions ne supprime pas les observations.
- Les espaces et retours à la ligne sont normalisés ; une simple ponctuation modifiée reste, par prudence, une modification de l’énoncé.
- Déplacer l’ordre des questions dans un même bloc ne change pas leur identité. Déplacer une question vers un autre bloc, recréer le bloc, copier ou importer une page crée de nouvelles identités. Il n’y a pas de fusion automatique d’historiques indépendants.

Une page réellement partagée ou un bloc **QCM tiers** reprend les identités et les observations de la source sur la même installation. Les évaluations des collègues contribuent donc à la même calibration, sans publier les résultats nominatifs de leurs apprenants dans le tableau des questions. Une **copie indépendante** repart avec ses propres observations.

Les QCM tiers acceptent uniquement les liens de partage de cette installation avec le mode **Contenu partagé**. Les liens externes à l’installation et le seul mode Copie indépendante ou Utilisation externe ne suffisent pas. Aucune référence récursive n’est suivie. Les droits sont revérifiés à chaque reprise et réponse ; leur retrait bloque la poursuite, sans effacer les données enregistrées.

## Références d’évaluation

Les questions, leurs taux estimés et leurs repères de temps sont figés au début d’une évaluation. Les apprenants suivants utilisent cette référence commune. Les nouvelles observations continuent de s’accumuler, mais ne changent pas rétroactivement le test.

**Actualiser pour les prochains participants** prépare une nouvelle référence à partir des observations actuelles. Cette commande est réservée à un enseignant autorisé sur le parcours et le contenu, avec protection CSRF, et refuse toute actualisation pendant une tentative non terminée dont le délai est encore ouvert. Elle ne donne pas de nouvel essai aux apprenants déjà évalués.

Une modification des questions ou réglages qui changerait une référence déjà engagée nécessite son actualisation explicite avant de nouveaux départs. Les tentatives en cours conservent leur copie des questions et de la correction. Réorganiser simplement les mêmes questions n’altère pas la référence du nouveau modèle.

Les anciennes évaluations gardent leur ancien barème, y compris pour les nouveaux participants tant que leur référence n’est pas actualisée. Les tentatives et notes existantes ne sont jamais recalculées par cette actualisation.

## Durée et reprise

La durée estimée du contenu fixe la durée totale. Le temps utilisable commence lorsque le serveur présente effectivement la première question, pour ne pas pénaliser une attente de verrou à l’ouverture. La question suivante doit disposer de son délai maximal complet : sinon l’activité se termine avec une explication, sans présenter une question pour une seule seconde.

Une question n’apparaît qu’une fois par tentative. L’activité s’arrête lorsque les questions sont épuisées, le temps total est écoulé ou qu’aucune question restante ne dispose de son temps maximal.

Recharger, ouvrir un autre onglet ou renvoyer un démarrage conserve la tentative. Les commandes sont idempotentes ; les échéances et corrections sont contrôlées côté serveur. Une évaluation reste limitée à une tentative. Un entraînement peut être refait seulement après la fin. Une erreur réseau donne une commande pour réessayer ; elle ne remet pas le temps à zéro.

Le démarrage HTTP et la première présentation sont enregistrés dans une seule transaction : en cas d’échec, les deux sont annulés ensemble. Une reprise avec une question encore ouverte ne demande pas de verrou d’écriture.

## Note lisible et minimum de cinq

Pour le nouveau calcul, **au moins cinq questions traitées sont nécessaires**. Sont prises en compte les réponses correctes, incorrectes et les délais individuels complets dépassés. Une interruption par la fin globale sans fenêtre complète n’entre pas dans ce minimum.

En dessous, le résultat indique **« Moins de 5 questions traitées : aucune note automatique. »** L’activité peut être terminée sans être notée ; les suivis et exports ne remplacent pas cette absence par zéro.

Toutes les réponses et difficultés rencontrées contribuent au calcul. Les ajustements deviennent progressivement plus prudents ; les anciens pas de 20, 10 et 5 points liés au chronomètre sont supprimés. La moyenne des deux dernières positions et le 10/10 après une seule question difficile réussie ne s’appliquent plus aux nouvelles références.

Les réponses connues sont comptées telles quelles. Le système estime les réussites sur les questions non posées, puis rapporte l’ensemble au réservoir complet. La note est bornée entre 0 et 10 et arrondie au dixième. Si toutes les questions ont été traitées, **6 bonnes réponses sur 8 donnent exactement 7,5 /10**. Une banque encore peu documentée est signalée. La note d’évaluation saisie par l’enseignant reste indépendante.

### Détail du modèle

Le modèle utilise une probabilité de type Rasch : `P(réussite) = 1 / (1 + exp(difficulté − aptitude))`. Un taux estimé `p` devient une difficulté `log((1−p)/p)`, bornée numériquement via des taux entre 1 et 99 %. L’estimation de l’aptitude utilise toutes les réponses avec un a priori normal de moyenne 0 et d’écart-type 1,5, sur une grille de −6 à 6 par pas de 0,1. Le niveau de départ choisit la première question, pas l’a priori de notation.

Les questions suivantes visent la difficulté la plus proche de l’aptitude estimée, parmi les questions disponibles et compatibles avec le temps restant. La note utilise la distribution estimée pour prédire les seules questions non traitées. Le modèle stabilise les cas tout juste / tout faux et permet des tests numériques reproductibles ; il ne prouve pas une validité psychométrique. Les taux restent dépendants des populations et conditions d’évaluation. Cinq réponses sont un minimum opérationnel, pas un certificat de précision.

## Aperçu enseignant

**Commencer le test** dans l’aperçu apprenant utilise le même moteur. **Recommencer le test** reste possible dans cette simulation uniquement. Les questions et paramètres sont conservés en session ; aucune tentative, observation de calibration ou note réelle n’est créée. Le tableau enseignant présente les observations actuelles ; une évaluation déjà figée utilise sa référence propre.

## Corrigé temporaire après le test

Sous le score, le corrigé montre chaque question présentée. Pour une question réussie, seules les bonnes propositions apparaissent, en **vert**. Pour une question non réussie, les bonnes propositions apparaissent en **orange** et les mauvais choix de l’apprenant en **rouge**. Les libellés expliquent aussi leur sens sans dépendre uniquement des couleurs. Une proposition neutre sélectionnée reste explicitement signalée sans être présentée comme une erreur.

Le corrigé disparaît en quittant ou rechargeant la page. Le résultat reste consultable dans le suivi ; les questions et choix individuels ne constituent plus un historique consultable.

## Stockage, sauvegarde et désactivation

La base dédiée est **`storage/adaptive.sqlite`**, schéma 7. Elle se crée automatiquement au premier enregistrement des réglages ou accès apprenant ; l’aperçu seul peut fonctionner en mémoire. PHP doit pouvoir écrire dans `storage/`. Aucune base vide n’est à copier depuis dev.

La migration des schémas 1/2/3/4/5/6 conserve les identités anciennes et les résultats, construit les correspondances de versions et les observations admissibles. Une sauvegarde SQLite cohérente est créée avant la migration d’une base sur disque. Les mises à jour de l’application sauvegardent aussi la base adaptative lorsqu’elle existe. Garder ensemble les sauvegardes de la base LMS et de sa base adaptative.

Depuis le schéma 5, `question_sets` conserve une seule copie de chaque réservoir figé. Les tentatives et références d’évaluation pointent vers cette copie : le texte complet n’est plus dupliqué pour chaque apprenant. Les champs historiques `pool` restent vides en base ; le moteur restitue leur contenu à partir de `pool_id`. La migration préserve les notes, échéances et versions, puis compacte la base une fois. Les réponses d’une tentative encore en cours sont conservées jusqu’à sa fin. Elle active la récupération incrémentale de l’espace libre. Si un verrou empêche cette compaction, les données migrées restent utilisables et l’opération peut être reprise lors d’une maintenance.

La maintenance habituelle et la purge du superadmin suppriment les tentatives dont l’étape, le compte ou l’inscription a été **supprimé définitivement**. Les tentatives remplacées après changement de type ou autorisation individuelle d’un nouvel essai sont également purgées. Un archivage ne déclenche pas cette suppression. Les anciens scores d’entraînement restent conservés. Seules les tentatives sans note d’entraînement ou d’exercice contrôlé peuvent être remplacées par la dernière tentative terminée ; une tentative en cours reste conservée. Les réponses correspondantes et les réservoirs sans référence sont libérés. Les totaux de calibration et les compteurs descriptifs par question survivent à la suppression d’une étape ou d’un compte, sans conserver les résultats individuels. La maintenance termine aussi jusqu’à 50 tentatives expirées par passage pour effacer leurs réponses temporaires, même si personne ne les rouvre. Le passage automatique traite au plus 500 tentatives par lot et n’attend pas un verrou d’écriture ; la purge manuelle traite l’ensemble. Aucun `VACUUM` complet ne s’exécute lors d’une consultation ordinaire : la maintenance récupère au plus 64 pages libres par passage.

Depuis le schéma 7, les réponses, choix et temps individuels restent en base **uniquement pendant la tentative** pour permettre sa reprise et calculer le score. Dans la même transaction que la fin, le moteur conserve la note, les compteurs du résultat et les indications de barème, puis supprime les réponses et la trajectoire détaillée. La migration efface aussi ces détails pour les tentatives déjà terminées, sans recalculer leurs notes. Les rapports utilisent les totaux sauvegardés, sans relire de réponses individuelles.

La calibration conserve des **totaux par version de question** (observations, réussites, poids et sommes des temps). Une empreinte SHA-256 binaire de la paire question/apprenant empêche les contributions répétées, sans enregistrer la paire en clair ni son résultat ou son temps. Cette empreinte est un mécanisme de déduplication, pas une garantie d’anonymisation contre des recoupements. Les 1 446 anciennes observations du snapshot du 26 novembre sont agrégées : les difficultés obtenues restent identiques.

Le corrigé est envoyé seulement dans la réponse HTTP qui termine la tentative et reste dans la page ouverte. Aucun corrigé n’est enregistré en session, `localStorage` ou `sessionStorage`. Quitter, recharger ou revenir par l’historique conserve le résultat sans restituer le corrigé. Si cette dernière réponse réseau est perdue, réessayer restitue le score ; le détail déjà effacé ne peut pas être récupéré. Même principe pour l’aperçu enseignant. Les sauvegardes antérieures à cette migration, y compris la sauvegarde de sécurité créée avant celle-ci, peuvent encore contenir l’ancien historique jusqu’à leur nettoyage habituel par le superadmin.

`APP_ADAPTIVE_QCM=0` désactive le module sans supprimer sa base. Conserver le parseur actuel, qui masque les réservoirs aux apprenants. Un retour à un ancien code ne doit pas être réalisé seul sur une base migrée : préférer la désactivation, ou une restauration cohérente des bases et du code, après sauvegarde des données plus récentes.

Le répertoire local `quiz-test` est un matériel d’audit, **pas une dépendance de l’application**. Il est ignoré par Git et n’appartient pas à la liste de fichiers publiée. Aucun déploiement n’est effectué par ces modifications.

## Vérifications

```sh
php dev/tests/adaptive_qcm.php                 # barèmes antérieurs et compatibilité
php dev/tests/adaptive_qcm_model.php           # nouveau calcul, minimum, vitesse, références
php dev/tests/adaptive_qcm_audit.php dev/quiz-test  # optionnel, copies des snapshots locaux
php dev/tests/adaptive_qcm_storage.php dev/quiz-test # migration, taille et purge sur copies
php dev/tests/adaptive_qcm_retention.php       # corrigé temporaire, purge atomique, reprise
php dev/tests/adaptive_qcm_concurrency.php
node dev/tests/adaptive_qcm_browser.mjs
php dev/tests/qcm_modes.php
```

Les tests ne s’exécutent pas sur la base active. Les snapshots fournis sont copiés dans un dossier temporaire et leurs empreintes originales vérifiées après lecture. Les tests techniques sont complétés par des contrôles sur les résultats observés ; une étude de calibration avec des groupes plus variés reste distincte de ces vérifications logicielles.
