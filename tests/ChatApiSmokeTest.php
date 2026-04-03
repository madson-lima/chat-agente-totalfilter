<?php

declare(strict_types=1);

$baseUrl = $argv[1] ?? 'http://localhost';

function request(string $method, string $url, array $payload = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
    ]);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, json_decode((string) $response, true)];
}

[$status] = request('GET', $baseUrl . '/api/health');
echo "Health: {$status}\n";

[$status, $start] = request('POST', $baseUrl . '/api/chat/start', [
    'visitor_id' => 'smoke-test',
    'page_url' => $baseUrl,
]);
echo "Start: {$status}\n";

$token = $start['session_token'] ?? '';
if ($token === '') {
    exit("Sessão não iniciada.\n");
}

[$status, $message] = request('POST', $baseUrl . '/api/chat/message', [
    'session_token' => $token,
    'message' => 'Como faço para falar com o comercial?',
]);

echo "Message: {$status}\n";
echo ($message['message'] ?? 'sem resposta') . "\n";
