# Limites connues et feuille de route

Le prototype répond au besoin de démonstration, mais les points suivants doivent être traités avant un usage réel avec des élèves.

## Limites assumées

### Identité et sécurité

- l’enseignant dispose d’un mot de passe hashé, de sessions, d’une protection CSRF et d’une récupération par lien temporaire, mais il n’existe pas encore d’authentification multifacteur ;
- les codes élèves sans mot de passe sont volontairement simples et prévisibles : ils ne conviennent pas à des données sensibles ;
- l’application ne propose pas encore de journal complet des connexions ni de protection anti-bruteforce dédiée à toutes les formes de connexion ;
- les inscriptions publiques sont plafonnées et expirent après validation courriel, mais un déploiement exposé devrait encore ajouter un CAPTCHA géré et une limitation au niveau du serveur ou du proxy ;
- les iframes et uploads ne sont pas filtrés comme ils devraient l’être en production.

### Administration

- les comptes, élèves, inscriptions et parcours se gèrent dans l’interface, mais certains référentiels globaux et opérations administratives restent partiels ;
- l’interface permet d’ajouter objectifs, compétences et types de rewards, mais pas encore de les modifier, désactiver ou supprimer ;
- l’éditeur réécrit tous les blocs de la page à chaque enregistrement et ne conserve pas d’historique de versions.
- les formats JSON sont versionnés mais ne disposent pas encore de signature cryptographique ; n’importer que des fichiers provenant d’une source de confiance ;

### Évaluation

- les synthèses utilisent une moyenne arithmétique non pondérée ;
- aucun niveau cible n’est défini par compétence ou par objectif ;
- il n’existe pas de preuve jointe, dépôt élève, grille critériée ou commentaire par compétence ;
- une confirmation répétée avec reward peut attribuer plusieurs rewards, ce qui est autorisé mais devrait être rendu plus explicite.

### Notifications et exploitation

- les messages sont textuels et sans gabarit HTML ;
- les échecs ne disposent ni de relance automatique, ni de journal d’exécution ;
- les migrations SQLite sont incrémentales et sauvegardées automatiquement ; les restaurations restent une opération administrateur explicite ;
- le cache PWA est volontairement limité aux assets publics ; les pages authentifiées ne fonctionnent pas hors ligne afin de ne pas exposer une ancienne session.

## Ordre de réalisation conseillé

1. Ajouter l’authentification multifacteur, un journal des connexions et une limitation homogène des tentatives.
2. Ajouter une commande assistée de restauration d’une sauvegarde de migration.
3. Compléter le CRUD des groupes, catégories et éléments de référentiel.
4. Sécuriser les uploads et définir une politique d’iframes autorisées.
5. Introduire des preuves de réalisation et des commentaires par compétence.
6. Rendre la règle d’acquisition configurable : moyenne, dernier niveau ou seuil de situations réussies.
7. Ajouter historique des contenus, brouillon/publication et notification ciblée.
8. Brancher un transport SMTP fiable et un worker de relance.
9. Ajouter des tests HTTP et des scénarios navigateurs mobiles automatisés.
