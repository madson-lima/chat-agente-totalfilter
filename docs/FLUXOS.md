# Principais Fluxos Conversacionais

## 1. Boas-vindas

- Abrir o chat.
- Criar sessão e registrar mensagem inicial.
- Exibir quick replies para orçamento, filtro certo, comercial e localização.

## 2. Qualificação do visitante

- Identificar se a pessoa busca informação institucional, suporte, produto, orçamento ou atendimento humano.
- Usar intenção inferida para priorizar a resposta.

## 3. Direcionamento para produto

- Solicitar aplicação, modelo, ano, referência ou uso pretendido.
- Usar base `product_index` e FAQ.
- Se faltar confirmação técnica, orientar validação com a equipe comercial.

## 4. Dúvida técnica básica

- Explicar função do tipo de filtro em linguagem simples.
- Não prometer compatibilidade específica sem base.
- Oferecer encaminhamento humano quando necessário.

## 5. Intenção de compra

- Reconhecer gatilhos como orçamento, cotação, preço, comprar, revenda e distribuição.
- Responder de forma consultiva.
- Sugerir captura de lead com os dados essenciais.

## 6. Captura de lead

- Coletar nome, telefone, e-mail, empresa, cidade/estado, produto de interesse e mensagem.
- Salvar em `leads`.
- Confirmar o recebimento com mensagem profissional.

## 7. Encaminhamento humano

- Ativado por pedido explícito ou por necessidade de validação comercial/técnica.
- Gravar em `human_handoff_requests`.
- Registrar status da sessão como `handoff_requested`.

## 8. Resposta institucional

- Buscar FAQ e `knowledge_pages`.
- Responder sobre empresa, história, qualidade, localização e canais.

## 9. Contato e localização

- Priorizar e-mail, telefones e endereço oficiais.
- Reforçar disponibilidade de atendimento humano.

## 10. Fallback elegante

- Assumir com transparência quando não houver base suficiente.
- Não improvisar dados.
- Oferecer contato com a equipe Totalfilter.
