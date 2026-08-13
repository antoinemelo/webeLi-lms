<?php

declare(strict_types=1);

function uploaded_json(string $field): array
{
    $file=$_FILES[$field]??null;
    if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new TransferException('Sélectionnez un fichier JSON valide.');
    if((int)($file['size']??0)>25*1024*1024)throw new TransferException('Le fichier dépasse la limite de 25 Mo.');
    $contents=file_get_contents((string)$file['tmp_name']);if($contents===false)throw new TransferException('Le fichier ne peut pas être lu.');
    try{$decoded=json_decode($contents,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){throw new TransferException('Le JSON est invalide.');}
    if(!is_array($decoded))throw new TransferException('Le document JSON doit être un objet.');return $decoded;
}

function send_json_download(array $document, string $filename): never
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',basename($filename)).'"');
    header('Cache-Control: no-store');
    echo json_encode($document,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}

function import_failure_message(Throwable $exception): string
{
    if (!$exception instanceof TransferException) return t('Import impossible : le fichier entre en conflit avec les données existantes.');
    $message=$exception->getMessage();
    foreach([
        'Import arrêté : page(s) absente(s) de la bibliothèque : '=>'Import arrêté : page(s) absente(s) de la bibliothèque : :details',
        'Import arrêté : parcours absent : '=>'Import arrêté : parcours absent : :details',
        'Le courriel '=>'Le courriel :details',
        'La génération PDF a échoué. '=>'La génération PDF a échoué. :details',
    ] as $prefix=>$key)if(str_starts_with($message,$prefix))return t($key,['details'=>substr($message,strlen($prefix))]);
    return t($message);
}

function reserve_imported_accounts(int $count): bool
{
    if($count<=0)return true;$ipHash=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
    $ipAttempts=(int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE ip_hash=? AND attempted_at>=datetime('now','-15 minutes')",[$ipHash])['n']??0);
    $hour=(int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE accepted=1 AND attempted_at>=datetime('now','-1 hour')")['n']??0);
    $day=(int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE accepted=1 AND attempted_at>=datetime('now','-1 day')")['n']??0);
    $pending=(int)(one("SELECT COUNT(*) AS n FROM users WHERE account_status='pending'")['n']??0);
    $allowed=$ipAttempts+$count<=REGISTRATION_IP_ATTEMPTS_15_MINUTES&&$hour+$count<=REGISTRATION_ACCEPTED_PER_HOUR&&$day+$count<=REGISTRATION_ACCEPTED_PER_DAY&&$pending+$count<=REGISTRATION_PENDING_LIMIT;
    $insert=db()->prepare('INSERT INTO registration_attempts(ip_hash,accepted) VALUES(?,?)');for($i=0;$i<$count;$i++)$insert->execute([$ipHash,$allowed?1:0]);return $allowed;
}

function registration_guard(string $role): void
{
    $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $startedAt = (float)($_SESSION['registration_started_at'] ?? 0);
    $elapsed = microtime(true) - $startedAt;
    $honeypot = trim((string)($_POST['website'] ?? ''));
    $ipAttempts = (int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE ip_hash=? AND attempted_at>=datetime('now','-15 minutes')", [$ipHash])['n'] ?? 0);
    $globalAttempts = (int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE accepted=1 AND attempted_at>=datetime('now','-1 hour')")['n'] ?? 0);
    $dailyAttempts = (int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE accepted=1 AND attempted_at>=datetime('now','-1 day')")['n'] ?? 0);
    $pendingAccounts = (int)(one("SELECT COUNT(*) AS n FROM users WHERE account_status='pending'")['n'] ?? 0);
    $allowed = registration_is_allowed($honeypot,$elapsed,$ipAttempts,$globalAttempts,$dailyAttempts,$pendingAccounts);
    run('INSERT INTO registration_attempts(ip_hash,accepted) VALUES(?,?)', [$ipHash,$allowed?1:0]);
    if ($honeypot !== '') {
        flash('Si les informations sont valides, un courriel de confirmation sera envoyé.');
        redirect('login');
    }
    if (!$allowed) {
        flash('Trop de demandes d’inscription ont été reçues. Patientez 15 minutes avant de réessayer.', 'error');
        redirect('register', ['role'=>$role]);
    }
}

function issue_account_verification(int $userId, string $email, string $firstName, string $code, ?string $language = null): bool
{
    // 128 bits restent largement suffisants pour un lien valable 15 minutes et
    // produisent une URL courte que les relais de messagerie ne replient pas.
    $token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    run("UPDATE users SET verification_token_hash=?,verification_expires_at=? WHERE id=? AND account_status='pending'", [
        hash('sha256',$token),
        (string)(time()+REGISTRATION_VERIFICATION_SECONDS),
        $userId,
    ]);
    $url = verification_url($token);
    $language = normalize_language($language) ?? current_language();
    $messageId = enqueue('account.verification',$email,t('Activez votre compte liike dans les 15 minutes', [], $language),
        t("Bonjour :name,\n\nVotre identifiant est :code.\n\nActivez votre compte dans les 15 minutes :\n:url\n\nSans validation, le compte sera automatiquement supprimé.", ['name'=>$firstName,'code'=>$code,'url'=>$url], $language));
    return try_send_outbox($messageId);
}

function verify_registration(string $token): never
{
    $token=trim($token);
    purge_expired_registrations();
    // Les jetons hexadécimaux de 64 caractères restent acceptés afin de ne pas
    // invalider les courriels émis avant le passage aux URL courtes.
    if (!preg_match('/^(?:[A-Za-z0-9_-]{22}|[a-f0-9]{64})$/', $token)) {
        flash('Ce lien de validation est invalide ou expiré.', 'error');
        redirect('login');
    }
    $user = verification_user(db(),hash('sha256',$token));
    if (!$user) {
        flash('Ce lien de validation est invalide ou expiré. Le compte non validé a été supprimé.', 'error');
        redirect('login');
    }
    $verificationLanguage = normalize_language((string)($user['language'] ?? ''));
    if ($verificationLanguage !== null) use_language($verificationLanguage);
    if ($user['account_status'] === 'pending') {
        db()->beginTransaction();
        try {
            // Le jeton reste associé au compte jusqu'à son échéance : les filtres
            // de messagerie peuvent ouvrir le lien avant le clic de l'utilisateur.
            $activate=db()->prepare("UPDATE users SET account_status='active',email_verified_at=CURRENT_TIMESTAMP WHERE id=? AND account_status='pending'");
            $activate->execute([$user['id']]);
            if ($activate->rowCount()===1 && $user['role'] === 'teacher') {
                run('INSERT INTO courses(reference,title,code,description,teacher_id,accent) VALUES(?,?,?,?,?,?)', [new_entity_reference('COURSE'),$user['pending_course_title']?:'Mon premier cours','COURS-'.$user['id'],'Votre premier parcours pédagogique.',$user['id'],'#6d5dfc']);
                $courseId = (int)db()->lastInsertId();
                foreach ([['Persévérance','🌱',5],['Curiosité','🔎',5],['Entraide','🤝',10],['Travail soigné','✨',5]] as [$name,$icon,$points]) {
                    run('INSERT INTO reward_types(course_id,name,icon,color,default_points) VALUES(?,?,?,?,?)', [$courseId,$name,$icon,'#6d5dfc',$points]);
                }
                run('UPDATE users SET pending_course_title=NULL WHERE id=?', [$user['id']]);
            }
            db()->commit();
        } catch (Throwable $exception) {
            db()->rollBack();
            throw $exception;
        }
    }
    flash(t('Votre courriel est validé. Votre compte est maintenant actif ; vous pouvez vous connecter avec :code.', ['code'=>$user['login_code']]));
    redirect('login');
}

function issue_password_reset(array $user): bool
{
    $token=rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');
    run("UPDATE users SET password_reset_token_hash=?,password_reset_expires_at=? WHERE id=? AND role='teacher' AND account_status='active'",[
        hash('sha256',$token),(string)(time()+PASSWORD_RESET_SECONDS),$user['id'],
    ]);
    $language=normalize_language((string)($user['language']??''))??current_language();
    $url=password_reset_url($token);
    $messageId=enqueue('password.reset',(string)$user['email'],t('Réinitialisation de votre mot de passe liike',[],$language),
        t("Bonjour :name,\n\nDéfinissez un nouveau mot de passe dans les 15 minutes :\n:url\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez ce message.",['name'=>$user['first_name'],'url'=>$url],$language));
    return try_send_outbox($messageId);
}

function send_student_code_reminder(array $user): bool
{
    $language=normalize_language((string)($user['language']??''))??current_language();
    $messageId=enqueue('credentials.reminder',(string)$user['email'],t('Votre code personnel liike',[],$language),
        t("Bonjour :name,\n\nVotre code personnel est :code.\n\nUtilisez l’onglet Élève pour vous connecter.",['name'=>$user['first_name'],'code'=>$user['login_code']],$language));
    return try_send_outbox($messageId);
}

function open_authenticated_session(array $user): never
{
    $studentSessionToken=null;
    if($user['role']==='student'){
        $studentSessionToken=start_student_session(db(),(int)$user['id']);
        if($studentSessionToken===null){
            unset($_SESSION['user_id'],$_SESSION['student_session_token'],$_SESSION['login_teacher_id']);
            flash('Toutes les sessions de ce compte ont été déconnectées, car une connexion simultanée a été détectée. Reconnectez-vous pour ouvrir une seule session.','error');
            redirect('login');
        }
    }
    session_regenerate_id(true);
    unset($_SESSION['login_teacher_id']);
    $_SESSION['user_id']=(int)$user['id'];
    if($studentSessionToken!==null)$_SESSION['student_session_token']=$studentSessionToken;
    $profileLanguage=normalize_language((string)($user['language']??''));
    if($profileLanguage!==null)use_language($profileLanguage);
    flash(t('Bienvenue :name !',['name'=>$user['first_name']]));
    if($user['role']==='student'&&!empty($_SESSION['pending_join_course_code']))redirect('join');
    unset($_SESSION['pending_join_course_code']);
    redirect($user['role']==='teacher'?'teacher':'student');
}

function handle_action(string $action): never
{
    if ($action === 'login_continue') {
        set_login_language((string)($_POST['language'] ?? ''));
        use_language((string)($_SESSION['login_language'] ?? 'fr'));
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $user=$identifier!==''?one("SELECT * FROM users WHERE lower(login_code)=lower(?) AND account_status='active'",[$identifier]):null;
        if(!$user){
            unset($_SESSION['login_teacher_id']);
            flash('Nom d’utilisateur inconnu.','error');
            redirect('login');
        }
        if($user['role']==='teacher'){
            $_SESSION['login_teacher_id']=(int)$user['id'];
            redirect('login');
        }
        open_authenticated_session($user);
    }

    if ($action === 'login_password') {
        set_login_language((string)($_POST['language'] ?? ''));
        use_language((string)($_SESSION['login_language'] ?? 'fr'));
        $teacherId=(int)($_SESSION['login_teacher_id']??0);
        $user=$teacherId>0?one("SELECT * FROM users WHERE id=? AND role='teacher' AND account_status='active'",[$teacherId]):null;
        if(!$user){unset($_SESSION['login_teacher_id']);flash('La tentative de connexion a expiré. Recommencez avec votre nom d’utilisateur.','error');redirect('login');}
        if(!password_verify((string)($_POST['password']??''),(string)$user['password_hash'])){
            flash('Mot de passe incorrect.','error');
            redirect('login');
        }
        open_authenticated_session($user);
    }

    if ($action === 'request_password_reset') {
        $email=mb_strtolower(trim((string)($_POST['email']??'')),'UTF-8');
        $ipHash=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        $emailHash=hash('sha256',$email);
        if(password_reset_request_allowed(db(),$ipHash,$emailHash)){
            record_password_reset_request(db(),$ipHash,$emailHash);
            $account=filter_var($email,FILTER_VALIDATE_EMAIL)?one("SELECT * FROM users WHERE lower(email)=? AND account_status='active'",[$email]):null;
            if($account){
                if($account['role']==='teacher')issue_password_reset($account);
                else send_student_code_reminder($account);
            }
        }
        flash('Si cette adresse correspond à un compte actif, les informations de récupération ont été envoyées.');
        redirect('login');
    }

    if ($action === 'reset_password') {
        $token=trim((string)($_POST['reset_token']??''));
        $password=(string)($_POST['new_password']??'');
        $confirmation=(string)($_POST['new_password_confirm']??'');
        $account=preg_match('/^[A-Za-z0-9_-]{22}$/',$token)?password_reset_user(db(),hash('sha256',$token)):null;
        if(!$account){flash('Ce lien de réinitialisation est invalide ou expiré.','error');redirect('recover');}
        $language=normalize_language((string)($account['language']??''));if($language!==null)use_language($language);
        if(mb_strlen($password)<8){flash('Le nouveau mot de passe doit contenir au moins 8 caractères.','error');redirect('reset-password',['r'=>$token]);}
        if($password!==$confirmation){flash('La confirmation du nouveau mot de passe ne correspond pas.','error');redirect('reset-password',['r'=>$token]);}
        run('UPDATE users SET password_hash=?,password_reset_token_hash=NULL,password_reset_expires_at=NULL WHERE id=? AND role=?',[password_hash($password,PASSWORD_DEFAULT),$account['id'],'teacher']);
        flash('Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');
        redirect('login');
    }

    if ($action === 'logout') {
        $studentId=(int)($_SESSION['user_id']??0);$studentToken=(string)($_SESSION['student_session_token']??'');
        if($studentId>0&&$studentToken!=='')close_student_session(db(),$studentId,$studentToken);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('login');
    }

    if ($action === 'register') {
        $language = normalize_language((string)($_POST['language'] ?? '')) ?? current_language();
        set_login_language($language);
        use_language($language);
        $role = ($_POST['role'] ?? 'student') === 'teacher' ? 'teacher' : 'student';
        $requestedCourseCode=$role==='student'?trim((string)($_POST['course_code']??'')):'';
        if($requestedCourseCode!=='')$_SESSION['pending_join_course_code']=$requestedCourseCode;
        registration_guard($role);
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = mb_strtoupper(trim((string)($_POST['last_name'] ?? '')), 'UTF-8');
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
        $phone = trim((string)($_POST['phone'] ?? '')) ?: null;
        $compactFirstName = preg_replace('/\s+/u', '', $firstName) ?? '';
        $compactLastName = preg_replace('/\s+/u', '', $lastName) ?? '';
        if (mb_strlen($compactFirstName) < 2 || mb_strlen($compactLastName) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Prénom, nom et courriel valides sont requis.', 'error');
            redirect('register', ['role'=>$role]);
        }
        $existing = one('SELECT * FROM users WHERE lower(email)=?', [$email]);
        if ($existing) {
            if ($existing['account_status'] === 'pending') {
                issue_account_verification((int)$existing['id'],$existing['email'],$existing['first_name'],$existing['login_code'],$existing['language'] ?? $language);
            }
            flash('Si cette adresse peut être inscrite, un courriel de validation valable 15 minutes a été envoyé.');
            redirect('login');
        }

        $classGroup = $role === 'student' ? trim((string)($_POST['class_group'] ?? '')) : '';
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirmation = (string)($_POST['password_confirm'] ?? '');
        $requestedIdentifier = mb_strtolower(trim((string)($_POST['identifier'] ?? '')), 'UTF-8');
        $courseTitle = trim((string)($_POST['course_title'] ?? ''));
        $joinCourse=$requestedCourseCode!==''?find_joinable_course(db(),$requestedCourseCode):null;
        if ($role === 'student' && $classGroup === '') {
            flash('Le groupe classe est requis pour un compte élève.', 'error');
            redirect('register', ['role'=>'student']);
        }
        if($role==='student'&&$requestedCourseCode!==''&&!$joinCourse){
            flash('Ce code ne correspond à aucun cours disponible.', 'error');
            redirect('register',['role'=>'student','course_code'=>$requestedCourseCode]);
        }
        if ($role === 'teacher') {
            if (!preg_match('/^[\p{L}\p{N}._-]{3,40}$/u', $requestedIdentifier)) {
                flash('Choisissez un identifiant de 3 à 40 caractères, sans espace.', 'error');
                redirect('register', ['role'=>'teacher']);
            }
            if (mb_strlen($password) < 8 || $password !== $passwordConfirmation) {
                flash('Le mot de passe doit contenir au moins 8 caractères et sa confirmation doit correspondre.', 'error');
                redirect('register', ['role'=>'teacher']);
            }
            if ($courseTitle === '') {
                flash('Indiquez le nom de votre premier cours.', 'error');
                redirect('register', ['role'=>'teacher']);
            }
        }

        $baseCode = $role === 'student' ? student_code($firstName, $lastName) : $requestedIdentifier;
        $code = unique_login_code($baseCode, $role === 'student');
        $initials = mb_strtoupper(mb_substr($compactFirstName,0,1).mb_substr($compactLastName,0,1),'UTF-8');
        $colors = ['#ef6a8a','#2da58d','#e49b35','#4178d0','#7f62d9'];
        db()->beginTransaction();
        try {
            run("INSERT INTO users(name,first_name,last_name,email,role,initials,color,class_group,phone,login_code,password_hash,account_status,pending_course_title,language,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,'pending',?,?,CURRENT_TIMESTAMP)", [
                $firstName.' '.$lastName,$firstName,$lastName,$email,$role,$initials,
                $colors[abs(crc32($email))%count($colors)],$classGroup,$phone,$code,
                $role === 'teacher' ? password_hash($password, PASSWORD_DEFAULT) : null,
                $role === 'teacher' ? $courseTitle : null,$language,
            ]);
            $newUserId = (int)db()->lastInsertId();
            if($joinCourse)run('INSERT INTO enrollments(course_id,student_id) VALUES(?,?)',[$joinCourse['id'],$newUserId]);
            db()->commit();
            if($joinCourse)unset($_SESSION['pending_join_course_code']);
        } catch (Throwable $exception) {
            db()->rollBack();
            if (is_pending_registration_limit($exception)) {
                flash('Le plafond de comptes en attente est atteint. Réessayez après leur validation ou leur expiration.', 'error');
                redirect('register', ['role'=>$role]);
            }
            throw $exception;
        }
        $sent = issue_account_verification($newUserId,$email,$firstName,$code,$language);
        flash(t($sent
            ? 'Un courriel de validation a été envoyé. Le lien reste valable 15 minutes.'
            : 'Votre demande est enregistrée pour 15 minutes. Le courriel de validation est dans la boîte d’envoi et doit être expédié par le service mail.'));
        redirect('login');
    }

    $user = require_actor();
    if($action==='join_course'&&$user['role']==='student'){
        $courseCode=trim((string)($_POST['course_code']??$_SESSION['pending_join_course_code']??''));
        $result=enroll_student_with_course_code(db(),(int)$user['id'],$courseCode);
        $course=$result['course'];
        if($result['status']==='joined'){
            unset($_SESSION['pending_join_course_code']);
            $teacherLanguage=normalize_language((string)($course['teacher_language']??''))??'fr';
            enqueue('student.self_enrolled',(string)$course['teacher_email'],t('Nouvel élève dans votre cours liike',[],$teacherLanguage),
                t(':student a rejoint le cours « :course » avec son code d’invitation.',['student'=>$user['name'],'course'=>$course['title']],$teacherLanguage));
            $_SESSION['flash']['message']=t('Vous avez rejoint le cours « :course ».',['course'=>$course['title']]);
            $_SESSION['flash']['kind']='success';
            redirect('student',['course'=>$course['id']]);
        }
        if($result['status']==='already_joined'){
            unset($_SESSION['pending_join_course_code']);
            flash('Vous êtes déjà inscrit·e à ce cours.');
            redirect('student',['course'=>$course['id']]);
        }
        if($result['status']==='archived'){
            unset($_SESSION['pending_join_course_code']);
            flash('Votre ancienne participation à ce cours est archivée. Contactez votre enseignant pour la réactiver.','error');
        }else flash('Ce code ne correspond à aucun cours disponible.','error');
        redirect('join');
    }
    if ($action === 'track_learning_activity') {
        $tracked=$user['role']==='student' && record_learning_activity(
            db(),
            (int)$user['id'],
            (int)($_POST['item_id']??0),
            (string)($_POST['visit_token']??''),
            (int)($_POST['active_seconds']??0)
        );
        http_response_code($tracked ? 204 : 403);
        exit;
    }
    if ($action === 'acquire_edit_lock' && $user['role'] === 'teacher') {
        $result=acquire_edit_lock(db(),(string)($_POST['entity_type']??''),(int)($_POST['entity_id']??0),(int)$user['id']);
        http_response_code($result['ok']?200:423);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }
    if ($action === 'release_edit_lock' && $user['role'] === 'teacher') {
        release_edit_locks(db(),(int)$user['id'],(string)($_POST['entity_type']??''),(int)($_POST['entity_id']??0));
        http_response_code(204);exit;
    }
    if ($action === 'add_course_teacher' && $user['role'] === 'teacher') {
        $courseId=(int)($_POST['course_id']??0);$teacherId=(int)($_POST['teacher_id']??0);
        $candidate=one("SELECT id FROM users WHERE id=? AND role='teacher' AND account_status='active'",[$teacherId]);
        if(!teacher_owns_course(db(),$courseId,(int)$user['id'])||!$candidate||$teacherId===(int)$user['id'])flash('Enseignant ou parcours invalide.','error');
        else{run('INSERT OR IGNORE INTO course_teachers(course_id,teacher_id,added_by) VALUES(?,?,?)',[$courseId,$teacherId,$user['id']]);flash('Enseignant ajouté au parcours.');}
        redirect('pathway',['course'=>$courseId]);
    }
    if ($action === 'remove_course_teacher' && $user['role'] === 'teacher') {
        $courseId=(int)($_POST['course_id']??0);$teacherId=(int)($_POST['teacher_id']??0);
        $member=one('SELECT 1 FROM course_teachers WHERE course_id=? AND teacher_id=?',[$courseId,$teacherId]);
        if(!teacher_owns_course(db(),$courseId,(int)$user['id'])||!$member)flash('Seul le créateur du parcours peut gérer les enseignants.','error');
        else{
            $pageIds=array_map('intval',array_column(all('SELECT DISTINCT page_id FROM pathway_items WHERE course_id=?',[$courseId]),'page_id'));
            run('DELETE FROM course_teachers WHERE course_id=? AND teacher_id=?',[$courseId,$teacherId]);
            run("DELETE FROM edit_locks WHERE teacher_id=? AND entity_type='course_structure' AND entity_id=?",[$teacherId,$courseId]);
            run("DELETE FROM edit_locks WHERE teacher_id=? AND entity_type='pathway_item' AND entity_id IN (SELECT id FROM pathway_items WHERE course_id=?)",[$teacherId,$courseId]);
            foreach($pageIds as $pageId)if(!teacher_can_access_page(db(),$pageId,$teacherId)){
                run("DELETE FROM edit_locks WHERE teacher_id=? AND entity_type='page_metadata' AND entity_id=?",[$teacherId,$pageId]);
                run("DELETE FROM edit_locks WHERE teacher_id=? AND entity_type='page_block' AND entity_id IN (SELECT id FROM page_blocks WHERE page_id=?)",[$teacherId,$pageId]);
            }
            flash('Enseignant retiré du parcours.');
        }
        redirect('pathway',['course'=>$courseId]);
    }
    if ($action === 'add_collaboration_comment' && $user['role'] === 'teacher') {
        $type=($_POST['subject_type']??'')==='page'?'page':'course';$subjectId=(int)($_POST['subject_id']??0);$body=trim((string)($_POST['body']??''));
        if($body===''||mb_strlen($body)>3000||!teacher_can_access_subject(db(),$type,$subjectId,(int)$user['id']))flash('Le commentaire ne peut pas être ajouté.','error');
        else{run('INSERT INTO collaboration_comments(subject_type,subject_id,author_id,body) VALUES(?,?,?,?)',[$type,$subjectId,$user['id'],$body]);flash('Commentaire ajouté.');}
        redirect($type==='page'?'page-edit':'pathway',$type==='page'?['id'=>$subjectId]:['course'=>$subjectId]);
    }
    if ($action === 'resolve_collaboration_comment' && $user['role'] === 'teacher') {
        $commentId=(int)($_POST['comment_id']??0);$comment=one('SELECT * FROM collaboration_comments WHERE id=? AND status=?',[$commentId,'open']);
        if(!$comment||!teacher_can_access_subject(db(),(string)$comment['subject_type'],(int)$comment['subject_id'],(int)$user['id']))flash('Commentaire introuvable.','error');
        else{run("UPDATE collaboration_comments SET status='resolved',resolved_by=?,resolved_at=CURRENT_TIMESTAMP WHERE id=?",[$user['id'],$commentId]);flash('Commentaire traité et archivé dans l’historique.');}
        $type=(string)($comment['subject_type']??($_POST['subject_type']??'course'));$subjectId=(int)($comment['subject_id']??($_POST['subject_id']??0));
        redirect($type==='page'?'page-edit':'pathway',$type==='page'?['id'=>$subjectId]:['course'=>$subjectId]);
    }
    if ($action === 'export_page' && $user['role'] === 'teacher') {
        try{$document=export_page_document(db(),(int)($_POST['page_id']??0),(int)$user['id'],APR_PUBLIC_ROOT);send_json_download($document,'liike-page-'.($document['page']['reference']??'export').'.json');}
        catch(Throwable $exception){flash(import_failure_message($exception),'error');redirect('library');}
    }
    if ($action === 'import_page' && $user['role'] === 'teacher') {
        try{$pageId=import_page_document(db(),uploaded_json('import_file'),(int)$user['id'],($_POST['mode']??'copy')==='overwrite'?'overwrite':'copy',APR_PUBLIC_ROOT);flash('Page importée avec succès.');redirect('page-edit',['id'=>$pageId]);}
        catch(Throwable $exception){flash(import_failure_message($exception),'error');redirect('library');}
    }
    if ($action === 'export_course' && $user['role'] === 'teacher') {
        try{$document=export_course_document(db(),(int)($_POST['course_id']??0),(int)$user['id'],isset($_POST['include_options']));send_json_download($document,'liike-parcours-'.($document['course']['reference']??'export').'.json');}
        catch(Throwable $exception){flash(import_failure_message($exception),'error');redirect('pathway');}
    }
    if ($action === 'export_course_pdf' && $user['role'] === 'teacher') {
        try{$courseId=(int)($_POST['course_id']??0);$course=one('SELECT title FROM courses WHERE id=?',[$courseId]);if(!$course||!teacher_can_access_course(db(),$courseId,(int)$user['id']))throw new TransferException('Parcours introuvable.');send_pdf_download(course_pdf_html(db(),$courseId,(int)$user['id']),'parcours-'.mb_strtolower((string)$course['title'],'UTF-8').'.pdf');}
        catch(Throwable $exception){flash(import_failure_message($exception),'error');redirect('pathway');}
    }
    if ($action === 'export_pathway_page_pdf' && $user['role'] === 'teacher') {
        try{$itemId=(int)($_POST['item_id']??0);$item=one('SELECT p.title,pi.course_id FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE pi.id=?',[$itemId]);if(!$item||!teacher_can_access_course(db(),(int)$item['course_id'],(int)$user['id']))throw new TransferException('Étape introuvable.');send_pdf_download(pathway_page_pdf_html(db(),$itemId,(int)$user['id']),'etape-'.mb_strtolower((string)$item['title'],'UTF-8').'.pdf');}
        catch(Throwable $exception){flash(import_failure_message($exception),'error');redirect('pathway');}
    }
    if ($action === 'import_course' && $user['role'] === 'teacher') {
        try{$courseId=import_course_document(db(),uploaded_json('import_file'),(int)$user['id'],($_POST['mode']??'copy')==='overwrite'?'overwrite':'copy',isset($_POST['reset_deadlines']));flash('Parcours importé avec succès.');redirect('pathway',['course'=>$courseId]);}
        catch(Throwable $exception){flash(import_failure_message($exception),'error');redirect('pathway');}
    }
    if ($action === 'export_students' && $user['role'] === 'teacher') {
        try{send_json_download(export_students_document(db(),(int)$user['id']),'liike-eleves.json');}
        catch(Throwable $exception){flash(import_failure_message($exception),'error');redirect('students');}
    }
    if ($action === 'import_students' && $user['role'] === 'teacher') {
        try{
            $document=uploaded_json('import_file');$validated=validate_students_document(db(),$document,(int)$user['id']);$newCount=0;
            foreach($validated as $student)if(!one('SELECT id FROM users WHERE lower(email)=?',[$student['email']]))$newCount++;
            if(!reserve_imported_accounts($newCount))throw new TransferException('Import arrêté : le plafond de créations ou de comptes en attente serait dépassé.');
            $result=import_students_document(db(),$document,(int)$user['id'],($_POST['mode']??'update')==='overwrite'?'overwrite':'update');
            foreach($result['created'] as $created)issue_account_verification((int)$created['id'],$created['email'],$created['first_name'],$created['code'],$created['language']??current_language());
            flash(t(':processed élève(s) importé(s), dont :created nouveau(x) compte(s) à valider par courriel.',['processed'=>$result['processed'],'created'=>count($result['created'])]));redirect('students');
        }catch(Throwable $exception){flash(import_failure_message($exception),'error');redirect('students');}
    }
    if ($action === 'superadmin_delete' && $user['role'] === 'teacher' && (int)($user['is_superadmin']??0)===1) {
        $entity=(string)($_POST['entity']??'');$id=(int)($_POST['id']??0);$deleted=false;
        if($entity==='user'){$deleted=superadmin_delete_user(db(),$id);}
        elseif($entity==='page'){$deleted=superadmin_delete_page(db(),$id);}
        elseif($entity==='course'){$deleted=superadmin_delete_course(db(),$id);}
        flash($deleted?'Suppression superadmin terminée.':'Suppression refusée ou élément introuvable.',$deleted?'success':'error');redirect('admin');
    }
    if (in_array($action, ['update_profile','update_teacher_profile'], true)) {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = mb_strtoupper(trim((string)($_POST['last_name'] ?? '')), 'UTF-8');
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
        $phoneInput = preg_replace('/\s+/u', ' ', trim((string)($_POST['phone'] ?? ''))) ?? '';
        $phone = $phoneInput !== '' ? $phoneInput : null;
        $identifier = $user['role'] === 'teacher' ? mb_strtolower(trim((string)($_POST['identifier'] ?? '')), 'UTF-8') : (string)$user['login_code'];
        $language = normalize_language((string)($_POST['language'] ?? ''))
            ?? normalize_language((string)($user['language'] ?? ''))
            ?? current_language();
        $newPassword = (string)($_POST['new_password'] ?? '');
        $passwordConfirmation = (string)($_POST['new_password_confirm'] ?? '');
        $compactFirstName = preg_replace('/\s+/u', '', $firstName) ?? '';
        $compactLastName = preg_replace('/\s+/u', '', $lastName) ?? '';

        if (mb_strlen($compactFirstName) < 2 || mb_strlen($compactLastName) < 2) {
            flash(t('Le prénom et le nom doivent contenir au moins 2 caractères hors espaces.'), 'error');
            redirect('profile');
        }
        if (mb_strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash(t('Indiquez une adresse courriel valide.'), 'error');
            redirect('profile');
        }
        if (one('SELECT id FROM users WHERE lower(email)=? AND id<>?', [$email,$user['id']])) {
            flash(t('Ce courriel est déjà utilisé.'), 'error');
            redirect('profile');
        }
        if ($phone !== null && mb_strlen($phone) > 40) {
            flash(t('Le numéro de téléphone ne peut pas dépasser 40 caractères.'), 'error');
            redirect('profile');
        }
        if ($user['role'] === 'teacher' && !preg_match('/^[\p{L}\p{N}._-]{3,40}$/u', $identifier)) {
            flash(t('L’identifiant doit contenir 3 à 40 caractères, sans espace.'), 'error');
            redirect('profile');
        }
        if ($user['role'] === 'teacher' && one('SELECT id FROM users WHERE lower(login_code)=? AND id<>?', [$identifier,$user['id']])) {
            flash(t('Cet identifiant est déjà utilisé.'), 'error');
            redirect('profile');
        }
        if ($user['role'] === 'teacher' && $newPassword !== '' && mb_strlen($newPassword) < 8) {
            flash(t('Le nouveau mot de passe doit contenir au moins 8 caractères.'), 'error');
            redirect('profile');
        }
        if ($user['role'] === 'teacher' && $newPassword !== $passwordConfirmation) {
            flash(t('La confirmation du nouveau mot de passe ne correspond pas.'), 'error');
            redirect('profile');
        }

        $initials = mb_strtoupper(mb_substr($compactFirstName,0,1).mb_substr($compactLastName,0,1),'UTF-8');
        $params = [$firstName.' '.$lastName,$firstName,$lastName,$initials,$email,$phone,$identifier,$language];
        $sql = 'UPDATE users SET name=?,first_name=?,last_name=?,initials=?,email=?,phone=?,login_code=?,language=?';
        if ($user['role'] === 'teacher' && $newPassword !== '') {
            $sql .= ',password_hash=?';
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id=? AND role=?';
        $params[] = $user['id'];
        $params[] = $user['role'];
        run($sql, $params);
        $savedProfile = one('SELECT email,phone,language FROM users WHERE id=? AND role=?', [$user['id'],$user['role']]);
        if (!$savedProfile
            || normalize_language((string)($savedProfile['language'] ?? '')) !== $language
            || mb_strtolower((string)$savedProfile['email'],'UTF-8') !== $email
            || ($savedProfile['phone'] ?? null) !== $phone) {
            flash(t('La mise à jour du profil a échoué.'), 'error');
            redirect('profile');
        }
        use_language($language);
        flash(t('Votre profil a été mis à jour.'));
        redirect('profile');
    }

    if ($action === 'student_validate' && $user['role'] === 'student') {
        $itemId = (int) $_POST['item_id'];
        $level = max(0, min(3, (int) $_POST['level']));
        $item = one("SELECT pi.*,c.teacher_id,c.title AS course_title,p.title AS page_title,e.id AS enrollment_id,u.email AS teacher_email,u.language AS teacher_language
            FROM pathway_items pi JOIN courses c ON c.id=pi.course_id JOIN pages p ON p.id=pi.page_id
            JOIN enrollments e ON e.course_id=c.id AND e.student_id=? AND e.status='active' JOIN users u ON u.id=c.teacher_id WHERE pi.id=?
            AND (NOT EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id)
              OR EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=?))", [$user['id'],$itemId,$user['id']]);
        if (!$item) { flash('Étape introuvable.', 'error'); redirect('student'); }
        run('INSERT INTO progress(enrollment_id,pathway_item_id,student_level,student_note,student_validated_at,updated_at)
            VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
            ON CONFLICT(enrollment_id,pathway_item_id) DO UPDATE SET student_level=excluded.student_level,student_note=excluded.student_note,student_validated_at=CURRENT_TIMESTAMP,teacher_level=NULL,teacher_note="",teacher_validated_at=NULL,updated_at=CURRENT_TIMESTAMP',
            [$item['enrollment_id'],$itemId,$level,trim((string)($_POST['note'] ?? ''))]);
        $teacherLanguage=normalize_language((string)($item['teacher_language']??''))??'fr';
        enqueue('student.validated', $item['teacher_email'], t(':name a terminé « :page »',['name'=>$user['name'],'page'=>$item['page_title']],$teacherLanguage),
            t(':name s’auto-positionne au niveau :level. Une confirmation est attendue.',['name'=>$user['name'],'level'=>$level],$teacherLanguage));
        flash('Étape envoyée à votre enseignant. Bravo pour ce pas !');
        redirect('learn', ['item'=>$itemId]);
    }

    if ($action === 'teacher_validate' && $user['role'] === 'teacher') {
        $enrollmentId = (int) $_POST['enrollment_id'];
        $itemId = (int) $_POST['item_id'];
        $level = max(0, min(3, (int) $_POST['level']));
        $context = one("SELECT e.*,s.email,s.name,s.language,pi.course_id,p.title AS page_title,c.title AS course_title
            FROM enrollments e JOIN users s ON s.id=e.student_id JOIN pathway_items pi ON pi.id=? AND pi.course_id=e.course_id
            JOIN pages p ON p.id=pi.page_id JOIN courses c ON c.id=e.course_id WHERE e.id=? AND e.status='active' AND s.account_status='active'
            AND (NOT EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id) OR EXISTS(SELECT 1 FROM pathway_item_students a WHERE a.pathway_item_id=pi.id AND a.student_id=s.id))", [$itemId,$enrollmentId]);
        if (!$context || !teacher_can_access_course(db(),(int)$context['course_id'],(int)$user['id'])) { flash('Validation impossible.', 'error'); redirect('teacher'); }
        run('INSERT INTO progress(enrollment_id,pathway_item_id,teacher_level,teacher_note,teacher_validated_at,updated_at)
            VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
            ON CONFLICT(enrollment_id,pathway_item_id) DO UPDATE SET teacher_level=excluded.teacher_level,teacher_note=excluded.teacher_note,teacher_validated_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP',
            [$enrollmentId,$itemId,$level,trim((string)($_POST['note'] ?? ''))]);
        $rewardId = (int) ($_POST['reward_type_id'] ?? 0);
        if ($rewardId > 0) {
            $reward = one('SELECT * FROM reward_types WHERE id=? AND course_id=? AND active=1', [$rewardId,$context['course_id']]);
            if ($reward) {
                $points = max(1, min(100, (int)($_POST['points'] ?? $reward['default_points'])));
                $message = trim((string)($_POST['reward_message'] ?? ''));
                run('INSERT INTO reward_awards(enrollment_id,pathway_item_id,reward_type_id,points,message,awarded_by) VALUES(?,?,?,?,?,?)', [$enrollmentId,$itemId,$rewardId,$points,$message,$user['id']]);
                $studentLanguage=normalize_language((string)($context['language']??''))??'fr';
                enqueue('reward.awarded', $context['email'], $reward['icon'].' '.t('Un encouragement pour votre travail',[],$studentLanguage), $reward['name']." · +$points ".t('points',[],$studentLanguage)."\n".$message);
            }
        }
        $teacherNote = trim((string)($_POST['note'] ?? ''));
        $studentLanguage=normalize_language((string)($context['language']??''))??'fr';
        enqueue('teacher.confirmed', $context['email'], t('Votre étape « :page » est confirmée',['page'=>$context['page_title']],$studentLanguage), t('Niveau confirmé : :level/3.',['level'=>$level],$studentLanguage).($teacherNote!==''?"\n\n".t('Note / Commentaire',[],$studentLanguage).' : '.$teacherNote:''));
        flash(t($rewardId ? 'Niveau confirmé et encouragement attribué.' : 'Niveau confirmé.'));
        redirect('student-detail', ['enrollment'=>$enrollmentId]);
    }

    if ($action === 'save_page' && $user['role'] === 'teacher') {
        $pageId = (int) ($_POST['page_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') { flash('Le titre est obligatoire.', 'error'); redirect('library'); }
        $summary=trim((string)($_POST['summary']??''));$status=($_POST['status']??'draft')==='ready'?'ready':'draft';$minutes=max(1,(int)($_POST['estimated_minutes']??15));
        $tagIds=array_values(array_unique(array_map('intval',(array)($_POST['tags']??[]))));
        $objectiveTitles=(array)($_POST['page_objective_title']??[]);$objectiveDescriptions=(array)($_POST['page_objective_description']??[]);$pageObjectives=[];$seenObjectives=[];
        foreach($objectiveTitles as $index=>$objectiveTitle){$objectiveTitle=trim((string)$objectiveTitle);if($objectiveTitle==='')continue;$key=mb_strtolower($objectiveTitle,'UTF-8');if(isset($seenObjectives[$key]))continue;$seenObjectives[$key]=true;$pageObjectives[]=['title'=>$objectiveTitle,'description'=>trim((string)($objectiveDescriptions[$index]??''))];if(count($pageObjectives)>=50)break;}
        $conflicts=[];$changed=false;
        db()->beginTransaction();
        try {
            if ($pageId) {
                $storedPage=one('SELECT * FROM pages WHERE id=?',[$pageId]);
                if(!$storedPage||!teacher_can_access_page(db(),$pageId,(int)$user['id']))throw new RuntimeException('Page introuvable.');
                $storedTags=array_map('intval',array_column(tags_for_page($pageId),'id'));sort($storedTags);$submittedTags=$tagIds;sort($submittedTags);
                $storedObjectives=array_map(static fn(array $objective):array=>['title'=>$objective['title'],'description'=>$objective['description']],page_objectives(db(),$pageId));
                $metadataChanged=$title!==$storedPage['title']||$summary!==$storedPage['summary']||$status!==$storedPage['status']||$minutes!==(int)$storedPage['estimated_minutes']||$storedTags!==$submittedTags||$storedObjectives!==$pageObjectives;
                if($metadataChanged){
                    $revision=(int)($_POST['page_revision']??-1);
                    if(edit_lock_allows(db(),'page_metadata',$pageId,(int)$user['id'])&&$revision===(int)$storedPage['revision']){
                        $update=db()->prepare('UPDATE pages SET title=?,summary=?,status=?,estimated_minutes=?,updated_at=CURRENT_TIMESTAMP,updated_by=?,revision=revision+1 WHERE id=? AND revision=?');
                        $update->execute([$title,$summary,$status,$minutes,$user['id'],$pageId,$revision]);
                        if($update->rowCount()===1){run('DELETE FROM page_tags WHERE page_id=?',[$pageId]);foreach($tagIds as $tagId)run('INSERT OR IGNORE INTO page_tags VALUES(?,?)',[$pageId,$tagId]);run('DELETE FROM page_objectives WHERE page_id=?',[$pageId]);foreach($pageObjectives as $position=>$objective)run('INSERT INTO page_objectives(page_id,title,description,position) VALUES(?,?,?,?)',[$pageId,$objective['title'],$objective['description'],$position+1]);$changed=true;}
                        else $conflicts[]=t('Réglages de la page');
                    }else $conflicts[]=t('Réglages de la page');
                }
            } else {
                run('INSERT INTO pages(reference,title,summary,status,estimated_minutes,owner_id,updated_by) VALUES(?,?,?,?,?,?,?)', [new_entity_reference('PAGE'),$title,$summary,$status,$minutes,$user['id'],$user['id']]);
                $pageId = (int) db()->lastInsertId();
                foreach($tagIds as $tagId)run('INSERT OR IGNORE INTO page_tags VALUES(?,?)',[$pageId,$tagId]);
                foreach($pageObjectives as $position=>$objective)run('INSERT INTO page_objectives(page_id,title,description,position) VALUES(?,?,?,?)',[$pageId,$objective['title'],$objective['description'],$position+1]);
                $changed=true;
            }
            $types = $_POST['block_type'] ?? [];
            $bodies = $_POST['block_body'] ?? [];
            $captions = $_POST['block_caption'] ?? [];
            $blockIds=$_POST['block_id']??[];$blockRevisions=$_POST['block_revision']??[];
            foreach ($types as $i => $type) {
                if (!in_array($type, ['markdown','image','file','iframe'], true)) continue;
                $body = trim((string)($bodies[$i] ?? ''));
                if (isset($_FILES['block_file']['error'][$i]) && $_FILES['block_file']['error'][$i] === UPLOAD_ERR_OK) {
                    $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', basename((string)$_FILES['block_file']['name'][$i]));
                    $safe = date('YmdHis') . '-' . $safe;
                    move_uploaded_file($_FILES['block_file']['tmp_name'][$i], APR_PUBLIC_ROOT . '/uploads/' . $safe);
                    $body = 'uploads/' . $safe;
                }
                if($body==='')continue;$caption=trim((string)($captions[$i]??''));$blockId=(int)($blockIds[$i]??0);$revision=(int)($blockRevisions[$i]??0);
                if($blockId>0){
                    $stored=one('SELECT * FROM page_blocks WHERE id=? AND page_id=?',[$blockId,$pageId]);
                    if(!$stored){$conflicts[]=t('Bloc introuvable');continue;}
                    if($type===$stored['type']&&$body===$stored['body']&&$caption===$stored['caption'])continue;
                    if(!edit_lock_allows(db(),'page_block',$blockId,(int)$user['id'])||$revision!==(int)$stored['revision']){$conflicts[]=t('Bloc :number',['number'=>(int)$stored['position']]);continue;}
                    $update=db()->prepare('UPDATE page_blocks SET type=?,body=?,caption=?,revision=revision+1,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND page_id=? AND revision=?');
                    $update->execute([$type,$body,$caption,$user['id'],$blockId,$pageId,$revision]);
                    if($update->rowCount()===1)$changed=true;else $conflicts[]=t('Bloc :number',['number'=>(int)$stored['position']]);
                }else{
                    $position=(int)(one('SELECT COALESCE(MAX(position),0)+1 AS n FROM page_blocks WHERE page_id=?',[$pageId])['n']??1);
                    run('INSERT INTO page_blocks(page_id,type,body,caption,position,updated_by) VALUES(?,?,?,?,?,?)',[$pageId,$type,$body,$caption,$position,$user['id']]);$changed=true;
                }
            }
            $deletedIds=(array)($_POST['deleted_block_id']??[]);$deletedRevisions=(array)($_POST['deleted_block_revision']??[]);
            foreach($deletedIds as $i=>$deletedId){$blockId=(int)$deletedId;$revision=(int)($deletedRevisions[$i]??-1);$stored=one('SELECT position,revision FROM page_blocks WHERE id=? AND page_id=?',[$blockId,$pageId]);if(!$stored)continue;if(!edit_lock_allows(db(),'page_block',$blockId,(int)$user['id'])||$revision!==(int)$stored['revision']){$conflicts[]=t('Bloc :number',['number'=>(int)$stored['position']]);continue;}$delete=db()->prepare('DELETE FROM page_blocks WHERE id=? AND page_id=? AND revision=?');$delete->execute([$blockId,$pageId,$revision]);if($delete->rowCount()===1)$changed=true;}
            if($changed)run('UPDATE pages SET updated_at=CURRENT_TIMESTAMP,updated_by=? WHERE id=?',[$user['id'],$pageId]);
            db()->commit();
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
        release_edit_locks(db(),(int)$user['id']);
        $recipients=$changed?all("SELECT DISTINCT u.email,u.language FROM pathway_items pi JOIN enrollments e ON e.course_id=pi.course_id JOIN users u ON u.id=e.student_id WHERE pi.page_id=? AND e.status='active' AND u.account_status='active'",[$pageId]):[];
        foreach($recipients as $recipient){$recipientLanguage=normalize_language((string)($recipient['language']??''))??'fr';enqueue('page.updated',$recipient['email'],t('Une ressource de votre parcours a changé',[],$recipientLanguage),t('La page « :page » vient d’être mise à jour.',['page'=>$title],$recipientLanguage));}
        flash($conflicts?t('Enregistrement partiel : :parts modifié(s) ailleurs ou verrouillé(s).',['parts'=>implode(', ',array_unique($conflicts))]):t($recipients?'Page enregistrée et notifications préparées.':'Page enregistrée.'),$conflicts?'error':'success');
        redirect('page-edit', ['id'=>$pageId]);
    }

    if ($action === 'delete_page' && $user['role'] === 'teacher') {
        $pageId = (int)($_POST['page_id'] ?? 0);
        if (delete_unused_page(db(),$pageId,(int)$user['id'])) {
            flash('Page supprimée définitivement.');
        } else {
            flash('Cette page est encore utilisée dans un parcours ou ne vous appartient pas.', 'error');
        }
        redirect('library');
    }

    if ($action === 'add_pathway_item' && $user['role'] === 'teacher') {
        $courseId = (int)$_POST['course_id']; $pageId = (int)$_POST['page_id'];
        $deadlineInput=trim((string)($_POST['deadline']??''));$deadline=database_date_from_input($deadlineInput);
        if($deadlineInput!==''&&$deadline===null){flash('Saisissez la date au format jj/mm/aaaa.','error');redirect('pathway',['course'=>$courseId]);}
        $course = one('SELECT * FROM courses WHERE id=?', [$courseId]);
        $page = one('SELECT id FROM pages WHERE id=? AND status=?', [$pageId,'ready']);
        if($course&&!teacher_can_access_course(db(),$courseId,(int)$user['id']))$course=null;
        if($page&&!teacher_can_access_page(db(),$pageId,(int)$user['id']))$page=null;
        if ($course && $page) {
            $lock=acquire_edit_lock(db(),'course_structure',$courseId,(int)$user['id']);
            if(!$lock['ok'])flash(t('La structure du parcours est en cours de modification par :name.',['name'=>$lock['owner']??t('un autre enseignant')]),'error');
            else try{
                db()->beginTransaction();
                $position = (int)(one('SELECT COALESCE(MAX(position),0)+1 AS n FROM pathway_items WHERE course_id=?',[$courseId])['n']);
                run('INSERT INTO pathway_items(course_id,page_id,position,deadline,is_evaluation) VALUES(?,?,?,?,?)', [$courseId,$pageId,$position,$deadline,isset($_POST['is_evaluation'])?1:0]);
                db()->commit();flash('Page ajoutée au parcours.');
            }catch(Throwable $exception){if(db()->inTransaction())db()->rollBack();throw $exception;}
            finally{release_edit_locks(db(),(int)$user['id'],'course_structure',$courseId);}
        }
        redirect('pathway', ['course'=>$courseId]);
    }

    if ($action === 'remove_pathway_item' && $user['role'] === 'teacher') {
        $itemId=(int)($_POST['item_id']??0);$candidate=one('SELECT course_id FROM pathway_items WHERE id=?',[$itemId]);$courseId=null;$lockConflict=false;
        if($candidate&&teacher_can_access_course(db(),(int)$candidate['course_id'],(int)$user['id'])){
            $lock=acquire_edit_lock(db(),'course_structure',(int)$candidate['course_id'],(int)$user['id']);
            if($lock['ok']){try{$courseId=remove_pathway_item(db(),$itemId,(int)$user['id']);}finally{release_edit_locks(db(),(int)$user['id'],'course_structure',(int)$candidate['course_id']);}}
            else{$lockConflict=true;flash(t('La structure du parcours est en cours de modification par :name.',['name'=>$lock['owner']??t('un autre enseignant')]),'error');}
        }
        if(!$lockConflict)flash(t($courseId ? 'Page retirée du parcours. Les progressions et encouragements liés à cette étape ont été supprimés.' : 'Étape introuvable.'), $courseId ? 'success' : 'error');
        redirect('pathway', $courseId ? ['course'=>$courseId] : ($candidate?['course'=>(int)$candidate['course_id']]:[]));
    }

    if ($action === 'manage_course' && $user['role'] === 'teacher') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $operation = (string)($_POST['operation'] ?? '');
        $course = one('SELECT id FROM courses WHERE id=? AND teacher_id=?', [$courseId,$user['id']]);
        if (!$course) { flash('Parcours introuvable.', 'error'); redirect('pathway'); }
        if ($operation === 'archive') {
            run('UPDATE courses SET archived=1 WHERE id=?', [$courseId]);
            flash('Parcours archivé. Il est masqué aux élèves, sans perte de données.');
        } elseif ($operation === 'reactivate') {
            run('UPDATE courses SET archived=0 WHERE id=?', [$courseId]);
            flash('Parcours réactivé.');
        }
        redirect('pathway', $operation === 'reactivate' ? ['course'=>$courseId] : []);
    }

    if($action==='update_course_identity'&&$user['role']==='teacher'){
        $courseId=(int)($_POST['course_id']??0);
        $result=update_course_identity(db(),$courseId,(int)$user['id'],(string)($_POST['title']??''),(string)($_POST['code']??''));
        if($result==='updated')flash('Nom et code du parcours mis à jour.');
        elseif($result==='duplicate')flash('Ce code de parcours est déjà utilisé. Choisissez-en un autre.','error');
        elseif($result==='invalid')flash('Le nom est requis. Le code doit contenir 3 à 40 caractères : lettres sans accent, chiffres, point, tiret ou soulignement.','error');
        else flash('Seul le créateur du parcours peut modifier son nom et son code.','error');
        redirect('pathway',['course'=>$courseId]);
    }

    if ($action === 'copy_course' && $user['role'] === 'teacher') {
        $sourceId = (int)($_POST['course_id'] ?? 0);
        $newCourseId = copy_course(db(),$sourceId,(int)$user['id'],trim((string)($_POST['title'] ?? '')),isset($_POST['reset_deadlines']));
        if (!$newCourseId) { flash('Copie impossible : parcours ou titre invalide.', 'error'); redirect('pathway'); }
        flash(t(isset($_POST['reset_deadlines']) ? 'Parcours copié sans élèves ni progressions, avec toutes les échéances remises à zéro.' : 'Parcours copié sans élèves ni progressions.'));
        redirect('pathway', ['course'=>$newCourseId]);
    }

    if ($action === 'move_item' && $user['role'] === 'teacher') {
        $id=(int)$_POST['item_id']; $direction=$_POST['direction']==='up'?-1:1;
        $item=one('SELECT * FROM pathway_items WHERE id=?',[$id]);
        if ($item&&teacher_can_access_course(db(),(int)$item['course_id'],(int)$user['id'])&&acquire_edit_lock(db(),'course_structure',(int)$item['course_id'],(int)$user['id'])['ok']) {
            $other=one('SELECT * FROM pathway_items WHERE course_id=? AND position '.($direction<0?'<':'>').' ? ORDER BY position '.($direction<0?'DESC':'ASC').' LIMIT 1',[$item['course_id'],$item['position']]);
            if ($other) { run('UPDATE pathway_items SET position=-1 WHERE id=?',[$id]); run('UPDATE pathway_items SET position=? WHERE id=?',[$item['position'],$other['id']]); run('UPDATE pathway_items SET position=? WHERE id=?',[$other['position'],$id]); }
            release_edit_locks(db(),(int)$user['id'],'course_structure',(int)$item['course_id']);
            redirect('pathway',['course'=>$item['course_id']]);
        }
        flash($item?'La structure du parcours est momentanément verrouillée par un autre enseignant.':'Étape introuvable.','error');
        redirect('pathway',$item?['course'=>$item['course_id']]:[]);
    }
    if ($action === 'update_pathway_item' && $user['role'] === 'teacher') {
        $id=(int)$_POST['item_id'];
        $item=one('SELECT * FROM pathway_items WHERE id=?',[$id]);
        if ($item&&teacher_can_access_course(db(),(int)$item['course_id'],(int)$user['id'])) {
            $deadlineInput=trim((string)($_POST['deadline']??''));$deadline=database_date_from_input($deadlineInput);
            if($deadlineInput!==''&&$deadline===null){flash('Saisissez la date au format jj/mm/aaaa.','error');redirect('pathway',['course'=>$item['course_id'],'edit'=>$id]);}
            $revision=(int)($_POST['item_revision']??-1);
            if(!edit_lock_allows(db(),'pathway_item',$id,(int)$user['id'])||$revision!==(int)$item['revision']){flash('Cette étape a été modifiée ou est en cours d’édition par un autre enseignant.','error');redirect('pathway',['course'=>$item['course_id'],'edit'=>$id]);}
            $allowedStudents=[];
            if(($_POST['access_mode']??'all')==='restricted'){
                foreach(array_unique(array_map('intval',(array)($_POST['allowed_students']??[]))) as $studentId){
                    if(one("SELECT 1 FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.course_id=? AND e.student_id=? AND e.status='active' AND u.account_status='active'",[$item['course_id'],$studentId]))$allowedStudents[]=$studentId;
                }
                if(!$allowedStudents){flash('Sélectionnez au moins un élève pour limiter l’accès à cette étape.','error');redirect('pathway',['course'=>$item['course_id'],'edit'=>$id]);}
            }
            $update=db()->prepare('UPDATE pathway_items SET deadline=?,is_evaluation=?,instructions=?,revision=revision+1 WHERE id=? AND revision=?');
            $update->execute([$deadline,isset($_POST['is_evaluation'])?1:0,trim((string)$_POST['instructions']),$id,$revision]);
            if($update->rowCount()!==1){flash('Cette étape a été modifiée par un autre enseignant.','error');redirect('pathway',['course'=>$item['course_id'],'edit'=>$id]);}
            run('DELETE FROM item_skills WHERE pathway_item_id=?',[$id]);
            foreach ((array)($_POST['skills']??[]) as $v) run('INSERT INTO item_skills SELECT ?,id FROM course_skills WHERE id=? AND course_id=?',[$id,(int)$v,$item['course_id']]);
            run('DELETE FROM pathway_item_students WHERE pathway_item_id=?',[$id]);
            foreach($allowedStudents as $studentId)run('INSERT INTO pathway_item_students(pathway_item_id,student_id) VALUES(?,?)',[$id,$studentId]);
            release_edit_locks(db(),(int)$user['id'],'pathway_item',$id);
            flash('Étape mise à jour.');
            redirect('pathway',['course'=>$item['course_id'],'edit'=>$id]);
        }
    }
    if ($action === 'add_framework' && $user['role'] === 'teacher') {
        $courseId=(int)$_POST['course_id']; $kind='skill';
        if (teacher_can_access_course(db(),$courseId,(int)$user['id'])) {
            run('INSERT INTO course_skills(course_id,code,title,description,position) VALUES(?,?,?,?,99)',[$courseId,strtoupper(trim((string)$_POST['code'])),trim((string)$_POST['title']),trim((string)$_POST['description'])]);
            flash(t('Compétence ajoutée.'));
        }
        redirect('pathway',['course'=>$courseId]);
    }
    if ($action === 'remove_skill' && $user['role'] === 'teacher') {
        $skillId=(int)($_POST['skill_id']??0);$skill=one('SELECT id,course_id FROM course_skills WHERE id=?',[$skillId]);
        if(!$skill||!teacher_can_access_course(db(),(int)$skill['course_id'],(int)$user['id'])){flash('Compétence introuvable.','error');redirect('pathway');}
        run('DELETE FROM course_skills WHERE id=?',[$skillId]);
        flash('Compétence retirée du parcours.');
        redirect('pathway',['course'=>(int)$skill['course_id']]);
    }
    if ($action === 'add_reward_type' && $user['role'] === 'teacher') {
        $courseId=(int)$_POST['course_id'];
        if (teacher_can_access_course(db(),$courseId,(int)$user['id'])) {
            run('INSERT INTO reward_types(course_id,name,icon,color,default_points,active) VALUES(?,?,?,?,?,1) ON CONFLICT(course_id,name) DO UPDATE SET icon=excluded.icon,default_points=excluded.default_points,active=1',[$courseId,trim((string)$_POST['name']),trim((string)$_POST['icon'])?:'✨','#6d5dfc',max(1,(int)$_POST['default_points'])]);
            flash('Type d’encouragement ajouté.');
        }
        redirect('pathway',['course'=>$courseId]);
    }
    if ($action === 'remove_reward_type' && $user['role'] === 'teacher') {
        $rewardTypeId=(int)($_POST['reward_type_id']??0);$rewardType=one('SELECT id,course_id FROM reward_types WHERE id=?',[$rewardTypeId]);
        if(!$rewardType||!teacher_can_access_course(db(),(int)$rewardType['course_id'],(int)$user['id'])){flash('Type d’encouragement introuvable.','error');redirect('pathway');}
        run('UPDATE reward_types SET active=0 WHERE id=?',[$rewardTypeId]);
        flash('Type d’encouragement retiré du parcours. Les encouragements déjà attribués restent dans l’historique.');
        redirect('pathway',['course'=>(int)$rewardType['course_id']]);
    }
    if ($action === 'create_student' && $user['role'] === 'teacher') {
        if ((int)(one("SELECT COUNT(*) AS n FROM users WHERE account_status='pending'")['n'] ?? 0) >= REGISTRATION_PENDING_LIMIT) {
            flash('Le plafond de comptes en attente est atteint. Attendez leur validation ou leur expiration avant une nouvelle création.', 'error');
            redirect('students');
        }
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = mb_strtoupper(trim((string)($_POST['last_name'] ?? '')), 'UTF-8');
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
        $group = trim((string)($_POST['class_group'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? '')) ?: null;
        $compactFirstName = preg_replace('/\s+/u', '', $firstName) ?? '';
        $compactLastName = preg_replace('/\s+/u', '', $lastName) ?? '';
        if (mb_strlen($compactFirstName) < 2 || mb_strlen($compactLastName) < 3 || $group === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Prénom, nom, courriel et groupe classe sont requis. Le nom doit fournir au moins 3 caractères hors espaces.', 'error');
            redirect('students');
        }
        $code = unique_login_code(student_code($firstName, $lastName), true);
        if (one('SELECT id FROM users WHERE email=?', [$email])) {
            flash('Ce courriel est déjà utilisé.', 'error');
            redirect('students');
        }
        $courseIds = array_values(array_unique(array_map('intval', (array)($_POST['courses'] ?? []))));
        if (!$courseIds) {
            flash('Sélectionnez au moins un cours pour inscrire cet élève.', 'error');
            redirect('students');
        }
        $owned = all('SELECT id FROM courses WHERE teacher_id=? AND id IN (' . implode(',', array_fill(0,count($courseIds),'?')) . ')', array_merge([$user['id']],$courseIds));
        if (count($owned) !== count($courseIds)) {
            flash('Un cours sélectionné n’est pas disponible.', 'error');
            redirect('students');
        }
        $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $ipAttempts = (int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE ip_hash=? AND attempted_at>=datetime('now','-15 minutes')", [$ipHash])['n'] ?? 0);
        $globalAttempts = (int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE accepted=1 AND attempted_at>=datetime('now','-1 hour')")['n'] ?? 0);
        $dailyAttempts = (int)(one("SELECT COUNT(*) AS n FROM registration_attempts WHERE accepted=1 AND attempted_at>=datetime('now','-1 day')")['n'] ?? 0);
        $pendingAccounts = (int)(one("SELECT COUNT(*) AS n FROM users WHERE account_status='pending'")['n'] ?? 0);
        $creationAllowed = registration_is_allowed('',10,$ipAttempts,$globalAttempts,$dailyAttempts,$pendingAccounts);
        run('INSERT INTO registration_attempts(ip_hash,accepted) VALUES(?,?)', [$ipHash,$creationAllowed?1:0]);
        if (!$creationAllowed) {
            flash('Le plafond de créations est atteint. Attendez les validations ou expirations avant une nouvelle création.', 'error');
            redirect('students');
        }
        $initials = mb_strtoupper(mb_substr($firstName,0,1).mb_substr($compactLastName,0,1),'UTF-8');
        $colors = ['#ef6a8a','#2da58d','#e49b35','#4178d0','#7f62d9'];
        db()->beginTransaction();
        try {
            run("INSERT INTO users(name,first_name,last_name,email,role,initials,color,class_group,phone,login_code,account_status,created_at,managed_by) VALUES(?,?,?,?,'student',?,?,?,?,?,'pending',CURRENT_TIMESTAMP,?)", [$firstName.' '.$lastName,$firstName,$lastName,$email,$initials,$colors[abs(crc32($email))%count($colors)],$group,$phone,$code,$user['id']]);
            $studentId=(int)db()->lastInsertId();
            foreach($courseIds as $courseId) run('INSERT INTO enrollments(course_id,student_id) VALUES(?,?)',[$courseId,$studentId]);
            db()->commit();
        } catch(Throwable $e) {
            db()->rollBack();
            if (is_pending_registration_limit($e)) {
                flash('Le plafond de comptes en attente est atteint. Attendez leur validation ou leur expiration avant une nouvelle création.', 'error');
                redirect('students');
            }
            throw $e;
        }
        $sent=issue_account_verification($studentId,$email,$firstName,$code,current_language());
        flash(t($sent
            ? 'Élève préinscrit à :count cours. Validation du courriel requise sous 15 minutes ; message envoyé.'
            : 'Élève préinscrit à :count cours. Validation du courriel requise sous 15 minutes ; message placé dans la boîte d’envoi.', ['count'=>count($courseIds)]));
        redirect('students');
    }
    if ($action === 'enroll_students' && $user['role'] === 'teacher') {
        $courseId=(int)($_POST['course_id']??0);
        $course=one('SELECT * FROM courses WHERE id=? AND teacher_id=?',[$courseId,$user['id']]);
        $studentIds=array_values(array_unique(array_map('intval',(array)($_POST['students']??[]))));
        if(!$course || !$studentIds){ flash('Choisissez un cours et au moins un élève.','error'); redirect('students'); }
        $added=0;
        foreach($studentIds as $studentId){
            $student=one("SELECT * FROM users WHERE id=? AND role='student' AND account_status='active'",[$studentId]);
            if(!$student)continue;
            $exists=one('SELECT id,status FROM enrollments WHERE course_id=? AND student_id=?',[$courseId,$studentId]);
            if($exists && $exists['status']==='archived'){
                run("UPDATE enrollments SET status='active',archived_at=NULL WHERE id=?",[$exists['id']]);
                $studentLanguage=normalize_language((string)($student['language']??''))??'fr';enqueue('student.enrolled',$student['email'],t('Retour dans un cours liike',[],$studentLanguage),t('Votre participation au cours « :course » a été réactivée.',['course'=>$course['title']],$studentLanguage));
                $added++;
            } elseif(!$exists){
                run('INSERT INTO enrollments(course_id,student_id) VALUES(?,?)',[$courseId,$studentId]);
                $studentLanguage=normalize_language((string)($student['language']??''))??'fr';enqueue('student.enrolled',$student['email'],t('Nouveau cours dans liike',[],$studentLanguage),t('Vous avez été inscrit·e au cours « :course ».',['course'=>$course['title']],$studentLanguage));
                $added++;
            }
        }
        flash($added ? "$added inscription(s) ajoutée(s) au cours." : 'Les élèves sélectionnés étaient déjà inscrits.');
        redirect('students',['course'=>$courseId]);
    }
    if ($action === 'manage_enrollment' && $user['role'] === 'teacher') {
        $enrollmentId=(int)($_POST['enrollment_id']??0);
        $operation=(string)($_POST['operation']??'');
        $enrollment=one('SELECT e.*,c.title,u.email,u.account_status FROM enrollments e JOIN courses c ON c.id=e.course_id JOIN users u ON u.id=e.student_id WHERE e.id=? AND c.teacher_id=?',[$enrollmentId,$user['id']]);
        if(!$enrollment){ flash('Participation introuvable.','error'); redirect('students'); }
        if($operation==='archive'){
            run("UPDATE enrollments SET status='archived',archived_at=CURRENT_TIMESTAMP WHERE id=?",[$enrollmentId]);
            flash('La participation est archivée ; son historique est conservé.');
        } elseif($operation==='reactivate'){
            if($enrollment['account_status']!=='active'){ flash('Réactivez d’abord le compte de l’élève.','error'); redirect('students'); }
            run("UPDATE enrollments SET status='active',archived_at=NULL WHERE id=?",[$enrollmentId]);
            flash('La participation est réactivée.');
        } elseif($operation==='delete'){
            run('DELETE FROM learning_visits WHERE student_id=? AND pathway_item_id IN (SELECT id FROM pathway_items WHERE course_id=?)',[$enrollment['student_id'],$enrollment['course_id']]);
            run('DELETE FROM enrollments WHERE id=?',[$enrollmentId]);
            flash('La participation et son historique dans ce cours ont été supprimés définitivement.');
        }
        redirect('students');
    }
    if ($action === 'manage_student_account' && $user['role'] === 'teacher') {
        $studentId=(int)($_POST['student_id']??0);
        $operation=(string)($_POST['operation']??'');
        $student=one("SELECT * FROM users WHERE id=? AND role='student'",[$studentId]);
        $owned=(int)(one('SELECT COUNT(*) AS n FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.student_id=? AND c.teacher_id=?',[$studentId,$user['id']])['n']??0);
        $other=(int)(one('SELECT COUNT(*) AS n FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.student_id=? AND c.teacher_id<>?',[$studentId,$user['id']])['n']??0);
        $isManager=$student&&(int)($student['managed_by']??0)===(int)$user['id'];
        if(!$student || (!$isManager&&$owned===0)){ flash('Vous ne pouvez gérer que les comptes dont vous êtes responsable ou inscrits à l’un de vos cours.','error'); redirect('students'); }
        if($other>0){ flash('Ce compte appartient aussi à un cours d’un autre enseignant et ne peut pas être archivé ou supprimé ici.','error'); redirect('students'); }
        if($operation==='archive'){
            run("UPDATE users SET account_status='archived',student_session_token_hash=NULL,student_session_seen_at=NULL WHERE id=?",[$studentId]);
            run("UPDATE enrollments SET status='archived',archived_at=CURRENT_TIMESTAMP WHERE student_id=?",[$studentId]);
            flash('Le compte élève et ses participations sont archivés.');
        } elseif($operation==='reactivate'){
            run("UPDATE users SET account_status='active' WHERE id=?",[$studentId]);
            flash('Le compte élève est réactivé. Les participations restent archivées jusqu’à leur réactivation explicite.');
        } elseif($operation==='delete'){
            run('DELETE FROM notification_outbox WHERE recipient=?',[$student['email']]);
            run('DELETE FROM users WHERE id=?',[$studentId]);
            flash('Le compte élève et toutes ses données ont été supprimés définitivement.');
        }
        redirect('students');
    }
    if ($action === 'validate_student_email' && $user['role'] === 'teacher') {
        purge_expired_registrations();
        $studentId=(int)($_POST['student_id']??0);
        $student=one("SELECT * FROM users WHERE id=? AND role='student' AND account_status='pending'",[$studentId]);
        $owned=(int)(one('SELECT COUNT(*) AS n FROM enrollments e JOIN courses c ON c.id=e.course_id WHERE e.student_id=? AND c.teacher_id=?',[$studentId,$user['id']])['n']??0);
        $isManager=$student&&(int)($student['managed_by']??0)===(int)$user['id'];
        if(!$student||(!$isManager&&$owned===0)){
            flash('Vous ne pouvez valider que le courriel d’un élève dont vous êtes responsable ou inscrit à l’un de vos cours.','error');
            redirect('students');
        }
        run("UPDATE users SET account_status='active',email_verified_at=CURRENT_TIMESTAMP,verification_token_hash=NULL,verification_expires_at=NULL WHERE id=? AND account_status='pending'",[$studentId]);
        run("DELETE FROM notification_outbox WHERE event='account.verification' AND recipient=?",[$student['email']]);
        $studentLanguage=normalize_language((string)($student['language']??''))??'fr';
        $messageId=enqueue('account.teacher_validated',$student['email'],t('Votre compte liike a été validé',[],$studentLanguage),
            t("Bonjour :name,\n\nVotre enseignant a validé votre compte. Vous pouvez vous connecter avec le code :code.",['name'=>$student['first_name'],'code'=>$student['login_code']],$studentLanguage));
        try_send_outbox($messageId);
        flash('Le courriel de l’élève a été validé et son compte est maintenant actif.');
        redirect('students');
    }
    flash('Action non disponible.', 'error');
    redirect($user['role']==='teacher'?'teacher':'student');
}
