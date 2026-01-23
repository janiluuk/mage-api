<?php

declare(strict_types=1);

namespace App\Actions\Product;

use Illuminate\Support\Facades\Auth;
use App\Repositories\Product\Criterion\UserIdCriterion;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\Product\Criterion\CategoryIdCriterion;

/**
 * Action to retrieve products for the authenticated user.
 *
 * This action fetches products belonging to the currently authenticated user,
 * optionally filtered by category, with support for custom ordering.
 *
 * @category  App
 * @package   App\Actions\Product
 * @license   https://opensource.org/licenses/MIT MIT License
 */
final class GetProductsByUserAction
{
    private ProductRepositoryInterface $productRepositoryInterface;

    public function __construct(
        ProductRepositoryInterface $productRepositoryInterface
    ) {
        $this->productRepositoryInterface = $productRepositoryInterface;
    }

    public function execute(GetProductsByUserRequest $request): GetProductsByUserResponse
    {
        $criteria = [];
        $criteria[] = new UserIdCriterion(Auth::id());

        if ($request->getCategoryId()) {
            $criteria[] = new CategoryIdCriterion($request->getCategoryId());
        }

        $products = $this->productRepositoryInterface->findByCriteria(
            $criteria,
            $request->getOrderType() ?: $this->productRepositoryInterface::DEFAULT_ORDER_TYPE,
            $request->getOrderDirection() ?: $this->productRepositoryInterface::DEFAULT_ORDER_DIRECTION
        );

        return new GetProductsByUserResponse($products);
    }
}
