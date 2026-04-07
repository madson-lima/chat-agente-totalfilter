<?php

declare(strict_types=1);

final class KnowledgeController
{
    private FaqRepository $faqRepository;
    private KnowledgeRepository $knowledgeRepository;
    private ProductRepository $productRepository;

    public function __construct(mixed $pdo, private array $config)
    {
        $this->faqRepository = new FaqRepository($pdo);
        $this->knowledgeRepository = new KnowledgeRepository($pdo);
        $this->productRepository = new ProductRepository($pdo);
    }

    public function faq(): void
    {
        jsonResponse(['ok' => true, 'items' => $this->faqRepository->all()]);
    }

    public function knowledge(): void
    {
        jsonResponse(['ok' => true, 'items' => $this->knowledgeRepository->pages()]);
    }

    public function products(): void
    {
        $query = cleanText((string) ($_GET['q'] ?? ''), 200);
        if ($query !== '') {
            $items = $this->productRepository->findByCodigo($query);
            if (empty($items)) {
                $items = $this->productRepository->search($query, 20);
            }
            jsonResponse(['ok' => true, 'items' => $items]);
        }

        jsonResponse(['ok' => true, 'items' => array_slice($this->productRepository->all(), 0, 100)]);
    }
}
