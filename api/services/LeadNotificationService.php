<?php

declare(strict_types=1);

final class LeadNotificationService
{
    public function __construct(private array $config, private Logger $logger)
    {
    }

    public function send(array $leadData): bool
    {
        $to = trim((string) ($this->config['lead_notification_email'] ?? ''));
        if ($to === '') {
            return false;
        }

        $subject = 'Novo lead recebido pelo assistente Totalfilter';
        $body = $this->buildBody($leadData);
        $mailer = strtolower((string) (($this->config['mail']['mailer'] ?? 'mail')));

        if ($mailer === 'smtp') {
            $smtp = new SmtpMailer($this->config, $this->logger);
            $sent = $smtp->send($to, $subject, $body);
            if ($sent) {
                return true;
            }
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: ' . (($this->config['mail']['from_email'] ?? 'no-reply@totalfilter.local')),
        ];

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));

        if (!$sent) {
            $this->logger->error('Falha ao enviar notificacao de lead por email', ['to' => $to, 'lead' => $leadData]);
        } else {
            $this->logger->info('Notificacao de lead enviada por email', ['to' => $to, 'lead_email' => $leadData['email'] ?? '']);
        }

        return $sent;
    }

    private function buildBody(array $leadData): string
    {
        return implode("\n", [
            'Novo lead recebido pelo assistente da Totalfilter',
            '',
            'Nome: ' . ($leadData['name'] ?? ''),
            'Telefone: ' . ($leadData['phone'] ?? ''),
            'E-mail: ' . ($leadData['email'] ?? ''),
            'Empresa: ' . ($leadData['company'] ?? ''),
            'Cidade/Estado: ' . ($leadData['city_state'] ?? ''),
            'Produto/Interesse: ' . ($leadData['product_interest'] ?? ''),
            'Mensagem: ' . ($leadData['message'] ?? ''),
            'Origem: ' . ($leadData['source'] ?? 'chat-widget'),
            'Enviado em: ' . date('Y-m-d H:i:s'),
        ]);
    }
}
