<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

$router = new Router();
$rateLimiter = new RateLimitMiddleware(database(), appConfig());
$chatController = new ChatController(database(), appConfig());
$knowledgeController = new KnowledgeController(database(), appConfig());

$router->add('GET', '/api/health', static fn() => jsonResponse([
    'ok' => true,
    'service' => 'totalfilter-assistant',
    'timestamp' => date(DATE_ATOM),
]));

$router->add('GET', '/api/config', static fn() => jsonResponse($chatController->widgetConfig()));
$router->add('POST', '/api/chat/start', static fn() => $rateLimiter->handle(fn() => $chatController->start()));
$router->add('POST', '/api/chat/message', static fn() => $rateLimiter->handle(fn() => $chatController->message()));
$router->add('POST', '/api/chat/reset', static fn() => $chatController->reset());
$router->add('GET', '/api/chat/history', static fn() => $chatController->history());
$router->add('POST', '/api/lead', static fn() => $rateLimiter->handle(fn() => $chatController->captureLead()));
$router->add('POST', '/api/handoff', static fn() => $rateLimiter->handle(fn() => $chatController->requestHandoff()));
$router->add('GET', '/api/faq', static fn() => $knowledgeController->faq());
$router->add('GET', '/api/knowledge', static fn() => $knowledgeController->knowledge());
$router->add('GET', '/api/products', static fn() => $knowledgeController->products());

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
