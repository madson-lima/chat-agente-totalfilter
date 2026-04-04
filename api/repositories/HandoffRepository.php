<?php

declare(strict_types=1);

use MongoDB\Database;
use MongoDB\Operation\FindOneAndUpdate;

final class HandoffRepository extends BaseRepository
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

    public function create(array $data): int
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare(
                'INSERT INTO human_handoff_requests
                 (session_id, name, phone, email, reason, preferred_channel, status, created_at, updated_at)
                 VALUES
                 (:session_id, :name, :phone, :email, :reason, :preferred_channel, :status, NOW(), NOW())'
            );
            $statement->execute($data);
            return (int) $this->pdo->lastInsertId();
        }

        $id = $this->nextId();
        $now = date('Y-m-d H:i:s');
        $this->mongo->human_handoff_requests->insertOne([
            'id' => $id,
            'session_id' => (int) ($data['session_id'] ?? 0),
            'name' => $data['name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'reason' => $data['reason'] ?? '',
            'preferred_channel' => $data['preferred_channel'] ?? 'telefone',
            'status' => $data['status'] ?? 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $id;
    }

    public function all(int $limit = 100): array
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('SELECT * FROM human_handoff_requests ORDER BY created_at DESC LIMIT :limit');
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            return $statement->fetchAll() ?: [];
        }

        return $this->normalizeMany($this->mongo->human_handoff_requests->find([], ['sort' => ['created_at' => -1], 'limit' => $limit]));
    }

    public function updateStatus(int $id, string $status): void
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('UPDATE human_handoff_requests SET status = :status, updated_at = NOW() WHERE id = :id');
            $statement->execute([
                'id' => $id,
                'status' => $status,
            ]);
            return;
        }

        $this->mongo->human_handoff_requests->updateOne(['id' => $id], ['$set' => ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]]);
    }

    private function nextId(): int
    {
        $result = $this->mongo->counters->findOneAndUpdate(
            ['_id' => 'human_handoff_requests'],
            ['$inc' => ['seq' => 1]],
            ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );
        $doc = json_decode(json_encode($result), true);
        return (int) ($doc['seq'] ?? 1);
    }

    private function normalizeMany(iterable $cursor): array
    {
        $items = [];
        foreach ($cursor as $document) {
            $item = json_decode(json_encode($document), true);
            if (is_array($item)) {
                unset($item['_id']);
                $items[] = $item;
            }
        }
        return $items;
    }
}
