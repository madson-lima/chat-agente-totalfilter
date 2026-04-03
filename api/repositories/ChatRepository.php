<?php

declare(strict_types=1);

final class ChatRepository extends BaseRepository
{
    public function createSession(string $token, string $visitorId, array $meta = []): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO chat_sessions
            (session_token, visitor_id, status, page_url, referrer_url, user_agent, ip_address, locale, metadata_json, created_at, updated_at)
            VALUES
            (:session_token, :visitor_id, "active", :page_url, :referrer_url, :user_agent, :ip_address, :locale, :metadata_json, NOW(), NOW())'
        );
        $statement->execute([
            'session_token' => $token,
            'visitor_id' => $visitorId,
            'page_url' => $meta['page_url'] ?? '',
            'referrer_url' => $meta['referrer_url'] ?? '',
            'user_agent' => substr((string) ($meta['user_agent'] ?? ''), 0, 255),
            'ip_address' => substr((string) ($meta['ip_address'] ?? clientIp()), 0, 64),
            'locale' => substr((string) ($meta['locale'] ?? 'pt-BR'), 0, 12),
            'metadata_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);

        return $this->findByToken($token) ?? [];
    }

    public function findByToken(string $token): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM chat_sessions WHERE session_token = :token LIMIT 1');
        $statement->execute(['token' => $token]);
        $session = $statement->fetch();
        return $session ?: null;
    }

    public function updateSession(int $sessionId, array $data): void
    {
        $allowed = ['status', 'summary', 'lead_status', 'last_topic', 'last_user_message', 'last_assistant_message', 'message_count', 'page_url', 'metadata_json'];
        $fields = [];
        $params = ['id' => $sessionId];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $fields[] = "{$column} = :{$column}";
                $params[$column] = $data[$column];
            }
        }

        if (!$fields) {
            return;
        }

        $sql = 'UPDATE chat_sessions SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    public function addMessage(int $sessionId, string $role, string $content, array $meta = []): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO chat_messages
            (session_id, role, content, meta_json, created_at)
            VALUES (:session_id, :role, :content, :meta_json, NOW())'
        );
        $statement->execute([
            'session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function recentMessages(int $sessionId, int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, role, content, created_at, meta_json
             FROM chat_messages
             WHERE session_id = :session_id
             ORDER BY id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return array_reverse($statement->fetchAll() ?: []);
    }

    public function fullHistory(int $sessionId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, role, content, created_at, meta_json
             FROM chat_messages
             WHERE session_id = :session_id
             ORDER BY id ASC'
        );
        $statement->execute(['session_id' => $sessionId]);
        return $statement->fetchAll() ?: [];
    }

    public function incrementMessageCount(int $sessionId): int
    {
        $statement = $this->pdo->prepare('UPDATE chat_sessions SET message_count = message_count + 1, updated_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $sessionId]);
        $query = $this->pdo->prepare('SELECT message_count FROM chat_sessions WHERE id = :id');
        $query->execute(['id' => $sessionId]);
        return (int) (($query->fetch()['message_count'] ?? 0));
    }

    public function allSessions(int $limit = 100): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM chat_sessions ORDER BY updated_at DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll() ?: [];
    }
}
