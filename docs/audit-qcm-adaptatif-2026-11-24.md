# QCM adaptatif : audit et fiabilisation du 24 novembre 2026

## Périmètre et conclusion

L’audit utilise les deux bases et les références Markdown fournies dans `quiz-test`, uniquement comme matériel d’analyse. Les tests travaillent sur des copies temporaires ; les fichiers originaux restent inchangés. Les modifications concernent `dev/`, sans déploiement.

Le stockage des questions en Markdown n’empêche pas une exécution fiable : le texte sert à rédiger ; SQLite conserve les versions, les références figées, les réponses et les observations. Les défauts constatés concernaient surtout la calibration, la notation et la restitution des statistiques.

Le fonctionnement corrigé est plus prudent et vérifiable. Il ne constitue pas une validation psychométrique : cinq réponses sont un minimum opérationnel, et la qualité de la mesure dépend encore des questions, du groupe et des conditions d’évaluation.

## Constats sur les données fournies

- Les deux bases passent le contrôle d’intégrité SQLite.
- Une activité comprend 20 questions, toutes initialement au niveau 2, avec 60 secondes par question et six minutes au total.
- Les 15 évaluations terminées représentent 159 présentations : 57 réponses correctes, 75 incorrectes et 27 délais dépassés. Les corrections et compteurs ont été réconciliés sans incohérence.
- Toutes les tentatives utilisent la même référence provisoire de réussite à 40 %. Les observations courantes diffèrent déjà, mais une référence engagée reste figée. Il manquait une commande explicite pour préparer la référence suivante.
- Les retours à la ligne Windows empêchaient l’affichage des compteurs actualisés dans l’éditeur.
- Une présentation encore sans réponse pouvait faire baisser le taux ; une première réussite pouvait le faire passer directement à 100 %.
- Le barème dépendait du moment où les réponses étaient données. Un scénario synthétique identique de trois réussites puis trois erreurs donnait 5/10 ou 8,25/10 selon le rythme.
- Une seule réussite à la question la plus difficile pouvait suffire pour 10/10.
- Certaines questions étaient présentées sans leur délai complet, parfois pour environ une seconde.
- Le niveau initial, le classement et l’ordre des propositions participaient à l’identité statistique, alors que ces modifications ne changent pas nécessairement ce qui est évalué.

## Changements appliqués

### Calibration des questions

Une estimation initiale de poids 10 est progressivement remplacée par les observations admissibles. Une question initialement à 40 % passe ainsi à environ 45,5 % après une première réussite. Les réponses en attente ne modifient pas son taux.

Seule la première observation admissible d’un apprenant pour une version de question contribue à la calibration, même si plusieurs parcours utilisent cette source. Les entraînements, aperçus enseignants et séances anonymes sont exclus. Une fenêtre individuelle tronquée est également exclue.

Une bonne réponse raisonnablement rapide reçoit un poids limité à 1,25 lorsque cinq réponses chronométrées existent déjà et que les seuils documentés sont respectés. Une réponse presque instantanée ou lente conserve le poids normal ; la lenteur n’ajoute pas de pénalité. La rapidité ne donne aucun bonus direct à la note.

### Versions et partage

Les changements d’énoncé, de propositions, de correction ou de temps maximal créent une nouvelle version. Les changements de classement, de compteurs, de niveau initial ou d’ordre des propositions conservent les observations. Les tentatives engagées gardent leur copie des questions.

Les sources réellement partagées et les blocs QCM tiers utilisent les identités de la source. Les copies indépendantes démarrent leur propre historique. Une question déplacée vers un autre bloc ou une page recréée reçoit une nouvelle identité : aucune fusion arbitraire d’historiques n’est réalisée.

La commande **Actualiser pour les prochains participants** prépare une nouvelle référence. Elle exige les droits sur le parcours et le contenu, refuse les tentatives encore en cours et ne permet pas de repasser une évaluation déjà effectuée. Les anciennes tentatives gardent leur note et leur barème.

### Déroulement et résultat

Le nouveau modèle utilise toutes les réponses et leurs difficultés, avec une estimation prudente qui se stabilise progressivement. Les sauts de 20/10/5 points liés au chronomètre disparaissent des nouvelles références.

Le résultat estime la réussite sur le réservoir entier : les réponses connues sont conservées, les autres sont estimées. Si le réservoir a été entièrement traité, six bonnes réponses sur huit donnent exactement **7,5 /10**. Le calcul détaillé figure dans [la documentation du module](qcm-adaptatif.md).

En dessous de **cinq questions traitées**, aucune note automatique n’est attribuée. Une activité peut donc être terminée sans être notée ; les moyennes et exports ne transforment pas ce cas en zéro.

