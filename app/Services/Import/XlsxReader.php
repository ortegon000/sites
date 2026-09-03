<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lector mínimo de .xlsx sobre las extensiones que PHP ya trae. Un `.xlsx` es
 * un zip de XML, así que leerlo no justifica una dependencia nueva para lo
 * único que hace falta aquí: importar un libro una vez y no volver a tocarlo.
 *
 * No interpreta fórmulas ni formatos; devuelve el valor tal cual está guardado.
 */
class XlsxReader
{
    /** @var array<int, string> */
    private array $sharedStrings = [];

    /** @var array<string, string> */
    private array $sheetPaths = [];

    public function __construct(private string $path)
    {
        $zip = new ZipArchive;

        if ($zip->open($this->path) !== true) {
            throw new RuntimeException("No se pudo abrir [{$this->path}].");
        }

        $this->readSharedStrings($zip);
        $this->readSheetIndex($zip);

        $zip->close();
    }

    /**
     * @return array<int, string>
     */
    public function sheetNames(): array
    {
        return array_keys($this->sheetPaths);
    }

    /**
     * Las filas de una hoja como arreglos asociativos, usando la primera fila
     * como encabezado. Las filas totalmente vacías se descartan.
     *
     * @return array<int, array<string, string>>
     */
    public function rows(string $sheetName): array
    {
        if (! isset($this->sheetPaths[$sheetName])) {
            throw new RuntimeException("El libro no tiene la hoja [{$sheetName}].");
        }

        $zip = new ZipArchive;
        $zip->open($this->path);
        $xml = new SimpleXMLElement((string) $zip->getFromName($this->sheetPaths[$sheetName]));
        $zip->close();

        $grid = [];

        foreach ($xml->sheetData->row as $row) {
            $cells = [];

            foreach ($row->c as $cell) {
                $cells[$this->columnIndex((string) $cell['r'])] = $this->cellValue($cell);
            }

            $grid[] = $cells;
        }

        if ($grid === []) {
            return [];
        }

        $headers = array_map(
            fn (string $header) => trim($header),
            array_shift($grid) ?: [],
        );

        $rows = [];

        foreach ($grid as $cells) {
            $row = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = trim($cells[$index] ?? '');
                }
            }

            if (array_filter($row, fn (string $value) => $value !== '') !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Excel guarda las fechas como días desde el 30/12/1899. Sin convertirlas,
     * "43982" no significa nada.
     */
    public static function date(?string $serial): ?CarbonImmutable
    {
        if ($serial === null || ! is_numeric($serial)) {
            return null;
        }

        $days = (int) round((float) $serial);

        if ($days <= 0) {
            return null;
        }

        return CarbonImmutable::create(1899, 12, 30)->addDays($days);
    }

    private function readSharedStrings(ZipArchive $zip): void
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');

        if ($raw === false) {
            return;
        }

        foreach ((new SimpleXMLElement($raw))->si as $item) {
            $text = '';

            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                $text .= (string) $node;
            }

            $this->sharedStrings[] = $text;
        }
    }

    private function readSheetIndex(ZipArchive $zip): void
    {
        $workbook = new SimpleXMLElement((string) $zip->getFromName('xl/workbook.xml'));
        $rels = new SimpleXMLElement((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));

        $targets = [];

        foreach ($rels->Relationship as $relationship) {
            $targets[(string) $relationship['Id']] = ltrim((string) $relationship['Target'], '/');
        }

        foreach ($workbook->sheets->sheet as $sheet) {
            $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = $targets[$id] ?? null;

            if ($target !== null) {
                $this->sheetPaths[(string) $sheet['name']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }
    }

    private function cellValue(SimpleXMLElement $cell): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $text = '';

            foreach ($cell->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                $text .= (string) $node;
            }

            return $text;
        }

        $value = (string) $cell->v;

        if ($type === 's') {
            return $this->sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d/', '', $reference) ?? '';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
