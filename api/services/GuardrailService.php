<?php

declare(strict_types=1);

final class GuardrailService
{
    private array $blockedPatterns = [
        '/ignore (all|previous) instructions/i',
        '/revele? (o )?(prompt|instruç|instruc|system)/i',
        '/mostre? (as )?regras internas/i',
        '/act as /i',
        '/prompt injection/i',
        '/bypass/i',
        '/developer message/i',
    ];

    public function inspect(string $message): array
    {
        $clean = cleanText($message, 2000);
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $clean) === 1) {
                return [
                    'allowed' => false,
                    'message' => 'Posso ajudar com informações sobre a Totalfilter, produtos, contato e orçamento. Para temas internos do sistema, sigo um fluxo protegido.',
                ];
            }
        }

        return [
            'allowed' => true,
            'message' => $clean,
        ];
    }
}
