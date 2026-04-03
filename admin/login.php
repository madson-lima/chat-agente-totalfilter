<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = cleanText($_POST['user'] ?? '', 80);
    $password = (string) ($_POST['password'] ?? '');
    $config = adminConfig();

    if ($user === $config['user'] && hash_equals((string) $config['password'], $password)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: /admin/');
        exit;
    }

    $error = 'Usuário ou senha inválidos.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel Totalfilter</title>
    <style>
        :root{--tf-black:#0a0a0a;--tf-gold:#ffd100;--tf-gold-dark:#d6b602;--tf-border:rgba(255,209,0,.22)}
        body{font-family:Arial,sans-serif;background:radial-gradient(circle at top right,rgba(255,209,0,.18),transparent 30%),linear-gradient(180deg,#f6f1dc,#fffdf4);display:grid;place-items:center;min-height:100vh;margin:0}
        .card{width:min(420px,92vw);background:#fff;border-radius:24px;padding:28px;box-shadow:0 18px 50px rgba(0,0,0,.14);border:1px solid var(--tf-border)}
        h1{margin-top:0;color:#151515}
        input{width:100%;margin:8px 0 14px;padding:12px;border:1px solid rgba(0,0,0,.12);border-radius:12px;background:#fffdf8}
        input:focus{outline:2px solid rgba(255,209,0,.35);border-color:var(--tf-gold-dark)}
        button{width:100%;padding:12px;border:0;border-radius:12px;background:linear-gradient(135deg,var(--tf-gold),var(--tf-gold-dark));color:#000;font-weight:700}
        .error{color:#b42318;margin-bottom:12px}
    </style>
</head>
<body>
    <form class="card" method="post">
        <h1>Painel Totalfilter</h1>
        <p>Faça login para gerenciar o assistente.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <label>Usuário</label>
        <input name="user" required>
        <label>Senha</label>
        <input type="password" name="password" required>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
