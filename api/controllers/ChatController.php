<?php

declare(strict_types=1);

final class ChatController
{
    private ChatRepository $chatRepository;
    private LeadService $leadService;
    private HandoffService $handoffService;
    private SettingsRepository $settingsRepository;
    private AssistantService $assistantService;
    private ContextService $contextService;
    private Logger $logger;

    public function __construct(PDO $pdo, private array $config)
    {
        $this->chatRepository = new ChatRepository($pdo);
        $leadRepository = new LeadRepository($pdo);
        $handoffRepository = new HandoffRepository($pdo);
        $faqRepository = new FaqRepository($pdo);
        $knowledgeRepository = new KnowledgeRepository($pdo);
        $productRepository = new ProductRepository($pdo);
        $this->settingsRepository = new SettingsRepository($pdo);
        $this->contextService = new ContextService($this->chatRepository, $config);
        $this->logger = new Logger($config);
        $knowledgeService = new KnowledgeService($faqRepository, $knowledgeRepository, $productRepository);
        $this->assistantService = new AssistantService($config, $this->settingsRepository, $knowledgeService, $this->contextService, new GuardrailService(), new LlmService($config, $this->logger));
        $this->leadService = new LeadService($leadRepository, $this->chatRepository, new LeadNotificationService($config, $this->logger));
        $this->handoffService = new HandoffService($handoffRepository, $this->chatRepository);
    }

    public function widgetConfig(): array
    {
        $settings = $this->settingsRepository->all();
        return [
            'ok' => true,
            'assistant' => [
                'name' => $settings['assistant_name'] ?? $this->config['widget']['name'],
                'title' => $settings['assistant_title'] ?? $this->config['widget']['title'],
                'subtitle' => $settings['assistant_subtitle'] ?? $this->config['widget']['subtitle'],
                'welcome_message' => $settings['welcome_message'] ?? 'Olá. Eu sou o assistente digital da Totalfilter. Posso ajudar com produtos, orçamento, lançamentos e contato.',
                'placeholder' => $settings['input_placeholder'] ?? 'Escreva sua dúvida ou objetivo',
                'quick_replies' => array_values(array_filter(array_map('trim', explode("\n", $settings['quick_replies'] ?? "Quero um orçamento\nComo encontrar o filtro certo?\nFalar com o comercial\nOnde fica a Totalfilter?")))),
                'primary_color' => $settings['primary_color'] ?? $this->config['widget']['primary_color'],
                'accent_color' => $settings['accent_color'] ?? $this->config['widget']['accent_color'],
                'mascot_url' => $settings['mascot_url'] ?? $this->config['widget']['mascot_url'],
            ],
        ];
    }

    public function start(): void
    {
        $payload = requestJson();
        $visitorId = cleanText($payload['visitor_id'] ?? bin2hex(random_bytes(12)), 64);
        $token = bin2hex(random_bytes(24));
        $session = $this->chatRepository->createSession($token, $visitorId, [
            'page_url' => cleanText($payload['page_url'] ?? '', 255),
            'referrer_url' => cleanText($payload['referrer_url'] ?? '', 255),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => clientIp(),
            'locale' => cleanText($payload['locale'] ?? 'pt-BR', 12),
        ]);

        $settings = $this->settingsRepository->all();
        $welcome = $settings['welcome_message'] ?? 'Olá. Sou o assistente digital da Totalfilter. Posso te orientar sobre produtos, qualidade, lançamentos e contato.';
        $this->chatRepository->addMessage((int) $session['id'], 'assistant', $welcome, ['kind' => 'welcome']);
        $this->chatRepository->incrementMessageCount((int) $session['id']);

        jsonResponse([
            'ok' => true,
            'session_token' => $token,
            'visitor_id' => $visitorId,
            'message' => $welcome,
            'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
            'context_actions' => [],
        ]);
    }

