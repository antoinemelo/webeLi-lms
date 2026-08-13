# Migrations de la base

Chaque évolution postérieure à la version 6 reçoit un fichier immuable nommé `NNN_description.php` et augmente `Database::SCHEMA_VERSION` ainsi que `PRAGMA user_version` dans `database/schema.sql`. Le schéma courant et les fichiers seed doivent également représenter directement le résultat final pour les nouvelles installations.

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
