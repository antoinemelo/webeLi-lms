<?php
declare(strict_types=1);

function pathway_item_type(array $item): string
{
    if(!empty($item['event_data']))return 'event';
    if(!empty($item['is_evaluation']))return 'evaluation';
    return !empty($item['self_evaluation_enabled'])?'self':'consultation';
}

/** Dates remain local to Europe/Zurich; all-day end dates in the editor are inclusive. */
function pathway_event_normalize(array $input): array
{
    $allDay=!empty($input['all_day']);
    $format=$allDay?'Y-m-d':'Y-m-d\TH:i';
    $zone=new DateTimeZone('Europe/Zurich');
    $dates=[];
    foreach(['start','end'] as $key){
        $value=$input[$key]??'';
        if(!is_string($value))throw new InvalidArgumentException('Renseignez les dates de début et de fin de l’événement.');
        $date=DateTimeImmutable::createFromFormat('!'.$format,$value,$zone);
        if(!$date||$date->format($format)!==$value)throw new InvalidArgumentException('Renseignez des dates et horaires valides pour l’événement.');
        $dates[$key]=$date;
    }
    if($dates['end']<$dates['start']||(!$allDay&&$dates['end']==$dates['start']))throw new InvalidArgumentException('La fin de l’événement doit suivre son début.');
    $location=$input['location']??'';
    if(!is_string($location)||mb_strlen($location)>500)throw new InvalidArgumentException('Le lieu est limité à 500 caractères.');
    return ['all_day'=>$allDay,'start'=>$input['start'],'end'=>$input['end'],'location'=>trim($location)];
}

function pathway_type_settings(array $input, array $previous=[]): array
{
    $type=$input['item_type']??(isset($input['is_evaluation'])?'evaluation':(!empty($input['self_evaluation_enabled']??$previous['self_evaluation_enabled']??1)?'self':'consultation'));
    if(!in_array($type,['consultation','evaluation','self','event'],true))throw new InvalidArgumentException('Choisissez un type d’étape valide.');
    $event=null;
    if($type==='event')foreach(['event_start_date','event_end_date','event_start_time','event_end_time'] as $key){
        if(isset($input[$key])&&!is_string($input[$key]))throw new InvalidArgumentException('Renseignez des dates et horaires valides pour l’événement.');
        $input[$key]=trim($input[$key]??'');
        if(str_ends_with($key,'_date')&&preg_match('~^(\d{2})/(\d{2})/(\d{4})$~',$input[$key],$parts))$input[$key]=$parts[3].'-'.$parts[2].'-'.$parts[1];
    }
    if($type==='event')$event=json_encode(pathway_event_normalize([
        'all_day'=>!empty($input['event_all_day']),
        'start'=>($input['event_start_date']??'').(empty($input['event_all_day'])?'T'.($input['event_start_time']??''):''),
        'end'=>($input['event_end_date']??'').(empty($input['event_all_day'])?'T'.($input['event_end_time']??''):''),
        'location'=>$input['event_location']??'',
    ]),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    return [$type==='evaluation'?1:0,$type==='self'?1:0,$event];
}

function pathway_event_dates(array $event): array
{
    $zone=new DateTimeZone('Europe/Zurich');
    $start=new DateTimeImmutable($event['start'],$zone);$end=new DateTimeImmutable($event['end'],$zone);
    if($event['all_day'])return [$start->format('Ymd'),$end->modify('+1 day')->format('Ymd')];
    $utc=new DateTimeZone('UTC');
    return [$start->setTimezone($utc)->format('Ymd\THis\Z'),$end->setTimezone($utc)->format('Ymd\THis\Z')];
}

function pathway_event_google_url(array $item): string
{
    $event=pathway_event_normalize(json_decode($item['event_data'],true,512,JSON_THROW_ON_ERROR));
    [$start,$end]=pathway_event_dates($event);
    return 'https://calendar.google.com/calendar/r/eventedit?'.http_build_query([
        'action'=>'TEMPLATE','text'=>$item['title'],'dates'=>$start.'/'.$end,
        'stz'=>'Europe/Zurich','etz'=>'Europe/Zurich','location'=>$event['location'],
        'details'=>trim(($item['summary']??'')."\n\n".($item['instructions']??'')),
    ],'', '&',PHP_QUERY_RFC3986);
}

function pathway_event_ics(array $item): string
{
    $event=pathway_event_normalize(json_decode($item['event_data'],true,512,JSON_THROW_ON_ERROR));
    [$start,$end]=pathway_event_dates($event);
    $escape=static fn(string $value):string=>str_replace(["\\","\r\n","\r","\n",';',','],["\\\\","\\n","\\n","\\n","\\;","\\,"],preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$value));
    $dateKind=$event['all_day']?';VALUE=DATE':'';
    $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//liike//Pathway Events//FR','CALSCALE:GREGORIAN','BEGIN:VEVENT',
        'UID:'.hash('sha256',$item['course_uuid'].':'.$item['id']).'@liike',
        'DTSTAMP:'.gmdate('Ymd\THis\Z'),'SEQUENCE:'.(int)($item['revision']??0),
        'DTSTART'.$dateKind.':'.$start,'DTEND'.$dateKind.':'.$end,
        'SUMMARY:'.$escape($item['title']),'DESCRIPTION:'.$escape(trim(($item['summary']??'')."\n\n".($item['instructions']??''))),
        'LOCATION:'.$escape($event['location']),'END:VEVENT','END:VCALENDAR'];
    // RFC 5545: CRLF, folding at 75 octets without splitting a UTF-8 character.
    $folded=[];
    foreach($lines as $line){while(strlen($line)>75){$part=mb_strcut($line,0,75,'UTF-8');$folded[]=$part;$line=' '.substr($line,strlen($part));}$folded[]=$line;}
    return implode("\r\n",$folded)."\r\n";
}

