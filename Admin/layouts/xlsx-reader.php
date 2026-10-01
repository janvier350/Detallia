<?php
// Lector nativo de XLSX (sin dependencias externas). Reutilizable entre paginas.

if (!function_exists('xlsx_col_to_index')) {
    function xlsx_col_to_index($col)
    {
        $col = strtoupper($col);
        $result = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $result = $result * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $result - 1;
    }
}

if (!function_exists('xlsx_sheet_names')) {
    function xlsx_sheet_names($filepath)
    {
        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new Exception("No se pudo abrir el archivo. Asegurate de que sea un .xlsx valido.");
        }
        $names = [];
        $wbXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        if ($wbXml) {
            foreach ($wbXml->sheets->sheet as $sheet) {
                $names[] = trim((string) $sheet['name']);
            }
        }
        $zip->close();
        return $names;
    }
}

if (!function_exists('parse_xlsx_sheet')) {
    function parse_xlsx_sheet($filepath, $sheetName)
    {
        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new Exception("No se pudo abrir el archivo. Asegurate de que sea un .xlsx valido.");
        }

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $wbXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $wbXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheetRid = null;
        foreach ($wbXml->sheets->sheet as $sheet) {
            if (trim((string) $sheet['name']) === $sheetName) {
                $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $sheetRid = (string) $attrs['id'];
                break;
            }
        }
        if (!$sheetRid) {
            $zip->close();
            throw new Exception("No se encontro la pestana \"$sheetName\" en el archivo.");
        }

        $relsXml = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $target = null;
        foreach ($relsXml->Relationship as $rel) {
            if ((string) $rel['Id'] === $sheetRid) {
                $target = (string) $rel['Target'];
                break;
            }
        }
        if (!$target) {
            $zip->close();
            throw new Exception("No se pudo ubicar el archivo interno de la hoja.");
        }
        $sheetPath = 'xl/' . ltrim($target, '/');

        $sheetXmlRaw = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheetXmlRaw === false) {
            throw new Exception("No se pudo leer el contenido de la hoja.");
        }

        $sheetXml = simplexml_load_string($sheetXmlRaw);
        $rows = [];
        foreach ($sheetXml->sheetData->row as $row) {
            $rowIndex = (int) $row['r'];
            $rowData = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                preg_match('/^([A-Z]+)/', $ref, $m);
                $colIndex = xlsx_col_to_index($m[1]);
                $type = (string) $c['t'];
                $value = '';
                if ($type === 's') {
                    $idx = (int) $c->v;
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) $c->is->t;
                } else {
                    $value = (string) $c->v;
                }
                $rowData[$colIndex] = trim($value);
            }
            $rows[$rowIndex] = $rowData;
        }
        return $rows;
    }
}
