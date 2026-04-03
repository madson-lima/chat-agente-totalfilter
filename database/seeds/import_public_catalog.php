<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

$config = appConfig();
$pdo = database();

$apiBase = 'https://back-end-play3-production.up.railway.app/api/products';
$siteBase = 'https://totalfilter.com.br/pages/detalhes.html?id=';
$limit = 50;
$page = 1;
$imported = 0;

function fetchJson(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => "User-Agent: TotalfilterCatalogImporter/1.0\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function normalizeTextValue(?string $value, int $limit = 255): string
{
    $value = trim((string) $value);
    return $value === '' || strtolower($value) === 'n/a' ? '' : mb_substr($value, 0, $limit);
}

$statement = $pdo->prepare(
    'INSERT INTO product_index
     (product_code, product_name, category, application_summary, technical_notes, status_label, product_url, keywords, is_launch, is_active, created_at, updated_at)
     VALUES
     (:product_code, :product_name, :category, :application_summary, :technical_notes, :status_label, :product_url, :keywords, :is_launch, 1, NOW(), NOW())
     ON DUPLICATE KEY UPDATE
       category = VALUES(category),
       application_summary = VALUES(application_summary),
       technical_notes = VALUES(technical_notes),
       status_label = VALUES(status_label),
       product_url = VALUES(product_url),
       keywords = VALUES(keywords),
       is_launch = VALUES(is_launch),
       updated_at = NOW()'
);

do {
    $payload = fetchJson($apiBase . '?page=' . $page . '&limit=' . $limit);
    $products = $payload['products'] ?? [];
    $totalPages = (int) ($payload['totalPages'] ?? $page);

    foreach ($products as $product) {
        $id = (string) ($product['_id'] ?? '');
        if ($id === '') {
            continue;
        }

        $detail = fetchJson($apiBase . '/' . rawurlencode($id));
        $name = normalizeTextValue($detail['name'] ?? $product['name'] ?? '', 180);
        if ($name === '') {
            continue;
        }

        $category = normalizeTextValue($detail['category'] ?? '', 120);
        $description = normalizeTextValue($detail['description'] ?? $product['description'] ?? '', 3000);
        $notes = [];
        foreach ([
            'Peso liquido' => normalizeTextValue($detail['peso'] ?? '', 120),
            'Externo' => normalizeTextValue($detail['externo'] ?? '', 120),
            'Interno' => normalizeTextValue($detail['interno'] ?? '', 120),
            'Altura' => normalizeTextValue($detail['altura'] ?? '', 120),
        ] as $label => $value) {
            if ($value !== '') {
                $notes[] = $label . ': ' . $value;
            }
        }

        $keywords = implode(', ', array_filter([
            $name,
            $description,
            $category,
            !empty($detail['peso']) ? 'peso' : '',
            !empty($detail['externo']) ? 'externo' : '',
            !empty($detail['interno']) ? 'interno' : '',
            !empty($detail['altura']) ? 'altura' : '',
        ]));

        $statement->execute([
            'product_code' => $name,
            'product_name' => $name,
            'category' => $category !== '' ? $category : 'Catalogo Totalfilter',
            'application_summary' => $description !== '' ? $description : 'Produto do catalogo publico Totalfilter.',
            'technical_notes' => implode(' | ', $notes),
            'status_label' => !empty($product['isLaunch']) ? 'Lancamento' : 'Catalogo publico',
            'product_url' => $siteBase . $id,
            'keywords' => mb_substr($keywords, 0, 255),
            'is_launch' => !empty($product['isLaunch']) ? 1 : 0,
        ]);

        $imported++;
    }

    $page++;
} while ($page <= $totalPages);

echo "Catalogo importado com sucesso. Produtos sincronizados: {$imported}\n";
