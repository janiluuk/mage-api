<?php

namespace App\Repositories\FilmProduction;

use App\Models\FilmProduction;
use App\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class FilmProductionRepository extends BaseRepository implements FilmProductionRepositoryInterface
{
    public function save(FilmProduction $production): FilmProduction
    {
        $production->save();
        return $production;
    }

    public function update(FilmProduction $production): FilmProduction
    {
        $production->save();
        return $production;
    }

    public function delete(FilmProduction $production): void
    {
        $production->delete();
    }

    public function getById(int $id): ?FilmProduction
    {
        return FilmProduction::with(['sequences', 'shots'])->find($id);
    }

    public function getAllByUserId(int $userId): Collection
    {
        return FilmProduction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAll(): Collection
    {
        return FilmProduction::orderBy('created_at', 'desc')->get();
    }
}