    public function message(): void
    {
        $payload = requestJson();
        $token = cleanText($payload['session_token'] ?? '', 64);
        $message = cleanText($payload['message'] ?? '', 1500);

        if ($token === '' || $message === '') {
            jsonResponse(['ok' => false, 'message' => 'Sessão ou mensagem inválida.'], 422);
        }

        $session = $this->chatRepository->findByToken($token);
        if (!$session) {
            jsonResponse(['ok' => false, 'message' => 'Sessão não encontrada.'], 404);
        }

        $this->chatRepository->addMessage((int) $session['id'], 'user', $message);
        $this->chatRepository->incrementMessageCount((int) $session['id']);

        $metadata = $this->sessionMetadata($session);
        if (!empty($metadata['lead_flow']['active'])) {
            $newIntent = $this->assistantService->inferPublicIntent($message);
            if ($this->shouldInterruptLeadFlow($message, $newIntent)) {
                unset($metadata['lead_flow']);
                $this->chatRepository->updateSession((int) $session['id'], [
                    'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    'last_topic' => $newIntent,
                ]);
                $session['metadata_json'] = json_encode($metadata, JSON_UNESCAPED_UNICODE);
            } else {
            if (!empty($metadata['lead_flow']['awaiting_confirmation'])) {
                $confirmationResponse = $this->handleLeadConfirmation($session, $metadata, $message);
                jsonResponse($confirmationResponse);
            }

            $flowResponse = $this->continueLeadFlow($session, $metadata, $message);
            jsonResponse($flowResponse);
            }
        }

        $reply = $this->assistantService->reply($session, $message);
        if ($reply['intent'] === 'compra') {
            $flowResponse = $this->startLeadFlow($session, $message);
            jsonResponse($flowResponse);
        }

        $assistantMessage = cleanText($reply['answer'], 4000);
        $messageMeta = ['source' => $reply['source'], 'intent' => $reply['intent']];
        $productCards = $this->buildProductCards($reply['knowledge'] ?? []);
        if (!empty($productCards)) {
            $messageMeta['product_cards'] = $productCards;
        }
        $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, $messageMeta);
        $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
        $this->chatRepository->updateSession((int) $session['id'], [
            'last_user_message' => $message,
            'last_assistant_message' => $assistantMessage,
            'last_topic' => $reply['intent'],
            'message_count' => $count,
        ]);
        $refreshed = $this->chatRepository->findByToken($token);
        if ($refreshed) {
            $this->contextService->maybeUpdateSummary($refreshed);
        }

        $this->logger->info('Mensagem processada', ['session_token' => $token, 'intent' => $reply['intent'], 'source' => $reply['source']]);

