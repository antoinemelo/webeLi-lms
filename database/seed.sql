INSERT INTO users (id, name, first_name, last_name, email, role, initials, color, class_group, phone, login_code, password_hash, is_superadmin, managed_by) VALUES
 (1, 'Nora MEIER', 'Nora', 'MEIER', 'nora@ecole.test', 'teacher', 'NM', '#5b4be7', '', NULL, 'nora', '$2y$10$NcHQLH/Xqq0OHFiYOk7WQejWexGMsUbhlZNDP/fgn.i6iSSzbnF8m', 1, NULL),
 (2, 'Lina ROSSI', 'Lina', 'ROSSI', 'lina@ecole.test', 'student', 'LR', '#ef6a8a', '10A', NULL, 'LIROS', NULL, 0, 1),
 (3, 'Sam DIALLO', 'Sam', 'DIALLO', 'sam@ecole.test', 'student', 'SD', '#2da58d', '10A', '+41 79 000 00 03', 'SADIA', NULL, 0, 1),
 (4, 'Noé MARTIN', 'Noé', 'MARTIN', 'noe@ecole.test', 'student', 'NM', '#e49b35', '10B', NULL, 'NOMAR', NULL, 0, 1);

INSERT INTO courses (id, reference, title, code, description, teacher_id, accent) VALUES
 (1, 'COURSE-SCIENCES-DEMO', 'Sciences · Le vivant', 'SCI-24', 'Observer, formuler une hypothèse et communiquer une démarche scientifique.', 1, '#6d5dfc'),
 (2, 'COURSE-MEDIAS-DEMO', 'Médias & information', 'MED-12', 'Comprendre et produire une information fiable.', 1, '#e45f86');

INSERT INTO enrollments (id, course_id, student_id) VALUES
 (1,1,2),(2,1,3),(3,1,4),(4,2,2);

INSERT INTO pages (id, reference, title, summary, status, estimated_minutes, owner_id, updated_by) VALUES
 (1, 'PAGE-BIENVENUE-DEMO', 'Bienvenue dans le parcours', 'Comprendre le fonctionnement du cours et choisir son point de départ.', 'ready', 8, 1, 1),
 (2, 'PAGE-OBSERVER-DEMO', 'Observer comme un scientifique', 'Passer d’une impression à une observation précise.', 'ready', 25, 1, 1),
 (3, 'PAGE-HYPOTHESE-DEMO', 'Construire une hypothèse', 'Écrire une hypothèse que l’on peut réellement tester.', 'ready', 35, 1, 1),
 (4, 'PAGE-CARNET-DEMO', 'Carnet de terrain', 'Documenter une sortie et partager ses indices.', 'ready', 45, 1, 1),
 (5, 'PAGE-EVALUATION-DEMO', 'Évaluation · expliquer sa démarche', 'Présenter une démarche scientifique complète.', 'ready', 50, 1, 1),
 (6, 'PAGE-SOURCE-DEMO', 'Reconnaître une source fiable', 'Un contenu prêt à l’emploi, encore hors parcours.', 'ready', 20, 1, 1),
 (7, 'PAGE-DEBAT-DEMO', 'Brouillon · débat mouvant', 'Une idée en préparation dans la bibliothèque.', 'draft', 15, 1, 1);

