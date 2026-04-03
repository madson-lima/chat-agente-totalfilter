<?php

declare(strict_types=1);

final class RateLimitMiddleware
{
    public function __construct(private PDO $pdo, private array $config)
    {
    }

    public function handle(callable $next): void
    {
        $ip = clientIp();
        $window = max(10, (int) ($this->config['rate_limit_window'] ?? 60));
        $max = max(5, (int) ($this->config['rate_limit_max'] ?? 25));
        $cutoff = date('Y-m-d H:i:s', time() - $window);

        $statement = $this->pdo->prepare(
            'INSERT INTO rate_limits (ip_address, request_count, window_start, created_at, updated_at)
             VALUES (:ip, 1, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               request_count = IF(window_start < :cutoff, 1, request_count + 1),
               window_start = IF(window_start < :cutoff, NOW(), window_start),
               updated_at = NOW()'
        );
        $statement->bindValue(':ip', $ip);
        $statement->bindValue(':cutoff', $cutoff);
        $statement->execute();

        $query = $this->pdo->prepare('SELECT request_count FROM rate_limits WHERE ip_address = :ip LIMIT 1');
        $query->execute(['ip' => $ip]);
        $row = $query->fetch() ?: ['request_count' => 0];

        if ((int) $row['request_count'] > $max) {
            jsonResponse([
                'ok' => false,
                'message' => 'Muitas solicitações em pouco tempo. Tente novamente em instantes.',
            ], 429);
        }

        $next();
    }
}
