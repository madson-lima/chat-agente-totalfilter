<?php

declare(strict_types=1);

final class IntentService
{
    public function detect(string $message, array $context = []): array
    {
        $normalized = $this->normalize($message);
        $lastTopic = $this->normalize((string) ($context['last_topic'] ?? ''));

        return match (true) {
            $this->isGreeting($normalized) => $this->result('saudacao', 0.98, null, ['greeting' => $this->greetingLabel($normalized)]),
            $this->matches('/\b(ficha tecnica|ficha|dados tecnicos|especificacao|especificacoes)\b/', $normalized) => $this->result('pedido_ficha_tecnica', 0.90),
            $this->matches('/\b(atendimento humano|suporte humano|falar com uma pessoa|falar com pessoa|falar com atendente|quero um atendente|preciso de um atendente|humano)\b/', $normalized) => $this->result('atendimento_humano', 0.96, $this->humanAction('atendimento')),
            $this->matches('/\b(falar com comercial|equipe comercial|setor comercial|atendimento comercial|vendedor|vendedora|vendas|comercial)\b/', $normalized) => $this->result('comercial', 0.95, $this->humanAction('comercial')),
            $this->matches('/\b(encaminh(e|a|ar|e)?|direcion(a|ar)|manda(r)?|passa(r)?)\b.*\b(equipe|atendimento|suporte|humano|pessoa|atendente)\b/', $normalized)
                || $this->matches('/\b(quero|preciso|pode|posso|gostaria).*\b(falar|conversar).*\b(equipe|pessoa|atendente|suporte)\b/', $normalized) => $this->result('encaminhamento_equipe', 0.96, $this->humanAction('atendimento')),
            $this->matches('/\b(telefone|whatsapp|email|e-mail|contato|endereco|localizacao|onde fica)\b/', $normalized) => $this->result('contato', 0.88),
            $this->matches('/\b(orcamento|cotacao|comprar|preco|valor|quanto custa|quanto sai|custa)\b/', $normalized) => $this->result('orcamento', 0.92),
            $this->matches('/\b(nao sei o codigo|nao tenho codigo|sem codigo|nao sei a referencia|preciso de ajuda|me ajuda)\b/', $normalized) => $this->result('fallback_ajudado', 0.86),
            $this->extractCode($message) !== '' => $this->result('busca_codigo', 0.87),
            $this->matches('/\b(aplicacao|aplicacao|equipamento|veiculo|motor|fiat|clark|allis|industrial|combustivel|hidraulico|ar|oleo|cabine)\b/', $normalized) => $this->result('busca_aplicacao', 0.80),
            in_array($lastTopic, ['produto', 'busca_codigo', 'busca_aplicacao'], true) && $this->matches('/\b(esse filtro|esse produto|esse codigo|este filtro|este produto)\b/', $normalized) => $this->result('referencia_contextual', 0.75),
            default => $this->result('fallback', 0.50),
        };
    }

