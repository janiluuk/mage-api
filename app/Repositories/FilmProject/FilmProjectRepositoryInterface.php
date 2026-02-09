<?php

namespace App\Repositories\FilmProject;

use App\Models\FilmProject;
use Illuminate\Support\Collection;

interface FilmProjectRepositoryInterface
{
    public function save(FilmProject $production): FilmProject;
    public function update(FilmProject $production): FilmProject;
    public function delete(FilmProject $production): void;
    public function getById(int $id): ?FilmProject;
    public function getAllByUserId(int $userId): Collection;
    public function getAll(): Collection;
}

