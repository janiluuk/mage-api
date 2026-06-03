<?php

declare(strict_types=1);

namespace App\Actions\FilmProduction;

use App\Repositories\FilmProduction\FilmProductionRepositoryInterface;
use App\Exceptions\FilmProduction\FilmProductionNotFoundException;

final class DeleteFilmProductionAction
{
    private FilmProductionRepositoryInterface $repository;

    public function __construct(FilmProductionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(DeleteFilmProductionRequest $request): void
    {
        $production = $this->repository->getById($request->getId());

        if (!$production) {
            throw new FilmProductionNotFoundException();
        }

        $this->repository->delete($production);
    }
}

