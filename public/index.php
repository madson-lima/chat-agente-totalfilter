<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/bootstrap.php';
$config = appConfig();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body{font-family:Arial,sans-serif;background:#eef3f8;color:#17324d;margin:0;padding:48px}
        .card{max-width:820px;margin:0 auto;background:#fff;border-radius:20px;padding:32px;box-shadow:0 20px 60px rgba(10,39,72,.12)}
        code{background:#edf3fa;padding:2px 6px;border-radius:6px}
    </style>
</head>
<body>
    <main class="card">
        <h1><?= htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p>Projeto do assistente digital Totalfilter instalado com sucesso.</p>
        <p>API: <code>/api/health</code></p>
        <p>Painel: <code>/admin/</code></p>
        <p>Embed: <code>&lt;script src="/chat-widget/embed.js"&gt;&lt;/script&gt;</code></p>
    </main>
</body>
<script src="/chat-widget/embed.js"></script>
</html>
