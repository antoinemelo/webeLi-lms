# Migrations de la base

Chaque évolution postérieure au socle historique version 6 reçoit un fichier immuable nommé `NNN_description.php` et augmente `Database::SCHEMA_VERSION` ainsi que `PRAGMA user_version` dans `database/schema.sql`. Le schéma courant et les fichiers seed doivent également représenter directement le résultat final pour les nouvelles installations.

Le fichier retourne un tableau autonome :

```php
<?php
return [
    'version' => 7,
    'name' => 'Description courte',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("INSERT OR IGNORE INTO tags(name,color) VALUES('Nouvelle catégorie','#ffffff')");
    },
];
```

La fonction doit être compatible avec une transaction SQLite : pas de `COMMIT`, `VACUUM`, changement de `foreign_keys` ou opération externe. Elle cible uniquement les données système nécessaires et ne remplace jamais les contenus opérationnels d’une instance.

Le socle courant est en version 37. La migration 30 ajoute la visibilité indépendante des regroupements (`pathway_groups.is_visible`), visible par défaut, sans modifier les réglages des étapes ni leurs résultats. La migration 29 ajoute les séances QCM collectives et leurs votes (`qcm_class_sessions`, `qcm_class_votes`) sans toucher aux QCM individuels. La migration 28 ajoute la prise en compte individuelle des évaluations dans les moyennes (`progress.evaluation_included`, activée par défaut), sans effacer les résultats existants. La messagerie possède une chaîne indépendante sous `database/messaging/migrations/`, actuellement en version 1, appliquée à `storage/messaging.sqlite`. Une évolution de cette chaîne augmente la version déclarée dans `app/Messaging/Database.php` et `scripts/apr.py` (`messaging_database_version` du manifeste), sans imposer une nouvelle migration du socle.

La migration historique 24 comporte une passerelle d’installation de la base de messagerie pour les anciens outils de maintenance. Cette exception déjà publiée ne doit pas être modifiée ni servir de modèle pour les migrations ordinaires. La Maintenance actuelle planifie les deux chaînes et sauvegarde la messagerie avant sa migration ; un échec ultérieur du socle restaure cette sauvegarde. L’optimisation des copies de code ne change aucune version de base. Voir [discussions et mises à jour](../../docs/discussions.md#données-mises-à-jour-et-limites).

La migration 31 ajoute les liens de partage de pages, les collaborateurs de page et les reçus de copie. Elle ne crée aucun lien actif automatiquement et ne modifie pas les contenus, parcours ou résultats existants.

La migration 32 ajoute le lien unique et ses modes autorisés, ainsi que les séances QCM anonymes et leurs votes temporaires. Les copies et droits déjà récupérés sont préservés. Aucune donnée pédagogique existante n’est modifiée. La migration est idempotente.

La migration 33 distingue le jeton du lien unique des anciens liens par mode. Ces derniers restent utilisables dans leur seul mode d’origine, si le propriétaire l’autorise toujours : un ancien lien externe transmis aux apprenants ne donne ainsi aucun accès aux sources ou à l’édition.

La migration 34 conserve l’origine des copies indépendantes dans `page_copy_origins`, avec reprise des reçus existants et préservation du nom source en cas de suppression. Elle n’altère aucune page, copie, inscription ou note.

La migration 35 permet de créer une séance QCM externe sans compte. `created_by` devient facultatif ; `host_key_hash` identifie la session anonyme autorisée à piloter. Les séances déjà créées gardent leur propriétaire connecté. La reconstruction transactionnelle conserve les participants, votes et séquences existants.

La migration 36 remplace uniquement le déclencheur de limitation des comptes publics en attente : 50 comptes sont autorisés, avec exclusion des comptes gérés par un enseignant. Aucun compte ni résultat existant n’est modifié. Les quotas réseau (30 demandes sur 15 minutes) et horaire (50) sont définis dans `RegistrationPolicy.php`.

La migration 37 ajoute uniquement la table `registration_limit_alerts` pour éviter les alertes administratives répétées lorsqu’un plafond d’inscription est atteint. Aucun courriel n’est créé par cette migration.
