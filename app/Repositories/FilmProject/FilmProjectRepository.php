<?php

namespace App\Repositories\FilmProject;

use App\Models\FilmProject;
use App\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class FilmProjectRepository extends BaseRepository implements FilmProjectRepositoryInterface
{
    public function save(FilmProject $production): FilmProject
    {
        $production->save();
        return $production;
    }

    public function update(FilmProject $production): FilmProject
    {
        $production->save();
        return $production;
    }

    public function delete(FilmProject $production): void
    {
        $production->delete();
    }

    public function getById(int $id): ?FilmProject
    {
        return FilmProject::with(['sequences', 'shots'])->find($id);
    }

    public function getAllByUserId(int $userId): Collection
    {
        return FilmProject::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAll(): Collection
    {
        return FilmProject::orderBy('created_at', 'desc')->get();
    }
}

