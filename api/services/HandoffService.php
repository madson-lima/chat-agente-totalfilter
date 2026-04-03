<?php

declare(strict_types=1);

final class HandoffService
{
    public function __construct(private HandoffRepository $handoffRepository, private ChatRepository $chatRepository)
    {
    }

    public function create(array $session, array $payload): array
    {
        $handoffId = $this->handoffRepository->create([
            'session_id' => (int) $session['id'],
            'name' => cleanText($payload['name'] ?? '', 120),
            'phone' => cleanPhone($payload['phone'] ?? ''),
            'email' => cleanEmail($payload['email'] ?? ''),
            'reason' => cleanText($payload['reason'] ?? 'Solicitação de atendimento humano', 500),
            'preferred_channel' => cleanText($payload['preferred_channel'] ?? 'telefone', 50),
            'status' => 'pending',
        ]);

        $this->chatRepository->updateSession((int) $session['id'], ['status' => 'handoff_requested']);

        return [
            'handoff_id' => $handoffId,
            'message' => 'Solicitação enviada. A equipe Totalfilter poderá seguir com o atendimento humano pelos canais informados.',
        ];
    }
}
