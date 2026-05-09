<?php
/**
 * Highline wiki importer.
 *
 * Bere jeden MD soubor exportovaný přímo z Google Docs (File → Download → Markdown)
 * a splituje ho na 16 kapitol → `wiki/<slug>.md`. Frontmatter (title, lead, quote,
 * group, order) se vytahuje z dedikovaných meta-bloků v source MD
 * (`**Title:**`, `**Short preview text:**`, `**Quote:**`).
 *
 * Obrázky se zachovávají INLINE jako MD reference-style definice
 * (`[imageN]: <data:image/...;base64,...>`) na konci každé kapitoly — pouze ty,
 * které jsou v dané kapitole skutečně použité (`![][imageN]`).
 *
 * Re-import:
 *   1. Aktualizuj `wiki-source/Highline guidebook.md` (Google Docs → File → Download → Markdown)
 *   2. php scripts/wiki-import.php
 *
 * Re-runnable: přepíše `wiki/*.md`. --dry-run jen vypíše plán.
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

$opts = getopt('', [
    'input::',
    'output::',
    'dry-run',
]);

$inputMd  = $opts['input']  ?? $projectRoot . '/wiki-source/Highline guidebook.md';
$outputMd = $opts['output'] ?? $projectRoot . '/wiki';
$dryRun   = isset($opts['dry-run']);

const CHAPTERS = [
    ['order' =>  1, 'group' => 'Používání highline',   'slug' => 'priprava',                'title' => 'Příprava'],
    ['order' =>  2, 'group' => 'Používání highline',   'slug' => 'bezpecnost',              'title' => 'Bezpečnost'],
    ['order' =>  3, 'group' => 'Používání highline',   'slug' => 'vybaveni',                'title' => 'Vybavení'],
    ['order' =>  4, 'group' => 'Používání highline',   'slug' => 'etika',                   'title' => 'Etika'],
    ['order' =>  5, 'group' => 'Používání highline',   'slug' => 'co-nedelat-na-highline',  'title' => 'Co (ne)dělat na highline?'],
    ['order' =>  6, 'group' => 'Kotvení & Materiály',  'slug' => 'materialy',               'title' => 'Materiály'],
    ['order' =>  7, 'group' => 'Kotvení & Materiály',  'slug' => 'vybava',                  'title' => 'Výbava'],
    ['order' =>  8, 'group' => 'Kotvení & Materiály',  'slug' => 'uzly',                    'title' => 'Uzly'],
    ['order' =>  9, 'group' => 'Kotvení & Materiály',  'slug' => 'kotveni',                 'title' => 'Kotvení'],
    ['order' => 10, 'group' => 'Kotvení & Materiály',  'slug' => 'prirodni-kotveni',        'title' => 'Přírodní kotvení'],
    ['order' => 11, 'group' => 'Kotvení & Materiály',  'slug' => 'master-point',            'title' => 'Master point'],
    ['order' => 12, 'group' => 'Kotvení & Materiály',  'slug' => 'a-frames',                'title' => 'A Frames'],
    ['order' => 13, 'group' => 'Kotvení & Materiály',  'slug' => 'kilonewtony',             'title' => 'KiloNewtony'],
    ['order' => 14, 'group' => 'Napínání highline',    'slug' => 'napinani-priprava',       'title' => 'Příprava'],
    ['order' => 15, 'group' => 'Napínání highline',    'slug' => 'budovani-kotveni',        'title' => 'Budování kotvení'],
    ['order' => 16, 'group' => 'Napínání highline',    'slug' => 'napinani',                'title' => 'Napínaní'],
];

if (!is_file($inputMd)) {
    fwrite(STDERR, "ERROR: Input MD not found: {$inputMd}\n");
    exit(1);
}

$src = file_get_contents($inputMd);
if ($src === false) {
    fwrite(STDERR, "ERROR: Cannot read {$inputMd}\n");
    exit(1);
}

// 1) Najdi všechny H2 — Google Docs MD export buď s "N. ##" prefixem (numbered list),
//    nebo plain "##". Filtrujeme dekorativní/meta hlavičky.
preg_match_all('/^[ \t]*(?:[0-9]+\.[ \t]+)?##[ \t]+(.+?)[ \t]*$/mu', $src, $hm, PREG_OFFSET_CAPTURE);

$realChapters = [];
foreach ($hm[0] as $i => $matchInfo) {
    [$_, $offset] = $matchInfo;
    $rawTitle = trim($hm[1][$i][0]);
    $clean = normalizeHeading($rawTitle);
    if ($clean === '') continue;
    if (str_starts_with($clean, 'Title:')) continue;
    if ($clean === 'Napínaní highline') continue; // skupinová hlavička, ne kapitola
    $realChapters[] = ['raw' => $rawTitle, 'clean' => $clean, 'offset' => $offset];
}

if (count($realChapters) !== count(CHAPTERS)) {
    fwrite(STDERR, sprintf(
        "ERROR: Expected %d chapter headings, got %d.\nFound:\n",
        count(CHAPTERS),
        count($realChapters),
    ));
    foreach ($realChapters as $h) {
        fwrite(STDERR, "  - {$h['clean']} @ {$h['offset']}\n");
    }
    exit(1);
}

// 2) Validace pořadí (title v CHAPTERS musí matchnout title v source).
foreach (CHAPTERS as $i => $chapter) {
    if ($realChapters[$i]['clean'] !== $chapter['title']) {
        fwrite(STDERR, sprintf(
            "ERROR: Chapter %d expected '%s', got '%s' at offset %d\n",
            $chapter['order'],
            $chapter['title'],
            $realChapters[$i]['clean'],
            $realChapters[$i]['offset'],
        ));
        exit(1);
    }
}

// 3) Najdi base64 ref-style definice na konci souboru.
//    Tvar: `[imageN]: <data:image/png;base64,iVBORw0KG...>`
preg_match_all('/^\[(image\d+)\]:\s*<(data:image\/[^>]+)>\s*$/mu', $src, $rm, PREG_SET_ORDER);
$refDefs = [];
foreach ($rm as $m) {
    $refDefs[$m[1]] = $m[2];
}

// 4) Pro každou kapitolu vytáhni slice + meta + body.
$srcLen = strlen($src);
$results = [];
foreach (CHAPTERS as $i => $chapter) {
    $start = $realChapters[$i]['offset'];
    $end = $realChapters[$i + 1]['offset'] ?? $srcLen;
    // Body končí ještě před base64 ref-defs (ty jsou na úplném konci souboru).
    if ($end === $srcLen) {
        // Najdi první [imageN]: <data:...> a uřízni před ní.
        if (preg_match('/^\[image\d+\]:\s*<data:image\//mu', substr($src, $start), $cm, PREG_OFFSET_CAPTURE)) {
            $end = $start + $cm[0][1];
        }
    }
    $slice = substr($src, $start, $end - $start);

    $parsed = parseChapter($slice, $chapter, $refDefs);
    $results[] = $parsed;
}

// 5) Output.
echo "== WIKI IMPORT ==\n";
echo "Source:  {$inputMd}\n";
echo "Output:  {$outputMd}\n";
echo "Dry-run: " . ($dryRun ? 'YES' : 'no') . "\n\n";

if (!$dryRun) {
    if (!is_dir($outputMd)) {
        mkdir($outputMd, 0755, true);
    }
    foreach (glob($outputMd . '/*.md') ?: [] as $f) {
        unlink($f);
    }
}

$totalImgs = 0;
$missing = [];
foreach ($results as $r) {
    $path = $outputMd . '/' . $r['filename'];
    echo sprintf(
        "  %-32s [%2d %-22s]  %7d B  imgs=%d\n",
        $r['filename'],
        $r['frontmatter']['order'],
        $r['frontmatter']['group'],
        strlen($r['body']),
        count($r['images']),
    );
    $totalImgs += count($r['images']);
    $missing = array_merge($missing, $r['missing']);
    if (!$dryRun) {
        $content = "---\n" . renderFrontmatter($r['frontmatter']) . "---\n\n" . $r['body'];
        if (file_put_contents($path, $content) === false) {
            fwrite(STDERR, "ERROR: Cannot write {$path}\n");
            exit(1);
        }
    }
}

echo "\nObrázků zachováno (inline base64): {$totalImgs}\n";
if ($missing !== []) {
    fwrite(STDERR, "WARNING: missing image refs (chybí v base64 ref-defs):\n");
    foreach (array_unique($missing) as $r) {
        fwrite(STDERR, "  - {$r}\n");
    }
}

echo "\nHotovo.\n";
exit(0);

// ============================================================
// helpers
// ============================================================

function normalizeHeading(string $s): string
{
    $s = preg_replace('/[*_]+/u', '', $s);
    $s = strip_tags($s);
    return trim($s);
}

/**
 * @param array<string,string> $refDefs
 * @return array{
 *     filename: string,
 *     frontmatter: array<string, scalar>,
 *     body: string,
 *     images: list<string>,
 *     missing: list<string>,
 * }
 */
