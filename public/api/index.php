<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app-loader.php';
require_once totalfilterAppBasePath() . '/api/bootstrap.php';

$appConfig = appConfig();
applyCorsHeaders($appConfig);

set_exception_handler(static function (Throwable $exception) use ($appConfig): void {
    (new Logger($appConfig))->error('Erro nao tratado na API', [
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'erro' => $exception->getMessage(),
    ]);
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $showDetail = !empty($appConfig['debug']) || str_starts_with($path, '/api/admin/import-products');

    jsonResponse([
        'ok' => false,
        'message' => 'Erro interno na API.',
        'detail' => $showDetail ? $exception->getMessage() : 'Consulte os logs da aplicacao.',
    ], 500);
});

register_shutdown_function(static function () use ($appConfig): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'] ?? 0, $fatalTypes, true)) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    (new Logger($appConfig))->error('Erro fatal na API', [
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'erro' => $error['message'] ?? '',
        'arquivo' => $error['file'] ?? '',
        'linha' => $error['line'] ?? '',
    ]);

    $path = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $showDetail = !empty($appConfig['debug']) || str_starts_with($path, '/api/admin/import-products');

    jsonResponse([
        'ok' => false,
        'message' => 'Erro fatal na API.',
        'detail' => $showDetail ? ($error['message'] ?? 'Erro fatal') : 'Consulte os logs da aplicacao.',
    ], 500);
});

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

$router->add('GET', '/api/health', static fn() => jsonResponse([
    'ok' => true,
    'service' => 'totalfilter-assistant',
    'timestamp' => date(DATE_ATOM),
]));

$router->add('GET', '/api/config', static function () use ($appConfig) {
    $chatController = new ChatController(database(), $appConfig);
    jsonResponse($chatController->widgetConfig());
});
$router->add('POST', '/api/chat/start', static function () use ($appConfig) {
    $rateLimiter = new RateLimitMiddleware(database(), $appConfig);
    $chatController = new ChatController(database(), $appConfig);
    $rateLimiter->handle(fn() => $chatController->start());
});
$router->add('POST', '/api/chat/message', static function () use ($appConfig) {
    $rateLimiter = new RateLimitMiddleware(database(), $appConfig);
    $chatController = new ChatController(database(), $appConfig);
    $rateLimiter->handle(fn() => $chatController->message());
});
$router->add('POST', '/api/chat/reset', static function () use ($appConfig) {
    $chatController = new ChatController(database(), $appConfig);
    $chatController->reset();
});
$router->add('GET', '/api/chat/history', static function () use ($appConfig) {
    $chatController = new ChatController(database(), $appConfig);
    $chatController->history();
});
$router->add('POST', '/api/lead', static function () use ($appConfig) {
    $rateLimiter = new RateLimitMiddleware(database(), $appConfig);
    $chatController = new ChatController(database(), $appConfig);
    $rateLimiter->handle(fn() => $chatController->captureLead());
});
$router->add('POST', '/api/handoff', static function () use ($appConfig) {
    $rateLimiter = new RateLimitMiddleware(database(), $appConfig);
    $chatController = new ChatController(database(), $appConfig);
    $rateLimiter->handle(fn() => $chatController->requestHandoff());
});
$router->add('GET', '/api/faq', static function () use ($appConfig) {
    $knowledgeController = new KnowledgeController(database(), $appConfig);
    $knowledgeController->faq();
});
$router->add('GET', '/api/knowledge', static function () use ($appConfig) {
    $knowledgeController = new KnowledgeController(database(), $appConfig);
    $knowledgeController->knowledge();
});
$router->add('GET', '/api/products', static function () use ($appConfig) {
    $knowledgeController = new KnowledgeController(database(), $appConfig);
    $knowledgeController->products();
});
$router->add('GET', '/api/admin/import-products/status', static function () use ($appConfig) {
    $token = (string) ($appConfig['product_import']['token'] ?? '');
    $provided = (string) ($_GET['token'] ?? '');
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
        $provided = trim($matches[1]);
    }
    if ($token === '' || $provided === '' || !hash_equals($token, $provided)) {
        jsonResponse(['ok' => false, 'message' => 'Nao autorizado.'], 401);
    }

    $statusFile = dirname(__DIR__, 2) . '/storage/product-import-status.json';
    if (!is_file($statusFile)) {
        jsonResponse(['ok' => true, 'status' => 'not_started']);
    }

    $status = json_decode((string) file_get_contents($statusFile), true);
    jsonResponse(is_array($status) ? $status : ['ok' => false, 'status' => 'invalid_status_file']);
});
$router->add('POST', '/api/admin/import-products', static function () use ($appConfig) {
    $token = (string) ($appConfig['product_import']['token'] ?? '');
    $provided = '';
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
        $provided = trim($matches[1]);
    }
    if ($provided === '') {
        $provided = (string) ($_POST['token'] ?? '');
    }
    if ($token === '' || $provided === '' || !hash_equals($token, $provided)) {
        jsonResponse(['ok' => false, 'message' => 'Nao autorizado.'], 401);
    }

    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        jsonResponse(['ok' => false, 'message' => 'Envie a planilha no campo multipart "file".'], 422);
    }

    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'message' => 'Falha no upload da planilha.', 'upload_error' => $file['error'] ?? null], 422);
    }

    $size = (int) ($file['size'] ?? 0);
    $maxBytes = (int) ($appConfig['product_import']['max_upload_bytes'] ?? 52428800);
    if ($size <= 0 || $size > $maxBytes) {
        jsonResponse(['ok' => false, 'message' => 'Arquivo vazio ou maior que o limite permitido.'], 422);
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsm', 'xlsx', 'xls'], true)) {
        jsonResponse(['ok' => false, 'message' => 'Formato invalido. Envie .xlsm, .xlsx ou .xls.'], 422);
    }

    $sheetName = cleanText((string) ($_POST['sheet'] ?? 'BASE DE DADOS'), 100);
    $sheetName = $sheetName !== '' ? $sheetName : 'BASE DE DADOS';
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $storageDir = dirname(__DIR__, 2) . '/storage';
    $importDir = $storageDir . '/imports';
    if (!is_dir($importDir)) {
        mkdir($importDir, 0775, true);
    }
    $jobId = bin2hex(random_bytes(8));
    $targetPath = $importDir . '/products-' . $jobId . '.' . $extension;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        jsonResponse(['ok' => false, 'message' => 'Nao foi possivel armazenar temporariamente a planilha.'], 500);
    }

    $statusFile = $storageDir . '/product-import-status.json';
    file_put_contents($statusFile, json_encode([
        'ok' => true,
        'status' => 'running',
        'job_id' => $jobId,
        'file' => $originalName,
        'sheet' => $sheetName,
        'started_at' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $phpBinary = PHP_BINARY;
    $script = totalfilterAppBasePath() . '/database/seeds/import_products_spreadsheet.php';
    $command = sprintf(
        '%s %s %s %s --status-file=%s > /tmp/product-import-%s.log 2>&1 &',
        escapeshellarg($phpBinary),
        escapeshellarg($script),
        escapeshellarg($targetPath),
        escapeshellarg($sheetName),
        escapeshellarg($statusFile),
        escapeshellarg($jobId)
    );
    exec($command);

    jsonResponse([
        'ok' => true,
        'status' => 'queued',
        'message' => 'Importacao iniciada em segundo plano.',
        'job_id' => $jobId,
        'status_url' => '/api/admin/import-products/status',
    ], 202);
});

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
