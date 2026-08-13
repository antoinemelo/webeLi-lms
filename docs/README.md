# Documentation liike

État documenté : 13 août 2026.

liike organise des contenus pédagogiques réutilisables dans des parcours propres à chaque cours. L’élève voit son travail, ses échéances et ses acquis ; l’enseignant construit les parcours et confirme les niveaux atteints.

## Choisir son entrée

- **Utiliser la démonstration** : [guide utilisateur](guide-utilisateur.md)
- **Comprendre les concepts et calculs** : [modèle fonctionnel](modele-fonctionnel.md)
- **Modifier le code ou la base** : [architecture](architecture.md)
- **Démarrer, sauvegarder ou envoyer les emails** : [exploitation](exploitation.md)
- **Évaluer ce qui manque avant production** : [limites et feuille de route](limitations-roadmap.md)

## Périmètre actuel

Le prototype couvre la bibliothèque de pages, les blocs, les tags, les parcours par cours, les échéances, les évaluations, les objectifs, les compétences, la double validation 0–3, les rewards cumulés, les notifications différées et une interface mobile/PWA.

Le projet ne fournit et ne requiert aucun répertoire `/server` ou `/serveur`. Le guide d’exploitation documente le démarrage PHP autonome, les deux formes d’arborescence et les emplacements possibles des dépendances Composer.

Il fournit désormais une connexion enseignante par mot de passe, des codes élèves, des sessions, des contrôles de rôle, une protection CSRF et des migrations SQLite incrémentales avec sauvegarde préalable. Les limites restantes sont détaillées dans la documentation dédiée.
