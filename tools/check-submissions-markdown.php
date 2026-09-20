<?php
define('ABSPATH', __DIR__);
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
// Test Parsedown safe mode independently; production adds WordPress sanitization.
function wp_kses_post($html) { return $html; }
require dirname(__DIR__) . '/src/Modules/Submissions/plugin/MarkdownPreview.php';
$html = Ouinpo_Submissions_MarkdownPreview::page("# Guide\n\n**Important**\n\n- Étape 1\n- Étape 2\n\n```php\necho 1;\n```\n\n<script>alert(1)</script>\n\n[x](javascript:alert)", '<guide>.md');
foreach (['<h1>Guide</h1>', '<strong>Important</strong>', '<ul>', '<pre><code', '&lt;guide&gt;.md'] as $expected) {
    if (!str_contains($html, $expected)) throw new RuntimeException('Missing: ' . $expected);
}
if (str_contains($html, '<script>') || str_contains($html, 'href="javascript:')) throw new RuntimeException('Unsafe markup');
echo "Markdown headings, emphasis, lists, code and safe rendering: OK.\n";
