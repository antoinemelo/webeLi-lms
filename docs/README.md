# Documentation liike

État documenté : 17 septembre 2026. La documentation décrit les sources de développement ; une instance conserve son ancien fonctionnement tant que le code correspondant n’y a pas été installé.

liike organise des contenus pédagogiques réutilisables dans des parcours propres à chaque cours. L’élève voit son travail, ses échéances et ses acquis ; l’enseignant construit les parcours et confirme les niveaux atteints.

## Choisir son entrée

- **Utiliser la démonstration** : [guide utilisateur](guide-utilisateur.md)
- **Comprendre les concepts et calculs** : [modèle fonctionnel](modele-fonctionnel.md)
- **Modifier le code ou la base** : [architecture](architecture.md)
- **Démarrer, sauvegarder ou envoyer les emails** : [exploitation](exploitation.md)
- **Échanger en privé, gérer les discussions et activer les notifications PWA** : [discussions](discussions.md)
- **Évaluer ce qui manque avant production** : [limites et feuille de route](limitations-roadmap.md)

## Périmètre actuel

L’application couvre la bibliothèque de pages, six types de blocs, les tags, les QCM avec reprise des brouillons, les remises de liens et de textes courts, les parcours, les échéances, les évaluations, les objectifs, les compétences, la double validation 0–3 et les rewards cumulés. Elle propose aussi les courriels/annonces ciblés avec modèles, le suivi administratif exportable, les discussions privées et les notifications PWA.

Le projet ne fournit et ne requiert aucun répertoire `/server` ou `/serveur`. Le guide d’exploitation documente le démarrage PHP autonome, les deux formes d’arborescence et les emplacements possibles des dépendances Composer.

Il fournit une connexion enseignante par mot de passe, des codes élèves, une reconnexion PWA de 90 jours, des contrôles de rôle et une protection CSRF. Le socle et la messagerie ont des bases SQLite et des migrations distinctes. La maintenance vérifie les publications complètes et, avec le nouvel outil, sauvegarde et remplace seulement les fichiers concernés. Les limites restantes sont détaillées dans la documentation dédiée.

## Fonctionnalités ajoutées récemment — septembre 2026

- [Regroupements d’étapes](guide-utilisateur.md#regrouper-les-étapes) : titres, chevrons et glisser-déposer, visibles dans l’éditeur et le parcours élève.

- [Enregistrement des contenus](guide-utilisateur.md#gérer-les-contenus) : sauvegarde silencieuse et lien explicite pour prévenir les élèves après les corrections.
- [Exports de progression](guide-utilisateur.md#exporter-la-progression-des-élèves) : sélection des élèves, CSV/PDF récapitulatif ou détaillé, notes individuelles, dernier résultat QCM et saut de page avant chaque élève.
- [Gestion depuis le suivi individuel](guide-utilisateur.md#exporter-la-progression-des-élèves) : accès à la fiche de gestion à droite des points.
- [Types d’étapes et événements](guide-utilisateur.md#types-détapes-et-événements) : choix exclusif, dates et horaires, fichier `.ics` et ajout à Google Calendar.
- [Aperçu enseignant](guide-utilisateur.md#aperçu-élève-pour-lenseignant) : étapes invisibles masquées et navigation filtrée.

Les règles de conservation et d’accès figurent dans le [modèle fonctionnel](modele-fonctionnel.md) ; la migration 26 et les commandes de vérification sont décrites dans [l’exploitation](exploitation.md).
