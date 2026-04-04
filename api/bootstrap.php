<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

require_once $basePath . '/config/env.php';
loadEnv($basePath);
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));

require_once $basePath . '/config/app.php';
require_once $basePath . '/config/database.php';
require_once $basePath . '/api/helpers.php';
require_once $basePath . '/api/Router.php';
require_once $basePath . '/api/middleware/RateLimitMiddleware.php';

foreach (glob($basePath . '/api/repositories/*.php') as $file) {
    require_once $file;
}

foreach (glob($basePath . '/api/services/*.php') as $file) {
    require_once $file;
}

foreach (glob($basePath . '/api/controllers/*.php') as $file) {
    require_once $file;
}

$appConfig = appConfig();
if (empty($appConfig['debug'])) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

applySecurityHeaders($appConfig);
