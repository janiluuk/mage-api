<?php

declare(strict_types=1);

namespace App\Actions\FilmProduction;

use App\Repositories\FilmProduction\FilmProductionRepositoryInterface;
use App\Exceptions\FilmProduction\FilmProductionNotFoundException;

final class UpdateFilmProductionAction
{
    private FilmProductionRepositoryInterface $repository;

    public function __construct(FilmProductionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(UpdateFilmProductionRequest $request): UpdateFilmProductionResponse
    {
        $production = $this->repository->getById($request->getId());

        if (!$production) {
            throw new FilmProductionNotFoundException();
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

        return new UpdateFilmProductionResponse($production);
    }
}

