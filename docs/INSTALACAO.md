# Instalação

## Requisitos

- PHP 8.2+
- MySQL 8+
- Extensão `pdo_mysql`
- Extensão `curl`
- Servidor web com document root apontando para `public/`

## Passos

1. Copie `.env.example` para `.env` e ajuste credenciais, URL e chave da API LLM.
2. Crie o banco de dados:

```sql
CREATE DATABASE totalfilter_chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Rode a migration:

```bash
mysql -u root -p totalfilter_chat < database/migrations/001_init.sql
```

4. Rode o seed inicial:

```bash
mysql -u root -p totalfilter_chat < database/seeds/001_seed_initial.sql
```

5. Aponte o servidor para `public/`.
6. Acesse `/admin/login.php` e use as credenciais configuradas no `.env`.
7. Insira o embed no site:

```html
<script src="/chat-widget/embed.js"></script>
```

## Configuração da LLM

- Preencha `LLM_API_URL`, `LLM_API_KEY` e `LLM_MODEL`.
- A API atual usa payload compatível com `chat/completions`.
- Se a LLM estiver indisponível, o sistema continua funcionando com fallback baseado em FAQ, conhecimento e regras.

## Notificação de lead por e-mail

- Configure `LEAD_NOTIFICATION_EMAIL` no `.env`.
- O projeto agora suporta SMTP autenticado e fallback para `mail()` do PHP.
- Preencha no `.env`:

```env
MAIL_MAILER="smtp"
SMTP_HOST="smtp.seudominio.com"
SMTP_PORT=587
SMTP_USERNAME="usuario@seudominio.com"
SMTP_PASSWORD="sua-senha-smtp"
SMTP_ENCRYPTION="tls"
SMTP_FROM_EMAIL="no-reply@seudominio.com"
SMTP_FROM_NAME="Assistente Totalfilter"
```

- Se `MAIL_MAILER="smtp"` estiver configurado corretamente, os leads serão enviados por SMTP.
- Se o SMTP falhar, o sistema ainda tenta `mail()` como fallback.
- Em ambiente local, o envio real depende de SMTP válido ou de o PHP estar configurado para envio de e-mail.

## Sincronizar catálogo público da Totalfilter

- O projeto agora inclui um importador do catálogo público:

```bash
php database/seeds/import_public_catalog.php
```

- Esse script consulta os endpoints públicos do site e atualiza `product_index`.
- Ele importa nome/código do produto, descrição pública, URL da página de detalhes, status de lançamento e, quando disponíveis, campos técnicos públicos como categoria, peso, externo, interno e altura.
