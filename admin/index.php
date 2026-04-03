<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$pdo = database();
$faqRepository = new FaqRepository($pdo);
$knowledgeRepository = new KnowledgeRepository($pdo);
$productRepository = new ProductRepository($pdo);
$leadRepository = new LeadRepository($pdo);
$handoffRepository = new HandoffRepository($pdo);
$chatRepository = new ChatRepository($pdo);
$settingsRepository = new SettingsRepository($pdo);

$settings = $settingsRepository->all();
$faqItems = $faqRepository->all(false);
$knowledgePages = $knowledgeRepository->pages(false);
$products = $productRepository->all(false);
$leads = $leadRepository->all(50);
$handoffs = $handoffRepository->all(50);
$sessions = $chatRepository->allSessions(50);
$stats = [
    'faq' => count($faqItems),
    'knowledge' => count($knowledgePages),
    'products' => count($products),
    'leads' => count($leads),
    'handoffs' => count($handoffs),
    'sessions' => count($sessions),
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel do Assistente Totalfilter</title>
    <style>
        :root{--tf-black:#0a0a0a;--tf-gold:#ffd100;--tf-gold-dark:#d6b602;--tf-cream:#fbf8ea;--tf-border:rgba(255,209,0,.22);--tf-text:#161616;--tf-muted:#645c3c}
        body{font-family:Arial,sans-serif;margin:0;background:linear-gradient(180deg,#f6f1dc,#fffdf4);color:var(--tf-text)}
        header{padding:28px;background:radial-gradient(circle at top right,rgba(255,209,0,.2),transparent 30%),linear-gradient(135deg,#1a1a1a,#000);color:#fff;border-bottom:1px solid rgba(255,209,0,.22)}
        .hero{display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:center}
        .hero img{width:72px;height:72px;border-radius:18px;object-fit:cover;border:1px solid rgba(255,209,0,.28);background:rgba(255,209,0,.08)}
        .hero h1{margin:0 0 6px}
        .hero p{margin:0;color:rgba(255,255,255,.82)}
        main{padding:24px;display:grid;gap:22px}
        .grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))}
        .stats{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
        .stat{background:linear-gradient(180deg,#fffef8,#fff);border:1px solid var(--tf-border);border-radius:18px;padding:16px;box-shadow:0 14px 36px rgba(0,0,0,.05)}
        .stat strong{display:block;font-size:28px;color:#000;margin-top:6px}
        .stat span{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--tf-muted)}
        .card{background:#fff;border-radius:22px;padding:20px;box-shadow:0 18px 50px rgba(0,0,0,.08);border:1px solid var(--tf-border)}
        .card h2{margin-top:0;color:#151515}
        input,textarea{width:100%;padding:10px 12px;border:1px solid rgba(0,0,0,.12);border-radius:12px;box-sizing:border-box;margin:6px 0 12px;background:#fffdf8;color:#171717}
        input:focus,textarea:focus{outline:2px solid rgba(255,209,0,.35);border-color:var(--tf-gold-dark)}
        textarea{min-height:94px}
        button,.btn{display:inline-block;border:0;border-radius:12px;padding:10px 14px;background:linear-gradient(135deg,var(--tf-gold),var(--tf-gold-dark));color:#000;text-decoration:none;cursor:pointer;font-weight:700}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th,td{padding:10px 8px;border-bottom:1px solid rgba(0,0,0,.08);text-align:left;vertical-align:top}
        th{color:#000}
        .muted{color:var(--tf-muted);font-size:13px}
        .danger{background:#1a1a1a;color:var(--tf-gold)}
        .secondary{background:#fff;color:#111;border:1px solid rgba(0,0,0,.12)}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap}
        .section-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px}
        .pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
        .pill-new{background:rgba(255,209,0,.18);color:#5b4700}
        .pill-pending{background:rgba(0,0,0,.08);color:#222}
        .pill-active{background:rgba(255,209,0,.18);color:#5b4700}
        .list-cards{display:grid;gap:12px}
        .mini-card{border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:14px;background:linear-gradient(180deg,#fff,#fffdf7)}
        .mini-card strong{display:block;margin-bottom:4px}
        .mini-card p{margin:4px 0;color:#3d3a2f;font-size:13px}
        .mini-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
    </style>
</head>
<body>
    <header>
        <div class="hero">
            <img src="<?= htmlspecialchars($settings['mascot_url'] ?? '/chat-widget/assets/mascot-real.png', ENT_QUOTES, 'UTF-8') ?>" alt="Mascote Totalfilter">
            <div>
                <h1>Painel do Assistente Totalfilter</h1>
                <p>Gestão de conhecimento, contatos comerciais e conversas do assistente digital.</p>
            </div>
        </div>
        <div class="toolbar" style="margin-top:18px">
            <a class="btn" href="/admin/export-leads.php">Exportar leads</a>
            <a class="btn danger" href="/admin/logout.php">Sair</a>
        </div>
    </header>
    <main>
        <section class="stats">
            <div class="stat"><span>FAQ</span><strong><?= $stats['faq'] ?></strong></div>
            <div class="stat"><span>Conhecimento</span><strong><?= $stats['knowledge'] ?></strong></div>
            <div class="stat"><span>Produtos</span><strong><?= $stats['products'] ?></strong></div>
            <div class="stat"><span>Leads</span><strong><?= $stats['leads'] ?></strong></div>
            <div class="stat"><span>Atendimento humano</span><strong><?= $stats['handoffs'] ?></strong></div>
            <div class="stat"><span>Conversas</span><strong><?= $stats['sessions'] ?></strong></div>
        </section>

        <section class="grid">
            <div class="card">
                <h2>Persona e widget</h2>
                <form action="/admin/actions.php" method="post">
                    <input type="hidden" name="entity" value="settings">
                    <input type="hidden" name="action" value="save">
                    <label>Nome do assistente</label>
                    <input name="assistant_name" value="<?= htmlspecialchars($settings['assistant_name'] ?? 'Toto', ENT_QUOTES, 'UTF-8') ?>">
                    <label>Título</label>
                    <input name="assistant_title" value="<?= htmlspecialchars($settings['assistant_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <label>Subtítulo</label>
                    <input name="assistant_subtitle" value="<?= htmlspecialchars($settings['assistant_subtitle'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <label>Mensagem de boas-vindas</label>
                    <textarea name="welcome_message"><?= htmlspecialchars($settings['welcome_message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <label>Quick replies (1 por linha)</label>
                    <textarea name="quick_replies"><?= htmlspecialchars($settings['quick_replies'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <label>Descrição da persona</label>
                    <textarea name="persona_description"><?= htmlspecialchars($settings['persona_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <label>Placeholder</label>
                    <input name="input_placeholder" value="<?= htmlspecialchars($settings['input_placeholder'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <label>Cor primária</label>
                    <input name="primary_color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#0057A8', ENT_QUOTES, 'UTF-8') ?>">
                    <label>Cor destaque</label>
                    <input name="accent_color" value="<?= htmlspecialchars($settings['accent_color'] ?? '#FF8C1A', ENT_QUOTES, 'UTF-8') ?>">
                    <label>URL do mascote</label>
                    <input name="mascot_url" value="<?= htmlspecialchars($settings['mascot_url'] ?? '/chat-widget/assets/mascot.svg', ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit">Salvar configurações</button>
                </form>
            </div>
            <div class="card">
                <h2>Novo FAQ</h2>
                <form action="/admin/actions.php" method="post">
                    <input type="hidden" name="entity" value="faq">
                    <input type="hidden" name="action" value="save">
                    <label>Categoria</label>
                    <input name="category">
                    <label>Pergunta</label>
                    <input name="question" required>
                    <label>Resposta</label>
                    <textarea name="answer" required></textarea>
                    <label>Palavras-chave</label>
                    <input name="keywords">
                    <label>Ordem</label>
                    <input name="sort_order" value="0">
                    <label><input type="checkbox" name="is_active" checked> Ativo</label>
                    <button type="submit">Salvar FAQ</button>
                </form>
            </div>
            <div class="card">
                <h2>Nova página de conhecimento</h2>
                <form action="/admin/actions.php" method="post">
                    <input type="hidden" name="entity" value="knowledge">
                    <input type="hidden" name="action" value="save">
                    <label>Título</label>
                    <input name="title" required>
                    <label>Slug</label>
                    <input name="slug">
                    <label>Resumo</label>
                    <textarea name="excerpt"></textarea>
                    <label>Conteúdo</label>
                    <textarea name="content" required></textarea>
                    <label>URL de origem</label>
                    <input name="source_url">
                    <label>Palavras-chave</label>
                    <input name="keywords">
                    <label>Prioridade</label>
                    <input name="priority" value="0">
                    <label><input type="checkbox" name="is_active" checked> Ativo</label>
                    <button type="submit">Salvar conhecimento</button>
                </form>
            </div>
            <div class="card">
                <h2>Novo produto indexado</h2>
                <form action="/admin/actions.php" method="post">
                    <input type="hidden" name="entity" value="product">
                    <input type="hidden" name="action" value="save">
                    <label>Código</label>
                    <input name="product_code">
                    <label>Nome</label>
                    <input name="product_name" required>
                    <label>Categoria</label>
                    <input name="category">
                    <label>Resumo de aplicação</label>
                    <textarea name="application_summary"></textarea>
                    <label>Observações técnicas</label>
                    <textarea name="technical_notes"></textarea>
                    <label>Status</label>
                    <input name="status_label">
                    <label>URL do produto</label>
                    <input name="product_url">
                    <label>Palavras-chave</label>
                    <input name="keywords">
                    <label><input type="checkbox" name="is_launch"> Marcar como lançamento</label>
                    <label><input type="checkbox" name="is_active" checked> Ativo</label>
                    <button type="submit">Salvar produto</button>
                </form>
            </div>
        </section>

        <section class="grid">
            <div class="card">
                <h2>FAQ cadastrada</h2>
                <table>
                    <thead><tr><th>Pergunta</th><th>Categoria</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($faqItems as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $item['category'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><a class="btn danger" href="/admin/actions.php?entity=faq&action=delete&id=<?= (int) $item['id'] ?>">Excluir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card">
                <h2>Base de conhecimento</h2>
                <table>
                    <thead><tr><th>Título</th><th>Prioridade</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($knowledgePages as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) $item['priority'] ?></td>
                            <td><a class="btn danger" href="/admin/actions.php?entity=knowledge&action=delete&id=<?= (int) $item['id'] ?>">Excluir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card">
                <h2>Produtos indexados</h2>
                <table>
                    <thead><tr><th>Produto</th><th>Categoria</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $item['category'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><a class="btn danger" href="/admin/actions.php?entity=product&action=delete&id=<?= (int) $item['id'] ?>">Excluir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid">
            <div class="card">
                <div class="section-head">
                    <h2>Leads recebidos</h2>
                    <span class="pill pill-new">Ultimos 50</span>
                </div>
                <div class="list-cards">
                    <?php foreach ($leads as $lead): ?>
                        <article class="mini-card">
                            <strong><?= htmlspecialchars($lead['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= htmlspecialchars((string) $lead['product_interest'], ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="mini-meta">
                                <span class="pill pill-new"><?= htmlspecialchars($lead['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span><?= htmlspecialchars((string) $lead['city_state'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p><strong>Contato:</strong> <?= htmlspecialchars(trim(($lead['phone'] ?? '') . ' ' . ($lead['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if (!empty($lead['company'])): ?><p><strong>Empresa:</strong> <?= htmlspecialchars((string) $lead['company'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                            <?php if (!empty($lead['message'])): ?><p><strong>Obs:</strong> <?= htmlspecialchars((string) $lead['message'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                            <div class="toolbar" style="margin-top:10px">
                                <a class="btn secondary" href="/admin/actions.php?entity=lead&action=status&id=<?= (int) $lead['id'] ?>&status=in_progress">Em atendimento</a>
                                <a class="btn" href="/admin/actions.php?entity=lead&action=status&id=<?= (int) $lead['id'] ?>&status=done">Concluir</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card">
                <div class="section-head">
                    <h2>Solicitações de atendimento humano</h2>
                    <span class="pill pill-pending">Fila</span>
                </div>
                <table>
                    <thead><tr><th>Nome</th><th>Canal</th><th>Status</th><th>Motivo</th></tr></thead>
                    <tbody>
                    <?php foreach ($handoffs as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $item['preferred_channel'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="pill pill-pending"><?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string) $item['reason'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="toolbar">
                                    <a class="btn secondary" href="/admin/actions.php?entity=handoff&action=status&id=<?= (int) $item['id'] ?>&status=in_progress">Assumir</a>
                                    <a class="btn" href="/admin/actions.php?entity=handoff&action=status&id=<?= (int) $item['id'] ?>&status=done">Concluir</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card">
                <div class="section-head">
                    <h2>Conversas recentes</h2>
                    <span class="pill pill-active">Sessões</span>
                </div>
                <table>
                    <thead><tr><th>Sessão</th><th>Status</th><th>Último tema</th><th>Lead</th></tr></thead>
                    <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?= htmlspecialchars(substr($session['session_token'], 0, 12) . '...', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="pill pill-active"><?= htmlspecialchars($session['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string) $session['last_topic'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $session['lead_status'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
