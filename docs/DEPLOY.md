# Deploy

## Hospedagem simples

- Suba o projeto mantendo `public/` como raiz pública.
- Garanta permissão de escrita em `storage/logs/`.
- Configure `.env` no servidor.
- Importe `database/migrations/001_init.sql` e `database/seeds/001_seed_initial.sql`.

## Apache

- Configure o `DocumentRoot` para `/public`.
- Certifique-se de que `AllowOverride` e `mod_rewrite` estejam habilitados se desejar URL rewriting no futuro.

## Nginx

- Configure `root /caminho/do/projeto/public;`
- Encaminhe `index.php` para PHP-FPM.

## Integração no site Totalfilter

- Publique o widget na mesma origem do site para evitar CORS desnecessário.
- Adicione o script antes do fechamento do `</body>`:

```html
<script src="/chat-widget/embed.js"></script>
```

- Se preferir customizar pelo HTML, também pode usar:

```html
<totalfilter-chat-widget base-url="https://www.totalfilter.com.br"></totalfilter-chat-widget>
<script src="https://www.totalfilter.com.br/chat-widget/widget.js"></script>
```

## Recomendações de produção

- Substituir a autenticação simples do painel por credenciais mais robustas ou SSO.
- Colocar HTTPS obrigatório.
- Adicionar backup do banco.
- Evoluir `product_index` com catálogo real e códigos OEM.
- Integrar CRM para leads e SLA de retorno.
