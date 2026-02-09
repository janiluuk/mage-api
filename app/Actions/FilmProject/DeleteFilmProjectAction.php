<?php

declare(strict_types=1);

namespace App\Actions\FilmProject;

use App\Repositories\FilmProject\FilmProjectRepositoryInterface;
use App\Exceptions\FilmProject\FilmProjectNotFoundException;

final class DeleteFilmProjectAction
{
    private FilmProjectRepositoryInterface $repository;

    public function __construct(FilmProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(DeleteFilmProjectRequest $request): void
    {
        $production = $this->repository->getById($request->getId());

        if (!$production) {
            throw new FilmProjectNotFoundException();
        }

        $this->repository->delete($production);
    }
}

