<?php
// public/inc/post-format.php
// Lightweight, safe post renderer. All author text is escaped before any
// generated tag is added, so rendered output never contains author HTML.

function inlineMarkup(string $escaped): string
{
    // Inline code first, so backtick-wrapped spans are protected from later regexes
    $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped);

    // Bold **text**
    $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);

    // Italic *text*
    $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped);

    // Links [text](url) — http/https only
    $escaped = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)\s]+)\)/',
        function ($m) {
            $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) {
                return $m[0];
            }
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer">' . $m[1] . '</a>';
        },
        $escaped
    );

    return $escaped;
}

function renderPostContent(string $content): string
{
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $count = count($lines);
    $i = 0;
    $html = '';

    while ($i < $count) {
        $trimmed = trim($lines[$i]);

        // Code fence ``` ... ```
        if (str_starts_with($trimmed, '```')) {
            $code = [];
            $i++;
            while ($i < $count && !str_starts_with(trim($lines[$i]), '```')) {
                $code[] = $lines[$i];
                $i++;
            }
            $i++;
            $html .= '<pre><code>' . htmlspecialchars(implode("\n", $code)) . '</code></pre>';
            continue;
        }

        // Blank line
        if ($trimmed === '') {
            $i++;
            continue;
        }

        // Blockquote: one or more consecutive "> " lines
        if (str_starts_with($trimmed, '>')) {
            $quote = [];
            while ($i < $count && str_starts_with(trim($lines[$i]), '>')) {
                $quote[] = trim(substr(trim($lines[$i]), 1));
                $i++;
            }
            $html .= '<blockquote><p>' . inlineMarkup(htmlspecialchars(implode("\n", $quote), ENT_QUOTES, 'UTF-8')) . '</p></blockquote>';
            continue;
        }

        // Headings ## / ###
        if (preg_match('/^###\s+(.+)$/', $trimmed, $m)) {
            $html .= '<h3>' . inlineMarkup(htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8')) . '</h3>';
            $i++;
            continue;
        }
        if (preg_match('/^##\s+(.+)$/', $trimmed, $m)) {
            $html .= '<h2>' . inlineMarkup(htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8')) . '</h2>';
            $i++;
            continue;
        }

        // Unordered list
        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
            $items = [];
            while ($i < $count && preg_match('/^[-*]\s+(.+)$/', trim($lines[$i]), $im)) {
                $items[] = $im[1];
                $i++;
            }
            $html .= '<ul>';
            foreach ($items as $item) {
                $html .= '<li>' . inlineMarkup(htmlspecialchars($item, ENT_QUOTES, 'UTF-8')) . '</li>';
            }
            $html .= '</ul>';
            continue;
        }

        // Plain paragraph: consecutive non-blank, non-special lines joined with a space
        $para = [];
        while ($i < $count) {
            $t = trim($lines[$i]);
            if ($t === ''
                || str_starts_with($t, '```')
                || str_starts_with($t, '>')
                || preg_match('/^#{1,3}\s+/', $t)
                || preg_match('/^[-*]\s+/', $t)) {
                break;
            }
            $para[] = $t;
            $i++;
        }
        $html .= '<p>' . inlineMarkup(htmlspecialchars(implode(' ', $para), ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    return $html;
}

function stripMarkdown(string $content): string
{
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $out = [];
    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '```')) {
            continue;
        }
        $t = preg_replace('/^>\s?/', '', $t);
        $t = preg_replace('/^#{1,6}\s+/', '', $t);
        $t = preg_replace('/^[-*]\s+/', '', $t);
        $t = preg_replace('/`([^`]+)`/', '$1', $t);
        $t = str_replace(['**', '__'], '', $t);
        $t = preg_replace('/\*([^*]+)\*/', '$1', $t);
        $t = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '$1', $t);
        $t = trim($t);
        if ($t !== '') {
            $out[] = $t;
        }
    }
    return implode(' ', $out);
}