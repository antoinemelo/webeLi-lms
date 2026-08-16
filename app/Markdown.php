<?php

declare(strict_types=1);

final class Markdown
{
    public static function render(string $source): string
    {
        $source = trim(str_replace(["\r\n", "\r"], "\n", $source));
        if ($source === '') return '';
        $lines = explode("\n", $source);
        $out = [];
        $paragraph = [];
        $list = [];
        $quote = [];
        $code = [];
        $inCode = false;

        $flushParagraph = static function () use (&$paragraph, &$out): void {
            if ($paragraph) {
                $out[] = '<p>' . self::inline(implode("\n", $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $flushList = static function () use (&$list, &$out): void {
            if ($list) {
                $out[] = self::renderList($list);
                $list = [];
            }
        };
        $flushQuote = static function () use (&$quote, &$out): void {
            if ($quote) { $out[] = '<blockquote>' . self::render(implode("\n", $quote)) . '</blockquote>'; $quote = []; }
        };

        $lineCount=count($lines);
        for($lineIndex=0;$lineIndex<$lineCount;$lineIndex++) {
            $line=$lines[$lineIndex];
            if (preg_match('/^```/', trim($line))) {
                $flushParagraph(); $flushList(); $flushQuote();
                if ($inCode) { $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>'; $code = []; }
                $inCode = !$inCode; continue;
            }
            if ($inCode) { $code[] = $line; continue; }
            if (trim($line) === '') { $flushParagraph(); $flushList(); $flushQuote(); continue; }
            $tableHeader=self::splitTableRow($line);
            $tableAlignment=$lineIndex+1<$lineCount&&$tableHeader!==null
                ?self::tableAlignments($lines[$lineIndex+1],count($tableHeader))
                :null;
            if($tableHeader!==null&&$tableAlignment!==null){
                $flushParagraph();$flushList();$flushQuote();$rows=[];$cursor=$lineIndex+2;
                while($cursor<$lineCount){
                    $row=self::splitTableRow($lines[$cursor]);
                    if($row===null)break;
                    $rows[]=array_slice(array_pad($row,count($tableHeader),''),0,count($tableHeader));
                    $cursor++;
                }
                $out[]=self::renderTable($tableHeader,$tableAlignment,$rows);
                $lineIndex=$cursor-1;
                continue;
            }
            if (preg_match('/^(#{1,4})\s+(.+)$/', trim($line), $m)) {
                $flushParagraph(); $flushList(); $flushQuote();
                $n = strlen($m[1]); $out[] = "<h$n>" . self::inline($m[2]) . "</h$n>"; continue;
            }
            if (preg_match('/^>\s?(.*)$/', trim($line), $m)) { $flushParagraph(); $flushList(); $quote[] = $m[1]; continue; }
            $listLine=str_replace("\t",'    ',$line);
            if (preg_match('/^( *)([-*+]|\d+[.)])\s+(.+)$/', $listLine, $m)) {
                $flushParagraph(); $flushQuote();
                $list[]=[
                    'indent'=>strlen($m[1]),
                    'type'=>preg_match('/^\d/',$m[2])?'ol':'ul',
                    'text'=>$m[3],
                ];
                continue;
            }
            $flushList(); $flushQuote(); $paragraph[] = $line;
        }
        if ($inCode) $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
        $flushParagraph(); $flushList(); $flushQuote();
        return implode("\n", $out);
    }

    /** @return list<string>|null */
    private static function splitTableRow(string $line): ?array
    {
        $line=trim($line);
        if($line==='')return null;
        $outerPipe=str_starts_with($line,'|');
        if($outerPipe)$line=substr($line,1);
        if(str_ends_with($line,'|')){
            $backslashes=0;
            for($index=strlen($line)-2;$index>=0&&$line[$index]==='\\';$index--)$backslashes++;
            if($backslashes%2===0){$line=substr($line,0,-1);$outerPipe=true;}
        }
        $cells=[];$cell='';$hasSeparator=false;$inCode=false;$length=strlen($line);
        for($index=0;$index<$length;$index++){
            $character=$line[$index];
            if($character==='\\'&&$index+1<$length&&$line[$index+1]==='|'){$cell.='|';$index++;continue;}
            if($character==='`'){$inCode=!$inCode;$cell.=$character;continue;}
            if($character==='|'&&!$inCode){$cells[]=trim($cell);$cell='';$hasSeparator=true;continue;}
            $cell.=$character;
        }
        if(!$hasSeparator&&!$outerPipe)return null;
        $cells[]=trim($cell);
        return $cells;
    }

    /** @return list<'left'|'center'|'right'>|null */
    private static function tableAlignments(string $line,int $columnCount): ?array
    {
        $cells=self::splitTableRow($line);
        if($cells===null||count($cells)!==$columnCount)return null;
        $alignments=[];
        foreach($cells as $cell){
            $cell=preg_replace('/\s+/','',trim($cell))??'';
            if(!preg_match('/^:?-{3,}:?$/',$cell))return null;
            $left=str_starts_with($cell,':');$right=str_ends_with($cell,':');
            $alignments[]=$left&&$right?'center':($right?'right':'left');
        }
        return $alignments;
    }

    /**
     * @param list<string> $header
     * @param list<'left'|'center'|'right'> $alignments
     * @param list<list<string>> $rows
     */
    private static function renderTable(array $header,array $alignments,array $rows): string
    {
        $cellHtml=static function(string $tag,string $value,string $alignment): string {
            $scope=$tag==='th'?' scope="col"':'';
            return '<'.$tag.$scope.' style="text-align:'.$alignment.'">'.self::inline($value).'</'.$tag.'>';
        };
        $html='<div class="markdown-table-wrap"><table class="markdown-table"><thead><tr>';
        foreach($header as $index=>$cell)$html.=$cellHtml('th',$cell,$alignments[$index]);
        $html.='</tr></thead><tbody>';
        foreach($rows as $row){$html.='<tr>';foreach($row as $index=>$cell)$html.=$cellHtml('td',$cell,$alignments[$index]);$html.='</tr>';}
        return $html.'</tbody></table></div>';
    }

    /**
     * @param list<array{indent:int,type:'ul'|'ol',text:string}> $items
     */
    private static function renderList(array $items): string
    {
        $html='';
        $stack=[];
        foreach($items as $item){
            $indent=$item['indent'];$type=$item['type'];$text=self::inline($item['text']);
            if(!$stack){
                $html.="<$type><li>$text";
                $stack[]=['indent'=>$indent,'type'=>$type];
                continue;
            }
            while(count($stack)>1&&$indent<$stack[array_key_last($stack)]['indent']){
                $level=array_pop($stack);$html.="</li></{$level['type']}>";
            }
            $current=$stack[array_key_last($stack)];
            if($indent>$current['indent']){
                $html.="<$type><li>$text";
                $stack[]=['indent'=>$indent,'type'=>$type];
                continue;
            }
            if($type!==$current['type']){
                $html.="</li></{$current['type']}><$type><li>$text";
                $stack[array_key_last($stack)]=['indent'=>$indent,'type'=>$type];
                continue;
            }
            $html.="</li><li>$text";
        }
        while($stack){$level=array_pop($stack);$html.="</li></{$level['type']}>";}
        return $html;
    }

    private static function inline(string $text): string
    {
        $text = e($text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace_callback('/\[([^]]+)]\((https?:\/\/[^\s)]+)\)/', static fn($m) => '<a href="' . e($m[2]) . '" rel="noopener" target="_blank">' . $m[1] . '</a>', $text) ?? $text;
        return nl2br($text, false);
    }
}
