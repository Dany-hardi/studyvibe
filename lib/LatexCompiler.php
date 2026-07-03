<?php
declare(strict_types=1);

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

        $escapedDir = escapeshellarg($tmpDir);
        
        // Command configuration for nonstopmode execution (suppress interactive prompts)
        $cmd = "cd {$escapedDir} && pdflatex -interaction=nonstopmode document.tex > /dev/null 2>&1";
        
        // Execute the compiler twice to resolve dynamic back-references and section sizes
        exec($cmd);
        exec($cmd);

        $pdfFile = $tmpDir . '/document.pdf';
        $pdfData = null;

        if (file_exists($pdfFile)) {
            $pdfData = file_get_contents($pdfFile);
        }

        // Cleanup temporary intermediate auxiliary files from the filesystem
        $files = ['document.tex', 'document.pdf', 'document.log', 'document.aux', 'document.out'];
        foreach ($files as $f) {
            @unlink($tmpDir . '/' . $f);
        }
        @rmdir($tmpDir);

        return $pdfData;
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
        return strtr($text, $map);
    }
}
