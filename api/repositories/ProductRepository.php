<?php

declare(strict_types=1);

final class ProductRepository extends BaseRepository
{
    public function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM product_index';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY is_launch DESC, updated_at DESC';
        return $this->pdo->query($sql)->fetchAll() ?: [];
    }

    public function search(string $query, int $limit = 6): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *,
             ((CASE WHEN product_name LIKE :like THEN 5 ELSE 0 END) +
              (CASE WHEN product_code LIKE :like THEN 5 ELSE 0 END) +
              (CASE WHEN application_summary LIKE :like THEN 3 ELSE 0 END) +
              (CASE WHEN keywords LIKE :like THEN 4 ELSE 0 END)) AS relevance
             FROM product_index
             WHERE is_active = 1
               AND (product_name LIKE :like OR product_code LIKE :like OR application_summary LIKE :like OR keywords LIKE :like)
             ORDER BY relevance DESC, is_launch DESC, updated_at DESC
             LIMIT :limit'
        );
        $like = '%' . $query . '%';
        $statement->bindValue(':like', $like);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll() ?: [];
    }

    public function latestLaunches(int $limit = 4): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM product_index WHERE is_active = 1 AND is_launch = 1 ORDER BY updated_at DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll() ?: [];
    }

    public function exactMatch(string $term): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM product_index
             WHERE is_active = 1
               AND (UPPER(product_code) = UPPER(:term) OR UPPER(product_name) = UPPER(:term))
             ORDER BY is_launch DESC, updated_at DESC
             LIMIT 5'
        );
        $statement->execute(['term' => trim($term)]);
        return $statement->fetchAll() ?: [];
    }

    public function save(array $data): void
    {
        if (!empty($data['id'])) {
            $statement = $this->pdo->prepare(
                'UPDATE product_index SET product_code = :product_code, product_name = :product_name, category = :category,
                 application_summary = :application_summary, technical_notes = :technical_notes, status_label = :status_label,
                 product_url = :product_url, keywords = :keywords, is_launch = :is_launch, is_active = :is_active,
                 updated_at = NOW() WHERE id = :id'
            );
            $statement->execute($data);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO product_index
             (product_code, product_name, category, application_summary, technical_notes, status_label, product_url,
              keywords, is_launch, is_active, created_at, updated_at)
             VALUES
             (:product_code, :product_name, :category, :application_summary, :technical_notes, :status_label, :product_url,
              :keywords, :is_launch, :is_active, NOW(), NOW())'
        );
        $statement->execute($data);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM product_index WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
