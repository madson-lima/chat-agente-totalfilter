<?php

declare(strict_types=1);

final class ProductSpreadsheetNormalizer
{
    private const HEADER_ALIASES = [
        'marcaOrigem' => ['marca', 'linha', 'fabricante', 'origem'],
        'codigoOriginal' => ['codigo', 'código', 'cod', 'cód', 'referencia', 'referência', 'unifilter', 'codigo original', 'código original'],
        'descricao' => ['descricao', 'descrição', 'produto', 'nome', 'item'],
        'desenhoCodigo' => ['desenho', 'desenho codigo', 'desenho código', 'codigo desenho', 'código desenho'],
        'aplicacao' => ['aplicacao', 'aplicação', 'aplicacoes', 'aplicações', 'aplicaçao', 'veiculo', 'veículo', 'equipamento'],
        'medidas' => ['medida', 'medidas', 'dimensao', 'dimensão', 'dimensoes', 'dimensões', 'altura', 'externo', 'interno'],
    ];

    public function gerarCodigoTotalfilter(string $codigoOriginal): string
    {
        $codigo = strtoupper($this->normalizeCode($codigoOriginal));
        if ($codigo === '') {
            return '';
        }

        return str_starts_with($codigo, 'T') ? $codigo : 'T' . $codigo;
    }

    public function normalizeRow(array $row, array $headerMap, int $rowNumber): ?array
    {
        $raw = [];
        foreach ($headerMap as $columnIndex => $header) {
            $value = $this->normalizeText((string) ($row[$columnIndex] ?? ''));
            if ($value !== '') {
                $raw[$header] = $value;
            }
        }

        if ($raw === [] || $this->looksLikeRepeatedHeader($raw)) {
            return null;
        }

        $codigoOriginal = $this->normalizeCode($this->valueFor('codigoOriginal', $raw));
        if ($codigoOriginal === '') {
            return null;
        }

        $codigoTotalfilter = $this->gerarCodigoTotalfilter($codigoOriginal);
        $marcaOrigem = $this->valueFor('marcaOrigem', $raw);
        $descricao = $this->valueFor('descricao', $raw);
        $desenhoCodigo = $this->valueFor('desenhoCodigo', $raw);
        $aplicacao = $this->valueFor('aplicacao', $raw);
        $medidas = $this->buildMedidas($raw);

        return [
            'marcaOrigem' => $marcaOrigem,
            'codigoOriginal' => $codigoOriginal,
            'codigoTotalfilter' => $codigoTotalfilter,
            'descricao' => $descricao,
            'desenhoCodigo' => $desenhoCodigo,
            'aplicacao' => $aplicacao,
            'medidas' => $medidas,
            'dadosBrutosDaLinha' => $raw + ['_linha_planilha' => $rowNumber],
            'searchableText' => $this->buildSearchableText([
                $codigoOriginal,
                $codigoTotalfilter,
                $descricao,
                $aplicacao,
                $desenhoCodigo,
                $marcaOrigem,
                $medidas,
            ]),
            'fonte' => 'REGISTRO DE PRODUTOS ACABADOS.xlsm:BASE DE DADOS',
            'ativo' => true,
        ];
    }

    public function detectHeaderMap(array $rows): array
    {
        $best = ['score' => 0, 'row_index' => null, 'map' => []];

        foreach ($rows as $rowIndex => $row) {
            $map = [];
            $score = 0;

            foreach ($row as $columnIndex => $value) {
                $header = $this->normalizeHeader((string) $value);
                if ($header === '') {
                    continue;
                }

                $map[$columnIndex] = $header;
                foreach (self::HEADER_ALIASES as $aliases) {
                    if ($this->matchesAnyAlias($header, $aliases)) {
                        $score++;
                        break;
                    }
                }
            }

            if ($score > $best['score']) {
                $best = ['score' => $score, 'row_index' => $rowIndex, 'map' => $map];
            }
        }

        if ($best['score'] < 2 || $best['row_index'] === null) {
            throw new RuntimeException('Nao foi possivel identificar o cabecalho da aba BASE DE DADOS.');
        }

        return $best;
    }

    private function valueFor(string $field, array $raw): string
    {
        foreach ($raw as $header => $value) {
            if ($this->matchesAnyAlias($this->normalizeHeader($header), self::HEADER_ALIASES[$field] ?? [])) {
                return $value;
            }
        }

        return '';
    }

    private function buildMedidas(array $raw): string
    {
        $values = [];
        foreach ($raw as $header => $value) {
            $normalized = $this->normalizeHeader($header);
            if ($this->matchesAnyAlias($normalized, self::HEADER_ALIASES['medidas']) || preg_match('/(altura|externo|interno|diametro|diâmetro|rosca|comprimento|largura)/u', $normalized) === 1) {
                $values[] = $header . ': ' . $value;
            }
        }

        return $this->normalizeText(implode(' | ', array_unique($values)));
    }

    private function buildSearchableText(array $parts): string
    {
        return strtoupper($this->normalizeText(implode(' ', array_filter($parts, static fn($part): bool => trim((string) $part) !== ''))));
    }

    private function looksLikeRepeatedHeader(array $raw): bool
    {
        $joined = mb_strtolower(implode(' ', array_values($raw)));
        return str_contains($joined, 'codigo') && (str_contains($joined, 'descricao') || str_contains($joined, 'aplicacao'));
    }

    private function matchesAnyAlias(string $header, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeHeader($alias);
            if ($header === $normalizedAlias || str_contains($header, $normalizedAlias) || str_contains($normalizedAlias, $header)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        return trim($value);
    }

    private function normalizeCode(string $value): string
    {
        $value = $this->normalizeText($value);
        $value = preg_replace('/[^A-Za-z0-9.-]/', '', $value) ?? '';
        return strtoupper(trim($value));
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtolower($this->normalizeText($value));
    }
}
