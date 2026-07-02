<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class XlsxSheetReader
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PKG_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * @return array{
     *     rows: array<int, list<string>>,
     *     hyperlinks: array<string, string>,
     *     fill_colors: array<string, string>
     * }
     */
    public function readSheet(string $path, string $sheetName): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Workbook not found: {$path}");
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open workbook: {$path}");
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetPath = $this->resolveSheetPath($zip, $sheetName);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new RuntimeException("Worksheet not found: {$sheetName}");
            }

            return [
                'rows' => $this->parseSheet($sheetXml, $sharedStrings),
                'hyperlinks' => $this->parseHyperlinks($zip, $sheetPath, $sheetXml),
                'fill_colors' => $this->parseFillColors($zip, $sheetXml),
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<list<string>>
     */
    public function rows(string $path, string $sheetName): array
    {
        $sheet = $this->readSheet($path, $sheetName);

        return array_values($sheet['rows']);
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);

        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('m', self::NS);
        $strings = [];

        foreach ($document->xpath('//m:si') ?: [] as $item) {
            $item->registerXPathNamespace('m', self::NS);
            $parts = $item->xpath('.//m:t') ?: [];
            $strings[] = implode('', array_map(fn ($node) => (string) $node, $parts));
        }

        return $strings;
    }

    private function resolveSheetPath(ZipArchive $zip, string $sheetName): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');

        if ($workbookXml === false) {
            throw new RuntimeException('Invalid workbook: missing xl/workbook.xml');
        }

        $workbook = simplexml_load_string($workbookXml);

        if ($workbook === false) {
            throw new RuntimeException('Invalid workbook XML.');
        }

        $workbook->registerXPathNamespace('m', self::NS);
        $workbook->registerXPathNamespace('r', self::REL_NS);

        $relationshipId = null;

        foreach ($workbook->xpath('//m:sheet') ?: [] as $sheet) {
            if ((string) $sheet['name'] === $sheetName) {
                $relationshipId = (string) $sheet->attributes(self::REL_NS)['id'];

                break;
            }
        }

        if ($relationshipId === null || $relationshipId === '') {
            throw new RuntimeException("Worksheet not found: {$sheetName}");
        }

        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($relsXml === false) {
            throw new RuntimeException('Invalid workbook: missing relationships.');
        }

        $rels = simplexml_load_string($relsXml);

        if ($rels === false) {
            throw new RuntimeException('Invalid workbook relationships XML.');
        }

        $rels->registerXPathNamespace('r', self::PKG_REL_NS);

        foreach ($rels->xpath('//r:Relationship') ?: [] as $relationship) {
            if ((string) $relationship['Id'] === $relationshipId) {
                $target = (string) $relationship['Target'];

                return str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/'.ltrim($target, '/');
            }
        }

        throw new RuntimeException("Worksheet relationship not found: {$sheetName}");
    }

    /**
     * @return array<string, string>
     */
    private function parseHyperlinks(ZipArchive $zip, string $sheetPath, string $sheetXml): array
    {
        $document = simplexml_load_string($sheetXml);

        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('m', self::NS);
        $hyperlinkNodes = $document->xpath('//m:hyperlink') ?: [];

        if ($hyperlinkNodes === []) {
            return [];
        }

        $relationships = $this->sheetRelationships($zip, $sheetPath);
        $hyperlinks = [];

        foreach ($hyperlinkNodes as $hyperlink) {
            $reference = strtoupper((string) $hyperlink['ref']);
            $url = trim((string) ($hyperlink['location'] ?? ''));

            if ($url === '') {
                $relationshipId = (string) $hyperlink->attributes(self::REL_NS)['id'];
                $url = trim((string) ($relationships[$relationshipId] ?? ''));
            }

            if ($reference !== '' && $url !== '') {
                $hyperlinks[$reference] = $url;
            }
        }

        return $hyperlinks;
    }

    /**
     * @return array<string, string>
     */
    private function sheetRelationships(ZipArchive $zip, string $sheetPath): array
    {
        $relsPath = dirname($sheetPath).'/_rels/'.basename($sheetPath).'.rels';
        $relsXml = $zip->getFromName($relsPath);

        if ($relsXml === false) {
            return [];
        }

        $rels = simplexml_load_string($relsXml);

        if ($rels === false) {
            return [];
        }

        $rels->registerXPathNamespace('r', self::PKG_REL_NS);
        $mapped = [];

        foreach ($rels->xpath('//r:Relationship') ?: [] as $relationship) {
            $mapped[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        return $mapped;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array<int, list<string>>
     */
    private function parseSheet(string $sheetXml, array $sharedStrings): array
    {
        $document = simplexml_load_string($sheetXml);

        if ($document === false) {
            throw new RuntimeException('Invalid worksheet XML.');
        }

        $document->registerXPathNamespace('m', self::NS);
        $rows = [];

        foreach ($document->xpath('//m:sheetData/m:row') ?: [] as $row) {
            $row->registerXPathNamespace('m', self::NS);
            $rowNumber = (int) $row['r'];
            $cells = [];

            foreach ($row->xpath('m:c') ?: [] as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndex($reference);
                $valueNode = $cell->children(self::NS)->v ?? null;
                $value = '';

                if ($valueNode !== null) {
                    $raw = (string) $valueNode;

                    if ((string) $cell['t'] === 's') {
                        $value = $sharedStrings[(int) $raw] ?? $raw;
                    } else {
                        $value = $raw;
                    }
                }

                $cells[$columnIndex] = trim($value);
            }

            if ($cells === []) {
                continue;
            }

            $maxColumn = max(array_keys($cells));
            $normalized = [];

            for ($index = 0; $index <= $maxColumn; $index++) {
                $normalized[] = $cells[$index] ?? '';
            }

            $rows[$rowNumber] = $normalized;
        }

        ksort($rows);

        return $rows;
    }

    private function columnIndex(string $cellReference): int
    {
        $letters = preg_replace('/\d+/', '', $cellReference) ?: '';
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @return array<string, string>
     */
    private function parseFillColors(ZipArchive $zip, string $sheetXml): array
    {
        $stylesXml = $zip->getFromName('xl/styles.xml');

        if ($stylesXml === false) {
            return [];
        }

        $styles = simplexml_load_string($stylesXml);

        if ($styles === false) {
            return [];
        }

        $styles->registerXPathNamespace('m', self::NS);
        $fills = $this->parseFills($styles);
        $cellXfs = $this->parseCellXfs($styles);
        $themeColors = $this->parseThemeColors($zip);

        $document = simplexml_load_string($sheetXml);

        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('m', self::NS);
        $fillColors = [];

        foreach ($document->xpath('//m:sheetData/m:row/m:c') ?: [] as $cell) {
            $reference = strtoupper((string) $cell['r']);
            $styleIndex = (int) ($cell['s'] ?? -1);

            if ($reference === '' || $styleIndex < 0) {
                continue;
            }

            $fillId = $cellXfs[$styleIndex]['fillId'] ?? null;

            if ($fillId === null) {
                continue;
            }

            $color = $this->resolveFillColor($fills[$fillId] ?? [], $themeColors);

            if ($color !== '') {
                $fillColors[$reference] = $color;
            }
        }

        return $fillColors;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseFills(\SimpleXMLElement $styles): array
    {
        $fills = [];

        foreach ($styles->xpath('//m:fills/m:fill') ?: [] as $index => $fill) {
            $fill->registerXPathNamespace('m', self::NS);
            $foreground = $fill->xpath('.//m:fgColor')[0] ?? null;

            if ($foreground === null) {
                $fills[$index] = [];

                continue;
            }

            $fills[$index] = [
                'rgb' => (string) ($foreground['rgb'] ?? ''),
                'theme' => (string) ($foreground['theme'] ?? ''),
                'tint' => (string) ($foreground['tint'] ?? ''),
            ];
        }

        return $fills;
    }

    /**
     * @return array<int, array{fillId: int}>
     */
    private function parseCellXfs(\SimpleXMLElement $styles): array
    {
        $cellXfs = [];

        foreach ($styles->xpath('//m:cellXfs/m:xf') ?: [] as $index => $xf) {
            $cellXfs[$index] = [
                'fillId' => (int) ($xf['fillId'] ?? 0),
            ];
        }

        return $cellXfs;
    }

    /**
     * @return array<string, string>
     */
    private function parseThemeColors(ZipArchive $zip): array
    {
        $themeXml = $zip->getFromName('xl/theme/theme1.xml');

        if ($themeXml === false) {
            return [];
        }

        $theme = simplexml_load_string($themeXml);

        if ($theme === false) {
            return [];
        }

        $theme->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $colors = [];

        foreach ($theme->xpath('//a:clrScheme/*') ?: [] as $node) {
            $name = $node->getName();
            $srgb = $node->xpath('.//a:srgbClr')[0] ?? null;
            $system = $node->xpath('.//a:sysClr')[0] ?? null;
            $colors[$name] = $srgb
                ? (string) $srgb['val']
                : (string) ($system['lastClr'] ?? '');
        }

        return $colors;
    }

    /**
     * @param  array<string, string>  $fill
     * @param  array<string, string>  $themeColors
     */
    private function resolveFillColor(array $fill, array $themeColors): string
    {
        $rgb = strtoupper(ltrim($fill['rgb'] ?? '', '#'));

        if ($rgb !== '') {
            if (strlen($rgb) === 8) {
                return '#'.substr($rgb, 2);
            }

            if (strlen($rgb) === 6) {
                return '#'.$rgb;
            }
        }

        if (($fill['theme'] ?? '') === '') {
            return '';
        }

        $themeNames = ['lt1', 'dk1', 'lt2', 'dk2', 'accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6', 'hlink', 'folHlink'];
        $themeName = $themeNames[(int) $fill['theme']] ?? 'accent1';
        $hex = $themeColors[$themeName] ?? '';

        return $hex !== '' ? '#'.$hex : '';
    }
}
