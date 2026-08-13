<?php

declare(strict_types=1);

function pdf_date_fr(?string $date): string
{
    if(!$date)return '—';$time=strtotime($date);if($time===false)return '—';
    if(function_exists('date_fr'))return date_fr($date,true);
    return date('d/m/Y',$time);
}

function pdf_document(string $title, string $body, bool $landscape = false): string
{
    $orientation=$landscape?'landscape':'portrait';
    return '<!doctype html><html lang="'.e(current_language()).'"><head><meta charset="utf-8"><title>'.e($title).'</title><style>
    @page{size:A4 '.$orientation.';margin:14mm}*{box-sizing:border-box}body{margin:0;color:#29273b;font:12px/1.5 Arial,sans-serif}h1{margin:0 0 6px;font-size:25px}h2{margin:22px 0 8px;font-size:17px}h3{margin:16px 0 6px;font-size:14px}p{margin:5px 0}.muted{color:#706e80}.header{margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid #6757df}.eyebrow{color:#6757df;font-size:10px;font-weight:bold;letter-spacing:1px;text-transform:uppercase}.meta{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}.pill{padding:3px 7px;border-radius:5px;background:#ece9ff;font-size:10px}.notice{margin:12px 0;padding:9px;border-left:3px solid #6757df;background:#f5f3ff}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #dcd9e5;text-align:left;vertical-align:top}th{background:#ece9ff;font-size:10px;text-transform:uppercase}tr:nth-child(even){background:#fafafd}.content{margin:13px 0;padding:12px;border:1px solid #dedbe7;border-radius:8px;break-inside:avoid}.content img{max-width:100%;max-height:175mm}.content blockquote{margin:8px 0;padding:7px 10px;border-left:3px solid #6757df;background:#f5f3ff}.content pre{overflow-wrap:anywhere;padding:8px;background:#29273b;color:#fff;white-space:pre-wrap}.content code{font-family:monospace}.footer{margin-top:20px;padding-top:8px;border-top:1px solid #ddd;color:#777;font-size:9px}</style></head><body>'.$body.'</body></html>';
}

function course_pdf_html(PDO $pdo, int $courseId, int $teacherId): string
{
    $query=$pdo->prepare('SELECT * FROM courses WHERE id=?');$query->execute([$courseId]);$course=$query->fetch(PDO::FETCH_ASSOC);if(!$course||!teacher_can_access_course($pdo,$courseId,$teacherId))throw new TransferException('Parcours introuvable.');
    $items=$pdo->prepare('SELECT pi.*,p.title,p.estimated_minutes FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE pi.course_id=? ORDER BY pi.position');$items->execute([$courseId]);$rows='';
    foreach($items->fetchAll(PDO::FETCH_ASSOC) as $item){$rows.='<tr><td>'.(int)$item['position'].'</td><td><strong>'.e($item['title']).'</strong></td><td>'.e(pdf_date_fr($item['deadline'])).'</td><td>'.(int)$item['estimated_minutes'].' min</td><td>'.e(t($item['is_evaluation']?'Évaluation':'Étape')).'</td></tr>';}
    $body='<header class="header"><div class="eyebrow">'.e(t('Parcours pédagogique')).'</div><h1>'.e($course['title']).'</h1><p class="muted">'.e($course['description']).'</p><div class="meta"><span class="pill">'.e(t('Code')).' '.e($course['code']).'</span><span class="pill">'.e(t($course['archived']?'Archivé':'Actif')).'</span></div></header><table><thead><tr><th>'.e(t('No étape')).'</th><th>'.e(t('Nom')).'</th><th>'.e(t('Échéance')).'</th><th>'.e(t('Durée')).'</th><th>'.e(t('Type')).'</th></tr></thead><tbody>'.$rows.'</tbody></table><div class="footer">Export liike · '.e(date('d/m/Y H:i')).'</div>';
    return pdf_document(t('Parcours').' · '.$course['title'],$body,true);
}

function pdf_local_image(string $body): string
{
    if(!str_starts_with($body,'uploads/')&&!str_starts_with($body,'assets/'))return $body;
    $path=APR_PUBLIC_ROOT.'/'.ltrim($body,'/');if(!is_file($path)||filesize($path)>10*1024*1024)return $body;
    $data=file_get_contents($path);if($data===false)return $body;$mime=(string)(mime_content_type($path)?:'application/octet-stream');return 'data:'.$mime.';base64,'.base64_encode($data);
}

function pathway_page_pdf_html(PDO $pdo, int $itemId, int $teacherId): string
{
    $query=$pdo->prepare('SELECT pi.*,p.title,p.summary,p.status,p.estimated_minutes,p.id AS page_id,c.title AS course_title,c.code AS course_code FROM pathway_items pi JOIN pages p ON p.id=pi.page_id JOIN courses c ON c.id=pi.course_id WHERE pi.id=?');$query->execute([$itemId]);$item=$query->fetch(PDO::FETCH_ASSOC);if(!$item||!teacher_can_access_course($pdo,(int)$item['course_id'],$teacherId))throw new TransferException('Étape introuvable.');
    $tags=$pdo->prepare('SELECT t.name FROM tags t JOIN page_tags pt ON pt.tag_id=t.id WHERE pt.page_id=? ORDER BY t.name');$tags->execute([$item['page_id']]);
    $objectives=$pdo->prepare('SELECT title FROM page_objectives WHERE page_id=? ORDER BY position,id');$objectives->execute([$item['page_id']]);
    $skills=$pdo->prepare('SELECT s.code,s.title FROM course_skills s JOIN item_skills i ON i.skill_id=s.id WHERE i.pathway_item_id=? ORDER BY s.position');$skills->execute([$itemId]);
    $meta='<span class="pill">'.e(t('Étape :number',['number'=>(int)$item['position']])).'</span><span class="pill">'.e(t($item['is_evaluation']?'Évaluation':'Activité')).'</span><span class="pill">'.(int)$item['estimated_minutes'].' min</span><span class="pill">'.e(t('Échéance')).' '.e(pdf_date_fr($item['deadline'])).'</span><span class="pill">'.e(t($item['status']==='ready'?'Prête':'Brouillon')).'</span>';
    foreach($tags->fetchAll(PDO::FETCH_COLUMN) as $tag)$meta.='<span class="pill">#'.e($tag).'</span>';
    $body='<header class="header"><div class="eyebrow">'.e($item['course_title']).' · '.e($item['course_code']).'</div><h1>'.e($item['title']).'</h1><p class="muted">'.e($item['summary']).'</p><div class="meta">'.$meta.'</div></header>';
    if(trim((string)$item['instructions'])!=='')$body.='<div class="notice"><strong>'.e(t('Consigne propre au parcours')).'</strong><p>'.nl2br(e($item['instructions'])).'</p></div>';
    $objectiveRows=$objectives->fetchAll(PDO::FETCH_COLUMN);$skillRows=$skills->fetchAll(PDO::FETCH_ASSOC);
    if($objectiveRows||$skillRows){$body.='<h2>'.e(t('Objectifs et compétences')).'</h2>';if($objectiveRows)$body.='<p><strong>'.e(t('Objectifs')).' :</strong> '.e(implode(' · ',$objectiveRows)).'</p>';if($skillRows)$body.='<p><strong>'.e(t('Compétences')).' :</strong> '.e(implode(' · ',array_map(fn($skill)=>$skill['code'].' — '.$skill['title'],$skillRows))).'</p>';}
    $blocks=$pdo->prepare('SELECT * FROM page_blocks WHERE page_id=? ORDER BY position');$blocks->execute([$item['page_id']]);$body.='<h2>'.e(t('Contenu détaillé')).'</h2>';
    foreach($blocks->fetchAll(PDO::FETCH_ASSOC) as $block){$body.='<section class="content">';if($block['type']==='markdown')$body.=Markdown::render($block['body']);elseif($block['type']==='image')$body.='<img src="'.e(pdf_local_image($block['body'])).'" alt="'.e($block['caption']).'"><p class="muted">'.e($block['caption']).'</p>';elseif($block['type']==='file')$body.='<strong>'.e(t('Fichier joint')).' :</strong> '.e($block['caption']?:basename($block['body'])).'<br><span class="muted">'.e($block['body']).'</span>';else $body.='<strong>'.e(t('Contenu intégré / vidéo')).' :</strong><br><span class="muted">'.e($block['body']).'</span>'; $body.='</section>';}
    $body.='<div class="footer">Export liike · '.e(date('d/m/Y H:i')).'</div>';return pdf_document($item['title'],$body,false);
}

function chromium_binary(): ?string
{
    foreach([
        '/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome','/usr/bin/google-chrome-stable',
        '/snap/bin/chromium','/opt/google/chrome/chrome',
    ] as $candidate)if(is_executable($candidate))return $candidate;return null;
}

function pdf_plain_text(string $html): string
{
    $html=preg_replace('~<(style|script)[^>]*>.*?</\1>~is','',$html)??$html;
    $html=preg_replace('~<li[^>]*>~i','- ',$html)??$html;
    $html=preg_replace('~</t[dh]>~i',' | ',$html)??$html;
    $html=preg_replace('~<(?:br\s*/?|/p|/div|/h[1-6]|/header|/section|/article|/tr|/li)[^>]*>~i',"\n",$html)??$html;
    $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=str_replace(["\r\n","\r","\xC2\xA0"],["\n","\n",' '],$text);
    $lines=[];
    foreach(explode("\n",$text) as $source){
        $source=trim((string)(preg_replace('/[ \t]+/u',' ',$source)??$source),' |');
        if($source===''){if($lines&&end($lines)!=='')$lines[]='';continue;}
        $lines[]=$source;
    }
    return trim(implode("\n",$lines));
}

function pdf_wrap_line(string $line, int $limit): array
{
    if($line==='')return [''];
    $wrapped=[];$current='';
    foreach(preg_split('/\s+/u',$line,-1,PREG_SPLIT_NO_EMPTY)?:[] as $word){
        while(mb_strlen($word,'UTF-8')>$limit){
            if($current!==''){$wrapped[]=$current;$current='';}
            $wrapped[]=mb_substr($word,0,$limit,'UTF-8');
            $word=mb_substr($word,$limit,null,'UTF-8');
        }
        $candidate=$current===''?$word:$current.' '.$word;
        if(mb_strlen($candidate,'UTF-8')>$limit){$wrapped[]=$current;$current=$word;}else $current=$candidate;
    }
    if($current!==''||!$wrapped)$wrapped[]=$current;
    return $wrapped;
}

function pdf_fallback_document(string $html): string
{
    $landscape=str_contains($html,'size:A4 landscape');
    $width=$landscape?842:595;$height=$landscape?595:842;
    $lineLimit=$landscape?132:88;$linesPerPage=$landscape?34:52;$lines=[];
    foreach(explode("\n",pdf_plain_text($html)) as $line)foreach(pdf_wrap_line($line,$lineLimit) as $wrapped)$lines[]=$wrapped;
    $pages=array_chunk($lines?:['Document vide'],$linesPerPage);
    $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
    $pageRefs=[];
    foreach($pages as $index=>$pageLines){
        $pageId=4+$index*2;$contentId=$pageId+1;$pageRefs[]=$pageId.' 0 R';
        $stream="BT\n/F1 10 Tf\n14 TL\n40 ".($height-45)." Td\n";
        foreach($pageLines as $line){
            $encoded=function_exists('iconv')?(string)iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$line):preg_replace('/[^\x20-\x7E]/','?',$line);
            $encoded=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$encoded)??'';
            $encoded=strtr($encoded,['\\'=>'\\\\','('=>'\\(',')'=>'\\)']);
            $stream.='('.$encoded.") Tj T*\n";
        }
        $stream.="ET\n";
        $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$width.' '.$height.'] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentId.' 0 R >>';
        $objects[$contentId]='<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
    }
    $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$pageRefs).'] /Count '.count($pageRefs).' >>';
    ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0=>0];
    foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$object."\nendobj\n";}
    $xref=strlen($pdf);$size=max(array_keys($objects))+1;$pdf.="xref\n0 $size\n0000000000 65535 f \n";
    for($id=1;$id<$size;$id++)$pdf.=sprintf('%010d 00000 n ',(int)($offsets[$id]??0))."\n";
    return $pdf.'trailer << /Size '.$size." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
}

