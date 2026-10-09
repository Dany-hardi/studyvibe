<?php
declare(strict_types=1);

require_once __DIR__ . '/Brand.php';

/**
 * StudyVibe LMS - LaTeX Compiler Utility
 * 
 * Interacts with the local system shell to compile LaTeX markup templates into 
 * production-ready PDF binaries. Handles concurrency through unique temporary folder 
 * isolation, manages the cleanup of compilation artifact logs, and provides sanitization 
 * routines for LaTeX text nodes.
 * 
 * @package    StudyVibe
 * @subpackage Lib
 * @author     Advanced Engineering Team
 */
class LatexCompiler
{
    /**
     * Compiles a LaTeX document string into binary PDF data.
     * Runs the pdflatex executable twice internally to ensure correct page-count 
     * numbering and TOC/cross-reference resolving.
     *
     * @param string $latexSource The complete raw LaTeX markup template.
     * @return string|null Binary PDF string on success, or null on execution failures.
     */
    public static function compile(string $latexSource): ?string
    {
        // Define directory paths for intermediate compiler outputs
        $baseDir = __DIR__ . '/../uploads/latex_tmp';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        // Generate a cryptographically random directory suffix to prevent race conditions during parallel exports
        $uniq = uniqid('ltx_', true);
        $tmpDir = $baseDir . '/' . $uniq;
        if (!mkdir($tmpDir, 0755, true)) {
            return null;
        }

        // Write the main LaTeX source document
        $texFile = $tmpDir . '/document.tex';
        file_put_contents($texFile, $latexSource);

        // Stage the brand logos next to the source so templates can use Brand::latexPreamble()'s \svlogo macros
        @copy(Brand::pdfPng(false), $tmpDir . '/svlogo.png');
        @copy(Brand::pdfPng(true), $tmpDir . '/svlogo-mono.png');

        $escapedDir = escapeshellarg($tmpDir);

        // Web-server users often have no writable HOME, which makes TeX fail when it builds font caches.
        // Point HOME and the TeX cache dirs at the private temp directory and call the binary by full path.
        $bin = self::findBinary();
        $env = "HOME={$escapedDir} TEXMFVAR={$escapedDir}/texmf-var TEXMFCONFIG={$escapedDir}/texmf-config";
        $cmd = "cd {$escapedDir} && {$env} {$bin} -interaction=nonstopmode -file-line-error document.tex > compile.out 2>&1";

        // Execute the compiler twice to resolve dynamic back-references and section sizes
        exec($cmd);
        exec($cmd);

        $pdfFile = $tmpDir . '/document.pdf';
        $pdfData = null;

        if (file_exists($pdfFile)) {
            $pdfData = file_get_contents($pdfFile);
        } else {
            $log = @file_get_contents($tmpDir . '/document.log') ?: (string)@file_get_contents($tmpDir . '/compile.out');
            error_log('[LatexCompiler] compilation failed: ' . substr($log, -1500));
        }

        // Cleanup temporary intermediate auxiliary files from the filesystem
        foreach (glob($tmpDir . '/texmf-*') ?: [] as $d) {
            self::removeTree($d);
        }
        $files = ['compile.out', 'document.tex', 'svlogo.png', 'svlogo-mono.png', 'document.pdf', 'document.log', 'document.aux', 'document.out'];
        foreach ($files as $f) {
            @unlink($tmpDir . '/' . $f);
        }
        @rmdir($tmpDir);

        return $pdfData;
    }


    /**
     * Sends the LaTeX source as a ZIP (document.tex plus the logo images it needs) that can be
     * compiled anywhere, e.g. on Overleaf. Used on request and as a fallback when pdflatex is
     * missing or fails on the server, so an export never ends in a bare error.
     */
    public static function sendSource(string $latexSource, string $baseName): never
    {
        $slug = trim((string)preg_replace('/[^a-z0-9_-]+/i', '_', $baseName), '_') ?: 'document';
        if (!class_exists('ZipArchive')) {
            header('Content-Type: application/x-tex; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $slug . '.tex"');
            echo $latexSource;
            exit;
        }
        $zipPath = tempnam(sys_get_temp_dir(), 'svtex_');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString('document.tex', $latexSource);
        foreach ([false => 'svlogo.png', true => 'svlogo-mono.png'] as $mono => $name) {
            $img = Brand::pdfPng((bool)$mono);
            if (is_file($img)) {
                $zip->addFile($img, $name);
            }
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $slug . '_latex.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    /**
     * Escapes standard special LaTeX reservation characters in text nodes.
     * Prevents compiler crashes caused by unescaped signs (like %, &, _, etc.).
     * 
     * @param string $text Raw text node values.
     * @return string The LaTeX-safe escaped representation.
     */
    public static function escape(string $text): string
    {
        $map = [
            '\\' => '\\textbackslash{}',
            '{'  => '\\{',
            '}'  => '\\}',
            '$'  => '\\$',
            '&'  => '\\&',
            '#'  => '\\#',
            '^'  => '\\textasciicircum{}',
            '_'  => '\\_',
            '%'  => '\\%',
            '~'  => '\\textasciitilde{}',
        ];
        return strtr(self::sanitize($text), $map);
    }

    /** Full path of the first LaTeX engine found, falling back to the bare command name. */
    private static function findBinary(): string
    {
        foreach (['/usr/bin/pdflatex', '/usr/local/bin/pdflatex', '/usr/local/texlive/bin/x86_64-linux/pdflatex', '/opt/homebrew/bin/pdflatex'] as $c) {
            if (is_executable($c)) {
                return escapeshellarg($c);
            }
        }
        return 'pdflatex';
    }

    private static function removeTree(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        foreach (glob($path . '/{,.}[!.]*', GLOB_BRACE) ?: [] as $child) {
            self::removeTree($child);
        }
        @rmdir($path);
    }

    /**
     * Makes free text safe for pdflatex: typographic characters become plain equivalents and
     * anything pdflatex cannot typeset (emoji, symbols outside Latin) is dropped instead of aborting the build.
     */
    public static function sanitize(string $text): string
    {
        $text = strtr($text, [
            "\u{2019}" => "'", "\u{2018}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2026}" => '...', "\u{00A0}" => ' ', "\u{202F}" => ' ', "\u{200B}" => '',
        ]);
        // Keep ASCII, Latin-1/Latin Extended, dashes and the euro sign; drop the rest (emoji, CJK, stray symbols)
        return (string)preg_replace('/[^\x{0009}\x{000A}\x{0020}-\x{007E}\x{00A1}-\x{024F}\x{2013}\x{2014}\x{20AC}]/u', '', $text);
    }
}
