<?php
declare(strict_types=1);

/**
 * PdfReportBuilder — génération de rapports PDF A4 sans dépendance externe.
 *
 * Produit des PDF 1.4 lisibles avec en-tête StudyVibe, sections et tableaux.
 * Les caractères accentués sont translittérés en Latin-1 pour Helvetica intégrée.
 */
class PdfReportBuilder
{
    private const PAGE_WIDTH  = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN      = 42.0;

    /** @var list<string> Contenu de chaque page (flux PDF) */
    private array $pages = [];

    private int $pageIndex = -1;

    /** Position verticale courante (origine bas-gauche) */
    private float $cursorY = 0.0;

    /**
     * Démarre une nouvelle page blanche dans le document.
     */
    public function addPage(): void
    {
        $this->pages[] = '';
        $this->pageIndex++;
        $this->cursorY = self::PAGE_HEIGHT - self::MARGIN;
    }

    /**
     * Ajuste la position verticale courante du curseur de dessin.
     */
    public function setCursorY(float $y): void
    {
        $this->cursorY = $y;
    }

    /**
     * Convertit une chaîne UTF-8 en Latin-1 sûre pour les polices PDF standard.
     */
    public static function encodeText(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }

    /**
     * Dessine du texte à une position absolue (coordonnées PDF, origine en bas à gauche).
     */
    public function drawText(float $x, float $y, string $text, int $fontSize = 11, bool $bold = false): void
    {
        $font = $bold ? '/F2' : '/F1';
        $encoded = self::encodeText($text);
        $this->pages[$this->pageIndex] .= "BT {$font} {$fontSize} Tf {$x} {$y} Td ({$encoded}) Tj ET\n";
    }

    /**
     * Dessine un rectangle plein (utilisé pour la barre de marque StudyVibe).
     */
    public function drawFilledRect(float $x, float $y, float $w, float $h, float $r, float $g, float $b): void
    {
        $this->pages[$this->pageIndex] .= sprintf(
            "%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n",
            $r,
            $g,
            $b,
            $x,
            $y,
            $w,
            $h
        );
    }

