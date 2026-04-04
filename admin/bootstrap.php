<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';

$appConfig = appConfig();
$sessionConfig = $appConfig['session'];
$sessionPath = dirname(__DIR__) . '/storage/sessions';
enforceHttps($appConfig);

if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}

session_name((string) $sessionConfig['name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (bool) $sessionConfig['secure'],
    'httponly' => (bool) $sessionConfig['httponly'],
    'samesite' => (string) $sessionConfig['samesite'],
]);

session_save_path($sessionPath);
session_start();

function adminConfig(): array
{
    return appConfig()['admin'];
}

function adminAuthed(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function requireAdmin(): void
{
    if (!adminAuthed()) {
        header('Location: /admin/login.php');
        exit;
    }
}
