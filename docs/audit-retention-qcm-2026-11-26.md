# QCM adaptatif : corrigé temporaire et conservation des scores

Audit du 26 novembre 2026. Travail effectué dans `dev`, sans déploiement. Les deux bases de `quiz-test` ont servi uniquement de sources : migrations et tests réalisés sur des copies temporaires.

## Constat dans les snapshots

- `adaptive.sqlite` : 1 187 840 octets, schéma 6, 23 tentatives terminées et 465 réponses détaillées.
- Calibration : 1 446 observations pour 264 versions de questions et 42 apprenants. 450 sont encore reliées à 21 tentatives ; 996 avaient été détachées lors de purges précédentes. Ce nombre désigne des contributions à des questions, pas des tests complets.
- 266 versions de questions enregistrées et 3 réservoirs figés communs. La duplication des réservoirs par apprenant avait déjà été supprimée.

## Modification

Le schéma 7 stocke les réponses seulement pendant une tentative. Sa clôture calcule la note et les totaux, puis efface les réponses et la trajectoire dans la même transaction. Le corrigé est envoyé une fois à la page qui termine le test. Il n’est enregistré ni en session PHP ni dans le stockage du navigateur ; quitter, recharger ou revenir par l’historique ne le restitue pas. Une réponse finale perdue sur le réseau permet de retrouver le score, pas les détails supprimés.

Les anciennes observations de calibration sont remplacées par des totaux par question. Une empreinte binaire sans résultat associé évite de recompter le même apprenant. Les moyennes, poids, taux et difficultés restent identiques. Les empreintes ne constituent pas une garantie d’anonymisation contre des recoupements.

Le corrigé affiche les bonnes propositions en vert pour les questions réussies ; pour les questions non réussies, les propositions attendues en orange et les choix incorrects en rouge. Les libellés doublent l’information colorée. Les choix neutres restent explicitement neutres. Les scores d’entraînement sont conservés lors du nettoyage ; les règles existantes de suppression des notes après changement de mode, nouvel essai autorisé ou suppression définitive restent applicables.

## Mesures et validation

Après migration et compaction de la copie : **688 128 octets**, soit **499 712 octets économisés (42,1 %)**. Les 23 tentatives restent présentes, les notes sont strictement identiques, les compteurs correspondent aux réponses initiales et les totaux de calibration sont conservés. Aucun détail de réponse terminé ne subsiste dans la base active migrée. Les sauvegardes de sécurité antérieures restent soumises à leur nettoyage habituel.

Vérifications : migration concurrente, intégrité SQLite, notes recalculées sur les données initiales à titre de contrôle, reprise des tentatives actives, absence de double comptage, purge atomique et annulation transactionnelle en cas d’échec, expiration sans retour de l’apprenant, aperçu enseignant sans corrigé en session, correction mobile et effacement au retour arrière. Un essai de 30 apprenants simultanés sur un réservoir de 300 questions a terminé sans erreur ni double comptage (4,76 s dans cet environnement de test ; ce n’est pas une mesure du serveur live).

Empreintes SHA-256 des sources, inchangées après les tests :

- `adaptive.sqlite` : `40d3809038cb14987065c47ebeec76455ba66d0dadacae1bb1053e1b75b4e91f`
- `apr.sqlite` : `337a6f3d5085c003280db81984f01722d65eaf32aad53a30d8f058a6b813c482`

Commandes reproductibles : voir [le guide du QCM adaptatif](qcm-adaptatif.md#vérifications). Les guides des apprenants et des enseignants ont été actualisés en HTML et PDF.
