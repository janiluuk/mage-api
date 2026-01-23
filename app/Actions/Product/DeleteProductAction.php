<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Exceptions\Product\ProductNotFoundException;
use App\Repositories\Product\ProductRepositoryInterface;

final class DeleteProductAction
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(DeleteProductRequest $request): void
    {
        $product = $this->productRepository->getById($request->getId());

        if (!$product) {
            throw new ProductNotFoundException();
        }

        // Store category ID before deletion
        $categoryId = $product->category_id;

        $this->productRepository->delete($product);

        // Clear product cache for this category to reflect deleted product
        GetProductsByCategoryAction::clearCategoryCache($categoryId);
    }
}
