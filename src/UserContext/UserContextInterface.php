<?php

declare(strict_types=1);

namespace App\UserContext;

interface UserContextInterface
{
    public function userId(): string;
}