function parseChapter(string $slice, array $chapter, array $refDefs): array
{
    $title = $chapter['title'];

    // Smaž první H2 řádek (s nebo bez "N. " prefixu).
    $slice = preg_replace('/^[ \t]*(?:[0-9]+\.[ \t]+)?##[ \t]+.*\R?/u', '', $slice, 1);

    // Smaž "## **Title:** ..." pseudo-hlavičku (Google Docs Title meta).
    $slice = preg_replace('/^[ \t]*##[ \t]+\*\*Title:\*\*[^\n]*\R?/mu', '', $slice);
    // Smaž samostatnou (bez ##) "**Title:**" řádku.
    $slice = preg_replace('/^[ \t]*\*\*Title:\*\*[^\n]*\R?/mu', '', $slice);

    // Multi-line lead a quote.
    $lead = extractMultilineMeta($slice, 'Short preview text');
    $quote = extractMultilineMeta($slice, 'Quote');

    if ($quote !== '') {
        $quote = trim($quote, " \t\u{201C}\u{201D}\"'");
    }

    // Vyřízni meta-bloky z body.
    $body = removeMultilineMeta($slice, 'Short preview text');
    $body = removeMultilineMeta($body, 'Quote');

    // Cleanup leading separators (—————), prázdné řádky.
    $body = preg_replace("/^[\s—\-]+\R*/u", '', $body);

    // Backslash escapes Google Docs MD export sype před `!` a `\` před `-` v některých
    // kontextech. CommonMark to renderuje správně, ale pro čitelnost stripujeme.
    $body = preg_replace('/\\\\([!\-])/u', '$1', $body);

    // 3+ prázdných řádků → 2.
    $body = preg_replace("/\n{3,}/", "\n\n", $body);
    $body = trim($body);

    // Najdi všechny `![][imageN]` reference v body.
    preg_match_all('/!\[[^\]]*\]\[(image\d+)\]/u', $body, $im);
    $usedRefs = array_values(array_unique($im[1]));

    // Append ref-defs (jen ty, které kapitola používá) na konec body.
    // POZOR: bez angular brackets <...> kolem URL — CommonMark autolink form má
    // problém s velkými data:URL (>10 KB) a fallne na plain text. Bare destination
    // funguje spolehlivě, base64 alfabet (`[A-Za-z0-9+/=]`) neobsahuje znaky,
    // které by potřebovaly escape.
    $missing = [];
    if ($usedRefs !== []) {
        $body .= "\n";
        foreach ($usedRefs as $ref) {
            if (!isset($refDefs[$ref])) {
                $missing[] = $ref;
                continue;
            }
            $body .= "\n[{$ref}]: {$refDefs[$ref]}";
        }
    }

    // Prepend H1.
    $body = "# {$title}\n\n" . $body . "\n";

    return [
        'filename' => $chapter['slug'] . '.md',
        'frontmatter' => [
            'title' => $title,
            'lead' => $lead,
            'quote' => $quote,
            'group' => $chapter['group'],
            'order' => $chapter['order'],
        ],
        'body' => $body,
        'images' => $usedRefs,
        'missing' => $missing,
    ];
}

