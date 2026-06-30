<?php
declare(strict_types=1);

/**
 * Utility class to compile LaTeX code to PDF using the local pdflatex command.
 */
class LatexCompiler
{
    /**
     * Compile a LaTeX string and return the PDF binary data.
     *
     * @param string $latexSource The complete LaTeX source code.
     * @return string|null The PDF binary string, or null on failure.
     */
    public static function compile(string $latexSource): ?string
    {
        $baseDir = __DIR__ . '/../uploads/latex_tmp';
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        // Generate a unique folder for this compilation to avoid concurrency conflicts
        $uniq = uniqid('ltx_', true);
        $tmpDir = $baseDir . '/' . $uniq;
        if (!mkdir($tmpDir, 0755, true)) {
            return null;
        }

        $texFile = $tmpDir . '/document.tex';
        file_put_contents($texFile, $latexSource);

        $escapedDir = escapeshellarg($tmpDir);
        
        // Execute pdflatex twice to resolve cross-references and page counts
        $cmd = "cd {$escapedDir} && pdflatex -interaction=nonstopmode document.tex > /dev/null 2>&1";
        exec($cmd);
        exec($cmd);

        $pdfFile = $tmpDir . '/document.pdf';
        $pdfData = null;

        if (file_exists($pdfFile)) {
            $pdfData = file_get_contents($pdfFile);
        }

        // Cleanup temporary files
        $files = ['document.tex', 'document.pdf', 'document.log', 'document.aux', 'document.out'];
        foreach ($files as $f) {
            @unlink($tmpDir . '/' . $f);
        }
        @rmdir($tmpDir);

        return $pdfData;
    }

    /**
     * Escape special LaTeX characters to prevent compilation errors.
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
