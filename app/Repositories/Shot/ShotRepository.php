<?php

namespace App\Repositories\Shot;

use App\Models\Shot;
use App\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class ShotRepository extends BaseRepository implements ShotRepositoryInterface
{
    public function save(Shot $shot): Shot
    {
        $shot->save();
        return $shot;
    }

    public function update(Shot $shot): Shot
    {
        $shot->save();
        return $shot;
    }

    public function delete(Shot $shot): void
    {
        $shot->delete();
    }

    public function getById(int $id): ?Shot
    {
        return Shot::find($id);
    }

    public function getAllBySequenceId(int $sequenceId): Collection
    {
        return Shot::where('sequence_id', $sequenceId)
            ->orderBy('order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }
}

