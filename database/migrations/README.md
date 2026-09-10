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

Le socle courant est en version 24. La messagerie possède une chaîne indépendante sous `database/messaging/migrations/`, actuellement en version 1, appliquée à `storage/messaging.sqlite`. Une évolution de cette chaîne augmente la version déclarée dans `app/Messaging/Database.php` et `scripts/apr.py` (`messaging_database_version` du manifeste), sans imposer une nouvelle migration du socle.

La migration historique 24 comporte une passerelle d’installation de la base de messagerie pour les anciens outils de maintenance. Cette exception déjà publiée ne doit pas être modifiée ni servir de modèle pour les migrations ordinaires. La Maintenance actuelle planifie les deux chaînes et sauvegarde la messagerie avant sa migration ; un échec ultérieur du socle restaure cette sauvegarde. L’optimisation des copies de code ne change aucune version de base. Voir [discussions et mises à jour](../../docs/discussions.md#données-mises-à-jour-et-limites).
