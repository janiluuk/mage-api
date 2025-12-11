<?php

namespace App\Exceptions;

use Exception;

class SdInstanceUnavailableException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string|null $type The instance type that was requested
     * @return static
     */
    public static function forType(?string $type = null): static
    {
        $message = $type
            ? "No enabled Stable Diffusion instance of type '{$type}' is available"
            : "No enabled Stable Diffusion instance is available";

        return new static($message);
    }
}
