<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app-loader.php';
require_once totalfilterAppBasePath() . '/api/bootstrap.php';

$appConfig = appConfig();
applyCorsHeaders($appConfig);

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

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
