<?php

declare(strict_types=1);

namespace App\Constant;
use BenSampo\Enum\Enum;

final class GeneratorType extends Enum
{
    public const GENERATOR_TYPE_VID2VID = 'vid2vid';
    public const GENERATOR_TYPE_DEFORUM = 'deforum';
}