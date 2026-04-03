<?php

declare(strict_types=1);

final class KnowledgeRepository extends BaseRepository
{
    public function pages(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM knowledge_pages';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY priority DESC, id DESC';
        return $this->pdo->query($sql)->fetchAll() ?: [];
    }

    public function search(string $query, int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *,
             ((CASE WHEN title LIKE :like THEN 4 ELSE 0 END) +
              (CASE WHEN excerpt LIKE :like THEN 3 ELSE 0 END) +
              (CASE WHEN content LIKE :like THEN 2 ELSE 0 END) +
              (CASE WHEN keywords LIKE :like THEN 5 ELSE 0 END)) AS relevance
             FROM knowledge_pages
             WHERE is_active = 1
               AND (title LIKE :like OR excerpt LIKE :like OR content LIKE :like OR keywords LIKE :like)
             ORDER BY relevance DESC, priority DESC
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
                'UPDATE knowledge_pages SET slug = :slug, title = :title, excerpt = :excerpt, content = :content,
                 source_type = :source_type, source_url = :source_url, keywords = :keywords, priority = :priority,
                 is_active = :is_active, updated_at = NOW() WHERE id = :id'
            );
            $statement->execute($data);
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO knowledge_pages
             (slug, title, excerpt, content, source_type, source_url, keywords, priority, is_active, created_at, updated_at)
             VALUES (:slug, :title, :excerpt, :content, :source_type, :source_url, :keywords, :priority, :is_active, NOW(), NOW())'
        );
        $statement->execute($data);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM knowledge_pages WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
