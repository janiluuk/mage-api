<?php

declare(strict_types=1);

namespace App\Actions\FilmProduction;

use App\Repositories\FilmProduction\FilmProductionRepositoryInterface;
use App\Exceptions\FilmProduction\FilmProductionNotFoundException;

final class GetFilmProductionByIdAction
{
    private FilmProductionRepositoryInterface $repository;

    public function __construct(FilmProductionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetFilmProductionByIdRequest $request): GetFilmProductionByIdResponse
    {
        $production = $this->repository->getById($request->getId());

        if (!$production) {
            throw new FilmProductionNotFoundException();
        }

        return new GetFilmProductionByIdResponse($production);
    }
}