function extractMultilineMeta(string $slice, string $field): string
{
    [, $captured] = scanMultilineMeta($slice, $field);
    return $captured === null ? '' : cleanLeadOrQuote($captured);
}

function removeMultilineMeta(string $slice, string $field): string
{
    [$kept] = scanMultilineMeta($slice, $field);
    return $kept;
}

/**
 * @return array{0: string, 1: ?string}
 */
function scanMultilineMeta(string $slice, string $field): array
{
    $lines = preg_split('/\R/u', $slice);
    if ($lines === false) {
        return [$slice, null];
    }
    $fieldRe = '/^[ \t>]*\*\*' . preg_quote($field, '/') . ':\*\*[ \t]*(.*)$/u';
    $otherFieldRe = '/^[ \t>]*\*\*[\p{L} ]+:\*\*/u';
    $headingRe = '/^#+[ \t]+/u';

    $kept = [];
    $captured = null;
    $buf = [];
    $skipping = false;

    foreach ($lines as $line) {
        if ($skipping) {
            $isBlank = trim($line) === '';
            $isOtherField = preg_match($otherFieldRe, $line) === 1;
            $isHeading = preg_match($headingRe, $line) === 1;
            if ($isBlank || $isOtherField || $isHeading) {
                $captured = ($captured === null ? '' : $captured . "\n") . implode("\n", $buf);
                $buf = [];
                $skipping = false;
                $kept[] = $line;
                continue;
            }
            $buf[] = $line;
            continue;
        }
        if (preg_match($fieldRe, $line, $m) === 1) {
            $skipping = true;
            $buf = [];
            $rest = $m[1];
            if ($rest !== '') {
                $buf[] = $rest;
            }
            continue;
        }
        $kept[] = $line;
    }
    if ($skipping) {
        $captured = ($captured === null ? '' : $captured . "\n") . implode("\n", $buf);
    }
    return [implode("\n", $kept), $captured];
}

function cleanLeadOrQuote(string $s): string
{
    $s = preg_replace('/\s*\R\s*/u', ' ', $s);
    $s = trim($s);
    // [text](url) → text  (lead/quote = plain text bez odkazu)
    $s = preg_replace('/\[(.*?)\]\([^)]*\)/us', '$1', $s);
    // **text** / *text* / _text_
    $s = preg_replace('/\*\*(.*?)\*\*/us', '$1', $s);
    $s = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/us', '$1', $s);
    // Backslash escapy.
    $s = preg_replace('/\\\\([!\-])/u', '$1', $s);
    return trim($s);
}

function renderFrontmatter(array $fm): string
{
    $out = '';
    foreach ($fm as $k => $v) {
        if (is_int($v) || is_float($v)) {
            $out .= $k . ': ' . $v . "\n";
        } else {
            $val = (string) $v;
            $needsQuote = $val === ''
                || preg_match('/[:#\[\]\{\},&*!|>\'"%@`]/u', $val) === 1
                || preg_match('/^[\-\?]/', $val) === 1;
            if ($needsQuote) {
                $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $val);
                $out .= $k . ': "' . $escaped . '"' . "\n";
            } else {
                $out .= $k . ': ' . $val . "\n";
            }
        }
    }
    return $out;
}
