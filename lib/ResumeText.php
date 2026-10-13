<?php
declare(strict_types=1);

/**
 * Plain text of a résumé, for screening and search. No external library: PDF, DOCX and legacy DOC are read directly.
 *
 *   $r = ResumeText::extract('/path/cv.pdf', 'pdf');   // ['text' => '...', 'readable' => bool, 'via' => 'pdf'|'docx'|'doc'|'']
 *
 * "readable" is false when too little real text came out (a scanned page, an exotic font encoding, a damaged file). The screening engine
 * treats an unreadable résumé as "a person must look", never as "irrelevant".
 *
 * PDF: stream objects are inflated, then the text operators (Tj, TJ, ', ") of every BT..ET block are read. Fonts that map glyph codes
 * through a ToUnicode CMap (what Word, LibreOffice and Google Docs export) are decoded with the CMaps found in the file.
 */
final class ResumeText
{
    private const MAX_BYTES = 8388608;      // never read more than 8 MB
    private const MAX_CHARS = 60000;        // what is kept
    private const MIN_READABLE = 80;        // below this many letters the text does not count

    /** @return array{text:string,readable:bool,via:string} */
    public static function extract(string $path, string $ext): array
    {
        $ext = strtolower($ext);
        $text = '';
        try {
            if (!is_file($path) || filesize($path) === 0 || filesize($path) > self::MAX_BYTES) {
                return ['text' => '', 'readable' => false, 'via' => ''];
            }
            $text = match ($ext) {
                'pdf'  => self::pdf((string)file_get_contents($path)),
                'docx' => self::docx($path),
                'doc'  => self::doc((string)file_get_contents($path)),
                default => '',
            };
        } catch (Throwable $e) {
            error_log('[ResumeText] ' . $ext . ': ' . $e->getMessage());
            $text = '';
        }
        $text = self::clean($text);
        return ['text' => $text, 'readable' => self::looksLikeText($text), 'via' => $ext];
    }

    /** Enough letters, and mostly letters: guards against binary noise taken for text. */
    public static function looksLikeText(string $t): bool
    {
        $len = mb_strlen($t);
        if ($len < self::MIN_READABLE) {
            return false;
        }
        $sample = mb_substr($t, 0, 4000);
        $letters = preg_match_all('/\p{L}/u', $sample);
        $spaces = preg_match_all('/\s/u', $sample);
        $all = max(1, mb_strlen($sample));
        return $letters / $all >= 0.55 && $spaces / $all >= 0.08 && $letters >= self::MIN_READABLE;
    }

