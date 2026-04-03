<?php

declare(strict_types=1);

final class LeadService
{
    public function __construct(
        private LeadRepository $leadRepository,
        private ChatRepository $chatRepository,
        private ?LeadNotificationService $notificationService = null
    ) {
    }

    public function store(array $session, array $payload): array
    {
        $leadPayload = [
            'session_id' => (int) $session['id'],
            'name' => cleanText($payload['name'] ?? '', 120),
            'phone' => cleanPhone($payload['phone'] ?? ''),
            'email' => cleanEmail($payload['email'] ?? ''),
            'company' => cleanText($payload['company'] ?? '', 120),
            'city_state' => cleanText($payload['city_state'] ?? '', 120),
            'product_interest' => cleanText($payload['product_interest'] ?? '', 255),
            'message' => cleanText($payload['message'] ?? '', 1000),
            'source' => cleanText($payload['source'] ?? 'chat-widget', 50),
            'status' => 'new',
        ];

        $leadId = $this->leadRepository->create($leadPayload);
        $this->chatRepository->updateSession((int) $session['id'], ['lead_status' => 'captured']);
        $this->notificationService?->send($leadPayload);

        return [
            'lead_id' => $leadId,
            'confirmation' => 'Perfeito. Seus dados foram registrados com sucesso e a equipe Totalfilter podera dar continuidade ao atendimento comercial.',
        ];
    }
}
