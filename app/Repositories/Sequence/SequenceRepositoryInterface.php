<?php

namespace App\Repositories\Sequence;

use App\Models\Sequence;
use Illuminate\Support\Collection;

interface SequenceRepositoryInterface
{
    public function save(Sequence $sequence): Sequence;
    public function update(Sequence $sequence): Sequence;
    public function delete(Sequence $sequence): void;
    public function getById(int $id): ?Sequence;
    public function getAllByProductionId(int $productionId): Collection;
}

