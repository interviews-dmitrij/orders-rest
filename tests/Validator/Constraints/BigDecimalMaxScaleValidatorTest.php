<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\BigDecimalMaxScale;
use App\Validator\Constraints\BigDecimalMaxScaleValidator;
use Brick\Math\BigDecimal;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<BigDecimalMaxScaleValidator>
 */
final class BigDecimalMaxScaleValidatorTest extends ConstraintValidatorTestCase
{
    public function testNullValuePassesPerSymfonyConvention(): void
    {
        $this->validator->validate(null, new BigDecimalMaxScale(5));

        $this->assertNoViolation();
    }

    public function testAcceptsValueWithFewerFractionalDigitsThanMax(): void
    {
        $this->validator->validate(BigDecimal::of('1.5'), new BigDecimalMaxScale(5));

        $this->assertNoViolation();
    }

    public function testAcceptsValueExactlyAtMaxScale(): void
    {
        $this->validator->validate(BigDecimal::of('1.23456'), new BigDecimalMaxScale(5));

        $this->assertNoViolation();
    }

    /**
     * Brick preserves the literal scale of the source string, so `'1.00'` retains scale 2.
     * The validator must see trailing-zero scale as significant — partners that send extra
     * zeros are signalling precision they expect us to honour, and `BigDecimal::of('1.00')`
     * against max=1 must fail just as `'1.23'` does.
     */
    public function testRejectsTrailingZeroScaleExceedingMax(): void
    {
        $this->validator->validate(BigDecimal::of('1.00'), new BigDecimalMaxScale(1));

        $this->buildViolation('This value should have at most {{ max }} fractional digits.')
            ->setParameter('{{ max }}', '1')
            ->setCode(BigDecimalMaxScale::SCALE_EXCEEDED_ERROR)
            ->assertRaised();
    }

    public function testRejectsValueOneScaleAboveMax(): void
    {
        $this->validator->validate(BigDecimal::of('10.123456'), new BigDecimalMaxScale(5));

        $this->buildViolation('This value should have at most {{ max }} fractional digits.')
            ->setParameter('{{ max }}', '5')
            ->setCode(BigDecimalMaxScale::SCALE_EXCEEDED_ERROR)
            ->assertRaised();
    }

    public function testRejectsAnyFractionalDigitWhenMaxIsZero(): void
    {
        $this->validator->validate(BigDecimal::of('5.1'), new BigDecimalMaxScale(0));

        $this->buildViolation('This value should have at most {{ max }} fractional digits.')
            ->setParameter('{{ max }}', '0')
            ->setCode(BigDecimalMaxScale::SCALE_EXCEEDED_ERROR)
            ->assertRaised();
    }

    public function testThrowsWhenValidatedValueIsNotBigDecimal(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('1.234567', new BigDecimalMaxScale(5));
    }

    public function testThrowsWhenDispatchedWithWrongConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(BigDecimal::of('1'), new NotNull());
    }

    protected function createValidator(): BigDecimalMaxScaleValidator
    {
        return new BigDecimalMaxScaleValidator();
    }
}
