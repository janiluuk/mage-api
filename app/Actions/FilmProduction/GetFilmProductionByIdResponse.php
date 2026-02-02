<?php

declare(strict_types=1);

namespace App\Actions\FilmProduction;

use App\Models\FilmProduction;
use App\Actions\Response;

class GetFilmProductionByIdResponse implements Response
{
    private FilmProduction $production;

    public function __construct(FilmProduction $production)
    {
        $this->production = $production;
    }

    public function getResponse(): FilmProduction
    {
        return $this->production;
    }
}

