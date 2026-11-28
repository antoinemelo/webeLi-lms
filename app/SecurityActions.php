<?php
declare(strict_types=1);
require_once __DIR__.'/StudentEmailLogin.php';
require_once __DIR__.'/SessionPolicy.php';
require_once __DIR__.'/SmtpConfig.php';

function student_email_service(): StudentEmailLogin {return new StudentEmailLogin(db(),dirname(__DIR__));}

function student_email_binding(): string
{
    return $_SESSION['student_email_binding']??=bin2hex(random_bytes(32));
}

function student_security_remembered(array $user): bool
{
    $remembered=pwa_login_user(db(),pwa_cookie_token());
    return $remembered&&(int)$remembered['id']===(int)$user['id'];
}

/** Start or resend only the server-held flow, never a client-supplied account/purpose. */
function student_email_issue_flow(array $flow): void
{
    $_SESSION['student_email_flow']=$flow;
    $challenge=student_email_service()->issue((int)$flow['user_id'],$flow['purpose'],student_email_binding(),$flow['payload']??[]);
    $_SESSION['student_email_flow']=array_replace($flow,$challenge);
}

function student_email_begin(array $user,string $purpose,array $payload=[]): never
{
    $login=$purpose==='login';
    $remembered=!$login&&student_security_remembered($user);
    $flow=['user_id'=>(int)$user['id'],'purpose'=>$purpose,'payload'=>$payload,
        'pwa_mode'=>$login?(($_POST['pwa_mode']??'')==='1'):$remembered,
        'remember_pwa'=>$login?(($_POST['remember_pwa']??'')==='1'):$remembered];
    try{student_email_issue_flow($flow);}
    catch(InvalidArgumentException|DomainException $error){flash($error->getMessage(),'error');}
    catch(Throwable $error){error_log('liike security code: '.get_class($error));flash('L’envoi du code est momentanément indisponible. Réessayez dans quelques instants.','error');}
    redirect('student-code');
}

/** Re-establish this verified session after a security change revoked every old token. */
function student_security_refresh_session(array $user,bool $remembered): void
{
    $token=start_student_session(db(),(int)$user['id'],(int)$user['security_revision']);
    if($token===null){unset($_SESSION['user_id'],$_SESSION['student_session_token'],$_SESSION['pwa_session_hash']);flash('Reconnectez-vous pour ouvrir votre compte.','error');redirect('login');}
    session_regenerate_id(true);
    unset($_SESSION['pwa_session_hash'],$_SESSION['student_email_flow'],$_SESSION['student_email_binding']);
    $_SESSION['user_id']=(int)$user['id'];$_SESSION['student_session_token']=$token;
    if($remembered){$pwa=issue_pwa_login(db(),(int)$user['id'],(int)$user['security_revision']);set_pwa_cookie($pwa['token'],$pwa['expires_at']);}
    else set_pwa_cookie('',time()-3600);
}

