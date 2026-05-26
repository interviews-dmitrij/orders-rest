<?php

declare(strict_types=1);

namespace App\Dto\Request;

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

        #[Assert\NotBlank]
        #[Assert\Date(message: 'This value is not a valid date.')]
        public string $expectedDeliveryDate,

        #[Assert\NotBlank]
        #[Assert\Regex(
            pattern: '/^\d{1,12}(\.\d{1,2})?$/',
            message: 'This value is not in the expected format.',
        )]
        public string $totalValue,

        #[Assert\Count(
            min: 1,
            minMessage: 'This collection should contain {{ limit }} element or more.',
        )]
        #[Assert\Valid]
        public array $products,
    ) {
    }
}
