# Assistente Digital Totalfilter

Projeto completo de assistente digital para o site da Totalfilter, com widget embutivel, backend em PHP 8.2+, MySQL, memoria de conversa, base de conhecimento, leads, handoff humano e painel administrativo basico.

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

## Proximos passos

1. Criar `.env` a partir de `.env.example`.
2. Rodar a migration e o seed.
3. Configurar a chave da API LLM.
4. Publicar a pasta `public/` como document root.
5. Inserir o script de embed no site.

## Deploy de producao

O guia atualizado de publicacao esta em `docs/DEPLOY.md`.
