<?php

declare(strict_types=1);

final class RateLimitMiddleware
{
    public function __construct(private mixed $db, private array $config)
    {
    }

    public function handle(callable $next): void
    {
        $ip = clientIp();
        $window = max(10, (int) ($this->config['rate_limit_window'] ?? 60));
        $max = max(5, (int) ($this->config['rate_limit_max'] ?? 25));
        $cutoff = date('Y-m-d H:i:s', time() - $window);

        if ($this->db instanceof PDO) {
            $statement = $this->db->prepare(
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

            $query = $this->db->prepare('SELECT request_count FROM rate_limits WHERE ip_address = :ip LIMIT 1');
            $query->execute(['ip' => $ip]);
            $row = $query->fetch() ?: ['request_count' => 0];
        } else {
            $collection = $this->db->rate_limits;
            $existing = $collection->findOne(['ip_address' => $ip]);
            $doc = is_object($existing) ? json_decode(json_encode($existing), true) : null;
            $now = date('Y-m-d H:i:s');

            if (!$doc) {
                $collection->insertOne([
                    'ip_address' => $ip,
                    'request_count' => 1,
                    'window_start' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $row = ['request_count' => 1];
            } else {
                $count = strtotime((string) $doc['window_start']) < strtotime($cutoff)
                    ? 1
                    : ((int) ($doc['request_count'] ?? 0) + 1);
                $windowStart = strtotime((string) $doc['window_start']) < strtotime($cutoff)
                    ? $now
                    : (string) $doc['window_start'];
                $collection->updateOne(['ip_address' => $ip], ['$set' => [
                    'request_count' => $count,
                    'window_start' => $windowStart,
                    'updated_at' => $now,
                ]]);
                $row = ['request_count' => $count];
            }
        }

        if ((int) $row['request_count'] > $max) {
            jsonResponse([
                'ok' => false,
                'message' => 'Muitas solicitações em pouco tempo. Tente novamente em instantes.',
            ], 429);
        }

        $next();
    }
}
