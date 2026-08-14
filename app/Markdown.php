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

        foreach ($lines as $line) {
            if (preg_match('/^```/', trim($line))) {
                $flushParagraph(); $flushList(); $flushQuote();
                if ($inCode) { $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>'; $code = []; }
                $inCode = !$inCode; continue;
            }
            if ($inCode) { $code[] = $line; continue; }
            if (trim($line) === '') { $flushParagraph(); $flushList(); $flushQuote(); continue; }
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
