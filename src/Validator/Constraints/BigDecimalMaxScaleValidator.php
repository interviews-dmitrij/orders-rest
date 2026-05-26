<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Brick\Math\BigDecimal;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class BigDecimalMaxScaleValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BigDecimalMaxScale) {
            throw new UnexpectedTypeException($constraint, BigDecimalMaxScale::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof BigDecimal) {
            throw new UnexpectedValueException($value, BigDecimal::class);
        }

        if ($value->getScale() > $constraint->max) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ max }}', (string) $constraint->max)
                ->setCode(BigDecimalMaxScale::SCALE_EXCEEDED_ERROR)
                ->addViolation();
        }
    }
}
