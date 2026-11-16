<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal, dependency-free .xlsx reader/writer (ZipArchive + SimpleXML).
 *
 * Deliberately tiny: it reads the FIRST worksheet as a grid of strings and
 * writes simple multi-sheet workbooks (text cells, bold header row). That is
 * all the vehicle bulk-import needs, and it avoids adding a Composer package.
 * Formulas are read as their cached values; styles are ignored (so a date
 * cell comes back as its Excel serial number — callers convert it).
 */
class SimpleXlsx
{
    /**
     * @return array<int, array<int, string>>  rows (0-indexed), each a list of cell strings
     */
    public static function read(string $path, int $maxRows = 5000): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The file is not a valid .xlsx workbook.');
        }

        try {
            $sheetPath = self::firstSheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false) {
                throw new RuntimeException('The workbook has no readable worksheet.');
            }

            $shared = self::sharedStrings($zip);
            $sheet = self::xml($sheetXml);
        } finally {
            $zip->close();
        }

        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            if (count($rows) >= $maxRows + 1) {
                break;
            }

            $cells = [];
            $next = 0;
            foreach ($row->c as $cell) {
                $index = isset($cell['r']) ? self::columnIndex((string) $cell['r']) : $next;
                $next = $index + 1;
                $cells[$index] = self::cellValue($cell, $shared);
            }

            $line = [];
            $max = $cells ? max(array_keys($cells)) : -1;
            for ($i = 0; $i <= $max; $i++) {
                $line[$i] = $cells[$i] ?? '';
            }

            // Preserve the source row number so error messages match Excel.
            $rowNumber = isset($row['r']) ? (int) $row['r'] : (count($rows) + 1);
            $rows[$rowNumber - 1] = $line;
        }

        return $rows;
    }

    /**
     * @param  array<string, array<int, array<int, mixed>>>  $sheets  sheet name => rows (first row = header)
     * @param  array<string, array<int, float>>  $widths  optional sheet name => column widths
     * @param  array<string, array<int, array{sqref: string, formula: string}>>  $validations  optional sheet name => dropdown (list) rules;
     *         'formula' is an Excel list source such as "Reference!$A$2:$A$9" (no leading "=")
     * @param  array<string, string>  $names  optional defined names: name => reference such as "Reference!$C$2:$C$5"
     */
    public static function write(string $path, array $sheets, array $widths = [], array $validations = [], array $names = []): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the workbook file.');
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        $n = 0;
        foreach ($sheets as $name => $rows) {
            $n++;
            $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbook .= '<sheet name="' . self::esc(mb_substr((string) $name, 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', self::sheetXml($rows, $widths[$name] ?? [], $validations[$name] ?? []));
        }

        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $workbook .= '</sheets>';
        if ($names) {
            $workbook .= '<definedNames>';
            foreach ($names as $name => $ref) {
                $workbook .= '<definedName name="' . self::esc($name) . '">' . self::esc($ref) . '</definedName>';
            }
            $workbook .= '</definedNames>';
        }
        $workbook .= '</workbook>';

        $zip->addFromString('[Content_Types].xml', $contentTypes . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');

        $zip->close();
    }

    protected static function sheetXml(array $rows, array $widths, array $validations = []): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if ($widths) {
            $xml .= '<cols>';
            foreach (array_values($widths) as $i => $w) {
                $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach (array_values($rows) as $r => $row) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($row) as $c => $value) {
                $ref = self::columnLetter($c) . ($r + 1);
                $style = $r === 0 ? ' s="1"' : '';
                $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . self::esc((string) $value) . '</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        if ($validations) {
            $xml .= '<dataValidations count="' . count($validations) . '">';
            foreach ($validations as $rule) {
                $xml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorStyle="stop"'
                    . ' errorTitle="Pick from the list" error="Please choose a value from the dropdown list."'
                    . ' sqref="' . self::esc($rule['sqref']) . '"><formula1>' . self::esc($rule['formula']) . '</formula1></dataValidation>';
            }
            $xml .= '</dataValidations>';
        }

        return $xml . '</worksheet>';
    }

    protected static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $wb = self::xml($workbook);
            $first = $wb->sheets->sheet[0] ?? null;
            if ($first) {
                $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach (self::xml($rels)->Relationship ?? [] as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');

                        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** @return array<int, string> */
    protected static function sharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');
        if ($raw === false) {
            return [];
        }

        $strings = [];
        foreach (self::xml($raw)->si ?? [] as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }
            $text = '';
            foreach ($si->r ?? [] as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    protected static function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            if (isset($cell->is->t)) {
                return (string) $cell->is->t;
            }
            $text = '';
            foreach ($cell->is->r ?? [] as $run) {
                $text .= (string) $run->t;
            }

            return $text;
        }

        $value = (string) ($cell->v ?? '');

        return match ($type) {
            's'     => $shared[(int) $value] ?? '',
            'b'     => $value === '1' ? 'TRUE' : 'FALSE',
            default => $value,
        };
    }

    protected static function xml(string $raw): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new RuntimeException('The workbook could not be read — is it a genuine .xlsx file?');
        }

        return $xml;
    }

    /** "C12" => 2 */
    protected static function columnIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/i', $ref, $m);
        $letters = strtoupper($m[0] ?? 'A');
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    protected static function columnLetter(int $index): string
    {
        $letters = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letters = chr(65 + (($i - 1) % 26)) . $letters;
        }

        return $letters;
    }

    protected static function esc(string $value): string
    {
        // Strip control characters XML 1.0 forbids, then escape.
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
