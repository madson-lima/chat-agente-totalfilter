<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

$filePath = $argv[1] ?? '';
$sheetName = $argv[2] ?? 'BASE DE DADOS';
$statusFile = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--status-file=')) {
        $statusFile = substr($argument, strlen('--status-file='));
    }
}

if ($filePath === '') {
    fwrite(STDERR, "Uso: php database/seeds/import_products_spreadsheet.php \"C:\\caminho\\REGISTRO DE PRODUTOS ACABADOS.xlsm\" [ABA]\n");
    exit(1);
}

try {
    writeImportStatus($statusFile, [
        'ok' => true,
        'status' => 'running',
        'file' => $filePath,
        'sheet' => $sheetName,
        'started_at' => date(DATE_ATOM),
    ]);

    $config = appConfig();
    $logger = new Logger($config);
    $repository = new ProductRepository(database());
    $normalizer = new ProductSpreadsheetNormalizer();
    $service = new ProductSpreadsheetImportService($repository, $normalizer, $logger);
    $stats = $service->import($filePath, $sheetName, static function (array $progress) use ($statusFile, $filePath, $sheetName): void {
        writeImportStatus($statusFile, [
            'ok' => true,
            'status' => 'running',
            'file' => $filePath,
            'sheet' => $sheetName,
            'updated_at' => date(DATE_ATOM),
            'progress' => $progress,
        ]);
    });

    echo "Importacao concluida.\n";
    foreach ($stats as $key => $value) {
        echo $key . ': ' . $value . "\n";
    }

    writeImportStatus($statusFile, [
        'ok' => true,
        'status' => 'completed',
        'file' => $filePath,
        'sheet' => $sheetName,
        'completed_at' => date(DATE_ATOM),
        'stats' => $stats,
    ]);
} catch (Throwable $exception) {
    writeImportStatus($statusFile, [
        'ok' => false,
        'status' => 'failed',
        'file' => $filePath,
        'sheet' => $sheetName,
        'failed_at' => date(DATE_ATOM),
        'message' => $exception->getMessage(),
    ]);
    fwrite(STDERR, 'Falha na importacao: ' . $exception->getMessage() . "\n");
    exit(1);
}

function writeImportStatus(string $statusFile, array $status): void
{
    if ($statusFile === '') {
        return;
    }

    file_put_contents($statusFile, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}
