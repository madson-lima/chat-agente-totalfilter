<?php

declare(strict_types=1);

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function cleanText(?string $value, int $limit = 5000): string
{
    $value = strip_tags((string) $value);
    $value = preg_replace('/\s+/u', ' ', $value ?? '') ?? '';
    $value = trim($value);
    return mb_substr($value, 0, $limit);
}

function cleanEmail(?string $value): string
{
    return filter_var((string) $value, FILTER_VALIDATE_EMAIL) ?: '';
}

function cleanPhone(?string $value): string
{
    $digits = preg_replace('/[^\d+]/', '', (string) $value) ?? '';
    return mb_substr($digits, 0, 20);
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function clientIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return explode(',', (string) $_SERVER[$key])[0];
        }
    }

    return '127.0.0.1';
}
