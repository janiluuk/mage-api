<?php

declare(strict_types=1);

namespace App\Actions\FilmProduction;

use App\Repositories\FilmProduction\FilmProductionRepositoryInterface;
use App\Models\FilmProduction;
use Illuminate\Support\Facades\Auth;

final class AddFilmProductionAction
{
    private FilmProductionRepositoryInterface $repository;

    public function __construct(FilmProductionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(AddFilmProductionRequest $request): AddFilmProductionResponse
    {
        $production = new FilmProduction();
        $production->name = $request->getName();
        $production->description = $request->getDescription();
        $production->status = $request->getStatus() ?? 'draft';
        $production->script = $request->getScript();
        $production->thumbnail = $request->getThumbnail();
        $production->user_id = Auth::id();
        $production->metadata = $request->getMetadata();

        $this->repository->save($production);

        return new AddFilmProductionResponse($production);
    }
}

