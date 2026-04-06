<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ProductSpreadsheetImportService
{
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

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet instanceof Worksheet) {
            throw new RuntimeException('Aba nao encontrada: ' . $sheetName);
        }

        $previewRows = $this->readRows($sheet, 1, min(40, $sheet->getHighestDataRow()));
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

        $highestRow = $sheet->getHighestDataRow();
        foreach ($this->readRows($sheet, $headerRow + 1, $highestRow) as $offset => $row) {
            $rowNumber = $headerRow + 1 + $offset;
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

        $this->logger?->info('Importacao de produtos concluida', $stats);
        return $stats;
    }

    private function readRows(Worksheet $sheet, int $startRow, int $endRow): array
    {
        $rows = [];
        if ($endRow < $startRow) {
            return $rows;
        }

        $highestColumn = $sheet->getHighestDataColumn();
        for ($row = $startRow; $row <= $endRow; $row++) {
            $rows[] = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
        }

        return $rows;
    }
}
