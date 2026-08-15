<?php

declare(strict_types=1);

function pdf_date_fr(?string $date): string
{
    if(!$date)return '—';$time=strtotime($date);if($time===false)return '—';
    if(function_exists('date_fr'))return date_fr($date,true);
    return date('d/m/Y',$time);
}

function pdf_meta(array $values): string
{
    return implode(' <span class="meta-separator">·</span> ',array_map(static fn(mixed $value):string=>'<span class="pill">'.e($value).'</span>',$values));
}

function pdf_sanitize_html(string $html): string
{
    return preg_replace('/[\x{10000}-\x{10FFFF}\x{200D}\x{20E3}\x{FE0E}\x{FE0F}]/u','',$html)??$html;
}

function pdf_document(string $title, string $body, bool $landscape = false): string
{
    $orientation=$landscape?'landscape':'portrait';
    return '<!doctype html><html lang="'.e(current_language()).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title><style>
    @page{size:A4 '.$orientation.';margin:14mm}*{box-sizing:border-box}body{margin:0;color:#29273b;font:12px/1.5 Arial,sans-serif}h1{margin:0 0 6px;font-size:25px}h2{margin:22px 0 8px;font-size:17px}h3{margin:16px 0 6px;font-size:14px}p{margin:5px 0}.muted{color:#706e80}.header{margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid #6757df}.eyebrow{color:#6757df;font-size:10px;font-weight:bold;letter-spacing:1px;text-transform:uppercase}.meta{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}.pill{padding:3px 7px;border-radius:5px;background:#ece9ff;font-size:10px}.meta-separator{color:#8b879c;font-weight:bold}.notice{margin:12px 0;padding:9px;border-left:3px solid #6757df;background:#f5f3ff}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #dcd9e5;text-align:left;vertical-align:top}th{background:#ece9ff;font-size:10px;text-transform:uppercase}tr:nth-child(even){background:#fafafd}.content{margin:13px 0;padding:12px;border:1px solid #dedbe7;border-radius:8px;break-inside:avoid}.content img{max-width:100%;max-height:175mm}.content blockquote{margin:8px 0;padding:7px 10px;border-left:3px solid #6757df;background:#f5f3ff}.content pre{overflow-wrap:anywhere;padding:8px;background:#29273b;color:#fff;white-space:pre-wrap}.content code{font-family:monospace}.footer{margin-top:20px;padding-top:8px;border-top:1px solid #ddd;color:#777;font-size:9px}</style></head><body>'.$body.'</body></html>';
}

function course_pdf_html(PDO $pdo, int $courseId, int $teacherId): string
{
    $query=$pdo->prepare('SELECT * FROM courses WHERE id=?');$query->execute([$courseId]);$course=$query->fetch(PDO::FETCH_ASSOC);if(!$course||!teacher_can_access_course($pdo,$courseId,$teacherId))throw new TransferException('Parcours introuvable.');
    $items=$pdo->prepare('SELECT pi.*,p.title,p.estimated_minutes FROM pathway_items pi JOIN pages p ON p.id=pi.page_id WHERE pi.course_id=? ORDER BY pi.position');$items->execute([$courseId]);$rows='';
    foreach($items->fetchAll(PDO::FETCH_ASSOC) as $item){$type=$item['is_evaluation']?t('Évaluation').' · ×'.number_format((float)$item['evaluation_weight'],1,',',''):t('Étape');$rows.='<tr><td>'.(int)$item['position'].'</td><td><strong>'.e($item['title']).'</strong></td><td>'.e(pdf_date_fr($item['deadline'])).'</td><td>'.(int)$item['estimated_minutes'].' min</td><td>'.e($type).'</td></tr>';}
    $body='<header class="header"><div class="eyebrow">'.e(t('Parcours pédagogique')).'</div><h1>'.e($course['title']).'</h1><p class="muted">'.e($course['description']).'</p><div class="meta">'.pdf_meta([t('Code').' '.$course['code'],t($course['archived']?'Archivé':'Actif')]).'</div></header><table><thead><tr><th>'.e(t('No étape')).'</th><th>'.e(t('Nom')).'</th><th>'.e(t('Échéance')).'</th><th>'.e(t('Durée')).'</th><th>'.e(t('Type')).'</th></tr></thead><tbody>'.$rows.'</tbody></table><div class="footer">Export liike · '.e(date('d/m/Y H:i')).'</div>';
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
    $metaValues=[t('Étape :number',['number'=>(int)$item['position']]),t($item['is_evaluation']?'Évaluation':'Activité'),(int)$item['estimated_minutes'].' min',t('Échéance').' '.pdf_date_fr($item['deadline']),t($item['status']==='ready'?'Prête':'Brouillon')];
    foreach($tags->fetchAll(PDO::FETCH_COLUMN) as $tag)$metaValues[]='#'.$tag;
    $meta=pdf_meta($metaValues);
    $body='<header class="header"><div class="eyebrow">'.e($item['course_title']).' · '.e($item['course_code']).'</div><h1>'.e($item['title']).'</h1><p class="muted">'.e($item['summary']).'</p><div class="meta">'.$meta.'</div></header>';
    if(trim((string)$item['instructions'])!=='')$body.='<div class="notice"><strong>'.e(t('Consigne propre au parcours')).'</strong><p>'.nl2br(e($item['instructions'])).'</p></div>';
    $objectiveRows=$objectives->fetchAll(PDO::FETCH_COLUMN);$skillRows=$skills->fetchAll(PDO::FETCH_ASSOC);
    if($objectiveRows||$skillRows){$body.='<h2>'.e(t('Objectifs et compétences')).'</h2>';if($objectiveRows)$body.='<p><strong>'.e(t('Objectifs')).' :</strong> '.e(implode(' · ',$objectiveRows)).'</p>';if($skillRows)$body.='<p><strong>'.e(t('Compétences')).' :</strong> '.e(implode(' · ',array_map(fn($skill)=>$skill['code'].' — '.$skill['title'],$skillRows))).'</p>';}
    $blocks=$pdo->prepare('SELECT * FROM page_blocks WHERE page_id=? ORDER BY position');$blocks->execute([$item['page_id']]);$body.='<h2>'.e(t('Contenu détaillé')).'</h2>';
    foreach($blocks->fetchAll(PDO::FETCH_ASSOC) as $block){$body.='<section class="content">';if($block['type']==='markdown')$body.=Markdown::render($block['body']);elseif($block['type']==='image')$body.='<img src="'.e(pdf_local_image($block['body'])).'" alt="'.e($block['caption']).'"><p class="muted">'.e($block['caption']).'</p>';elseif($block['type']==='file')$body.='<strong>'.e(t('Fichier joint')).' :</strong> '.e($block['caption']?:basename($block['body'])).'<br><span class="muted">'.e($block['body']).'</span>';else $body.='<strong>'.e(t('Contenu intégré / vidéo')).' :</strong><br><span class="muted">'.e($block['body']).'</span>'; $body.='</section>';}
    $body.='<div class="footer">Export liike · '.e(date('d/m/Y H:i')).'</div>';return pdf_document($item['title'],$body,false);
}

function pdf_application_root(): string
{
    return is_file(APR_PUBLIC_ROOT.'/app/bootstrap.php')?APR_PUBLIC_ROOT:dirname(APR_PUBLIC_ROOT);
}

function pdf_vendor_autoload_path(?string $applicationRoot=null): ?string
{
    $root=rtrim($applicationRoot??pdf_application_root(),'/\\');
    foreach([$root.'/vendor/autoload.php',dirname($root).'/vendor/autoload.php'] as $candidate){
        if(is_file($candidate))return $candidate;
    }
    return null;
}

function load_pdf_engine(): void
{
    if(!extension_loaded('mbstring')||!extension_loaded('gd'))throw new TransferException('Le moteur PDF intégré nécessite les extensions PHP mbstring et gd. Utilisez l’aperçu imprimable.');
    if(!class_exists(\Mpdf\Mpdf::class,false)){
        $autoload=pdf_vendor_autoload_path();
        if($autoload===null)throw new TransferException('Le moteur PDF intégré est absent. Installez vendor/ à la racine de l’application ou dans son répertoire parent.');
        require_once $autoload;
    }
    if(!class_exists(\Mpdf\Mpdf::class))throw new TransferException('Le moteur PDF intégré ne peut pas être chargé. Utilisez l’aperçu imprimable.');
}

function render_pdf_bytes(string $html,bool $landscape=false): string
{
    load_pdf_engine();
    $cache=pdf_application_root().'/storage/pdf-cache';
    if(!is_dir($cache)&&!mkdir($cache,0770,true)&&!is_dir($cache))throw new TransferException('Le cache du moteur PDF ne peut pas être créé.');
    try{
        preg_match('/<title>(.*?)<\/title>/si',$html,$title);
        preg_match('/<body[^>]*>(.*)<\/body>/si',$html,$body);
        $pdf=new \Mpdf\Mpdf([
            'mode'=>'utf-8',
            'format'=>$landscape?'A4-L':'A4',
            'tempDir'=>$cache,
            'default_font'=>'dejavusans',
            'margin_left'=>14,
            'margin_right'=>14,
            'margin_top'=>14,
            'margin_bottom'=>14,
        ]);
        $pdf->SetTitle(strip_tags((string)($title?html_entity_decode($title[1],ENT_QUOTES|ENT_HTML5,'UTF-8'):'liike')));
        $pdf->WriteHTML('body{color:#29273b;font-family:dejavusans;font-size:10pt;line-height:1.45}h1{margin:0 0 5mm;font-size:22pt;color:#29273b}h2{margin:7mm 0 2.5mm;font-size:15pt;color:#29273b}h3{margin:5mm 0 2mm;font-size:12pt}p{margin:1.5mm 0}.muted{color:#706e80}.header{margin-bottom:6mm;padding-bottom:4mm;border-bottom:1mm solid #6757df}.eyebrow{color:#6757df;font-size:8pt;font-weight:bold;letter-spacing:.7pt;text-transform:uppercase}.meta{margin-top:3mm}.pill{display:inline-block;padding:1.3mm 2.3mm;background-color:#ece9ff;font-size:8pt}.meta-separator{padding:0 1.5mm;color:#8b879c;font-weight:bold}.notice{margin:4mm 0;padding:3mm;border-left:1mm solid #6757df;background-color:#f5f3ff}table{width:100%;border-collapse:collapse}thead{display:table-header-group}th,td{padding:2.5mm;border:.2mm solid #dcd9e5;text-align:left;vertical-align:top}th{background-color:#ece9ff;font-size:8pt;text-transform:uppercase}tr:nth-child(even){background-color:#fafafd}.content{margin:4mm 0;padding:4mm;border:.2mm solid #dedbe7;page-break-inside:avoid}.content img{max-width:100%;max-height:175mm}.content blockquote{margin:2mm 0;padding:2mm 3mm;border-left:1mm solid #6757df;background-color:#f5f3ff}.content pre{padding:3mm;background-color:#29273b;color:#fff;white-space:pre-wrap}.content code{font-family:dejavusansmono}.footer{margin-top:7mm;padding-top:3mm;border-top:.2mm solid #ddd;color:#777;font-size:7pt}',\Mpdf\HTMLParserMode::HEADER_CSS);
        $pdfBody=pdf_sanitize_html($body[1]??$html);
        $pdf->WriteHTML($pdfBody,\Mpdf\HTMLParserMode::HTML_BODY);
        $bytes=$pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
    }catch(Throwable $exception){
        throw new TransferException('La génération PDF a échoué. '.$exception->getMessage(),0,$exception);
    }
    if(!str_starts_with($bytes,'%PDF-')||strlen($bytes)<1000)throw new TransferException('La génération PDF a produit un document invalide.');
    return $bytes;
}

function send_pdf_download(string $html,string $filename,bool $landscape=false): never
{
    $bytes=render_pdf_bytes($html,$landscape);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',basename($filename)).'"');
    header('Content-Length: '.strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
}
