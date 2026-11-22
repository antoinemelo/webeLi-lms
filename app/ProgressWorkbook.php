<?php
declare(strict_types=1);

require_once __DIR__.'/DocumentExport.php';

/** Small OpenXML report writer. User text is always inline text, never a formula. */
final class ProgressWorkbookSheet
{
    public array $rows=[],$merges=[],$links=[],$bars=[],$breaks=[];
    public int $freeze=0;
    public ?string $filter=null;
    public function __construct(public string $name,public array $widths){}
    public static function column(int $index): string {
        $result='';for($index++;$index>0;$index=intdiv($index-1,26))$result=chr(65+($index-1)%26).$result;return $result;
    }
    public static function xml(string $text): string {
        return htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','',$text)??'',ENT_XML1|ENT_QUOTES,'UTF-8');
    }
    public function row(array $values,int $style=5,?float $height=null): int {
        $number=count($this->rows)+1;$cells='';$lines=1;
        foreach($values as $index=>$value){
            $cellStyle=$style;if(is_array($value)){[$value,$cellStyle]=$value;}
            $address=self::column($index).$number;
            if($value===null||$value===''){$cells.='<c r="'.$address.'" s="'.$cellStyle.'"/>';continue;}
            if(is_int($value)||is_float($value))$cells.='<c r="'.$address.'" s="'.$cellStyle.'"><v>'.$value.'</v></c>';
            else{
                $value=(string)$value;$cells.='<c r="'.$address.'" s="'.$cellStyle.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml($value).'</t></is></c>';
                $lineCount=0;foreach(explode("\n",$value) as $line)$lineCount+=max(1,(int)ceil(mb_strwidth($line,'UTF-8')/max(5,($this->widths[$index]??20)-2)));
                $lines=max($lines,$lineCount);
            }
        }
        $height??=min(400,max(24,16*$lines+8));
        $this->rows[]='<row r="'.$number.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';return $number;
    }
    public function full(string $text,int $style=3,?float $height=null): int {
        $width=array_sum($this->widths)-4;$lines=0;foreach(explode("\n",$text) as $line)$lines+=max(1,(int)ceil(mb_strwidth($line,'UTF-8')/$width));
        $row=$this->row([$text],$style,$height??max(25,17*$lines+9));
        $this->merges[]='A'.$row.':'.self::column(count($this->widths)-1).$row;return $row;
    }
    public function output(): string {
        $last=self::column(count($this->widths)-1).count($this->rows);
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:'.$last.'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        if($this->freeze)$xml.='<pane ySplit="'.$this->freeze.'" topLeftCell="A'.($this->freeze+1).'" activePane="bottomLeft" state="frozen"/>';
        $xml.='</sheetView></sheetViews><sheetFormatPr defaultRowHeight="24"/><cols>';
        foreach($this->widths as $i=>$width)$xml.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
        $xml.='</cols><sheetData>'.implode('',$this->rows).'</sheetData>';
        if($this->filter)$xml.='<autoFilter ref="'.$this->filter.'"/>';
        if($this->merges){$xml.='<mergeCells count="'.count($this->merges).'">';foreach($this->merges as $merge)$xml.='<mergeCell ref="'.$merge.'"/>';$xml.='</mergeCells>';}
        foreach($this->bars as $index=>$range)$xml.='<conditionalFormatting sqref="'.$range.'"><cfRule type="dataBar" priority="'.($index+1).'"><dataBar><cfvo type="num" val="0"/><cfvo type="num" val="3"/><color rgb="FFBCB1EC"/></dataBar></cfRule></conditionalFormatting>';
        if($this->links){$xml.='<hyperlinks>';foreach($this->links as $index=>$link)$xml.='<hyperlink ref="'.$link['ref'].'" r:id="rId'.($index+1).'"/>';$xml.='</hyperlinks>';}
        $xml.='<printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/><headerFooter><oddFooter>&amp;Lliike · '.self::xml($this->name).'&amp;R&amp;P / &amp;N</oddFooter></headerFooter>';
        if($this->breaks){$xml.='<rowBreaks count="'.count($this->breaks).'" manualBreakCount="'.count($this->breaks).'">';foreach($this->breaks as $row)$xml.='<brk id="'.$row.'" max="16383" man="1"/>';$xml.='</rowBreaks>';}
        return $xml.'</worksheet>';
    }
}

