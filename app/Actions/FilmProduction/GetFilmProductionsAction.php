<?php

declare(strict_types=1);

namespace App\Actions\FilmProduction;

use App\Repositories\FilmProduction\FilmProductionRepositoryInterface;
use Illuminate\Support\Facades\Auth;

final class GetFilmProductionsAction
{
    private FilmProductionRepositoryInterface $repository;

    public function __construct(FilmProductionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(): GetFilmProductionsResponse
    {
        $productions = $this->repository->getAllByUserId(Auth::id());
        return new GetFilmProductionsResponse($productions);
    }
}

