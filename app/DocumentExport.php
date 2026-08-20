<?php

declare(strict_types=1);

/**
 * @return array{title:string,markdown:string}
 */
function pathway_page_document_export(PDO $pdo, int $itemId, int $teacherId): array
{
    $query=$pdo->prepare('SELECT pi.*,p.reference,p.title,p.summary,p.status,p.estimated_minutes,p.id AS page_id,c.title AS course_title,c.code AS course_code FROM pathway_items pi JOIN pages p ON p.id=pi.page_id JOIN courses c ON c.id=pi.course_id WHERE pi.id=?');
    $query->execute([$itemId]);$item=$query->fetch(PDO::FETCH_ASSOC);
    if(!$item||!teacher_can_access_course($pdo,(int)$item['course_id'],$teacherId))throw new TransferException('Étape introuvable.');

    $tags=$pdo->prepare('SELECT t.name FROM tags t JOIN page_tags pt ON pt.tag_id=t.id WHERE pt.page_id=? ORDER BY t.name');$tags->execute([$item['page_id']]);
    $objectives=$pdo->prepare('SELECT title FROM page_objectives WHERE page_id=? ORDER BY position,id');$objectives->execute([$item['page_id']]);
    $skills=$pdo->prepare('SELECT s.code,s.title FROM course_skills s JOIN item_skills i ON i.skill_id=s.id WHERE i.pathway_item_id=? ORDER BY s.position');$skills->execute([$itemId]);
    $blocks=$pdo->prepare('SELECT type,body,caption FROM page_blocks WHERE page_id=? ORDER BY position,id');$blocks->execute([$item['page_id']]);

    $lines=['# '.document_markdown_text((string)$item['title']),''];
    if(trim((string)$item['summary'])!==''){$lines[]=document_markdown_text((string)$item['summary']);$lines[]='';}
    $lines[]='> **'.t('Parcours').' :** '.document_markdown_text((string)$item['course_title']).' (`'.document_markdown_code((string)$item['course_code']).'`)';
    $lines[]='> **'.t('Étape :number',['number'=>(int)$item['position']]).'** · '.t($item['is_evaluation']?'Évaluation':'Activité').' · '.(int)$item['estimated_minutes'].' min';
    $lines[]='> **'.t('Échéance').' :** '.pdf_date_fr($item['deadline']).' · **'.t('Statut').' :** '.t($item['status']==='ready'?'Prête':'Brouillon');
    $tagRows=$tags->fetchAll(PDO::FETCH_COLUMN);
    if($tagRows)$lines[]='> **'.t('Catégories').' :** '.implode(', ',array_map(static fn(string $tag):string=>'#'.document_markdown_text($tag),$tagRows));
    $lines[]='';

    if(trim((string)$item['instructions'])!==''){
        $lines[]='## '.t('Consigne propre au parcours');$lines[]='';$lines[]=trim((string)$item['instructions']);$lines[]='';
    }
    $objectiveRows=$objectives->fetchAll(PDO::FETCH_COLUMN);$skillRows=$skills->fetchAll(PDO::FETCH_ASSOC);
    if($objectiveRows){$lines[]='## '.t('Objectifs');$lines[]='';foreach($objectiveRows as $objective)$lines[]='- '.document_markdown_text((string)$objective);$lines[]='';}
    if($skillRows){$lines[]='## '.t('Compétences');$lines[]='';foreach($skillRows as $skill)$lines[]='- `'.document_markdown_code((string)$skill['code']).'` — '.document_markdown_text((string)$skill['title']);$lines[]='';}
    $lines[]='## '.t('Contenu détaillé');$lines[]='';

    foreach($blocks->fetchAll(PDO::FETCH_ASSOC) as $index=>$block){
        if($index>0){$lines[]='';$lines[]='---';$lines[]='';}
        $body=trim((string)$block['body']);$caption=trim((string)$block['caption']);
        if($block['type']==='markdown'){$lines[]=$body;continue;}
        $label=document_markdown_text($caption!==''?$caption:basename($body));
        if($block['type']==='image'){$lines[]='!['.$label.']('.document_markdown_link($body).')';continue;}
        if($block['type']==='file'){$lines[]='['.$label.']('.document_markdown_link($body).')';continue;}
        $lines[]='['.($label!==''?$label:t('Contenu intégré / vidéo')).']('.document_markdown_link($body).')';
    }
    $markdown=rtrim(implode("\n",$lines))."\n";
    return ['title'=>(string)$item['title'],'markdown'=>$markdown];
}

