<?php

namespace App\Services\VideoJobs;

trait GeneratesSeeds
{
    protected function normalizeSeed(int $seed): int
    {
        return $seed > 0 ? $seed : rand(1, 4294967295);
    }
}