function pathway_event_for_actor(PDO $pdo,int $id,array $actor): array
{
    $query=$pdo->prepare('SELECT pi.*,p.title,p.summary,c.messaging_uuid AS course_uuid FROM pathway_items pi JOIN pages p ON p.id=pi.page_id JOIN courses c ON c.id=pi.course_id WHERE pi.id=?');
    $query->execute([$id]);$item=$query->fetch(PDO::FETCH_ASSOC);
    $allowed=$item&&(($actor['role']==='teacher'&&teacher_can_access_course($pdo,(int)$item['course_id'],(int)$actor['id']))||($actor['role']==='student'&&item_is_visible_to_student($pdo,$id,(int)$actor['id'])));
    if(!$allowed||empty($item['event_data']))throw new InvalidArgumentException('Cette étape n’est pas disponible.');
    return $item;
}

function render_pathway_type_fields(array $item=[]): void
{
    $type=pathway_item_type($item?:['self_evaluation_enabled'=>1]);
    $event=json_decode($item['event_data']??'null',true)??[];
    ?><label class="field"><span><?=e(t('Type d’étape'))?></span><select name="item_type" data-pathway-type><?php foreach(['consultation'=>'Consultation simple','evaluation'=>'Évaluation','self'=>'Autoévaluation','event'=>'Événement'] as $value=>$label): ?><option value="<?=$value?>" <?=$type===$value?'selected':''?>><?=e(t($label))?></option><?php endforeach; ?></select></label>
    <fieldset data-event-fields <?=$type==='event'?'':'hidden'?>><legend><?=e(t('Événement'))?></legend><p class="muted-copy"><?=e(t('Le titre de la page sera le nom de l’événement. Horaires : Europe/Zurich.'))?></p>
    <label class="check plain"><input type="checkbox" name="event_all_day" value="1" data-event-all-day <?=!empty($event['all_day'])?'checked':''?>> <?=e(t('Toute la journée'))?></label>
    <div class="row g-3"><?php foreach(['start'=>'Début','end'=>'Fin'] as $key=>$label): ?><div class="col-sm-6"><label class="field"><span><?=e(t($label))?></span><input type="date" name="event_<?=$key?>_date" value="<?=e(substr($event[$key]??'',0,10))?>" data-event-date></label><label class="field" data-event-time-field><span><?=e(t($key==='start'?'Heure de début':'Heure de fin'))?></span><input type="time" name="event_<?=$key?>_time" value="<?=e(substr($event[$key]??'',11,5))?>" data-event-time></label></div><?php endforeach; ?></div>
    <label class="field"><span><?=e(t('Lieu'))?> (<?=e(t('facultatif'))?>)</span><input name="event_location" maxlength="500" value="<?=e($event['location']??'')?>"></label></fieldset><?php
}

function render_pathway_event(array $item): void
{
    if(empty($item['event_data']))return;
    $event=json_decode($item['event_data'],true);$format=$event['all_day']?'d/m/Y':'d/m/Y H:i';
    $start=new DateTimeImmutable($event['start'],new DateTimeZone('Europe/Zurich'));$end=new DateTimeImmutable($event['end'],new DateTimeZone('Europe/Zurich'));
    ?><section class="pathway-event-card border rounded p-3 my-3"><h2><i class="bi bi-calendar-event" aria-hidden="true"></i> <?=e(t('Événement'))?></h2><p><?=e($start->format($format))?><?php if($event['start']!==$event['end']): ?> → <?=e($end->format($format))?><?php endif; ?> · <?=e($event['all_day']?t('Toute la journée'):'Europe/Zurich')?></p><?php if($event['location']!==''): ?><p><?=e($event['location'])?></p><?php endif; ?><div class="d-flex flex-wrap gap-2"><a class="button secondary" href="<?=e(route('event-download',['item'=>$item['id']]))?>"><i class="bi bi-download" aria-hidden="true"></i> <?=e(t('Télécharger le calendrier (.ics)'))?></a><a class="button secondary" href="<?=e(pathway_event_google_url($item))?>" target="_blank" rel="noopener noreferrer"><?=e(t('Ajouter à Google Calendar'))?></a></div><small><?=e(t('Après l’ajout à votre agenda, les modifications ne sont pas synchronisées automatiquement.'))?></small></section><?php
}
