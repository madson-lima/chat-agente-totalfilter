<?php

declare(strict_types=1);

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
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

function isHttpsRequest(array $config = []): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $trustedProxy = (string) ($config['trusted_proxy'] ?? '');
    if ($trustedProxy !== '') {
        $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remoteAddr === $trustedProxy && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
        }
    }

    return ((string) ($_SERVER['SERVER_PORT'] ?? '')) === '443';
}

function applySecurityHeaders(array $config = []): void
{
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (isHttpsRequest($config)) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function applyCorsHeaders(array $config = []): void
{
    $allowedOrigins = $config['cors']['allowed_origins'] ?? [];
    if (!is_array($allowedOrigins) || $allowedOrigins === []) {
        return;
    }

    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

    if (!empty($config['cors']['allow_credentials'])) {
        header('Access-Control-Allow-Credentials: true');
    }
}

function enforceHttps(array $config = []): void
{
    if (empty($config['force_https']) || isHttpsRequest($config)) {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function adminVerifyPassword(array $config, string $password): bool
{
    $hash = (string) ($config['password_hash'] ?? '');
    if ($hash !== '') {
        return password_verify($password, $hash);
    }

    return hash_equals((string) ($config['password'] ?? ''), $password);
}

function csrfToken(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfQuery(): string
{
    return '_csrf_token=' . rawurlencode(csrfToken());
}

function verifyCsrf(?string $token): bool
{
    return !empty($_SESSION['_csrf_token']) && is_string($token) && hash_equals((string) $_SESSION['_csrf_token'], $token);
}
