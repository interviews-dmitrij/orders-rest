<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

interface ApiProblemInterface extends Throwable
{
    public function httpStatus(): int;

    public function title(): string;
}
