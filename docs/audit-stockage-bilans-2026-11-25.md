# Audit du stockage adaptatif, bilans et modes d’activité — 25 novembre 2026

Modifications réalisées dans `dev/`, sans déploiement. Les deux bases de `quiz-test/` ont été examinées en lecture seule puis copiées dans des répertoires temporaires pour les migrations et les tests. Ce répertoire n’est pas utilisé par l’application. Aucune base de fonctionnement de dev ou du serveur live n’a été modifiée.

## Origine du volume

Les snapshots fournis sont intègres. La base adaptative contient 70 tentatives, 999 réponses, 166 versions de questions et 953 observations de calibration. Il n’y a pas de doublon de calibration pour un même apprenant et une même version de question.

Le volume provient surtout des réservoirs JSON : **2 615 620 octets répartis entre 70 tentatives, pour seulement 6 contenus distincts**. Chaque tentative recopiait ses questions, propositions et paramètres. Le nombre de pages de contenu ne permet donc pas, à lui seul, d’estimer ce volume. Seulement 64 Ko provenaient de pages SQLite libres.

Par ailleurs, **47 tentatives concernaient des étapes définitivement supprimées** de la base LMS. Les réponses n’étaient pas orphelines dans la base adaptative elle-même : les références manquantes se situaient entre les deux bases.

| Mesure sur copies | Taille de la base adaptative |
| --- | ---: |
| Snapshot initial | 3 874 816 octets |
| Migration et mutualisation, 70 tentatives conservées | 1 212 416 octets |
| Après purge des 47 tentatives sans étape, 23 conservées | 937 984 octets |

La réduction finale est d’environ **76 %**. Les notes, réponses, délais et références des tentatives conservées restent inchangés. Toutes les notes du modèle actuel ont été recalculées depuis les réponses fournies : aucune divergence. Les versions des questions, leurs compteurs et leurs observations de calibration restent conservés après la purge des étapes supprimées.

La base principale contient notamment 3 478 notifications déjà envoyées et 2 012 traces de visite récentes. Ce constat n’est pas une raison de supprimer les données utiles au suivi ; leur politique de conservation n’a pas été raccourcie.

## Stockage et entretien

- Une copie de chaque réservoir distinct dans `question_sets`, référencée par les tentatives et les évaluations. Le moteur restitue le même contenu sans le recopier sur disque pour chaque apprenant.
- Sauvegarde SQLite cohérente avant migration et verrou de migration : quatre ouvertures simultanées ne produisent qu’une sauvegarde.
- Compaction unique lors du passage au stockage mutualisé ; récupération incrémentale ensuite. Aucun compactage intégral à chaque navigation.
- Entretien automatique par lots de 500 tentatives au maximum, sans attente sur un verrou d’écriture. Le passage manuel du superadmin traite l’ensemble.
- Suppression des tentatives d’étapes, de comptes ou d’inscriptions supprimés ; conservation des inscriptions et parcours simplement archivés.
- Suppression des générations remplacées après changement de type ou autorisation de reprise. Pour un entraînement ou exercice contrôlé, conservation du dernier essai terminé et de l’essai en cours, nettoyage des précédents.
- Les observations de calibration restent utiles après suppression d’une étape : leur lien avec la tentative supprimée est détaché. Les identifiants d’un compte définitivement supprimé sont retirés des observations. Les compteurs descriptifs anonymes des questions restent conservés.
- Libération des réservoirs inutilisés et des références d’évaluation remplacées qui ne servent plus à aucune tentative.

Le schéma LMS passe à **40**, le schéma adaptatif à **6**. Les migrations s’appliqueront sur l’instance utilisée lors de l’installation et de l’ouverture du module ; les réductions ci-dessus ont été mesurées sur les copies d’audit. Il ne faut pas remplacer la base live par celle de `quiz-test/`.

## Exercices contrôlés et reprises individuelles

Le type **Exercice contrôlé** complète Consultation simple, Évaluation, Autoévaluation et Événement. Il actualise les statistiques adaptatives sans conserver de note ; sa correction apparaît à la fin. Pour les QCM classiques, seule une trace de réalisation est conservée dans ce mode. Consultation simple et Autoévaluation restent des entraînements sans calibration adaptative.

Quitter Évaluation exige une confirmation explicite avant de supprimer notes, validations, commentaires et résultats QCM de l’étape. Tout changement de type invalide les tentatives et brouillons ; modifier seulement une date, une pondération ou un accès ne les efface pas. Les statistiques acquises des questions restent conservées. La copie et l’import/export d’un parcours conservent le nouveau type, sans copier les tentatives.