function handle_security_action(string $action): void
{
    if(in_array($action,['student_email_verify','student_email_resend','student_email_cancel'],true)){
        $flow=$_SESSION['student_email_flow']??null;
        if(!is_array($flow)){flash('La demande a expiré. Recommencez.','error');redirect(actor()?'profile':'login');}
        if($flow['purpose']!=='login'){
            $current=require_actor();
            if($current['role']!=='student'||(int)$current['id']!==(int)$flow['user_id']){unset($_SESSION['student_email_flow']);redirect('profile');}
        }
        if($action==='student_email_cancel'){
            if(!empty($flow['id']))run('DELETE FROM student_email_challenges WHERE id=? AND binding=?',[$flow['id'],hash('sha256',student_email_binding())]);
            unset($_SESSION['student_email_flow'],$_SESSION['student_email_binding']);redirect($flow['purpose']==='login'?'login':'profile');
        }
        try{
            if($action==='student_email_resend'){
                student_email_issue_flow($flow);flash('Un nouveau code a été demandé. Seul le dernier code reçu est valable.');redirect('student-code');
            }
            $code=is_string($_POST['code']??null)?trim($_POST['code']):'';
            $result=student_email_service()->verify((string)($flow['id']??''),student_email_binding(),$code);
            if(!$result['valid']){flash(t('Code incorrect. :count essai(s) restant(s).',['count'=>$result['remaining']]),'error');redirect('student-code');}
            if($result['purpose']==='email_old'){
                $flow['purpose']='email_new';$flow['payload']=$result['payload'];unset($flow['id'],$flow['recipient'],$flow['created_at'],$flow['expires_at']);
                student_email_issue_flow($flow);flash('Adresse actuelle confirmée. Saisissez maintenant le code envoyé à votre nouvelle adresse.');redirect('student-code');
            }
            unset($_SESSION['student_email_flow'],$_SESSION['student_email_binding']);
            if($result['purpose']==='login'){
                $_POST['pwa_mode']=$flow['pwa_mode']?'1':'0';$_POST['remember_pwa']=$flow['remember_pwa']?'1':'0';
                open_authenticated_session($result['user'],true);
            }
            student_security_refresh_session($result['user'],(bool)$flow['remember_pwa']);
            flash(match($result['purpose']){'enable'=>'Protection par code activée.','disable'=>'Protection par code désactivée.',default=>'Votre nouvelle adresse est confirmée.'});redirect('profile');
        }catch(InvalidArgumentException|DomainException $error){flash($error->getMessage(),'error');}
        catch(Throwable $error){error_log('liike security verification: '.get_class($error));flash('Vérification momentanément indisponible. Réessayez dans quelques instants.','error');}
        redirect('student-code');
    }
    if($action==='student_email_security_begin'){
        $user=require_actor();if($user['role']!=='student'){http_response_code(403);exit;}
        $enabled=($_POST['email_login_enabled']??'')==='1';
        if($enabled===(bool)$user['email_login_enabled'])redirect('profile');
        student_email_begin($user,$enabled?'enable':'disable');
    }
    if($action==='reset_student_login_attempts'){
        $admin=require_actor();
        if($admin['role']!=='teacher'||(int)($admin['is_superadmin']??0)!==1){http_response_code(403);exit;}
        try{
            student_email_service()->resetAttempts((int)($_POST['student_id']??0),$admin);
            flash('Les demandes de code et les essais de ce compte ont été réinitialisés. L’apprenant doit demander un nouveau code.');
        }catch(InvalidArgumentException $error){flash($error->getMessage(),'error');}
        catch(Throwable $error){error_log('liike login attempts reset: '.get_class($error));flash('La réinitialisation est momentanément indisponible. Réessayez.','error');}
        redirect('admin');
    }
    if($action==='set_student_login_block'){
        $teacher=require_actor();$studentId=(int)($_POST['student_id']??0);
        try{student_email_service()->block($studentId,$teacher,($_POST['blocked']??'')==='1');flash(($_POST['blocked']??'')==='1'?'Accès bloqué. Les inscriptions et résultats sont conservés.':'Accès débloqué.');}
        catch(InvalidArgumentException $error){http_response_code(403);flash($error->getMessage(),'error');}
        if(($_POST['return_view']??'')==='admin'&&(int)($teacher['is_superadmin']??0)===1)redirect('admin');
        redirect('students',['student'=>$studentId]);
    }
    if(in_array($action,['save_smtp','test_smtp'],true)){
        $admin=require_actor();if($admin['role']!=='teacher'||(int)($admin['is_superadmin']??0)!==1){http_response_code(403);exit;}
        try{
            if($action==='save_smtp'){SmtpConfig::save(dirname(__DIR__),$_POST,$admin);flash('Configuration de messagerie enregistrée.');}
            else{
                $config=SmtpConfig::read(dirname(__DIR__));
                if(!$config['enabled'])throw new InvalidArgumentException('Enregistrez et activez SMTP avant de le tester.');
                // Only the current superadmin can receive this test; no arbitrary recipient.
                $ok=deliver_app_mail($admin['email'],'liike · Test SMTP','Votre configuration SMTP liike fonctionne.');
                flash($ok?'Courriel de test envoyé à votre adresse.':'Envoi impossible. Vérifiez les réglages SMTP et les accès du serveur.',$ok?'success':'error');
            }
        }catch(InvalidArgumentException $error){flash($error->getMessage(),'error');}
        catch(Throwable $error){error_log('liike SMTP settings: '.get_class($error));flash('Configuration de messagerie indisponible. Vérifiez les réglages et les droits de stockage.','error');}
        redirect('admin');
    }
}
