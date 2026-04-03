<?php

declare(strict_types=1);

final class ContextService
{
    public function __construct(private ChatRepository $chatRepository, private array $config)
    {
    }

    public function build(array $session): array
    {
        $messages = $this->chatRepository->recentMessages((int) $session['id'], (int) ($this->config['max_context_messages'] ?? 10));
        return [
            'summary' => $session['summary'] ?? '',
            'messages' => $messages,
            'last_topic' => $session['last_topic'] ?? '',
            'last_user_message' => $session['last_user_message'] ?? '',
            'last_assistant_message' => $session['last_assistant_message'] ?? '',
        ];
    }

    public function maybeUpdateSummary(array $session): void
    {
        $count = (int) ($session['message_count'] ?? 0);
        $trigger = max(4, (int) ($this->config['summary_trigger_count'] ?? 8));

        if ($count === 0 || $count % $trigger !== 0) {
            return;
        }

        $messages = $this->chatRepository->recentMessages((int) $session['id'], $trigger);
        $fragments = [];
        foreach ($messages as $message) {
            $fragments[] = strtoupper($message['role']) . ': ' . $message['content'];
        }

        $summary = cleanText(implode(' | ', $fragments), (int) ($this->config['summary_max_chars'] ?? 1400));
        $topic = $this->extractTopic($messages);

        $this->chatRepository->updateSession((int) $session['id'], [
            'summary' => $summary,
            'last_topic' => $topic,
        ]);
    }

    public function extractTopic(array $messages): string
    {
        $joined = mb_strtolower(implode(' ', array_column($messages, 'content')));
        $map = [
            'orçamento' => ['orçamento', 'cotação', 'preço'],
            'produto' => ['filtro', 'produto', 'aplicação'],
            'qualidade' => ['qualidade', 'durabilidade', 'original'],
            'contato' => ['telefone', 'email', 'endereço', 'localização'],
            'atendimento humano' => ['atendente', 'humano', 'comercial'],
        ];

        foreach ($map as $topic => $terms) {
            foreach ($terms as $term) {
                if (str_contains($joined, $term)) {
                    return $topic;
                }
            }
        }

        return 'atendimento geral';
    }
}
