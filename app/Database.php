<?php

declare(strict_types=1);

require_once __DIR__.'/DatabaseMigrations.php';

final class Database
{
    public const SCHEMA_VERSION = 9;

    public static function connect(string $root): PDO
    {
        $storage = $root . '/storage';
        if (!is_dir($storage)) {
            mkdir($storage, 0775, true);
        }
        $path = $storage . '/apr.sqlite';
        $fresh = !is_file($path);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        if ($fresh) {
            $pdo->exec((string) file_get_contents($root . '/database/schema.sql'));
            $pdo->exec((string) file_get_contents($root . '/database/seed.sql'));
        }
        self::runMigrations($pdo,$path,!$fresh);
        return $pdo;
    }

    public static function migrateFile(string $path, bool $backup=true): array
    {
        $path=(string)(realpath($path)?:$path);
        if(!is_file($path))throw new RuntimeException('Base SQLite introuvable : '.$path);
        $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys = ON');$pdo->exec('PRAGMA busy_timeout = 5000');
        return self::runMigrations($pdo,$path,$backup);
    }

    public static function migrationStatus(PDO $pdo): array
    {
        $migrations=self::migrations();$applied=[];
        $hasTable=(bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='schema_migrations'")->fetchColumn();
        if($hasTable)$applied=array_map('intval',$pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN));
        $userVersion=(int)$pdo->query('PRAGMA user_version')->fetchColumn();
        if(!$applied&&$userVersion>=self::SCHEMA_VERSION)$applied=array_keys($migrations);
        $pending=array_values(array_diff(array_keys($migrations),$applied));
        $current=0;foreach(array_keys($migrations) as $version){if(!in_array($version,$applied,true))break;$current=$version;}
        return ['current'=>$current,'latest'=>self::SCHEMA_VERSION,'applied'=>$applied,'pending'=>$pending,'up_to_date'=>!$pending];
    }

    private static function migrations(): array
    {
        $migrations=[
            1=>['name'=>'Comptes, inscriptions et références stables','method'=>'migrateUsers'],
            2=>['name'=>'Historique des consultations','method'=>'migrateLearningActivity'],
            3=>['name'=>'Collaboration, restrictions et verrous par session','method'=>'migrateCollaboration'],
            4=>['name'=>'Dernier accès aux parcours','method'=>'migrateCourseAccesses'],
            5=>['name'=>'Objectifs propres aux pages','method'=>'migratePageObjectives'],
            6=>['name'=>'Catégories de contenus historiques','method'=>'migrateDefaultTags'],
        ];
        foreach(database_packaged_migrations(dirname(__DIR__).'/database/migrations') as $version=>$migration){
            if(isset($migrations[$version]))throw new RuntimeException('La migration v'.$version.' existe déjà dans le socle historique.');
            $migrations[$version]=$migration;
        }
        ksort($migrations);$latest=(int)array_key_last($migrations);
        if($latest!==self::SCHEMA_VERSION)throw new RuntimeException('La version de schéma déclarée (v'.self::SCHEMA_VERSION.') ne correspond pas à la dernière migration (v'.$latest.').');
        return $migrations;
    }

