<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

$filePath = $argv[1] ?? '';
$sheetName = $argv[2] ?? 'BASE DE DADOS';

if ($filePath === '') {
    fwrite(STDERR, "Uso: php database/seeds/import_products_spreadsheet.php \"C:\\caminho\\REGISTRO DE PRODUTOS ACABADOS.xlsm\" [ABA]\n");
    exit(1);
}

try {
    $config = appConfig();
    $logger = new Logger($config);
    $repository = new ProductRepository(database());
    $normalizer = new ProductSpreadsheetNormalizer();
    $service = new ProductSpreadsheetImportService($repository, $normalizer, $logger);
    $stats = $service->import($filePath, $sheetName);

    echo "Importacao concluida.\n";
    foreach ($stats as $key => $value) {
        echo $key . ': ' . $value . "\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha na importacao: ' . $exception->getMessage() . "\n");
    exit(1);
}
