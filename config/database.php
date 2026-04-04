<?php

declare(strict_types=1);

use MongoDB\Client;
use MongoDB\Database;

function database(): mixed
{
    $driver = strtolower((string) env('DATABASE_DRIVER', 'mysql'));
    $mongoUri = (string) env('MONGO_URI', '');

    if ($mongoUri !== '' || $driver === 'mongodb') {
        return mongoDatabase();
    }

    return mysqlDatabase();
}

function mysqlDatabase(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'totalfilter_chat'),
        env('DB_CHARSET', 'utf8mb4')
    );

    try {
        $pdo = new PDO($dsn, (string) env('DB_USER', 'root'), (string) env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        databaseConnectionFailed();
    }

    return $pdo;
}

function mongoDatabase(): Database
{
    static $database = null;

    if ($database instanceof Database) {
        return $database;
    }

    try {
        $client = new Client((string) env('MONGO_URI', ''));
        $database = $client->selectDatabase((string) env('MONGO_DATABASE', 'totalfilter_chat'));
    } catch (Throwable $exception) {
        databaseConnectionFailed();
    }

    return $database;
}

function databaseConnectionFailed(): never
{
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => 'Falha ao conectar ao banco de dados.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
