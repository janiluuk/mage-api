<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\Product\Criterion\IsActiveCriterion;
use App\Repositories\Product\Criterion\CategoryIdCriterion;
use App\Repositories\Product\Criterion\ProductNameCriterion;
use App\Repositories\Product\Criterion\PropertyValueCriterion;
use App\Repositories\Product\Criterion\PropertyValueIdCriterion;
use Illuminate\Support\Facades\Cache;

final class GetProductsByCategoryAction
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(GetProductsByCategoryRequest $request): GetProductsByCategoryResponse
    {
        $criteria[] = new CategoryIdCriterion($request->getCategoryId());

        $criteria[] = new IsActiveCriterion();

        if ($request->getName()) {
            $criteria[] = new ProductNameCriterion($request->getName());
        }

        if ($request->getProperties()) {
            foreach ($request->getProperties() as $property) {
                if (isset($property['valueId'])) {
                    $criteria[] = new PropertyValueIdCriterion($property['propertyId'], $property['valueId']);
                } elseif (isset($property['value'])) {
                    $criteria[] = new PropertyValueCriterion($property['propertyId'], $property['value']);
                }
            }
        }

        // Generate cache key based on criteria and sorting
        $cacheKey = $this->generateCacheKey(
            $request->getCategoryId(),
            $request->getName(),
            $request->getProperties(),
            $request->getOrderType() ?: $this->productRepository::DEFAULT_ORDER_TYPE,
            $request->getOrderDirection() ?: $this->productRepository::DEFAULT_ORDER_DIRECTION
        );

        // Cache products by category for 30 minutes (1800 seconds)
        // Cache invalidation handled in Add/Update/Delete actions
        $products = Cache::remember(
            $cacheKey,
            1800,
            function () use ($criteria, $request) {
                return $this->productRepository->findByCriteria(
                    $criteria,
                    $request->getOrderType() ?: $this->productRepository::DEFAULT_ORDER_TYPE,
                    $request->getOrderDirection() ?: $this->productRepository::DEFAULT_ORDER_DIRECTION
                );
            }
        );

        return new GetProductsByCategoryResponse($products);
    }

    /**
     * Generate cache key for product listings by category
     */
    private function generateCacheKey(
        int $categoryId,
        ?string $name,
        ?array $properties,
        string $orderType,
        string $orderDirection
    ): string {
        // Get cache version for this category to enable easy invalidation
        $cacheVersion = Cache::get("products_category_version_{$categoryId}", 1);
        
        $keyParts = [
            'products_by_category',
            $categoryId,
            $cacheVersion,
            $name ? md5($name) : 'no_name',
            $properties ? md5(json_encode($properties)) : 'no_props',
            $orderType,
            $orderDirection,
        ];

        return implode('_', $keyParts);
    }

    /**
     * Clear cache for a specific category by incrementing version
     * This is called by Add/Update/Delete actions to invalidate cache
     */
    public static function clearCategoryCache(int $categoryId): void
    {
        // Increment cache version to invalidate all existing cache entries for this category
        $currentVersion = Cache::get("products_category_version_{$categoryId}", 1);
        Cache::forever("products_category_version_{$categoryId}", $currentVersion + 1);
    }
}