        jsonResponse([
            'ok' => true,
            'message' => $assistantMessage,
            'intent' => $reply['intent'],
            'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
            'suggest_capture_lead' => $reply['intent'] === 'compra',
            'context_actions' => [],
        ]);
    }

    public function history(): void
    {
        $token = cleanText($_GET['session_token'] ?? '', 64);
        if ($token === '') {
            jsonResponse(['ok' => false, 'message' => 'Sessão inválida.'], 422);
        }

        $session = $this->chatRepository->findByToken($token);
        if (!$session) {
            jsonResponse(['ok' => false, 'message' => 'Sessão não encontrada.'], 404);
        }

        jsonResponse([
            'ok' => true,
            'session' => $session,
            'history' => $this->chatRepository->fullHistory((int) $session['id']),
        ]);
    }

    public function reset(): void
    {
        $payload = requestJson();
        $token = cleanText($payload['session_token'] ?? '', 64);
        $session = $this->chatRepository->findByToken($token);
        if (!$session) {
            jsonResponse(['ok' => false, 'message' => 'Sessão não encontrada.'], 404);
        }

        $this->chatRepository->updateSession((int) $session['id'], ['status' => 'reset', 'summary' => '', 'last_topic' => '', 'last_user_message' => '', 'last_assistant_message' => '']);
        jsonResponse(['ok' => true, 'message' => 'Conversa reiniciada com sucesso.']);
    }

    public function captureLead(): void
    {
        $payload = requestJson();
        $token = cleanText($payload['session_token'] ?? '', 64);
        $session = $this->chatRepository->findByToken($token);
        if (!$session) {
            jsonResponse(['ok' => false, 'message' => 'Sessão não encontrada.'], 404);
        }

        $lead = $this->leadService->store($session, $payload);
        jsonResponse(['ok' => true, 'lead_id' => $lead['lead_id'], 'message' => $lead['confirmation']]);
    }

    public function requestHandoff(): void
    {
        $payload = requestJson();
        $token = cleanText($payload['session_token'] ?? '', 64);
        $session = $this->chatRepository->findByToken($token);
        if (!$session) {
            jsonResponse(['ok' => false, 'message' => 'Sessão não encontrada.'], 404);
        }

        $handoff = $this->handoffService->create($session, $payload);
        jsonResponse(['ok' => true, 'handoff_id' => $handoff['handoff_id'], 'message' => $handoff['message']]);
    }

    private function sessionMetadata(array $session): array
    {
        $decoded = json_decode((string) ($session['metadata_json'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function startLeadFlow(array $session, string $message): array
    {
        $metadata = $this->sessionMetadata($session);
        $fields = [
            'product_interest' => $this->detectInitialProductInterest($message),
            'city_state' => '',
            'name' => '',
            'phone' => '',
            'email' => '',
            'company' => '',
            'message' => '',
        ];

        $nextField = $this->nextLeadField($fields);
        $metadata['lead_flow'] = [
            'active' => true,
            'fields' => $fields,
            'current_field' => $nextField,
        ];

        $assistantMessage = $this->leadQuestion($nextField);
        $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, ['source' => 'lead-flow', 'intent' => 'compra']);
        $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
        $this->chatRepository->updateSession((int) $session['id'], [
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'last_assistant_message' => $assistantMessage,
            'last_topic' => 'compra',
            'message_count' => $count,
        ]);

        return [
            'ok' => true,
            'message' => $assistantMessage,
            'intent' => 'compra',
            'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
            'suggest_capture_lead' => false,
            'context_actions' => $this->leadFlowActions($nextField, false),
        ];
    }

    private function continueLeadFlow(array $session, array $metadata, string $message): array
    {
        $flow = $metadata['lead_flow'];
        $currentField = $flow['current_field'] ?? '';
        $fields = is_array($flow['fields'] ?? null) ? $flow['fields'] : [];

        if ($currentField !== '') {
            $validation = $this->validateLeadField($currentField, $message);
            if (!$validation['valid']) {
                $assistantMessage = $validation['message'];
                $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, ['source' => 'lead-flow-validation', 'intent' => 'compra']);
                $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
                $this->chatRepository->updateSession((int) $session['id'], [
                    'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    'last_assistant_message' => $assistantMessage,
                    'last_topic' => 'compra',
                    'message_count' => $count,
                ]);

                return [
                    'ok' => true,
                    'message' => $assistantMessage,
                    'intent' => 'compra',
                    'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
                    'suggest_capture_lead' => false,
                ];
            }

            $fields[$currentField] = $validation['value'];
        }

        $nextField = $this->nextLeadField($fields);

        if ($nextField === null) {
            $metadata['lead_flow'] = [
                'active' => true,
                'fields' => $fields,
                'current_field' => null,
                'awaiting_confirmation' => true,
            ];

            $assistantMessage = $this->leadSummaryMessage($fields);
            $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, ['source' => 'lead-flow', 'intent' => 'compra']);
            $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
            $this->chatRepository->updateSession((int) $session['id'], [
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'last_assistant_message' => $assistantMessage,
                'last_topic' => 'compra',
                'message_count' => $count,
            ]);

            return [
                'ok' => true,
                'message' => $assistantMessage,
                'intent' => 'compra',
                'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
                'suggest_capture_lead' => false,
                'context_actions' => [],
            ];
        }

        $metadata['lead_flow'] = [
            'active' => true,
            'fields' => $fields,
            'current_field' => $nextField,
        ];

        $assistantMessage = $this->leadQuestion($nextField);
        $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, ['source' => 'lead-flow', 'intent' => 'compra']);
        $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
        $this->chatRepository->updateSession((int) $session['id'], [
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'last_assistant_message' => $assistantMessage,
            'last_topic' => 'compra',
            'message_count' => $count,
        ]);

        return [
            'ok' => true,
            'message' => $assistantMessage,
            'intent' => 'compra',
            'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
            'suggest_capture_lead' => false,
            'context_actions' => $this->leadFlowActions($nextField, false),
        ];
    }

    private function detectInitialProductInterest(string $message): string
    {
        $normalized = cleanText($message, 255);
        if (mb_strlen($normalized) < 12) {
            return '';
        }

        return $normalized;
    }

    private function nextLeadField(array $fields): ?string
    {
        foreach (['product_interest', 'city_state', 'name', 'phone', 'email', 'company', 'message'] as $field) {
            if (($fields[$field] ?? '') === '') {
                return $field;
            }
        }

        return null;
    }

    private function leadQuestion(string $field): string
    {
        return match ($field) {
            'product_interest' => 'Para eu seguir com o atendimento comercial, qual produto, tipo de filtro ou aplicacao voce procura?',
            'city_state' => 'Perfeito. Em qual cidade e estado voce esta?',
            'name' => 'Qual e o seu nome, por favor?',
            'phone' => 'Qual telefone a equipe comercial pode usar para falar com voce?',
            'email' => 'Se quiser, me informe tambem seu e-mail para retorno comercial. Se preferir pular, responda "pular".',
            'company' => 'Qual e o nome da sua empresa? Se preferir pular, responda "pular".',
            'message' => 'Existe alguma observacao adicional para a equipe comercial? Se nao houver, responda "pular".',
            default => 'Pode me passar mais um detalhe para eu continuar o atendimento?',
        };
    }

    private function normalizeLeadField(string $field, string $value): string
    {
        $value = cleanText($value, 1000);
        $skip = in_array(mb_strtolower($value), ['pular', 'nao tenho', 'não tenho', 'sem', 'nao', 'não'], true);

        if (in_array($field, ['email', 'company', 'message'], true) && $skip) {
            return '-';
        }

        return match ($field) {
            'phone' => cleanPhone($value),
            'email' => cleanEmail($value) ?: '-',
            'name' => cleanText($value, 120),
            'company' => cleanText($value, 120),
            'city_state' => cleanText($value, 120),
            'product_interest' => cleanText($value, 255),
            'message' => cleanText($value, 1000),
            default => cleanText($value, 255),
        };
    }

    private function validateLeadField(string $field, string $value): array
    {
        $normalized = $this->normalizeLeadField($field, $value);

        if ($field === 'phone' && mb_strlen($normalized) < 10) {
            return [
                'valid' => false,
                'value' => '',
                'message' => 'Nao consegui identificar um telefone valido. Pode me enviar com DDD, por exemplo: 11999998888?',
            ];
        }

        if ($field === 'email' && $normalized === '-' && !in_array(mb_strtolower(cleanText($value, 100)), ['pular', 'nao tenho', 'não tenho', 'sem', 'nao', 'não'], true)) {
            return [
                'valid' => false,
                'value' => '',
                'message' => 'Esse e-mail parece invalido. Se quiser informar, envie no formato nome@empresa.com. Se preferir pular, responda "pular".',
            ];
        }

        if (in_array($field, ['product_interest', 'city_state', 'name'], true) && $normalized === '') {
            return [
                'valid' => false,
                'value' => '',
                'message' => $this->leadQuestion($field),
            ];
        }

        return [
            'valid' => true,
            'value' => $normalized,
            'message' => '',
        ];
    }

    private function handleLeadConfirmation(array $session, array $metadata, string $message): array
    {
        $normalized = mb_strtolower(cleanText($message, 100));
        $flow = $metadata['lead_flow'] ?? [];
        $fields = is_array($flow['fields'] ?? null) ? $flow['fields'] : [];

        $correctionField = $this->detectCorrectionField($normalized);
        if ($correctionField !== null) {
            $metadata['lead_flow'] = [
                'active' => true,
                'fields' => $fields,
                'current_field' => $correctionField,
                'awaiting_confirmation' => false,
            ];

            $assistantMessage = 'Sem problema. Vamos corrigir esse dado. ' . $this->leadQuestion($correctionField);
            $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, ['source' => 'lead-flow-correction', 'intent' => 'compra']);
            $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
            $this->chatRepository->updateSession((int) $session['id'], [
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'last_assistant_message' => $assistantMessage,
                'last_topic' => 'compra',
                'message_count' => $count,
            ]);

            return [
                'ok' => true,
                'message' => $assistantMessage,
                'intent' => 'compra',
                'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
                'suggest_capture_lead' => false,
                'context_actions' => [],
            ];
        }

        if (in_array($normalized, ['sim', 's', 'confirmar', 'ok', 'pode'], true)) {
            $lead = $this->leadService->store($session, array_merge($fields, ['source' => 'chat-guided-flow']));
            $assistantMessage = $lead['confirmation'] . ' Se quiser, eu tambem posso te passar novamente os contatos comerciais da Totalfilter.';
            unset($metadata['lead_flow']);

            $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, ['source' => 'lead-flow-confirmation', 'intent' => 'compra']);
            $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
            $this->chatRepository->updateSession((int) $session['id'], [
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'last_assistant_message' => $assistantMessage,
                'last_topic' => 'compra',
                'message_count' => $count,
            ]);

            return [
                'ok' => true,
                'message' => $assistantMessage,
                'intent' => 'compra',
                'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
                'suggest_capture_lead' => false,
                'context_actions' => [],
            ];
        }

        if (in_array($normalized, ['nao', 'não', 'n', 'cancelar'], true)) {
            unset($metadata['lead_flow']);
            $assistantMessage = 'Tudo bem. Nao vou enviar seus dados agora. Se quiser, podemos continuar por aqui ou eu posso te passar novamente os contatos comerciais da Totalfilter.';

            $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, ['source' => 'lead-flow-cancel', 'intent' => 'compra']);
            $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
            $this->chatRepository->updateSession((int) $session['id'], [
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'last_assistant_message' => $assistantMessage,
                'last_topic' => 'compra',
                'message_count' => $count,
            ]);

            return [
                'ok' => true,
                'message' => $assistantMessage,
                'intent' => 'compra',
                'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
                'suggest_capture_lead' => false,
            ];
        }

        $assistantMessage = 'Se os dados estiverem corretos, responda "sim" para enviar. Se preferir nao enviar agora, responda "nao". Se quiser corrigir algo, responda por exemplo "alterar telefone" ou "corrigir e-mail".';
        $this->chatRepository->addMessage((int) $session['id'], 'assistant', $assistantMessage, ['source' => 'lead-flow-confirmation', 'intent' => 'compra']);
        $count = $this->chatRepository->incrementMessageCount((int) $session['id']);
        $this->chatRepository->updateSession((int) $session['id'], [
            'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'last_assistant_message' => $assistantMessage,
            'last_topic' => 'compra',
            'message_count' => $count,
        ]);

        return [
            'ok' => true,
            'message' => $assistantMessage,
            'intent' => 'compra',
            'history' => $this->chatRepository->recentMessages((int) $session['id'], 20),
            'suggest_capture_lead' => false,
            'context_actions' => $this->leadFlowActions(null, true),
        ];
    }

    private function leadSummaryMessage(array $fields): string
    {
        $company = ($fields['company'] ?? '') !== '-' ? $fields['company'] : 'nao informado';
        $email = ($fields['email'] ?? '') !== '-' ? $fields['email'] : 'nao informado';
        $message = ($fields['message'] ?? '') !== '-' ? $fields['message'] : 'sem observacoes adicionais';

        return "Perfeito. Antes de enviar para a equipe comercial, confirme se estes dados estao corretos:\n\n"
            . "Produto ou aplicacao: " . ($fields['product_interest'] ?? '') . "\n"
            . "Cidade/Estado: " . ($fields['city_state'] ?? '') . "\n"
            . "Nome: " . ($fields['name'] ?? '') . "\n"
            . "Telefone: " . ($fields['phone'] ?? '') . "\n"
            . "E-mail: " . $email . "\n"
            . "Empresa: " . $company . "\n"
            . "Observacoes: " . $message . "\n\n"
            . 'Se estiver tudo certo, responda "sim". Se preferir nao enviar agora, responda "nao". Se quiser corrigir algo, responda por exemplo "alterar telefone" ou "corrigir e-mail".';
    }

    private function detectCorrectionField(string $message): ?string
    {
        return match (true) {
            preg_match('/(alterar|corrigir).*(produto|aplicacao|aplicação|filtro)/u', $message) === 1 => 'product_interest',
            preg_match('/(alterar|corrigir).*(cidade|estado)/u', $message) === 1 => 'city_state',
            preg_match('/(alterar|corrigir).*(nome)/u', $message) === 1 => 'name',
            preg_match('/(alterar|corrigir).*(telefone|celular|whatsapp)/u', $message) === 1 => 'phone',
            preg_match('/(alterar|corrigir).*(e-mail|email)/u', $message) === 1 => 'email',
            preg_match('/(alterar|corrigir).*(empresa)/u', $message) === 1 => 'company',
            preg_match('/(alterar|corrigir).*(observacao|observação|mensagem)/u', $message) === 1 => 'message',
            default => null,
        };
    }

    private function leadFlowActions(?string $field, bool $awaitingConfirmation): array
    {
        if ($awaitingConfirmation) {
            return [
                ['label' => 'Confirmar envio', 'value' => 'sim'],
                ['label' => 'Corrigir produto', 'value' => 'alterar produto'],
                ['label' => 'Corrigir cidade', 'value' => 'alterar cidade'],
                ['label' => 'Corrigir nome', 'value' => 'alterar nome'],
                ['label' => 'Corrigir telefone', 'value' => 'alterar telefone'],
                ['label' => 'Corrigir e-mail', 'value' => 'corrigir e-mail'],
                ['label' => 'Corrigir empresa', 'value' => 'alterar empresa'],
                ['label' => 'Cancelar', 'value' => 'nao'],
            ];
        }

        return match ($field) {
            'city_state' => [
                ['label' => 'Barueri - SP', 'value' => 'Barueri - SP'],
                ['label' => 'Sao Paulo - SP', 'value' => 'Sao Paulo - SP'],
                ['label' => 'Outra cidade/estado', 'value' => 'Vou digitar minha cidade e estado'],
            ],
            'email' => [
                ['label' => 'Pular e-mail', 'value' => 'pular'],
            ],
            'company' => [
                ['label' => 'Pular empresa', 'value' => 'pular'],
            ],
            'message' => [
                ['label' => 'Sem observacoes', 'value' => 'pular'],
            ],
            default => [],
        };
    }

    private function shouldInterruptLeadFlow(string $message, string $intent): bool
    {
        if (in_array($intent, ['contato', 'handoff', 'qualidade', 'lancamentos'], true)) {
            return true;
        }

        if ($intent === 'produto' && preg_match('/(onde fica|endereco|endereço|telefone|comercial|qualidade|lancamento|lançamento)/iu', $message) === 1) {
            return true;
        }

        return false;
    }

    private function buildProductCards(array $knowledge): array
    {
        $items = array_slice($knowledge['products'] ?? [], 0, 3);
        $cards = [];

        foreach ($items as $item) {
            $cards[] = [
                'title' => $item['product_name'] ?? '',
                'code' => $item['product_code'] ?? '',
                'category' => $item['category'] ?? '',
                'summary' => $item['application_summary'] ?? '',
                'details_url' => $item['product_url'] ?? '',
                'status' => $item['status_label'] ?? '',
            ];
        }

        return array_values(array_filter($cards, fn($card) => $card['title'] !== ''));
    }
}
