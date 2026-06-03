<?php

namespace App\Exceptions\FilmProduction;

use Exception;

class FilmProductionNotFoundException extends Exception
{
    protected $message = 'Film production not found';
    protected $code = 404;
}

