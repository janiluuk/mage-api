<?php

namespace App\Exceptions\FilmProject;

use Exception;

class FilmProjectNotFoundException extends Exception
{
    protected $message = 'Film production not found';
    protected $code = 404;
}