    private static function clean(string $t): string
    {
        if (!mb_check_encoding($t, 'UTF-8')) {
            $t = mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
        }
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $t) ?? '';
        $t = preg_replace('/[ \t]+/u', ' ', $t) ?? '';
        $t = preg_replace("/\s*\n\s*/u", "\n", $t) ?? '';
        return mb_substr(trim($t), 0, self::MAX_CHARS);
    }

    // -----------------------------------------------------------------------------------------------------------------
    // DOCX / DOC
    // -----------------------------------------------------------------------------------------------------------------

    private static function docx(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = '';
        $n = $zip->numFiles;
        if ($n > 2000) {      // a zip bomb is not a résumé
            $zip->close();
            return '';
        }
        $st = $zip->statName('word/document.xml');
        if ($st && $st['size'] < 20 * 1024 * 1024) {
            $xml = (string)$zip->getFromName('word/document.xml');
        }
        $zip->close();
        if ($xml === '') {
            return '';
        }
        $xml = preg_replace('~</w:p>|<w:br\s*/?>|<w:tab\s*/?>~', "\n", $xml) ?? '';
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Legacy Word: the text is stored as plain runs; keep the long printable ones (8-bit and UTF-16). Rough but dependable for screening. */
    private static function doc(string $raw): string
    {
        $out = [];
        if (preg_match_all('/[\x20-\x7E\xC0-\xFF]{8,}/', $raw, $m)) {
            foreach ($m[0] as $run) {
                $out[] = mb_convert_encoding($run, 'UTF-8', 'Windows-1252');
            }
        }
        if (preg_match_all('/(?:[\x20-\x7E\xC0-\xFF]\x00){8,}/', $raw, $m)) {
            foreach ($m[0] as $run) {
                $out[] = mb_convert_encoding($run, 'UTF-8', 'UTF-16LE');
            }
        }
        return implode("\n", $out);
    }

    // -----------------------------------------------------------------------------------------------------------------
    // PDF
    // -----------------------------------------------------------------------------------------------------------------

    private static function pdf(string $raw): string
    {
        if (!str_contains(substr($raw, 0, 1024), '%PDF-')) {
            return '';
        }
        $streams = [];
        $off = 0;
        $count = 0;
        while (($s = strpos($raw, 'stream', $off)) !== false && $count < 4000) {
            $dataStart = $s + 6;
            if (($raw[$dataStart] ?? '') === "\r") {
                $dataStart++;
            }
            if (($raw[$dataStart] ?? '') === "\n") {
                $dataStart++;
            }
            $e = strpos($raw, 'endstream', $dataStart);
            if ($e === false) {
                break;
            }
            $dict = substr($raw, max(0, $s - 600), min(600, $s));
            $dict = substr($dict, (int)strrpos($dict, 'obj') ?: 0);
            $data = substr($raw, $dataStart, $e - $dataStart);
            if (str_contains($dict, 'FlateDecode')) {
                $inflated = @gzuncompress($data);
                if ($inflated === false) {
                    $inflated = @gzinflate(substr($data, 2));
                }
                if ($inflated === false) {
                    $inflated = @gzinflate($data);
                }
                $data = $inflated === false ? '' : $inflated;
            }
            if ($data !== '' && strlen($data) < 12 * 1024 * 1024) {
                $streams[] = $data;
            }
            $off = $e + 9;
            $count++;
        }
        // Uncompressed objects written outside streams are rare; the whole file is still scanned for ToUnicode maps below.
        $map = [];
        foreach ($streams as $d) {
            if (str_contains($d, 'beginbfchar') || str_contains($d, 'beginbfrange')) {
                self::readCMap($d, $map);
            }
        }
        $text = [];
        foreach ($streams as $d) {
            if (!str_contains($d, 'BT')) {
                continue;
            }
            $t = self::contentText($d, $map);
            if ($t !== '') {
                $text[] = $t;
            }
        }
        return implode("\n", $text);
    }

    /** Reads bfchar / bfrange sections of a ToUnicode CMap into [code => utf8]. */
    private static function readCMap(string $cmap, array &$map): void
    {
        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocks)) {
            foreach ($blocks[1] as $b) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $b, $p, PREG_SET_ORDER)) {
                    foreach ($p as $m) {
                        $map[hexdec($m[1])] = self::utf16hex($m[2]);
                    }
                }
            }
        }
        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks)) {
            foreach ($blocks[1] as $b) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<[0-9A-Fa-f]+>|\[[^\]]*\])/', $b, $p, PREG_SET_ORDER)) {
                    foreach ($p as $m) {
                        $lo = hexdec($m[1]);
                        $hi = min(hexdec($m[2]), $lo + 4096);
                        if ($m[3][0] === '<') {
                            $base = self::utf16hex(trim($m[3], '<>'));
                            $cp = mb_ord($base, 'UTF-8') ?: 0;
                            for ($c = $lo; $c <= $hi; $c++) {
                                $map[$c] = mb_chr($cp + ($c - $lo), 'UTF-8');
                            }
                        } elseif (preg_match_all('/<([0-9A-Fa-f]+)>/', $m[3], $list)) {
                            foreach ($list[1] as $i => $h) {
                                $map[$lo + $i] = self::utf16hex($h);
                            }
                        }
                    }
                }
            }
        }
    }

    private static function utf16hex(string $h): string
    {
        if (strlen($h) % 2) {
            $h = '0' . $h;
        }
        $bin = (string)hex2bin($h);
        return strlen($bin) === 1 ? $bin : mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
    }

    /** Text of one content stream, line by line. */
    private static function contentText(string $d, array $map): string
    {
        $out = '';
        if (!preg_match_all('/BT(.*?)ET/s', $d, $blocks)) {
            return '';
        }
        foreach ($blocks[1] as $block) {
            // Tokens in order: strings, arrays, and the operators that move or show text
            if (!preg_match_all('/\[(?:[^\]\\\\]|\\\\.)*\]\s*TJ|(?:\((?:[^()\\\\]|\\\\.)*\)|<[0-9A-Fa-f\s]*>)\s*(?:Tj|\'|")|-?[\d.]+\s+-?[\d.]+\s+T[dD]|T\*|-?[\d.]+\s+-?[\d.]+\s+-?[\d.]+\s+-?[\d.]+\s+-?[\d.]+\s+-?[\d.]+\s+Tm/s', $block, $tok)) {
                continue;
            }
            $line = '';
            foreach ($tok[0] as $t) {
                if (substr($t, -2) === 'TJ') {
                    if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)|<[0-9A-Fa-f\s]*>|-?[\d.]+/s', $t, $parts)) {
                        foreach ($parts[0] as $p) {
                            if ($p[0] === '(' || $p[0] === '<') {
                                $line .= self::decode($p, $map);
                            } elseif ((float)$p < -180) {      // a wide kerning gap is a space
                                $line .= ' ';
                            }
                        }
                    }
                } elseif (preg_match('/(?:Tj|\'|")$/', $t)) {
                    $line .= self::decode(rtrim((string)preg_replace('/\s*(Tj|\'|")$/', '', $t)), $map);
                } elseif (preg_match('/^(-?[\d.]+)\s+(-?[\d.]+)\s+T[dD]$/', trim($t), $mm)) {
                    $line .= ((float)$mm[2] == 0.0) ? ' ' : "\n";      // moving along the same line is a gap, moving down is a new line
                } else {
                    $line .= "\n";                                      // T* and Tm start a new line
                }
            }
            $out .= $line . "\n";
        }
        return $out;
    }

    /** A PDF string literal "(...)" or hex string "<...>" to UTF-8, through the ToUnicode map when it knows the codes. */
    private static function decode(string $s, array $map): string
    {
        if ($s[0] === '<') {
            $hex = preg_replace('/\s+/', '', trim($s, '<>')) ?? '';
            if ($hex === '') {
                return '';
            }
            if ($map && strlen($hex) % 4 === 0) {
                $o = '';
                $known = 0;
                $n = strlen($hex) / 4;
                for ($i = 0; $i < strlen($hex); $i += 4) {
                    $code = hexdec(substr($hex, $i, 4));
                    if (isset($map[$code])) {
                        $o .= $map[$code];
                        $known++;
                    }
                }
                if ($known >= $n * 0.6) {
                    return $o;
                }
            }
            if ($map && strlen($hex) % 2 === 0) {
                $o = '';
                for ($i = 0; $i < strlen($hex); $i += 2) {
                    $c = hexdec(substr($hex, $i, 2));
                    $o .= $map[$c] ?? (($c >= 32 && $c < 127) ? chr($c) : '');
                }
                return $o;
            }
            $bin = (string)hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex);
            return mb_convert_encoding($bin, 'UTF-8', 'Windows-1252');
        }
        $str = substr($s, 1, -1);
        $str = preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3}|\r?\n)/', static function ($m) {
            return match ($m[1][0]) {
                'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => '', 'f' => '', '(' => '(', ')' => ')', '\\' => '\\',
                "\n", "\r" => '',
                default => chr(octdec($m[1]) & 0xFF),
            };
        }, $str) ?? '';
        if ($map) {
            $o = '';
            for ($i = 0, $n = strlen($str); $i < $n; $i++) {
                $c = ord($str[$i]);
                $o .= $map[$c] ?? $str[$i];
            }
            $str = $o;
            if (mb_check_encoding($str, 'UTF-8')) {
                return $str;
            }
        }
        return mb_convert_encoding($str, 'UTF-8', 'Windows-1252');
    }
}