function document_markdown_text(string $value): string
{
    return str_replace(['\\','`','*','_','[',']'],['\\\\','\\`','\\*','\\_','\\[','\\]'],trim($value));
}

function document_markdown_code(string $value): string
{
    return str_replace('`','\\`',trim($value));
}

function document_markdown_link(string $value): string
{
    return str_replace([' ','(',')'],['%20','%28','%29'],trim($value));
}

function document_xml_text(string $value): string
{
    $value=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$value)??$value;
    return htmlspecialchars($value,ENT_QUOTES|ENT_XML1|ENT_SUBSTITUTE,'UTF-8');
}

/** @param array{bold?:bool,italic?:bool,code?:bool} $format */
function docx_run(string $text, array $format=[]): string
{
    if($text==='')return '';
    $properties='';
    if(!empty($format['bold']))$properties.='<w:b/>';
    if(!empty($format['italic']))$properties.='<w:i/>';
    if(!empty($format['code']))$properties.='<w:rFonts w:ascii="Courier New" w:hAnsi="Courier New"/><w:shd w:fill="F1F1F4"/>';
    return '<w:r>'.($properties!==''?'<w:rPr>'.$properties.'</w:rPr>':'').'<w:t xml:space="preserve">'.document_xml_text($text).'</w:t></w:r>';
}

function docx_inline_runs(string $text): string
{
    $pattern='/\*\*[^*\n]+\*\*|`[^`\n]+`|\*[^*\n]+\*|!?\[[^\]\n]+\]\([^)\s]+\)/u';
    preg_match_all($pattern,$text,$matches,PREG_OFFSET_CAPTURE);
    $xml='';$offset=0;
    foreach($matches[0] as [$token,$position]){
        $plain=substr($text,$offset,$position-$offset);$xml.=docx_run(preg_replace('/\\\\([\\\\`*_\[\]])/u','$1',$plain)??$plain);$offset=$position+strlen($token);
        if(str_starts_with($token,'**'))$xml.=docx_run(substr($token,2,-2),['bold'=>true]);
        elseif(str_starts_with($token,'`'))$xml.=docx_run(substr($token,1,-1),['code'=>true]);
        elseif(str_starts_with($token,'*'))$xml.=docx_run(substr($token,1,-1),['italic'=>true]);
        elseif(preg_match('/^(!?)\[([^]]+)]\(([^)]+)\)$/u',$token,$link))$xml.=docx_run(($link[1]==='!'?t('Image').' : ':'').$link[2].' ('.$link[3].')');
    }
    $tail=substr($text,$offset);return $xml.docx_run(preg_replace('/\\\\([\\\\`*_\[\]])/u','$1',$tail)??$tail);
}

function docx_paragraph(string $text='', ?string $style=null, int $indent=0, bool $pageBreak=false, bool $literal=false): string
{
    $properties='';
    if($style!==null)$properties.='<w:pStyle w:val="'.document_xml_text($style).'"/>';
    if($indent>0)$properties.='<w:ind w:left="'.($indent*360).'"/>';
    $body=$pageBreak?'<w:r><w:br w:type="page"/></w:r>':($literal?docx_run($text):docx_inline_runs($text));
    return '<w:p>'.($properties!==''?'<w:pPr>'.$properties.'</w:pPr>':'').$body.'</w:p>';
}

