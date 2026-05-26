<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrderProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 64)]
        public string $productId,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Regex(
            pattern: '/^\d{1,12}(\.\d{1,2})?$/',
            message: 'This value is not in the expected format.',
        )]
        public string $price,

        #[Assert\Range(min: 1, max: 1_000_000)]
        public int $quantity,
    ) {
    }
}
