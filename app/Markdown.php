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
        $listType = '';
        $quote = [];
        $code = [];
        $inCode = false;

        $flushParagraph = static function () use (&$paragraph, &$out): void {
            if ($paragraph) {
                $out[] = '<p>' . self::inline(implode("\n", $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $flushList = static function () use (&$list, &$listType, &$out): void {
            if ($list) {
                $items = array_map(fn($v) => '<li>' . self::inline($v) . '</li>', $list);
                $out[] = '<' . $listType . '>' . implode('', $items) . '</' . $listType . '>';
                $list = []; $listType = '';
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
            if (preg_match('/^[-*+]\s+(.+)$/', trim($line), $m)) {
                $flushParagraph(); $flushQuote();
                if ($listType && $listType !== 'ul') $flushList();
                $listType = 'ul'; $list[] = $m[1]; continue;
            }
            if (preg_match('/^\d+[.)]\s+(.+)$/', trim($line), $m)) {
                $flushParagraph(); $flushQuote();
                if ($listType && $listType !== 'ol') $flushList();
                $listType = 'ol'; $list[] = $m[1]; continue;
            }
            $flushList(); $flushQuote(); $paragraph[] = $line;
        }
        if ($inCode) $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
        $flushParagraph(); $flushList(); $flushQuote();
        return implode("\n", $out);
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
