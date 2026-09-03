<?php

declare(strict_types=1);

return [
    'version'=>16,
    'name'=>'Étapes ajoutées et première connexion élève',
    'up'=>static function(PDO $pdo): void {
        $itemColumns=array_column($pdo->query('PRAGMA table_info(pathway_items)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('created_at',$itemColumns,true))$pdo->exec('ALTER TABLE pathway_items ADD COLUMN created_at TEXT');
        $pdo->exec("UPDATE pathway_items SET created_at=COALESCE(created_at,updated_at,strftime('%Y-%m-%d %H:%M:%f','now'))");
        $pdo->exec('DROP TRIGGER IF EXISTS touch_pathway_item_after_insert');
        $pdo->exec("CREATE TRIGGER touch_pathway_item_after_insert AFTER INSERT ON pathway_items WHEN NEW.created_at IS NULL OR NEW.updated_at IS NULL BEGIN UPDATE pathway_items SET created_at=COALESCE(created_at,strftime('%Y-%m-%d %H:%M:%f','now')),updated_at=COALESCE(updated_at,strftime('%Y-%m-%d %H:%M:%f','now')) WHERE id=NEW.id; END");

        $userColumns=array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC),'name');
        if(!in_array('student_first_login_at',$userColumns,true))$pdo->exec('ALTER TABLE users ADD COLUMN student_first_login_at TEXT');
        $pdo->exec("UPDATE users SET student_first_login_at=strftime('%Y-%m-%d %H:%M:%f','now') WHERE role='student' AND student_first_login_at IS NULL AND (
            EXISTS(SELECT 1 FROM course_accesses ca WHERE ca.user_id=users.id)
            OR EXISTS(SELECT 1 FROM learning_visits lv WHERE lv.student_id=users.id)
            OR EXISTS(SELECT 1 FROM qcm_attempts qa WHERE qa.student_id=users.id)
            OR EXISTS(SELECT 1 FROM announcement_reads ar WHERE ar.student_id=users.id)
            OR EXISTS(SELECT 1 FROM enrollments e JOIN progress pr ON pr.enrollment_id=e.id WHERE e.student_id=users.id AND pr.student_validated_at IS NOT NULL)
        )");
    },
];
