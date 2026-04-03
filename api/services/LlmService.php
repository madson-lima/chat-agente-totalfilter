<?php

declare(strict_types=1);

final class LlmService
{
    public function __construct(private array $config, private Logger $logger)
    {
    }

    public function generate(string $systemPrompt, array $messages): ?string
    {
        $apiKey = (string) ($this->config['llm']['api_key'] ?? '');
        $apiUrl = (string) ($this->config['llm']['api_url'] ?? '');

        if ($apiKey === '' || $apiUrl === '') {
            return null;
        }

        $payload = [
            'model' => $this->config['llm']['model'] ?? 'gpt-4.1-mini',
            'temperature' => (float) ($this->config['llm']['temperature'] ?? 0.4),
            'messages' => array_merge([
                ['role' => 'system', 'content' => $systemPrompt],
            ], $messages),
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => (int) ($this->config['llm']['timeout'] ?? 25),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $error !== '' || $httpCode >= 400) {
            $this->logger->error('Falha na chamada LLM', ['error' => $error, 'status' => $httpCode, 'body' => $result]);
            return null;
        }

        $decoded = json_decode($result, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;

        return is_string($content) ? trim($content) : null;
    }
}
