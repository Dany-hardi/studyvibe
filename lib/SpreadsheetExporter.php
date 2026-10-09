<?php
declare(strict_types=1);

/**
 * SpreadsheetExporter — génération de fichiers Excel (.xls) via SpreadsheetML.
 *
 * Aucune dépendance Composer : le format XML SpreadsheetML est ouvert par
 * Microsoft Excel, LibreOffice Calc et Google Sheets.
 */
require_once __DIR__ . '/Brand.php';

class SpreadsheetExporter
{
    /**
     * Échappe les caractères spéciaux XML pour éviter les fichiers corrompus.
     */
    public static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Construit le XML SpreadsheetML pour un classeur multi-feuilles.
     *
     * A sheet may also carry 'title' (a heading under the logo), 'meta' (a list of [label, value] pairs shown above the table) and
     * 'formats' (column index => 'int' | 'dec' | 'pct' | 'text'; numbers default to a plain number, text to text).
     *
     * @param array<int, array{name: string, headers: string[], rows: array<int, array<int, string|int|float|null>>, title?: string, meta?: array, formats?: array}> $sheets
     */
    public static function buildWorkbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office"><Author>StudyVibe</Author><Company>StudyVibe</Company></DocumentProperties>' . "\n";
        $line = '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D8D2C4"/>';
        $xml .= '<Styles>' . "\n";
        $xml .= '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11" ss:Color="' . Brand::INK . '"/></Style>' . "\n";
        // Header row: bold, light fill, a clay rule underneath, wrapped, centred vertically
        $xml .= '<Style ss:ID="Header"><Font ss:FontName="Calibri" ss:Bold="1" ss:Color="' . Brand::INK . '"/><Interior ss:Color="#ECE5D6" ss:Pattern="Solid"/><Alignment ss:Vertical="Center" ss:WrapText="1"/>'
              . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="' . Brand::CLAY . '"/></Borders></Style>' . "\n";
        $xml .= '<Style ss:ID="HeaderNum"><Font ss:FontName="Calibri" ss:Bold="1" ss:Color="' . Brand::INK . '"/><Interior ss:Color="#ECE5D6" ss:Pattern="Solid"/><Alignment ss:Horizontal="Right" ss:Vertical="Center" ss:WrapText="1"/>'
              . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="' . Brand::CLAY . '"/></Borders></Style>' . "\n";
        // Body cells: text, whole number, one decimal, percent, date; each in a plain and a striped version
        foreach (['' => '', 'Alt' => '<Interior ss:Color="#FAF7F0" ss:Pattern="Solid"/>'] as $suffix => $fill) {
            $xml .= '<Style ss:ID="Text' . $suffix . '"><Alignment ss:Vertical="Center" ss:WrapText="1"/>' . $fill . '<Borders>' . $line . '</Borders></Style>' . "\n";
            $xml .= '<Style ss:ID="Int' . $suffix . '"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/>' . $fill . '<Borders>' . $line . '</Borders><NumberFormat ss:Format="0"/></Style>' . "\n";
            $xml .= '<Style ss:ID="Dec' . $suffix . '"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/>' . $fill . '<Borders>' . $line . '</Borders><NumberFormat ss:Format="0.0"/></Style>' . "\n";
            $xml .= '<Style ss:ID="Pct' . $suffix . '"><Alignment ss:Horizontal="Right" ss:Vertical="Center"/>' . $fill . '<Borders>' . $line . '</Borders><NumberFormat ss:Format="0.0&quot; %&quot;"/></Style>' . "\n";
        }
        $xml .= '<Style ss:ID="Brand"><Font ss:FontName="Arial Rounded MT Bold" ss:Size="20" ss:Bold="1" ss:Color="' . Brand::WORD . '"/><Alignment ss:Vertical="Center"/></Style>' . "\n";
        $xml .= '<Style ss:ID="Title"><Font ss:FontName="Calibri" ss:Size="15" ss:Bold="1" ss:Color="' . Brand::INK . '"/><Alignment ss:Vertical="Center"/></Style>' . "\n";
        $xml .= '<Style ss:ID="MetaKey"><Font ss:FontName="Calibri" ss:Italic="1" ss:Color="' . Brand::INK2 . '"/></Style>' . "\n";
        $xml .= '<Style ss:ID="BrandSub"><Font ss:FontName="Calibri" ss:Size="9" ss:Color="' . Brand::INK2 . '"/></Style>' . "\n";
        $xml .= '</Styles>' . "\n";