Le temps utilisable commence à la présentation de la première question. Une question supplémentaire n’est proposée que si son délai maximal reste disponible. Un rechargement, un double envoi ou un nouvel onglet ne remet pas une tentative à zéro.

### Lisibilité et exploitation

L’éditeur montre directement le niveau initial, la difficulté estimée, la réussite observée, les réponses retenues et le temps moyen. Les mentions « Estimation initiale », « Historique limité » et « Historique disponible » distinguent la quantité d’observations ; leur seuil ne certifie pas la précision.

L’apprenant reçoit une note sur 10 avec une décimale, le nombre de questions prises en compte et une explication simple en l’absence de note. Les guides HTML et PDF ont été actualisés et vérifiés visuellement, sur neuf pages pour les enseignants et cinq pour les apprenants.

Le démarrage et la première présentation sont enregistrés dans une seule transaction. Les reprises ordinaires ne demandent pas de verrou d’écriture. Les doubles réponses sont idempotentes. Les garanties de synchronisation SQLite n’ont pas été réduites pour accélérer les écritures.

## Migration et retour arrière

La base reste `storage/adaptive.sqlite`, désormais au schéma 4. Une sauvegarde cohérente précède sa migration ; les sauvegardes de mise à jour incluent aussi cette base. Les anciennes notes et réponses des snapshots sont strictement conservées après migration.

`APP_ADAPTIVE_QCM=0` permet de désactiver le module sans supprimer les données. Revenir à un ancien code seul sur une base migrée n’est pas un retour arrière sûr : conserver le code actuel avec le module désactivé, ou restaurer ensemble des sauvegardes cohérentes du code et des deux bases après avoir sauvegardé les données récentes.

## Vérifications et limites

Les suites couvrent le nouveau modèle, les barèmes antérieurs, les migrations, les observations partagées, les droits d’accès, les délais, la reprise, l’absence de note, les sauvegardes et l’aperçu enseignant. Les scénarios HTTP utilisent une installation temporaire et des utilisateurs fictifs.

| Vérification | Résultat |
| --- | --- |
| Compatibilité et barèmes antérieurs | 75 contrôles réussis |
| Nouveau modèle, calibration, références et démarrage atomique | 31 contrôles réussis |
| Audit sur copies des données fournies | 10 contrôles, aucune anomalie |
| Mises à jour et sauvegardes | 24 contrôles réussis |
| Régression générale | 183 contrôles réussis |
| Modes QCM classiques | Suite réussie |
| Navigateur mobile et API | 9 scénarios réussis |
| Concurrence : 30 apprenants, 300 questions, doubles envois | Réussite, 8,12 secondes pour l’ensemble du scénario local |
| Syntaxe PHP et JavaScript | Aucune erreur dans les fichiers vérifiés |

Une ancienne assertion de la suite générale attendait encore l’ancienne signature de l’aide QCM ; elle a été alignée sur le modal qui reçoit aussi la page et ses blocs. Le test navigateur tolère un arrondi inférieur à une milliseconde lors de la sérialisation d’une échéance, tout en vérifiant que la reprise conserve cette échéance.

Le contrôle de concurrence comprend 30 apprenants, un réservoir de 300 questions, des doubles envois et la vérification d’une référence commune. Des essais préalables ont exposé des attentes de verrou dans l’environnement local ; le démarrage a été regroupé pour réduire les transactions. La durée mesurée est un résultat local, pas une garantie de débit sur le serveur de production.

Les observations statistiques restent dépendantes du public. Une banque peu variée, de faibles effectifs ou des réponses obtenues avec assistance peuvent fausser l’estimation. Il faut confronter les premières évaluations aux appréciations pédagogiques avant de considérer ces notes comme une mesure précise du niveau.

## Références consultées

Ces projets servent à examiner les principes d’estimation, de sélection et de suivi des questions. Aucun de ces plugins n’a été installé dans le LMS.

- [Moodle Adaptive Quiz : algorithme adaptatif](https://github.com/vtos/moodle-mod_adaptivequiz/blob/7d64eb76ef9dcb06f35167ef1e714cfb8f52b4b9/classes/local/catalgo.php).
- [CAPQuiz : estimation Elo](https://github.com/KQMATH/moodle-mod_capquiz/blob/ee0ee8cdf8f7e4f40a7ecd1b8817ef58e5e0616b/classes/local/helpers/elo.php) et [rapport enseignant sur les questions](https://kqmath.github.io/docs/capquiz/Questions-report).
- [ALiSe : modèle de Rasch](https://github.com/Wunderbyte-GmbH/moodle-local_catquiz/blob/9e15399d5d759d062ec88e84d3323f04bc3dfd72/catmodel/rasch/classes/rasch.php).
