<?php
declare(strict_types=1);

/** Guides contain reviewed, static French HTML, never user-provided markup. */
function user_guide_data(string $role): array
{
    if(!in_array($role,['student','teacher'],true))throw new InvalidArgumentException('Guide introuvable.');
    return require __DIR__.'/guides/'.$role.'.php';
}

function user_guide_escape(string $text): string
{
    return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function user_guide_filename(string $role): string
{
    return match($role){
        'teacher'=>'guide-liike-enseignants.pdf',
        'student'=>'guide-liike-apprenants.pdf',
        default=>throw new InvalidArgumentException('Guide introuvable.'),
    };
}

function user_guide_pages(array $guide,bool $pdf=false): string
{
    $html='';
    foreach($guide['pages'] as $index=>$page){
        if($pdf&&$index)$html.='<pagebreak />';
        $html.='<section class="guide-page" id="'.user_guide_escape($page['id']).'" aria-labelledby="guide-title-'.$index.'">'
            .'<p class="guide-kicker">'.str_pad((string)($index+1),2,'0',STR_PAD_LEFT).' / '.str_pad((string)count($guide['pages']),2,'0',STR_PAD_LEFT).' · '.user_guide_escape($guide['audience']).'</p>'
            .'<h2 id="guide-title-'.$index.'">'.user_guide_escape($page['title']).'</h2>'
            .'<p class="guide-lead">'.user_guide_escape($page['lead']).'</p>';
        foreach($page['cards'] as $card)$html.='<div class="guide-card"><h3>'.user_guide_escape($card['title']).'</h3>'.$card['html'].'</div>';
        $html.='<aside class="guide-tip"><h3>'.user_guide_escape($page['tip_title']).'</h3><p>'.user_guide_escape($page['tip']).'</p></aside></section>';
    }
    return $html;
}

function view_user_guide(): void
{
    $user=require_actor();$guide=user_guide_data($user['role']);
    ?>
    <link rel="stylesheet" href="assets/guides/guides.css?v=1">
    <article id="top" class="user-guide" lang="fr" data-user-guide="<?=e($user['role'])?>">
      <header class="guide-hero">
        <div><p class="guide-kicker">liike · Le guide pratique · <?=e($guide['audience'])?></p><h1><?=e($guide['title'])?></h1><p><?=e($guide['subtitle'])?></p></div>
        <a class="button primary guide-download reader-pdf-download" href="<?=e(route('user-guide',['format'=>'pdf']))?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>Télécharger le PDF</span></a>
        <p class="guide-edition"><?=count($guide['pages'])?> pages · Français · Édition novembre 2026</p>
      </header>
      <nav class="guide-toc" aria-label="Sommaire du guide"><h2>Dans ce guide</h2><ol><?php foreach($guide['pages'] as $page): ?><li><a href="#<?=e($page['id'])?>"><?=e($page['title'])?></a></li><?php endforeach; ?></ol></nav>
      <?=user_guide_pages($guide)?>
      <footer class="guide-end"><p>Retrouvez ce guide à tout moment dans le menu de votre profil.</p><a href="#top">Retour au début du guide ↑</a></footer>
    </article>
    <?php
}

/** Read a prepared document: no PDF engine or per-user cache on the request path. */
function send_user_guide_pdf(string $role): never
{
    $filename=user_guide_filename($role);$path=APR_PUBLIC_ROOT.'/assets/guides/'.$filename;
    if(!is_file($path)){http_response_code(503);header('Content-Type: text/plain; charset=UTF-8');echo 'Le guide PDF est momentanément indisponible.';exit;}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Length: '.filesize($path));
    readfile($path);exit;
}

/** Offline build only; the web application serves the generated files. */
function user_guide_pdf_bytes(string $role,string $temporaryDirectory): string
{
    $guide=user_guide_data($role);load_pdf_engine();
    $pdf=new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','tempDir'=>$temporaryDirectory,'default_font'=>'dejavusans',
        'margin_left'=>18,'margin_right'=>18,'margin_top'=>22,'margin_bottom'=>20,'margin_header'=>10,'margin_footer'=>10]);
    $pdf->SetTitle($guide['title'].' · '.$guide['audience']);$pdf->SetAuthor('liike');$pdf->SetSubject($guide['subtitle']);
    $pdf->SetHTMLHeader('<table width="100%" style="font-size:8pt;color:#6d6684;border-bottom:0.3mm solid #ddd7ee"><tr><td><b style="color:#6757df">liike</b> / LE GUIDE PRATIQUE</td><td align="right">'.user_guide_escape($guide['audience']).'</td></tr></table>');
    $pdf->SetHTMLFooter('<table width="100%" style="font-size:8pt;color:#6d6684"><tr><td>Édition novembre 2026 · liike</td><td align="right">{PAGENO} / {nbpg}</td></tr></table>');
    $pdf->WriteHTML('body{font-family:dejavusans;font-size:10pt;line-height:1.48;color:#302b42}h2{font-size:25pt;line-height:1.16;color:#302445;margin:2mm 0 3mm}h3{font-size:12pt;color:#5542a5;margin:4mm 0 1.4mm}p{margin:1.5mm 0}ul,ol{margin:2mm 0;padding-left:6mm}li{margin-bottom:1mm}strong{color:#302445}.guide-kicker{font-size:8pt;font-weight:bold;letter-spacing:1pt;color:#6757df;margin:0 0 2mm}.guide-lead{font-size:11pt;color:#635a76;border-bottom:.6mm solid #d6cdef;padding-bottom:4mm;margin-bottom:4mm}.guide-card{margin-bottom:3mm}.guide-card h3{page-break-after:avoid}.guide-tip{margin-top:5mm;padding:3.5mm 4mm;background-color:#edf7f5;border-left:1mm solid #32816f;page-break-inside:avoid}.guide-tip h3{margin:0 0 1.5mm;color:#246653;font-size:11pt}.guide-tip p{font-size:9.5pt;margin:0}',\Mpdf\HTMLParserMode::HEADER_CSS);
    $pdf->WriteHTML(user_guide_pages($guide,true),\Mpdf\HTMLParserMode::HTML_BODY);
    return $pdf->Output('',\Mpdf\Output\Destination::STRING_RETURN);
}
