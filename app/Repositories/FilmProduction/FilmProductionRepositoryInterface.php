<?php

namespace App\Repositories\FilmProduction;

use App\Models\FilmProduction;
use Illuminate\Support\Collection;

interface FilmProductionRepositoryInterface
{
    public function save(FilmProduction $production): FilmProduction;
    public function update(FilmProduction $production): FilmProduction;
    public function delete(FilmProduction $production): void;
    public function getById(int $id): ?FilmProduction;
    public function getAllByUserId(int $userId): Collection;
    public function getAll(): Collection;
}

