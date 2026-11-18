<?php
declare(strict_types=1);

return ['version'=>34,'name'=>'Origine des copies indépendantes de bibliothèque','up'=>static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_copy_origins (
        page_id INTEGER PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE,
        source_page_id INTEGER REFERENCES pages(id) ON DELETE SET NULL,
        source_owner_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
        source_owner_name TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    INSERT OR IGNORE INTO page_copy_origins(page_id,source_page_id,source_owner_id,source_owner_name)
        SELECT r.page_id,p.id,u.id,u.name FROM page_share_receipts r
        JOIN page_shares s ON s.id=r.share_id AND s.mode='copy'
        JOIN pages p ON p.id=s.page_id JOIN users u ON u.id=p.owner_id
        WHERE r.page_id IS NOT NULL AND r.page_id<>p.id;");
}];
