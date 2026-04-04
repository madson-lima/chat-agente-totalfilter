# Deploy

## Objetivo

Este projeto foi estruturado para rodar em hospedagem comum com:

- PHP 8.2+
- MySQL 8+
- Apache ou Nginx
- pasta `public/` como raiz publica

## Checklist de producao

1. Configurar a pasta `public/` como `DocumentRoot`.
2. Subir todos os arquivos do projeto, exceto `.env` local.
3. Criar o `.env` de producao no servidor.
4. Importar `database/migrations/001_init.sql`.
5. Importar `database/seeds/001_seed_initial.sql`.
6. Garantir escrita em `storage/logs/` e `storage/sessions/`.
7. Ativar HTTPS e definir `APP_FORCE_HTTPS=true`.
8. Configurar SMTP real para notificacao de leads.
9. Definir senha forte no painel com `ADMIN_PASSWORD_HASH`.
10. Testar `GET /api/health`, widget, painel e envio de lead.

## Variaveis recomendadas no .env

Use estas diretrizes no servidor:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL="https://www.totalfilter.com.br"
APP_FORCE_HTTPS=true
SESSION_COOKIE_SECURE=true
MAIL_MAILER="smtp"
```

Para o painel administrativo, o ideal e usar hash da senha:

```env
ADMIN_USER="seu-usuario"
ADMIN_PASSWORD_HASH="COLE_O_HASH_GERADO_AQUI"
```

Exemplo de geracao do hash:

```bash
php -r "echo password_hash('SUA-SENHA-FORTE', PASSWORD_DEFAULT) . PHP_EOL;"
```

## Apache

- Configure o `DocumentRoot` para a pasta `/public`.
- Habilite `mod_rewrite` e `mod_headers`.
- Mantenha `AllowOverride All` para que o `.htaccess` da pasta publica seja respeitado.

Exemplo:

```apache
<VirtualHost *:80>
    ServerName www.totalfilter.com.br
    DocumentRoot /var/www/chat-agente/public

    <Directory /var/www/chat-agente/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Nginx

Exemplo basico:

```nginx
server {
    listen 80;
    server_name www.totalfilter.com.br;
    root /var/www/chat-agente/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

## Permissoes

Garanta que o usuario do PHP tenha escrita nestas pastas:

- `storage/logs/`
- `storage/sessions/`

Em Linux:

```bash
chown -R www-data:www-data /var/www/chat-agente/storage
chmod -R 775 /var/www/chat-agente/storage
```

## Banco de dados

Crie o banco com `utf8mb4` e importe:

```bash
mysql -u usuario -p totalfilter_chat < database/migrations/001_init.sql
mysql -u usuario -p totalfilter_chat < database/seeds/001_seed_initial.sql
```

Se quiser o catalogo publico inicial:

```bash
php database/seeds/import_public_catalog.php
```

## Integracao no site

Publique o widget na mesma origem do site para evitar CORS desnecessario.

Embed simples:

```html
<script src="/chat-widget/embed.js"></script>
```

Opcao com custom element:

```html
<totalfilter-chat-widget base-url="https://www.totalfilter.com.br"></totalfilter-chat-widget>
<script src="https://www.totalfilter.com.br/chat-widget/widget.js"></script>
```

## Validacao apos deploy

Teste estes pontos:

1. `https://seudominio/api/health`
2. abertura do widget
3. envio de mensagem no chat
4. lead salvo no banco
5. email de lead recebido
6. login do painel
7. exportacao de leads

## Recomendacoes adicionais

- Coloque o projeto atras de HTTPS obrigatorio.
- Mantenha backup do banco e do `.env`.
- Revogue qualquer chave exposta durante testes.
- Se o projeto crescer, substitua o login simples do painel por SSO ou controle de usuarios.
