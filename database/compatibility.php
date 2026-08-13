<?php

declare(strict_types=1);

return static function(PDO $pdo): void {
    $required=[
        'schema_migrations'=>['version','name','checksum','applied_at'],
        'users'=>['id','first_name','last_name','email','role','login_code','account_status','is_superadmin','language','managed_by'],
        'courses'=>['id','reference','title','code','teacher_id','archived'],
        'course_teachers'=>['course_id','teacher_id','added_by'],
        'enrollments'=>['id','course_id','student_id','status','archived_at'],
        'course_accesses'=>['user_id','course_id','last_accessed_at'],
        'pages'=>['id','reference','title','status','owner_id','revision'],
        'page_blocks'=>['id','page_id','type','body','position','revision','updated_by','updated_at'],
        'page_objectives'=>['id','page_id','title','position'],
        'pathway_items'=>['id','course_id','page_id','position','access_mode','revision'],
        'pathway_item_students'=>['pathway_item_id','student_id'],
        'progress'=>['id','enrollment_id','pathway_item_id','student_level','teacher_level'],
        'course_skills'=>['id','course_id','code','title'],
        'item_skills'=>['pathway_item_id','skill_id'],
        'reward_types'=>['id','course_id','name','default_points','active'],
        'reward_awards'=>['id','enrollment_id','pathway_item_id','points'],
        'learning_visits'=>['id','student_id','pathway_item_id','visit_token','duration_seconds'],
        'edit_locks'=>['entity_type','entity_id','teacher_id','owner_token','expires_at'],
        'collaboration_comments'=>['id','subject_type','subject_id','author_id','status'],
        'notification_outbox'=>['id','event','recipient','status'],
        'tags'=>['id','name','color'],
        'page_tags'=>['page_id','tag_id'],
    ];
    foreach($required as $table=>$columns){
        $exists=(bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name=".$pdo->quote($table))->fetchColumn();
        if(!$exists)throw new RuntimeException('Table requise absente : '.$table);
        $available=array_column($pdo->query('PRAGMA table_info('.$pdo->quote($table).')')->fetchAll(PDO::FETCH_ASSOC),'name');
        $missing=array_diff($columns,$available);
        if($missing)throw new RuntimeException('Colonne(s) requise(s) absente(s) dans '.$table.' : '.implode(', ',$missing));
    }
    $version=(int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if($version<1)throw new RuntimeException('La base ne possède aucune version de migration valide.');
};
