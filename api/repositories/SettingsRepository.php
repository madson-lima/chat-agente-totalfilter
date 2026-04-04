<?php

declare(strict_types=1);

use MongoDB\Database;

final class SettingsRepository extends BaseRepository
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

    public function all(): array
    {
        if ($this->pdo instanceof PDO) {
            $items = $this->pdo->query('SELECT setting_key, setting_value FROM assistant_settings ORDER BY setting_key ASC')->fetchAll() ?: [];
            $settings = [];
            foreach ($items as $item) {
                $settings[$item['setting_key']] = $item['setting_value'];
            }
            return $settings;
        }

        $cursor = $this->mongo->assistant_settings->find([], ['sort' => ['setting_key' => 1]]);
        $settings = [];
        foreach ($cursor as $document) {
            $item = json_decode(json_encode($document), true);
            if (is_array($item)) {
                $settings[$item['setting_key']] = $item['setting_value'] ?? '';
            }
        }
        return $settings;
    }

    public function set(string $key, string $value): void
    {
        if ($this->pdo instanceof PDO) {
            $statement = $this->pdo->prepare(
                'INSERT INTO assistant_settings (setting_key, setting_value, created_at, updated_at)
                 VALUES (:setting_key, :setting_value, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
            );
            $statement->execute([
                'setting_key' => $key,
                'setting_value' => $value,
            ]);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->mongo->assistant_settings->updateOne(
            ['setting_key' => $key],
            ['$set' => ['setting_value' => $value, 'updated_at' => $now], '$setOnInsert' => ['created_at' => $now]],
            ['upsert' => true]
        );
    }
}
