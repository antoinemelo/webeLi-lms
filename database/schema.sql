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
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    owner_id INTEGER NOT NULL,
    updated_by INTEGER,
    revision INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE page_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('markdown','image','file','iframe')),
    body TEXT NOT NULL DEFAULT '',
    caption TEXT NOT NULL DEFAULT '',
    position INTEGER NOT NULL,
    revision INTEGER NOT NULL DEFAULT 0,
    updated_by INTEGER,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(page_id, position),
    FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE,
    FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
);

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
    instructions TEXT NOT NULL DEFAULT '',
    access_mode TEXT NOT NULL DEFAULT 'all' CHECK(access_mode IN ('all','restricted','none')),
    revision INTEGER NOT NULL DEFAULT 0,
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
    teacher_level INTEGER CHECK(teacher_level BETWEEN 0 AND 3),
    teacher_note TEXT NOT NULL DEFAULT '',
    teacher_validated_at TEXT,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(enrollment_id, pathway_item_id),
    FOREIGN KEY(enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY(pathway_item_id) REFERENCES pathway_items(id) ON DELETE CASCADE
);

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
    default_points INTEGER NOT NULL DEFAULT 5 CHECK(default_points > 0),
    active INTEGER NOT NULL DEFAULT 1 CHECK(active IN (0,1)),
    UNIQUE(course_id, name),
    FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE reward_awards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    enrollment_id INTEGER NOT NULL,
    pathway_item_id INTEGER NOT NULL,
    reward_type_id INTEGER NOT NULL,
    points INTEGER NOT NULL CHECK(points > 0),
    message TEXT NOT NULL DEFAULT '',
    awarded_by INTEGER NOT NULL,
    awarded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','sent','failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at TEXT
);

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
WHEN NEW.account_status='pending' AND (SELECT COUNT(*) FROM users WHERE account_status='pending')>=10
BEGIN
    SELECT RAISE(ABORT, 'pending registration limit reached');
END;

PRAGMA user_version = 6;
