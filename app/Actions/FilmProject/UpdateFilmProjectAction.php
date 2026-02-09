<?php

declare(strict_types=1);

namespace App\Actions\FilmProject;

use App\Repositories\FilmProject\FilmProjectRepositoryInterface;
use App\Exceptions\FilmProject\FilmProjectNotFoundException;

final class UpdateFilmProjectAction
{
    private FilmProjectRepositoryInterface $repository;

    public function __construct(FilmProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(UpdateFilmProjectRequest $request): UpdateFilmProjectResponse
    {
        $production = $this->repository->getById($request->getId());

        if (!$production) {
            throw new FilmProjectNotFoundException();
        }

        if ($request->getName() !== null) {
            $production->name = $request->getName();
        }
        if ($request->getDescription() !== null) {
            $production->description = $request->getDescription();
        }
        if ($request->getStatus() !== null) {
            $production->status = $request->getStatus();
        }
        if ($request->getScript() !== null) {
            $production->script = $request->getScript();
        }
        if ($request->getThumbnail() !== null) {
            $production->thumbnail = $request->getThumbnail();
        }
        if ($request->getMetadata() !== null) {
            $production->metadata = $request->getMetadata();
        }

        $this->repository->update($production);

        return new UpdateFilmProjectResponse($production);
    }
}

