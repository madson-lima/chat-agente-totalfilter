<?php

declare(strict_types=1);

final class HandoffRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO human_handoff_requests
             (session_id, name, phone, email, reason, preferred_channel, status, created_at, updated_at)
             VALUES
             (:session_id, :name, :phone, :email, :reason, :preferred_channel, :status, NOW(), NOW())'
        );
        $statement->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function all(int $limit = 100): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM human_handoff_requests ORDER BY created_at DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll() ?: [];
    }

    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE human_handoff_requests SET status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }
}
