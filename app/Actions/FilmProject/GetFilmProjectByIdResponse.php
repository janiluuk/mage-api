<?php

declare(strict_types=1);

namespace App\Actions\FilmProject;

use App\Models\FilmProject;
use App\Actions\Response;

class GetFilmProjectByIdResponse implements Response
{
    private FilmProject $production;

    public function __construct(FilmProject $production)
    {
        $this->production = $production;
    }

    public function getResponse(): FilmProject
    {
        return $this->production;
    }
}

