<?php
declare(strict_types=1);

return ['version'=>38,'name'=>'Liens privés des bilans de progression','up'=>static function(PDO $pdo):void{
    $pdo->exec('CREATE TABLE IF NOT EXISTS progress_report_links (
        enrollment_id INTEGER PRIMARY KEY REFERENCES enrollments(id) ON DELETE CASCADE,
        token TEXT NOT NULL UNIQUE CHECK(length(token)=64),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
}];
