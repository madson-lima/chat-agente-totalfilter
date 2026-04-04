# Deploy na Railway

## Arquitetura recomendada

Para o seu caso, use este modelo:

- site principal e widget publico na Locaweb
- API do assistente na Railway
- banco MySQL do assistente acessivel pela Railway

## O que este projeto ja suporta

- deploy via `Dockerfile`
- `healthcheck` em `/api/health`
- CORS configuravel para o dominio do site
- widget com `data-api-base-url` para chamar a API externa

## Passo a passo

### 1. Criar o projeto

Na Railway:

1. clique em `New Project`
2. escolha `Deploy from GitHub repo`
3. selecione este repositório

### 2. Banco de dados

Este projeto continua usando MySQL.

Voce pode:

- usar um MySQL externo
- ou usar um serviço MySQL separado e apontar as variaveis da Railway para ele

Preencha:

```env
DB_HOST=
DB_PORT=3306
DB_NAME=
DB_USER=
DB_PASS=
DB_CHARSET=utf8mb4
```

### 3. Variaveis de ambiente na Railway

Defina no serviço:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://SEU-SERVICO.up.railway.app
APP_TIMEZONE=America/Sao_Paulo
APP_FORCE_HTTPS=true
CORS_ALLOWED_ORIGINS=https://www.totalfilter.com.br,https://totalfilter.com.br
CORS_ALLOW_CREDENTIALS=false

LLM_PROVIDER=openai-compatible
LLM_API_URL=https://api.openai.com/v1/chat/completions
LLM_API_KEY=SUA_CHAVE
LLM_MODEL=gpt-4.1-mini
LLM_TEMPERATURE=0.4
LLM_TIMEOUT=25

ADMIN_USER=madson-lima
ADMIN_PASSWORD_HASH=SEU_HASH

RATE_LIMIT_WINDOW=60
RATE_LIMIT_MAX=25
MAX_CONTEXT_MESSAGES=10
SUMMARY_TRIGGER_COUNT=8
SUMMARY_MAX_CHARS=1400

LOG_FILE=storage/logs/app.log
SESSION_NAME=totalfilter_admin
SESSION_COOKIE_SECURE=true
SESSION_COOKIE_SAMESITE=Lax

LEAD_NOTIFICATION_EMAIL=madson.limaubn@gmail.com
MAIL_MAILER=smtp
SMTP_HOST=SEU_SMTP
SMTP_PORT=587
SMTP_USERNAME=SEU_USUARIO_SMTP
SMTP_PASSWORD=SUA_SENHA_SMTP
SMTP_ENCRYPTION=tls
SMTP_FROM_EMAIL=no-reply@totalfilter.com.br
SMTP_FROM_NAME=Assistente Totalfilter

WIDGET_NAME=Totalzinho
WIDGET_TITLE=Totalzinho da Totalfilter
WIDGET_SUBTITLE=Consultor digital Totalfilter
WIDGET_PRIMARY_COLOR=#0A0A0A
WIDGET_ACCENT_COLOR=#FFD100
WIDGET_MASCOT_URL=/assistente/chat-widget/assets/mascot-real.png
```

## Widget na Locaweb apontando para a Railway

No site, use:

```html
<script
  src="/assistente/chat-widget/embed.js"
  data-api-base-url="https://SEU-SERVICO.up.railway.app"
></script>
```

Assim:

- os arquivos visuais continuam vindo do seu site
- as chamadas `/api/...` vao para a Railway

## Health check

Use:

```text
/api/health
```

## Banco e seed

A Railway nao roda automaticamente a migration deste projeto.
Entao voce precisa importar manualmente:

- `database/migrations/001_init.sql`
- `database/seeds/001_seed_initial.sql`

## Testes finais

Teste:

- `https://SEU-SERVICO.up.railway.app/api/health`
- chat abrindo no site
- envio de mensagem no widget
- login do painel na URL da Railway, se exposto

## Observacao importante

Se voce nao quiser expor o painel admin publicamente na Railway, mantenha o painel apenas no ambiente controlado ou proteja a rota por IP/proxy reverso depois.
