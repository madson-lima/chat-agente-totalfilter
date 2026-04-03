<?php

declare(strict_types=1);

final class KnowledgeService
{
    public function __construct(
        private FaqRepository $faqRepository,
        private KnowledgeRepository $knowledgeRepository,
        private ProductRepository $productRepository
    ) {
    }

    public function buildContext(string $query): array
    {
        $normalized = cleanText($query, 400);
        $faq = $this->faqRepository->search($normalized);
        $knowledge = $this->knowledgeRepository->search($normalized);
        $codeTerm = $this->extractProductCode($normalized);
        $products = [];
        if ($codeTerm !== '') {
            $products = $this->productRepository->exactMatch($codeTerm);
        }
        if (empty($products)) {
            $products = $this->productRepository->search($normalized);
        }
        $launches = str_contains(mb_strtolower($normalized), 'lanç') || str_contains(mb_strtolower($normalized), 'novidade')
            ? $this->productRepository->latestLaunches()
            : [];

        return [
            'faq' => $faq,
            'knowledge' => $knowledge,
            'products' => $products,
            'launches' => $launches,
        ];
    }

    public function topAnswer(array $knowledge): ?string
    {
        if (!empty($knowledge['faq'][0])) {
            return $knowledge['faq'][0]['answer'];
        }
        if (!empty($knowledge['knowledge'][0])) {
            return $knowledge['knowledge'][0]['excerpt'] ?: mb_substr($knowledge['knowledge'][0]['content'], 0, 320);
        }
        if (!empty($knowledge['products'][0])) {
            $product = $knowledge['products'][0];
            return sprintf(
                '%s é um item da linha %s. %s',
                $product['product_name'],
                $product['category'] ?: 'Totalfilter',
                $product['application_summary']
            );
        }
        return null;
    }

    public function formatContext(array $knowledge): string
    {
        $lines = [];

        foreach (['faq' => 'FAQ', 'knowledge' => 'Base institucional', 'products' => 'Produtos', 'launches' => 'Lançamentos'] as $key => $label) {
            if (empty($knowledge[$key])) {
                continue;
            }

            $lines[] = $label . ':';
            foreach ($knowledge[$key] as $item) {
                if ($key === 'faq') {
                    $lines[] = '- P: ' . $item['question'] . ' | R: ' . $item['answer'];
                    continue;
                }
                if ($key === 'products' || $key === 'launches') {
                    $lines[] = '- ' . $item['product_name'] . ' (' . ($item['product_code'] ?: 'sem código') . '): ' . $item['application_summary'];
                    continue;
                }

                $lines[] = '- ' . $item['title'] . ': ' . ($item['excerpt'] ?: mb_substr($item['content'], 0, 220));
            }
        }

        return implode("\n", $lines);
    }

    private function extractProductCode(string $query): string
    {
        $tokens = preg_split('/[^A-Za-z0-9]+/', $query) ?: [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (strlen($token) < 5) {
                continue;
            }
            if (preg_match('/[A-Za-z]/', $token) === 1 && preg_match('/\d/', $token) === 1) {
                return strtoupper($token);
            }
        }

        return '';
    }
}