function render_pdf_fallback_file(string $html): string
{
    $path=tempnam(sys_get_temp_dir(),'lms-pdf-');
    if($path===false||file_put_contents($path,pdf_fallback_document($html))===false)throw new TransferException('Le fichier PDF temporaire ne peut pas être créé.');
    return $path;
}

function render_pdf_file(string $html): string
{
    $chromium=chromium_binary();if(!$chromium||!function_exists('proc_open'))return render_pdf_fallback_file($html);
    $directory=sys_get_temp_dir().'/elan-pdf-'.bin2hex(random_bytes(8));if(!mkdir($directory,0700,true))throw new TransferException('Le répertoire PDF temporaire ne peut pas être créé.');$htmlPath=$directory.'/document.html';$pdfPath=$directory.'/document.pdf';file_put_contents($htmlPath,$html);
    $profilePath=$directory.'/chromium-profile';
    $command=[$chromium,'--headless=new','--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--allow-file-access-from-files','--user-data-dir='.$profilePath,'--no-pdf-header-footer','--print-to-pdf='.$pdfPath,'file://'.$htmlPath];
    $pipes=[];$process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new TransferException('Chromium ne peut pas être démarré.');$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    if($status!==0||!is_file($pdfPath)||filesize($pdfPath)<5){@unlink($htmlPath);@unlink($pdfPath);throw new TransferException('La génération PDF a échoué. '.trim($stderr?:$stdout));}
    @unlink($htmlPath);return $pdfPath;
}

function send_pdf_download(string $html, string $filename): never
{
    $pdfPath=render_pdf_file($html);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',basename($filename)).'"');header('Content-Length: '.filesize($pdfPath));header('Cache-Control: no-store');readfile($pdfPath);@unlink($pdfPath);exit;
}
