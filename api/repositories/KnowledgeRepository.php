<?php

declare(strict_types=1);

use MongoDB\Database;
use MongoDB\Operation\FindOneAndUpdate;

final class KnowledgeRepository extends BaseRepository
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

    public function pages(bool $activeOnly = true): array
    {
        if ($this->pdo instanceof PDO) {
            $sql = 'SELECT * FROM knowledge_pages';
            if ($activeOnly) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY priority DESC, id DESC';
            return $this->pdo->query($sql)->fetchAll() ?: [];
        }

        $filter = $activeOnly ? ['is_active' => 1] : [];
        return $this->normalizeMany($this->mongo->knowledge_pages->find($filter, ['sort' => ['priority' => -1, 'id' => -1]]));
    }

    public function search(string $query, int $limit = 5): array
    {
        if ($this->pdo instanceof PDO) {
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

        $regex = new MongoDB\BSON\Regex(preg_quote($query, '/'), 'i');
        $items = $this->normalizeMany($this->mongo->knowledge_pages->find([
            'is_active' => 1,
            '$or' => [
                ['title' => $regex],
                ['excerpt' => $regex],
                ['content' => $regex],
                ['keywords' => $regex],
            ],
        ]));
        foreach ($items as &$item) {
            $relevance = 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['title'] ?? '')) ? 4 : 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['excerpt'] ?? '')) ? 3 : 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['content'] ?? '')) ? 2 : 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['keywords'] ?? '')) ? 5 : 0;
            $item['relevance'] = $relevance;
        }
        usort($items, fn($a, $b) => [$b['relevance'], $b['priority'] ?? 0] <=> [$a['relevance'], $a['priority'] ?? 0]);
        return array_slice($items, 0, $limit);
    }

    public function save(array $data): void
    {
        if ($this->pdo instanceof PDO) {
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
            return;
        }

        $payload = [
            'slug' => $data['slug'],
            'title' => $data['title'],
            'excerpt' => $data['excerpt'],
            'content' => $data['content'],
            'source_type' => $data['source_type'],
            'source_url' => $data['source_url'],
            'keywords' => $data['keywords'],
            'priority' => (int) $data['priority'],
            'is_active' => (int) $data['is_active'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['id'])) {
            $this->mongo->knowledge_pages->updateOne(['id' => (int) $data['id']], ['$set' => $payload]);
            return;
        }

        $payload['id'] = $this->nextId();
        $payload['created_at'] = $payload['updated_at'];
        $this->mongo->knowledge_pages->insertOne($payload);
    }

    public function delete(int $id): void
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('DELETE FROM knowledge_pages WHERE id = :id');
            $statement->execute(['id' => $id]);
            return;
        }
        $this->mongo->knowledge_pages->deleteOne(['id' => $id]);
    }

    private function nextId(): int
    {
        $result = $this->mongo->counters->findOneAndUpdate(
            ['_id' => 'knowledge_pages'],
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
