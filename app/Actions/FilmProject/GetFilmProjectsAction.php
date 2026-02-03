<?php

declare(strict_types=1);

namespace App\Actions\FilmProject;

use App\Repositories\FilmProject\FilmProjectRepositoryInterface;
use Illuminate\Support\Facades\Auth;

final class GetFilmProjectsAction
{
    private FilmProjectRepositoryInterface $repository;

    public function __construct(FilmProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function execute(): GetFilmProjectsResponse
    {
        $productions = $this->repository->getAllByUserId(Auth::id());
        return new GetFilmProjectsResponse($productions);
    }
}

