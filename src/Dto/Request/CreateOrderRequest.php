<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Validator\Constraints\BigDecimalGreaterThanOrEqual;
use App\Validator\Constraints\BigDecimalMaxScale;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderRequest
{
    /**
     * @param list<CreateOrderProductRequest> $products
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 64)]
        public string $orderId,

        #[Assert\NotNull]
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
        public DateTimeImmutable $expectedDeliveryDate,

        #[BigDecimalGreaterThanOrEqual('0')]
        #[BigDecimalMaxScale(5)]
        public BigDecimal $totalValue,

        #[Assert\Count(
            min: 1,
            minMessage: 'This collection should contain {{ limit }} element or more.',
        )]
        #[Assert\Valid]
        public array $products,
    ) {
    }
}
