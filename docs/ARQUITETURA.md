# Arquitetura Final Recomendada

## Objetivo

Entregar um assistente digital embutível no site da Totalfilter com foco em pré-venda, suporte, apresentação institucional e captura de oportunidade comercial.

## Camadas

### Widget frontend

- Implementado em Web Component com JavaScript puro.
- Carregamento por um único script: `/chat-widget/embed.js`.
- Persistência local com `localStorage` para `visitor_id` e `session_token`.
- UI responsiva para desktop e mobile.
- Suporta histórico, quick replies, typing state, reset e captura de lead.

### API REST PHP

- Front controller em `public/api/index.php`.
- Roteamento simples sem framework pesado.
- Serviços separados por responsabilidade:
  - `AssistantService`: orquestra contexto, intenção, regras e LLM.
  - `ContextService`: memória de sessão e resumo automático.
  - `KnowledgeService`: busca em FAQ, base institucional e produtos.
  - `LeadService` e `HandoffService`: gravações comerciais.
  - `GuardrailService`: proteção contra prompt injection e vazamento de instruções.
- `RateLimitMiddleware` limita o abuso por IP.

### Banco MySQL

- `chat_sessions`: contexto persistente e estado da conversa.
- `chat_messages`: histórico completo por sessão.
- `faq_items`: FAQ editável pelo painel.
- `knowledge_pages`: conteúdo institucional e regras de negócio.
- `product_index`: índice de produtos e lançamentos.
- `leads`: oportunidades capturadas.
- `human_handoff_requests`: pedidos de atendimento humano.
- `assistant_settings`: ajustes de persona, saudação e visual.
- `rate_limits`: controle básico anti-abuso.

### Painel administrativo

- PHP renderizado no servidor.
- Login simples via `.env`.
- CRUD básico para FAQ, páginas de conhecimento e produtos.
- Ajuste de nome interno, mensagens e visual do assistente.
- Visualização de leads, handoffs e sessões.
- Exportação de leads em CSV.

## Estratégia de resposta

1. Recuperar memória da sessão.
2. Buscar conhecimento em FAQ.
3. Buscar base institucional.
4. Buscar índice de produtos e lançamentos.
5. Chamar LLM com contexto controlado.
6. Se LLM falhar ou faltar base, responder por fallback seguro.

## Segurança mínima já prevista

- Sanitização de entradas.
- Validação de e-mail e telefone.
- Rate limit por IP.
- Logging local.
- Proteção básica contra prompt injection.
- Não exposição de prompt interno, instruções e arquitetura.
- Fallback seguro quando houver dúvida factual.
