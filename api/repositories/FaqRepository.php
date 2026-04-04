<?php

declare(strict_types=1);

use MongoDB\Database;
use MongoDB\Operation\FindOneAndUpdate;

final class FaqRepository extends BaseRepository
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

    public function all(bool $activeOnly = true): array
    {
        if ($this->pdo instanceof PDO) {
            $sql = 'SELECT * FROM faq_items';
            if ($activeOnly) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY sort_order ASC, id DESC';
            return $this->pdo->query($sql)->fetchAll() ?: [];
        }

        $filter = $activeOnly ? ['is_active' => 1] : [];
        $cursor = $this->mongo->faq_items->find($filter, ['sort' => ['sort_order' => 1, 'id' => -1]]);
        return $this->normalizeMany($cursor);
    }

    public function search(string $query, int $limit = 5): array
    {
        if ($this->pdo instanceof PDO) {
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

        $regex = new MongoDB\BSON\Regex(preg_quote($query, '/'), 'i');
        $cursor = $this->mongo->faq_items->find([
            'is_active' => 1,
            '$or' => [
                ['question' => $regex],
                ['answer' => $regex],
                ['keywords' => $regex],
            ],
        ]);

        $items = $this->normalizeMany($cursor);
        foreach ($items as &$item) {
            $relevance = 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['question'] ?? '')) ? 5 : 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['answer'] ?? '')) ? 2 : 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['keywords'] ?? '')) ? 4 : 0;
            $item['relevance'] = $relevance;
        }
        usort($items, fn($a, $b) => [$b['relevance'], $a['sort_order'] ?? 0] <=> [$a['relevance'], $b['sort_order'] ?? 0]);
        return array_slice($items, 0, $limit);
    }

    public function save(array $data): void
    {
        if ($this->pdo instanceof PDO) {
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
            return;
        }

        $payload = [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'keywords' => $data['keywords'],
            'category' => $data['category'],
            'sort_order' => (int) $data['sort_order'],
            'is_active' => (int) $data['is_active'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['id'])) {
            $this->mongo->faq_items->updateOne(['id' => (int) $data['id']], ['$set' => $payload]);
            return;
        }

        $payload['id'] = $this->nextId();
        $payload['created_at'] = $payload['updated_at'];
        $this->mongo->faq_items->insertOne($payload);
    }

    public function delete(int $id): void
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('DELETE FROM faq_items WHERE id = :id');
            $statement->execute(['id' => $id]);
            return;
        }

        $this->mongo->faq_items->deleteOne(['id' => $id]);
    }

    private function nextId(): int
    {
        $result = $this->mongo->counters->findOneAndUpdate(
            ['_id' => 'faq_items'],
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