        foreach ($sheets as $sheet) {
            $xml .= self::buildWorksheetXml(
                (string)$sheet['name'],
                (array)$sheet['headers'],
                (array)$sheet['rows'],
                isset($sheet['title']) ? (string)$sheet['title'] : null,
                (array)($sheet['meta'] ?? []),
                (array)($sheet['formats'] ?? [])
            );
        }

        $xml .= '</Workbook>';
        return $xml;
    }

    /**
     * Construit une feuille de calcul individuelle (en-têtes + lignes de données).
     *
     * @param string[] $headers
     * @param array<int, array<int, string|int|float|null>> $rows
     */
    public static function buildWorksheetXml(string $name, array $headers, array $rows, ?string $title = null, array $meta = [], array $formats = []): string
    {
        $safeName = self::escapeXml(trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', mb_substr($name, 0, 31))) ?: 'Feuille');
        $nCols = max(1, count($headers));

        // Column widths from what the columns hold (in points, between 60 and 330)
        $widths = [];
        foreach ($headers as $c => $h) {
            $widths[$c] = mb_strlen((string)$h);
        }
        foreach (array_slice($rows, 0, 400) as $row) {
            foreach (array_values($row) as $c => $cell) {
                $widths[$c] = max($widths[$c] ?? 0, min(60, mb_strlen((string)$cell)));
            }
        }

        $xml  = '<Worksheet ss:Name="' . $safeName . '"><Table ss:DefaultRowHeight="18">' . "\n";
        for ($c = 0; $c < $nCols; $c++) {
            $xml .= '<Column ss:AutoFitWidth="0" ss:Width="' . max(60, min(330, (int)round(($widths[$c] ?? 8) * 6.2 + 14))) . '"/>';
        }
        $xml .= "\n";

        // Brand banner: the wordmark set as rich text (Excel cells cannot hold the vector logo), V in clay
        $xml .= '<Row ss:Height="30"><Cell ss:StyleID="Brand"><ss:Data ss:Type="String" xmlns="http://www.w3.org/TR/REC-html40">'
              . '<Font html:Face="Arial Rounded MT Bold" html:Size="20" html:Color="' . Brand::WORD . '"><B>study</B></Font>'
              . '<Font html:Face="Arial Rounded MT Bold" html:Size="20" html:Color="' . Brand::CLAY . '"><B>vibe</B></Font>'
              . '</ss:Data></Cell></Row>' . "\n";
        $headerRow = 1;
        if ($title !== null && $title !== '') {
            $xml .= '<Row ss:Height="24"><Cell ss:StyleID="Title"><Data ss:Type="String">' . self::escapeXml($title) . '</Data></Cell></Row>' . "\n";
            $headerRow++;
        }
        foreach ($meta as $pair) {
            $xml .= '<Row><Cell ss:StyleID="MetaKey"><Data ss:Type="String">' . self::escapeXml((string)($pair[0] ?? '')) . '</Data></Cell>'
                  . '<Cell><Data ss:Type="String">' . self::escapeXml((string)($pair[1] ?? '')) . '</Data></Cell></Row>' . "\n";
            $headerRow++;
        }
        $xml .= '<Row><Cell ss:StyleID="BrandSub"><Data ss:Type="String">' . self::escapeXml($name . ' · ' . date('d/m/Y H:i')) . '</Data></Cell></Row>' . "\n";
        $xml .= '<Row/>' . "\n";
        $headerRow += 3;   // wordmark counted above; this is the date line and the blank row

        $xml .= '<Row ss:Height="24">';
        foreach ($headers as $c => $header) {
            $numeric = in_array($formats[$c] ?? '', ['int', 'dec', 'pct'], true);
            $xml .= '<Cell ss:StyleID="' . ($numeric ? 'HeaderNum' : 'Header') . '"><Data ss:Type="String">' . self::escapeXml((string)$header) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";

        foreach ($rows as $i => $row) {
            $xml .= '<Row>';
            foreach (array_values($row) as $c => $cell) {
                $xml .= self::buildCellXml($cell, ($formats[$c] ?? null), $i % 2 === 1);
            }
            $xml .= '</Row>' . "\n";
        }
        $xml .= '</Table>' . "\n";

        // Header row stays visible while scrolling, filter arrows on the header, A4 landscape fitted to one page wide
        $last = $headerRow + count($rows);
        $xml .= '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'
              . '<PageSetup><Layout x:Orientation="Landscape"/><Header x:Data="&amp;L&amp;&quot;Calibri,Bold&quot;StudyVibe&amp;R' . self::escapeXml($name) . '"/><Footer x:Data="&amp;CPage &amp;P / &amp;N"/></PageSetup>'
              . '<FitToPage/><Print><FitHeight>0</FitHeight><ValidPrinterInfo/><PaperSizeIndex>9</PaperSizeIndex></Print>'
              . '<FreezePanes/><FrozenNoSplit/><SplitHorizontal>' . $headerRow . '</SplitHorizontal><TopRowBottomPane>' . $headerRow . '</TopRowBottomPane><ActivePane>2</ActivePane>'
              . '<Panes><Pane><Number>3</Number></Pane><Pane><Number>2</Number></Pane></Panes></WorksheetOptions>' . "\n";
        if ($rows) {
            $xml .= '<AutoFilter x:Range="R' . $headerRow . 'C1:R' . $last . 'C' . $nCols . '" xmlns="urn:schemas-microsoft-com:office:excel"/>' . "\n";
        }
        $xml .= '</Worksheet>' . "\n";
        return $xml;
    }

    /**
     * Détermine le type de cellule Excel (nombre ou texte) et produit le XML correspondant.
     */
    public static function buildCellXml(string|int|float|null $value, ?string $format = null, bool $striped = false): string
    {
        $alt = $striped ? 'Alt' : '';
        if ($value === null || $value === '') {
            return '<Cell ss:StyleID="Text' . $alt . '"><Data ss:Type="String"></Data></Cell>';
        }

        $isNumber = is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && !str_contains($value, ' ') && $format !== 'text');
        if ($isNumber) {
            $style = match ($format) {
                'pct' => 'Pct',
                'dec' => 'Dec',
                'int' => 'Int',
                default => is_float($value) || (is_string($value) && str_contains($value, '.')) ? 'Dec' : 'Int',
            };
            return '<Cell ss:StyleID="' . $style . $alt . '"><Data ss:Type="Number">' . self::escapeXml((string)$value) . '</Data></Cell>';
        }

        return '<Cell ss:StyleID="Text' . $alt . '"><Data ss:Type="String">' . self::escapeXml((string)$value) . '</Data></Cell>';
    }

    /**
     * Envoie le classeur au navigateur en tant que téléchargement Excel.
     *
     * @param array<int, array{name: string, headers: string[], rows: array<int, array<int, string|int|float|null>>}> $sheets
     */
    public static function sendDownload(string $filename, array $sheets): void
    {
        $xml = self::buildWorkbookXml($sheets);

        if (!str_ends_with(strtolower($filename), '.xls')) {
            $filename .= '.xls';
        }

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        echo $xml;
    }
}
