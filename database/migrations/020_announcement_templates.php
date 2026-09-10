<?php

declare(strict_types=1);

return [
    'version'=>20,
    'name'=>'Modèles de messages, destinataires et copies courriel',
    'up'=>static function(PDO $pdo): void {
        foreach([
            'course_announcements'=>['audience'=>"TEXT NOT NULL DEFAULT 'all' CHECK(audience IN ('all','class','selected'))",'request_key'=>'TEXT','request_hash'=>'TEXT'],
            'notification_outbox'=>['cc'=>"TEXT NOT NULL DEFAULT ''",'bcc'=>"TEXT NOT NULL DEFAULT ''"],
        ] as $table=>$fields){
            $columns=array_column($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC),'name');
            foreach($fields as $name=>$definition)if(!in_array($name,$columns,true))$pdo->exec('ALTER TABLE '.$table.' ADD COLUMN '.$name.' '.$definition);
        }
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_announcement_request ON course_announcements(created_by,request_key);
            CREATE TABLE IF NOT EXISTS message_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                teacher_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                revision INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_message_templates_teacher ON message_templates(teacher_id,name);
            CREATE TABLE IF NOT EXISTS announcement_recipients (
                announcement_id INTEGER NOT NULL REFERENCES course_announcements(id) ON DELETE CASCADE,
                student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                student_name TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                email TEXT NOT NULL,
                cc TEXT NOT NULL DEFAULT '',
                PRIMARY KEY(announcement_id,student_id)
            );
            CREATE INDEX IF NOT EXISTS idx_announcement_recipients_student ON announcement_recipients(student_id,announcement_id);");
    },
];
