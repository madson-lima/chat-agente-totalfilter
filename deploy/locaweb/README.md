# Pacote Locaweb

Esta pasta foi preparada para facilitar o upload manual via FileZilla.

## Estrutura

- `private-package/`
  Arquivos que devem ficar fora do `public_html`.

- `public-package/assistente/`
  Arquivos publicos do assistente, para subir em `/public_html/assistente/`.

## Destinos no servidor

### 1. Parte privada

Envie o conteudo de:

`private-package/`

para:

`/totalfilter-backend3/chat-agente-totalfilter/`

### 2. Parte publica

Envie o conteudo de:

`public-package/assistente/`

para:

`/public_html/assistente/`

## Arquivos para editar antes do upload

### app-path.php

Edite:

`public-package/assistente/app-path.php`

Confirmando o caminho privado no servidor. O valor padrao preparado e:

```php
<?php
return '/totalfilter-backend3/chat-agente-totalfilter';
```

### .env

Edite:

`private-package/.env`

Preenchendo:

- banco MySQL da Locaweb
- chave da API LLM
- SMTP
- hash da senha do admin

## Embed no site atual

Adicione no site:

```html
<script src="/assistente/chat-widget/embed.js"></script>
```

Se a API ficar na Railway:

```html
<script
  src="/assistente/chat-widget/embed.js"
  data-api-base-url="https://SEU-SERVICO.up.railway.app"
></script>
```

## Testes finais

- `https://www.totalfilter.com.br/assistente/api/health`
- `https://www.totalfilter.com.br/assistente/admin/login.php`
