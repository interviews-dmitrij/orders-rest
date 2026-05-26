<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class BigDecimalMaxScale extends Constraint
{
    public string $message = 'This value should have at most {{ max }} fractional digits.';

    /**
     * @param array<string>|null $groups
     */
    public function __construct(
        public readonly int $max,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        if (null !== $message) {
            $this->message = $message;
        }
    }
}
