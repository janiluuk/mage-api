<?php

namespace App\Repositories\Category;

use App\Models\Category;
use Illuminate\Support\Collection;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Cache;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function getAll(): Collection
    {
        // Cache categories for 1 hour as they rarely change
        return Cache::remember('categories_all', 3600, function () {
            return Category::all();
        });
    }

    public function getById(int $id): ?Category
    {
        return Category::firstWhere('id', $id);
    }

    public function findByCriteria(
        array $criteria
    ): Collection {
        $query = Category::query();

        foreach ($criteria as $criterion) {
            $query = $criterion->apply($query);
        }

        return $query->get();
    }
}
