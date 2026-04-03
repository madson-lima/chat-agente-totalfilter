# Assistente Digital Totalfilter

Projeto completo de assistente digital para o site da Totalfilter, com widget embutível, backend em PHP 8.2+, MySQL, memória de conversa, base de conhecimento, leads, handoff humano e painel administrativo básico.

## Arquitetura recomendada

- `public/`: document root com front controller da API, widget embutível e ponte para o painel.
- `api/`: controladores, serviços, repositórios, middleware e bootstrap da aplicação.
- `config/`: carregamento de ambiente, conexão MySQL e configuração central.
- `database/`: migration principal e seed inicial de FAQ, conhecimento, produtos e settings.
- `admin/`: painel administrativo PHP server-rendered com autenticação simples via `.env`.
- `storage/logs/`: logs de operação e falhas.
- `docs/`: instalação, deploy, prompt do assistente, fluxos e testes.

## Árvore do projeto

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

## Próximos passos

1. Criar `.env` a partir de `.env.example`.
2. Rodar a migration e o seed.
3. Configurar a chave da API LLM.
4. Publicar a pasta `public/` como document root.
5. Inserir o script de embed no site.