    /**
     * Dessine un trait horizontal décoratif.
     */
    public function drawLine(float $x1, float $y1, float $x2, float $y2, float $width = 0.8): void
    {
        $this->pages[$this->pageIndex] .= sprintf(
            "%.2F w %.2F %.2F m %.2F %.2F l S\n",
            $width,
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    /**
     * Vérifie l'espace restant ; ajoute une page si nécessaire avant un bloc.
     */
    public function ensureSpace(float $needed): void
    {
        if ($this->pageIndex < 0) {
            $this->addPage();
        }
        if ($this->cursorY - $needed < self::MARGIN) {
            $this->addPage();
        }
    }

    /**
     * Page de garde avec logo textuel StudyVibe, titre du rapport et métadonnées.
     *
     * @param string[] $metaLines Lignes d'information (date, auteur, périmètre…)
     */
    public function addCoverPage(string $title, string $subtitle, array $metaLines = []): void
    {
        $this->addPage();

        $top = self::PAGE_HEIGHT - self::MARGIN;

        // Barre verte de marque (#004B23)
        $this->drawFilledRect(self::MARGIN, $top - 8, self::PAGE_WIDTH - 2 * self::MARGIN, 6, 0.0, 0.294, 0.137);

        // Nom de la plateforme (équivalent logo textuel)
        $this->drawText(self::MARGIN, $top - 36, 'StudyVibe', 26, true);
        $this->drawText(self::MARGIN, $top - 54, 'Plateforme Academique LMS', 10, false);

        $this->drawLine(self::MARGIN, $top - 68, self::PAGE_WIDTH - self::MARGIN, $top - 68, 0.5);

        $this->drawText(self::MARGIN, $top - 110, $title, 20, true);
        $this->drawText(self::MARGIN, $top - 135, $subtitle, 12, false);

        $y = $top - 175;
        foreach ($metaLines as $line) {
            $this->drawText(self::MARGIN, $y, $line, 10, false);
            $y -= 18;
        }

        $this->cursorY = $top - 220;
    }

    /**
     * Ajoute un titre de section dans le corps du rapport.
     */
    public function addSectionTitle(string $title): void
    {
        $this->ensureSpace(36);
        $this->drawText(self::MARGIN, $this->cursorY, $title, 14, true);
        $this->drawLine(self::MARGIN, $this->cursorY - 8, self::PAGE_WIDTH - self::MARGIN, $this->cursorY - 8, 0.6);
        $this->cursorY -= 28;
    }

    /**
     * Rend un tableau avec en-têtes grisés, bordures et retour à la ligne automatique.
     *
     * @param string[] $headers
     * @param array<int, array<int, string|int|float|null>> $rows
     * @param float[]|null $colWidths Largeurs relatives (somme ≈ largeur utile)
     */
    public function addTable(array $headers, array $rows, ?array $colWidths = null): void
    {
        $usableWidth = self::PAGE_WIDTH - 2 * self::MARGIN;
        $colCount    = count($headers);

        if ($colCount === 0) {
            return;
        }

        if ($colWidths === null || count($colWidths) !== $colCount) {
            $colWidths = array_fill(0, $colCount, $usableWidth / $colCount);
        }

        $rowHeight = 18.0;
        $headerH   = 22.0;

        $this->ensureSpace($headerH + 4);
        $this->renderTableRow($headers, $colWidths, $this->cursorY, $headerH, true);
        $this->cursorY -= $headerH;

        foreach ($rows as $row) {
            $cells = [];
            for ($i = 0; $i < $colCount; $i++) {
                $cells[] = isset($row[$i]) ? (string)$row[$i] : '';
            }

            $wrapped = $this->wrapRowCells($cells, $colWidths);
            $lines   = max(1, ...array_map('count', $wrapped));
            $height  = $rowHeight * $lines;

            $this->ensureSpace($height + 2);
            $this->renderWrappedRow($wrapped, $colWidths, $this->cursorY, $rowHeight);
            $this->cursorY -= $height;
        }

        $this->cursorY -= 12;
    }

    /**
     * Découpe le texte de chaque cellule selon la largeur disponible (approximation par caractères).
     *
     * @param string[] $cells
     * @param float[] $colWidths
     * @return array<int, string[]>
     */
    private function wrapRowCells(array $cells, array $colWidths): array
    {
        $wrapped = [];
        foreach ($cells as $i => $cell) {
            $maxChars = max(8, (int)floor($colWidths[$i] / 5.2));
            $wrapped[$i] = $this->wrapText($cell, $maxChars);
        }
        return $wrapped;
    }

    /**
     * Coupe une chaîne en lignes d'une longueur maximale donnée.
     *
     * @return string[]
     */
    private function wrapText(string $text, int $maxChars): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }

        $words  = preg_split('/\s+/', $text) ?: [$text];
        $lines  = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = mb_strlen($word) > $maxChars
                    ? mb_substr($word, 0, $maxChars - 1) . '…'
                    : $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    /**
     * Dessine une ligne d'en-tête de tableau (fond gris clair simulé par un rectangle).
     *
     * @param string[] $cells
     * @param float[] $colWidths
     */
    private function renderTableRow(array $cells, array $colWidths, float $topY, float $height, bool $header): void
    {
        $x = self::MARGIN;
        $bottomY = $topY - $height;

        if ($header) {
            $this->pages[$this->pageIndex] .= sprintf(
                "0.94 0.94 0.94 rg %.2F %.2F %.2F %.2F re f\n",
                $x,
                $bottomY,
                array_sum($colWidths),
                $height
            );
        }

        $this->drawTableBorders($colWidths, $topY, $height, 1);

        $textY = $bottomY + ($height / 2) - 4;
        $cx    = self::MARGIN;
        foreach ($cells as $i => $cell) {
            $this->drawText($cx + 4, $textY, (string)$cell, $header ? 9 : 8, $header);
            $cx += $colWidths[$i];
        }
    }

    /**
     * Dessine une ligne de données pouvant occuper plusieurs lignes de texte.
     *
     * @param array<int, string[]> $wrappedCells
     * @param float[] $colWidths
     */
    private function renderWrappedRow(array $wrappedCells, array $colWidths, float $topY, float $lineHeight): void
    {
        $lineCount = max(1, ...array_map('count', $wrappedCells));
        $height    = $lineHeight * $lineCount;
        $bottomY   = $topY - $height;

        $this->drawTableBorders($colWidths, $topY, $height, 0);

        $cx = self::MARGIN;
        foreach ($wrappedCells as $i => $lines) {
            $lineY = $topY - $lineHeight + 4;
            foreach ($lines as $line) {
                $this->drawText($cx + 4, $lineY, $line, 8, false);
                $lineY -= $lineHeight;
            }
            $cx += $colWidths[$i];
        }
    }

    /**
     * Trace les bordures externes et internes d'une ligne de tableau.
     *
     * @param float[] $colWidths
     */
    private function drawTableBorders(array $colWidths, float $topY, float $height, int $rowIndex): void
    {
        $x      = self::MARGIN;
        $bottom = $topY - $height;
        $totalW = array_sum($colWidths);

        $this->pages[$this->pageIndex] .= "0.75 w 0 0 0 RG\n";
        $this->pages[$this->pageIndex] .= sprintf(
            "%.2F %.2F %.2F %.2F re S\n",
            $x,
            $bottom,
            $totalW,
            $height
        );

        $cx = $x;
        for ($i = 0; $i < count($colWidths) - 1; $i++) {
            $cx += $colWidths[$i];
            $this->pages[$this->pageIndex] .= sprintf(
                "%.2F %.2F m %.2F %.2F l S\n",
                $cx,
                $bottom,
                $cx,
                $topY
            );
        }
    }

    /**
     * Assemble le document PDF final (catalogue, pages, polices, flux).
     */
    public function output(): string
    {
        if ($this->pageIndex < 0) {
            $this->addCoverPage('Rapport StudyVibe', 'Document vide');
        }

        $pageCount = count($this->pages);
        $objects   = [];
        $offsets   = [];

        $fontRegular = 3 + ($pageCount * 2);
        $fontBold    = $fontRegular + 1;

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageRefs = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageRefs[] = (4 + ($i * 2)) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . $pageCount . ' >>';

        for ($i = 0; $i < $pageCount; $i++) {
            $contentObj = 3 + ($i * 2);
            $pageObj    = 4 + ($i * 2);
            $stream     = $this->pages[$i];
            $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 '
                . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> /Contents '
                . $contentObj . ' 0 R >>';
        }

        $objects[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$fontBold]    = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $maxObj = $fontBold;
        $pdf    = "%PDF-1.4\n";

        for ($num = 1; $num <= $maxObj; $num++) {
            $offsets[$num] = strlen($pdf);
            $body = $objects[$num] ?? '<< >>';
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($num = 1; $num <= $maxObj; $num++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    /**
     * Envoie le PDF généré en téléchargement HTTP.
     */
    public function sendDownload(string $filename): void
    {
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        echo $this->output();
    }
}