INSERT INTO page_blocks (page_id,type,body,caption,position) VALUES
 (1,'markdown','# Bienvenue 👋

Ce parcours avance **à ton rythme**. Pour chaque étape :

1. découvre les ressources ;
2. réalise la consigne ;
3. auto-évalue ton niveau de 0 à 3 ;
4. demande la confirmation de ton enseignant.

> Tu peux toujours revenir sur une étape déjà réalisée.','',1),
 (1,'markdown','## Les quatre niveaux

- **0 · Je découvre** : j’ai besoin d’aide
- **1 · Je commence** : je réussis avec un modèle
- **2 · Je maîtrise** : je réussis seul·e
- **3 · Je peux expliquer** : je peux aider une autre personne','',2),
 (2,'markdown','# Regarder ne suffit pas

Une observation scientifique décrit ce qui est visible ou mesurable, sans immédiatement l’expliquer.

**Défi :** observe un végétal pendant cinq minutes. Note cinq faits et une question.','',1),
 (2,'iframe','https://www.youtube-nocookie.com/embed/4vRZVQzQ2cU','Vidéo : apprendre à observer',2),
 (2,'file','uploads/fiche-observation.txt','Télécharger la fiche d’observation',3),
 (3,'markdown','# De la question à l’hypothèse

Une bonne hypothèse relie une cause possible à un effet observable.

`Si … alors … parce que …`

Rédige deux hypothèses, puis choisis celle que tu pourrais tester avec le matériel disponible.','',1),
 (4,'markdown','# Ton carnet de terrain

Ajoute trois observations datées, une photo ou un croquis et une courte conclusion.

## Avant de terminer

- les faits sont séparés des interprétations ;
- les unités sont indiquées ;
- la source de chaque image est citée.','',1),
 (4,'image','assets/field-notes.svg','Exemple de carnet clair et annoté',2),
 (5,'markdown','# Présentation finale

En trois minutes, présente ta question, ton hypothèse, les indices récoltés et ce que tu conclus.

> Il n’est pas nécessaire que ton hypothèse soit confirmée : la qualité de la démarche compte davantage.','',1),
 (6,'markdown','# Qui parle ? Avec quelles preuves ?

Utilise la grille **Auteur · Date · Sources · Intention** pour comparer deux articles.','',1),
 (7,'markdown','# Débat mouvant

Contenu à compléter avant utilisation.','',1);

INSERT INTO tags (id,name,color) VALUES
 (1,'Démarrage','#e8e5ff'),(2,'Méthode','#dff5ef'),(3,'Activité','#fff0cf'),(4,'Évaluation','#ffe1e9'),(5,'Médias','#dceeff'),
 (6,'Lecture','#e4efff'),(7,'Exercice','#f1e6ff');
INSERT INTO page_tags VALUES (1,1),(2,2),(2,3),(3,2),(3,3),(4,3),(5,4),(6,5),(7,3);

INSERT INTO page_objectives (page_id,title,description,position) VALUES
 (1,'Gagner en autonomie','Planifier et évaluer son propre travail.',1),
 (1,'Évaluer la fiabilité d’une information','Identifier auteur, intention et preuves.',2),
 (2,'Mener une démarche d’investigation','Passer d’une question à une conclusion étayée.',1),
 (3,'Mener une démarche d’investigation','Passer d’une question à une conclusion étayée.',1),
 (4,'Mener une démarche d’investigation','Passer d’une question à une conclusion étayée.',1),
 (4,'Communiquer ses résultats','Présenter clairement faits, raisonnement et limites.',2),
 (5,'Mener une démarche d’investigation','Passer d’une question à une conclusion étayée.',1),
 (5,'Communiquer ses résultats','Présenter clairement faits, raisonnement et limites.',2),
 (6,'Évaluer la fiabilité d’une information','Identifier auteur, intention et preuves.',1);

INSERT INTO course_objectives (id,course_id,title,description,position) VALUES
 (1,1,'Mener une démarche d’investigation','Passer d’une question à une conclusion étayée.',1),
 (2,1,'Communiquer ses résultats','Présenter clairement faits, raisonnement et limites.',2),
 (3,1,'Gagner en autonomie','Planifier et évaluer son propre travail.',3),
 (4,2,'Évaluer la fiabilité d’une information','Identifier auteur, intention et preuves.',1);

INSERT INTO course_skills (id,course_id,code,title,description,position) VALUES
 (1,1,'OBS','Observer et décrire','Produire des observations précises et mesurables.',1),
 (2,1,'HYP','Formuler une hypothèse','Proposer une explication testable.',2),
 (3,1,'ARG','Argumenter avec des preuves','Relier les indices à une conclusion.',3),
 (4,1,'COM','Communiquer clairement','Structurer une présentation scientifique.',4),
 (5,2,'SRC','Qualifier une source','Repérer auteur, date, intention et références.',1);

INSERT INTO pathway_items (id,course_id,page_id,position,deadline,is_evaluation,instructions) VALUES
 (1,1,1,1,date('now','-12 day'),0,''),
 (2,1,2,2,date('now','-5 day'),0,'Dépose tes cinq observations dans ton carnet.'),
 (3,1,3,3,date('now','+2 day'),0,'Apporte une hypothèse testable.'),
 (4,1,4,4,date('now','+8 day'),0,''),
 (5,1,5,5,date('now','+15 day'),1,'Présentation orale individuelle.'),
 (6,2,1,1,date('now','-2 day'),0,''),
 (7,2,6,2,date('now','+6 day'),1,'Comparer deux sources sur un même sujet.');

INSERT INTO item_objectives VALUES (1,3),(2,1),(3,1),(4,1),(4,2),(5,1),(5,2),(6,4),(7,4);
INSERT INTO item_skills VALUES (2,1),(3,2),(4,1),(4,3),(5,3),(5,4),(7,5);

INSERT INTO progress (enrollment_id,pathway_item_id,student_level,student_note,student_validated_at,teacher_level,teacher_note,teacher_validated_at) VALUES
 (1,1,3,'Tout est clair.',datetime('now','-11 day'),3,'Prête à avancer.',datetime('now','-10 day')),
 (1,2,2,'J’ai réussi à séparer faits et interprétations.',datetime('now','-4 day'),2,'Observations précises.',datetime('now','-3 day')),
 (2,1,2,'Compris.',datetime('now','-10 day'),2,'',datetime('now','-9 day')),
 (2,2,2,'Mon relevé est terminé.',datetime('now','-3 day'),NULL,'',NULL),
 (2,3,1,'Je ne sais pas si elle est testable.',datetime('now','-1 day'),NULL,'',NULL),
 (3,1,2,'',datetime('now','-8 day'),2,'',datetime('now','-7 day')),
 (4,6,3,'',datetime('now','-1 day'),3,'',datetime('now'));

INSERT INTO reward_types (id,course_id,name,icon,color,default_points) VALUES
 (1,1,'Persévérance','🌱','#2da58d',5),
 (2,1,'Curiosité','🔎','#6d5dfc',5),
 (3,1,'Entraide','🤝','#e49b35',10),
 (4,1,'Travail soigné','✨','#e45f86',5),
 (5,2,'Esprit critique','🧭','#4178d0',10);

INSERT INTO reward_awards (enrollment_id,pathway_item_id,reward_type_id,points,message,awarded_by,awarded_at) VALUES
 (1,1,2,5,'Tu as posé une excellente question.',1,datetime('now','-10 day')),
 (1,2,4,5,'Des observations très lisibles.',1,datetime('now','-3 day')),
 (2,1,1,5,'Tu as repris la consigne jusqu’au bout.',1,datetime('now','-9 day')),
 (3,1,3,10,'Merci d’avoir aidé le groupe.',1,datetime('now','-7 day')),
 (4,6,5,10,'Belle attention portée aux sources.',1,datetime('now'));
