<?php
defined('ABSPATH') || exit;

final class Ouinpo_Submissions_MarkdownPreview
{
    public static function page(string $markdown, string $filename): string
    {
        if (!class_exists('Parsedown')) {
            require_once dirname(__DIR__, 2) . '/SegFault/plugin/libs/parsedown/Parsedown.php';
        }
        $parser = new Parsedown();
        $parser->setSafeMode(true);
        $html = wp_kses_post($parser->text($markdown));
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($filename) . '</title><style>body{font:18px/1.65 system-ui,sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem;color:#18232b;background:#fff}pre{overflow:auto;padding:1rem;background:#f1f4f6}code{font-family:monospace}table{border-collapse:collapse;display:block;overflow:auto}th,td{border:1px solid #bbc5cc;padding:.5rem}blockquote{border-left:4px solid #bbc5cc;margin-left:0;padding-left:1rem}a{color:#075a9c}h1,h2,h3{line-height:1.25}</style></head><body><main><p>Ressource : ' . esc_html($filename) . '</p>' . $html . '</main></body></html>';
    }
}
