<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Brick\Math\BigDecimal;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class BigDecimalGreaterThanOrEqualValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof BigDecimalGreaterThanOrEqual) {
            throw new UnexpectedTypeException($constraint, BigDecimalGreaterThanOrEqual::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof BigDecimal) {
            throw new UnexpectedValueException($value, BigDecimal::class);
        }

        if ($value->isLessThan(BigDecimal::of($constraint->value))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ compared_value }}', $constraint->value)
                ->setCode('big-decimal-greater-than-or-equal')
                ->addViolation();
        }
    }
}
