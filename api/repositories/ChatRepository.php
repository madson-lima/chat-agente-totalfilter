<?php

declare(strict_types=1);

use MongoDB\Database;
use MongoDB\Operation\FindOneAndUpdate;

final class ChatRepository extends BaseRepository
{
    private ?PDO $pdo = null;
    private ?Database $mongo = null;

    public function __construct(mixed $db)
    {
        parent::__construct($db);
        if ($db instanceof PDO) {
            $this->pdo = $db;
        }
        if ($db instanceof Database) {
            $this->mongo = $db;
        }
    }

    public function createSession(string $token, string $visitorId, array $meta = []): array
    {
        if ($this->pdo instanceof PDO) {
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

        $now = date('Y-m-d H:i:s');
        $id = $this->nextMongoId('chat_sessions');
        $this->mongo->chat_sessions->insertOne([
            'id' => $id,
            'session_token' => $token,
            'visitor_id' => $visitorId,
            'status' => 'active',
            'lead_status' => 'none',
            'page_url' => $meta['page_url'] ?? '',
            'referrer_url' => $meta['referrer_url'] ?? '',
            'user_agent' => substr((string) ($meta['user_agent'] ?? ''), 0, 255),
            'ip_address' => substr((string) ($meta['ip_address'] ?? clientIp()), 0, 64),
            'locale' => substr((string) ($meta['locale'] ?? 'pt-BR'), 0, 12),
            'summary' => '',
            'last_topic' => '',
            'last_user_message' => '',
            'last_assistant_message' => '',
            'message_count' => 0,
            'metadata_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByToken($token) ?? [];
    }

    public function findByToken(string $token): ?array
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('SELECT * FROM chat_sessions WHERE session_token = :token LIMIT 1');
            $statement->execute(['token' => $token]);
            $session = $statement->fetch();
            return $session ?: null;
        }

        $document = $this->mongo->chat_sessions->findOne(['session_token' => $token]);
        return $this->normalizeMongoDocument($document);
    }

    public function updateSession(int $sessionId, array $data): void
    {
        $allowed = ['status', 'summary', 'lead_status', 'last_topic', 'last_user_message', 'last_assistant_message', 'message_count', 'page_url', 'metadata_json'];

        if ($this->pdo instanceof PDO) {
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
            return;
        }

        $set = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $set[$column] = $data[$column];
            }
        }

        if ($set === []) {
            return;
        }

        $set['updated_at'] = date('Y-m-d H:i:s');
        $this->mongo->chat_sessions->updateOne(['id' => $sessionId], ['$set' => $set]);
    }

    public function addMessage(int $sessionId, string $role, string $content, array $meta = []): int
    {
        if ($this->pdo instanceof PDO) {
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

        $id = $this->nextMongoId('chat_messages');
        $this->mongo->chat_messages->insertOne([
            'id' => $id,
            'session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function recentMessages(int $sessionId, int $limit = 10): array
    {
        if ($this->pdo instanceof PDO) {
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

        $cursor = $this->mongo->chat_messages->find(
            ['session_id' => $sessionId],
            ['sort' => ['id' => -1], 'limit' => $limit]
        );

        $items = [];
        foreach ($cursor as $document) {
            $items[] = $this->normalizeMongoDocument($document);
        }

        return array_reverse($items);
    }

    public function fullHistory(int $sessionId): array
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare(
                'SELECT id, role, content, created_at, meta_json
                 FROM chat_messages
                 WHERE session_id = :session_id
                 ORDER BY id ASC'
            );
            $statement->execute(['session_id' => $sessionId]);
            return $statement->fetchAll() ?: [];
        }

        $cursor = $this->mongo->chat_messages->find(
            ['session_id' => $sessionId],
            ['sort' => ['id' => 1]]
        );

        $items = [];
        foreach ($cursor as $document) {
            $items[] = $this->normalizeMongoDocument($document);
        }

        return $items;
    }

    public function incrementMessageCount(int $sessionId): int
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('UPDATE chat_sessions SET message_count = message_count + 1, updated_at = NOW() WHERE id = :id');
            $statement->execute(['id' => $sessionId]);
            $query = $this->pdo->prepare('SELECT message_count FROM chat_sessions WHERE id = :id');
            $query->execute(['id' => $sessionId]);
            return (int) (($query->fetch()['message_count'] ?? 0));
        }

        $result = $this->mongo->chat_sessions->findOneAndUpdate(
            ['id' => $sessionId],
            ['$inc' => ['message_count' => 1], '$set' => ['updated_at' => date('Y-m-d H:i:s')]],
            ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );

        $session = $this->normalizeMongoDocument($result);
        return (int) ($session['message_count'] ?? 0);
    }

    public function allSessions(int $limit = 100): array
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('SELECT * FROM chat_sessions ORDER BY updated_at DESC LIMIT :limit');
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            return $statement->fetchAll() ?: [];
        }

        $cursor = $this->mongo->chat_sessions->find([], ['sort' => ['updated_at' => -1], 'limit' => $limit]);
        $items = [];
        foreach ($cursor as $document) {
            $items[] = $this->normalizeMongoDocument($document);
        }
        return $items;
    }

    private function nextMongoId(string $counter): int
    {
        $result = $this->mongo->counters->findOneAndUpdate(
            ['_id' => $counter],
            ['$inc' => ['seq' => 1]],
            ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );

        $document = $this->normalizeMongoDocument($result);
        return (int) ($document['seq'] ?? 1);
    }

    private function normalizeMongoDocument(object|array|null $document): ?array
    {
        if ($document === null) {
            return null;
        }

        $array = json_decode(json_encode($document, JSON_UNESCAPED_UNICODE), true);
        if (!is_array($array)) {
            return null;
        }
        unset($array['_id']);
        return $array;
    }
}