    public function responseFor(array $intent, array $context = []): ?array
    {
        $name = (string) ($intent['intent'] ?? 'fallback');
        $action = $intent['action'] ?? null;

        return match ($name) {
            'saudacao' => [
                'answer' => $this->greetingResponse((string) ($intent['greeting'] ?? '')),
                'intent' => 'saudacao',
                'source' => 'intent-rule',
                'action' => null,
                'context_actions' => [],
            ],
            'encaminhamento_equipe' => [
                'answer' => 'Claro! Posso te encaminhar para nossa equipe de atendimento. Quer que eu abra o WhatsApp da Totalfilter?',
                'intent' => 'encaminhamento_equipe',
                'source' => 'intent-rule',
                'action' => $action,
                'context_actions' => $this->handoffActions(),
            ],
            'atendimento_humano' => [
                'answer' => 'Sem problema. Vou direcionar seu atendimento para nossa equipe. Quer que eu abra o WhatsApp da Totalfilter?',
                'intent' => 'atendimento_humano',
                'source' => 'intent-rule',
                'action' => $action,
                'context_actions' => $this->handoffActions(),
            ],
            'comercial' => [
                'answer' => 'Perfeito. Vou direcionar voce para nossa equipe comercial. Quer que eu abra o WhatsApp da Totalfilter?',
                'intent' => 'comercial',
                'source' => 'intent-rule',
                'action' => $action,
                'context_actions' => $this->handoffActions(),
            ],
            'orcamento' => [
                'answer' => 'Claro! Me informe o codigo ou a aplicacao do filtro para eu te ajudar com o orcamento.',
                'intent' => 'orcamento',
                'source' => 'intent-rule',
                'action' => null,
                'context_actions' => [
                    ['label' => 'Tenho o codigo', 'value' => 'Tenho o codigo do filtro'],
                    ['label' => 'Nao sei o codigo', 'value' => 'Nao sei o codigo'],
                    ['label' => 'Falar com comercial', 'value' => 'falar com comercial'],
                ],
            ],
            'fallback_ajudado' => [
                'answer' => 'Sem problema. Me informe o equipamento, a aplicacao ou o tipo de filtro que eu tento localizar para voce.',
                'intent' => 'fallback_ajudado',
                'source' => 'intent-rule',
                'action' => null,
                'context_actions' => [],
            ],
            default => null,
        };
    }

    public function normalize(string $message): string
    {
        $text = trim(mb_strtolower($message));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }

        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        $text = preg_replace('/[^\p{L}\p{N}\s.-]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    public function extractCode(string $message): string
    {
        $tokens = preg_split('/[^A-Za-z0-9.-]+/', $message) ?: [];
        foreach ($tokens as $token) {
            $token = strtoupper(trim($token));
            if (strlen($token) >= 5 && preg_match('/\d/', $token) === 1) {
                return $token;
            }
        }

        return '';
    }

    private function result(string $intent, float $confidence, ?array $action = null, array $extra = []): array
    {
        return array_merge([
            'intent' => $intent,
            'confidence' => $confidence,
            'action' => $action,
        ], $extra);
    }

    private function humanAction(string $target): array
    {
        return [
            'type' => 'human_handoff',
            'target' => $target,
        ];
    }

    private function handoffActions(): array
    {
        return [
            ['label' => 'Abrir WhatsApp', 'value' => 'sim'],
            ['label' => 'Continuar no chat', 'value' => 'nao'],
        ];
    }

    private function isGreeting(string $message): bool
    {
        return preg_match('/^(bom dia|boa tarde|boa noite|oi|ola|opa|e ai|eae|hello|bomdia|boatarde|boanoite)( tudo bem)?$/', $message) === 1;
    }

    private function matches(string $pattern, string $message): bool
    {
        return preg_match($pattern, $message) === 1;
    }

    private function greetingLabel(string $message): string
    {
        if (str_starts_with($message, 'bom dia') || str_starts_with($message, 'bomdia')) {
            return 'bom_dia';
        }
        if (str_starts_with($message, 'boa tarde') || str_starts_with($message, 'boatarde')) {
            return 'boa_tarde';
        }
        if (str_starts_with($message, 'boa noite') || str_starts_with($message, 'boanoite')) {
            return 'boa_noite';
        }
        if (str_starts_with($message, 'oi')) {
            return 'oi';
        }

        return 'ola';
    }

    private function greetingResponse(string $greeting): string
    {
        return match ($greeting) {
            'bom_dia' => 'Bom dia! Como posso te ajudar hoje?',
            'boa_tarde' => 'Boa tarde! Como posso te ajudar hoje?',
            'boa_noite' => 'Boa noite! Como posso te ajudar?',
            'oi' => 'Oi! Posso te ajudar a encontrar um filtro, aplicacao ou orcamento.',
            default => 'Ola! Como posso te ajudar?',
        };
    }
}
