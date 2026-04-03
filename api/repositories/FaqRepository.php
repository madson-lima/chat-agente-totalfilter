<?php

declare(strict_types=1);

final class FaqRepository extends BaseRepository
{
    public function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM faq_items';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id DESC';
        return $this->pdo->query($sql)->fetchAll() ?: [];
    }

    public function search(string $query, int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *,
             ((CASE WHEN question LIKE :like THEN 5 ELSE 0 END) +
              (CASE WHEN answer LIKE :like THEN 2 ELSE 0 END) +
              (CASE WHEN keywords LIKE :like THEN 4 ELSE 0 END)) AS relevance
             FROM faq_items
             WHERE is_active = 1
               AND (question LIKE :like OR answer LIKE :like OR keywords LIKE :like)
             ORDER BY relevance DESC, sort_order ASC
             LIMIT :limit'
        );
        $like = '%' . $query . '%';
        $statement->bindValue(':like', $like);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll() ?: [];
    }

    public function save(array $data): void
    {
        if (!empty($data['id'])) {
            $statement = $this->pdo->prepare(
                'UPDATE faq_items SET question = :question, answer = :answer, keywords = :keywords, category = :category,
                 sort_order = :sort_order, is_active = :is_active, updated_at = NOW() WHERE id = :id'
            );
            $statement->execute($data);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO faq_items (question, answer, keywords, category, sort_order, is_active, created_at, updated_at)
             VALUES (:question, :answer, :keywords, :category, :sort_order, :is_active, NOW(), NOW())'
        );
        $statement->execute($data);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM faq_items WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