function progress_workbook_date(?string $value,bool $dateOnly=false): ?float
{
    if(!$value)return null;
    $local=$dateOnly?substr($value,0,10):progress_export_date($value);
    return (new DateTimeImmutable($local,new DateTimeZone('UTC')))->getTimestamp()/86400+25569;
}

function progress_workbook_styles(): string
{
    $formats=[164=>'dd/mm/yyyy hh:mm',165=>'0.00" / 10"',166=>'0.00" / 3"',167=>'"× "0.##',168=>'dd/mm/yyyy'];
    $xml='<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="5">';
    foreach($formats as $id=>$format)$xml.='<numFmt numFmtId="'.$id.'" formatCode="'.ProgressWorkbookSheet::xml($format).'"/>';
    $xml.='</numFmts><fonts count="5"><font><sz val="11"/><color rgb="FF29273B"/><name val="Calibri"/></font><font><b/><sz val="20"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><sz val="10"/><color rgb="FF706E80"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF40366F"/><name val="Calibri"/></font><font><u/><sz val="11"/><color rgb="FF5344BB"/><name val="Calibri"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF5344BB"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFECE9FF"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><bottom style="hair"><color rgb="FFDDD9E9"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="15">';
    foreach([[0,0,0,0],[1,2,0,0],[2,0,0,0],[3,3,0,0],[3,3,1,0],[0,0,1,0],[0,0,1,9],[0,0,1,2],[0,0,1,164],[0,0,1,165],[0,0,1,166],[0,0,1,167],[4,0,0,0],[0,0,1,168],[0,0,1,1]] as [$font,$fill,$border,$format]){
        $xml.='<xf xfId="0" numFmtId="'.$format.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"'.($format?' horizontal="center"':'').'/></xf>';
    }
    return $xml.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}

/** Keep even very short lines within Excel's maximum row height. */
function progress_workbook_note_chunks(string $note): array
{
    $chunks=[];$current='';
    foreach(preg_split('/(?<=\n)/u',$note) as $line)foreach(mb_str_split($line,700,'UTF-8') as $part){
        if($current!==''&&(mb_strlen($current.$part)>700||substr_count($current,"\n")>=15)){$chunks[]=$current;$current='';}
        $current.=$part;
    }
    if($current!=='')$chunks[]=$current;
    return $chunks;
}

