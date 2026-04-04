<?php

declare(strict_types=1);

function appConfig(): array
{
    return [
        'name' => env('APP_NAME', 'Totalfilter Assist'),
        'env' => env('APP_ENV', 'production'),
        'url' => env('APP_URL', ''),
        'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
        'debug' => (bool) env('APP_DEBUG', false),
        'force_https' => (bool) env('APP_FORCE_HTTPS', false),
        'trusted_proxy' => env('APP_TRUSTED_PROXY', ''),
        'cors' => [
            'allowed_origins' => array_values(array_filter(array_map(
                static fn(string $origin): string => trim($origin),
                explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
            ))),
            'allow_credentials' => (bool) env('CORS_ALLOW_CREDENTIALS', false),
        ],
        'rate_limit_window' => (int) env('RATE_LIMIT_WINDOW', 60),
        'rate_limit_max' => (int) env('RATE_LIMIT_MAX', 25),
        'max_context_messages' => (int) env('MAX_CONTEXT_MESSAGES', 10),
        'summary_trigger_count' => (int) env('SUMMARY_TRIGGER_COUNT', 8),
        'summary_max_chars' => (int) env('SUMMARY_MAX_CHARS', 1400),
        'log_file' => dirname(__DIR__) . DIRECTORY_SEPARATOR . (string) env('LOG_FILE', 'storage/logs/app.log'),
        'lead_notification_email' => env('LEAD_NOTIFICATION_EMAIL', ''),
        'session' => [
            'name' => env('SESSION_NAME', 'totalfilter_admin'),
            'secure' => (bool) env('SESSION_COOKIE_SECURE', false),
            'httponly' => true,
            'samesite' => env('SESSION_COOKIE_SAMESITE', 'Lax'),
        ],
        'mail' => [
            'mailer' => env('MAIL_MAILER', 'mail'),
            'host' => env('SMTP_HOST', ''),
            'port' => (int) env('SMTP_PORT', 587),
            'username' => env('SMTP_USERNAME', ''),
            'password' => env('SMTP_PASSWORD', ''),
            'encryption' => env('SMTP_ENCRYPTION', 'tls'),
            'from_email' => env('SMTP_FROM_EMAIL', 'no-reply@totalfilter.local'),
            'from_name' => env('SMTP_FROM_NAME', 'Assistente Totalfilter'),
        ],
        'widget' => [
            'name' => env('WIDGET_NAME', 'Toto'),
            'title' => env('WIDGET_TITLE', 'Toto da Totalfilter'),
            'subtitle' => env('WIDGET_SUBTITLE', 'Atendimento digital Totalfilter'),
            'primary_color' => env('WIDGET_PRIMARY_COLOR', '#0057A8'),
            'accent_color' => env('WIDGET_ACCENT_COLOR', '#FF8C1A'),
            'mascot_url' => env('WIDGET_MASCOT_URL', '/chat-widget/assets/mascot.svg'),
        ],
        'llm' => [
            'provider' => env('LLM_PROVIDER', 'openai-compatible'),
            'api_url' => env('LLM_API_URL', ''),
            'api_key' => env('LLM_API_KEY', ''),
            'model' => env('LLM_MODEL', 'gpt-4.1-mini'),
            'temperature' => (float) env('LLM_TEMPERATURE', 0.4),
            'timeout' => (int) env('LLM_TIMEOUT', 25),
        ],
        'admin' => [
            'user' => env('ADMIN_USER', 'admin'),
            'password' => env('ADMIN_PASSWORD', 'troque-esta-senha'),
            'password_hash' => env('ADMIN_PASSWORD_HASH', ''),
        ],
    ];
}
