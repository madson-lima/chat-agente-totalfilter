<?php

declare(strict_types=1);

final class ProductResponseHelper
{
    public function answerForCode(string $codigo, array $products): string
    {
        if (empty($products)) {
            return 'Nao encontrei um produto correspondente com esse codigo. Posso tentar localizar por aplicacao, descricao ou desenho.';
        }

        $product = $products[0];
        $codigoOriginal = (string) ($product['codigoOriginal'] ?? $product['product_code'] ?? $codigo);
        $codigoTotalfilter = (string) ($product['codigoTotalfilter'] ?? $product['product_code'] ?? '');
        $descricao = (string) ($product['descricao'] ?? $product['product_name'] ?? '');
        $aplicacao = (string) ($product['aplicacao'] ?? $product['application_summary'] ?? '');
        $desenho = (string) ($product['desenhoCodigo'] ?? '');

        $parts = ["Sim. Encontrei o produto {$codigoOriginal}."];
        if ($codigoTotalfilter !== '') {
            $parts[] = "Na linha Totalfilter, o codigo correspondente e {$codigoTotalfilter}.";
        }
        if ($descricao !== '') {
            $parts[] = "Descricao: {$descricao}.";
        }
        if ($aplicacao !== '') {
            $parts[] = "Aplicacao: {$aplicacao}.";
        }
        if ($desenho !== '') {
            $parts[] = "Desenho: {$desenho}.";
        }

        return implode(' ', $parts);
    }

    public function equivalentForCode(string $codigo, array $products): string
    {
        if (empty($products)) {
            return 'Nao encontrei um produto correspondente com esse codigo. Posso tentar localizar por aplicacao, descricao ou desenho.';
        }

        $product = $products[0];
        $codigoOriginal = (string) ($product['codigoOriginal'] ?? $codigo);
        $codigoTotalfilter = (string) ($product['codigoTotalfilter'] ?? $product['product_code'] ?? '');

        if ($codigoTotalfilter === '') {
            return 'Encontrei o produto, mas nao ha codigo Totalfilter cadastrado para ele na base importada.';
        }

        return "O equivalente na linha Totalfilter para o codigo {$codigoOriginal} e {$codigoTotalfilter}.";
    }
}
