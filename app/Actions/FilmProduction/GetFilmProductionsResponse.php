<?php

declare(strict_types=1);

namespace App\Actions\FilmProduction;

use App\Actions\Response;
use Illuminate\Support\Collection;

class GetFilmProductionsResponse implements Response
{
    private Collection $productions;

    public function __construct(Collection $productions)
    {
        $this->productions = $productions;
    }

    public function getResponse(): Collection
    {
        return $this->productions;
    }
}

