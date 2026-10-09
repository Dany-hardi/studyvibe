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
     * @param array<int, array{name: string, headers: string[], rows: array<int, array<int, string|int|float|null>>}> $sheets
     */
    public static function buildWorkbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
        $xml .= 'xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= '<Styles>' . "\n";
        $xml .= '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="' . Brand::INK . '"/><Interior ss:Color="#ECE5D6" ss:Pattern="Solid"/>'
              . '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="' . Brand::CLAY . '"/></Borders></Style>' . "\n";
        $xml .= '<Style ss:ID="Brand"><Font ss:FontName="Arial Rounded MT Bold" ss:Size="20" ss:Bold="1" ss:Color="' . Brand::WORD . '"/><Alignment ss:Vertical="Center"/></Style>' . "\n";
        $xml .= '<Style ss:ID="BrandSub"><Font ss:Size="9" ss:Color="' . Brand::INK2 . '"/></Style>' . "\n";
        $xml .= '</Styles>' . "\n";

        foreach ($sheets as $sheet) {
            $xml .= self::buildWorksheetXml(
                (string)$sheet['name'],
                (array)$sheet['headers'],
                (array)$sheet['rows']
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
    public static function buildWorksheetXml(string $name, array $headers, array $rows): string
    {
        $safeName = self::escapeXml(mb_substr($name, 0, 31));
        $xml  = '<Worksheet ss:Name="' . $safeName . '"><Table>' . "\n";

        // Brand banner: the wordmark set as rich text (Excel cells cannot hold the vector logo), V in clay
        $xml .= '<Row ss:Height="30"><Cell ss:StyleID="Brand"><ss:Data ss:Type="String" xmlns="http://www.w3.org/TR/REC-html40">'
              . '<Font html:Face="Arial Rounded MT Bold" html:Size="20" html:Color="' . Brand::WORD . '"><B>study</B></Font>'
              . '<Font html:Face="Arial Rounded MT Bold" html:Size="20" html:Color="' . Brand::CLAY . '"><B>vibe</B></Font>'
              . '</ss:Data></Cell></Row>' . "\n";
        $xml .= '<Row><Cell ss:StyleID="BrandSub"><Data ss:Type="String">' . self::escapeXml($name . ' · ' . date('d/m/Y H:i')) . '</Data></Cell></Row>' . "\n";
        $xml .= '<Row/>' . "\n";

        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . self::escapeXml((string)$header) . '</Data></Cell>';
        }
        $xml .= '</Row>' . "\n";

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $xml .= self::buildCellXml($cell);
            }
            $xml .= '</Row>' . "\n";
        }

        $xml .= '</Table></Worksheet>' . "\n";
        return $xml;
    }

    /**
     * Détermine le type de cellule Excel (nombre ou texte) et produit le XML correspondant.
     */
    public static function buildCellXml(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '<Cell><Data ss:Type="String"></Data></Cell>';
        }

        if (is_int($value) || is_float($value)) {
            return '<Cell><Data ss:Type="Number">' . self::escapeXml((string)$value) . '</Data></Cell>';
        }

        $str = (string)$value;
        if (is_numeric($str) && !str_contains($str, ' ')) {
            return '<Cell><Data ss:Type="Number">' . self::escapeXml($str) . '</Data></Cell>';
        }

        return '<Cell><Data ss:Type="String">' . self::escapeXml($str) . '</Data></Cell>';
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
