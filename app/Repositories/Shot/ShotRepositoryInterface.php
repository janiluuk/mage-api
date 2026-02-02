<?php

namespace App\Repositories\Shot;

use App\Models\Shot;
use Illuminate\Support\Collection;

interface ShotRepositoryInterface
{
    public function save(Shot $shot): Shot;
    public function update(Shot $shot): Shot;
    public function delete(Shot $shot): void;
    public function getById(int $id): ?Shot;
    public function getAllBySequenceId(int $sequenceId): Collection;
}

