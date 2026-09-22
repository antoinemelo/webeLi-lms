<?php
declare(strict_types=1);

function teacher_pathway_view(array $user): string
{
    return ($_COOKIE['liike_pathway_view_'.(int)$user['id']]??'')==='pathway'?'pathway':'teacher-preview';
}

function remember_teacher_pathway_view(array $user,string $view): void
{
    if($user['role']!=='teacher'||!in_array($view,['pathway','teacher-preview'],true))return;
    $name='liike_pathway_view_'.(int)$user['id'];
    if(($_COOKIE[$name]??null)===$view)return;
    $path=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/index.php')),'/.').'/';
    setcookie($name,$view,['expires'=>time()+31536000,'path'=>$path,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
    $_COOKIE[$name]=$view;
}

/** All dates are calendar dates in Switzerland, including across DST transitions. */
function teacher_calendar_period(string $mode,?string $date=null): array
{
    $zone=new DateTimeZone('Europe/Zurich');$today=new DateTimeImmutable('today',$zone);
    $anchor=$date&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date,$zone):false;
    if(!$anchor||$anchor->format('Y-m-d')!==$date)$anchor=$today;
    $mode=$mode==='month'?'month':'week';
    if($mode==='week'){
        $start=$anchor->modify('-'.((int)$anchor->format('N')-1).' days');$end=$start->modify('+6 days');
        $previous=$start->modify('-7 days');$next=$start->modify('+7 days');
    }else{
        $first=$anchor->modify('first day of this month');$last=$anchor->modify('last day of this month');
        $start=$first->modify('-'.((int)$first->format('N')-1).' days');$end=$last->modify('+'.(7-(int)$last->format('N')).' days');
        $previous=$first->modify('-1 month');$next=$first->modify('+1 month');
    }
    return compact('mode','anchor','today','start','end','previous','next');
}

function teacher_calendar_entries(PDO $pdo,int $teacherId,array $period): array
{
    $query=$pdo->prepare("SELECT pi.id,pi.course_id,pi.deadline,pi.is_evaluation,pi.self_evaluation_enabled,pi.access_mode,
        p.title,c.title AS course_title,c.accent
        FROM pathway_items pi JOIN courses c ON c.id=pi.course_id JOIN pages p ON p.id=pi.page_id
        JOIN users u ON u.id=? AND u.role='teacher' AND u.account_status='active'
        WHERE c.archived=0 AND (c.teacher_id=u.id OR EXISTS(SELECT 1 FROM course_teachers ct WHERE ct.course_id=c.id AND ct.teacher_id=u.id))
          AND pi.deadline BETWEEN ? AND ?
        ORDER BY pi.deadline,c.title,pi.position,pi.id");
    $query->execute([$teacherId,$period['start']->format('Y-m-d'),$period['end']->format('Y-m-d')]);
    $days=[];
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $entry)$days[$entry['deadline']][]=$entry;
    return $days;
}

function teacher_calendar_date_label(DateTimeImmutable $date,string $pattern): string
{
    if(class_exists(IntlDateFormatter::class)){
        $formatter=new IntlDateFormatter(current_language(),IntlDateFormatter::NONE,IntlDateFormatter::NONE,'Europe/Zurich',IntlDateFormatter::GREGORIAN,$pattern);
        $label=$formatter->format($date);if($label!==false)return $label;
    }
    return $date->format(match($pattern){'LLLL yyyy'=>'F Y','EEE'=>'D','EEE d'=>'D j','d MMM'=>'j M',default=>'l j F Y'});
}

function render_teacher_calendar(array $course): void
{
    $user=require_actor();$teacherId=(int)$user['id'];
    $requested=$_GET['calendar_mode']??$_SESSION['teacher_calendar_modes'][$teacherId]??'week';
    $mode=$requested==='month'?'month':'week';$_SESSION['teacher_calendar_modes'][$teacherId]=$mode;
    $date=$_GET['calendar_date']??$_SESSION['teacher_calendar_dates'][$teacherId]??null;
    $period=teacher_calendar_period($mode,is_string($date)?$date:null);
    $_SESSION['teacher_calendar_dates'][$teacherId]=$period['anchor']->format('Y-m-d');
    $entries=teacher_calendar_entries(db(),$teacherId,$period);$count=array_sum(array_map('count',$entries));
    $base=['course'=>(int)$course['id']];
    foreach(['sort','dir'] as $key)if(is_string($_GET[$key]??null))$base[$key]=$_GET[$key];
    $link=static fn(string $display,DateTimeImmutable $date):string=>route('teacher',$base+['calendar_mode'=>$display,'calendar_date'=>$date->format('Y-m-d')]).'#teacher-calendar';
    $title=$mode==='month'?teacher_calendar_date_label($period['anchor'],'LLLL yyyy'):teacher_calendar_date_label($period['start'],'d MMM').' – '.teacher_calendar_date_label($period['end'],'d MMM').' '.$period['end']->format('Y');
    $preferred=teacher_pathway_view($user);
    ?>
    <section class="teacher-calendar calendar-<?=$mode?>" id="teacher-calendar" aria-labelledby="teacher-calendar-title">
      <div class="calendar-heading"><div><h2 id="teacher-calendar-title"><?=e(t('Calendrier des échéances'))?></h2></div><span class="calendar-total"><?=e(t($count===1?':count échéance':':count échéances',['count'=>$count]))?></span></div>
      <div class="calendar-toolbar">
        <div class="calendar-navigation"><a href="<?=e($link($mode,$period['previous']))?>" class="calendar-arrow" aria-label="<?=e(t($mode==='week'?'Semaine précédente':'Mois précédent'))?>"><i class="bi bi-chevron-left" aria-hidden="true"></i></a><h3><?=e($title)?></h3><a href="<?=e($link($mode,$period['next']))?>" class="calendar-arrow" aria-label="<?=e(t($mode==='week'?'Semaine suivante':'Mois suivant'))?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></a></div>
        <div class="calendar-controls"><a class="calendar-today" href="<?=e($link($mode,$period['today']))?>"><?=e(t('Aujourd’hui'))?></a><nav class="calendar-modes" aria-label="<?=e(t('Affichage du calendrier'))?>"><?php foreach(['week'=>'Semaine','month'=>'Mois'] as $display=>$label): ?><a href="<?=e($link($display,$period['anchor']))?>" <?=$mode===$display?'aria-current="true"':''?>><?=e(t($label))?></a><?php endforeach; ?></nav></div>
      </div>
      <?php if(!$count): ?><p class="calendar-empty"><?=e(t('Aucune échéance sur cette période.'))?></p><?php endif; ?>
      <div class="calendar-weekdays" aria-hidden="true"><?php for($index=0;$index<7;$index++): ?><span><?=e(teacher_calendar_date_label($period['start']->modify('+'.$index.' days'),'EEE'))?></span><?php endfor; ?></div>
      <div class="calendar-grid">
        <?php for($day=$period['start'];$day<=$period['end'];$day=$day->modify('+1 day')): $key=$day->format('Y-m-d');$items=$entries[$key]??[];$isToday=$key===$period['today']->format('Y-m-d');$outside=$mode==='month'&&$day->format('Y-m')!==$period['anchor']->format('Y-m'); ?>
          <div class="calendar-day<?=$isToday?' is-today':''?><?=$outside?' outside-month':''?><?=$items?' has-deadlines':''?>" data-calendar-day="<?=$key?>">
            <time datetime="<?=$key?>" aria-label="<?=e(teacher_calendar_date_label($day,'EEEE d MMMM yyyy'))?>" <?=$isToday?'aria-current="date"':''?>><span class="calendar-day-number"><?=$day->format('j')?></span><span class="calendar-day-full"><?=e(teacher_calendar_date_label($day,'EEEE d MMMM yyyy'))?></span></time>
            <?php foreach($items as $entry): $accent=preg_match('/^#[0-9a-fA-F]{6}$/D',$entry['accent'])?$entry['accent']:'#6d5dfc';$href=$preferred==='pathway'||$entry['access_mode']==='none'?route('pathway',['course'=>$entry['course_id'],'edit'=>$entry['id']]).'#pathway-item-'.$entry['id']:route('teacher-preview-page',['item'=>$entry['id']]); ?>
              <a class="calendar-entry" href="<?=e($href)?>" style="--calendar-course:<?=e($accent)?>" title="<?=e($entry['course_title'].' · '.$entry['title'])?>"><small><?=e($entry['course_title'])?></small><span><?php if($entry['is_evaluation']): ?><i class="bi bi-clipboard-check" aria-hidden="true"></i> <?php endif; ?><?=e(mb_strlen($entry['title'],'UTF-8')>40?mb_substr($entry['title'],0,37,'UTF-8').'...':$entry['title'])?></span></a>
            <?php endforeach; ?>
          </div>
        <?php endfor; ?>
      </div>
    </section>
    <?php
}
