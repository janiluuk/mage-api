<?php

namespace App\Repositories\Sequence;

use App\Models\Sequence;
use App\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class SequenceRepository extends BaseRepository implements SequenceRepositoryInterface
{
    public function save(Sequence $sequence): Sequence
    {
        $sequence->save();
        return $sequence;
    }

    public function update(Sequence $sequence): Sequence
    {
        $sequence->save();
        return $sequence;
    }

    public function delete(Sequence $sequence): void
    {
        $sequence->delete();
    }

    public function getById(int $id): ?Sequence
    {
        return Sequence::with(['shots'])->find($id);
    }

    public function getAllByProductionId(int $productionId): Collection
    {
        return Sequence::where('film_production_id', $productionId)
            ->orderBy('order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }
}

