<?php

namespace App\JsonApi\V1\Batches;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use LaravelJsonApi\Eloquent\Contracts\Paginator;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsTo;
use LaravelJsonApi\Eloquent\Fields\Relations\BelongsToMany;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Filters\WhereIdIn;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Schema for Batch (Story) resources
 */
class BatchSchema extends Schema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = Batch::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            BelongsTo::make('user')->type('users')->readOnly(),
            BelongsToMany::make('videoJobs')->type('video-jobs')->readOnly(),
            Str::make('name'),
            Str::make('description'),
            Str::make('status')->sortable(),
            Number::make('total_jobs'),
            Number::make('completed_jobs'),
            Number::make('failed_jobs'),
            Number::make('progress'),
            Number::make('user_id'),
            DateTime::make('started_at')->sortable(),
            DateTime::make('completed_at')->sortable(),
            DateTime::make('created_at')->sortable()->readOnly(),
            DateTime::make('updated_at')->sortable()->readOnly(),
        ];
    }

    /**
     * Get the resource filters.
     *
     * @return array
     */
    public function filters(): array
    {
        return [
            WhereIdIn::make($this),
            Where::make('status'),
            Where::make('user_id'),
        ];
    }

    /**
     * Get the resource paginator.
     *
     * @return Paginator|null
     */
    public function pagination(): ?Paginator
    {
        return PagePagination::make();
    }

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Illuminate\Http\Request|null         $request
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function indexQuery(?Request $request, Builder $query): Builder
    {
        $user = $request->user();
        if (!$user) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    /**
     * Determine if the resource is authorizable.
     *
     * @return bool
     */
    public function authorizable(): bool
    {
        return false;
    }
}

