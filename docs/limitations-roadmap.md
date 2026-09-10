# Limites connues et feuille de route

État revu le 10 septembre 2026. Les fonctions disponibles et leurs contraintes sont décrites ci-dessous ; la feuille de route ne doit pas être confondue avec les fonctions déjà livrées.

## Limites assumées

### Identité et sécurité

- l’enseignant dispose d’un mot de passe hashé, de sessions, d’une protection CSRF et d’une récupération par lien temporaire, mais il n’existe pas encore d’authentification multifacteur ;
- les codes élèves sans mot de passe sont volontairement simples et prévisibles : ils ne conviennent pas à des données sensibles ;
- l’application ne propose pas encore de journal complet des connexions ni de protection anti-bruteforce dédiée à toutes les formes de connexion ;
- les inscriptions publiques sont plafonnées et expirent après validation courriel, mais un déploiement exposé devrait encore ajouter un CAPTCHA géré et une limitation au niveau du serveur ou du proxy ;
- les imports de blocs contrôlent la taille, les extensions des documents et le format réel des images ; aucune analyse antivirus n’est intégrée. Les iframes sont reconstruites à partir d’une URL HTTP(S), mais il n’existe pas de liste de domaines autorisés configurable ni de sandbox iframe ;
- les noms et adresses ne sont pas chiffrés dans SQLite ; les droits applicatifs et la protection des fichiers restent nécessaires.

### Administration

- les comptes, élèves, inscriptions et parcours se gèrent dans l’interface, mais certains référentiels globaux et opérations administratives restent partiels ;
- les compétences peuvent être modifiées et retirées ; les types d’encouragements peuvent être modifiés et désactivés sans effacer les attributions passées ;
- l’éditeur conserve les blocs inchangés et contrôle les révisions concurrentes, mais ne propose pas d’historique complet des versions de contenu ;
- les formats JSON sont versionnés mais ne disposent pas encore de signature cryptographique ; n’importer que des fichiers provenant d’une source de confiance ;

### Évaluation

- les synthèses pondèrent les activités confirmées à `0,5` et les évaluations selon leur pondération configurée ; cette règle reste une convention interne, pas une grille institutionnelle paramétrable ;
- aucun niveau cible n’est défini par compétence ou par objectif ;
- les élèves peuvent remettre un lien ou un texte de 512 caractères, avec brouillon et nouvelle remise autorisée ; il n’existe pas de dépôt de fichier élève, de grille critériée ou de commentaire par compétence. Le contenu d’un document externe peut changer après remise ;
- une confirmation répétée avec reward peut attribuer plusieurs rewards, ce qui est autorisé mais devrait être rendu plus explicite.

### Notifications et exploitation

- les courriels disposent de modèles personnels avec variables, mais le transport reste `mail()` ; son acceptation ne garantit pas la livraison au destinataire ;
- le worker et le traitement de secours réessaient les courriels en échec après cinq minutes. Les corps techniques envoyés sont effacés, leurs métadonnées sont conservées ;
- les mises à jour vérifient l’archive complète. Le nouvel outil réduit les copies et sauvegardes de code, sans réduire le téléchargement ni la sauvegarde systématique de la base principale ;
- un échec géré de mise à jour déclenche le retour arrière ; la restauration volontaire d’une sauvegarde reste une opération d’exploitation, sans assistant dédié ;
- les discussions ont une base séparée sans purge automatique des fils. Les sauvegardes métier du socle ne les restaurent pas ; les sauvegardes techniques de migration peuvent conserver des messages ensuite effacés ;
- les messages privés sont limités à 256 caractères, modifiables trois minutes, sans pièces jointes ni conservation des versions précédentes. Les exports de fils ne constituent pas une preuve cryptographique certifiée ;
- les notifications push dépendent des autorisations et du système de l’appareil ; un essai réel sur iOS/Android reste nécessaire en complément des simulations Chromium ;
- le cache PWA est limité aux assets publics. Les pages authentifiées ne fonctionnent pas hors ligne ; seuls les brouillons QCM et travaux disposent d’une copie locale de reprise.

## Ordre de réalisation conseillé

1. Ajouter l’authentification multifacteur, un journal des connexions et une limitation homogène des tentatives.
2. Ajouter une commande assistée de restauration d’une sauvegarde de migration.
3. Compléter les opérations encore absentes sur les référentiels globaux.
4. Ajouter une analyse des fichiers importés et une politique configurable d’iframes autorisées.
5. Envisager des preuves figées de réalisation et des commentaires par compétence.
6. Rendre la règle d’acquisition configurable : moyenne, dernier niveau ou seuil de situations réussies.
7. Ajouter un historique des contenus et une gestion explicite des versions publiées.
8. Envisager un transport SMTP configurable et un suivi des retours de livraison.
9. Compléter les tests PHP et Chromium existants par des essais sur téléphones réels et des mesures de charge des discussions et grands exports.
