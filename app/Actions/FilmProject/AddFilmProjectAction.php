<?php

declare(strict_types=1);

namespace App\Actions\FilmProject;

use App\Repositories\FilmProject\FilmProjectRepositoryInterface;
use App\Models\FilmProject;
use Illuminate\Support\Facades\Auth;

final class AddFilmProjectAction
{
    private FilmProjectRepositoryInterface $repository;

    public function __construct(FilmProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(AddFilmProjectRequest $request): AddFilmProjectResponse
    {
        $production = new FilmProject();
        $production->name = $request->getName();
        $production->description = $request->getDescription();
        $production->status = $request->getStatus() ?? 'draft';
        $production->script = $request->getScript();
        $production->thumbnail = $request->getThumbnail();
        $production->user_id = Auth::id();
        $production->metadata = $request->getMetadata();

        $this->repository->save($production);

        return new AddFilmProjectResponse($production);
    }
}

