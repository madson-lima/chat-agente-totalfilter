<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireAdmin();

if (!verifyCsrf($_GET['_csrf_token'] ?? null)) {
    http_response_code(419);
    exit('CSRF token invalido.');
}

$leadRepository = new LeadRepository(database());
$leads = $leadRepository->all(1000);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="totalfilter-leads.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['id', 'nome', 'telefone', 'email', 'empresa', 'cidade_estado', 'produto_interesse', 'mensagem', 'status', 'criado_em'], ';');

foreach ($leads as $lead) {
    fputcsv($output, [
        $lead['id'],
        $lead['name'],
        $lead['phone'],
        $lead['email'],
        $lead['company'],
        $lead['city_state'],
        $lead['product_interest'],
        $lead['message'],
        $lead['status'],
        $lead['created_at'],
    ], ';');
}

fclose($output);
exit;
