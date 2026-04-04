<?php

declare(strict_types=1);

use MongoDB\Database;
use MongoDB\Operation\FindOneAndUpdate;

final class ProductRepository extends BaseRepository
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
            $sql = 'SELECT * FROM product_index';
            if ($activeOnly) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY is_launch DESC, updated_at DESC';
            return $this->pdo->query($sql)->fetchAll() ?: [];
        }

        $filter = $activeOnly ? ['is_active' => 1] : [];
        return $this->normalizeMany($this->mongo->product_index->find($filter, ['sort' => ['is_launch' => -1, 'updated_at' => -1]]));
    }

    public function search(string $query, int $limit = 6): array
    {
        if ($this->pdo instanceof PDO) {
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

        $regex = new MongoDB\BSON\Regex(preg_quote($query, '/'), 'i');
        $items = $this->normalizeMany($this->mongo->product_index->find([
            'is_active' => 1,
            '$or' => [
                ['product_name' => $regex],
                ['product_code' => $regex],
                ['application_summary' => $regex],
                ['keywords' => $regex],
            ],
        ]));
        foreach ($items as &$item) {
            $relevance = 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['product_name'] ?? '')) ? 5 : 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['product_code'] ?? '')) ? 5 : 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['application_summary'] ?? '')) ? 3 : 0;
            $relevance += preg_match('/' . preg_quote($query, '/') . '/i', (string) ($item['keywords'] ?? '')) ? 4 : 0;
            $item['relevance'] = $relevance;
        }
        usort($items, fn($a, $b) => [$b['relevance'], $b['is_launch'] ?? 0, $b['updated_at'] ?? ''] <=> [$a['relevance'], $a['is_launch'] ?? 0, $a['updated_at'] ?? '']);
        return array_slice($items, 0, $limit);
    }

    public function latestLaunches(int $limit = 4): array
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('SELECT * FROM product_index WHERE is_active = 1 AND is_launch = 1 ORDER BY updated_at DESC LIMIT :limit');
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            return $statement->fetchAll() ?: [];
        }

        return $this->normalizeMany($this->mongo->product_index->find(['is_active' => 1, 'is_launch' => 1], ['sort' => ['updated_at' => -1], 'limit' => $limit]));
    }

    public function exactMatch(string $term): array
    {
        if ($this->pdo instanceof PDO) {
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

        $term = strtoupper(trim($term));
        $items = $this->normalizeMany($this->mongo->product_index->find(['is_active' => 1]));
        $items = array_values(array_filter($items, static fn(array $item): bool => strtoupper((string) ($item['product_code'] ?? '')) === $term || strtoupper((string) ($item['product_name'] ?? '')) === $term));
        usort($items, fn($a, $b) => [$b['is_launch'] ?? 0, $b['updated_at'] ?? ''] <=> [$a['is_launch'] ?? 0, $a['updated_at'] ?? '']);
        return array_slice($items, 0, 5);
    }

    public function save(array $data): void
    {
        if ($this->pdo instanceof PDO) {
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
            return;
        }

        $payload = [
            'product_code' => $data['product_code'],
            'product_name' => $data['product_name'],
            'category' => $data['category'],
            'application_summary' => $data['application_summary'],
            'technical_notes' => $data['technical_notes'],
            'status_label' => $data['status_label'],
            'product_url' => $data['product_url'],
            'keywords' => $data['keywords'],
            'is_launch' => (int) $data['is_launch'],
            'is_active' => (int) $data['is_active'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['id'])) {
            $this->mongo->product_index->updateOne(['id' => (int) $data['id']], ['$set' => $payload]);
            return;
        }

        $payload['id'] = $this->nextId();
        $payload['created_at'] = $payload['updated_at'];
        $this->mongo->product_index->insertOne($payload);
    }

    public function delete(int $id): void
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare('DELETE FROM product_index WHERE id = :id');
            $statement->execute(['id' => $id]);
            return;
        }
        $this->mongo->product_index->deleteOne(['id' => $id]);
    }

    private function nextId(): int
    {
        $result = $this->mongo->counters->findOneAndUpdate(
            ['_id' => 'product_index'],
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
