<?php

declare(strict_types=1);

namespace App\Actions\FilmProduction;

final class AddFilmProductionRequest
{
    private ?string $name;
    private ?string $description;
    private ?string $status;
    private ?string $script;
    private ?string $thumbnail;
    private ?array $metadata;

    public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?string $status = null,
        ?string $script = null,
        ?string $thumbnail = null,
        ?array $metadata = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->status = $status;
        $this->script = $script;
        $this->thumbnail = $thumbnail;
        $this->metadata = $metadata;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getScript(): ?string
    {
        return $this->script;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
}

