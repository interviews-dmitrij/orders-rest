<?php

declare(strict_types=1);

namespace App\UserContext;

final class MockUserContext implements UserContextInterface
{
    public const string MOCK_USER_ID = 'mock-user';

    public function userId(): string
    {
        return self::MOCK_USER_ID;
    }
}
