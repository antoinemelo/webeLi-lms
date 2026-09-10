PRAGMA foreign_keys = ON;

CREATE TABLE schema_migrations (
    version INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    checksum TEXT NOT NULL,
    applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL CHECK(last_name = upper(last_name)),
    email TEXT NOT NULL UNIQUE,
    secondary_email TEXT,
    role TEXT NOT NULL CHECK(role IN ('student', 'teacher')),
    initials TEXT NOT NULL,
    color TEXT NOT NULL DEFAULT '#6d5dfc',
    class_group TEXT NOT NULL DEFAULT '',
    phone TEXT,
    login_code TEXT NOT NULL UNIQUE CHECK(role = 'teacher' OR length(login_code) >= 5),
    password_hash TEXT,
    account_status TEXT NOT NULL DEFAULT 'active' CHECK(account_status IN ('pending','active','archived')),
    email_verified_at TEXT,
    verification_token_hash TEXT,
    verification_expires_at TEXT,
    password_reset_token_hash TEXT,
    password_reset_expires_at TEXT,
    pending_course_title TEXT,
    is_superadmin INTEGER NOT NULL DEFAULT 0 CHECK(is_superadmin IN (0,1)),
    language TEXT CHECK(language IS NULL OR language IN ('fr','en','de','it','es')),
    student_session_token_hash TEXT,
    student_session_seen_at INTEGER,
    student_first_login_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    managed_by INTEGER,
    FOREIGN KEY(managed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE courses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    code TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    teacher_id INTEGER NOT NULL,
    accent TEXT NOT NULL DEFAULT '#6d5dfc',
    archived INTEGER NOT NULL DEFAULT 0 CHECK(archived IN (0,1)),
    FOREIGN KEY(teacher_id) REFERENCES users(id)
);

CREATE TABLE course_teachers (
    course_id INTEGER NOT NULL,
    teacher_id INTEGER NOT NULL,
    added_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(course_id, teacher_id),
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(added_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE enrollments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','archived')),
    archived_at TEXT,
    pathway_changes_seen_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
    UNIQUE(course_id, student_id),
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE course_accesses (
    user_id INTEGER NOT NULL,
    course_id INTEGER NOT NULL,
    last_accessed_at INTEGER NOT NULL,
    PRIMARY KEY(user_id, course_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    summary TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','ready')),
    estimated_minutes INTEGER NOT NULL DEFAULT 15 CHECK(estimated_minutes > 0),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
    owner_id INTEGER NOT NULL,
    updated_by INTEGER,
    revision INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE page_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('markdown','image','file','iframe','submission')),
    body TEXT NOT NULL DEFAULT '',
    caption TEXT NOT NULL DEFAULT '',
    image_alt TEXT,
    embed_kind TEXT NOT NULL DEFAULT 'auto' CHECK(embed_kind IN ('auto','media','integration')),
    embed_height INTEGER CHECK(embed_height IS NULL OR embed_height BETWEEN 100 AND 2000),
    submission_mode TEXT NOT NULL DEFAULT 'link' CHECK(submission_mode IN ('link','text','both')),
    submission_required INTEGER NOT NULL DEFAULT 1 CHECK(submission_required IN (0,1)),
    position INTEGER NOT NULL,
    revision INTEGER NOT NULL DEFAULT 0,
    updated_by INTEGER,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(page_id, position),
    FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE work_submissions (
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pathway_item_id INTEGER NOT NULL REFERENCES pathway_items(id) ON DELETE CASCADE,
    page_block_id INTEGER NOT NULL REFERENCES page_blocks(id) ON DELETE CASCADE,
    url TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '' CHECK(length(body)<=512),
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','submitted')),
    revision INTEGER NOT NULL DEFAULT 0 CHECK(revision>=0),
    submitted_at TEXT,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(student_id,pathway_item_id,page_block_id)
);

CREATE TABLE work_submission_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pathway_item_id INTEGER NOT NULL REFERENCES pathway_items(id) ON DELETE CASCADE,
    page_block_id INTEGER NOT NULL REFERENCES page_blocks(id) ON DELETE CASCADE,
    url TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '' CHECK(length(body)<=512),
    prompt TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL DEFAULT '',
    submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reopened_at TEXT,
    reopened_by INTEGER REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX idx_work_versions_lookup ON work_submission_versions(student_id,pathway_item_id,page_block_id,id);

CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    color TEXT NOT NULL DEFAULT '#e8e5ff'
);

CREATE TABLE page_tags (
    page_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY(page_id, tag_id),
    FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

CREATE TABLE page_objectives (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    position INTEGER NOT NULL DEFAULT 0,
    UNIQUE(page_id, title),
    FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE
);

CREATE INDEX idx_page_objectives_page ON page_objectives(page_id, position, id);

CREATE TABLE course_objectives (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    position INTEGER NOT NULL DEFAULT 0,
    UNIQUE(course_id, title),
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE course_skills (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    code TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    position INTEGER NOT NULL DEFAULT 0,
    UNIQUE(course_id, code),
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE pathway_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    page_id INTEGER NOT NULL,
    position INTEGER NOT NULL,
    deadline TEXT,
    is_evaluation INTEGER NOT NULL DEFAULT 0 CHECK(is_evaluation IN (0,1)),
    self_evaluation_enabled INTEGER NOT NULL DEFAULT 1 CHECK(self_evaluation_enabled IN (0,1)),
    evaluation_weight REAL NOT NULL DEFAULT 1 CHECK(evaluation_weight IN (0.5,1,2,3,4)),
    instructions TEXT NOT NULL DEFAULT '',
    access_mode TEXT NOT NULL DEFAULT 'all' CHECK(access_mode IN ('all','restricted','none')),
    framework_tracking_enabled INTEGER NOT NULL DEFAULT 1 CHECK(framework_tracking_enabled IN (0,1)),
    revision INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
    UNIQUE(course_id, position),
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE RESTRICT
);

CREATE TABLE pathway_item_students (
    pathway_item_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    PRIMARY KEY(pathway_item_id, student_id),
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TRIGGER touch_pathway_item_after_update
AFTER UPDATE OF course_id,page_id,position,deadline,is_evaluation,self_evaluation_enabled,evaluation_weight,instructions,access_mode,framework_tracking_enabled ON pathway_items
WHEN NEW.updated_at IS OLD.updated_at
BEGIN
    UPDATE pathway_items SET updated_at=strftime('%Y-%m-%d %H:%M:%f','now') WHERE id=NEW.id;
END;

CREATE TABLE collaboration_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_type TEXT NOT NULL CHECK(subject_type IN ('page','course')),
    subject_id INTEGER NOT NULL,
    author_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','resolved')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by INTEGER,
    resolved_at TEXT,
    FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(resolved_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE edit_locks (
    entity_type TEXT NOT NULL CHECK(entity_type IN ('page_metadata','page_block','pathway_item','course_structure')),
    entity_id INTEGER NOT NULL,
    teacher_id INTEGER NOT NULL,
    owner_token TEXT NOT NULL,
    acquired_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    PRIMARY KEY(entity_type, entity_id),
    FOREIGN KEY(teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE item_objectives (
    pathway_item_id INTEGER NOT NULL,
    objective_id INTEGER NOT NULL,
    PRIMARY KEY(pathway_item_id, objective_id),
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
    FOREIGN KEY(objective_id) REFERENCES course_objectives(id) ON DELETE CASCADE
);

CREATE TABLE item_skills (
    pathway_item_id INTEGER NOT NULL,
    skill_id INTEGER NOT NULL,
    PRIMARY KEY(pathway_item_id, skill_id),
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
    FOREIGN KEY(skill_id) REFERENCES course_skills(id) ON DELETE CASCADE
);

CREATE TABLE progress (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    enrollment_id INTEGER NOT NULL,
    pathway_item_id INTEGER NOT NULL,
    student_level INTEGER CHECK(student_level BETWEEN 0 AND 3),
    student_note TEXT NOT NULL DEFAULT '',
    student_validated_at TEXT,
    completed_at TEXT,
    teacher_level INTEGER CHECK(teacher_level BETWEEN 0 AND 3),
    evaluation_score REAL CHECK(evaluation_score BETWEEN 0 AND 10),
    teacher_note TEXT NOT NULL DEFAULT '',
    teacher_validated_at TEXT,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(enrollment_id, pathway_item_id),
    FOREIGN KEY(enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE
);

CREATE TABLE student_private_notes (
    student_id INTEGER NOT NULL,
    pathway_item_id INTEGER NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(student_id, pathway_item_id),
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE
);

CREATE TABLE course_announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    created_by INTEGER,
    title TEXT NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived INTEGER NOT NULL DEFAULT 0 CHECK(archived IN (0,1)),
    audience TEXT NOT NULL DEFAULT 'all' CHECK(audience IN ('all','class','selected')),
    request_key TEXT,
    request_hash TEXT,
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE UNIQUE INDEX idx_announcement_request ON course_announcements(created_by,request_key);

CREATE TABLE message_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    revision INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_message_templates_teacher ON message_templates(teacher_id,name);

CREATE TABLE announcement_recipients (
    announcement_id INTEGER NOT NULL REFERENCES course_announcements(id) ON DELETE CASCADE,
    student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    student_name TEXT NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    email TEXT NOT NULL,
    cc TEXT NOT NULL DEFAULT '',
    PRIMARY KEY(announcement_id,student_id)
);
CREATE INDEX idx_announcement_recipients_student ON announcement_recipients(student_id,announcement_id);

CREATE TABLE announcement_reads (
    announcement_id INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    read_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(announcement_id, student_id),
    FOREIGN KEY(announcement_id) REFERENCES course_announcements(id) ON DELETE CASCADE,
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE qcm_drafts (
    student_id INTEGER NOT NULL,
    pathway_item_id INTEGER NOT NULL,
    page_block_id INTEGER NOT NULL,
    qcm_key TEXT NOT NULL,
    answers TEXT NOT NULL,
    revision INTEGER NOT NULL DEFAULT 1 CHECK(revision > 0),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(student_id,pathway_item_id,page_block_id,qcm_key),
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
    FOREIGN KEY(page_block_id) REFERENCES page_blocks(id) ON DELETE CASCADE
);

CREATE TABLE qcm_attempts (
    student_id INTEGER NOT NULL,
    pathway_item_id INTEGER NOT NULL,
    page_block_id INTEGER NOT NULL,
    qcm_key TEXT NOT NULL,
    score_percent REAL NOT NULL CHECK(score_percent BETWEEN 0 AND 100),
    correct_questions INTEGER NOT NULL CHECK(correct_questions >= 0),
    total_questions INTEGER NOT NULL CHECK(total_questions > 0 AND correct_questions <= total_questions),
    attempt_count INTEGER NOT NULL DEFAULT 1 CHECK(attempt_count > 0),
    answered_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(student_id,pathway_item_id,page_block_id,qcm_key),
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
    FOREIGN KEY(page_block_id) REFERENCES page_blocks(id) ON DELETE CASCADE
);

CREATE INDEX idx_student_private_notes_item ON student_private_notes(pathway_item_id);
CREATE INDEX idx_course_announcements_course ON course_announcements(course_id, archived, created_at);
CREATE INDEX idx_announcement_reads_student ON announcement_reads(student_id, announcement_id);
CREATE INDEX idx_qcm_attempts_student_item ON qcm_attempts(student_id,pathway_item_id,answered_at DESC);

CREATE TABLE learning_visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    pathway_item_id INTEGER NOT NULL,
    visit_token TEXT NOT NULL,
    started_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    duration_seconds INTEGER NOT NULL DEFAULT 0 CHECK(duration_seconds >= 0),
    UNIQUE(student_id, pathway_item_id, visit_token),
    FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE
);

CREATE TABLE reward_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    icon TEXT NOT NULL DEFAULT '✨',
    color TEXT NOT NULL DEFAULT '#6d5dfc',
    default_points INTEGER NOT NULL DEFAULT 1 CHECK(default_points BETWEEN -100 AND 100 AND default_points <> 0),
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    UNIQUE(course_id, name),
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE reward_awards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    enrollment_id INTEGER NOT NULL,
    pathway_item_id INTEGER NOT NULL,
    reward_type_id INTEGER NOT NULL,
    points INTEGER NOT NULL CHECK(points BETWEEN -100 AND 100 AND points <> 0),
    message TEXT NOT NULL DEFAULT '',
    awarded_by INTEGER NOT NULL,
    awarded_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%d %H:%M:%f','now')),
    FOREIGN KEY(enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE,
    FOREIGN KEY(reward_type_id) REFERENCES reward_types(id) ON DELETE RESTRICT,
    FOREIGN KEY(awarded_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE notification_outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event TEXT NOT NULL,
    recipient TEXT NOT NULL,
    subject TEXT NOT NULL,
    body TEXT NOT NULL,
    cc TEXT NOT NULL DEFAULT '',
    bcc TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','sent','failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at TEXT,
    announcement_id INTEGER,
    available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(announcement_id) REFERENCES course_announcements(id) ON DELETE SET NULL
);

CREATE TRIGGER cancel_pending_announcement_mail BEFORE DELETE ON course_announcements BEGIN
    DELETE FROM notification_outbox WHERE event='course.announcement' AND announcement_id=OLD.id AND status='pending';
END;

CREATE TABLE registration_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_hash TEXT NOT NULL,
    accepted INTEGER NOT NULL DEFAULT 0 CHECK(accepted IN (0,1)),
    attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_reset_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_hash TEXT NOT NULL,
    email_hash TEXT NOT NULL,
    requested_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_pathway_course_position ON pathway_items(course_id, position);
CREATE INDEX idx_progress_enrollment ON progress(enrollment_id);
CREATE INDEX idx_learning_visits_student_item ON learning_visits(student_id, pathway_item_id, last_seen_at DESC);
CREATE INDEX idx_learning_visits_retention ON learning_visits(last_seen_at);
CREATE INDEX idx_rewards_enrollment ON reward_awards(enrollment_id, awarded_at DESC);
CREATE INDEX idx_outbox_pending ON notification_outbox(status, created_at);
CREATE INDEX idx_outbox_available ON notification_outbox(status, available_at, id);
CREATE INDEX idx_registration_attempts_ip ON registration_attempts(ip_hash, attempted_at);
CREATE INDEX idx_password_reset_attempts_ip ON password_reset_attempts(ip_hash, requested_at);
CREATE INDEX idx_password_reset_attempts_email ON password_reset_attempts(email_hash, requested_at);
CREATE INDEX idx_users_verification_expiry ON users(account_status, verification_expires_at);
CREATE INDEX idx_courses_reference ON courses(reference);
CREATE INDEX idx_course_accesses_recent ON course_accesses(user_id,last_accessed_at DESC);
CREATE INDEX idx_pages_reference ON pages(reference);
CREATE INDEX idx_course_teachers_teacher ON course_teachers(teacher_id, course_id);
CREATE INDEX idx_item_students_student ON pathway_item_students(student_id, pathway_item_id);
CREATE INDEX idx_collaboration_comments_subject ON collaboration_comments(subject_type, subject_id, status, created_at);
CREATE INDEX idx_edit_locks_expiry ON edit_locks(expires_at);

CREATE TRIGGER limit_pending_registrations
BEFORE INSERT ON users
WHEN NEW.account_status='pending' AND NEW.managed_by IS NULL
  AND (SELECT COUNT(*) FROM users WHERE account_status='pending' AND managed_by IS NULL)>=10
BEGIN
    SELECT RAISE(ABORT, 'pending registration limit reached');
END;

CREATE TABLE student_followups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
            course_id INTEGER REFERENCES courses(id) ON DELETE SET NULL,
            course_title TEXT NOT NULL DEFAULT '',
            kind TEXT NOT NULL CHECK(kind IN ('meeting','correspondence','payment')),
            occurred_at TEXT NOT NULL,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            revision INTEGER NOT NULL DEFAULT 0,
            request_key TEXT NOT NULL,
            request_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(created_by,request_key)
        );
        CREATE TABLE student_followup_participants (
            followup_id INTEGER NOT NULL REFERENCES student_followups(id) ON DELETE CASCADE,
            student_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            PRIMARY KEY(followup_id,student_id)
        );
        CREATE INDEX idx_followups_date ON student_followups(occurred_at DESC,id DESC);
        CREATE INDEX idx_followup_participants_student ON student_followup_participants(student_id,followup_id);
        CREATE INDEX idx_outbox_announcement_recipient ON notification_outbox(announcement_id,recipient);
        CREATE TRIGGER remove_empty_student_followup AFTER DELETE ON student_followup_participants
        BEGIN DELETE FROM student_followups WHERE id=OLD.followup_id AND NOT EXISTS(SELECT 1 FROM student_followup_participants WHERE followup_id=OLD.followup_id); END;
        UPDATE notification_outbox SET body='' WHERE status='sent' AND body<>'';
        CREATE TRIGGER clear_sent_mail_body_insert AFTER INSERT ON notification_outbox WHEN NEW.status='sent' AND NEW.body<>''
        BEGIN UPDATE notification_outbox SET body='' WHERE id=NEW.id; END;
        CREATE TRIGGER clear_sent_mail_body_update AFTER UPDATE OF status,body ON notification_outbox WHEN NEW.status='sent' AND NEW.body<>''
        BEGIN UPDATE notification_outbox SET body='' WHERE id=NEW.id; END;

PRAGMA user_version = 24;

CREATE TABLE pwa_logins (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at INTEGER NOT NULL
);
CREATE TRIGGER revoke_pwa_login_on_account_change
AFTER UPDATE OF password_hash,login_code,account_status,role ON users
WHEN OLD.password_hash IS NOT NEW.password_hash OR OLD.login_code IS NOT NEW.login_code
  OR OLD.account_status IS NOT NEW.account_status OR OLD.role IS NOT NEW.role
BEGIN DELETE FROM pwa_logins WHERE user_id=NEW.id; END;

ALTER TABLE users ADD COLUMN messaging_uuid TEXT NOT NULL DEFAULT '';
CREATE UNIQUE INDEX users_messaging_uuid ON users(messaging_uuid) WHERE messaging_uuid<>'';
CREATE TRIGGER users_messaging_identity AFTER INSERT ON users WHEN NEW.messaging_uuid='' BEGIN UPDATE users SET messaging_uuid=lower(hex(randomblob(16))) WHERE id=NEW.id; END;
ALTER TABLE courses ADD COLUMN messaging_uuid TEXT NOT NULL DEFAULT '';
CREATE UNIQUE INDEX courses_messaging_uuid ON courses(messaging_uuid) WHERE messaging_uuid<>'';
CREATE TRIGGER courses_messaging_identity AFTER INSERT ON courses WHEN NEW.messaging_uuid='' BEGIN UPDATE courses SET messaging_uuid=lower(hex(randomblob(16))) WHERE id=NEW.id; END;
ALTER TABLE courses ADD COLUMN messaging_enabled INTEGER NOT NULL DEFAULT 0 CHECK(messaging_enabled IN (0,1));
CREATE TABLE messaging_instance (id INTEGER PRIMARY KEY CHECK(id=1),uuid TEXT NOT NULL);
INSERT INTO messaging_instance VALUES(1,lower(hex(randomblob(16))));
