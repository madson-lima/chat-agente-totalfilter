<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$token = $_POST['_csrf_token'] ?? $_GET['_csrf_token'] ?? null;
if (!verifyCsrf(is_string($token) ? $token : null)) {
    http_response_code(419);
    exit('CSRF token invalido.');
}

$pdo = database();
$faqRepository = new FaqRepository($pdo);
$knowledgeRepository = new KnowledgeRepository($pdo);
$productRepository = new ProductRepository($pdo);
$settingsRepository = new SettingsRepository($pdo);
$leadRepository = new LeadRepository($pdo);
$handoffRepository = new HandoffRepository($pdo);

$entity = cleanText($_POST['entity'] ?? $_GET['entity'] ?? '', 40);
$action = cleanText($_POST['action'] ?? $_GET['action'] ?? '', 20);

if ($entity === 'settings' && $action === 'save') {
    foreach (['assistant_name', 'assistant_title', 'assistant_subtitle', 'input_placeholder', 'primary_color', 'accent_color', 'mascot_url'] as $key) {
        $settingsRepository->set($key, cleanText($_POST[$key] ?? '', 4000));
    }

    foreach (['welcome_message', 'quick_replies', 'persona_description'] as $key) {
        $value = trim((string) ($_POST[$key] ?? ''));
        $settingsRepository->set($key, mb_substr($value, 0, 4000));
    }
}

if ($entity === 'faq') {
    if ($action === 'save') {
        $faqRepository->save([
            'id' => !empty($_POST['id']) ? (int) $_POST['id'] : null,
            'question' => cleanText($_POST['question'] ?? '', 255),
            'answer' => cleanText($_POST['answer'] ?? '', 3000),
            'keywords' => cleanText($_POST['keywords'] ?? '', 255),
            'category' => cleanText($_POST['category'] ?? '', 100),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);
    }
    if ($action === 'delete') {
        $faqRepository->delete((int) ($_GET['id'] ?? 0));
    }
}

if ($entity === 'knowledge') {
    if ($action === 'save') {
        $title = cleanText($_POST['title'] ?? '', 180);
        $knowledgeRepository->save([
            'id' => !empty($_POST['id']) ? (int) $_POST['id'] : null,
            'slug' => cleanText($_POST['slug'] ?? slugify($title), 160),
            'title' => $title,
            'excerpt' => cleanText($_POST['excerpt'] ?? '', 1000),
            'content' => cleanText($_POST['content'] ?? '', 10000),
            'source_type' => cleanText($_POST['source_type'] ?? 'manual', 50),
            'source_url' => cleanText($_POST['source_url'] ?? '', 255),
            'keywords' => cleanText($_POST['keywords'] ?? '', 255),
            'priority' => (int) ($_POST['priority'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);
    }
    if ($action === 'delete') {
        $knowledgeRepository->delete((int) ($_GET['id'] ?? 0));
    }
}

if ($entity === 'product') {
    if ($action === 'save') {
        $productRepository->save([
            'id' => !empty($_POST['id']) ? (int) $_POST['id'] : null,
            'product_code' => cleanText($_POST['product_code'] ?? '', 100),
            'product_name' => cleanText($_POST['product_name'] ?? '', 180),
            'category' => cleanText($_POST['category'] ?? '', 120),
            'application_summary' => cleanText($_POST['application_summary'] ?? '', 3000),
            'technical_notes' => cleanText($_POST['technical_notes'] ?? '', 3000),
            'status_label' => cleanText($_POST['status_label'] ?? '', 60),
            'product_url' => cleanText($_POST['product_url'] ?? '', 255),
            'keywords' => cleanText($_POST['keywords'] ?? '', 255),
            'is_launch' => isset($_POST['is_launch']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);
    }
    if ($action === 'delete') {
        $productRepository->delete((int) ($_GET['id'] ?? 0));
    }
}

if ($entity === 'lead' && $action === 'status') {
    $leadRepository->updateStatus((int) ($_GET['id'] ?? 0), cleanText($_GET['status'] ?? 'new', 30));
}

if ($entity === 'handoff' && $action === 'status') {
    $handoffRepository->updateStatus((int) ($_GET['id'] ?? 0), cleanText($_GET['status'] ?? 'pending', 30));
}

header('Location: /admin/');
exit;
