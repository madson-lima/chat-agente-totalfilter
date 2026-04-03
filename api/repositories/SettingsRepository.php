<?php

declare(strict_types=1);

final class SettingsRepository extends BaseRepository
{
    public function all(): array
    {
        $items = $this->pdo->query('SELECT setting_key, setting_value FROM assistant_settings ORDER BY setting_key ASC')->fetchAll() ?: [];
        $settings = [];
        foreach ($items as $item) {
            $settings[$item['setting_key']] = $item['setting_value'];
        }
        return $settings;
    }

    public function set(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO assistant_settings (setting_key, setting_value, created_at, updated_at)
             VALUES (:setting_key, :setting_value, NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
    }
}
