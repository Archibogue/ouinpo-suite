<?php
/** Standalone parser tests: php tools/check-ticket-presentation.php. No WordPress data access. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
require dirname(__DIR__).'/src/Modules/TicketSimulator/Presentation.php';
// Deliberately no sanitizing stub: test the bundled parser's own safe mode independently.
function wp_kses_post($html) { return $html; }
use Ouinpo\Suite\Modules\TicketSimulator\Presentation;
function verify(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "OK: $label\n";}
$legacy='> Traces déclarées par l’élève (à vérifier) : '.json_encode(['information'=>'À vérifier','proof'=>"<script>alert(1)</script>\n[piège](javascript:alert(1))"]);
$original=$legacy;
$html=Presentation::markdown("# Copie\n\n".$legacy);
verify(str_contains($html,'<h1>Copie</h1>'),'Markdown headings rendered');
verify(str_contains($html,'Informations recherchées / manquantes')&&str_contains($html,'À vérifier'),'Legacy JSON decoded into named French sections');
verify(!str_contains($html,'<script')&&!str_contains($html,'href="javascript:'),'Legacy traces cannot create executable markup');
verify($legacy===$original,'Stored source untouched');
$malicious=Presentation::markdown("<script>alert(1)</script>\n\n[piège](javascript:alert(1))\n\n![remote](https://example.invalid/pixel.png)");
verify(!preg_match('/<(script|img)\b|href="javascript:/i',$malicious),'Safe mode blocks active content and removes remote images');
verify(str_contains(Presentation::readableMarkdown('> Traces déclarées par l’élève (à vérifier) : {oops}'),'{oops}'),'Malformed legacy data preserved as text');
verify(str_contains(Presentation::markdown('Début : 2026-09-20 14:16:00 UTC'),'<time datetime="2026-09-20T14:16:00Z">'),'UTC timestamp has an explicit machine-readable timezone');
verify(Presentation::readableMarkdown('État : working')==='Remise et correction au moment de la capture : À remettre','Legacy assessment state is translated only for display');