    private static function runMigrations(PDO $pdo, string $path, bool $backup): array
    {
        $lock=fopen($path.'.migration.lock','c+');
        if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Impossible de verrouiller la migration de la base.');
        try{
            $status=self::migrationStatus($pdo);$migrations=self::migrations();
            if(!$status['pending']){
                self::recordCurrentSchema($pdo,$migrations);
                database_compatibility_contract(dirname(__DIR__).'/database/compatibility.php')($pdo);
                return ['applied'=>[],'backup'=>null,'status'=>self::migrationStatus($pdo)];
            }
            $backupPath=null;
            if($backup){
                $directory=dirname($path).'/backups';
                if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('Le dossier de sauvegarde de la base ne peut pas être créé.');
                $backupPath=$directory.'/'.pathinfo($path,PATHINFO_FILENAME).'-avant-schema-v'.self::SCHEMA_VERSION.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';
                $pdo->exec('VACUUM INTO '.$pdo->quote($backupPath));
                if(!is_file($backupPath)||filesize($backupPath)===0)throw new RuntimeException('La sauvegarde préalable de la base a échoué.');
            }
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY,name TEXT NOT NULL,checksum TEXT NOT NULL,applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
            $applied=[];
            foreach($status['pending'] as $version){
                $migration=$migrations[$version];$external=isset($migration['up']);
                try{
                    if($external)$pdo->beginTransaction();
                    if($external)($migration['up'])($pdo);else{$method=$migration['method'];self::$method($pdo);}
                    $checksum=$migration['checksum']??hash('sha256',$version.':'.$migration['name']);
                    $statement=$pdo->prepare('INSERT OR REPLACE INTO schema_migrations(version,name,checksum,applied_at) VALUES(?,?,?,CURRENT_TIMESTAMP)');
                    $statement->execute([$version,$migration['name'],$checksum]);
                    if($external)$pdo->commit();
                }catch(Throwable $exception){if($external&&$pdo->inTransaction())$pdo->rollBack();throw new RuntimeException('Migration '.$version.' (« '.$migration['name'].' ») impossible : '.$exception->getMessage(),0,$exception);}
                $applied[]=$version;
            }
            $pdo->exec('PRAGMA user_version = '.self::SCHEMA_VERSION);
            if($pdo->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException('Le contrôle d’intégrité SQLite a échoué après migration.');
            $foreignKeys=$pdo->query('PRAGMA foreign_key_check')->fetchAll();
            if($foreignKeys)throw new RuntimeException('Des relations de la base sont invalides après migration.');
            database_compatibility_contract(dirname(__DIR__).'/database/compatibility.php')($pdo);
            return ['applied'=>$applied,'backup'=>$backupPath,'status'=>self::migrationStatus($pdo)];
        }finally{
            flock($lock,LOCK_UN);fclose($lock);
        }
    }

    private static function recordCurrentSchema(PDO $pdo, array $migrations): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY,name TEXT NOT NULL,checksum TEXT NOT NULL,applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $count=(int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        $userVersion=(int)$pdo->query('PRAGMA user_version')->fetchColumn();
        if($count===0&&$userVersion>=self::SCHEMA_VERSION){
            $statement=$pdo->prepare('INSERT INTO schema_migrations(version,name,checksum) VALUES(?,?,?)');
            foreach($migrations as $version=>$migration)$statement->execute([$version,$migration['name'],$migration['checksum']??hash('sha256',$version.':'.$migration['name'])]);
        }
        $pdo->exec('PRAGMA user_version = '.self::SCHEMA_VERSION);
    }

    private static function migrateUsers(PDO $pdo): void
    {
        $usersSql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        if (preg_match('/length\s*\(\s*login_code\s*\)\s*=\s*5/i', $usersSql)) {
            self::allowSuffixedStudentCodes($pdo);
        }
        $columns = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        $additions = [
            'first_name' => "TEXT NOT NULL DEFAULT ''",
            'last_name' => "TEXT NOT NULL DEFAULT ''",
            'class_group' => "TEXT NOT NULL DEFAULT ''",
            'phone' => 'TEXT',
            'login_code' => 'TEXT',
            'password_hash' => 'TEXT',
            'account_status' => "TEXT NOT NULL DEFAULT 'active' CHECK(account_status IN ('pending','active','archived'))",
            'email_verified_at' => 'TEXT',
            'verification_token_hash' => 'TEXT',
            'verification_expires_at' => 'TEXT',
            'password_reset_token_hash' => 'TEXT',
            'password_reset_expires_at' => 'TEXT',
            'pending_course_title' => 'TEXT',
            'is_superadmin' => "INTEGER NOT NULL DEFAULT 0 CHECK(is_superadmin IN (0,1))",
            'language' => "TEXT CHECK(language IS NULL OR language IN ('fr','en','de','it','es'))",
            'student_session_token_hash' => 'TEXT',
            'student_session_seen_at' => 'INTEGER',
            'created_at' => 'TEXT',
            'managed_by' => 'INTEGER REFERENCES users(id)',
        ];
        foreach ($additions as $name => $definition) {
            if (!in_array($name, $columns, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN $name $definition");
            }
        }

        $users = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
        $update = $pdo->prepare('UPDATE users SET name=?,first_name=?,last_name=?,initials=?,class_group=?,login_code=?,password_hash=? WHERE id=?');
        foreach ($users as $user) {
            $parts = preg_split('/\s+/u', trim((string) $user['name']), 2) ?: [];
            $firstName = trim((string) ($user['first_name'] ?: ($parts[0] ?? '')));
            $lastName = mb_strtoupper(trim((string) ($user['last_name'] ?: ($parts[1] ?? ''))), 'UTF-8');
            $code = (string) ($user['login_code'] ?? '');
            if ($code === '') {
                $code = $user['role'] === 'teacher' ? 'nora' : self::studentCode($firstName, $lastName);
            }
            $password = $user['password_hash'] ?? null;
            if ($user['role'] === 'teacher' && !$password) {
                $password = '$2y$10$NcHQLH/Xqq0OHFiYOk7WQejWexGMsUbhlZNDP/fgn.i6iSSzbnF8m';
            }
            $initials = mb_strtoupper(mb_substr($firstName, 0, 1) . mb_substr(preg_replace('/\s+/u', '', $lastName) ?? '', 0, 1), 'UTF-8');
            $group = (string) ($user['class_group'] ?? '');
            if ($user['role'] === 'student' && $group === '') {
                $group = (int) $user['id'] === 4 ? '10B' : '10A';
            }
            $update->execute([$firstName . ' ' . $lastName, $firstName, $lastName, $initials, $group, $code, $password, $user['id']]);
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_login_code ON users(login_code)');
        $pdo->exec("UPDATE users SET account_status='active',created_at=COALESCE(created_at,CURRENT_TIMESTAMP) WHERE account_status IS NULL OR created_at IS NULL");
        $pdo->exec("UPDATE users SET managed_by=(SELECT c.teacher_id FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.student_id=users.id ORDER BY e.id LIMIT 1) WHERE role='student' AND managed_by IS NULL");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_managed_by ON users(managed_by,role,account_status)');
        self::migrateEnrollments($pdo);
        self::migrateCourses($pdo);
        self::migratePages($pdo);
        $pdo->exec('CREATE TABLE IF NOT EXISTS registration_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT,ip_hash TEXT NOT NULL,accepted INTEGER NOT NULL DEFAULT 0 CHECK(accepted IN (0,1)),attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_registration_attempts_ip ON registration_attempts(ip_hash,attempted_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_verification_expiry ON users(account_status,verification_expires_at)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT,ip_hash TEXT NOT NULL,email_hash TEXT NOT NULL DEFAULT '',requested_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $resetColumns=array_column($pdo->query('PRAGMA table_info(password_reset_attempts)')->fetchAll(),'name');
        if(!in_array('email_hash',$resetColumns,true))$pdo->exec("ALTER TABLE password_reset_attempts ADD COLUMN email_hash TEXT NOT NULL DEFAULT ''");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_attempts_ip ON password_reset_attempts(ip_hash,requested_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_password_reset_attempts_email ON password_reset_attempts(email_hash,requested_at)');
        $pdo->exec("CREATE TRIGGER IF NOT EXISTS limit_pending_registrations BEFORE INSERT ON users WHEN NEW.account_status='pending' AND (SELECT COUNT(*) FROM users WHERE account_status='pending')>=10 BEGIN SELECT RAISE(ABORT, 'pending registration limit reached'); END");
        if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_superadmin=1")->fetchColumn() === 0) {
            $pdo->exec("UPDATE users SET is_superadmin=1 WHERE id=(SELECT id FROM users WHERE role='teacher' ORDER BY id LIMIT 1)");
        }
    }

    private static function migrateEnrollments(PDO $pdo): void
    {
        $columns = array_column($pdo->query('PRAGMA table_info(enrollments)')->fetchAll(), 'name');
        if (!in_array('status', $columns, true)) {
            $pdo->exec("ALTER TABLE enrollments ADD COLUMN status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','archived'))");
        }
        if (!in_array('archived_at', $columns, true)) {
            $pdo->exec('ALTER TABLE enrollments ADD COLUMN archived_at TEXT');
        }
    }

    private static function migratePages(PDO $pdo): void
    {
        $columns = array_column($pdo->query('PRAGMA table_info(pages)')->fetchAll(), 'name');
        if (!in_array('owner_id', $columns, true)) {
            $pdo->exec('ALTER TABLE pages ADD COLUMN owner_id INTEGER REFERENCES users(id)');
        }
        if (!in_array('reference', $columns, true)) {
            $pdo->exec('ALTER TABLE pages ADD COLUMN reference TEXT');
        }
        $pdo->exec("UPDATE pages SET owner_id=COALESCE(updated_by,(SELECT c.teacher_id FROM pathway_items pi JOIN courses c ON c.id=pi.course_id WHERE pi.page_id=pages.id LIMIT 1),(SELECT id FROM users WHERE role='teacher' ORDER BY id LIMIT 1)) WHERE owner_id IS NULL");
        $pdo->exec("UPDATE pages SET reference='PAGE-'||id||'-'||lower(hex(randomblob(8))) WHERE reference IS NULL OR reference=''");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pages_owner ON pages(owner_id,status,updated_at)');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_pages_reference ON pages(reference)');
    }

    private static function migrateCourses(PDO $pdo): void
    {
        $columns = array_column($pdo->query('PRAGMA table_info(courses)')->fetchAll(), 'name');
        if (!in_array('reference', $columns, true)) {
            $pdo->exec('ALTER TABLE courses ADD COLUMN reference TEXT');
        }
        $pdo->exec("UPDATE courses SET reference='COURSE-'||id||'-'||lower(hex(randomblob(8))) WHERE reference IS NULL OR reference=''");
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_courses_reference ON courses(reference)');
    }

    private static function migrateLearningActivity(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS learning_visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            pathway_item_id INTEGER NOT NULL,
            visit_token TEXT NOT NULL,
            started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            duration_seconds INTEGER NOT NULL DEFAULT 0 CHECK(duration_seconds >= 0),
            UNIQUE(student_id,pathway_item_id,visit_token),
            FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_learning_visits_student_item ON learning_visits(student_id,pathway_item_id,last_seen_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_learning_visits_retention ON learning_visits(last_seen_at)');
    }

    private static function migrateCourseAccesses(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS course_accesses (user_id INTEGER NOT NULL,course_id INTEGER NOT NULL,last_accessed_at INTEGER NOT NULL,PRIMARY KEY(user_id,course_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_course_accesses_recent ON course_accesses(user_id,last_accessed_at DESC)');
    }

    private static function migrateCollaboration(PDO $pdo): void
    {
        $pageColumns=array_column($pdo->query('PRAGMA table_info(pages)')->fetchAll(),'name');
        if(!in_array('revision',$pageColumns,true))$pdo->exec('ALTER TABLE pages ADD COLUMN revision INTEGER NOT NULL DEFAULT 0');
        $blockColumns=array_column($pdo->query('PRAGMA table_info(page_blocks)')->fetchAll(),'name');
        if(!in_array('revision',$blockColumns,true))$pdo->exec('ALTER TABLE page_blocks ADD COLUMN revision INTEGER NOT NULL DEFAULT 0');
        if(!in_array('updated_by',$blockColumns,true))$pdo->exec('ALTER TABLE page_blocks ADD COLUMN updated_by INTEGER REFERENCES users(id)');
        if(!in_array('updated_at',$blockColumns,true))$pdo->exec('ALTER TABLE page_blocks ADD COLUMN updated_at TEXT');
        $pdo->exec('UPDATE page_blocks SET updated_at=COALESCE(updated_at,CURRENT_TIMESTAMP)');
        $itemColumns=array_column($pdo->query('PRAGMA table_info(pathway_items)')->fetchAll(),'name');
        if(!in_array('revision',$itemColumns,true))$pdo->exec('ALTER TABLE pathway_items ADD COLUMN revision INTEGER NOT NULL DEFAULT 0');
        if(!in_array('access_mode',$itemColumns,true))$pdo->exec("ALTER TABLE pathway_items ADD COLUMN access_mode TEXT NOT NULL DEFAULT 'all' CHECK(access_mode IN ('all','restricted','none'))");

        $pdo->exec('CREATE TABLE IF NOT EXISTS course_teachers (course_id INTEGER NOT NULL,teacher_id INTEGER NOT NULL,added_by INTEGER NOT NULL,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(course_id,teacher_id),FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(added_by) REFERENCES users(id) ON DELETE RESTRICT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS pathway_item_students (pathway_item_id INTEGER NOT NULL,student_id INTEGER NOT NULL,PRIMARY KEY(pathway_item_id,student_id),FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE)');
        $pdo->exec("CREATE TABLE IF NOT EXISTS collaboration_comments (id INTEGER PRIMARY KEY AUTOINCREMENT,subject_type TEXT NOT NULL CHECK(subject_type IN ('page','course')),subject_id INTEGER NOT NULL,author_id INTEGER NOT NULL,body TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','resolved')),created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,resolved_by INTEGER,resolved_at TEXT,FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(resolved_by) REFERENCES users(id) ON DELETE SET NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS edit_locks (entity_type TEXT NOT NULL CHECK(entity_type IN ('page_metadata','page_block','pathway_item','course_structure')),entity_id INTEGER NOT NULL,teacher_id INTEGER NOT NULL,owner_token TEXT NOT NULL,acquired_at INTEGER NOT NULL,expires_at INTEGER NOT NULL,PRIMARY KEY(entity_type,entity_id),FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE)");
        $lockColumns=array_column($pdo->query('PRAGMA table_info(edit_locks)')->fetchAll(),'name');
        if(!in_array('owner_token',$lockColumns,true)){$pdo->exec("ALTER TABLE edit_locks ADD COLUMN owner_token TEXT NOT NULL DEFAULT ''");$pdo->exec('DELETE FROM edit_locks');}
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_course_teachers_teacher ON course_teachers(teacher_id,course_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_item_students_student ON pathway_item_students(student_id,pathway_item_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_collaboration_comments_subject ON collaboration_comments(subject_type,subject_id,status,created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_edit_locks_expiry ON edit_locks(expires_at)');
    }

    private static function migratePageObjectives(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS page_objectives (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0,
            UNIQUE(page_id,title),
            FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_page_objectives_page ON page_objectives(page_id,position,id)');

        if ((int)$pdo->query('SELECT COUNT(*) FROM page_objectives')->fetchColumn() === 0) {
            $pdo->exec("INSERT OR IGNORE INTO page_objectives(page_id,title,description,position)
                SELECT pi.page_id,o.title,o.description,MIN(pi.position)
                FROM item_objectives io
                JOIN pathway_items pi ON pi.id=io.pathway_item_id
                JOIN course_objectives o ON o.id=io.objective_id
                GROUP BY pi.page_id,o.title,o.description");
        }
    }

    private static function migrateDefaultTags(PDO $pdo): void
    {
        $insert=$pdo->prepare('INSERT OR IGNORE INTO tags(name,color) VALUES(?,?)');
        foreach([
            ['Démarrage','#e8e5ff'],
            ['Méthode','#dff5ef'],
            ['Activité','#fff0cf'],
            ['Évaluation','#ffe1e9'],
            ['Médias','#dceeff'],
            ['Lecture','#e4efff'],
            ['Exercice','#f1e6ff'],
        ] as [$name,$color])$insert->execute([$name,$color]);
    }

    private static function allowSuffixedStudentCodes(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            $pdo->beginTransaction();
            $pdo->exec("CREATE TABLE users_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL CHECK(last_name = upper(last_name)),
                email TEXT NOT NULL UNIQUE,
                role TEXT NOT NULL CHECK(role IN ('student', 'teacher')),
                initials TEXT NOT NULL,
                color TEXT NOT NULL DEFAULT '#6d5dfc',
                class_group TEXT NOT NULL DEFAULT '',
                phone TEXT,
                login_code TEXT NOT NULL UNIQUE CHECK(role = 'teacher' OR length(login_code) >= 5),
                password_hash TEXT
            )");
            $pdo->exec('INSERT INTO users_new SELECT * FROM users');
            $pdo->exec('DROP TABLE users');
            $pdo->exec('ALTER TABLE users_new RENAME TO users');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    private static function studentCode(string $firstName, string $lastName): string
    {
        $compactFirstName = preg_replace('/\s+/u', '', trim($firstName)) ?? '';
        $compactLastName = preg_replace('/\s+/u', '', $lastName) ?? '';
        return mb_strtoupper(mb_substr($compactFirstName, 0, 2) . mb_substr($compactLastName, 0, 3), 'UTF-8');
    }
}