Dans le suivi individuel, **Autoriser un nouvel essai** efface les résultats du quiz de cette activité pour cet apprenant seulement. Les autres apprenants et activités sont inchangés. Les réponses des anciens onglets et les doubles autorisations sont refusées. Les générations sont contrôlées côté serveur ; la suppression des données principales et l’autorisation de reprise sont atomiques. Une panne simulée pendant l’opération rétablit intégralement l’ancien état. Les tentatives adaptatives remplacées sont exclues immédiatement des bilans, puis purgées.

La règle d’une seule observation de calibration par apprenant et version de question s’applique aussi aux nouveaux essais et aux exercices contrôlés, afin de ne pas surpondérer les répétitions.

## Acquis et bilans

**Acquis** présente uniquement Évaluation et un bouton **Vue détaillée**. Le bilan interne, identifié par l’inscription, exige une connexion et reste accessible à l’apprenant concerné et aux enseignants autorisés, indépendamment du lien privé externe.

Les anciens boutons dépendaient de l’existence d’un lien créé lors d’un export détaillé, d’où les différences entre groupes. Le suivi affiche maintenant systématiquement **Bilan**, **Activer bilan / Désactiver bilan** et **Visites**, avec icônes et présentation adaptée au téléphone. L’activation concerne uniquement l’accès externe. Une désactivation invalide l’ancienne URL ; un nouvel export ne la réactive pas. La première création d’un QR externe lors d’un export est conservée, conformément au fonctionnement demandé précédemment.

Les notes exclues individuellement, ou rattachées à une étape exclue du référentiel de calcul, sont barrées dans le bilan HTML et les exports détaillés PDF/Excel. Zéro reste une note réelle et peut être barré ; une absence de note ne devient pas zéro. Le CSV conserve l’indicateur d’inclusion. Les moyennes de la vue Acquis et des bilans respectent les mêmes exclusions.

Les guides HTML et PDF sont actualisés : 9 pages pour les enseignants et 5 pour les apprenants, disponibles depuis le profil. Pagination et affichage vérifiés.

## Vérifications réalisées

- Migrations des deux snapshots sur copies, intégrité, conservation des identités et notes, comparaison des empreintes SHA-256 des originaux avant/après.
- Modèle adaptatif, compatibilité des anciens barèmes, calibration, stockage partagé, rétention, migration simultanée et nettoyage.
- 30 apprenants simultanés sur un réservoir de 300 questions : doubles envois sans double comptage, référence commune, environ 6,4 secondes sur cette machine. Ce test local n’est pas une mesure de charge du serveur live.
- Types d’activité, absence de note en exercice contrôlé, absence de calibration en consultation, reprises classiques/adaptatives, isolement des apprenants, annulation transactionnelle en cas de panne, copie/import du nouveau type.
- Navigateur et téléphone : réglages, confirmation obligatoire côté serveur, correction sans faux zéro, refus des anciens onglets, reprise individuelle, bilans internes et externes, révocation persistante, CSRF et contrôle des accès.
- Exports PDF/Excel : pagination, texte intégral, nombres, moyennes et deux notes barrées dont zéro. Guides PDF rendus et vérifiés visuellement.
- Régressions fonctionnelles et de performance : 183 contrôles généraux ; bibliothèque en quatre requêtes pour 200 contenus supplémentaires ; navigation ne répétant pas la maintenance et nettoyage différé sans attendre un écrivain SQLite.

Commandes principales :

```sh
php dev/tests/adaptive_qcm_storage.php dev/quiz-test
php dev/tests/adaptive_qcm_audit.php dev/quiz-test
php dev/tests/adaptive_qcm.php
php dev/tests/adaptive_qcm_model.php
php dev/tests/adaptive_qcm_concurrency.php
php dev/tests/quiz_modes.php
php dev/tests/runtime_performance.php
php dev/tests/housekeeping.php
php dev/tests/smoke.php
php dev/tests/progress_report.php
php dev/tests/incremental_updates.php
node dev/tests/adaptive_qcm_browser.mjs
node dev/tests/progress_export_browser.mjs
php dev/scripts/build_user_guides.php
```

Ces tests utilisent des données temporaires. La fiabilité technique vérifiée ici ne remplace pas la validation pédagogique d’une banque de questions ; le minimum de cinq questions et l’indication d’un historique limité restent applicables.