function course_progress_export_xlsx(array $data): string
{
    $sheets=[];$summary=new ProgressWorkbookSheet(t('Récapitulatif'),[28,10,30,14,17,13,12,17,17,17,22]);
    $summary->full($data['course']['title'],1,36);
    $summary->full($data['course']['code'].' · '.t('Date de l’export').' : '.progress_export_date($data['exported_at']).' (réf. UTC Europe/Zurich)',2);
    $summary->full(t('Les moyennes suivent les règles du tableau de bord. La dernière activité couvre le dernier mois.'),2);
    $header=$summary->row(array_map('t',['Apprenant','Groupe classe','Courriel','Progression','Étapes réalisées','Étapes confirmées','À confirmer','Éval. /10','Autoévaluations /3','Encouragements','Dernière activité']),4,36);
    foreach($data['students'] as $student)$summary->row([progress_report_name($student),$student['class_group'],$student['email'],[$student['total']?$student['done']/$student['total']:0,6],$student['done'].' / '.$student['total'],[(int)$student['confirmed'],14],[(int)$student['waiting'],14],[$student['evaluation_average']===null?null:(float)$student['evaluation_average'],9],[$student['self_average']===null?null:(float)$student['self_average'],10],[(int)$student['points'],14],[progress_workbook_date($student['last_activity_at']),8]]);
    $summary->freeze=$header;$summary->filter='A'.$header.':'.ProgressWorkbookSheet::column(count($summary->widths)-1).count($summary->rows);$sheets[]=$summary;
    if($data['mode']==='detailed')foreach($data['students'] as $student){
        $name=preg_replace('~[\\\\/?*\[\]:]~u',' ',progress_report_name($student));$name=trim($name," '");$suffix=' · '.$student['id'];
        $sheet=new ProgressWorkbookSheet(mb_substr($name,0,31-mb_strlen($suffix)).$suffix,[44,16,16,16,10,16,16]);
        $report=progress_report_student($data,$student);
        $sheet->full(progress_report_name($student),1,36);
        $sheet->full($data['course']['title'].' · '.$data['course']['code'].' · '.progress_report_contact($student),2);
        $sheet->full(t('Date de l’export').' : '.progress_export_date($data['exported_at']).' (réf. UTC Europe/Zurich)',2);
        if(isset($data['report_urls'][(int)$student['id']]))$sheet->links[]=['ref'=>'A1','url'=>$data['report_urls'][(int)$student['id']]];
        $metricsHeader=$sheet->row([t('Progression'),null,t('Éval. /10'),null,null,t('Autoévaluations /3'),null],3,28);
        $metricsValues=$sheet->row([[$student['total']?$student['done']/$student['total']:0,6],null,[$student['evaluation_average']===null?null:(float)$student['evaluation_average'],9],null,null,[$student['self_average']===null?null:(float)$student['self_average'],10],null],5,30);
        foreach([$metricsHeader,$metricsValues] as $r)foreach([['A','B'],['C','E'],['F','G']] as [$left,$right])$sheet->merges[]=$left.$r.':'.$right.$r;
        $sheet->full(t('Étapes réalisées').' : '.$student['done'].' / '.$student['total'].' · '.t('Étapes confirmées').' : '.(int)$student['confirmed'].' · '.t('Encouragements').' : '.(int)$student['points'].' · '.t('À confirmer').' : '.(int)$student['waiting'].' · '.t('Dernière activité').' : '.(progress_export_date($student['last_activity_at'])?:'-'),2);
        $sheet->freeze=count($sheet->rows);
        if(!$report['sections'])$sheet->full(t('Ce parcours ne contient aucune étape.'),2);
        foreach($report['sections'] as $section){
            if($section['title']!==null)$sheet->full($section['title'],3);
            if(!$section['rows']){$sheet->full(t('Aucune étape'),2);continue;}
            $sheet->row(array_map('t',['Étape','Échéance','Réalisation','Éval. /10','×','Niveau apprenant /3','Niveau enseignant /3']),4,36);
            foreach($section['rows'] as $row)$sheet->row([$row['title'],[progress_workbook_date($row['deadline'],true),13],[progress_workbook_date($row['completed_at'],true),13],[$row['evaluation']===null?null:(float)$row['evaluation'],9],[$row['weight']===null?null:(float)$row['weight'],11],[$row['student_level']===null?null:(float)$row['student_level'],10],[$row['teacher_level']===null?null:(float)$row['teacher_level'],10]]);
        }
        $quizzes=[];foreach($report['sections'] as $section)foreach($section['rows'] as $row)foreach($row['quizzes'] as $quiz)$quizzes[]=$quiz+['step'=>$row['title']];
        if($quizzes){
            $sheet->breaks[]=count($sheet->rows);
            $sheet->full(t('Résultat QCM /10'),3);
            $r=$sheet->row([t('Étape'),t('QCM'),null,null,null,t('Résultat QCM /10'),t('Dernière réponse au QCM')],4,30);$sheet->merges[]='B'.$r.':E'.$r;
            foreach($quizzes as $quiz){$r=$sheet->row([$quiz['step'],$quiz['title'],null,null,null,[$quiz['score'],9],[progress_workbook_date($quiz['answered_at']),8]],5,max(28,18*ceil(mb_strwidth($quiz['title'])/48)+8,18*ceil(mb_strwidth($quiz['step'])/40)+8));$sheet->merges[]='B'.$r.':E'.$r;}
        }
        if($report['comments'])$sheet->full(t('Commentaires'),3);
        foreach($report['comments'] as $comment){
            $sheet->full($comment['title'],4);
            foreach(['student'=>'Apprenant','teacher'=>'Enseignant'] as $key=>$label)if($comment[$key]!==''){
                $note=t($label).' : '.$comment[$key];
                // Excel caps row height; split long notes without losing their text.
                foreach(progress_workbook_note_chunks($note) as $chunk)$sheet->full($chunk,5);
            }
        }
        foreach(['objective'=>'Objectifs','skill'=>'Compétences'] as $kind=>$label){
            $r=$sheet->row([mb_strtoupper(t($label),'UTF-8'),null,null,null,null,t('ENS/FORM'),t('APR')],4,30);$sheet->merges[]='A'.$r.':E'.$r;$first=$r+1;
            if(empty($report['mastery'][$kind])){$sheet->full(t('Aucun élément évalué.'),2);continue;}
            foreach($report['mastery'][$kind] as $level){
                $title=($level['code']!==''?$level['code'].' · ':'').$level['title'];
                $r=$sheet->row([$title,null,null,null,null,[$level['teacher_level'],10],[$level['student_level'],10]],5,max(24,16*ceil(mb_strwidth($title)/90)+6));$sheet->merges[]='A'.$r.':E'.$r;
            }
            $sheet->bars[]='F'.$first.':G'.$r;
        }
        $sheets[]=$sheet;
    }
    $namespace='http://schemas.openxmlformats.org';$entries=[];
    $types='<?xml version="1.0" encoding="UTF-8"?><Types xmlns="'.$namespace.'/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $workbook='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.$namespace.'/spreadsheetml/2006/main" xmlns:r="'.$namespace.'/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>';
    $rels='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="'.$namespace.'/package/2006/relationships"><Relationship Id="styles" Type="'.$namespace.'/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    foreach($sheets as $index=>$sheet){
        $id=$index+1;$types.='<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $workbook.='<sheet name="'.ProgressWorkbookSheet::xml($sheet->name).'" sheetId="'.$id.'" r:id="sheet'.$id.'"/>';
        $rels.='<Relationship Id="sheet'.$id.'" Type="'.$namespace.'/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
        $entries['xl/worksheets/sheet'.$id.'.xml']=$sheet->output();
        if($sheet->links){$links='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="'.$namespace.'/package/2006/relationships">';foreach($sheet->links as $n=>$link)$links.='<Relationship Id="rId'.($n+1).'" Type="'.$namespace.'/officeDocument/2006/relationships/hyperlink" Target="'.ProgressWorkbookSheet::xml($link['url']).'" TargetMode="External"/>';$entries['xl/worksheets/_rels/sheet'.$id.'.xml.rels']=$links.'</Relationships>';}
    }
    $workbook.='</sheets><definedNames>';
    foreach($sheets as $index=>$sheet)$workbook.='<definedName name="_xlnm.Print_Area" localSheetId="'.$index.'">'.ProgressWorkbookSheet::xml("'".str_replace("'","''",$sheet->name)."'!\$A\$1:\$".ProgressWorkbookSheet::column(count($sheet->widths)-1).'$'.count($sheet->rows)).'</definedName>';
    $entries['[Content_Types].xml']=$types.'</Types>';$entries['xl/workbook.xml']=$workbook.'</definedNames></workbook>';$entries['xl/_rels/workbook.xml.rels']=$rels.'</Relationships>';$entries['xl/styles.xml']=progress_workbook_styles();
    $entries['_rels/.rels']='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="'.$namespace.'/package/2006/relationships"><Relationship Id="workbook" Type="'.$namespace.'/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    return document_zip_bytes($entries);
}
function send_progress_workbook(array $data,string $filename): never
{
    $bytes=course_progress_export_xlsx($data);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',$filename).'"');
    header('X-Content-Type-Options: nosniff');header('Content-Length: '.strlen($bytes));echo $bytes;exit;
}