function render_docx_bytes(string $markdown, string $title): string
{
    $lines=explode("\n",str_replace(["\r\n","\r"],"\n",$markdown));$paragraphs=[];$inCode=false;
    foreach($lines as $line){
        $trim=trim($line);
        if(preg_match('/^```/',$trim)){$inCode=!$inCode;continue;}
        if($inCode){$paragraphs[]=docx_paragraph($line,'Code',0,false,true);continue;}
        if(preg_match('/^<div\s+style\s*=\s*(["\'])\s*page-break-after\s*:\s*always\s*;?\s*\1\s*>\s*<\/div\s*>$/i',$trim)){$paragraphs[]=docx_paragraph('',null,0,true);continue;}
        if($trim===''){$paragraphs[]=docx_paragraph();continue;}
        if(preg_match('/^(#{1,6})[ \t]+(.+?)(?:[ \t]+#+[ \t]*)?$/u',$trim,$heading)){$paragraphs[]=docx_paragraph($heading[2],'Heading'.strlen($heading[1]));continue;}
        if(preg_match('/^( *)([-*+]|\d+[.)])\s+(.+)$/u',str_replace("\t",'    ',$line),$list)){$depth=intdiv(strlen($list[1]),2)+1;$marker=preg_match('/^\d/',$list[2])?$list[2]:'•';$paragraphs[]=docx_paragraph($marker.' '.$list[3],'ListParagraph',$depth);continue;}
        if(preg_match('/^>\s?(.*)$/u',$trim,$quote)){$paragraphs[]=docx_paragraph($quote[1],'Quote');continue;}
        if(preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/',preg_replace('/\s+/','',$trim)??'')){$paragraphs[]=docx_paragraph('────────────────────────','Separator');continue;}
        if($trim===':::qcm'){$paragraphs[]=docx_paragraph(t('QCM'),'Heading3');continue;}
        if($trim===':::')continue;
        $paragraphs[]=docx_paragraph($line);
    }

    $document='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.implode('',$paragraphs).'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>';
    $styles='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos"/><w:sz w:val="22"/></w:rPr></w:rPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:spacing w:before="280" w:after="160"/></w:pPr><w:rPr><w:b/><w:color w:val="29273B"/><w:sz w:val="36"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:spacing w:before="240" w:after="120"/></w:pPr><w:rPr><w:b/><w:color w:val="6757DF"/><w:sz w:val="30"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:rPr><w:b/><w:sz w:val="26"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading4"><w:name w:val="heading 4"/><w:basedOn w:val="Heading3"/></w:style><w:style w:type="paragraph" w:styleId="Heading5"><w:name w:val="heading 5"/><w:basedOn w:val="Heading3"/></w:style><w:style w:type="paragraph" w:styleId="Heading6"><w:name w:val="heading 6"/><w:basedOn w:val="Heading3"/></w:style><w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:basedOn w:val="Normal"/></w:style><w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/><w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="360"/><w:spacing w:before="80" w:after="80"/></w:pPr><w:rPr><w:i/><w:color w:val="555166"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Code"><w:name w:val="Code"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:before="0" w:after="0"/><w:shd w:fill="29273B"/></w:pPr><w:rPr><w:rFonts w:ascii="Courier New" w:hAnsi="Courier New"/><w:color w:val="FFFFFF"/><w:sz w:val="19"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Separator"><w:name w:val="Separator"/><w:basedOn w:val="Normal"/><w:rPr><w:color w:val="B6B2C1"/></w:rPr></w:style></w:styles>';
    $created=gmdate('Y-m-d\TH:i:s\Z');
    $entries=[
        '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
        '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
        'word/document.xml'=>$document,
        'word/styles.xml'=>$styles,
        'word/_rels/document.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'docProps/core.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.document_xml_text($title).'</dc:title><dc:creator>liike</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:modified></cp:coreProperties>',
        'docProps/app.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>liike</Application></Properties>',
    ];
    return document_zip_bytes($entries);
}

function latex_escape(string $value): string
{
    return strtr($value,[
        '\\'=>'\\textbackslash{}','{'=>'\\{','}'=>'\\}','$'=>'\\$','&'=>'\\&','#'=>'\\#',
        '^'=>'\\textasciicircum{}','_'=>'\\_','%'=>'\\%','~'=>'\\textasciitilde{}',
        '|'=>'\\textbar{}','<'=>'\\textless{}','>'=>'\\textgreater{}',
    ]);
}

function latex_markdown_unescape(string $value): string
{
    return preg_replace('/\\\\([\\\\`*_\[\]])/u','$1',$value)??$value;
}

function latex_url(string $value): string
{
    return str_replace(['{','}'],['%7B','%7D'],trim($value));
}

function latex_inline(string $text): string
{
    $pattern='/\*\*[^*\n]+\*\*|`[^`\n]+`|\*[^*\n]+\*|!?\[[^\]\n]+\]\([^)\s]+\)/u';
    preg_match_all($pattern,$text,$matches,PREG_OFFSET_CAPTURE);$latex='';$offset=0;
    foreach($matches[0] as [$token,$position]){
        $latex.=latex_escape(latex_markdown_unescape(substr($text,$offset,$position-$offset)));$offset=$position+strlen($token);
        if(str_starts_with($token,'**'))$latex.='\\textbf{'.latex_escape(latex_markdown_unescape(substr($token,2,-2))).'}';
        elseif(str_starts_with($token,'`'))$latex.='\\texttt{'.latex_escape(substr($token,1,-1)).'}';
        elseif(str_starts_with($token,'*'))$latex.='\\emph{'.latex_escape(latex_markdown_unescape(substr($token,1,-1))).'}';
        elseif(preg_match('/^(!?)\[([^]]+)]\(([^)]+)\)$/u',$token,$link)){
            $label=latex_escape(latex_markdown_unescape($link[2]));$url=latex_url($link[3]);
            $latex.=($link[1]==='!'?'\\textbf{'.latex_escape(t('Image')).' : '.$label.'}':$label).' (\\url{'.$url.'})';
        }
    }
    return $latex.latex_escape(latex_markdown_unescape(substr($text,$offset)));
}

function render_latex_document(string $markdown, string $title): string
{
    $markdown=preg_replace('/[\x{10000}-\x{10FFFF}\x{200D}\x{20E3}\x{FE0E}\x{FE0F}]/u','',$markdown)??$markdown;
    $lines=explode("\n",str_replace(["\r\n","\r"],"\n",$markdown));$body=[];$inCode=false;
    foreach($lines as $line){
        $trim=trim($line);
        if(preg_match('/^```/',$trim)){
            $body[]=$inCode?'\\end{verbatim}':'\\begin{verbatim}';$inCode=!$inCode;continue;
        }
        if($inCode){$body[]=str_replace('\\end{verbatim}','\\end {verbatim}',$line);continue;}
        if(preg_match('/^<div\s+style\s*=\s*(["\'])\s*page-break-after\s*:\s*always\s*;?\s*\1\s*>\s*<\/div\s*>$/i',$trim)){$body[]='\\newpage';continue;}
        if($trim===''){$body[]='';continue;}
        if(preg_match('/^(#{1,6})[ \t]+(.+?)(?:[ \t]+#+[ \t]*)?$/u',$trim,$heading)){
            $commands=[1=>'section*',2=>'subsection*',3=>'subsubsection*',4=>'paragraph*',5=>'subparagraph*',6=>'textbf'];
            $body[]='\\'.$commands[strlen($heading[1])].'{'.latex_inline($heading[2]).'}';continue;
        }
        if(preg_match('/^( *)([-*+]|\d+[.)])\s+(.+)$/u',str_replace("\t",'    ',$line),$list)){
            $depth=intdiv(strlen($list[1]),2);$marker=preg_match('/^\d/',$list[2])?latex_escape($list[2]):'\\textbullet';
            $body[]='\\noindent\\hspace*{'.number_format($depth*1.2,1,'.','').'em}'.$marker.'\\ '.latex_inline($list[3]).'\\par';continue;
        }
        if(preg_match('/^>\s?(.*)$/u',$trim,$quote)){$body[]='\\begin{quote}'.latex_inline($quote[1]).'\\end{quote}';continue;}
        if(preg_match('/^(?:-{3,}|\*{3,}|_{3,})$/',preg_replace('/\s+/','',$trim)??'')){$body[]='\\par\\noindent\\rule{\\linewidth}{0.4pt}\\par';continue;}
        if($trim===':::qcm'){$body[]='\\subsubsection*{'.latex_escape(t('QCM')).'}';continue;}
        if($trim===':::')continue;
        $body[]=latex_inline($line).'\\par';
    }
    if($inCode)$body[]='\\end{verbatim}';
    return implode("\n",[
        '\\documentclass[11pt,a4paper]{article}',
        '\\usepackage[T1]{fontenc}',
        '\\usepackage[utf8]{inputenc}',
        '\\usepackage[margin=2cm]{geometry}',
        '\\usepackage{xcolor}',
        '\\usepackage[hidelinks]{hyperref}',
        '\\usepackage{url}',
        '\\setlength{\\parindent}{0pt}',
        '\\setlength{\\parskip}{0.45em}',
        '\\definecolor{liikePurple}{HTML}{6757DF}',
        '\\hypersetup{pdftitle={'.latex_escape($title).'}}',
        '\\begin{document}',
        implode("\n",$body),
        '\\end{document}',
        '',
    ]);
}

/** @param array<string,string> $entries */
function document_zip_bytes(array $entries): string
{
    $local='';$central='';$offset=0;$now=getdate();$year=max(1980,(int)$now['year']);
    $dosTime=((int)$now['hours']<<11)|((int)$now['minutes']<<5)|intdiv((int)$now['seconds'],2);
    $dosDate=(($year-1980)<<9)|((int)$now['mon']<<5)|(int)$now['mday'];
    foreach($entries as $name=>$contents){
        $compressed=gzdeflate($contents,6);$method=$compressed===false?0:8;if($compressed===false)$compressed=$contents;
        $flags=0x0800;$crc=crc32($contents);$compressedSize=strlen($compressed);$size=strlen($contents);$nameLength=strlen($name);
        $header=pack('VvvvvvVVVvv',0x04034b50,20,$flags,$method,$dosTime,$dosDate,$crc,$compressedSize,$size,$nameLength,0).$name;
        $local.=$header.$compressed;
        $central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,$flags,$method,$dosTime,$dosDate,$crc,$compressedSize,$size,$nameLength,0,0,0,0,0,$offset).$name;
        $offset+=strlen($header)+$compressedSize;
    }
    $count=count($entries);
    return $local.$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),strlen($local),0);
}

function send_markdown_download(string $markdown, string $filename): never
{
    header('Content-Type: text/markdown; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',basename($filename)).'"');
    header('Content-Length: '.strlen($markdown));header('Cache-Control: private, no-store');echo $markdown;exit;
}

function send_docx_download(string $markdown, string $title, string $filename): never
{
    $bytes=render_docx_bytes($markdown,$title);
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',basename($filename)).'"');
    header('Content-Length: '.strlen($bytes));header('Cache-Control: private, no-store');echo $bytes;exit;
}

function send_latex_download(string $markdown, string $title, string $filename): never
{
    $latex=render_latex_document($markdown,$title);
    header('Content-Type: application/x-tex; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',basename($filename)).'"');
    header('Content-Length: '.strlen($latex));header('Cache-Control: private, no-store');echo $latex;exit;
}
