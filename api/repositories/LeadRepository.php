<?php

declare(strict_types=1);

final class LeadRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO leads
             (session_id, name, phone, email, company, city_state, product_interest, message, source, status, created_at, updated_at)
             VALUES
             (:session_id, :name, :phone, :email, :company, :city_state, :product_interest, :message, :source, :status, NOW(), NOW())'
        );
        $statement->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function all(int $limit = 200): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM leads ORDER BY created_at DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll() ?: [];
    }

    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE leads SET status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }
}
