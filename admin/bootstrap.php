<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
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
