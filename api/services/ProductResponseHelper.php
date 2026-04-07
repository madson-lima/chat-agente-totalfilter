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
        $codigoTotalfilter = (string) ($product['codigoTotalfilter'] ?? $product['product_code'] ?? $codigo);
        $codigoOriginal = (string) ($product['codigoOriginal'] ?? '');
        $descricao = (string) ($product['descricao'] ?? $product['product_name'] ?? '');
        $aplicacao = (string) ($product['aplicacao'] ?? $product['application_summary'] ?? '');
        $desenho = (string) ($product['desenhoCodigo'] ?? '');

        $parts = ["Sim. Encontrei na linha Totalfilter o codigo {$codigoTotalfilter}."];
        if ($codigoOriginal !== '' && $codigoOriginal !== $codigoTotalfilter) {
            $parts[] = "Codigo original/equivalente: {$codigoOriginal}.";
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
        $codigoTotalfilter = (string) ($product['codigoTotalfilter'] ?? $product['product_code'] ?? '');
        $codigoOriginal = (string) ($product['codigoOriginal'] ?? $codigo);

        if ($codigoTotalfilter === '') {
            return 'Encontrei o produto, mas nao ha codigo Totalfilter cadastrado para ele na base importada.';
        }

        if ($codigoOriginal !== '' && $codigoOriginal !== $codigoTotalfilter) {
            return "O codigo Totalfilter e {$codigoTotalfilter}. Ele corresponde ao codigo original/equivalente {$codigoOriginal}.";
        }

        return "O codigo Totalfilter e {$codigoTotalfilter}.";
    }
}
