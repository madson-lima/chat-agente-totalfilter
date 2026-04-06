<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ProductSpreadsheetImportService
{
    private const CHUNK_SIZE = 250;

    public function __construct(
        private ProductRepository $productRepository,
        private ProductSpreadsheetNormalizer $normalizer,
        private ?Logger $logger = null
    ) {
    }

    public function import(string $filePath, string $sheetName = 'BASE DE DADOS'): array
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException('Planilha nao encontrada: ' . $filePath);
        }

        $highestRow = $this->highestRow($filePath, $sheetName);
        $previewRows = $this->readRows($filePath, $sheetName, 1, min(40, $highestRow));
        $header = $this->normalizer->detectHeaderMap($previewRows);
        $headerRow = ((int) $header['row_index']) + 1;
        $headerMap = $header['map'];

        $stats = [
            'sheet' => $sheetName,
            'header_row' => $headerRow,
            'lidos' => 0,
            'inseridos' => 0,
            'atualizados' => 0,
            'ignorados' => 0,
            'erros' => 0,
        ];

        for ($startRow = $headerRow + 1; $startRow <= $highestRow; $startRow += self::CHUNK_SIZE) {
            $endRow = min($startRow + self::CHUNK_SIZE - 1, $highestRow);
            foreach ($this->readRows($filePath, $sheetName, $startRow, $endRow) as $offset => $row) {
                $rowNumber = $startRow + $offset;
                $stats['lidos']++;

                try {
                    $normalized = $this->normalizer->normalizeRow($row, $headerMap, $rowNumber);
                    if ($normalized === null) {
                        $stats['ignorados']++;
                        continue;
                    }

                    $result = $this->productRepository->upsertImportedProduct($normalized);
                    if ($result === 'inserted') {
                        $stats['inseridos']++;
                    } else {
                        $stats['atualizados']++;
                    }
                } catch (Throwable $exception) {
                    $stats['erros']++;
                    $this->logger?->error('Falha ao importar linha da planilha', [
                        'linha' => $rowNumber,
                        'erro' => $exception->getMessage(),
                    ]);
                }
            }

            gc_collect_cycles();
        }

        $this->logger?->info('Importacao de produtos concluida', $stats);
        return $stats;
    }

    private function highestRow(string $filePath, string $sheetName): int
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $worksheets = $reader->listWorksheetInfo($filePath);
        foreach ($worksheets as $worksheet) {
            if (($worksheet['worksheetName'] ?? '') === $sheetName) {
                return (int) ($worksheet['totalRows'] ?? 0);
            }
        }

        throw new RuntimeException('Aba nao encontrada: ' . $sheetName);
    }

    private function readRows(string $filePath, string $sheetName, int $startRow, int $endRow): array
    {
        $rows = [];
        if ($endRow < $startRow) {
            return $rows;
        }

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }
        $reader->setReadFilter(new ProductSpreadsheetChunkReadFilter($startRow, $endRow));

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet instanceof Worksheet) {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Aba nao encontrada: ' . $sheetName);
        }

        $highestColumn = $sheet->getHighestDataColumn();
        for ($row = $startRow; $row <= $endRow; $row++) {
            $rows[] = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $sheet, $reader);

        return $rows;
    }
}
