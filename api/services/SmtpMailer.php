<?php

declare(strict_types=1);

final class SmtpMailer
{
    public function __construct(private array $config, private Logger $logger)
    {
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $mailConfig = $this->config['mail'] ?? [];
        $host = (string) ($mailConfig['host'] ?? '');
        $port = (int) ($mailConfig['port'] ?? 587);
        $username = (string) ($mailConfig['username'] ?? '');
        $password = (string) ($mailConfig['password'] ?? '');
        $encryption = strtolower((string) ($mailConfig['encryption'] ?? 'tls'));
        $fromEmail = (string) ($mailConfig['from_email'] ?? 'no-reply@totalfilter.local');
        $fromName = (string) ($mailConfig['from_name'] ?? 'Assistente Totalfilter');

        if ($host === '' || $username === '' || $password === '') {
            return false;
        }

        $remoteHost = $encryption === 'ssl' ? "ssl://{$host}" : $host;
        $socket = @stream_socket_client("{$remoteHost}:{$port}", $errno, $errstr, 20);

        if (!$socket) {
            $this->logger->error('Falha ao conectar no SMTP', ['host' => $host, 'port' => $port, 'error' => $errstr]);
            return false;
        }

        stream_set_timeout($socket, 20);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO localhost', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Nao foi possivel iniciar TLS no SMTP.');
                }
                $this->command($socket, 'EHLO localhost', [250]);
            }

            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $headers = [
                'From: ' . $fromName . ' <' . $fromEmail . '>',
                'To: <' . $to . '>',
                'Subject: ' . $subject,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
            $this->command($socket, $message, [250]);
            $this->command($socket, 'QUIT', [221]);
            fclose($socket);
            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Falha no envio SMTP', ['error' => $exception->getMessage(), 'to' => $to]);
            fclose($socket);
            return false;
        }
    }

    private function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCodes);
    }

    private function expect($socket, array $expectedCodes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3} /', $line) === 1) {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException('Resposta SMTP inesperada: ' . trim($response));
        }

        return $response;
    }
}
