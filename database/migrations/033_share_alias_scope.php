<?php
declare(strict_types=1);

return ['version'=>33,'name'=>'Préserver le mode des anciens liens de partage','up'=>static function(PDO $pdo):void{
    $columns=$pdo->query('PRAGMA table_info(qcm_guest_sessions)')->fetchAll(PDO::FETCH_ASSOC);
    if(!in_array('source_share_token',array_column($columns,'name'),true)){
        $pdo->exec("ALTER TABLE qcm_guest_sessions ADD COLUMN source_share_token TEXT NOT NULL DEFAULT ''");
        $pdo->exec("UPDATE qcm_guest_sessions SET source_share_token=COALESCE((SELECT token FROM page_share_links WHERE page_id=qcm_guest_sessions.page_id),'')");
    }
    // A former external link may already be held by pupils. It must not become
    // the new multi-mode link that can expose editable sources and corrections.
    $pdo->exec("UPDATE page_share_links SET token=lower(hex(randomblob(32))),revision=revision+1
        WHERE EXISTS(SELECT 1 FROM page_shares legacy WHERE legacy.token=page_share_links.token)");
}];
