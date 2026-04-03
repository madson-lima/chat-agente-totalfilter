<?php

declare(strict_types=1);

function loadEnv(string $basePath): array
{
    $envPath = $basePath . DIRECTORY_SEPARATOR . '.env';
    $samplePath = $basePath . DIRECTORY_SEPARATOR . '.env.example';
    $source = file_exists($envPath) ? $envPath : $samplePath;

    if (!file_exists($source)) {
        return [];
    }

    $values = [];
    $lines = file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        $values[$key] = $value;

        if (getenv($key) === false) {
            putenv(sprintf('%s=%s', $key, $value));
        }
    }

    return $values;
}

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return match (strtolower($value)) {
        'true' => true,
        'false' => false,
        'null' => null,
        default => $value,
    };
}
