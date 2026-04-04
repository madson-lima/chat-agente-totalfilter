# Deploy na Locaweb com FileZilla

## Melhor estrutura para o seu caso

Como o seu site principal ja existe dentro de `public_html`, o caminho mais seguro e:

- manter o codigo privado fora do `public_html`
- publicar somente a parte publica do assistente em uma subpasta do site

Estrutura recomendada no servidor:

```text
/
|-- public_html/
|   |-- assistente/
|   |   |-- admin/
|   |   |-- api/
|   |   |-- chat-widget/
|   |   |-- .htaccess
|   |   |-- app-loader.php
|   |   |-- app-path.php
|   |   `-- index.php
|   `-- ... seu site atual ...
`-- totalfilter-backend3/
    `-- chat-agente-totalfilter/
        |-- admin/
        |-- api/
        |-- config/
        |-- database/
        |-- docs/
        |-- storage/
        |-- tests/
        |-- .env
        `-- README.md
```

## O que enviar pelo FileZilla

### 1. Pasta privada

Envie estes itens para:

```text
/totalfilter-backend3/chat-agente-totalfilter/
```

Itens:

- `admin/`
- `api/`
- `config/`
- `database/`
- `docs/`
- `storage/`
- `tests/`
- `.env`
- `README.md`

Nao precisa enviar:

- `.git/`
- `.gitignore`
- arquivos temporarios locais

### 2. Pasta publica

Envie o conteudo da pasta local `public/` para:

```text
/public_html/assistente/
```

Ou seja, dentro de `assistente/` devem ficar:

- `admin/`
- `api/`
- `chat-widget/`
- `.htaccess`
- `app-loader.php`
- `index.php`

## Arquivo obrigatorio no servidor: app-path.php

Depois de subir a pasta publica, copie o arquivo:

```text
public/app-path.example.php
```

para:

```text
/public_html/assistente/app-path.php
```

E altere para o caminho real da pasta privada no servidor:

```php
<?php
return '/totalfilter-backend3/chat-agente-totalfilter';
```

Esse arquivo permite que a parte publica carregue o backend privado fora do `public_html`.

## .env de producao

Crie o `.env` dentro de:

```text
/totalfilter-backend3/chat-agente-totalfilter/.env
```

Base recomendada:

```env
APP_NAME="Assistente Totalfilter"
APP_ENV=production
APP_DEBUG=false
APP_URL="https://www.totalfilter.com.br/assistente"
APP_TIMEZONE="America/Sao_Paulo"
APP_FORCE_HTTPS=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=SEU_BANCO
DB_USER=SEU_USUARIO
DB_PASS=SUA_SENHA
DB_CHARSET=utf8mb4

LLM_PROVIDER=openai-compatible
LLM_API_URL="https://api.openai.com/v1/chat/completions"
LLM_API_KEY="SUA_CHAVE"
LLM_MODEL="gpt-4.1-mini"

ADMIN_USER="madson-lima"
ADMIN_PASSWORD_HASH="COLE_O_HASH_AQUI"

SESSION_NAME="totalfilter_admin"
SESSION_COOKIE_SECURE=true
SESSION_COOKIE_SAMESITE="Lax"

LEAD_NOTIFICATION_EMAIL="madson.limaubn@gmail.com"
MAIL_MAILER="smtp"
SMTP_HOST="SEU_SMTP"
SMTP_PORT=587
SMTP_USERNAME="SEU_USUARIO_SMTP"
SMTP_PASSWORD="SUA_SENHA_SMTP"
SMTP_ENCRYPTION="tls"
SMTP_FROM_EMAIL="no-reply@totalfilter.com.br"
SMTP_FROM_NAME="Assistente Totalfilter"

WIDGET_NAME="Totalzinho"
WIDGET_TITLE="Totalzinho da Totalfilter"
WIDGET_SUBTITLE="Consultor digital Totalfilter"
WIDGET_PRIMARY_COLOR="#0A0A0A"
WIDGET_ACCENT_COLOR="#FFD100"
WIDGET_MASCOT_URL="/assistente/chat-widget/assets/mascot-real.png"
```

## Embed no site atual

No HTML do site atual, antes de `</body>`, adicione:

```html
<script src="/assistente/chat-widget/embed.js"></script>
```

Como o `embed.js` detecta a base automaticamente, ele vai usar:

- `/assistente/chat-widget/widget.js`
- `/assistente/api/...`

sem conflitar com o restante do site.

## Se a API for publicada na Railway

Se voce decidir manter apenas o widget na Locaweb e mover a API para a Railway, troque o embed para:

```html
<script
  src="/assistente/chat-widget/embed.js"
  data-api-base-url="https://SEU-SERVICO.up.railway.app"
></script>
```

Assim:

- o visual do chat continua no seu site
- as chamadas da API vao para a Railway
- a Railway precisa ter `CORS_ALLOWED_ORIGINS` com o dominio do site

## URLs finais de teste

Depois do upload, teste:

- `https://www.totalfilter.com.br/assistente/`
- `https://www.totalfilter.com.br/assistente/api/health`
- `https://www.totalfilter.com.br/assistente/admin/login.php`

## Banco de dados

No painel da Locaweb:

1. crie um banco MySQL
2. importe `database/migrations/001_init.sql`
3. importe `database/seeds/001_seed_initial.sql`

Se quiser usar o catalogo publico inicial, rode o importador em um ambiente com PHP CLI ou importe os dados por outro processo antes de subir.

## Senha do admin com hash

Gere o hash localmente com:

```powershell
php -r "echo password_hash('SUA-SENHA-FORTE', PASSWORD_DEFAULT) . PHP_EOL;"
```

Cole o resultado em:

```env
ADMIN_PASSWORD_HASH="..."
```

## Permissoes

Se a Locaweb permitir ajuste de permissao, garanta escrita em:

- `storage/logs/`
- `storage/sessions/`

## Ordem pratica de publicacao

1. subir a pasta privada
2. subir a pasta publica em `/public_html/assistente`
3. criar `app-path.php`
4. criar `.env`
5. criar/importar o banco
6. testar `/assistente/api/health`
7. inserir o script no site atual
8. testar o chat no site
