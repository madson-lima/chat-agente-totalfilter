<?php

declare(strict_types=1);

final class ProductSearchService
{
    public function __construct(private ProductRepository $productRepository)
    {
    }

    public function buscarPorCodigo(string $codigo): array
    {
        return $this->productRepository->findByCodigo($codigo);
    }

    public function buscarPorAplicacao(string $texto): array
    {
        return $this->productRepository->searchByField('aplicacao', $texto);
    }

    public function buscarPorDescricao(string $texto): array
    {
        return $this->productRepository->searchByField('descricao', $texto);
    }

    public function buscarPorTermoLivre(string $texto): array
    {
        return $this->productRepository->search($texto, 8);
    }
}
