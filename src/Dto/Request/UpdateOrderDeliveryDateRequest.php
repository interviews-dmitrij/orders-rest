<?php

declare(strict_types=1);

namespace App\Dto\Request;

use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateOrderDeliveryDateRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public DateTimeImmutable $expectedDeliveryDate,
    ) {
    }
}
