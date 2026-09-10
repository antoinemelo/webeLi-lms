<?php
declare(strict_types=1);
return ['version'=>1,'name'=>'Discussions privées et notifications','up'=>static function(PDO $p): void {
$p->exec(<<<'SQL'
CREATE TABLE conversations (
 id TEXT PRIMARY KEY, scope_key TEXT NOT NULL, student_key TEXT NOT NULL, teacher_key TEXT NOT NULL,
 scope_title TEXT NOT NULL, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL,
 UNIQUE(scope_key,student_key,teacher_key)
);
CREATE TABLE participants (
 conversation_id TEXT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
 user_key TEXT NOT NULL, name TEXT NOT NULL, last_read_id INTEGER NOT NULL DEFAULT 0,
 PRIMARY KEY(conversation_id,user_key)
);
CREATE INDEX participants_user ON participants(user_key,conversation_id);
CREATE TABLE messages (
 id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id TEXT NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
 author_key TEXT NOT NULL, author_name TEXT NOT NULL, body TEXT NOT NULL CHECK(length(body) BETWEEN 1 AND 256),
 created_at INTEGER NOT NULL, edited_at INTEGER, revision INTEGER NOT NULL DEFAULT 0,
 request_key TEXT NOT NULL, UNIQUE(conversation_id,author_key,request_key)
);
CREATE INDEX messages_thread ON messages(conversation_id,id);
CREATE TABLE deletion_requests (
 id INTEGER PRIMARY KEY AUTOINCREMENT, user_key TEXT NOT NULL, name TEXT NOT NULL, requested_at INTEGER NOT NULL,
 status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','declined','cancelled')),
 resolved_at INTEGER, resolved_by TEXT
);
CREATE UNIQUE INDEX one_pending_deletion ON deletion_requests(user_key) WHERE status='pending';
CREATE TABLE push_subscriptions (
 id TEXT PRIMARY KEY, user_key TEXT NOT NULL, endpoint TEXT NOT NULL UNIQUE, public_key TEXT NOT NULL,
 auth_token TEXT NOT NULL, created_at INTEGER NOT NULL
);
CREATE INDEX push_user ON push_subscriptions(user_key);
CREATE TABLE push_events (
 id INTEGER PRIMARY KEY AUTOINCREMENT, user_key TEXT NOT NULL, event_key TEXT NOT NULL,
 kind TEXT NOT NULL, reference TEXT NOT NULL, created_at INTEGER NOT NULL, UNIQUE(user_key,event_key)
);
CREATE TABLE push_deliveries (
 event_id INTEGER NOT NULL REFERENCES push_events(id) ON DELETE CASCADE,
 subscription_id TEXT NOT NULL REFERENCES push_subscriptions(id) ON DELETE CASCADE,
 attempts INTEGER NOT NULL DEFAULT 0, available_at INTEGER NOT NULL DEFAULT 0,
 state TEXT NOT NULL DEFAULT 'pending', PRIMARY KEY(event_id,subscription_id)
);
CREATE INDEX push_pending ON push_deliveries(state,available_at);
PRAGMA secure_delete=ON;
SQL);
}];
