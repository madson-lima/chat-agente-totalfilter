<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['_csrf_token'] ?? null)) {
        $error = 'Sua sessao expirou. Atualize a pagina e tente novamente.';
    }

    $user = cleanText($_POST['user'] ?? '', 80);
    $password = (string) ($_POST['password'] ?? '');
    $config = adminConfig();

    if ($error === '' && $user === $config['user'] && adminVerifyPassword($config, $password)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: /admin/');
        exit;
    }

    if ($error === '') {
        $error = 'Usuario ou senha invalidos.';
    }
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
        .password-wrap{position:relative}
        .password-wrap input{box-sizing:border-box;padding-right:48px}
        .password-toggle{position:absolute;right:8px;top:8px;width:34px;height:34px;border:0;border-radius:10px;background:transparent;color:#333;cursor:pointer;display:grid;place-items:center}
        .password-toggle:hover,.password-toggle:focus{background:rgba(255,209,0,.18);outline:0}
        .password-toggle svg{width:19px;height:19px;stroke:currentColor}
        button{width:100%;padding:12px;border:0;border-radius:12px;background:linear-gradient(135deg,var(--tf-gold),var(--tf-gold-dark));color:#000;font-weight:700}
        .error{color:#b42318;margin-bottom:12px}
    </style>
</head>
<body>
    <form class="card" method="post">
        <h1>Painel Totalfilter</h1>
        <p>Faca login para gerenciar o assistente.</p>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?= csrfField() ?>
        <label>Usuario</label>
        <input name="user" required>
        <label>Senha</label>
        <div class="password-wrap">
            <input id="admin-password" type="password" name="password" required>
            <button class="password-toggle" type="button" aria-label="Mostrar senha" aria-pressed="false" data-password-toggle>
                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </div>
        <button type="submit">Entrar</button>
    </form>
    <script>
        const toggle = document.querySelector('[data-password-toggle]');
        const password = document.getElementById('admin-password');

        toggle?.addEventListener('click', () => {
            const visible = password.type === 'text';
            password.type = visible ? 'password' : 'text';
            toggle.setAttribute('aria-pressed', String(!visible));
            toggle.setAttribute('aria-label', visible ? 'Mostrar senha' : 'Ocultar senha');
        });
    </script>
</body>
</html>
