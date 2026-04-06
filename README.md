# Assistente Digital Totalfilter

Projeto completo de assistente digital para o site da Totalfilter, com widget embutivel, backend em PHP 8.2+, MongoDB Atlas/MySQL, memoria de conversa, base de conhecimento, catalogo de produtos, leads, handoff humano e painel administrativo basico.

## Arquitetura recomendada

- `public/`: document root com front controller da API, widget embutivel e ponte para o painel.
- `api/`: controladores, servicos, repositorios, middleware e bootstrap da aplicacao.
- `config/`: carregamento de ambiente, conexao MySQL e configuracao central.
- `database/`: migration principal e seed inicial de FAQ, conhecimento, produtos e settings.
- `admin/`: painel administrativo PHP server-rendered com autenticacao simples via `.env`.
- `storage/logs/`: logs de operacao e falhas.
- `docs/`: instalacao, deploy, prompt do assistente, fluxos e testes.

## Arvore do projeto

```text
chat-agente/
|-- .env.example
|-- README.md
|-- admin/
|   |-- actions.php
|   |-- bootstrap.php
|   |-- export-leads.php
|   |-- index.php
|   |-- login.php
|   `-- logout.php
|-- api/
|   |-- Router.php
|   |-- bootstrap.php
|   |-- helpers.php
|   |-- controllers/
|   |   |-- ChatController.php
|   |   `-- KnowledgeController.php
|   |-- middleware/
|   |   `-- RateLimitMiddleware.php
|   |-- repositories/
|   |   |-- BaseRepository.php
|   |   |-- ChatRepository.php
|   |   |-- FaqRepository.php
|   |   |-- HandoffRepository.php
|   |   |-- KnowledgeRepository.php
|   |   |-- LeadRepository.php
|   |   |-- ProductRepository.php
|   |   `-- SettingsRepository.php
|   `-- services/
|       |-- AssistantService.php
|       |-- ContextService.php
|       |-- GuardrailService.php
|       |-- HandoffService.php
|       |-- KnowledgeService.php
|       |-- LeadService.php
|       |-- LlmService.php
|       `-- Logger.php
|-- config/
|   |-- app.php
|   |-- database.php
|   `-- env.php
|-- database/
|   |-- migrations/
|   |   `-- 001_init.sql
|   `-- seeds/
|       `-- 001_seed_initial.sql
|-- docs/
|   |-- ARQUITETURA.md
|   |-- DEPLOY.md
|   |-- FLUXOS.md
|   |-- INSTALACAO.md
|   |-- PROMPT-SYSTEM.md
|   `-- TESTES.md
|-- public/
|   |-- admin/
|   |   |-- actions.php
|   |   |-- export-leads.php
|   |   |-- index.php
|   |   |-- login.php
|   |   `-- logout.php
|   |-- api/
|   |   `-- index.php
|   |-- chat-widget/
|   |   |-- assets/
|   |   |   `-- mascot.svg
|   |   |-- embed.js
|   |   |-- widget.css
|   |   `-- widget.js
|   `-- index.php
|-- storage/
|   `-- logs/
`-- tests/
    `-- ChatApiSmokeTest.php
```

## Embed simples

```html
<script src="/chat-widget/embed.js"></script>
```

## Importar planilha de produtos

A importacao da planilha `REGISTRO DE PRODUTOS ACABADOS.xlsm` usa a aba `BASE DE DADOS` e grava os produtos na collection `product_index`, a mesma base consultada pelo agente.

Regra de codigo Totalfilter:

```text
UH082CTS255 -> TUH082CTS255
UA351TP -> TUA351TP
DABO3341322 -> TDABO3341322
```

A regra apenas adiciona `T` no inicio quando o codigo original ainda nao comeca com `T`. Ela nao troca letras e nao altera o codigo original.

Para instalar a biblioteca de leitura de Excel:

```powershell
composer install
```

Para importar:

```powershell
php database/seeds/import_products_spreadsheet.php "C:\Users\madsh\Downloads\REGISTRO DE PRODUTOS ACABADOS.xlsm"
```

O script e idempotente: se encontrar o mesmo `codigoOriginal`, `codigoTotalfilter` ou `product_code`, atualiza o registro em vez de duplicar. Ao final ele informa quantos registros foram lidos, inseridos, atualizados, ignorados e com erro.

Depois da importacao, o agente passa a buscar produtos por codigo original, codigo Totalfilter, descricao, aplicacao, desenho e termos livres.

Exemplos de teste no chat:

```text
Tem filtro UH082CTS255?
Tem filtro TUH082CTS255?
Qual o equivalente Totalfilter do codigo UH082CTS255?
Tem filtro para CLARK 8169232C?
Qual o desenho do filtro DABO3341322?
```

## Proximos passos

1. Criar `.env` a partir de `.env.example`.
2. Rodar a migration e o seed.
3. Configurar a chave da API LLM.
4. Publicar a pasta `public/` como document root.
5. Inserir o script de embed no site.

## Deploy de producao

O guia atualizado de publicacao esta em `docs/DEPLOY.md`.
Para Locaweb com FileZilla, use `docs/DEPLOY-LOCAWEB.md`.
Para backend separado na Railway, use `docs/DEPLOY-RAILWAY.md`.
